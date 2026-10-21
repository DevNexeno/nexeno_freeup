<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Listener;

use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Accounts\AccountDateService;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCA\NexenoFreeUp\Service\Requests\NoticePeriodService;
use OCA\NexenoFreeUp\Service\Requests\ObsoleteRequestService;
use OCP\Accounts\UserUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedInWithCookieEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class UserSyncListener implements IEventListener {
	public function __construct(
		private StatsCache $statsCache,
		private NoticePeriodService $noticePeriod,
		private ObsoleteRequestService $obsoleteRequests,
		private LicenceHistoryService $licenceHistory,
		private FreeUpRequestMapper $requestMapper,
		private AuditMapper $auditMapper,
		private IGroupManager $groupManager,
		private AccountDateService $accountDates,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		try {
			if (
				$event instanceof UserLoggedInEvent
				|| $event instanceof PostLoginEvent
				|| $event instanceof UserFirstTimeLoggedInEvent
				|| $event instanceof UserLoggedInWithCookieEvent
			) {
				// No cache clearing on login (too frequent); the login is overlaid on the cached user list.
				$loggedIn = method_exists($event, 'getUser') ? $event->getUser() : null;
				if ($loggedIn instanceof IUser) {
					$this->statsCache->recordLogin($loggedIn->getUID());
					$this->noticePeriod->cancelNoticeOnLogin($loggedIn->getUID());
				}
				return;
			}

			if ($event instanceof UserDeletedEvent) {
				$uid = $event->getUid();
				$this->requestMapper->deleteByUserId($uid);
				$this->auditMapper->anonymizeUser($uid);
				$this->statsCache->clearAccountFacts();
				try {
					$this->obsoleteRequests->cleanUpAfterUserDeleted();
				} catch (\Throwable $e) {
					$this->logger->warning('NexenoFreeUp: Error cleaning up after user delete: ' . $e->getMessage());
				}
				// Licence count is re-recorded by the hourly job.
				$this->licenceHistory->markSnapshotDirty();
				return;
			}

			if ($event instanceof UserChangedEvent) {
				$feature = $event->getFeature();
				if (!in_array($feature, ['displayName', 'eMailAddress', 'mail', 'email', 'quota', 'enabled'], true)) {
					return;
				}
			}

			// Other edits (name, email, quota) show up when the cached user facts expire.
			if (
				$event instanceof UserCreatedEvent
				|| ($event instanceof UserChangedEvent && $event->getFeature() === 'enabled')
			) {
				$this->statsCache->clearAccountFacts();
			}

			$user = null;
			if ($event instanceof UserChangedEvent) {
				$user = $event->getUser();
			} elseif ($event instanceof UserUpdatedEvent) {
				$user = $event->getUser();
			} elseif ($event instanceof UserAddedEvent) {
				$user = $event->getUser();
			} elseif ($event instanceof UserRemovedEvent) {
				$user = $event->getUser();
			} elseif ($event instanceof UserCreatedEvent) {
				$user = $event->getUser();
			}

			if ($event instanceof UserCreatedEvent && $user instanceof IUser) {
				$this->accountDates->recordCreated($user->getUID());
			}

			if ($user instanceof IUser) {
				$uid = $user->getUID();
				$existing = $this->requestMapper->findByUserId($uid);
				if ($existing !== null) {
					$displayName = $user->getDisplayName() ?: $uid;
					$email = (string)$user->getSystemEMailAddress();
					$groups = implode(',', $this->groupManager->getUserGroupIds($user));

					$existing->setDisplayName($displayName);
					$existing->setEmail($email);
					$existing->setGroupsList($groups);
					$this->requestMapper->update($existing);
				}
			}

			if (
				$event instanceof UserCreatedEvent
				|| ($event instanceof UserChangedEvent && $event->getFeature() === 'enabled')
			) {
				$this->licenceHistory->markSnapshotDirty();
			}
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Error handling user sync event: ' . $e->getMessage(), [
				'exception' => $e,
				'app' => 'nexeno_freeup',
			]);
		}
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Listener;

use OCA\NexenoFreeUp\AppInfo\Application;
use OCA\NexenoFreeUp\Service\Settings\ApproverService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class NavigationListener implements IEventListener {
	public function __construct(
		private INavigationManager $navigationManager,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ApproverService $approvers,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalEntriesEvent) {
			return;
		}

		try {
			$user = $this->userSession->getUser();
			if ($user === null) {
				return;
			}

			$userId = $user->getUID();
			$isAdmin = $this->groupManager->isAdmin($userId);
			$isApprover = $this->approvers->isUserApprover($userId);

			if (!$isAdmin && !$isApprover) {
				return;
			}

			$this->navigationManager->add([
				'id' => Application::APP_ID,
				'app' => Application::APP_ID,
				'name' => 'Nexeno FreeUp',
				'order' => 51,
				'href' => $this->urlGenerator->linkToRoute('nexeno_freeup.page.index'),
				'icon' => $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
				'type' => 'link',
			]);
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Error dynamically registering navigation: ' . $e->getMessage(), [
				'exception' => $e,
				'app' => Application::APP_ID,
			]);
		}
	}
}

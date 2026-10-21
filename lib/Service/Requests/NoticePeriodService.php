<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Requests;

use DateTime;
use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Duplicates\CleanupService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCA\NexenoFreeUp\Util\AuditText;
use OCP\IL10N;
use OCP\IUserManager;

class NoticePeriodService {
	public function __construct(
		private IL10N $l,
		private FreeUpRequestMapper $requestMapper,
		private AuditMapper $auditMapper,
		private IUserManager $userManager,
		private SettingsService $settings,
		private StatsCache $statsCache,
		private ?NoticeService $noticeService = null,
		private ?CleanupService $cleanupService = null,
	) {
	}

	public function startNoticePeriod(FreeUpRequest $req, string $actorId): FreeUpRequest {
		$user = $this->userManager->get($req->getUserId());
		$archiveOn = (new DateTime())->modify('+' . $this->settings->getNoticeDays() . ' days');
		$req->setNoticeSentAt(new DateTime());
		$req->setArchiveAfter($archiveOn);

		$result = ['emailed' => false, 'notified' => false, 'emailProblem' => 'no_user'];
		if ($user !== null) {
			$result = $this->noticeService->sendArchiveNotice($user, $archiveOn, (int)$req->getId());
		}
		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$meta['notice'] = $result;
		$req->setTargetMeta((string)json_encode($meta));
		$req = $this->requestMapper->update($req);

		$how = $result['emailed'] ? 'By email and notification' : ($result['notified'] ? 'By notification only (no email sent)' : 'Could not be reached');
		$text = AuditText::join($how, 'Disable on ' . AuditText::date($archiveOn));
		$this->auditMapper->log(
			'notice_sent',
			$req->getUserId(),
			$actorId,
			$actorId,
			$text
		);

		return $req;
	}

	public function startCleanupNoticePeriod(FreeUpRequest $req, string $actorId): FreeUpRequest {
		$on = (new DateTime())->modify('+' . $this->settings->getNoticeDays() . ' days');
		$req->setNoticeSentAt(new DateTime());
		$req->setArchiveAfter($on);

		$summary = $this->notifyCleanupOwners($req, $on);

		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$meta['notice'] = $summary;
		$req->setTargetMeta((string)json_encode($meta));
		$req = $this->requestMapper->update($req);

		[$subjectUser, $subjectName] = AuditText::subject($req);
		$this->auditMapper->log(
			'notice_sent',
			$subjectUser,
			$actorId,
			$actorId,
			AuditText::join(
				AuditText::count($summary['owners'], 'owner', 'owners') . ', ' . $summary['emailed'] . ' by email',
				'Replaced on ' . AuditText::date($on)
			),
			$subjectName
		);

		return $req;
	}

	/**
	 * @return array{owners:int,emailed:int,notified:int,noAddress:int,failed:int}
	 */
	private function notifyCleanupOwners(FreeUpRequest $req, DateTime $on): array {
		$owners = $this->cleanupService->affectedOwners($req);
		$summary = ['owners' => count($owners), 'emailed' => 0, 'notified' => 0, 'noAddress' => 0, 'failed' => 0];
		foreach ($owners as $uid => $fileName) {
			$user = $this->userManager->get($uid);
			if ($user === null) {
				continue;
			}
			$res = $this->noticeService->sendCleanupNotice($user, $fileName, $on, (int)$req->getId());
			$summary['emailed'] += $res['emailed'] ? 1 : 0;
			$summary['notified'] += $res['notified'] ? 1 : 0;
			if (!$res['emailed']) {
				$res['emailProblem'] === 'no_address' ? $summary['noAddress']++ : $summary['failed']++;
			}
		}
		return $summary;
	}

	/**
	 * The archive date does not change.
	 *
	 * @return array{people:int,emailed:int,notified:int}
	 */
	public function resendNotice(int $requestId, string $actorId): array {
		$req = $this->requestMapper->findById($requestId);
		$on = $req?->getArchiveAfter();
		if ($req === null || $req->getStatus() !== 'approved' || $on === null || $this->noticeService === null) {
			throw new \InvalidArgumentException($this->l->t('There is no open notice period for this request.'));
		}

		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		if ($req->getRequestType() === 'cleanup') {
			$sum = $this->notifyCleanupOwners($req, $on);
			$result = ['people' => $sum['owners'], 'emailed' => $sum['emailed'], 'notified' => $sum['notified']];
			$meta['notice'] = $sum;
		} else {
			$user = $this->userManager->get($req->getUserId());
			$res = $user ? $this->noticeService->sendArchiveNotice($user, $on, $requestId) : ['emailed' => false, 'notified' => false, 'emailProblem' => 'no_user'];
			$result = ['people' => 1, 'emailed' => $res['emailed'] ? 1 : 0, 'notified' => $res['notified'] ? 1 : 0];
			$meta['notice'] = $res;
		}
		$req->setTargetMeta((string)json_encode($meta));
		$this->requestMapper->update($req);

		[$subjectUser, $subjectName] = AuditText::subject($req);
		$this->auditMapper->log(
			'notice_sent',
			$subjectUser,
			$actorId,
			$actorId,
			'Sent again to ' . AuditText::count($result['people'], 'user', 'users') . ', ' . $result['emailed'] . ' by email',
			$subjectName
		);
		$this->statsCache->clear();

		return $result;
	}

	public function cancelBecauseUserLoggedIn(FreeUpRequest $req, string $actorId): FreeUpRequest {
		$userId = $req->getUserId();
		$req->setStatus('rejected');
		$req->setReviewNotes(trim((string)$req->getReviewNotes() . ' Cancelled: the user logged in after the notice.'));
		$req = $this->requestMapper->update($req);
		$this->noticeService?->dismissArchiveNotice($userId, (int)$req->getId());
		$this->auditMapper->log(
			'archive_cancelled',
			$userId,
			$actorId,
			$actorId,
			'Signed in during the notice period'
		);
		$this->statsCache->clear();
		return $req;
	}

	public function cancelNoticeOnLogin(string $userId): void {
		$req = $this->requestMapper->findByUserId($userId);
		if ($req === null || $req->getStatus() !== 'approved' || $req->getNoticeSentAt() === null) {
			return;
		}
		$this->cancelBecauseUserLoggedIn($req, $userId);
	}
}

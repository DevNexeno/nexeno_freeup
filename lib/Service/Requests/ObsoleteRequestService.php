<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Requests;

use DateTime;
use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Duplicates\DuplicatePruner;
use OCA\NexenoFreeUp\Util\AuditText;
use OCP\IUserManager;

class ObsoleteRequestService {
	public function __construct(
		private FreeUpRequestMapper $requestMapper,
		private DuplicateFileMapper $duplicateMapper,
		private AuditMapper $auditMapper,
		private IUserManager $userManager,
		private DuplicatePruner $pruner,
		private ApproverNotifier $approverNotifier,
		private StatsCache $statsCache,
		private ?NoticeService $noticeService = null,
	) {
	}

	public function isRequestStillValid(FreeUpRequest $req): bool {
		if ($req->getRequestType() === 'account') {
			return $this->userManager->userExists($req->getUserId());
		}
		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		foreach (($meta['checksums'] ?? []) as $checksum) {
			$dup = $this->duplicateMapper->findByChecksum((string)$checksum);
			if ($dup !== null && $dup->getOccurrences() >= 2) {
				return true;
			}
		}
		return false;
	}

	public function markObsolete(FreeUpRequest $req): void {
		$req->setStatus('rejected');
		$req->setReviewedBy('system');
		$req->setReviewedAt(new DateTime());
		$req->setReviewNotes('Cancelled automatically: the account or the files no longer exist.');
		$this->requestMapper->update($req);

		[$subjectUser, $subjectName] = AuditText::subject($req);
		$this->auditMapper->log(
			'request_obsolete',
			$subjectUser,
			'system',
			'System',
			$req->getRequestType() === 'cleanup' ? 'The copies no longer exist' : 'The account no longer exists',
			$subjectName
		);
		$this->approverNotifier->dismissApprovalNotification((int)$req->getId());

		if ($this->noticeService !== null) {
			$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
			foreach (($meta['users'] ?? [$req->getUserId()]) as $uid) {
				$this->noticeService->dismissNotice((string)$uid, 'cleanup_notice', (int)$req->getId());
			}
			$this->noticeService->dismissNotice($req->getUserId(), 'archive_notice', (int)$req->getId());
		}
	}

	/**
	 * @return int number of requests closed
	 */
	public function sweepObsoleteRequests(): int {
		$open = $this->requestMapper->findByStatuses(['proposed', 'approved']);
		if (empty($open)) {
			return 0;
		}
		$this->pruner->pruneStaleDuplicates($this->duplicateMapper->findAllSorted());

		$closed = 0;
		foreach ($open as $req) {
			if (!$this->isRequestStillValid($req)) {
				$this->markObsolete($req);
				$closed++;
			}
		}
		if ($closed > 0) {
			$this->statsCache->clear();
		}
		return $closed;
	}

	public function cleanUpAfterUserDeleted(): void {
		$this->pruner->pruneStaleDuplicates($this->duplicateMapper->findAllSorted());
		$this->sweepObsoleteRequests();
	}
}

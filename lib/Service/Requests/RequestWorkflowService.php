<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Requests;

use DateTime;
use OCA\NexenoFreeUp\BackgroundJob\ArchiveUserJob;
use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Accounts\StorageService;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Duplicates\CleanupService;
use OCA\NexenoFreeUp\Service\Duplicates\DuplicatePruner;
use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCA\NexenoFreeUp\Service\Settings\ApproverService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCA\NexenoFreeUp\Util\AuditText;
use OCA\NexenoFreeUp\Util\Format;
use OCP\BackgroundJob\IJobList;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Account requests disable the account; clean-up requests are carried out by CleanupService.
 */
class RequestWorkflowService {
	public function __construct(
		private IL10N $l,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private FreeUpRequestMapper $requestMapper,
		private DuplicateFileMapper $duplicateMapper,
		private AuditMapper $auditMapper,
		private StorageService $storage,
		private SettingsService $settings,
		private ApproverService $approvers,
		private ApproverNotifier $approverNotifier,
		private NoticePeriodService $noticePeriod,
		private ObsoleteRequestService $obsoleteRequests,
		private DuplicatePruner $pruner,
		private LicenceHistoryService $licenceHistory,
		private StatsCache $statsCache,
		private LoggerInterface $logger,
		private ?IJobList $jobList = null,
		private ?CleanupService $cleanupService = null,
		private ?NoticeService $noticeService = null,
	) {
	}

	/**
	 * @param bool $reasonIsSuggestion the reason is the pre-filled suggestion, left unchanged: it only repeats the facts,
	 *                                 so Recent activity leaves it out (the request still keeps it)
	 */
	public function proposeArchival(string $userId, string $reason, string $proposedBy, bool $reasonIsSuggestion = false): FreeUpRequest {
		$user = $this->userManager->get($userId);
		if (!$user) {
			throw new \InvalidArgumentException($this->l->t('That account no longer exists.'));
		}

		$storageMap = $this->storage->getUserStorageMap();
		$storageBytes = $storageMap[$userId] ?? 0;
		$groups = implode(',', $this->groupManager->getUserGroupIds($user));

		$req = $this->requestMapper->findByUserId($userId);
		if ($req === null) {
			$req = new FreeUpRequest();
			$req->setUserId($userId);
		}

		$req->setDisplayName($user->getDisplayName() ?: $userId);
		$req->setEmail((string)$user->getSystemEMailAddress());
		$req->setGroupsList($groups);
		$req->setLastLogin((int)$user->getLastLogin());
		$req->setStorageBytes($storageBytes);
		$req->setStatus('proposed');
		$req->setProposedBy($proposedBy);
		$req->setProposedAt(new DateTime());
		$req->setProposalReason($reason);

		if ($req->getId() === null) {
			$req = $this->requestMapper->insert($req);
		} else {
			$req = $this->requestMapper->update($req);
		}

		$this->auditMapper->log(
			'propose_archival',
			$userId,
			$proposedBy,
			$proposedBy,
			AuditText::join('Uses ' . Format::bytes($storageBytes), $reasonIsSuggestion ? '' : AuditText::reason($reason))
		);

		$this->approverNotifier->notifyApprovers($req, $proposedBy);

		$this->statsCache->clear();

		return $req;
	}

	public function approveArchival(int $requestId, string $notes, string $reviewedBy): FreeUpRequest {
		$req = $this->requestMapper->findById($requestId);
		if (!$req) {
			throw new \InvalidArgumentException($this->l->t('That request no longer exists.'));
		}

		if ($req->getStatus() !== 'proposed') {
			throw new \InvalidArgumentException($this->l->t('That request is not waiting for approval.'));
		}

		$this->pruner->pruneStaleDuplicates($this->duplicateMapper->findAllSorted());
		if (!$this->obsoleteRequests->isRequestStillValid($req)) {
			$this->obsoleteRequests->markObsolete($req);
			$this->statsCache->clear();
			throw new \InvalidArgumentException($this->l->t('This request is no longer valid because the account or the files no longer exist. It has been removed.'));
		}

		$userId = $req->getUserId();

		$req->setStatus('approved');
		$req->setReviewedBy($reviewedBy);
		$req->setReviewedAt(new DateTime());
		$req->setReviewNotes($notes);
		$req = $this->requestMapper->update($req);

		[$subjectUser, $subjectName] = AuditText::subject($req);
		$this->auditMapper->log(
			'approve_archival',
			$subjectUser,
			$reviewedBy,
			$reviewedBy,
			AuditText::note($notes),
			$subjectName
		);

		$this->approverNotifier->dismissApprovalNotification($requestId);

		if ($this->settings->isNoticeEnabled() && $this->noticeService !== null) {
			if ($req->getRequestType() === 'account') {
				$req = $this->noticePeriod->startNoticePeriod($req, $reviewedBy);
			} elseif ($this->cleanupService !== null) {
				$req = $this->noticePeriod->startCleanupNoticePeriod($req, $reviewedBy);
			}
		}

		// Without a notice period nobody is waiting to react, so there is nothing to schedule.
		// The queued job below stays as the retry when this fails.
		if (!$this->settings->isNoticeEnabled()) {
			try {
				$req = $this->executeArchivalJob($requestId, $userId, $reviewedBy);
				$this->statsCache->clear();
				return $req;
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Approved request #' . $requestId . ' could not run right away, the background job will retry: ' . $e->getMessage());
			}
		}

		if ($this->jobList !== null) {
			try {
				$this->jobList->add(ArchiveUserJob::class, [
					'requestId' => $requestId,
					'userId' => $userId,
					'actorId' => $reviewedBy,
				]);
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Could not enqueue ArchiveUserJob: ' . $e->getMessage());
			}
		}

		$this->statsCache->clear();

		return $req;
	}

	/**
	 * Each request is processed on its own, so one failure never blocks the others.
	 *
	 * @param int[] $requestIds
	 * @param callable(int):bool $canDecide whether the current person may decide on a request
	 * @return array{done:int,failed:array<int,array{id:int,error:string}>}
	 */
	public function decideMany(array $requestIds, string $decision, string $notes, string $reviewedBy, callable $canDecide): array {
		$done = 0;
		$failed = [];
		foreach (array_values(array_unique(array_map('intval', $requestIds))) as $id) {
			try {
				if (!$canDecide($id)) {
					throw new \InvalidArgumentException($this->approvers->denyMessageForRequest($id));
				}
				if ($decision === 'approve') {
					$this->approveArchival($id, $notes, $reviewedBy);
				} else {
					$this->rejectProposal($id, $notes, $reviewedBy);
				}
				$done++;
			} catch (\Throwable $e) {
				$failed[] = ['id' => $id, 'error' => $e->getMessage()];
			}
		}
		return ['done' => $done, 'failed' => $failed];
	}

	/**
	 * Without `$requestIds`: every request that is due (those inside their notice period stay queued).
	 * With `$requestIds`: exactly those, skipping the notice period (an admin's explicit choice).
	 *
	 * @return array{processed:int,accounts:int,cleanups:int,waiting:int,cancelled:int}
	 */
	public function runApprovedArchives(string $actorId, ?array $requestIds = null, ?int $maxItems = null): array {
		$this->obsoleteRequests->sweepObsoleteRequests();
		$accounts = 0;
		$cleanups = 0;
		$waiting = 0;
		$cancelled = 0;
		$only = $requestIds === null ? null : array_map('intval', $requestIds);
		foreach ($this->requestMapper->findByStatuses(['approved']) as $req) {
			if ($only !== null && !in_array((int)$req->getId(), $only, true)) {
				continue;
			}
			try {
				// Items picked by hand skip the notice period; the rest only run when due.
				$done = $this->executeArchivalJob((int)$req->getId(), (string)$req->getUserId(), $actorId, $only !== null);
				if ($done->getStatus() === 'archived') {
					$done->getRequestType() === 'cleanup' ? $cleanups++ : $accounts++;
				} elseif ($done->getStatus() === 'approved') {
					$waiting++;
				} else {
					$cancelled++;
				}
				if ($maxItems !== null && ($accounts + $cleanups + $cancelled) >= $maxItems) {
					break;
				}
			} catch (\Throwable $e) {
				$this->logger->error('NexenoFreeUp: Archive run failed for request #' . $req->getId() . ': ' . $e->getMessage());
			}
		}
		$this->statsCache->clear();
		return ['processed' => $accounts + $cleanups, 'accounts' => $accounts, 'cleanups' => $cleanups, 'waiting' => $waiting, 'cancelled' => $cancelled];
	}

	public function executeArchivalJob(int $requestId, string $userId, string $actorId, bool $skipNotice = false): FreeUpRequest {
		$req = $this->requestMapper->findById($requestId);
		if (!$req) {
			$req = $this->requestMapper->findByUserId($userId);
		}
		if (!$req) {
			throw new \InvalidArgumentException($this->l->t('That request no longer exists.'));
		}

		// Only approved requests may run; keeps the job and "run now" from executing twice.
		if ($req->getStatus() !== 'approved') {
			return $req;
		}

		$archiveAfter = $req->getArchiveAfter();
		if (!$skipNotice && $archiveAfter !== null && $archiveAfter > new DateTime()) {
			return $req;
		}
		if ($skipNotice && $archiveAfter !== null && $archiveAfter > new DateTime()) {
			[$subjectUser, $subjectName] = AuditText::subject($req);
			$this->auditMapper->log(
				'notice_skipped',
				$subjectUser,
				$actorId,
				$actorId,
				'Planned for ' . AuditText::date($archiveAfter),
				$subjectName
			);
		}

		if ($req->getRequestType() === 'cleanup') {
			if ($this->cleanupService === null) {
				throw new \RuntimeException('Clean-up service unavailable.');
			}
			$req = $this->cleanupService->execute($req, $actorId);
			if ($req->getStatus() === 'archived') {
				$req = $this->tellCleanupOwners($req);
			}
			$this->statsCache->clear();
			return $req;
		}

		// The person logged in after being told: they still use the account, so it stays.
		$noticeSentAt = $req->getNoticeSentAt();
		if ($noticeSentAt !== null) {
			$account = $this->userManager->get($userId);
			if ($account !== null && (int)$account->getLastLogin() >= $noticeSentAt->getTimestamp()) {
				$req = $this->noticePeriod->cancelBecauseUserLoggedIn($req, $actorId);
				return $req;
			}
		}

		if (!$this->userManager->userExists($userId)) {
			$this->obsoleteRequests->markObsolete($req);
			$this->statsCache->clear();
			return $this->requestMapper->findById((int)$req->getId()) ?? $req;
		}

		$req->setStatus('archiving');
		$this->requestMapper->update($req);

		$this->auditMapper->log(
			'archive_started',
			$userId,
			$actorId,
			$actorId,
			''
		);

		$user = $this->userManager->get($userId);
		if ($user) {
			$user->setEnabled(false);
		}

		$req->setStatus('archived');
		$req->setArchivedAt(new DateTime());
		$req = $this->requestMapper->update($req);

		$storageFormatted = Format::bytes($req->getStorageBytes());
		$this->auditMapper->log(
			'archive_completed',
			$userId,
			$actorId,
			$actorId,
			AuditText::join('Sign-in blocked', '1 licence freed', $storageFormatted . ' of files kept')
		);

		// Email only: the account is disabled, so a notification would never be read.
		if ($user !== null && $this->noticeService !== null) {
			$res = $this->noticeService->sendArchivedMessage($user, (int)$req->getId());
			$req = $this->saveDoneNotice($req, ['emailed' => $res['emailed'], 'emailProblem' => $res['emailProblem']]);
		}

		try {
			$this->licenceHistory->captureSnapshot();
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Snapshot capture after archival: ' . $e->getMessage());
		}

		$this->statsCache->clear();

		return $req;
	}

	private function tellCleanupOwners(FreeUpRequest $req): FreeUpRequest {
		if ($this->noticeService === null) {
			return $req;
		}
		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$summary = ['owners' => 0, 'emailed' => 0, 'notified' => 0, 'noAddress' => 0, 'failed' => 0];
		foreach (($meta['replacedOwners'] ?? []) as $ownerId => $fileName) {
			$owner = $this->userManager->get((string)$ownerId);
			if ($owner === null) {
				continue;
			}
			$res = $this->noticeService->sendCleanupDoneMessage($owner, (string)$fileName, (int)$req->getId());
			$summary['owners']++;
			$summary['emailed'] += $res['emailed'] ? 1 : 0;
			$summary['notified'] += $res['notified'] ? 1 : 0;
			if (!$res['emailed']) {
				$res['emailProblem'] === 'no_address' ? $summary['noAddress']++ : $summary['failed']++;
			}
		}
		return $this->saveDoneNotice($req, $summary);
	}

	private function saveDoneNotice(FreeUpRequest $req, array $doneNotice): FreeUpRequest {
		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$meta['doneNotice'] = $doneNotice;
		$req->setTargetMeta((string)json_encode($meta));
		return $this->requestMapper->update($req);
	}

	public function rejectProposal(int $requestId, string $notes, string $reviewedBy): FreeUpRequest {
		$req = $this->requestMapper->findById($requestId);
		if (!$req) {
			throw new \InvalidArgumentException($this->l->t('That request no longer exists.'));
		}

		if ($req->getStatus() !== 'proposed') {
			throw new \InvalidArgumentException($this->l->t('That request was already decided.'));
		}

		$req->setStatus('rejected');
		$req->setReviewedBy($reviewedBy);
		$req->setReviewedAt(new DateTime());
		$req->setReviewNotes($notes);

		$req = $this->requestMapper->update($req);

		[$subjectUser, $subjectName] = AuditText::subject($req);
		$this->auditMapper->log(
			'reject_proposal',
			$subjectUser,
			$reviewedBy,
			$reviewedBy,
			AuditText::note($notes),
			$subjectName
		);

		$this->approverNotifier->dismissApprovalNotification($requestId);

		$this->statsCache->clear();

		return $req;
	}

	/**
	 * @return array{emailed:bool, notified:bool, emailProblem:?string}|null null when no message service is available
	 */
	public function restoreUser(string $userId, string $actorId): ?array {
		$user = $this->userManager->get($userId);
		if (!$user) {
			throw new \InvalidArgumentException($this->l->t('That account no longer exists.'));
		}

		$user->setEnabled(true);

		// An account disabled directly in Nextcloud has no FreeUp request to close; restoring it just enables it.
		$req = $this->requestMapper->findByUserId($userId);
		if ($req !== null && !in_array($req->getStatus(), ['archived', 'archiving'], true)) {
			$req = null;
		}
		if ($req !== null) {
			$req->setStatus('restored');
			$this->requestMapper->update($req);
		}

		$told = $this->noticeService?->sendRestoredMessage($user, (int)($req?->getId() ?? 0));
		if ($req !== null && $told !== null) {
			$this->saveRestoreNotice($req, $told);
		}

		$this->auditMapper->log(
			'restore_user',
			$userId,
			$actorId,
			$actorId,
			'Can sign in again'
		);

		$this->statsCache->clear();
		return $told;
	}

	private function saveRestoreNotice(FreeUpRequest $req, array $told): void {
		$meta = json_decode((string)$req->getTargetMeta(), true) ?: [];
		$meta['restoreNotice'] = $told;
		$req->setTargetMeta((string)json_encode($meta));
		$this->requestMapper->update($req);
	}
}

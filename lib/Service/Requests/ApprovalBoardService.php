<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Requests;

use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Accounts\AccountReportService;
use OCA\NexenoFreeUp\Service\Accounts\StorageService;
use OCA\NexenoFreeUp\Service\Settings\ApproverService;
use OCP\IUserManager;

class ApprovalBoardService {
	public function __construct(
		private FreeUpRequestMapper $requestMapper,
		private IUserManager $userManager,
		private ObsoleteRequestService $obsoleteRequests,
		private AccountReportService $accountReport,
		private StorageService $storage,
		private ApproverService $approvers,
	) {
	}

	public function getPendingApprovals(): array {
		return $this->getApprovalBoard()['proposed'];
	}

	/**
	 * @return array{proposed:array,queued:array,completed:array}
	 */
	public function getApprovalBoard(?string $viewerId = null): array {
		$this->obsoleteRequests->sweepObsoleteRequests();
		$accounts = [];
		foreach ($this->accountReport->getAccountsReport() as $a) {
			$accounts[$a['userId']] = $a;
		}
		$storageMap = $this->storage->getUserStorageMap();
		$now = time();
		$approversByType = ['account' => $this->approvers->getApproverLabel('account'), 'cleanup' => $this->approvers->getApproverLabel('cleanup')];
		$viewerCan = ['account' => $this->approvers->canApprove($viewerId, 'account'), 'cleanup' => $this->approvers->canApprove($viewerId, 'cleanup')];

		$format = function (FreeUpRequest $r) use ($accounts, $storageMap, $now, $approversByType, $viewerCan): array {
			$base = $r->jsonSerialize();
			$base['canDecide'] = $viewerCan[$r->getRequestType() === 'cleanup' ? 'cleanup' : 'account'];
			if ($r->getRequestType() === 'cleanup') {
				$meta = $base['targetMeta'] ?? [];
				$copies = (int)($meta['copies'] ?? 0);
				$bytes = $r->getStatus() === 'archived' ? (int)($meta['freedBytes'] ?? $r->getStorageBytes()) : (int)($meta['wastedBytes'] ?? $r->getStorageBytes());
				return array_merge($base, [
					'kind' => 'cleanup',
					'copies' => $copies,
					'users' => $meta['users'] ?? [],
					'noticeOwners' => (int)($meta['notice']['owners'] ?? 0),
					'noticeEmailed' => (int)($meta['notice']['emailed'] ?? 0),
					'noticeNoAddress' => (int)($meta['notice']['noAddress'] ?? 0),
					'noticeFailed' => (int)($meta['notice']['failed'] ?? 0),
					'keeper' => $meta['keeper'] ?? null,
					'doneOwners' => isset($meta['doneNotice']) ? (int)($meta['doneNotice']['owners'] ?? 0) : null,
					'doneEmailed' => (int)($meta['doneNotice']['emailed'] ?? 0),
					'doneNoAddress' => (int)($meta['doneNotice']['noAddress'] ?? 0),
					'doneFailed' => (int)($meta['doneNotice']['failed'] ?? 0),
					'removedCopies' => (int)($meta['removedCopies'] ?? 0),
					'goneCopies' => (int)($meta['goneCopies'] ?? 0),
					'storageBytes' => $bytes,
					'approver' => $approversByType['cleanup'],
					'canRestore' => false,
				]);
			}

			$uid = $r->getUserId();
			$acc = $accounts[$uid] ?? null;
			$storage = $r->getStatus() === 'proposed' ? ($storageMap[$uid] ?? $r->getStorageBytes()) : $r->getStorageBytes();
			$lastLogin = $acc['lastLogin'] ?? $r->getLastLogin();
			$isNever = ((int)$lastLogin <= 0);
			$months = $isNever ? -1 : (int)round(max(0, $now - (int)$lastLogin) / (30.416 * 86400));
			$type = $acc['type'] ?? 'User';

			return array_merge($base, [
				'kind' => 'account',
				'displayName' => $acc['displayName'] ?? ($r->getDisplayName() ?: $uid),
				'email' => $acc['email'] ?? (string)$r->getEmail(),
				'accountType' => $type,
				'users' => [$uid],
				'storageBytes' => $storage,
				'inactiveMonths' => $months,
				'isNeverLoggedIn' => $isNever,
				'createdAt' => $acc['createdAt'] ?? 0,
				'approver' => $approversByType['account'],
				'userMissing' => $this->userManager->get($uid) === null,
				'noticeProblem' => $base['targetMeta']['notice']['emailProblem'] ?? null,
				'noticeEmailed' => (bool)($base['targetMeta']['notice']['emailed'] ?? false),
				'noticeNotified' => (bool)($base['targetMeta']['notice']['notified'] ?? false),
				'doneEmailed' => (bool)($base['targetMeta']['doneNotice']['emailed'] ?? false),
				'doneProblem' => $base['targetMeta']['doneNotice']['emailProblem'] ?? null,
				'canRestore' => ($this->userManager->get($uid) !== null),
			]);
		};

		return [
			'proposed' => array_map($format, array_reverse($this->requestMapper->findByStatuses(['proposed']))),
			'queued' => array_map($format, array_reverse($this->requestMapper->findByStatuses(['approved', 'archiving']))),
			'completed' => array_map($format, $this->requestMapper->findByStatuses(['archived'], 100)),
		];
	}
}

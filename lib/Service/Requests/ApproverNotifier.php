<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Requests;

use OCA\NexenoFreeUp\Db\FreeUpRequest;
use OCA\NexenoFreeUp\Service\Settings\ApproverService;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class ApproverNotifier {
	public function __construct(
		private ApproverService $approvers,
		private LoggerInterface $logger,
		private ?INotificationManager $notificationManager = null,
	) {
	}

	public function notifyApprovers(FreeUpRequest $req, string $proposedBy): void {
		if ($this->notificationManager === null) {
			return;
		}

		$targetUserId = $req->getUserId();
		$isCleanup = $req->getRequestType() === 'cleanup';
		$approverUserIds = $this->approvers->getApproverUserIds($isCleanup ? 'cleanup' : 'account');
		$displayName = $isCleanup ? (string)$req->getTargetName() : ($req->getDisplayName() ?: $targetUserId);

		foreach ($approverUserIds as $approverId) {
			try {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp('nexeno_freeup')
					->setUser($approverId)
					->setDateTime(new \DateTime())
					->setObject('request', (string)$req->getId())
					->setSubject($isCleanup ? 'cleanup_proposal_submitted' : 'archival_proposal_submitted', [
						'requestId' => $req->getId(),
						'userId' => $targetUserId,
						'displayName' => $displayName,
						'proposedBy' => $proposedBy,
					]);

				$this->notificationManager->notify($notification);
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Failed to send notification to approver ' . $approverId . ': ' . $e->getMessage());
			}
		}
	}

	public function notifyApproversOfBatch(int $count, string $proposedBy): void {
		if ($this->notificationManager === null) {
			return;
		}
		foreach ($this->approvers->getApproverUserIds('cleanup') as $approverId) {
			try {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp('nexeno_freeup')
					->setUser($approverId)
					->setDateTime(new \DateTime())
					->setObject('cleanup_batch', (string)time())
					->setSubject('cleanup_batch_submitted', ['count' => $count, 'proposedBy' => $proposedBy]);
				$this->notificationManager->notify($notification);
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Failed to notify approver ' . $approverId . ': ' . $e->getMessage());
			}
		}
	}

	public function dismissApprovalNotification(int $requestId): void {
		if ($this->notificationManager === null) {
			return;
		}
		try {
			$approverUserIds = array_unique(array_merge($this->approvers->getApproverUserIds('account'), $this->approvers->getApproverUserIds('cleanup')));
			foreach ($approverUserIds as $approverId) {
				try {
					$notification = $this->notificationManager->createNotification();
					$notification->setApp('nexeno_freeup')
						->setUser($approverId)
						->setObject('request', (string)$requestId);
					$this->notificationManager->markProcessed($notification);
				} catch (\Throwable $e) {
					// non-critical
				}
			}
		} catch (\Throwable $e) {
			// non-critical
		}
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\BackgroundJob;

use OCA\NexenoFreeUp\Service\Requests\RequestWorkflowService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class ArchiveUserJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private RequestWorkflowService $workflow,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	protected function run($argument): void {
		$requestId = (int)($argument['requestId'] ?? 0);
		$userId = (string)($argument['userId'] ?? '');
		$actorId = (string)($argument['actorId'] ?? 'system');

		if ($requestId <= 0 && empty($userId)) {
			$this->logger->error('NexenoFreeUp: ArchiveUserJob missing requestId and userId.');
			return;
		}

		try {
			$this->logger->info("NexenoFreeUp: ArchiveUserJob starting for user {$userId} (request #{$requestId}).");
			$this->workflow->executeArchivalJob($requestId, $userId, $actorId);
			$this->logger->info("NexenoFreeUp: ArchiveUserJob completed for user {$userId}.");
		} catch (\Throwable $e) {
			$this->logger->error("NexenoFreeUp: ArchiveUserJob failed for user {$userId}: " . $e->getMessage(), [
				'exception' => $e,
				'requestId' => $requestId,
				'userId' => $userId,
			]);
		}
	}
}

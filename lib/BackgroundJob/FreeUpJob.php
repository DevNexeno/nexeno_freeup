<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\BackgroundJob;

use OCA\NexenoFreeUp\Service\AuditTrailService;
use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCA\NexenoFreeUp\Service\Requests\RequestWorkflowService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class FreeUpJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private LicenceHistoryService $licenceHistory,
		private AuditTrailService $auditTrail,
		private RequestWorkflowService $workflow,
		private SettingsService $settings,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(86400);
		// Heavy (counts every account): let Nextcloud run it in a quiet period.
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$this->settings->recordHeartbeat('daily');
		try {
			$this->licenceHistory->captureSnapshot();
			$this->auditTrail->pruneExpiredRetentionData();
			$this->workflow->runApprovedArchives('system');
			$this->logger->info('NexenoFreeUp: Successfully captured monthly snapshot and executed GDPR retention cleanup.');
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Background job failed: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\BackgroundJob;

use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCA\NexenoFreeUp\Service\Requests\RequestWorkflowService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/** Hourly: archives approved accounts and replaces approved duplicate copies once the notice period ends. */
class ArchiveDueJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private RequestWorkflowService $workflow,
		private SettingsService $settings,
		private LicenceHistoryService $licenceHistory,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(3600);
	}

	protected function run($argument): void {
		$this->settings->recordHeartbeat('archive');
		try {
			$result = $this->workflow->runApprovedArchives('system');
			if ($result['processed'] > 0 || $result['cancelled'] > 0) {
				$this->logger->info('NexenoFreeUp: Hourly archive run finished.', ['result' => $result]);
			}
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Hourly archive run failed: ' . $e->getMessage(), ['exception' => $e]);
		}

		try {
			$this->licenceHistory->refreshSnapshotIfDirty();
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Could not refresh the licence snapshot: ' . $e->getMessage());
		}
	}
}

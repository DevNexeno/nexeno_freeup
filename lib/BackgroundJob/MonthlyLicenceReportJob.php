<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\BackgroundJob;

use OCA\NexenoFreeUp\Service\Licences\LicenceReportService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/** Daily check; the report goes out on the first run of a new month. */
class MonthlyLicenceReportJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private LicenceReportService $reportService,
		private SettingsService $settings,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(86400);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$this->settings->recordHeartbeat('report');
		try {
			$this->reportService->sendIfDue();
		} catch (\Throwable $e) {
			$this->logger->error('NexenoFreeUp: Monthly licence report failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}

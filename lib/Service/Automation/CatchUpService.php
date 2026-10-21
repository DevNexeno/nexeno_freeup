<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Automation;

use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\AuditTrailService;
use OCA\NexenoFreeUp\Service\Licences\LicenceReportService;
use OCA\NexenoFreeUp\Service\Requests\RequestWorkflowService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use Psr\Log\LoggerInterface;

/**
 * Safety net for servers where Nextcloud's background jobs run late or not at all (no system cron).
 * When an approver opens FreeUp and a job is overdue, the due work is done there and then, a few items at a time.
 * Everything it calls is safe to repeat, so a job and a catch-up can never do the same work twice.
 */
class CatchUpService {
	/** The archive job runs hourly; this much silence means it is late. */
	private const ARCHIVE_LATE_AFTER = 3900;
	/** The report and clean-up jobs run daily. */
	private const DAILY_LATE_AFTER = 93600;
	/** At most one catch-up check per this many seconds, however many people have FreeUp open. */
	private const THROTTLE = 300;
	/** Items processed per catch-up, so a page request never runs for long. */
	private const BATCH = 10;

	public function __construct(
		private SettingsService $settings,
		private FreeUpRequestMapper $requests,
		private RequestWorkflowService $workflow,
		private LicenceReportService $report,
		private AuditTrailService $audit,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{accounts:int,cleanups:int}
	 */
	public function catchUp(): array {
		$done = ['accounts' => 0, 'cleanups' => 0];
		$now = time();
		if ($now - $this->settings->getCatchUp('run') < self::THROTTLE) {
			return $done;
		}
		$this->settings->recordCatchUp('run');

		if ($now - $this->settings->getHeartbeat('archive') > self::ARCHIVE_LATE_AFTER && $this->requests->countDueApproved() > 0) {
			try {
				$result = $this->workflow->runApprovedArchives('system', null, self::BATCH);
				$done = ['accounts' => $result['accounts'], 'cleanups' => $result['cleanups']];
			} catch (\Throwable $e) {
				$this->logger->error('NexenoFreeUp: Catch-up archive run failed: ' . $e->getMessage(), ['exception' => $e]);
			}
		}

		if ($now - $this->settings->getHeartbeat('report') > self::DAILY_LATE_AFTER && $now - $this->settings->getCatchUp('report') > 3600) {
			$this->settings->recordCatchUp('report');
			try {
				$this->report->sendIfDue();
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Catch-up licence report failed: ' . $e->getMessage());
			}
		}

		if ($now - $this->settings->getHeartbeat('daily') > self::DAILY_LATE_AFTER && $now - $this->settings->getCatchUp('daily') > 86400) {
			$this->settings->recordCatchUp('daily');
			try {
				$this->audit->pruneExpiredRetentionData();
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Catch-up retention clean-up failed: ' . $e->getMessage());
			}
		}

		return $done;
	}
}

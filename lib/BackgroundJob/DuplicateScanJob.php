<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\BackgroundJob;

use OCA\NexenoFreeUp\Service\Duplicates\DuplicateScanService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/** Runs the scan outside the web request, since comparing file contents can take minutes. */
class DuplicateScanJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private DuplicateScanService $duplicateScanner,
	) {
		parent::__construct($time);
	}

	protected function run($argument): void {
		$this->duplicateScanner->runQueuedDuplicateScan((int)($argument['minBytes'] ?? 1), isset($argument['by']) ? (string)$argument['by'] : null);
	}
}

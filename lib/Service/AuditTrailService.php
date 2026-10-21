<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service;

use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Db\SnapshotMapper;
use OCA\NexenoFreeUp\Service\Licences\LicenceHistoryService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class AuditTrailService {
	public function __construct(
		private AuditMapper $auditMapper,
		private SnapshotMapper $snapshotMapper,
		private FreeUpRequestMapper $requestMapper,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	public function getAuditLog(int $limit = 500): array {
		$entries = $this->auditMapper->findRecent($limit);
		return array_map(fn ($e) => $e->jsonSerialize(), $entries);
	}

	/**
	 * GDPR storage limitation (Art. 5(1)(e)).
	 */
	public function pruneExpiredRetentionData(): array {
		$auditRetentionDays = (int)$this->config->getAppValue('nexeno_freeup', 'audit_retention_days', '365');
		if ($auditRetentionDays < 30) {
			$auditRetentionDays = 365;
		}

		$purgedAudit = $this->auditMapper->purgeOlderThan($auditRetentionDays);
		$purgedSnapshots = $this->snapshotMapper->purgeOutsideWindow(LicenceHistoryService::HISTORY_RETENTION_MONTHS);
		$purgedRequests = $this->requestMapper->purgeResolvedOlderThan($auditRetentionDays);

		$this->logger->info('NexenoFreeUp: GDPR Retention Cleanup executed', [
			'purgedAuditEntries' => $purgedAudit,
			'purgedSnapshots' => $purgedSnapshots,
			'purgedResolvedRequests' => $purgedRequests,
			'auditRetentionDays' => $auditRetentionDays,
		]);

		return [
			'purgedAudit' => $purgedAudit,
			'purgedSnapshots' => $purgedSnapshots,
			'purgedRequests' => $purgedRequests,
		];
	}
}

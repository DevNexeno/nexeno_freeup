<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Accounts;

use OCA\NexenoFreeUp\Db\AuditMapper;
use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Db\FreeUpRequestMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCA\NexenoFreeUp\Service\Duplicates\DuplicateScanService;
use OCA\NexenoFreeUp\Service\Settings\SettingsService;
use OCP\IConfig;

class OverviewService {
	public function __construct(
		private AccountReportService $accountReport,
		private StorageService $storage,
		private SettingsService $settings,
		private DuplicateScanService $duplicateScanner,
		private DuplicateFileMapper $duplicateMapper,
		private AuditMapper $auditMapper,
		private FreeUpRequestMapper $requestMapper,
		private IConfig $config,
		private StatsCache $statsCache,
	) {
	}

	public function getDashboardOverview(bool $forceRefresh = false): array {
		if (!$forceRefresh) {
			$cached = $this->statsCache->getArray(StatsCache::OVERVIEW, true);
			if ($cached !== null) {
				return $cached;
			}
		}

		$accounts = $this->accountReport->getAccountsReport([], $forceRefresh);

		$totalAccounts = count($accounts);
		$activeAccounts = 0;
		$inactiveCandidates = 0;
		$pendingApprovals = 0;
		$proposedAccounts = 0;
		$queuedArchives = 0;
		$archivedAccounts = 0;
		$disabledAccounts = 0;

		$totalStorageBytes = 0;
		$inactiveStorageBytes = 0;
		$reclaimedStorageBytes = 0;
		$disabledStorageBytes = 0;

		$thresholdMonths = $this->settings->getInactivityThresholdMonths();

		foreach ($accounts as $acc) {
			$totalStorageBytes += $acc['storageBytes'];

			if (!$acc['enabled'] || $acc['status'] === 'archived' || $acc['status'] === 'archiving' || $acc['status'] === 'disabled') {
				if ($acc['status'] === 'archived' || $acc['status'] === 'archiving') {
					$archivedAccounts++;
					$reclaimedStorageBytes += $acc['storageBytes'];
				} else {
					$disabledAccounts++;
					$disabledStorageBytes += $acc['storageBytes'];
				}
				continue;
			}

			if ($acc['status'] === 'proposed') {
				$proposedAccounts++;
				$pendingApprovals++;
			} elseif ($acc['status'] === 'approved') {
				$queuedArchives++;
				$pendingApprovals++;
			}

			if (!$acc['isExempt'] && $acc['status'] !== 'exempt') {
				if ($acc['status'] === 'candidate' || ((float)($acc['inactiveMonths'] ?? 0)) >= $thresholdMonths) {
					$inactiveCandidates++;
					$inactiveStorageBytes += $acc['storageBytes'];
				} else {
					$activeAccounts++;
				}
			} else {
				$activeAccounts++;
			}
		}

		$duplicateWastedBytes = $this->duplicateMapper->sumWastedBytes();
		$duplicateCount = $this->duplicateMapper->countDuplicates();
		$extraCopies = $this->duplicateMapper->sumExtraCopies();

		$recentAuditEntries = [];
		try {
			$recentAudit = $this->auditMapper->findRecent(6);
			$recentAuditEntries = array_map(function ($a) {
				return [
					'id' => $a->getId(),
					'action' => $a->getAction(),
					'targetUser' => $a->getTargetUser(),
					'targetName' => $a->getTargetName(),
					'actorName' => $a->getActorName() ?? $a->getActorId(),
					'details' => $a->getDetails(),
					'createdAt' => $a->getCreatedAt()?->format(\DateTime::ATOM),
				];
			}, $recentAudit);
		} catch (\Throwable $e) {
			// Non-critical
		}

		$capacityBytes = max($this->storage->getStorageCapacityBytes(), $totalStorageBytes);
		$instanceName = $this->config->getAppValue('nexeno_freeup', 'organization_name', $this->config->getAppValue('theming', 'name', 'Nextcloud'));

		$overview = [
			'instanceName' => !empty($instanceName) ? $instanceName : 'Nextcloud',
			'totalAccounts' => $totalAccounts,
			'activeAccounts' => $activeAccounts,
			'inactiveCandidates' => $inactiveCandidates,
			'disabledAccounts' => $disabledAccounts,
			'pendingApprovals' => $this->requestMapper->countPending(),
			'openApprovals' => $this->requestMapper->countPending(),
			'queuedArchives' => $this->requestMapper->countApproved(),
			'archivedAccounts' => $archivedAccounts,
			'licencesInUse' => $activeAccounts + $inactiveCandidates,
			'activeLicences' => $activeAccounts + $inactiveCandidates,
			'totalStorageBytes' => $totalStorageBytes,
			// Every account falls in exactly one of these four, so they add up to the total used.
			'activeStorageBytes' => max(0, $totalStorageBytes - $inactiveStorageBytes - $reclaimedStorageBytes - $disabledStorageBytes),
			'disabledStorageBytes' => $disabledStorageBytes,
			'storageCapacityBytes' => $capacityBytes,
			'storageFreeBytes' => max(0, $capacityBytes - $totalStorageBytes),
			'inactiveStorageBytes' => $inactiveStorageBytes,
			'reclaimedStorageBytes' => $reclaimedStorageBytes,
			'duplicateWastedBytes' => $duplicateWastedBytes,
			'duplicateCount' => $duplicateCount,
			'extraCopiesCount' => $extraCopies,
			'potentialReclaimableBytes' => $inactiveStorageBytes + $duplicateWastedBytes,
			'inactivityThresholdMonths' => $this->settings->getInactivityThresholdMonths(),
			'recentActivity' => $recentAuditEntries,
			'lastScan' => $this->duplicateScanner->getLastDuplicateScan(),
		];

		$this->statsCache->setArray(StatsCache::OVERVIEW, $overview, 30);

		return $overview;
	}
}

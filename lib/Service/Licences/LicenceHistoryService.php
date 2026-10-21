<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Licences;

use DateTime;
use OCA\NexenoFreeUp\Db\Snapshot;
use OCA\NexenoFreeUp\Db\SnapshotMapper;
use OCA\NexenoFreeUp\Service\Accounts\AccountReportService;
use OCA\NexenoFreeUp\Service\Accounts\OverviewService;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class LicenceHistoryService {
	/** The current month plus the 11 before it are kept. */
	public const HISTORY_RETENTION_MONTHS = 12;

	public function __construct(
		private SnapshotMapper $snapshotMapper,
		private OverviewService $overview,
		private AccountReportService $accountReport,
		private IConfig $config,
		private StatsCache $statsCache,
		private LoggerInterface $logger,
	) {
	}

	/** The hourly job re-records the figure once, instead of every user change counting all accounts. */
	public function markSnapshotDirty(): void {
		if ($this->config->getAppValue('nexeno_freeup', 'snapshot_dirty', 'no') !== 'yes') {
			$this->config->setAppValue('nexeno_freeup', 'snapshot_dirty', 'yes');
		}
	}

	public function refreshSnapshotIfDirty(): void {
		if ($this->config->getAppValue('nexeno_freeup', 'snapshot_dirty', 'no') !== 'yes') {
			return;
		}
		$this->config->setAppValue('nexeno_freeup', 'snapshot_dirty', 'no');
		$this->captureSnapshot();
	}

	public function getLicenceHistory(bool $forceRefresh = false): array {
		// Past months keep their recorded figures; the current month is live and stored only when a figure changed.
		$this->snapshotMapper->purgeOutsideWindow(self::HISTORY_RETENTION_MONTHS);
		$snapshots = $this->snapshotMapper->findRecent(self::HISTORY_RETENTION_MONTHS);

		try {
			$overview = $this->overview->getDashboardOverview($forceRefresh);
			$loggedIn = $this->countLoggedInThisMonth();
			$current = null;
			foreach ($snapshots as $s) {
				if ($s->getSnapshotMonth() === date('Y-m')) {
					$current = $s;
					break;
				}
			}
			if (
				$current === null
				|| (int)$current->getLicencesInUse() !== (int)$overview['licencesInUse']
				|| $current->getLoggedInAccounts() !== $loggedIn
				|| (int)$current->getTotalAccounts() !== (int)$overview['totalAccounts']
				|| (int)$current->getArchivedAccounts() !== (int)$overview['archivedAccounts']
				|| (int)$current->getTotalStorageBytes() !== (int)$overview['totalStorageBytes']
			) {
				$this->persistCurrentSnapshot($overview, $loggedIn);
				$snapshots = $this->snapshotMapper->findRecent(self::HISTORY_RETENTION_MONTHS);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Could not update this month\'s licence figure: ' . $e->getMessage());
		}

		$result = array_map(function (Snapshot $s) {
			$data = $s->jsonSerialize();
			$data['month'] = $s->getSnapshotMonth();
			$paid = $s->getLicencesInUse() ?? ($s->getActiveAccounts() + $s->getInactiveAccounts());
			$loggedIn = $s->getLoggedInAccounts();
			$data['licencesInUse'] = $paid;
			$data['loggedIn'] = $loggedIn;
			$data['notUsed'] = $loggedIn === null ? null : max(0, $paid - $loggedIn);
			return $data;
		}, $snapshots);

		return $result;
	}

	public function captureSnapshot(): Snapshot {
		$this->statsCache->clear();
		$res = $this->persistCurrentSnapshot($this->overview->getDashboardOverview(true), $this->countLoggedInThisMonth());
		$this->statsCache->clear();
		return $res;
	}

	/** Past months are never touched. */
	private function persistCurrentSnapshot(array $overview, int $loggedIn): Snapshot {
		$month = date('Y-m');
		$snapshot = $this->snapshotMapper->findByMonth($month);
		if ($snapshot === null) {
			$snapshot = new Snapshot();
			$snapshot->setSnapshotMonth($month);
		}

		$snapshot->setTotalAccounts($overview['totalAccounts']);
		$snapshot->setActiveAccounts($overview['activeAccounts']);
		$snapshot->setInactiveAccounts($overview['inactiveCandidates']);
		$snapshot->setArchivedAccounts($overview['archivedAccounts']);
		$snapshot->setTotalStorageBytes($overview['totalStorageBytes']);
		$snapshot->setInactiveStorageBytes($overview['inactiveStorageBytes']);
		$snapshot->setReclaimedStorageBytes($overview['reclaimedStorageBytes']);
		$snapshot->setLicencesInUse((int)$overview['licencesInUse']);
		$snapshot->setLoggedInAccounts($loggedIn);
		$snapshot->setCreatedAt(new DateTime());

		$res = $snapshot->getId() === null ? $this->snapshotMapper->insert($snapshot) : $this->snapshotMapper->update($snapshot);

		$this->snapshotMapper->purgeOutsideWindow(self::HISTORY_RETENTION_MONTHS);
		return $res;
	}

	/** Captured when the snapshot runs, so the last run of a month is its final figure. */
	private function countLoggedInThisMonth(): int {
		$monthStart = strtotime(date('Y-m-01 00:00:00'));
		$count = 0;
		foreach ($this->accountReport->getAccountsReport() as $a) {
			if (!empty($a['enabled']) && !$a['isNeverLoggedIn'] && (int)$a['lastLogin'] >= $monthStart) {
				$count++;
			}
		}
		return $count;
	}
}

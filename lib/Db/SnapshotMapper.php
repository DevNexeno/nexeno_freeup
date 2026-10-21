<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Snapshot>
 */
class SnapshotMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nx_freeup_snapshots', Snapshot::class);
	}

	public function findByMonth(string $month): ?Snapshot {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_snapshots')
			->where($qb->expr()->eq('snapshot_month', $qb->createNamedParameter($month)))
			->orderBy('id', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/** @return Snapshot[] */
	public function findRecent(int $limit = 60): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_snapshots')
			->orderBy('snapshot_month', 'DESC')
			->addOrderBy('id', 'DESC');

		$entities = $this->findEntities($qb);

		$byMonth = [];
		foreach ($entities as $entity) {
			$m = $entity->getSnapshotMonth();
			if (!isset($byMonth[$m])) {
				$byMonth[$m] = $entity;
				if (count($byMonth) >= $limit) {
					break;
				}
			}
		}

		return array_reverse(array_values($byMonth));
	}

	/** Keeps the current month plus the 11 before it when $months = 12 (GDPR storage limitation). */
	public function purgeOutsideWindow(int $months = 12): int {
		$oldestKept = date('Y-m', strtotime('first day of this month -' . max(0, $months - 1) . ' months'));

		$qb = $this->db->getQueryBuilder();
		$qb->delete('nx_freeup_snapshots')
			->where($qb->expr()->lt('snapshot_month', $qb->createNamedParameter($oldestKept)));

		return (int)$qb->executeStatement();
	}
}

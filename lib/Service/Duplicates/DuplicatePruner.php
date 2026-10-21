<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Duplicates;

use OCA\NexenoFreeUp\Db\DuplicateFile;
use OCA\NexenoFreeUp\Db\DuplicateFileMapper;
use OCA\NexenoFreeUp\Service\Cache\StatsCache;
use OCP\IDBConnection;
use OCP\IUserManager;

class DuplicatePruner {
	public function __construct(
		private IDBConnection $db,
		private DuplicateFileMapper $duplicateMapper,
		private IUserManager $userManager,
		private StatsCache $statsCache,
	) {
	}

	/**
	 * @param int[] $ids
	 * @return array<int,true>
	 */
	private function existingFileIds(array $ids): array {
		$found = [];
		foreach (array_chunk(array_values(array_unique(array_filter($ids))), 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('fileid')
				->from('filecache')
				->where($qb->expr()->in('fileid', $qb->createNamedParameter($chunk, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->like('path', $qb->createNamedParameter('files/%')));
			$res = $qb->executeQuery();
			while (($id = $res->fetchOne()) !== false) {
				$found[(int)$id] = true;
			}
			$res->closeCursor();
		}
		return $found;
	}

	/**
	 * @param DuplicateFile[] $rows
	 * @return DuplicateFile[] the rows that are still duplicates
	 */
	public function pruneStaleDuplicates(array $rows): array {
		$allIds = [];
		$parsed = [];
		foreach ($rows as $row) {
			$instances = json_decode((string)$row->getInstancesJson(), true) ?: [];
			$parsed[$row->getId()] = $instances;
			foreach ($instances as $inst) {
				$allIds[] = (int)($inst['fileId'] ?? 0);
			}
		}
		$existing = $this->existingFileIds($allIds);
		$userExists = [];

		$kept = [];
		$changed = false;
		foreach ($rows as $row) {
			$instances = $parsed[$row->getId()];
			$alive = array_values(array_filter($instances, function (array $inst) use ($existing, &$userExists): bool {
				$uid = (string)($inst['userId'] ?? '');
				$userExists[$uid] ??= $this->userManager->userExists($uid);
				return $userExists[$uid] && isset($existing[(int)($inst['fileId'] ?? 0)]);
			}));

			if (count($alive) === count($instances)) {
				$kept[] = $row;
				continue;
			}
			$changed = true;
			if (count($alive) < 2) {
				$this->duplicateMapper->delete($row);
				continue;
			}
			$row->setInstancesJson((string)json_encode($alive));
			$row->setOccurrences(count($alive));
			$row->setWastedBytes($row->getSize() * (count($alive) - 1));
			$this->duplicateMapper->update($row);
			$kept[] = $row;
		}
		if ($changed) {
			$this->statsCache->clear();
		}
		return $kept;
	}
}

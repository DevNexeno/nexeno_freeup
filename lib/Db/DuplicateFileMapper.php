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
 * @template-extends QBMapper<DuplicateFile>
 */
class DuplicateFileMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nx_freeup_duplicates', DuplicateFile::class);
	}

	public function findByChecksum(string $checksum): ?DuplicateFile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_duplicates')
			->where($qb->expr()->eq('checksum', $qb->createNamedParameter($checksum)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/** @return DuplicateFile[] */
	public function findAllSorted(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_duplicates')
			->orderBy('wasted_bytes', 'DESC');

		return $this->findEntities($qb);
	}

	public function countDuplicates(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from('nx_freeup_duplicates');

		$res = $qb->executeQuery();
		$count = (int)$res->fetchOne();
		$res->closeCursor();
		return $count;
	}

	public function sumWastedBytes(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COALESCE(SUM(wasted_bytes), 0)'))
			->from('nx_freeup_duplicates');

		$res = $qb->executeQuery();
		$sum = (int)$res->fetchOne();
		$res->closeCursor();
		return $sum;
	}

	public function sumExtraCopies(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COALESCE(SUM(occurrences - 1), 0)'))
			->from('nx_freeup_duplicates');

		$res = $qb->executeQuery();
		$sum = (int)$res->fetchOne();
		$res->closeCursor();
		return $sum;
	}

	public function clearAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('nx_freeup_duplicates')
			->executeStatement();
	}
}

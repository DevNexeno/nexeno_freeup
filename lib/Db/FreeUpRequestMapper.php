<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FreeUpRequest>
 */
class FreeUpRequestMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nx_freeup_requests', FreeUpRequest::class);
	}

	public function findById(int $id): ?FreeUpRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	public function findByUserId(string $userId): ?FreeUpRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('request_type', $qb->createNamedParameter('account')))
			->orderBy('id', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/** @return FreeUpRequest[] */
	public function findAllRequests(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('request_type', $qb->createNamedParameter('account')))
			->orderBy('id', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @param string[] $statuses
	 * @return FreeUpRequest[]
	 */
	public function findByStatuses(array $statuses, int $limit = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_requests')
			->where($qb->expr()->in('status', $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('id', 'DESC');
		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}
		return $this->findEntities($qb);
	}

	/** @return array<string, FreeUpRequest> latest clean-up request per targetRef */
	public function findLatestCleanupsByTarget(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('request_type', $qb->createNamedParameter('cleanup')))
			->orderBy('id', 'ASC');
		$map = [];
		foreach ($this->findEntities($qb) as $r) {
			$map[(string)$r->getTargetRef()] = $r;
		}
		return $map;
	}

	public function countPending(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('status', $qb->createNamedParameter('proposed')));

		$res = $qb->executeQuery();
		$count = (int)$res->fetchOne();
		$res->closeCursor();
		return $count;
	}

	/** Approved items that can run now: no notice date, or the date has been reached. */
	public function countDueApproved(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('status', $qb->createNamedParameter('approved')))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('archive_after'),
				$qb->expr()->lte('archive_after', $qb->createNamedParameter(new \DateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			));
		$res = $qb->executeQuery();
		$count = (int)$res->fetchOne();
		$res->closeCursor();
		return $count;
	}

	public function countApproved(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from('nx_freeup_requests')
			->where($qb->expr()->eq('status', $qb->createNamedParameter('approved')));

		$res = $qb->executeQuery();
		$count = (int)$res->fetchOne();
		$res->closeCursor();
		return $count;
	}

	public function deleteByUserId(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('nx_freeup_requests')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('request_type', $qb->createNamedParameter('account')));
		$qb->executeStatement();
	}

	/** GDPR storage limitation (Art. 5(1)(e)). */
	public function purgeResolvedOlderThan(int $days = 365): int {
		$qb = $this->db->getQueryBuilder();
		$cutoff = (new \DateTime())->modify("-{$days} days");

		$qb->delete('nx_freeup_requests')
			->where($qb->expr()->in('status', $qb->createNamedParameter(['rejected', 'archived'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->lt('proposed_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATE)));

		return (int)$qb->executeStatement();
	}
}

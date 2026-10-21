<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Db;

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<AuditEntry>
 */
class AuditMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nx_freeup_audit', AuditEntry::class);
	}

	/**
	 * @param string|null $targetUser account the entry is about (null for files, scans, reports)
	 * @param string|null $targetName subject when it is not an account, e.g. a clean-up file
	 */
	public function log(string $action, ?string $targetUser, ?string $actorId, ?string $actorName, ?string $details = null, ?string $targetName = null): AuditEntry {
		$entry = new AuditEntry();
		$entry->setAction($action);
		$entry->setTargetUser($targetUser);
		$entry->setTargetName($targetName !== null ? mb_substr($targetName, 0, 255) : null);
		$entry->setActorId($actorId);
		$entry->setActorName($actorName);
		$entry->setDetails($details);
		$entry->setCreatedAt(new DateTime());

		return $this->insert($entry);
	}

	/** @return AuditEntry[] */
	public function findRecent(int $limit = 100): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('nx_freeup_audit')
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function anonymizeUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('nx_freeup_audit')
			->set('target_user', $qb->createNamedParameter('deleted-user'))
			->where($qb->expr()->eq('target_user', $qb->createNamedParameter($userId)));
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->update('nx_freeup_audit')
			->set('actor_id', $qb->createNamedParameter('deleted-user'))
			->set('actor_name', $qb->createNamedParameter('Deleted User'))
			->where($qb->expr()->eq('actor_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();

		// GDPR Art. 17: redact the user ID inside details text
		$qb = $this->db->getQueryBuilder();
		$qb->update('nx_freeup_audit')
			->set('details', $qb->createNamedParameter('Details removed because the account was deleted (GDPR right to erasure).'))
			->where($qb->expr()->like('details', $qb->createNamedParameter('%' . $userId . '%')));
		$qb->executeStatement();
	}

	/** GDPR storage limitation (Art. 5(1)(e)). */
	public function purgeOlderThan(int $days = 365): int {
		$qb = $this->db->getQueryBuilder();
		$cutoff = (new DateTime())->modify("-{$days} days");

		$qb->delete('nx_freeup_audit')
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($cutoff, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_DATE)));

		return (int)$qb->executeStatement();
	}
}

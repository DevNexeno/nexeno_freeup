<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Accounts;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Nextcloud does not store when an account was created, and an account that never logged in has no login date either.
 * To tell whether such an account is old enough to count as inactive, FreeUp keeps its own date per account:
 * the exact creation time (recorded when the account is created) or, for older accounts, the day FreeUp first saw it.
 */
class AccountDateService {
	private const APP = 'nexeno_freeup';
	private const CREATED = 'created_at';
	private const FIRST_SEEN = 'first_seen';

	public function __construct(
		private IConfig $config,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function recordCreated(string $userId, ?int $now = null): void {
		$this->config->setUserValue($userId, self::APP, self::CREATED, (string)($now ?? time()));
	}

	/**
	 * One query for every account, so a long user list never costs one lookup per account.
	 *
	 * @return array{created:array<string,int>,seen:array<string,int>}
	 */
	public function recorded(): array {
		$dates = ['created' => [], 'seen' => []];
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('userid', 'configkey', 'configvalue')
				->from('preferences')
				->where($qb->expr()->eq('appid', $qb->createNamedParameter(self::APP)))
				->andWhere($qb->expr()->in('configkey', $qb->createNamedParameter([self::CREATED, self::FIRST_SEEN], IQueryBuilder::PARAM_STR_ARRAY)));
			$res = $qb->executeQuery();
			while ($row = $res->fetch()) {
				$time = (int)$row['configvalue'];
				if ($time > 0) {
					$dates[$row['configkey'] === self::CREATED ? 'created' : 'seen'][(string)$row['userid']] = $time;
				}
			}
			$res->closeCursor();
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Could not read account dates: ' . $e->getMessage());
		}
		return $dates;
	}

	/**
	 * @param string[] $userIds accounts FreeUp has no date for yet
	 */
	public function recordFirstSeen(array $userIds, int $now): void {
		foreach ($userIds as $userId) {
			try {
				$this->config->setUserValue($userId, self::APP, self::FIRST_SEEN, (string)$now);
			} catch (\Throwable $e) {
				$this->logger->warning("NexenoFreeUp: Could not record first-seen date for {$userId}: " . $e->getMessage());
			}
		}
	}
}

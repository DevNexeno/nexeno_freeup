<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Cache;

use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * Every service that changes something an overview shows must call clear().
 */
class StatsCache {
	/** Seconds the per-user facts are reused before being collected again. */
	public const ACCOUNT_FACTS_TTL = 600;

	public const OVERVIEW = 'dashboard_overview';
	public const ACCOUNT_FACTS = 'accounts_facts';
	private const LICENCE_HISTORY = 'licence_history';
	private const RECENT_LOGINS = 'recent_logins';

	private ?ICache $cache = null;

	public function __construct(
		private LoggerInterface $logger,
		private ?ICacheFactory $cacheFactory = null,
	) {
		if ($this->cacheFactory !== null) {
			try {
				$this->cache = $this->cacheFactory->createDistributed('nexeno_freeup');
			} catch (\Throwable $e) {
				$this->logger->debug('NexenoFreeUp: Could not initialize distributed cache: ' . $e->getMessage());
			}
		}
	}

	public function clear(): void {
		if ($this->cache !== null) {
			try {
				$this->cache->remove(self::OVERVIEW);
				$this->cache->remove(self::LICENCE_HISTORY);
			} catch (\Throwable $e) {
				$this->logger->debug('NexenoFreeUp: Error clearing cache: ' . $e->getMessage());
			}
		}
	}

	public function clearAccountFacts(): void {
		if ($this->cache !== null) {
			try {
				$this->cache->remove(self::ACCOUNT_FACTS);
			} catch (\Throwable $e) {
				$this->logger->debug('NexenoFreeUp: Error clearing account facts: ' . $e->getMessage());
			}
		}
		$this->clear();
	}

	/**
	 * @param bool $acceptArray also accept a value the cache returned already decoded
	 */
	public function getArray(string $key, bool $acceptArray = false): ?array {
		if ($this->cache === null) {
			return null;
		}
		try {
			$cached = $this->cache->get($key);
			if (is_string($cached)) {
				$decoded = json_decode($cached, true);
				if (is_array($decoded)) {
					return $decoded;
				}
			} elseif ($acceptArray && is_array($cached)) {
				return $cached;
			}
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Cache get error for ' . $key . ': ' . $e->getMessage());
		}
		return null;
	}

	public function setArray(string $key, array $value, int $ttl): void {
		if ($this->cache === null) {
			return;
		}
		try {
			$this->cache->set($key, json_encode($value), $ttl);
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Cache set error for ' . $key . ': ' . $e->getMessage());
		}
	}

	/**
	 * Also drops the overview and recent logins: both are derived from the old facts.
	 */
	public function storeAccountFacts(array $facts): void {
		if ($this->cache === null) {
			return;
		}
		try {
			$this->cache->remove(self::RECENT_LOGINS);
			$this->cache->remove(self::OVERVIEW);
			$this->cache->set(self::ACCOUNT_FACTS, json_encode($facts), self::ACCOUNT_FACTS_TTL);
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Cache set error for accounts_facts: ' . $e->getMessage());
		}
	}

	/**
	 * Small overlay on the cached user list so an account shows as active at once.
	 */
	public function recordLogin(string $userId): void {
		if ($this->cache === null) {
			return;
		}
		try {
			$map = $this->readRecentLogins();
			$map[$userId] = time();
			$this->cache->set(self::RECENT_LOGINS, json_encode($map), self::ACCOUNT_FACTS_TTL * 2);
			$this->cache->remove(self::OVERVIEW);
		} catch (\Throwable $e) {
			$this->logger->debug('NexenoFreeUp: Could not record login: ' . $e->getMessage());
		}
	}

	/**
	 * @return array<string,int>
	 */
	public function readRecentLogins(): array {
		if ($this->cache === null) {
			return [];
		}
		try {
			$decoded = json_decode((string)$this->cache->get(self::RECENT_LOGINS), true);
			return is_array($decoded) ? array_map('intval', $decoded) : [];
		} catch (\Throwable) {
			return [];
		}
	}
}

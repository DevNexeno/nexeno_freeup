<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Duplicates;

use OCP\IConfig;

/**
 * Unlike Nextcloud's rate limit, keeps scan start times so a refusal can say when the next scan is allowed.
 */
class ScanThrottle {
	/** At most this many scans per person within WINDOW seconds. */
	public const LIMIT = 6;
	public const WINDOW = 600;

	private const KEY = 'scan_requests';

	public function __construct(
		private IConfig $config,
	) {
	}

	public function blockedUntil(string $userId, ?int $now = null): ?int {
		$now ??= time();
		$recent = $this->recent($userId, $now);
		if (count($recent) < self::LIMIT) {
			return null;
		}
		return $recent[count($recent) - self::LIMIT] + self::WINDOW;
	}

	public function record(string $userId, ?int $now = null): void {
		$now ??= time();
		$recent = $this->recent($userId, $now);
		$recent[] = $now;
		$this->config->setUserValue($userId, 'nexeno_freeup', self::KEY, (string)json_encode(array_slice($recent, -self::LIMIT)));
	}

	/**
	 * @return int[]
	 */
	private function recent(string $userId, int $now): array {
		$stored = json_decode($this->config->getUserValue($userId, 'nexeno_freeup', self::KEY, '[]'), true);
		$times = array_map('intval', is_array($stored) ? $stored : []);
		$times = array_values(array_filter($times, fn (int $t) => $t > $now - self::WINDOW && $t <= $now));
		sort($times);
		return $times;
	}
}

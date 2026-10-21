<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Util;

/** English-style sizes, kept because they are saved inside audit-trail text. Screens format in the browser. */
final class Format {
	public static function bytes(int|float $bytes, int $precision = 2): string {
		$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}
}

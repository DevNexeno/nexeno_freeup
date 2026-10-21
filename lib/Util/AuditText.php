<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Util;

use DateTimeInterface;
use OCA\NexenoFreeUp\Db\FreeUpRequest;

/** Message parts for audit entries. Messages omit account names so they stay readable after the account is deleted. */
final class AuditText {
	public static function count(int $n, string $one, string $many): string {
		return $n . ' ' . ($n === 1 ? $one : $many);
	}

	/**
	 * Account requests are about the account; clean-ups concern several owners, so only the file is named.
	 * @return array{0:?string,1:?string} target user, target name
	 */
	public static function subject(FreeUpRequest $req): array {
		return $req->getRequestType() === 'cleanup'
			? [null, (string)$req->getTargetName()]
			: [$req->getUserId(), null];
	}

	public static function join(string ...$parts): string {
		return implode(' · ', array_filter(array_map('trim', $parts), fn (string $p) => $p !== ''));
	}

	public static function date(DateTimeInterface $date): string {
		return $date->format('j M Y');
	}

	/** Empty when no reason was written. */
	public static function reason(?string $text): string {
		return self::labelled('Reason', $text);
	}

	public static function note(?string $text): string {
		return self::labelled('Note', $text);
	}

	private static function labelled(string $label, ?string $text): string {
		$text = trim((string)$text);
		return $text === '' ? '' : $label . ': ' . rtrim($text, '.');
	}
}

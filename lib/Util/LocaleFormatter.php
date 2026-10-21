<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Util;

use DateTimeInterface;
use IntlDateFormatter;
use NumberFormatter;

/** Locale-aware numbers, sizes and dates for emails, reports and CSV; mirrors src/services/format.js. */
final class LocaleFormatter {
	private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

	private const DATE_STYLES = [
		'short' => IntlDateFormatter::SHORT,
		'medium' => IntlDateFormatter::MEDIUM,
		'long' => IntlDateFormatter::LONG,
		'full' => IntlDateFormatter::FULL,
	];

	/** Nextcloud writes `de_DE`, `sr@latin`; ICU wants `de_DE`, `sr_Latn`. */
	public static function icuLocale(string $locale): string {
		$locale = str_replace('-', '_', trim($locale));
		return $locale === '' ? 'en_US' : str_replace('@latin', '_Latn', $locale);
	}

	public static function number(int|float $value, string $locale, int $maxDecimals = 0): string {
		if (!class_exists(NumberFormatter::class)) {
			return number_format((float)$value, $maxDecimals);
		}
		$f = new NumberFormatter(self::icuLocale($locale), NumberFormatter::DECIMAL);
		$f->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimals);
		$f->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
		return (string)$f->format($value);
	}

	public static function bytes(int|float $bytes, string $locale, int $decimals = 2): string {
		$bytes = max($bytes, 0);
		$pow = $bytes > 0 ? min((int)floor(log($bytes) / log(1024)), count(self::UNITS) - 1) : 0;
		return self::number($bytes / (1024 ** $pow), $locale, $pow === 0 ? 0 : $decimals) . ' ' . self::UNITS[$pow];
	}

	public static function date(int|DateTimeInterface $when, string $locale, string $style = 'medium', ?string $timezone = null): string {
		return self::dateFormatter($locale, self::DATE_STYLES[$style] ?? IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, $timezone, null)
			->format($when) ?: '';
	}

	/** A time of day: `locale` follows the locale's own clock, `24` or `12` forces one. */
	public static function time(int|DateTimeInterface $when, string $locale, string $clock = 'locale', ?string $timezone = null): string {
		$pattern = match ($clock) {
			'24' => 'HH:mm',
			'12' => 'hh:mm a',
			default => null,
		};
		return self::dateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $timezone, $pattern)
			->format($when) ?: '';
	}

	public static function monthYear(int|DateTimeInterface $when, string $locale, ?string $timezone = null): string {
		return self::dateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, 'LLLL y')
			->format($when) ?: '';
	}

	private static function dateFormatter(string $locale, int $dateType, int $timeType, ?string $timezone, ?string $pattern): IntlDateFormatter {
		return new IntlDateFormatter(
			self::icuLocale($locale),
			$dateType,
			$timeType,
			$timezone ?? date_default_timezone_get(),
			IntlDateFormatter::GREGORIAN,
			$pattern,
		);
	}
}

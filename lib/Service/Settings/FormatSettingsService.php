<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Settings;

use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;

/**
 * Admin overrides for date, time and number formats. Unset means each person's own Nextcloud locale;
 * `force_locale` in config.php always wins. Nextcloud's own configuration is only read.
 */
class FormatSettingsService {
	public const MODE_PERSON = 'person';
	public const MODE_FIXED = 'fixed';
	public const DATE_STYLES = ['short', 'medium', 'long', 'full'];
	public const CLOCKS = ['locale', '24', '12'];

	public function __construct(
		private IL10N $l,
		private IConfig $config,
		private IFactory $l10nFactory,
		private IUserSession $userSession,
		private UserLocaleService $userLocales,
	) {
	}

	public function getMode(): string {
		return $this->config->getAppValue('nexeno_freeup', 'format_mode', self::MODE_PERSON) === self::MODE_FIXED ? self::MODE_FIXED : self::MODE_PERSON;
	}

	/** '' when none is saved or it no longer exists. */
	public function getFixedLocale(): string {
		$locale = $this->config->getAppValue('nexeno_freeup', 'format_locale', '');
		return $locale !== '' && $this->l10nFactory->localeExists($locale) ? $locale : '';
	}

	/** With the Nextcloud locale, FreeUp keeps no format choices of its own: the stored ones only apply to its own locale. */
	public function getDateStyle(): string {
		if ($this->getMode() !== self::MODE_FIXED) {
			return 'medium';
		}
		$style = $this->config->getAppValue('nexeno_freeup', 'format_date_style', 'medium');
		return in_array($style, self::DATE_STYLES, true) ? $style : 'medium';
	}

	public function getClock(): string {
		if ($this->getMode() !== self::MODE_FIXED) {
			return 'locale';
		}
		$clock = $this->config->getAppValue('nexeno_freeup', 'format_clock', 'locale');
		return in_array($clock, self::CLOCKS, true) ? $clock : 'locale';
	}

	/**
	 * A null argument leaves that value as it is.
	 *
	 * @throws \InvalidArgumentException for a value that is not an allowed choice
	 */
	public function save(?string $mode, ?string $locale, ?string $dateStyle, ?string $clock): void {
		if ($mode !== null && !in_array($mode, [self::MODE_PERSON, self::MODE_FIXED], true)) {
			throw new \InvalidArgumentException($this->l->t('Choose how dates and numbers are written.'));
		}
		if ($locale !== null && $locale !== '' && !$this->l10nFactory->localeExists($locale)) {
			throw new \InvalidArgumentException($this->l->t('That locale is not available in this Nextcloud.'));
		}
		if ($dateStyle !== null && !in_array($dateStyle, self::DATE_STYLES, true)) {
			throw new \InvalidArgumentException($this->l->t('Choose a date style.'));
		}
		if ($clock !== null && !in_array($clock, self::CLOCKS, true)) {
			throw new \InvalidArgumentException($this->l->t('Choose a time format.'));
		}
		$fixedWithoutLocale = ($mode ?? $this->getMode()) === self::MODE_FIXED && ($locale ?? $this->getFixedLocale()) === '';
		if ($fixedWithoutLocale && $this->userLocales->forcedLocale() === null) {
			throw new \InvalidArgumentException($this->l->t('Pick the locale to use for everyone.'));
		}

		foreach (['format_mode' => $mode, 'format_locale' => $locale, 'format_date_style' => $dateStyle, 'format_clock' => $clock] as $key => $value) {
			if ($value !== null) {
				$this->config->setAppValue('nexeno_freeup', $key, $value);
			}
		}
		// Emails used to have their own locale choice; they now follow the one above.
		$this->config->deleteAppValue('nexeno_freeup', 'format_email');
	}

	/** Null when each person's own Nextcloud locale applies. */
	public function getAppLocale(): ?string {
		$forced = $this->userLocales->forcedLocale();
		if ($forced !== null) {
			return $forced;
		}
		return $this->getMode() === self::MODE_FIXED && $this->getFixedLocale() !== '' ? $this->getFixedLocale() : null;
	}

	public function getLocaleFor(?IUser $user): string {
		return $this->getAppLocale() ?? $this->userLocales->forUser($user);
	}

	/** Emails follow the same choice as the app: FreeUp's own locale if one is chosen (or forced), else the recipient's. */
	public function getEmailLocale(?IUser $recipient): string {
		return $this->getAppLocale() ?? $this->userLocales->forUser($recipient);
	}

	/** @return array<string,mixed> */
	public function toArray(bool $admin): array {
		$data = [
			'mode' => $this->getMode(),
			'locale' => $this->getFixedLocale(),
			'dateStyle' => $this->getDateStyle(),
			'clock' => $this->getClock(),
			'forcedLocale' => $this->userLocales->forcedLocale(),
			'appLocale' => $this->getAppLocale(),
		];
		if ($admin) {
			$names = [];
			foreach ($this->l10nFactory->findAvailableLocales() as $l) {
				$names[(string)$l['code']] = (string)$l['name'];
			}
			$own = $this->userLocales->forUser($this->userSession->getUser());
			$data['server'] = [
				'yourLocale' => $own,
				'yourLocaleName' => $names[$own] ?? $own,
			];
		}
		return $data;
	}

	/** @return array<int,array{code:string,name:string}> */
	public function listLocales(): array {
		return array_map(
			fn (array $l) => ['code' => (string)$l['code'], 'name' => (string)$l['name']],
			$this->l10nFactory->findAvailableLocales()
		);
	}
}

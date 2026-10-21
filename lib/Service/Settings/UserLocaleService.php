<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Settings;

use OCP\IConfig;
use OCP\IUser;
use OCP\L10N\IFactory;

/**
 * Resolves a locale like Nextcloud does: `force_locale`, the person's own choice, then `default_locale` or the
 * locale of the server's default language. For server-side output (emails, reports, CSV); the browser gets its
 * locale from Nextcloud.
 */
class UserLocaleService {
	public function __construct(
		private IConfig $config,
		private IFactory $l10nFactory,
	) {
	}

	/** Falls back to the server default when there is no account (e.g. the finance contact). */
	public function forUser(?IUser $user): string {
		$forced = $this->forcedLocale();
		if ($forced !== null) {
			return $forced;
		}
		if ($user !== null) {
			$own = $this->config->getUserValue($user->getUID(), 'core', 'locale', '');
			if ($own !== '' && $this->l10nFactory->localeExists($own)) {
				return $own;
			}
		}
		$default = $this->configuredDefaultLocale();
		if ($default !== null) {
			return $default;
		}
		if ($user !== null) {
			$language = $this->l10nFactory->getUserLanguage($user);
			if ($this->l10nFactory->localeExists($language)) {
				return $language;
			}
		}
		return $this->l10nFactory->findGenericLanguage();
	}

	public function forcedLocale(): ?string {
		$forced = $this->config->getSystemValue('force_locale', false);
		return is_string($forced) && $forced !== '' ? $forced : null;
	}

	/** IFactory::findLocale() would mix in the signed-in person's locale, which must not leak into someone else's email. */
	public function serverDefaultLocale(): string {
		return $this->configuredDefaultLocale() ?? $this->l10nFactory->findGenericLanguage();
	}

	private function configuredDefaultLocale(): ?string {
		$default = $this->config->getSystemValue('default_locale', false);
		return is_string($default) && $default !== '' && $this->l10nFactory->localeExists($default) ? $default : null;
	}
}

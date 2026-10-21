<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
require_once './vendor/autoload.php';
use Nextcloud\CodingStandard\Config;
$config = new Config();
$config
    ->getFinder()
    ->notPath('l10n')
    ->notPath('src')
    ->notPath('js')
    ->notPath('css')
    ->notPath('node_modules')
    ->notPath('vendor')
    ->notPath('vendor-bin')
    ->in(__DIR__);
return $config;
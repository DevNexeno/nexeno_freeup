<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

style('nexeno_freeup', 'nexeno_freeup');
script('nexeno_freeup', 'nexeno_freeup');

// Cache buster: browsers keep the CSS for months otherwise.
$cssPath = dirname(__DIR__) . '/css/nexeno_freeup.css';
$cssMtime = file_exists($cssPath) ? filemtime($cssPath) : time();
$urlGenerator = \OC::$server->get(\OCP\IURLGenerator::class);
$freshCssUrl = $urlGenerator->linkTo('nexeno_freeup', 'css/nexeno_freeup.css') . '?v=' . $cssMtime;
\OCP\Util::addHeader('link', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $freshCssUrl]);

$defaults = \OC::$server->get(\OCP\Defaults::class);
$themeColor = $defaults->getColorPrimary();
$themingFavicon = '/apps/theming/favicon/nexeno_freeup';

\OCP\Util::addHeader('link', ['rel' => 'icon', 'type' => 'image/x-icon', 'href' => $themingFavicon]);

$initialOverview = !empty($_['initialOverview']) ? $_['initialOverview'] : '{}';
$initialLicenceHistory = !empty($_['initialLicenceHistory']) ? $_['initialLicenceHistory'] : '[]';
$initialSettings = !empty($_['initialSettings']) ? $_['initialSettings'] : '{}';
?>

<div id="nexeno_freeup-initial-data"
     style="display:none;"
     data-overview="<?php p(base64_encode($initialOverview)); ?>"
     data-licence-history="<?php p(base64_encode($initialLicenceHistory)); ?>"
     data-settings="<?php p(base64_encode($initialSettings)); ?>"
     data-theme-color="<?php p($themeColor); ?>"
     data-theming-favicon="<?php p($themingFavicon); ?>"></div>

<div id="nexeno_freeup-app"></div>

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#overview', 'url' => '/overview', 'verb' => 'GET'],
		['name' => 'page#inactive_accounts', 'url' => '/inactive-accounts', 'verb' => 'GET'],
		['name' => 'page#accounts', 'url' => '/accounts', 'verb' => 'GET'],
		['name' => 'page#approvals', 'url' => '/approvals', 'verb' => 'GET'],
		['name' => 'page#duplicates', 'url' => '/duplicates', 'verb' => 'GET'],
		['name' => 'page#duplicate_detail', 'url' => '/duplicate-detail', 'verb' => 'GET'],
		['name' => 'page#licences', 'url' => '/licences', 'verb' => 'GET'],
		['name' => 'page#audit', 'url' => '/audit', 'verb' => 'GET'],
		['name' => 'page#settings', 'url' => '/settings', 'verb' => 'GET'],

		['name' => 'free_up_api#getOverview', 'url' => '/api/v1/overview', 'verb' => 'GET'],
		['name' => 'free_up_api#getAccounts', 'url' => '/api/v1/accounts', 'verb' => 'GET'],
		['name' => 'free_up_api#getApprovals', 'url' => '/api/v1/approvals', 'verb' => 'GET'],
		['name' => 'free_up_api#proposeArchival', 'url' => '/api/v1/propose', 'verb' => 'POST'],
		['name' => 'free_up_api#proposeCleanup', 'url' => '/api/v1/cleanup/propose', 'verb' => 'POST'],
		['name' => 'free_up_api#decideMany', 'url' => '/api/v1/decide-bulk', 'verb' => 'POST'],
		['name' => 'free_up_api#proposeCleanupBulk', 'url' => '/api/v1/cleanup/propose-bulk', 'verb' => 'POST'],
		['name' => 'free_up_api#resendNotice', 'url' => '/api/v1/notice/resend', 'verb' => 'POST'],
		['name' => 'free_up_api#runArchive', 'url' => '/api/v1/archive/run', 'verb' => 'POST'],
		['name' => 'free_up_api#approveArchival', 'url' => '/api/v1/approve', 'verb' => 'POST'],
		['name' => 'free_up_api#rejectProposal', 'url' => '/api/v1/reject', 'verb' => 'POST'],
		['name' => 'free_up_api#restoreUser', 'url' => '/api/v1/restore', 'verb' => 'POST'],

		['name' => 'free_up_api#getDuplicates', 'url' => '/api/v1/duplicates', 'verb' => 'GET'],
		['name' => 'free_up_api#scanDuplicates', 'url' => '/api/v1/duplicates/scan', 'verb' => 'POST'],
		['name' => 'free_up_api#sendReportNow', 'url' => '/api/v1/report/send-now', 'verb' => 'POST'],
		['name' => 'free_up_api#searchPrincipals', 'url' => '/api/v1/principals', 'verb' => 'GET'],
		['name' => 'free_up_api#scanRunNow', 'url' => '/api/v1/duplicates/scan/run-now', 'verb' => 'POST'],
		['name' => 'free_up_api#scanStatus', 'url' => '/api/v1/duplicates/scan/status', 'verb' => 'GET'],

		['name' => 'free_up_api#getLicenceHistory', 'url' => '/api/v1/licences', 'verb' => 'GET'],
		['name' => 'free_up_api#getAuditLog', 'url' => '/api/v1/audit', 'verb' => 'GET'],

		['name' => 'free_up_api#getSettings', 'url' => '/api/v1/settings', 'verb' => 'GET'],
		['name' => 'free_up_api#saveSettings', 'url' => '/api/v1/settings', 'verb' => 'PUT'],
		['name' => 'free_up_api#getFormatLocales', 'url' => '/api/v1/format/locales', 'verb' => 'GET'],
		['name' => 'free_up_api#runCatchUp', 'url' => '/api/v1/catch-up', 'verb' => 'POST'],
		['name' => 'free_up_api#exportReport', 'url' => '/api/v1/export', 'verb' => 'GET'],
	],
];

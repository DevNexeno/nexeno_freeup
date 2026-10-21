/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { fmtNumber } from './format.js'

function accountType(type) {
	switch (type) {
		case 'Student': return t('nexeno_freeup', 'Student')
		case 'Staff': return t('nexeno_freeup', 'Staff')
		case 'Admin': return t('nexeno_freeup', 'Admin')
		case 'User':
		case undefined:
		case null:
		case '': return t('nexeno_freeup', 'User')
		default: return type // a group name chosen by the administrator
	}
}

/**
 * @param {object} req a request from /api/v1/approvals
 * @return {object} the same request with `title` and `subtitle`
 */
export function describeRequest(req) {
	if (req.kind === 'cleanup') {
		const copies = Number(req.copies) || 0
		return {
			...req,
			title: t('nexeno_freeup', 'Clean-up: {name}', { name: req.targetName || '' }),
			subtitle: n('nexeno_freeup', '{count} copy to 1 shared copy', '{count} copies to 1 shared copy', copies, { count: fmtNumber(copies) }),
		}
	}
	const months = Math.max(0, Number(req.inactiveMonths) || 0)
	const login = req.isNeverLoggedIn
		? t('nexeno_freeup', 'never logged in')
		: n('nexeno_freeup', 'no login for {count} month', 'no login for {count} months', months, { count: fmtNumber(months) })
	return {
		...req,
		title: req.displayName || req.userId,
		subtitle: [accountType(req.accountType), login].join(' · '),
	}
}

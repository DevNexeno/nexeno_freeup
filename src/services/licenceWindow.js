/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import { fmtDayMonth, fmtNumber } from './format.js'

export const WINDOW_MONTHS = 12

export function monthKey(date) {
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

export function parseMonth(key) {
	const [y, m] = key.split('-').map(Number)
	return new Date(y, m - 1, 1)
}

/**
 * Starts at the first recorded month and spans 12 months; once older than that it rolls forward to end at the
 * current month (older months are deleted for GDPR).
 *
 * @param {Array} history rows with `month` (YYYY-MM)
 * @param {Date} now current date
 * @return {string[]} 12 month keys, oldest first
 */
export function licenceWindowMonths(history, now = new Date()) {
	const current = new Date(now.getFullYear(), now.getMonth(), 1)
	const earliestAllowed = new Date(current.getFullYear(), current.getMonth() - (WINDOW_MONTHS - 1), 1)

	const recorded = (history || [])
		.map((h) => h.month || h.snapshotMonth)
		.filter(Boolean)
		.map(parseMonth)
		.sort((a, b) => a - b)

	const first = recorded[0] || current
	const start = first > earliestAllowed ? first : earliestAllowed

	return Array.from({ length: WINDOW_MONTHS }, (_, i) => monthKey(new Date(start.getFullYear(), start.getMonth() + i, 1)))
}

/**
 * Uses last month's final figure once recorded, else the current month so far. Empty while neither has a login count.
 *
 * @param {Array} history rows with `month` (YYYY-MM) and `notUsed`
 * @param {Date} now current date
 * @return {string}
 */
export function notLoggedInText(history, now = new Date()) {
	const rows = history || []
	const find = (date) => rows.find((h) => (h.month || h.snapshotMonth) === monthKey(date))
	const hasCount = (row) => row && row.notUsed !== null && row.notUsed !== undefined
	const count = (row) => fmtNumber(Number(row.notUsed))

	const lastMonth = find(new Date(now.getFullYear(), now.getMonth() - 1, 1))
	if (hasCount(lastMonth)) {
		return t('nexeno_freeup', '{count} not logged in last month', { count: count(lastMonth) })
	}
	const thisMonth = find(now)
	if (hasCount(thisMonth)) {
		const since = fmtDayMonth(new Date(now.getFullYear(), now.getMonth(), 1))
		return t('nexeno_freeup', '{count} have not logged in since {date}', { count: count(thisMonth), date: since })
	}
	return ''
}

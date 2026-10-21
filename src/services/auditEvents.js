/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { fmtMonthYear, localizeText } from './format.js'

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const CATEGORY = {
	accounts: () => t('nexeno_freeup', 'Accounts'),
	duplicates: () => t('nexeno_freeup', 'Duplicates'),
	scans: () => t('nexeno_freeup', 'Scans'),
	reports: () => t('nexeno_freeup', 'Reports'),
	other: () => t('nexeno_freeup', 'Other'),
}

// `cleanup`: wording used when the entry is about a file instead of an account
const EVENTS = {
	propose_archival: { variant: 'warning', label: () => t('nexeno_freeup', 'Removal proposed') },
	approve_archival: { variant: 'primary', label: () => t('nexeno_freeup', 'Removal approved'), cleanup: () => t('nexeno_freeup', 'Clean-up approved') },
	reject_proposal: { variant: 'secondary', label: () => t('nexeno_freeup', 'Removal dismissed'), cleanup: () => t('nexeno_freeup', 'Clean-up dismissed') },
	notice_sent: { variant: 'secondary', label: () => t('nexeno_freeup', 'User notified'), cleanup: () => t('nexeno_freeup', 'Owners notified') },
	request_obsolete: { variant: 'secondary', label: () => t('nexeno_freeup', 'Request closed'), cleanup: () => t('nexeno_freeup', 'Clean-up closed') },
	notice_skipped: { variant: 'warning', label: () => t('nexeno_freeup', 'Disabled before the waiting period ended'), cleanup: () => t('nexeno_freeup', 'Clean-up run before the waiting period ended') },
	archive_cancelled: { variant: 'secondary', label: () => t('nexeno_freeup', 'Disabling cancelled') },
	archive_started: { variant: 'tertiary', label: () => t('nexeno_freeup', 'Disabling started') },
	archive_completed: { variant: 'success', label: () => t('nexeno_freeup', 'Account disabled') },
	restore_user: { variant: 'success', label: () => t('nexeno_freeup', 'Account restored') },
	propose_cleanup: { group: 'duplicates', variant: 'warning', label: () => t('nexeno_freeup', 'Clean-up proposed') },
	cleanup_completed: { group: 'duplicates', variant: 'success', label: () => t('nexeno_freeup', 'Clean-up done') },
	scan_duplicates: { group: 'scans', variant: 'tertiary', label: () => t('nexeno_freeup', 'Scan completed') },
	licence_report_sent: { group: 'reports', variant: 'secondary', label: () => t('nexeno_freeup', 'Licence report sent') },
}

/**
 * @param {object} entry an audit entry from /api/v1/audit (action, targetUser, targetName, details)
 * @return {{group: string, category: string, variant: string, title: string, detail: string}}
 */
export function describeAuditEntry(entry) {
	const e = EVENTS[entry.action]
	const isFile = !!entry.targetName
	let label
	if (e) {
		label = isFile && e.cleanup ? e.cleanup() : e.label()
	} else {
		const human = String(entry.action || '').replace(/_/g, ' ')
		label = human.charAt(0).toUpperCase() + human.slice(1)
	}
	const group = e?.group || (isFile ? 'duplicates' : e ? 'accounts' : 'other')
	const subject = isFile ? entry.targetName : (entry.targetUser ? '@' + entry.targetUser : '')
	return {
		group,
		category: CATEGORY[group](),
		variant: e?.variant || 'tertiary',
		title: subject ? `${label} · ${subject}` : label,
		// saved text holds English sizes and dates; show them in the viewer's locale
		detail: localizeText(translateDetail(entry.details || '')),
	}
}

/**
 * The server stores details as English phrases joined with " · ". They are translated here at display time;
 * unknown phrases (such as a typed reason) are shown as written.
 *
 * @param m
 */
const PHRASES = [
	['By email and notification', () => t('nexeno_freeup', 'By email and notification')],
	['By notification only (no email sent)', () => t('nexeno_freeup', 'By notification only (no email sent)')],
	['Could not be reached', () => t('nexeno_freeup', 'Could not be reached')],
	['Signed in during the notice period', () => t('nexeno_freeup', 'Signed in during the notice period')],
	['Sign-in blocked', () => t('nexeno_freeup', 'Sign-in blocked')],
	['1 licence freed', () => t('nexeno_freeup', '1 licence freed')],
	['Can sign in again', () => t('nexeno_freeup', 'Can sign in again')],
	['The copies no longer exist', () => t('nexeno_freeup', 'The copies no longer exist')],
	['The account no longer exists', () => t('nexeno_freeup', 'The account no longer exists')],
	['Storage and licence figures updated', () => t('nexeno_freeup', 'Storage and licence figures updated')],
	['No duplicate files found', () => t('nexeno_freeup', 'No duplicate files found')],
	// a month: new entries save it as 2026-10, older ones as the English name
	[/^(\d{4})-(0[1-9]|1[0-2])$/, (m) => fmtMonthYear(new Date(Number(m[1]), Number(m[2]) - 1, 1))],
	[/^(January|February|March|April|May|June|July|August|September|October|November|December) (\d{4})$/, (m) => fmtMonthYear(new Date(Number(m[2]), MONTHS.indexOf(m[1]), 1))],
	[/^Uses (.+)$/, (m) => t('nexeno_freeup', 'Uses {size}', { size: m[1] })],
	[/^(.+) of files kept$/, (m) => t('nexeno_freeup', '{size} of files kept', { size: m[1] })],
	[/^(?:Archive|Disable) on (.+)$/, (m) => t('nexeno_freeup', 'Disable on {date}', { date: m[1] })],
	[/^Replaced on (.+)$/, (m) => t('nexeno_freeup', 'Replaced on {date}', { date: m[1] })],
	[/^Planned for (.+)$/, (m) => t('nexeno_freeup', 'Planned for {date}', { date: m[1] })],
	[/^Sent to (.+)$/, (m) => t('nexeno_freeup', 'Sent to {email}', { email: m[1] })],
	[/^Reason: ([\s\S]*)$/, (m) => t('nexeno_freeup', 'Reason: {text}', { text: m[1] })],
	[/^Note: ([\s\S]*)$/, (m) => t('nexeno_freeup', 'Note: {text}', { text: m[1] })],
	[/^(\d+) copy$|^(\d+) copies$/, (m) => {
		const c = Number(m[1] || m[2])
		return n('nexeno_freeup', '{count} copy', '{count} copies', c, { count: c })
	}],
	[/^(\d+) copy replaced by a shared copy$|^(\d+) copies replaced by a shared copy$/, (m) => {
		const c = Number(m[1] || m[2])
		return n('nexeno_freeup', '{count} copy replaced by a shared copy', '{count} copies replaced by a shared copy', c, { count: c })
	}],
	[/^(\d+) already deleted by (?:its owner|their owners)$/, (m) => n('nexeno_freeup', '{count} already deleted by its owner', '{count} already deleted by their owners', Number(m[1]), { count: Number(m[1]) })],
	[/^(\d+) left unchanged \(changed since the scan\)$/, (m) => t('nexeno_freeup', '{count} left unchanged (changed since the scan)', { count: m[1] })],
	[/^(\d+) (?:owner|owners), (\d+) by email$/, (m) => n('nexeno_freeup', '{count} owner, {emailed} by email', '{count} owners, {emailed} by email', Number(m[1]), { count: m[1], emailed: m[2] })],
	[/^Sent again to (\d+) (?:person|people|user|users), (\d+) by email$/, (m) => n('nexeno_freeup', 'Sent again to {count} user, {emailed} by email', 'Sent again to {count} users, {emailed} by email', Number(m[1]), { count: m[1], emailed: m[2] })],
	[/^Duplicates: (\d+) files? with extra copies, (.+) could be freed$/, (m) => n('nexeno_freeup', 'Duplicates: {count} file with extra copies, {size} could be freed', 'Duplicates: {count} files with extra copies, {size} could be freed', Number(m[1]), { count: m[1], size: m[2] })],
]

function translatePhrase(phrase) {
	for (const [match, write] of PHRASES) {
		if (typeof match === 'string') {
			if (phrase === match) {
				return write()
			}
		} else {
			const m = phrase.match(match)
			if (m) {
				return write(m)
			}
		}
	}
	return phrase
}

export function translateDetail(details) {
	return String(details || '').split(' · ').map(translatePhrase).join(' · ')
}

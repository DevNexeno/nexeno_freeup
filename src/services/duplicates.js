/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'

/**
 * File type badge text, e.g. "PDF"; "FILE" when the name has no short extension.
 *
 * @param name
 */
export function extensionOf(name) {
	const m = /\.([A-Za-z0-9]{1,5})$/.exec(name || '')
	return m ? m[1].toUpperCase() : 'FILE'
}

/**
 * Why the kept copy was chosen (CleanupService::pickKeeper reasons).
 *
 * @param reason
 */
export function keeperReasonText(reason) {
	switch (reason) {
		case 'oldest_active': return t('nexeno_freeup', 'The oldest copy on an active account will be retained.')
		case 'active': return t('nexeno_freeup', 'The copy on an active account will be retained.')
		case 'oldest': return t('nexeno_freeup', 'The oldest copy will be retained.')
		default: return t('nexeno_freeup', 'One copy will be retained.')
	}
}

function parseInstances(f) {
	let fileInstances = f.instances
	if (typeof fileInstances === 'string') {
		try {
			fileInstances = JSON.parse(fileInstances)
		} catch {
			fileInstances = []
		}
	}
	if (!Array.isArray(fileInstances) && f.instancesJson) {
		try {
			fileInstances = JSON.parse(f.instancesJson)
		} catch {
			fileInstances = []
		}
	}
	return Array.isArray(fileInstances) ? fileInstances : []
}

function toDuplicateItem(f) {
	const fileInstances = parseInstances(f)
	const userList = [...new Set(fileInstances.map((i) => i.userId).filter(Boolean))]
	const userSummary = userList.length > 0 ? userList.map((u) => '@' + u).join(', ') : (f.userId ? '@' + f.userId : '')

	return {
		uniqueKey: 'file-' + f.id,
		id: f.id,
		name: f.fileName,
		folder: f.folder || (fileInstances[0]?.folder || '/'),
		userId: userList[0] || f.userId || '',
		userList,
		userSummary,
		checksum: f.checksum,
		keeper: f.keeper || null,
		size: f.size || 0,
		occurrences: f.occurrences || (fileInstances.length || 2),
		totalConsumed: (f.size || 0) * (f.occurrences || (fileInstances.length || 2)),
		wastedBytes: f.wastedBytes || 0,
		instances: fileInstances,
		data: {
			...f,
			instances: fileInstances,
		},
	}
}

/**
 * Finds the duplicate a detail page is about. The id in the address may be the row key ("file-12"), the id,
 * the file name or the checksum.
 *
 * @param {Array} duplicates as loaded from /api/v1/duplicates
 * @param {string|null} id
 * @return {object|null} the item the Duplicate Breakdown page shows
 */
export function resolveDuplicateItemById(duplicates, id) {
	if (!id) {
		return null
	}
	const target = decodeURIComponent(String(id)).trim().toLowerCase()

	for (const f of duplicates) {
		const fileItem = toDuplicateItem(f)
		if (
			String(fileItem.uniqueKey).toLowerCase() === target
			|| String(f.id).toLowerCase() === target
			|| String(f.fileName || '').toLowerCase() === target
			|| String(f.checksum || '').toLowerCase() === target
		) {
			return fileItem
		}
	}

	return null
}

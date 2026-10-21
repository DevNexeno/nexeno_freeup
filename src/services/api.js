/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { formatRelativeTime as ncFormatRelativeTime, translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { parseDate } from './format.js'
import { notifyError } from './toast.js'

export function getApiUrl(path) {
	const clean = path.startsWith('/') ? path.substring(1) : path
	return generateUrl('/apps/nexeno_freeup/' + clean)
}

export async function apiRequest(method, path, data = null, params = null) {
	const url = getApiUrl(path)
	const options = {
		method,
		url,
		headers: {
			'Cache-Control': 'no-cache, no-store, must-revalidate',
			Pragma: 'no-cache',
		},
	}
	if (params) {
		options.params = params
	}
	if (data && (method === 'POST' || method === 'PUT')) {
		options.data = data
	}

	const response = await axios.request(options)
	return response.data
}

export function parseInitialData() {
	const el = document.getElementById('nexeno_freeup-initial-data')
	if (el) {
		try {
			const overviewB64 = el.getAttribute('data-overview')
			const historyB64 = el.getAttribute('data-licence-history') || el.getAttribute('data-history')
			const settingsB64 = el.getAttribute('data-settings')
			const parsed = {
				overview: overviewB64 ? JSON.parse(atob(overviewB64)) : null,
				licenceHistory: historyB64 ? JSON.parse(atob(historyB64)) : [],
				settings: settingsB64 ? JSON.parse(atob(settingsB64)) : {},
			}
			return parsed
		} catch {
			// malformed data attributes: use the empty defaults
		}
	}
	return { overview: null, licenceHistory: [], settings: {} }
}

export async function downloadCsvReport(type) {
	try {
		const url = getApiUrl('/api/v1/export')
		const response = await axios.get(url, {
			params: { type },
			responseType: 'blob',
			headers: {
				'Cache-Control': 'no-cache',
			},
		})
		const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' })
		const downloadUrl = window.URL.createObjectURL(blob)
		const link = document.createElement('a')
		link.href = downloadUrl
		link.setAttribute('download', `nexeno-freeup-${type}-${new Date().toISOString().split('T')[0]}.csv`)
		document.body.appendChild(link)
		link.click()
		document.body.removeChild(link)
		window.URL.revokeObjectURL(downloadUrl)
	} catch (e) {
		const denied = e?.response?.status === 403
		notifyError(denied ? t('nexeno_freeup', 'You do not have access to this report.') : t('nexeno_freeup', 'The report could not be downloaded. Please try again.'))
	}
}

/**
 * @param {Date|number|string} input
 * @return {string}
 */
export function formatRelative(input) {
	const d = parseDate(input)
	if (!d) {
		return ''
	}
	try {
		return ncFormatRelativeTime(d)
	} catch {
		return ''
	}
}

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import { ref } from 'vue'
import { apiRequest } from '../services/api.js'
import { fmtTime } from '../services/format.js'
import { notifyError, notifyInfo, notifySuccess } from '../services/toast.js'

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

// The scan is queued, then started at once so it does not wait for the next background-job run.
async function waitForScan(scan) {
	const startedAt = Date.now()
	let current = scan
	let asked = false
	let pause = 2000
	while (current && (current.state === 'queued' || current.state === 'running')) {
		if (Date.now() - startedAt > 30 * 60 * 1000) {
			throw new Error(t('nexeno_freeup', 'The scan is still running in the background. Check again in a few minutes.'))
		}
		if (current.state === 'queued' && !asked) {
			asked = true
			try {
				const res = await apiRequest('POST', '/api/v1/duplicates/scan/run-now')
				current = res.scan
				continue
			} catch {
				// proxy time-out: scan continues, poll its status
			}
		}
		await wait(pause)
		pause = Math.min(pause + 1000, 8000)
		const res = await apiRequest('GET', '/api/v1/duplicates/scan/status')
		current = res.scan
	}
	if (current?.state === 'failed') {
		throw new Error(t('nexeno_freeup', 'The scan failed. Details are in the Nextcloud log.'))
	}
}

function scanErrorText(err) {
	if (err?.response?.status === 429) {
		const retryAt = Number(err.response.data?.retryAt) || 0
		if (retryAt > 0) {
			const time = fmtTime(retryAt * 1000)
			return t('nexeno_freeup', 'You started several scans in a short time. You can run a new scan again at {time}.', { time })
		}
		return t('nexeno_freeup', 'You started several scans in a short time. Please wait a few minutes, then try again.')
	}
	return t('nexeno_freeup', 'Duplicate scan failed: {error}', { error: err?.response?.data?.error || err?.message || t('nexeno_freeup', 'unknown error') })
}

/**
 * @param {ReturnType<import('./useFreeUpData').useFreeUpData>} data
 */
export function useDuplicateScan(data) {
	const isScanning = ref(false)

	async function scanDuplicates(options = {}) {
		data.duplicatesLoading.value = true
		try {
			const minSizeKb = options?.minSizeKb ?? 0
			const res = await apiRequest('POST', `/api/v1/duplicates/scan?minSizeKb=${minSizeKb}`)
			await waitForScan(res.scan)
			await data.loadDuplicates()
			data.forget('overview')
		} finally {
			data.duplicatesLoading.value = false
		}
	}

	async function runFullScan() {
		if (isScanning.value) {
			return
		}
		isScanning.value = true
		notifyInfo(t('nexeno_freeup', 'Scan started. Checking duplicate files and updating account, storage, and licence data in the background.'))
		try {
			await scanDuplicates()
			await Promise.all([
				data.loadOverview(true),
				data.loadAccounts(true),
				data.loadLicences(true),
				// a scan closes requests whose copies are gone and logs an audit entry
				data.loadApprovals(true),
				data.isAdmin.value ? data.loadAudit(true) : Promise.resolve(),
			])
			notifySuccess(t('nexeno_freeup', 'Scan complete. Duplicate files, accounts, storage, and licence metrics are up to date.'))
		} catch (err) {
			notifyError(scanErrorText(err))
		} finally {
			isScanning.value = false
		}
	}

	return { isScanning, runFullScan }
}

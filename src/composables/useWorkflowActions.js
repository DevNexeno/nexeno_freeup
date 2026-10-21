/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { ref } from 'vue'
import { apiRequest } from '../services/api.js'
import { notifyError, notifySuccess } from '../services/toast.js'

function showToast(message, isError = false) {
	if (isError) {
		notifyError(message)
	} else {
		notifySuccess(message)
	}
}

function errorText(e) {
	return e?.response?.data?.error || e?.message || t('nexeno_freeup', 'Something went wrong.')
}

/**
 * @param {ReturnType<import('./useFreeUpData').useFreeUpData>} data
 * @param {import('vue').Ref<string>} currentView
 */
export function useWorkflowActions(data, currentView) {
	const runningArchive = ref(false)

	async function reloadDuplicatesIfShown() {
		if (currentView.value === 'duplicates') {
			await data.loadDuplicates()
		}
	}

	async function decideMany({ action, requestIds, notes, done }) {
		try {
			const res = await apiRequest('POST', '/api/v1/decide-bulk', { decision: action, requestIds, notes })
			await Promise.all([data.loadApprovals(true), data.loadOverview(true)])
			data.forget('accounts', 'licences', 'duplicates')
			await reloadDuplicatesIfShown()
			const failed = res.failed || []
			const base = action === 'approve'
				? n('nexeno_freeup', '{count} request approved', '{count} requests approved', res.done || 0, { count: res.done || 0 })
				: n('nexeno_freeup', '{count} request dismissed', '{count} requests dismissed', res.done || 0, { count: res.done || 0 })
			showToast(failed.length ? `${base} · ${t('nexeno_freeup', '{count} could not be done: {reason}', { count: failed.length, reason: failed[0].error })}` : base, failed.length > 0)
		} catch (e) {
			showToast(t('nexeno_freeup', 'Could not decide: {error}', { error: e?.response?.data?.error || e?.message || 'unknown' }), true)
		} finally {
			done?.()
		}
	}

	// always reload: the server may already have removed a request that is no longer valid
	async function decideOne(path, requestId, notes, doneText) {
		try {
			await apiRequest('POST', path, { requestId, notes })
			showToast(doneText)
		} catch (e) {
			showToast(errorText(e), true)
		} finally {
			await Promise.all([data.loadApprovals(true), data.loadOverview(true)])
			data.forget('accounts', 'overview', 'licences', 'duplicates')
			await reloadDuplicatesIfShown()
		}
	}

	function approve({ requestId, notes }) {
		return decideOne('/api/v1/approve', requestId, notes, t('nexeno_freeup', 'Request approved'))
	}

	function reject({ requestId, notes }) {
		return decideOne('/api/v1/reject', requestId, notes, t('nexeno_freeup', 'Request dismissed'))
	}

	async function runArchive(payload = {}) {
		if (runningArchive.value) {
			return
		}
		runningArchive.value = true
		try {
			const res = await apiRequest('POST', '/api/v1/archive/run', payload?.requestIds ? { requestIds: payload.requestIds } : null)
			await Promise.all([data.loadApprovals(true), data.loadOverview(true), data.loadAccounts(true), data.loadDuplicates()])
			data.forget('licences', 'duplicates')
			const parts = [n('nexeno_freeup', '{count} request completed', '{count} requests completed', res.processed || 0, { count: res.processed || 0 })]
			if (res.waiting) {
				parts.push(n('nexeno_freeup', '{count} still in its notice period', '{count} still in their notice period', res.waiting, { count: res.waiting }))
			}
			if (res.cancelled) {
				parts.push(n('nexeno_freeup', '{count} cancelled because the user logged in', '{count} cancelled because the user logged in', res.cancelled, { count: res.cancelled }))
			}
			showToast(parts.join(' · '))
		} catch (e) {
			showToast(t('nexeno_freeup', 'Processing failed: {error}', { error: e?.response?.data?.error || e?.message || 'unknown' }), true)
		} finally {
			runningArchive.value = false
			payload?.done?.()
		}
	}

	async function resendNotice({ requestId, done }) {
		try {
			const res = await apiRequest('POST', '/api/v1/notice/resend', { requestId })
			const people = res.people || 0
			const emailed = res.emailed || 0
			let message
			if (people <= 1) {
				message = emailed > 0
					? t('nexeno_freeup', 'Reminder sent to user via email.')
					: t('nexeno_freeup', 'Reminder sent to user via in-app notification only.')
			} else if (emailed >= people) {
				message = t('nexeno_freeup', 'Reminder sent to {count} users via email.', { count: people })
			} else if (emailed > 0) {
				message = t('nexeno_freeup', 'Reminder sent to {count} users, {email} of them via email.', { count: people, email: emailed })
			} else {
				message = t('nexeno_freeup', 'Reminder sent to {count} users via in-app notification only.', { count: people })
			}
			showToast(message)
			await data.loadApprovals(true)
		} catch (e) {
			showToast(t('nexeno_freeup', 'Could not send the reminder: {error}', { error: e?.response?.data?.error || e?.message || 'unknown' }), true)
		} finally {
			done?.()
		}
	}

	async function restoreUser(userId) {
		try {
			const res = await apiRequest('POST', '/api/v1/restore', { userId })
			showToast(res?.emailed
				? t('nexeno_freeup', 'Account restored. The user was notified by email.')
				: (res?.emailProblem === 'no_address'
						? t('nexeno_freeup', 'Account restored. The user has no email address, so they will only receive an in-app notification.')
						: t('nexeno_freeup', 'Account restored. The email could not be sent; the user will get an in-app notification.')))
		} catch (e) {
			showToast(errorText(e), true)
		} finally {
			await Promise.all([data.loadAccounts(true), data.loadOverview(true), data.loadApprovals(true)])
			data.forget('licences')
		}
	}

	async function cleanupProposed() {
		await Promise.all([data.loadDuplicates(), data.loadOverview(true), data.loadApprovals(true)])
	}

	function refreshInactive() {
		data.loadAccounts(true)
		data.loadOverview(true)
		data.loadApprovals(true)
	}

	async function sendReport(done) {
		try {
			const res = await apiRequest('POST', '/api/v1/report/send-now')
			notifySuccess(t('nexeno_freeup', 'The licence report was sent to {email}', { email: res.to }))
			if (res.lastSent) {
				data.settings.value = { ...data.settings.value, financeReportLastMonth: res.lastSent }
			}
		} catch (e) {
			notifyError(errorText(e))
		} finally {
			done?.()
		}
	}

	async function saveSettings(payload, done) {
		try {
			const res = await apiRequest('PUT', '/api/v1/settings', payload)
			// server clamps values to the allowed range
			data.settings.value = { ...data.settings.value, ...payload, ...(res?.settings || {}) }
			if (payload.inactivityMonths) {
				data.overview.value = {
					...data.overview.value,
					inactivityThresholdMonths: data.settings.value.inactivityMonths ?? payload.inactivityMonths,
				}
			}
			data.forget('overview', 'accounts', 'licences')
			data.loadOverview(true)
			data.loadLicences(true)
			notifySuccess(t('nexeno_freeup', 'Settings saved'))
			done?.(true)
		} catch (e) {
			notifyError(t('nexeno_freeup', 'Settings could not be saved: {error}', { error: errorText(e) }))
			done?.(false)
		}
	}

	return {
		runningArchive,
		approve,
		reject,
		decideMany,
		runArchive,
		resendNotice,
		restoreUser,
		cleanupProposed,
		refreshInactive,
		sendReport,
		saveSettings,
	}
}

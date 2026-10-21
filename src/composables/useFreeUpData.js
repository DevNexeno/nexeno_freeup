/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, reactive, ref, watch } from 'vue'
import { apiRequest, parseInitialData } from '../services/api.js'
import { setFormatConfig } from '../services/format.js'
import { describeRequest } from '../services/requestText.js'

export function useFreeUpData() {
	const overview = ref({})
	const accounts = ref([])
	const approvals = ref([])
	const queuedApprovals = ref([])
	const completedApprovals = ref([])
	const duplicates = ref([])
	const cleanupStatus = ref({})
	const licenceHistory = ref([])
	const auditLogs = ref([])
	const settings = ref({})
	watch(() => settings.value?.format, (format) => setFormatConfig(format), { deep: true, immediate: true })

	const accountsLoading = ref(false)
	const approvalsLoading = ref(false)
	const duplicatesLoading = ref(false)
	const auditLoading = ref(false)
	const loadedViews = reactive(new Set())

	const isAdmin = computed(() => settings.value?.isAdmin === true)
	const isApprover = computed(() => settings.value?.isApprover === true || isAdmin.value)

	// sidebar badge: accounts inactive for the set period (for one that never logged in, counted from its creation)
	const inactiveCount = computed(() => {
		const o = overview.value || {}
		if (o.inactiveCandidates !== undefined) {
			return Number(o.inactiveCandidates) || 0
		}
		const threshold = Number(settings.value?.inactivityMonths) || 6
		return (accounts.value || []).filter((u) => !u.isExempt && u.status !== 'exempt' && u.enabled && u.status !== 'disabled' && u.status !== 'archived' && (Number(u.inactiveMonths) || 0) >= threshold).length
	})

	const pendingApprovalsCount = computed(() => {
		if (loadedViews.has('approvals')) {
			return approvals.value.length
		}
		if (overview.value?.openApprovals !== undefined) {
			return Number(overview.value.openApprovals) || 0
		}
		return approvals.value.length
	})

	// only approved items past their notice period can run
	const dueQueuedCount = computed(() => queuedApprovals.value.filter((r) => r.status === 'approved' && (!r.archiveAfter || new Date(r.archiveAfter) <= new Date())).length)

	function forget(...views) {
		views.forEach((view) => loadedViews.delete(view))
	}

	async function loadOverview(forceRefresh = false) {
		try {
			const url = forceRefresh ? '/api/v1/overview?refresh=true' : '/api/v1/overview'
			const res = await apiRequest('GET', url)
			if (res.overview) {
				overview.value = res.overview
				loadedViews.add('overview')
			}
			if (res.history) {
				licenceHistory.value = res.history
				loadedViews.add('licences')
			}
		} catch {
			// non-critical
		}
	}

	async function loadAccounts(forceRefresh = false) {
		accountsLoading.value = true
		try {
			const url = forceRefresh ? '/api/v1/accounts?refresh=true' : '/api/v1/accounts'
			const res = await apiRequest('GET', url)
			if (res.accounts) {
				accounts.value = res.accounts
				loadedViews.add('accounts')
			}
		} catch {
			// non-critical
		} finally {
			accountsLoading.value = false
		}
	}

	async function loadApprovals(forceRefresh = false) {
		approvalsLoading.value = true
		try {
			const url = forceRefresh ? '/api/v1/approvals?refresh=true' : '/api/v1/approvals'
			const res = await apiRequest('GET', url)
			if (res.approvals) {
				approvals.value = res.approvals.map(describeRequest)
				queuedApprovals.value = (res.queued || []).map(describeRequest)
				completedApprovals.value = (res.completed || []).map(describeRequest)
				loadedViews.add('approvals')
			}
		} catch {
			// non-critical
		} finally {
			approvalsLoading.value = false
		}
	}

	// `quiet` re-reads in the background without hiding the list that is already on screen
	async function loadDuplicates(quiet = false) {
		if (!quiet || duplicates.value.length === 0) {
			duplicatesLoading.value = true
		}
		try {
			const res = await apiRequest('GET', '/api/v1/duplicates')
			if (res.duplicates) {
				duplicates.value = res.duplicates
				cleanupStatus.value = res.cleanupStatus || {}
				loadedViews.add('duplicates')
			}
		} catch {
			// non-critical
		} finally {
			duplicatesLoading.value = false
		}
	}

	async function loadLicences(forceRefresh = false) {
		try {
			const url = forceRefresh ? '/api/v1/licences?refresh=true' : '/api/v1/licences'
			const res = await apiRequest('GET', url)
			if (res.history) {
				licenceHistory.value = res.history
				loadedViews.add('licences')
			}
		} catch {
			// non-critical
		}
	}

	async function loadAudit(forceRefresh = false) {
		auditLoading.value = true
		try {
			const url = forceRefresh ? '/api/v1/audit?refresh=true' : '/api/v1/audit'
			const res = await apiRequest('GET', url)
			if (res.audit) {
				auditLogs.value = res.audit
				loadedViews.add('audit')
			}
		} catch {
			// non-critical
		} finally {
			auditLoading.value = false
		}
	}

	async function loadSettings() {
		try {
			const res = await apiRequest('GET', '/api/v1/settings')
			if (res.settings) {
				settings.value = { ...settings.value, ...res.settings }
			}
		} catch {
			// non-critical
		}
	}

	// Every visit to a page re-reads its data, so what is shown is never older than the last click.
	function loadViewData(view, forceRefresh = false) {
		if (view === 'overview') {
			loadOverview(forceRefresh)
			loadLicences(forceRefresh)
			return
		}
		if (view === 'licences') {
			loadLicences(forceRefresh)
			return
		}

		if (view === 'accounts' || view === 'inactive-accounts') {
			// The first visit rebuilds the per-account facts; later visits take the server's cached facts (10 minutes) plus
			// live request status, so clicking around never re-reads every user on a large server.
			const rebuild = forceRefresh || !loadedViews.has('accounts')
			// overview after accounts so the storage bar and sidebar count agree
			loadAccounts(rebuild).then(() => loadOverview())
		} else if (view === 'approvals') {
			loadApprovals(true)
		} else if (view === 'duplicates') {
			loadDuplicates(true)
		} else if (view === 'audit' && isAdmin.value) {
			loadAudit()
		} else if (view === 'settings' && isAdmin.value) {
			loadSettings()
		}
	}

	function applyInitialData() {
		const initial = parseInitialData()
		if (initial.overview && Object.keys(initial.overview).length > 0) {
			overview.value = initial.overview
			loadedViews.add('overview')
		}
		if (initial.licenceHistory && initial.licenceHistory.length > 0) {
			licenceHistory.value = initial.licenceHistory
			loadedViews.add('licences')
		}
		if (initial.settings) {
			settings.value = initial.settings
		}

		if (licenceHistory.value.length === 0) {
			loadLicences()
		}
	}

	return {
		overview,
		accounts,
		approvals,
		queuedApprovals,
		completedApprovals,
		duplicates,
		cleanupStatus,
		licenceHistory,
		auditLogs,
		settings,
		accountsLoading,
		approvalsLoading,
		duplicatesLoading,
		auditLoading,
		isAdmin,
		isApprover,
		inactiveCount,
		pendingApprovalsCount,
		dueQueuedCount,
		forget,
		loadOverview,
		loadAccounts,
		loadApprovals,
		loadDuplicates,
		loadLicences,
		loadAudit,
		loadViewData,
		applyInitialData,
	}
}

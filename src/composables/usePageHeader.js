/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, watch } from 'vue'
import { formatRelative } from '../services/api.js'
import { fmtBytes, fmtMonthYear, fmtNumber } from '../services/format.js'
import { licenceWindowMonths, parseMonth } from '../services/licenceWindow.js'

function viewTitle(view) {
	switch (view) {
		case 'inactive-accounts':
			return t('nexeno_freeup', 'Inactive accounts')
		case 'accounts':
			return t('nexeno_freeup', 'Storage by account')
		case 'approvals':
			return t('nexeno_freeup', 'Approvals')
		case 'duplicates':
			return t('nexeno_freeup', 'Duplicate files across the organisation')
		case 'duplicate-detail':
			return t('nexeno_freeup', 'Duplicate Breakdown')
		case 'licences':
			return t('nexeno_freeup', 'Licences in use per month')
		case 'audit':
			return t('nexeno_freeup', 'Recent activity')
		case 'settings':
			return t('nexeno_freeup', 'Settings')
		default:
			return t('nexeno_freeup', 'Account and storage report')
	}
}

/**
 * @param {ReturnType<import('./useFreeUpData').useFreeUpData>} data
 * @param {import('vue').Ref<string>} currentView
 */
export function usePageHeader(data, currentView) {
	const title = computed(() => viewTitle(currentView.value))

	const lastScanText = computed(() => {
		if (data.overview.value?.lastScan) {
			return t('nexeno_freeup', 'last scan {time}', { time: formatRelative(data.overview.value.lastScan) })
		}
		return t('nexeno_freeup', 'no scan yet')
	})

	const licenceSubtitle = computed(() => {
		const months = licenceWindowMonths(data.licenceHistory.value)
		const fmt = (key) => fmtMonthYear(parseMonth(key))
		return t('nexeno_freeup', 'Every enabled account counts as one licence. Licence period: {from} to {to}.', {
			from: fmt(months[0]),
			to: fmt(months[months.length - 1]),
		})
	})

	const storageSubtitle = computed(() => t('nexeno_freeup', '{used} of {total} used', {
		used: fmtBytes(data.overview.value?.totalStorageBytes),
		total: fmtBytes(data.overview.value?.storageCapacityBytes || data.overview.value?.totalStorageBytes),
	}))

	const inactiveSubtitle = computed(() => {
		const threshold = data.settings.value?.inactivityMonths || 6
		const count = data.accounts.value.filter((u) => !u.isExempt && u.status !== 'exempt' && u.enabled && u.status !== 'disabled' && u.status !== 'archived'
			&& (u.status === 'candidate' || (Number(u.inactiveMonths) || 0) >= threshold)).length

		if (count === 0) {
			return t('nexeno_freeup', 'No account has gone more than {months} months without a login.', { months: threshold })
		}
		return n(
			'nexeno_freeup',
			'{count} account has been inactive for {months}+ months. Select accounts to propose their removal.',
			'{count} accounts have been inactive for {months}+ months. Select accounts to propose their removal.',
			count,
			{ count: fmtNumber(count), months: threshold },
		)
	})

	const subtitle = computed(() => {
		switch (currentView.value) {
			case 'overview':
				return lastScanText.value
			case 'inactive-accounts':
				return inactiveSubtitle.value
			case 'audit':
				return t('nexeno_freeup', 'Everything FreeUp did. Entries are kept for {months} months, then deleted.', { months: data.settings.value?.auditRetentionMonths || 12 })
			case 'settings':
				return t('nexeno_freeup', 'Only administrators can change these.')
			case 'licences':
				return licenceSubtitle.value
			case 'approvals':
				return t('nexeno_freeup', 'Requests are proposed, approved, and then completed by FreeUp. No action is taken without approval.')
			case 'duplicates':
				return t('nexeno_freeup', 'Files that exist more than once. Propose a clean-up to keep the oldest copy and replace the remaining copies with read-only shares.')
			case 'accounts':
				return storageSubtitle.value
			default:
				return ''
		}
	})

	const organisation = computed(() => (currentView.value === 'overview' ? data.overview.value?.instanceName || 'Nextcloud' : ''))

	watch(
		[currentView, title, () => data.overview.value?.instanceName],
		([view, pageTitle, inst]) => {
			const instance = inst || 'Nextcloud'
			document.title = view === 'overview' ? `Nexeno FreeUp - ${instance}` : `${pageTitle} - Nexeno FreeUp - ${instance}`
		},
		{ immediate: true },
	)

	return { title, subtitle, organisation }
}

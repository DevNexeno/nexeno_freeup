<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-inactive">
		<Transition name="fade">
			<NcNoteCard v-if="selectedUserIds.length > 0" type="info" class="selection-note">
				<div class="selection-row">
					<span>
						{{ n('nexeno_freeup', '{count} account selected', '{count} accounts selected', selectedUserIds.length, { count: selectedUserIds.length }) }}
						·
						{{ t('nexeno_freeup', '{size} can be reclaimed', { size: selectedStorageFormatted }) }}
					</span>
					<span class="selection-actions">
						<NcButton
							v-if="!allMatchingSelected && selectableAccounts.length > displayedAccounts.length"
							variant="tertiary"
							@click="selectAllMatching">
							{{ t('nexeno_freeup', 'Select all {count} matching', { count: fmtNumber(selectableAccounts.length) }) }}
						</NcButton>
						<NcButton variant="tertiary" @click="clearSelection">
							{{ t('nexeno_freeup', 'Clear selection') }}
						</NcButton>
					</span>
				</div>
			</NcNoteCard>
		</Transition>

		<DataTableCard
			:loading="loading && accounts.length === 0"
			:loadingLabel="t('nexeno_freeup', 'Loading inactive accounts…')"
			:isEmpty="filteredAccounts.length === 0"
			:emptyName="t('nexeno_freeup', 'No inactive accounts found')"
			:emptyDescription="accounts.length === 0
				? t('nexeno_freeup', 'No user accounts detected in the organization.')
				: t('nexeno_freeup', 'No accounts currently match the selected inactivity and group filters.')">
			<template #filters>
				<div class="filter-group">
					<label class="filter-group-label" for="freeup-login-filter">{{ t('nexeno_freeup', 'Inactive for') }}</label>
					<NcSelect
						:modelValue="monthsFilter"
						:placeholder="t('nexeno_freeup', 'Choose a period')"
						class="login-select"
						inputId="freeup-login-filter"
						:aria-label-combobox="t('nexeno_freeup', 'Inactive for')"
						:options="loginOptions"
						:reduce="(o) => o.value"
						:clearable="false"
						label="label"
						@update:modelValue="setMonthsFilter" />
				</div>
				<div class="filter-group">
					<label class="filter-group-label" for="freeup-type-filter">{{ t('nexeno_freeup', 'Group') }}</label>
					<NcSelect
						v-model="typeFilter"
						class="type-select"
						inputId="freeup-type-filter"
						:aria-label-combobox="t('nexeno_freeup', 'Group')"
						:options="typeOptions"
						:reduce="(o) => o.value"
						:clearable="false"
						label="label" />
				</div>
			</template>

			<template #tools>
				<TableSearch
					v-model="search"
					:label="t('nexeno_freeup', 'Search accounts')"
					:placeholder="t('nexeno_freeup', 'Search name, user ID or email')" />
				<NcButton
					variant="tertiary"
					:disabled="filteredAccounts.length === 0"
					:aria-label="t('nexeno_freeup', 'Export {count} accounts as CSV', { count: filteredAccounts.length })"
					:title="t('nexeno_freeup', 'Export list as CSV')"
					@click="exportCsv">
					<template #icon>
						<DownloadOutlineIcon :size="20" />
					</template>
				</NcButton>
			</template>

			<template #emptyIcon>
				<AccountSearchOutlineIcon :size="64" />
			</template>
			<template v-if="hasActiveFilters" #emptyAction>
				<NcButton variant="secondary" @click="resetFilters">
					{{ t('nexeno_freeup', 'Reset filters') }}
				</NcButton>
			</template>

			<table class="inactive-table">
				<thead>
					<tr>
						<th class="col-checkbox">
							<NcCheckboxRadioSwitch
								:modelValue="isPageSelected"
								:indeterminate="isPagePartiallySelected"
								:disabled="selectableOnPage.length === 0"
								:aria-label="t('nexeno_freeup', 'Select all loaded accounts')"
								@update:modelValue="togglePage" />
						</th>
						<SortableTh
							column="name"
							:label="t('nexeno_freeup', 'Account')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							thClass="col-account"
							@sort="setSort" />
						<SortableTh
							column="type"
							:label="t('nexeno_freeup', 'Group')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							thClass="col-type"
							@sort="setSort" />
						<SortableTh
							column="lastLogin"
							:label="t('nexeno_freeup', 'Last login')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							thClass="col-login"
							@sort="setSort" />
						<SortableTh
							column="inactive"
							:label="t('nexeno_freeup', 'Inactive')"
							align="right"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							thClass="col-inactive"
							@sort="setSort" />
						<SortableTh
							column="storage"
							:label="t('nexeno_freeup', 'Storage')"
							align="right"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							thClass="col-storage"
							@sort="setSort" />
						<th class="col-actions">
							<span class="hidden-visually">{{ t('nexeno_freeup', 'Actions') }}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="user in displayedAccounts"
						:key="user.userId"
						:class="{ 'is-row-selected': isSelected(user.userId), 'is-row-locked': isLocked(user) }"
						@click="onRowClick(user)">
						<td class="col-checkbox" @click.stop>
							<span
								v-if="isLocked(user)"
								class="status-icon-pending"
								:title="t('nexeno_freeup', 'Already in approval queue')">
								<ClockOutlineIcon :size="20" />
							</span>
							<NcCheckboxRadioSwitch
								v-else
								:modelValue="isSelected(user.userId)"
								:aria-label="t('nexeno_freeup', 'Select {name}', { name: user.displayName })"
								@update:modelValue="toggleSelect(user.userId)" />
						</td>

						<td class="col-account">
							<div class="account-cell-content">
								<span class="user-avatar-circle" :class="avatarClass(user.type)" aria-hidden="true">
									{{ getUserInitials(user.displayName, user.userId) }}
								</span>
								<div class="account-meta">
									<div class="account-display-name">
										{{ user.displayName }}
									</div>
									<div class="account-uid">
										{{ user.userId }}
									</div>
								</div>
							</div>
						</td>

						<td class="col-type">
							<NcChip :text="typeLabel(user.type)" :variant="chipVariant(user.type)" noClose />
						</td>

						<td class="col-login">
							<template v-if="user.isNeverLoggedIn">
								<span class="login-never">{{ t('nexeno_freeup', 'Never logged in') }}</span>
								<span v-if="user.createdAt > 0" class="account-created-subtext" :title="fmtRelativeDays(user.createdAt)">
									{{ t('nexeno_freeup', 'Created {date}', { date: fmtDate(user.createdAt) }) }}
								</span>
							</template>
							<template v-else>
								<div>{{ formatLoginDate(user.lastLogin) }}</div>
								<span v-if="user.createdAt > 0" class="account-created-subtext">
									{{ t('nexeno_freeup', 'Created {date}', { date: fmtDate(user.createdAt) }) }}
								</span>
							</template>
						</td>

						<td class="col-inactive text-right">
							{{ formatInactiveDuration(user) }}
							<span v-if="user.isNeverLoggedIn" class="account-created-subtext">{{ t('nexeno_freeup', 'since created') }}</span>
						</td>

						<td class="col-storage text-right">
							{{ fmtBytes(user.storageBytes) }}
						</td>

						<td class="col-actions" @click.stop>
							<NcButton
								v-if="!isLocked(user)"
								variant="secondary"
								size="small"
								:aria-label="t('nexeno_freeup', 'Propose removal of {name}', { name: user.displayName })"
								@click="openProposeModal([user.userId])">
								<template #icon>
									<SendOutlineIcon :size="16" />
								</template>
								{{ t('nexeno_freeup', 'Propose removal') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<TableLoadMore :shown="shown" :total="total" @more="loadMore" />
		</DataTableCard>

		<NcDialog
			v-if="showProposeModal"
			:name="t('nexeno_freeup', 'Propose account removal')"
			size="normal"
			:closeOnClickOutside="!submitting"
			:noClose="submitting"
			@closing="closeProposeModal">
			<DialogBody>
				<DialogSummary>
					<DialogStat :label="t('nexeno_freeup', 'Accounts to remove')">
						{{ fmtNumber(proposalIds.length) }}
					</DialogStat>
					<DialogStat :label="t('nexeno_freeup', 'Storage to reclaim')">
						{{ proposalStorageFormatted }}
					</DialogStat>
				</DialogSummary>

				<NcNoteCard v-if="noEmailCount > 0" type="warning">
					{{ n('nexeno_freeup', '{count} account has no email address. After approval, this user will only receive a notification.', '{count} accounts have no email address. After approval, these users will only receive a notification.', noEmailCount, { count: noEmailCount }) }}
				</NcNoteCard>

				<DialogList :rows="previewRows" :total="proposalIds.length" />

				<NcNoteCard type="info">
					{{ settings.noticeEnabled !== false
						? n('nexeno_freeup', 'Once an approver approves the request, each user is notified and has %n day to sign in. If they do not sign in within this period, their account is disabled and its licence is released. No files are deleted.', 'Once an approver approves the request, each user is notified and has %n days to sign in. If they do not sign in within this period, their account is disabled and its licence is released. No files are deleted.', Number(settings.noticeDays) || 14)
						: t('nexeno_freeup', 'Once an approver approves the request, the account is disabled at the next run and its licence is released. No files are deleted.') }}
				</NcNoteCard>

				<NcTextArea
					v-model="proposalReason"
					:label="t('nexeno_freeup', 'Reason for proposal')"
					:placeholder="t('nexeno_freeup', 'Explain why these accounts should be disabled…')"
					:disabled="submitting"
					rows="3" />

				<NcNoteCard v-if="submitFeedback" :type="submitError ? 'error' : 'success'">
					{{ submitFeedback }}
				</NcNoteCard>
			</DialogBody>

			<template #actions>
				<NcButton variant="tertiary" :disabled="submitting" @click="closeProposeModal">
					{{ t('nexeno_freeup', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="submitting || proposalIds.length === 0 || proposalReason.trim() === ''"
					@click="submitProposals">
					<template #icon>
						<NcLoadingIcon v-if="submitting" :size="20" />
						<SendOutlineIcon v-else :size="20" />
					</template>
					{{ submitting ? t('nexeno_freeup', 'Submitting…') : t('nexeno_freeup', 'Submit proposals') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import AccountSearchOutlineIcon from 'vue-material-design-icons/AccountSearchOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import DownloadOutlineIcon from 'vue-material-design-icons/DownloadOutline.vue'
import SendOutlineIcon from 'vue-material-design-icons/SendOutline.vue'
import DialogBody from '../../components/dialog/DialogBody.vue'
import DialogList from '../../components/dialog/DialogList.vue'
import DialogStat from '../../components/dialog/DialogStat.vue'
import DialogSummary from '../../components/dialog/DialogSummary.vue'
import DataTableCard from '../../components/table/DataTableCard.vue'
import SortableTh from '../../components/table/SortableTh.vue'
import TableLoadMore from '../../components/table/TableLoadMore.vue'
import TableSearch from '../../components/table/TableSearch.vue'
import { useProgressiveList } from '../../composables/useProgressiveList.js'
import { apiRequest } from '../../services/api.js'
import { fmtBytes, fmtDate, fmtNumber, fmtRelativeDays } from '../../services/format.js'

const props = defineProps({
	accounts: { type: Array, default: () => [] },
	loading: { type: Boolean, default: false },
	settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['refresh', 'selectionChange'])

const DEFAULT_MONTH_CHOICES = [4, 6, 8, 12, 24]

const MONTH_OPTIONS = computed(() => {
	const choices = props.settings?.limits?.inactivityMonths?.choices
	return Array.isArray(choices) && choices.length > 0 ? choices : DEFAULT_MONTH_CHOICES
})
const defaultMonths = computed(() => {
	const saved = Number(props.settings?.inactivityMonths) || 6
	return MONTH_OPTIONS.value.includes(saved) ? saved : MONTH_OPTIONS.value.reduce((best, m) => (Math.abs(m - saved) < Math.abs(best - saved) ? m : best), MONTH_OPTIONS.value[0])
})
const monthsFilter = ref(defaultMonths.value)

function setMonthsFilter(val) {
	if (val !== null && val !== undefined) {
		monthsFilter.value = val
	}
}

const loginOptions = computed(() => MONTH_OPTIONS.value.map((m) => ({ value: m, label: t('nexeno_freeup', '{months}+ months', { months: m }) })))

const typeFilter = ref('all')
const search = ref('')
const sortColumn = ref(null)
const sortDirection = ref('asc')
const selectedUserIds = ref([])

// Students and Staffs first.
const typeOptions = computed(() => {
	const present = new Set()
	props.accounts.forEach((u) => {
		if (u.type && !u.isExempt && u.enabled && u.status !== 'disabled' && u.status !== 'archived') {
			present.add(u.type)
		}
	})
	const first = ['Student', 'Staff'].filter((x) => present.has(x))
	const rest = [...present].filter((x) => !first.includes(x)).sort((a, b) => a.localeCompare(b))
	return [
		{ value: 'all', label: t('nexeno_freeup', 'All types') },
		...[...first, ...rest].map((type) => ({ value: type, label: typeLabel(type) })),
	]
})

// The server calls an account in no group "User".
function typeLabel(type) {
	if (!type || type === 'User') {
		return t('nexeno_freeup', 'No group')
	}
	if (type === 'Student') {
		return t('nexeno_freeup', 'Students')
	}
	if (type === 'Staff') {
		return t('nexeno_freeup', 'Staffs')
	}
	return type
}

function isLocked(user) {
	return user.status === 'proposed' || user.status === 'approved' || user.status === 'archived'
}

const filteredAccounts = computed(() => {
	const q = search.value.trim().toLowerCase()
	const list = props.accounts.filter((user) => {
		if (user.isExempt || user.status === 'exempt') {
			return false
		}
		if (!user.enabled || user.status === 'disabled' || user.status === 'archived') {
			return false
		}
		if ((Number(user.inactiveMonths) || 0) < Number(monthsFilter.value)) {
			return false
		}
		if (typeFilter.value !== 'all' && (user.type || '') !== typeFilter.value) {
			return false
		}
		if (q) {
			const hay = `${user.displayName} ${user.userId} ${user.email || ''}`.toLowerCase()
			if (!hay.includes(q)) {
				return false
			}
		}
		return true
	})

	if (!sortColumn.value) {
		return list
	}
	const dir = sortDirection.value === 'asc' ? 1 : -1
	const key = (u) => {
		switch (sortColumn.value) {
			case 'name': return (u.displayName || u.userId || '').toLowerCase()
			case 'type': return (u.type || '').toLowerCase()
			case 'lastLogin': return u.isNeverLoggedIn ? 0 : Number(u.lastLogin) || 0
			case 'inactive': return Number(u.inactiveMonths) || 0
			case 'storage': return Number(u.storageBytes) || 0
			default: return 0
		}
	}
	return [...list].sort((a, b) => {
		const ka = key(a)
		const kb = key(b)
		if (ka < kb) {
			return -dir
		}
		if (ka > kb) {
			return dir
		}
		return 0
	})
})

const hasActiveFilters = computed(() => (
	monthsFilter.value !== defaultMonths.value || typeFilter.value !== 'all' || search.value.trim() !== ''
))

function resetFilters() {
	monthsFilter.value = defaultMonths.value
	typeFilter.value = 'all'
	search.value = ''
}

function setSort(column) {
	if (sortColumn.value === column) {
		sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
	} else {
		sortColumn.value = column
		sortDirection.value = 'asc'
	}
}

const { displayed: displayedAccounts, shown, total, loadMore } = useProgressiveList(filteredAccounts)

const selectableAccounts = computed(() => filteredAccounts.value.filter((u) => !isLocked(u)))
const selectableOnPage = computed(() => displayedAccounts.value.filter((u) => !isLocked(u)))

const selectedSet = computed(() => new Set(selectedUserIds.value))

const isPageSelected = computed(() => (
	selectableOnPage.value.length > 0 && selectableOnPage.value.every((u) => selectedSet.value.has(u.userId))
))
const isPagePartiallySelected = computed(() => {
	const count = selectableOnPage.value.filter((u) => selectedSet.value.has(u.userId)).length
	return count > 0 && count < selectableOnPage.value.length
})
const allMatchingSelected = computed(() => (
	selectableAccounts.value.length > 0 && selectableAccounts.value.every((u) => selectedSet.value.has(u.userId))
))

function isSelected(userId) {
	return selectedSet.value.has(userId)
}

function toggleSelect(userId) {
	selectedUserIds.value = isSelected(userId)
		? selectedUserIds.value.filter((id) => id !== userId)
		: [...selectedUserIds.value, userId]
}

function onRowClick(user) {
	if (!isLocked(user)) {
		toggleSelect(user.userId)
	}
}

function togglePage(checked) {
	const ids = new Set(selectedUserIds.value)
	selectableOnPage.value.forEach((u) => (checked ? ids.add(u.userId) : ids.delete(u.userId)))
	selectedUserIds.value = [...ids]
}

function selectAllMatching() {
	const ids = new Set(selectedUserIds.value)
	selectableAccounts.value.forEach((u) => ids.add(u.userId))
	selectedUserIds.value = [...ids]
}

function clearSelection() {
	selectedUserIds.value = []
}

// Follow Settings changes unless another period was picked.
watch(defaultMonths, (next, previous) => {
	if (monthsFilter.value === previous) {
		monthsFilter.value = next
	}
})

watch(selectedUserIds, (ids) => emit('selectionChange', ids))

// Drop selected accounts that left the list.
watch(() => props.accounts, () => {
	const valid = new Set(props.accounts.filter((u) => !isLocked(u)).map((u) => u.userId))
	const kept = selectedUserIds.value.filter((id) => valid.has(id))
	if (kept.length !== selectedUserIds.value.length) {
		selectedUserIds.value = kept
	}
})

const selectedStorageFormatted = computed(() => {
	let total = 0
	props.accounts.forEach((u) => {
		if (selectedSet.value.has(u.userId)) {
			total += Number(u.storageBytes) || 0
		}
	})
	return fmtBytes(total)
})

function getUserInitials(name, uid) {
	const text = (name || uid || '').trim()
	if (!text) {
		return '?'
	}
	const parts = text.split(/\s+/)
	return parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : text.slice(0, 2).toUpperCase()
}

function avatarClass(type) {
	const k = (type || '').toLowerCase()
	if (k === 'student') {
		return 'avatar-student'
	}
	if (k === 'staff') {
		return 'avatar-staff'
	}
	return 'avatar-default'
}

function chipVariant(type) {
	const k = (type || '').toLowerCase()
	if (k === 'student') {
		return 'warning'
	}
	if (k === 'staff') {
		return 'primary'
	}
	return 'tertiary'
}

function formatLoginDate(timestamp) {
	if (!timestamp || timestamp <= 0) {
		return t('nexeno_freeup', 'Never logged in')
	}
	return fmtDate(timestamp)
}

function formatInactiveDuration(user) {
	const m = Math.round(Number(user.inactiveMonths) || 0)
	if (m <= 0) {
		return t('nexeno_freeup', '< 1 month')
	}
	return n('nexeno_freeup', '{m} month', '{m} months', m, { m })
}

function exportCsv() {
	const esc = (v) => {
		const text = String(v ?? '')
		// A leading = + - @ would be run as a formula by Excel; prefix it.
		const safe = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text
		return `"${safe.replace(/"/g, '""')}"`
	}
	const header = [
		t('nexeno_freeup', 'Account'),
		t('nexeno_freeup', 'Name'),
		t('nexeno_freeup', 'Email'),
		t('nexeno_freeup', 'Group'),
		t('nexeno_freeup', 'Created at'),
		t('nexeno_freeup', 'Last login'),
		t('nexeno_freeup', 'Months inactive'),
		t('nexeno_freeup', 'Storage used'),
	]
	const rows = filteredAccounts.value.map((u) => [
		u.userId,
		u.displayName,
		u.email || '',
		typeLabel(u.type),
		u.createdAt > 0 ? fmtDate(u.createdAt) : '',
		u.isNeverLoggedIn ? t('nexeno_freeup', 'Never logged in') : formatLoginDate(u.lastLogin),
		Math.round(Number(u.inactiveMonths) || 0),
		fmtBytes(u.storageBytes),
	])
	const csv = [header, ...rows].map((r) => r.map(esc).join(',')).join('\n')
	const url = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }))
	const a = document.createElement('a')
	a.href = url
	a.download = `inactive-accounts-${monthsFilter.value}m.csv`
	document.body.appendChild(a)
	a.click()
	a.remove()
	URL.revokeObjectURL(url)
}

// The accounts in the open proposal: the ticked ones, or the one whose row button was pressed.
const proposalIds = ref([])
const proposalSet = computed(() => new Set(proposalIds.value))

const previewAccounts = computed(() => props.accounts
	.filter((u) => proposalSet.value.has(u.userId)))

const previewRows = computed(() => previewAccounts.value.map((u) => ({
	key: u.userId,
	title: u.displayName,
	meta: fmtBytes(u.storageBytes),
	user: u.userId,
})))

const proposalStorageFormatted = computed(() => fmtBytes(previewAccounts.value.reduce((sum, u) => sum + (Number(u.storageBytes) || 0), 0)))

// Without email, notices go by notification only.
const noEmailCount = computed(() => (props.settings?.noticeEnabled === false
	? 0
	: previewAccounts.value.filter((u) => !u.email).length))

const showProposeModal = ref(false)
const proposalReason = ref('')
// Pre-filled reason; left unchanged it is omitted from Recent activity.
const suggestedReason = ref('')
const submitting = ref(false)
const submitFeedback = ref('')
const submitError = ref(false)

function suggestReason(ids) {
	const chosen = props.accounts.filter((u) => ids.includes(u.userId))
	const never = chosen.filter((u) => u.isNeverLoggedIn).length
	if (never === chosen.length) {
		return t('nexeno_freeup', 'Never logged in. Proposing to disable the account and release its licence.')
	}
	if (never === 0) {
		return t('nexeno_freeup', 'Inactive for {months}+ months. Proposing to disable the account and release its licence.', { months: monthsFilter.value })
	}
	return t('nexeno_freeup', 'Inactive for {months}+ months, or never logged in. Proposing to disable the account and release its licence.', { months: monthsFilter.value })
}

// `ids`: the accounts to propose; without it, the ticked ones.
function openProposeModal(ids = null) {
	const list = Array.isArray(ids) ? ids : selectedUserIds.value
	if (list.length === 0) {
		return
	}
	proposalIds.value = [...list]
	proposalReason.value = suggestReason(proposalIds.value)
	suggestedReason.value = proposalReason.value
	submitFeedback.value = ''
	submitError.value = false
	showProposeModal.value = true
}

function closeProposeModal() {
	if (submitting.value) {
		return
	}
	showProposeModal.value = false
	submitFeedback.value = ''
}

async function submitProposals() {
	if (proposalIds.value.length === 0) {
		return
	}
	submitting.value = true
	submitFeedback.value = ''
	submitError.value = false

	let succeeded = 0
	const failedIds = []
	for (const uid of [...proposalIds.value]) {
		try {
			await apiRequest('POST', '/api/v1/propose', {
				userId: uid,
				reason: proposalReason.value.trim(),
				reasonIsSuggestion: proposalReason.value.trim() === suggestedReason.value.trim(),
			})
			succeeded++
		} catch {
			failedIds.push(uid)
		}
	}
	submitting.value = false

	if (failedIds.length === 0) {
		submitFeedback.value = n('nexeno_freeup', 'Successfully submitted {count} proposal.', 'Successfully submitted {count} proposals.', succeeded, { count: succeeded })
		const done = new Set(proposalIds.value)
		selectedUserIds.value = selectedUserIds.value.filter((id) => !done.has(id))
		emit('refresh')
		setTimeout(closeProposeModal, 900)
	} else {
		submitError.value = true
		submitFeedback.value = t('nexeno_freeup', '{succeeded} proposal(s) submitted, {failed} failed. Failed accounts stay selected.', { succeeded, failed: failedIds.length })
		proposalIds.value = failedIds
		selectedUserIds.value = failedIds
		emit('refresh')
	}
}

defineExpose({
	openProposeModal,
	selectedUserIds,
	filteredAccounts,
})
</script>

<style lang="scss" scoped>
.freeup-view-inactive {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.filter-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);
}

// Nextcloud gives dropdowns a wide minimum; a fixed width keeps filters on one row.
.filter-group .login-select,
.filter-group .type-select { width: 220px; min-width: 0 !important; }

@media (max-width: 768px) {
  .filter-group {
    flex: 1 1 100%;
    flex-wrap: nowrap;
  }

  .filter-group-label { flex: 0 0 6.5em; }

  .filter-group .login-select,
  .filter-group .type-select { flex: 1 1 0; width: auto; min-width: 0 !important; max-width: 100%; }

}

.filter-group-label {
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
  margin-inline-end: var(--default-grid-baseline);
}

.selection-note {
  margin: 0 0 calc(var(--default-grid-baseline) * 4);
}

.selection-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);
}

.selection-actions {
  display: inline-flex;
  gap: var(--default-grid-baseline);
}

.inactive-table {
  tbody tr {
    cursor: pointer;

    &:hover td { background: var(--color-background-hover); }
    &.is-row-selected td { background: var(--color-primary-element-light); }
    &.is-row-locked { cursor: default; opacity: .7; }
  }
}

.inactive-table .col-checkbox {
  padding-inline: var(--default-grid-baseline);
  width: 56px;
  text-align: center;

  /* Nextcloud's checkbox pads its square to one side; without that padding it lines up with the clock icon. */
  :deep(.checkbox-radio-switch) {
    display: flex;
    justify-content: center;
  }

  :deep(.checkbox-content) {
    padding: 0;
    margin: 0;
  }

  :deep(.checkbox-content .checkbox-content__icon) {
    align-self: center;
    margin-block: 0;
  }
}

.status-icon-pending {
  display: flex;
  justify-content: center;
  color: var(--color-text-maxcontrast);
}

.col-account { min-width: 220px; }

.inactive-table .col-actions {
  width: 1%;
  white-space: nowrap;
  text-align: end;
}

.account-cell-content {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 3);
}

.user-avatar-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
  color: var(--color-main-text);
  background: var(--color-background-dark);

  &.avatar-student {
    background: color-mix(in srgb, var(--color-element-warning, var(--color-warning)) 22%, var(--color-main-background));
  }

  &.avatar-staff {
    background: var(--color-primary-element-light);
  }
}

.account-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.account-display-name { font-weight: 600; }

.account-uid {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}

.login-never {
  color: var(--color-text-error, var(--color-error-text));
  font-weight: 500;
}

.account-created-subtext {
  display: block;
  font-size: 11px;
  color: var(--color-text-maxcontrast);
  margin-top: 2px;
}

.text-right { text-align: end; }

.fade-enter-active,
.fade-leave-active { transition: opacity var(--animation-quick, 100ms); }
.fade-enter-from,
.fade-leave-to { opacity: 0; }

</style>

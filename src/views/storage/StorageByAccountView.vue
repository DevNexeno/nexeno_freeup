<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-accounts">
		<section class="storage-split-card" :aria-label="t('nexeno_freeup', 'Where Your Storage Goes')">
			<h2 class="card-title">
				{{ t('nexeno_freeup', 'Where Your Storage Goes') }}
			</h2>
			<div
				class="split-bar"
				role="img"
				:aria-label="splitAriaLabel">
				<span
					v-for="seg in segments"
					:key="seg.key"
					class="split-segment"
					:class="'seg-' + seg.key"
					:style="{ width: seg.pct + '%' }" />
			</div>
			<ul class="split-legend">
				<li v-for="seg in legendSegments" :key="seg.key">
					<span class="legend-swatch" :class="'seg-' + seg.key" />
					{{ seg.label }} · {{ seg.formatted }}
				</li>
			</ul>
		</section>

		<DataTableCard
			:countLabel="n('nexeno_freeup', '{count} account', '{count} accounts', filteredAccounts.length, { count: fmtNumber(filteredAccounts.length) })"
			:loading="loading && accounts.length === 0"
			:loadingLabel="t('nexeno_freeup', 'Loading accounts…')"
			:isEmpty="filteredAccounts.length === 0"
			:emptyName="accounts.length === 0 ? t('nexeno_freeup', 'No accounts found') : t('nexeno_freeup', 'No matching accounts found')"
			:emptyDescription="accounts.length === 0
				? t('nexeno_freeup', 'No user accounts detected in the organization.')
				: t('nexeno_freeup', 'Try changing your search keywords or filter criteria.')">
			<template #filters>
				<div class="filter-group">
					<label class="filter-group-label" for="freeup-status-filter">{{ t('nexeno_freeup', 'Status') }}</label>
					<NcSelect
						v-model="statusFilter"
						class="status-select"
						inputId="freeup-status-filter"
						:aria-label-combobox="t('nexeno_freeup', 'Status')"
						:options="statusOptions"
						:reduce="(o) => o.value"
						:clearable="false"
						label="label" />
				</div>
			</template>

			<template #tools>
				<TableSearch
					v-model="searchQuery"
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

			<table class="storage-table">
				<thead>
					<tr>
						<SortableTh
							column="user"
							:label="t('nexeno_freeup', 'Account')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<SortableTh
							column="status"
							thClass="col-status"
							:label="t('nexeno_freeup', 'Status')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<SortableTh
							column="storage"
							thClass="col-bar"
							:label="t('nexeno_freeup', 'Storage used')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<SortableTh
							column="files"
							align="right"
							thClass="col-files"
							:label="t('nexeno_freeup', 'Files')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<th v-if="showActionColumn" class="col-actions">
							{{ t('nexeno_freeup', 'Action') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="user in displayedAccounts" :key="user.userId">
						<td class="col-account">
							<div class="account-cell-content">
								<span class="user-avatar-circle" :class="avatarClass(user.type)" aria-hidden="true">
									{{ initials(user) }}
								</span>
								<div class="account-meta">
									<div class="account-display-name">
										{{ user.displayName }}
									</div>
									<div class="account-sub">
										{{ accountSubtitle(user) }}
									</div>
								</div>
							</div>
						</td>

						<td class="col-status">
							<NcChip :text="statusLabel(user)" :variant="statusVariant(user)" noClose />
						</td>

						<td class="col-bar">
							<div class="usage-cell">
								<span class="usage-value">
									{{ fmtBytes(user.storageBytes) }}
									<span v-if="hasQuota(user)" class="usage-of">{{ t('nexeno_freeup', 'of {quota}', { quota: fmtBytes(user.quotaBytes) }) }}</span>
								</span>
								<div
									v-if="hasQuota(user)"
									class="usage-bar"
									role="progressbar"
									:aria-valuenow="quotaPercent(user)"
									aria-valuemin="0"
									aria-valuemax="100"
									:aria-label="t('nexeno_freeup', 'Storage used by {name}', { name: user.displayName })"
									:title="t('nexeno_freeup', '{percent} of the {quota} limit', { percent: fmtPercent(quotaPercent(user) / 100, 0), quota: fmtBytes(user.quotaBytes) })">
									<span
										class="usage-fill"
										:class="'is-' + quotaLevel(user)"
										:style="{ width: Math.min(100, quotaPercent(user)) + '%' }" />
								</div>
								<span v-else class="usage-none">{{ t('nexeno_freeup', 'No storage limit') }}</span>
							</div>
						</td>

						<td class="col-files text-right">
							{{ fmtNumber(user.fileCount ?? 0) }}
						</td>

						<td v-if="showActionColumn" class="col-actions">
							<NcButton v-if="canReactivate(user)" variant="secondary" @click="$emit('restoreUser', user.userId)">
								<template #icon>
									<RestoreIcon :size="20" />
								</template>
								{{ t('nexeno_freeup', 'Restore Account') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<TableLoadMore :shown="shown" :total="total" @more="loadMore" />
		</DataTableCard>
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import AccountSearchOutlineIcon from 'vue-material-design-icons/AccountSearchOutline.vue'
import DownloadOutlineIcon from 'vue-material-design-icons/DownloadOutline.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DataTableCard from '../../components/table/DataTableCard.vue'
import SortableTh from '../../components/table/SortableTh.vue'
import TableLoadMore from '../../components/table/TableLoadMore.vue'
import TableSearch from '../../components/table/TableSearch.vue'
import { useProgressiveList } from '../../composables/useProgressiveList.js'
import { downloadCsvReport } from '../../services/api.js'
import { fmtBytes, fmtNumber, fmtPercent } from '../../services/format.js'

const props = defineProps({
	accounts: { type: Array, default: () => [] },
	overview: { type: Object, default: () => ({}) },
	loading: { type: Boolean, default: false },
	isAdmin: { type: Boolean, default: false },
})

defineEmits(['restoreUser'])

const searchQuery = ref('')
const sortColumn = ref('storage')
const sortDirection = ref('desc')

const STATUS_ORDER = ['active', 'candidate', 'disabled', 'exempt']

function statusKey(user) {
	// An account stays inactive until it is actually disabled, whatever its request says.
	if (['proposed', 'approved', 'archiving'].includes(user.status)) {
		return 'candidate'
	}
	// Disabled by FreeUp or directly in Nextcloud: the same thing for the reader, and both can be restored.
	if (user.status === 'archived') {
		return 'disabled'
	}
	return STATUS_ORDER.includes(user.status) ? user.status : 'active'
}

const statusFilter = ref('all')

function labelForStatus(key) {
	switch (key) {
		case 'candidate': return t('nexeno_freeup', 'Inactive')
		case 'disabled': return t('nexeno_freeup', 'Disabled')
		case 'exempt': return t('nexeno_freeup', 'Protected')
		default: return t('nexeno_freeup', 'Active')
	}
}

const statusOptions = computed(() => {
	const counts = {}
	props.accounts.forEach((u) => {
		counts[statusKey(u)] = (counts[statusKey(u)] || 0) + 1
	})
	return [
		{ value: 'all', label: t('nexeno_freeup', '{status} ({count})', { status: t('nexeno_freeup', 'All statuses'), count: fmtNumber(props.accounts.length) }) },
		...STATUS_ORDER.filter((key) => counts[key] > 0).map((key) => ({
			value: key,
			label: t('nexeno_freeup', '{status} ({count})', { status: labelForStatus(key), count: fmtNumber(counts[key]) }),
		})),
	]
})

watch(statusOptions, (options) => {
	if (!options.some((o) => o.value === statusFilter.value)) {
		statusFilter.value = 'all'
	}
})

function toggleSort(column) {
	if (sortColumn.value === column) {
		sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
	} else {
		sortColumn.value = column
		sortDirection.value = column === 'user' || column === 'status' ? 'asc' : 'desc'
	}
}

function sortKey(u, column) {
	switch (column) {
		case 'user': return (u.displayName || u.userId || '').toLowerCase()
		case 'status': return u.status || ''
		case 'storage': return Number(u.storageBytes) || 0
		case 'files': return Number(u.fileCount) || 0
		default: return 0
	}
}

const filteredAccounts = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	const list = props.accounts.filter((u) => {
		if (statusFilter.value !== 'all' && statusKey(u) !== statusFilter.value) {
			return false
		}
		if (!q) {
			return true
		}
		return `${u.displayName} ${u.userId} ${u.email || ''}`.toLowerCase().includes(q)
	})
	const dir = sortDirection.value === 'asc' ? 1 : -1
	return [...list].sort((a, b) => {
		const ka = sortKey(a, sortColumn.value)
		const kb = sortKey(b, sortColumn.value)
		return ka < kb ? -dir : (ka > kb ? dir : 0)
	})
})

const { displayed: displayedAccounts, shown, total, loadMore } = useProgressiveList(filteredAccounts)

function hasQuota(user) {
	return Number(user.quotaBytes) > 0
}

function quotaPercent(user) {
	if (!hasQuota(user)) {
		return 0
	}
	const percent = ((Number(user.storageBytes) || 0) / Number(user.quotaBytes)) * 100
	return percent > 0 && percent < 1 ? 1 : Math.round(percent)
}

function quotaLevel(user) {
	const percent = quotaPercent(user)
	if (percent >= 90) {
		return 'full'
	}
	if (percent >= 75) {
		return 'high'
	}
	return 'ok'
}

// Same accounts and statuses as the table; each account is in exactly one group.
const SEGMENT_STATUSES = {
	active: ['active'],
	protected: ['exempt'],
	inactive: ['candidate'],
	// Archived accounts are disabled accounts, so one group.
	disabled: ['disabled'],
}

const usedByGroup = computed(() => {
	const totals = Object.fromEntries(Object.keys(SEGMENT_STATUSES).map((key) => [key, 0]))
	props.accounts.forEach((u) => {
		const group = Object.keys(SEGMENT_STATUSES).find((key) => SEGMENT_STATUSES[key].includes(statusKey(u)))
		if (group) {
			totals[group] += Number(u.storageBytes) || 0
		}
	})
	return totals
})

const segments = computed(() => {
	const capacity = Number(props.overview?.storageCapacityBytes) || 0
	if (capacity <= 0) {
		return []
	}
	const used = Object.values(usedByGroup.value).reduce((a, b) => a + b, 0)
	const raw = [
		...Object.keys(SEGMENT_STATUSES).map((key) => ({ key, bytes: usedByGroup.value[key] })),
		{ key: 'free', bytes: Math.max(0, capacity - used) },
	]
	return raw
		.filter((s) => s.bytes > 0)
		.map((s) => ({ ...s, pct: (s.bytes / capacity) * 100 }))
})

const legendSegments = computed(() => {
	const labels = {
		active: t('nexeno_freeup', 'Active accounts'),
		protected: t('nexeno_freeup', 'Protected accounts'),
		inactive: t('nexeno_freeup', 'Inactive accounts'),
		disabled: t('nexeno_freeup', 'Disabled accounts'),
		free: t('nexeno_freeup', 'Available storage'),
	}
	return segments.value.map((s) => ({ ...s, label: labels[s.key], formatted: fmtBytes(s.bytes) }))
})

const splitAriaLabel = computed(() => legendSegments.value.map((s) => `${s.label} ${s.formatted}`).join(', '))

function initials(user) {
	const text = (user.displayName || user.userId || '').trim()
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

function accountSubtitle(user) {
	return user.email || `@${user.userId}`
}

function statusLabel(user) {
	switch (statusKey(user)) {
		case 'candidate': {
			const m = Math.round(Number(user.inactiveMonths) || 0)
			return n('nexeno_freeup', 'Inactive for {m} month', 'Inactive for {m} months', m, { m })
		}
		case 'disabled': return t('nexeno_freeup', 'Disabled')
		case 'exempt': return t('nexeno_freeup', 'Protected')
		default: return t('nexeno_freeup', 'Active')
	}
}

function statusVariant(user) {
	switch (statusKey(user)) {
		case 'candidate': return 'warning'
		case 'disabled': return 'tertiary'
		case 'exempt': return 'secondary'
		default: return 'success'
	}
}

function canReactivate(user) {
	return statusKey(user) === 'disabled' && props.isAdmin
}

const showActionColumn = computed(() => displayedAccounts.value.some(canReactivate))

function exportCsv() {
	downloadCsvReport('accounts')
}
</script>

<style lang="scss" scoped>
.filter-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);
}
.filter-group-label { color: var(--color-text-maxcontrast); font-weight: 600; }
.filter-group .status-select { width: 240px; min-width: 0 !important; }

@media (max-width: 768px) {
  .filter-group { flex: 1 1 100%; flex-wrap: nowrap; }
  .filter-group-label { flex: 0 0 6.5em; }
  .filter-group .status-select { flex: 1 1 0; width: auto; max-width: 100%; }
}

.freeup-view-accounts {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
}

.storage-split-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: calc(var(--default-grid-baseline) * 5);
}

.card-title {
  margin: 0 0 calc(var(--default-grid-baseline) * 3);
  font-size: 18px;
  font-weight: 700;
}

.split-bar {
  display: flex;
  height: 20px;
  border-radius: var(--border-radius-pill, 999px);
  overflow: hidden;
  background: var(--color-background-dark);
}

.split-segment { height: 100%; min-width: 3px; }

// Same colours as the status chips in the table.
.seg-active { background: var(--color-element-success, var(--color-success)); }
.seg-protected { background: var(--color-primary-element-light-hover, var(--color-primary-element-light)); }
.seg-inactive { background: var(--color-element-warning, var(--color-warning)); }
.seg-disabled { background: var(--color-text-maxcontrast); }
.seg-free { background: var(--color-background-dark); border: 1px solid var(--color-border-maxcontrast); }
.split-segment.seg-free { border: none; }

.split-legend {
  display: flex;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 6);
  list-style: none;
  margin: calc(var(--default-grid-baseline) * 3) 0 0;
  padding: 0;

  li {
    display: inline-flex;
    align-items: center;
    gap: calc(var(--default-grid-baseline) * 2);
  }
}

.legend-swatch {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  flex-shrink: 0;
}

.col-account { min-width: 240px; }
.col-status { width: 190px; }
.col-bar { width: 340px; }
.col-files { width: 120px; }
.col-actions { width: 170px; text-align: end; }
.text-right { text-align: end; }

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
  background: var(--color-background-dark);

  &.avatar-student {
    background: color-mix(in srgb, var(--color-element-warning, var(--color-warning)) 22%, var(--color-main-background));
  }

  &.avatar-staff { background: var(--color-primary-element-light); }
}

.account-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.account-display-name { font-weight: 600; }

.account-sub {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}

.usage-cell {
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 1.5);
  max-width: 300px;
}

.usage-value {
  display: flex;
  align-items: baseline;
  gap: calc(var(--default-grid-baseline) * 1.5);
  white-space: nowrap;
  font-weight: 600;
}

.usage-bar {
  width: 100%;
  height: 8px;
  border-radius: var(--border-radius-pill, 999px);
  background: var(--color-background-dark);
  overflow: hidden;
}

.usage-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-primary-element);

  &.is-high { background: var(--color-element-warning, var(--color-warning)); }
  &.is-full { background: var(--color-element-error, var(--color-error)); }
}

.usage-none { color: var(--color-text-maxcontrast); }

.usage-of { font-size: 13px; color: var(--color-text-maxcontrast); }
</style>

<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-duplicates">
		<div class="kpi-grid">
			<div class="kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Potential storage savings') }}
				</div>
				<div class="kpi-value">
					{{ fmtBytes(totalWasted) }}
				</div>
			</div>
			<div class="kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Duplicate Copies') }}
				</div>
				<div class="kpi-value">
					{{ fmtNumber(extraCopies) }}
				</div>
			</div>
			<div class="kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Files with Duplicates') }}
				</div>
				<div class="kpi-value">
					{{ fmtNumber(duplicates.length) }}
				</div>
			</div>
		</div>

		<Transition name="fade">
			<NcNoteCard v-if="selectedIds.length > 0" type="info" class="selection-note">
				<div class="selection-row">
					<span>
						{{ n('nexeno_freeup', '{count} file selected', '{count} files selected', selectedIds.length, { count: selectedIds.length }) }}
						·
						{{ t('nexeno_freeup', '{size} can be freed', { size: fmtBytes(selectedWasted) }) }}
					</span>
					<span class="selection-actions">
						<NcButton
							v-if="!allSelectableSelected && selectableItems.length > displayedItems.length"
							variant="tertiary"
							@click="selectAllMatching">
							{{ t('nexeno_freeup', 'Select all {count} matching', { count: fmtNumber(selectableItems.length) }) }}
						</NcButton>
						<NcButton variant="tertiary" @click="clearSelection">
							{{ t('nexeno_freeup', 'Clear selection') }}
						</NcButton>
					</span>
				</div>
			</NcNoteCard>
		</Transition>

		<DataTableCard
			:countLabel="n('nexeno_freeup', '{count} duplicated file', '{count} duplicated files', filteredItems.length, { count: fmtNumber(filteredItems.length) })"
			:loading="loading"
			:loadingLabel="t('nexeno_freeup', 'Analyzing files…')"
			:isEmpty="filteredItems.length === 0"
			:emptyName="totalItemsCount === 0 ? t('nexeno_freeup', 'No duplicate files found') : t('nexeno_freeup', 'No matching duplicates found')"
			:emptyDescription="totalItemsCount === 0
				? t('nexeno_freeup', 'Run a new scan to look for identical files. Shared folders and starter files are excluded.')
				: t('nexeno_freeup', 'Try changing your search keywords or filter.')">
			<template #tools>
				<TableSearch
					v-model="searchQuery"
					:label="t('nexeno_freeup', 'Search duplicates')"
					:placeholder="t('nexeno_freeup', 'Search file name or user')" />
				<NcButton
					variant="tertiary"
					:aria-label="t('nexeno_freeup', 'Export as CSV')"
					:title="t('nexeno_freeup', 'Export as CSV')"
					@click="exportCsv">
					<template #icon>
						<DownloadOutlineIcon :size="20" />
					</template>
				</NcButton>
			</template>

			<template #emptyIcon>
				<CheckCircleOutlineIcon :size="64" />
			</template>

			<table class="dup-table">
				<colgroup>
					<col class="w-check">
					<col class="w-file">
					<col>
					<col class="w-num">
					<col class="w-num">
					<col class="w-action">
				</colgroup>
				<thead>
					<tr>
						<th class="col-check">
							<NcCheckboxRadioSwitch
								:modelValue="isPageSelected"
								:indeterminate="isPagePartiallySelected"
								:disabled="!canPropose || selectableOnPage.length === 0"
								:aria-label="t('nexeno_freeup', 'Select all loaded files')"
								@update:modelValue="togglePage" />
						</th>
						<SortableTh
							column="name"
							:label="t('nexeno_freeup', 'File name')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<th>{{ t('nexeno_freeup', 'Found in') }}</th>
						<SortableTh
							column="occurrences"
							align="right"
							:label="t('nexeno_freeup', 'Copies')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<SortableTh
							column="wastedBytes"
							align="right"
							:label="t('nexeno_freeup', 'Could free')"
							:sortColumn="sortColumn"
							:sortDirection="sortDirection"
							@sort="toggleSort" />
						<th class="col-action">
							<span class="visually-hidden">{{ t('nexeno_freeup', 'Actions') }}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="item in displayedItems"
						:key="item.uniqueKey"
						:class="{ 'is-row-selected': isSelected(item) }"
						@click="openDetails(item)">
						<td class="col-check" @click.stop>
							<span v-if="statusOf(item) === 'archived'" class="status-icon-pending is-done" :title="t('nexeno_freeup', 'Clean-up done')">
								<CheckCircleOutlineIcon :size="20" />
							</span>
							<span v-else-if="isLocked(item)" class="status-icon-pending" :title="t('nexeno_freeup', 'Already in the approval queue')">
								<ClockOutlineIcon :size="20" />
							</span>
							<NcCheckboxRadioSwitch
								v-else-if="canPropose"
								:modelValue="isSelected(item)"
								:aria-label="t('nexeno_freeup', 'Select {name}', { name: item.name })"
								@update:modelValue="toggleSelect(item)" />
						</td>
						<td class="col-file">
							<div class="file-cell">
								<span class="type-badge" aria-hidden="true">{{ extensionOf(item.name) }}</span>
								<div class="file-meta">
									<div class="file-name" :title="item.name">
										<span class="name-text">{{ item.name }}</span>
									</div>
									<div class="file-sub">
										{{ t('nexeno_freeup', '{size} each', { size: fmtBytes(item.size) }) }}
									</div>
								</div>
							</div>
						</td>

						<td class="col-found">
							<div class="found-users">
								<span class="avatar-stack">
									<NcAvatar
										v-for="uid in item.userList.slice(0, 4)"
										:key="uid"
										:user="uid"
										:size="24"
										:disableMenu="true"
										hideStatus />
								</span>
								<span class="found-users-text">{{ usersText(item) }}</span>
							</div>
						</td>

						<td class="text-right">
							{{ fmtNumber(item.occurrences) }}
						</td>
						<td class="text-right strong">
							{{ fmtBytes(item.wastedBytes) }}
						</td>

						<td class="col-action" @click.stop>
							<span v-if="statusOf(item) === 'proposed'" class="cleanup-state is-pending">
								{{ t('nexeno_freeup', 'Sent for approval') }}
							</span>
							<span v-else-if="statusOf(item) === 'approved' || statusOf(item) === 'archiving'" class="cleanup-state is-pending">
								{{ t('nexeno_freeup', 'Approved · queued') }}
							</span>
							<span v-else-if="statusOf(item) === 'archived'" class="cleanup-state is-done">
								{{ t('nexeno_freeup', 'Clean-up done') }}
							</span>
							<NcButton v-else-if="canPropose" variant="secondary" @click="openProposal(item)">
								{{ t('nexeno_freeup', 'Propose clean-up') }}
							</NcButton>
							<NcButton v-else variant="tertiary" @click="openDetails(item)">
								{{ t('nexeno_freeup', 'View details') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<TableLoadMore :shown="shown" :total="total" @more="loadMore" />
		</DataTableCard>

		<DuplicateProposalDialog
			:items="proposalItems"
			:settings="settings"
			@close="closeProposal"
			@success="handleProposalSuccess" />
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import DownloadOutlineIcon from 'vue-material-design-icons/DownloadOutline.vue'
import DataTableCard from '../../components/table/DataTableCard.vue'
import SortableTh from '../../components/table/SortableTh.vue'
import TableLoadMore from '../../components/table/TableLoadMore.vue'
import TableSearch from '../../components/table/TableSearch.vue'
import DuplicateProposalDialog from './DuplicateProposalDialog.vue'
import { useProgressiveList } from '../../composables/useProgressiveList.js'
import { downloadCsvReport } from '../../services/api.js'
import { extensionOf } from '../../services/duplicates.js'
import { fmtBytes, fmtNumber } from '../../services/format.js'

const props = defineProps({
	duplicates: { type: Array, default: () => [] },
	cleanupStatus: { type: Object, default: () => ({}) },
	loading: { type: Boolean, default: false },
	isAdmin: { type: Boolean, default: false },
	isApprover: { type: Boolean, default: false },
	settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['viewDetails', 'cleanupProposed', 'selectionChange'])

const searchQuery = ref('')
const sortColumn = ref('wastedBytes')
const sortDirection = ref('desc')

const totalWasted = computed(() => props.duplicates.reduce((a, d) => a + (Number(d.wastedBytes) || 0), 0))
const extraCopies = computed(() => props.duplicates.reduce((a, d) => a + Math.max(0, (Number(d.occurrences) || 1) - 1), 0))
const totalItemsCount = computed(() => props.duplicates.length)

function parseInstances(f) {
	let list = f.instances
	if (typeof list === 'string') {
		try {
			list = JSON.parse(list)
		} catch {
			list = []
		}
	}
	if (!Array.isArray(list) && f.instancesJson) {
		try {
			list = JSON.parse(f.instancesJson)
		} catch {
			list = []
		}
	}
	return Array.isArray(list) ? list : []
}

function folderOfInstance(inst) {
	if (inst.folder) {
		return inst.folder
	}
	const clean = (inst.path || '').replace(/^files\//, '')
	const i = clean.lastIndexOf('/')
	return i > -1 ? clean.slice(0, i) : '/'
}

const allItems = computed(() => {
	const items = []

	props.duplicates.forEach((f) => {
		const instances = parseInstances(f)
		const userList = [...new Set(instances.map((i) => i.userId).filter(Boolean))]
		const folders = new Set(instances.map(folderOfInstance))
		const occurrences = f.occurrences || instances.length || 2
		items.push({
			uniqueKey: 'file-' + f.id,
			id: f.id,
			name: f.fileName,
			folder: instances.length ? folderOfInstance(instances[0]) : '/',
			userId: userList[0] || f.userId || '',
			userList: userList.length ? userList : (f.userId ? [f.userId] : []),
			locationCount: folders.size || occurrences,
			checksum: f.checksum,
			keeper: f.keeper || null,
			size: f.size || 0,
			occurrences,
			totalConsumed: (f.size || 0) * occurrences,
			wastedBytes: f.wastedBytes || 0,
			instances,
			data: { ...f, instances },
		})
	})

	return items
})

const filteredItems = computed(() => {
	let list = allItems.value

	const q = searchQuery.value.trim().toLowerCase()
	if (q) {
		list = list.filter((i) => (i.name || '').toLowerCase().includes(q)
			|| (i.folder || '').toLowerCase().includes(q)
			|| i.userList.some((u) => u.toLowerCase().includes(q))
			|| (i.checksum || '').toLowerCase().includes(q))
	}

	const dir = sortDirection.value === 'asc' ? 1 : -1
	const col = sortColumn.value
	return [...list].sort((a, b) => {
		if (col === 'name') {
			return (a.name || '').localeCompare(b.name || '') * dir
		}
		return ((Number(a[col]) || 0) - (Number(b[col]) || 0)) * dir
	})
})

const { displayed: displayedItems, shown, total, loadMore } = useProgressiveList(filteredItems)

function toggleSort(column) {
	if (sortColumn.value === column) {
		sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
	} else {
		sortColumn.value = column
		sortDirection.value = column === 'name' ? 'asc' : 'desc'
	}
}

function usersText(item) {
	const users = item.userList
	if (users.length === 0) {
		return t('nexeno_freeup', 'Unknown owner')
	}
	const shown = users.slice(0, 3).map((u) => '@' + u).join(', ')
	const rest = users.length - 3
	const where = n('nexeno_freeup', '{count} location', '{count} locations', item.locationCount, { count: item.locationCount })
	return rest > 0
		? t('nexeno_freeup', '{names} +{rest} more · {where}', { names: shown, rest, where })
		: `${shown} · ${where}`
}

const canPropose = computed(() => props.isAdmin || props.isApprover)

function refOf(item) {
	return 'file:' + item.checksum
}

function statusOf(item) {
	return props.cleanupStatus[refOf(item)] || ''
}

const selectedIds = ref([])
const selectedSet = computed(() => new Set(selectedIds.value))

function isLocked(item) {
	return ['proposed', 'approved', 'archiving', 'archived'].includes(statusOf(item))
}

const selectableItems = computed(() => (canPropose.value ? filteredItems.value.filter((i) => !isLocked(i)) : []))
const selectableOnPage = computed(() => displayedItems.value.filter((i) => !isLocked(i)))
const isPageSelected = computed(() => selectableOnPage.value.length > 0 && selectableOnPage.value.every((i) => selectedSet.value.has(String(i.id))))
const isPagePartiallySelected = computed(() => {
	const count = selectableOnPage.value.filter((i) => selectedSet.value.has(String(i.id))).length
	return count > 0 && count < selectableOnPage.value.length
})
const allSelectableSelected = computed(() => selectableItems.value.length > 0 && selectableItems.value.every((i) => selectedSet.value.has(String(i.id))))

const selectedItems = computed(() => allItems.value.filter((i) => selectedSet.value.has(String(i.id))))
const selectedWasted = computed(() => selectedItems.value.reduce((a, i) => a + (Number(i.wastedBytes) || 0), 0))

function isSelected(item) {
	return selectedSet.value.has(String(item.id))
}

function toggleSelect(item) {
	const id = String(item.id)
	selectedIds.value = isSelected(item) ? selectedIds.value.filter((x) => x !== id) : [...selectedIds.value, id]
}

function togglePage(checked) {
	const ids = new Set(selectedIds.value)
	selectableOnPage.value.forEach((i) => (checked ? ids.add(String(i.id)) : ids.delete(String(i.id))))
	selectedIds.value = [...ids]
}

function selectAllMatching() {
	const ids = new Set(selectedIds.value)
	selectableItems.value.forEach((i) => ids.add(String(i.id)))
	selectedIds.value = [...ids]
}

function clearSelection() {
	selectedIds.value = []
}

watch(selectedIds, (ids) => emit('selectionChange', ids))

watch(() => [props.duplicates, props.cleanupStatus], () => {
	const valid = new Set(allItems.value.filter((i) => !isLocked(i)).map((i) => String(i.id)))
	const kept = selectedIds.value.filter((id) => valid.has(id))
	if (kept.length !== selectedIds.value.length) {
		selectedIds.value = kept
	}
})

const proposalItems = ref([])

function openProposal(item) {
	proposalItems.value = [item]
}

function openProposeModal() {
	proposalItems.value = selectedItems.value
}

function closeProposal() {
	proposalItems.value = []
}

function handleProposalSuccess({ failedIds = [] } = {}) {
	if (failedIds.length === 0) {
		selectedIds.value = []
		proposalItems.value = []
	} else {
		const failedSet = new Set(failedIds)
		selectedIds.value = selectedIds.value.filter((id) => failedSet.has(id))
		proposalItems.value = proposalItems.value.filter((i) => failedSet.has(String(i.id)))
	}
	emit('cleanupProposed')
}

function openDetails(item) {
	emit('viewDetails', item)
}

function exportCsv() {
	downloadCsvReport('duplicates')
}

defineExpose({ openProposeModal, selectedIds })
</script>

<style lang="scss" scoped>
.freeup-view-duplicates {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: calc(var(--default-grid-baseline) * 4);
}

.kpi-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: calc(var(--default-grid-baseline) * 5);
}
.kpi-label { color: var(--color-text-maxcontrast); margin-bottom: var(--default-grid-baseline); }
.kpi-value { font-size: 28px; font-weight: 700; line-height: 1.2; }

.dup-table {
  min-width: 1016px;
  table-layout: fixed;

  tbody tr {
    cursor: pointer;
    &:hover td { background: var(--color-background-hover); }
    &.is-row-selected td { background: var(--color-primary-element-light); }
  }
}

.w-check { width: 56px; }
.w-file { width: 42%; }
.w-num { width: 100px; }
.w-action { width: 220px; }
.col-action {
  text-align: start;
  padding-inline: calc(var(--default-grid-baseline) * 6) calc(var(--default-grid-baseline) * 3);

  :deep(.button-vue) { width: 100%; }
}
.dup-table .col-check {
  padding-inline: var(--default-grid-baseline);
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
.status-icon-pending { display: flex; justify-content: center; color: var(--color-text-maxcontrast); }
.text-right { text-align: end; }

.selection-note { margin: 0; }
.selection-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: calc(var(--default-grid-baseline) * 2); }
.selection-actions { display: inline-flex; gap: var(--default-grid-baseline); }

.fade-enter-active,
.fade-leave-active { transition: opacity var(--animation-quick, 100ms); }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
.strong { font-weight: 700; }

.file-cell { display: flex; align-items: center; gap: calc(var(--default-grid-baseline) * 3); }

.type-badge.small { min-width: 30px; height: 30px; font-size: 10px; }

.type-badge {
  min-width: 36px;
  height: 36px;
  padding: 0 4px;
  border-radius: var(--border-radius-element, 8px);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  background: var(--color-background-dark);
  flex-shrink: 0;

}

.file-cell { min-width: 0; }
.file-meta { min-width: 0; flex: 1; }

.file-name {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  font-weight: 600;
  min-width: 0;
}

.name-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }

.file-sub {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.found-users-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }

.found-users {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  color: var(--color-text-maxcontrast);
  font-size: 13px;
}

.avatar-stack {
  display: inline-flex;

  > * + * { margin-inline-start: -6px; }
  :deep(.avatardiv) { box-shadow: 0 0 0 2px var(--color-main-background); }
}

.cleanup-state {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: var(--default-clickable-area);
  font-weight: 600;
  white-space: nowrap;
  &.is-pending { color: var(--color-success-text, var(--color-element-success)); }
  &.is-done { color: var(--color-text-maxcontrast); }
}

.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>

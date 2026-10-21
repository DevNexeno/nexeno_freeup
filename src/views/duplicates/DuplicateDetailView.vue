<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dup-detail-view">
		<div class="detail-nav">
			<NcButton variant="secondary" @click="$emit('back')">
				<template #icon>
					<ArrowLeftIcon :size="20" />
				</template>
				{{ t('nexeno_freeup', 'Back to duplicates') }}
			</NcButton>

			<div v-if="item" class="detail-nav-actions">
				<span v-if="status === 'proposed'" class="cleanup-state is-pending">
					{{ t('nexeno_freeup', 'Sent for approval') }}
				</span>
				<span v-else-if="status === 'approved' || status === 'archiving'" class="cleanup-state is-pending">
					{{ t('nexeno_freeup', 'Approved · queued') }}
				</span>
				<span v-else-if="status === 'archived'" class="cleanup-state is-done">
					{{ t('nexeno_freeup', 'Clean-up done') }}
				</span>
				<NcButton
					v-else-if="canPropose"
					variant="primary"
					@click="openProposeModal">
					<template #icon>
						<SendOutlineIcon :size="20" />
					</template>
					{{ t('nexeno_freeup', 'Propose clean-up') }}
				</NcButton>
			</div>
		</div>

		<div v-if="loading && !item" class="detail-state">
			<NcLoadingIcon :size="40" :name="t('nexeno_freeup', 'Loading duplicate details…')" />
		</div>

		<NcEmptyContent
			v-else-if="!item"
			:name="t('nexeno_freeup', 'Duplicate file not found')"
			:description="t('nexeno_freeup', 'This file could not be found. It may have been cleaned up, or the list has changed since the last scan.')">
			<template #icon>
				<FileDocumentOutlineIcon :size="64" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="$emit('back')">
					{{ t('nexeno_freeup', 'Back to duplicates') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<template v-else>
			<section class="summary-card">
				<div class="summary-main">
					<span class="type-badge" aria-hidden="true">{{ extensionOf(itemName) }}</span>
					<div class="summary-text">
						<h2 class="summary-name">
							{{ itemName }}
						</h2>
						<div class="summary-owners">
							<span class="avatar-stack">
								<NcAvatar
									v-for="uid in copyUsers.slice(0, 5)"
									:key="uid"
									:user="uid"
									:size="24"
									:disableMenu="true"
									hideStatus />
							</span>
							<span>{{ copyUsers.map((u) => '@' + u).join(', ') }}</span>
						</div>
						<p class="summary-sub">
							{{ n('nexeno_freeup', '{count} identical copy detected', '{count} identical copies detected', occurrences, { count: occurrences }) }}
						</p>
					</div>
				</div>

				<div class="summary-stats">
					<div class="summary-stat">
						<span class="summary-stat-label">{{ t('nexeno_freeup', 'Size') }}</span>
						<span class="summary-stat-value">{{ fmtBytes(totalSize) }}</span>
					</div>
					<div class="summary-stat">
						<span class="summary-stat-label">{{ t('nexeno_freeup', 'Copies') }}</span>
						<span class="summary-stat-value">{{ fmtNumber(occurrences) }}</span>
					</div>
					<div class="summary-stat">
						<span class="summary-stat-label">{{ t('nexeno_freeup', 'Storage consumed') }}</span>
						<span class="summary-stat-value">{{ fmtBytes(totalConsumed) }}</span>
					</div>
					<div class="summary-stat">
						<span class="summary-stat-label">{{ t('nexeno_freeup', 'Could free') }}</span>
						<span class="summary-stat-value">{{ fmtBytes(wastedBytes) }}</span>
					</div>
				</div>
			</section>

			<section class="detail-card">
				<h3 class="card-title">
					{{ t('nexeno_freeup', 'All copy locations') }}
					<NcChip :text="String(instances.length)" variant="tertiary" noClose />
				</h3>
				<p v-if="item.keeper" class="card-sub">
					{{ keeperReasonText(item.keeper.reason) }}
					{{ t('nexeno_freeup', 'Other users will receive a shared link to access it, while their individual copies will be moved to their trash.') }}
				</p>

				<NcEmptyContent
					v-if="instances.length === 0"
					class="detail-state"
					:name="t('nexeno_freeup', 'No copy locations found')"
					:description="t('nexeno_freeup', 'No physical copies were recorded for this duplicate file.')">
					<template #icon>
						<FileDocumentOutlineIcon :size="64" />
					</template>
				</NcEmptyContent>

				<div v-else class="table-scroll">
					<table class="detail-table">
						<colgroup>
							<col class="w-copy">
							<col>
							<col class="w-owner">
							<col>
							<col class="w-size">
						</colgroup>
						<thead>
							<tr>
								<th>{{ t('nexeno_freeup', 'Result') }}</th>
								<th>{{ t('nexeno_freeup', 'File name') }}</th>
								<th>{{ t('nexeno_freeup', 'Owner') }}</th>
								<th>{{ t('nexeno_freeup', 'Full path') }}</th>
								<th class="num">
									{{ t('nexeno_freeup', 'Size') }}
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="{ inst, i } in copiesList.displayed.value" :key="'inst-' + i">
								<td>
									<NcChip
										:text="isKept(i) ? t('nexeno_freeup', 'Kept') : t('nexeno_freeup', 'Replaced')"
										:variant="isKept(i) ? 'success' : 'secondary'"
										noClose />
								</td>
								<td>
									<div class="file-cell">
										<span class="type-badge small" aria-hidden="true">{{ extensionOf(inst.name || inst.path) }}</span>
										<span class="file-name" :title="inst.name || inst.path">{{ inst.name || inst.path }}</span>
									</div>
								</td>
								<td>
									<span class="owner-cell">
										<NcAvatar
											:user="inst.userId"
											:size="24"
											:disableMenu="true"
											hideStatus />
										@{{ inst.userId }}
									</span>
								</td>
								<td class="path-cell" :title="inst.path || inst.rawPath">
									{{ inst.path || inst.rawPath }}
								</td>
								<td class="num nowrap">
									{{ fmtBytes(totalSize) }}
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<TableLoadMore :shown="copiesList.shown.value" :total="copiesList.total.value" @more="copiesList.loadMore" />
			</section>
		</template>

		<DuplicateProposalDialog
			:items="proposalItems"
			:settings="settings"
			@close="closeProposal"
			@success="handleProposalSuccess" />
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import FileDocumentOutlineIcon from 'vue-material-design-icons/FileDocumentOutline.vue'
import SendOutlineIcon from 'vue-material-design-icons/SendOutline.vue'
import TableLoadMore from '../../components/table/TableLoadMore.vue'
import DuplicateProposalDialog from './DuplicateProposalDialog.vue'
import { useProgressiveList } from '../../composables/useProgressiveList.js'
import { extensionOf, keeperReasonText } from '../../services/duplicates.js'
import { fmtBytes, fmtNumber } from '../../services/format.js'

const props = defineProps({
	item: { type: Object, default: null },
	loading: { type: Boolean, default: false },
	isAdmin: { type: Boolean, default: false },
	isApprover: { type: Boolean, default: false },
	settings: { type: Object, default: () => ({}) },
	cleanupStatus: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['back', 'cleanupProposed'])

const proposalItems = ref([])

const canPropose = computed(() => props.isAdmin || props.isApprover)
const checksum = computed(() => props.item?.checksum || props.item?.data?.checksum || '')
const refKey = computed(() => (checksum.value ? 'file:' + checksum.value : ''))
const status = computed(() => (refKey.value ? (props.cleanupStatus[refKey.value] || '') : ''))

function isKept(i) {
	return !props.item?.keeper || props.item.keeper.index === i
}

const itemName = computed(() => props.item?.name || props.item?.fileName || t('nexeno_freeup', 'Duplicate file'))
const totalSize = computed(() => props.item?.size || 0)

const instances = computed(() => {
	let list = props.item?.instances || props.item?.data?.instances
	if (typeof list === 'string') {
		try {
			list = JSON.parse(list)
		} catch {
			list = []
		}
	}
	return Array.isArray(list) ? list : []
})

const copyUsers = computed(() => {
	const users = [...new Set(instances.value.map((i) => i.userId).filter(Boolean))]
	if (users.length > 0) {
		return users
	}
	return props.item?.userId ? [props.item.userId] : []
})

// The kept copy first, then the others; a file held by hundreds of users loads one page of copies at a time.
const orderedCopies = computed(() => instances.value
	.map((inst, i) => ({ inst, i }))
	.sort((a, b) => Number(isKept(b.i)) - Number(isKept(a.i))))
const copiesList = useProgressiveList(orderedCopies)

const occurrences = computed(() => props.item?.occurrences || instances.value.length || 2)
const totalConsumed = computed(() => props.item?.totalConsumed || (totalSize.value * occurrences.value))
const wastedBytes = computed(() => props.item?.wastedBytes || (totalSize.value * Math.max(0, occurrences.value - 1)))

function openProposeModal() {
	if (props.item) {
		proposalItems.value = [props.item]
	}
}

function closeProposal() {
	proposalItems.value = []
}

function handleProposalSuccess() {
	proposalItems.value = []
	emit('cleanupProposed')
}
</script>

<style lang="scss" scoped>
.dup-detail-view {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
}

.detail-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 3);
}

.detail-nav-actions {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  margin-inline-start: auto;
}

.cleanup-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: var(--default-clickable-area);
  font-weight: 600;
  white-space: nowrap;

  &.is-pending {
    color: var(--color-success-text, var(--color-element-success));
  }

  &.is-done {
    color: var(--color-text-maxcontrast);
  }
}

.detail-state {
  display: flex;
  justify-content: center;
  padding: calc(var(--default-grid-baseline) * 10) 0;
}

.summary-card,
.detail-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
}

.detail-card { overflow: hidden; }

.summary-card {
  padding: calc(var(--default-grid-baseline) * 5);
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: calc(var(--default-grid-baseline) * 5);
}

.summary-main {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 4);
  min-width: 0;
}

.summary-text { min-width: 0; }

.summary-name {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);
  overflow-wrap: anywhere;
  min-width: 0;
}

.summary-owners {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  margin-top: var(--default-grid-baseline);
  color: var(--color-text-maxcontrast);
}

.summary-sub { margin: var(--default-grid-baseline) 0 0; color: var(--color-text-maxcontrast); }

.summary-stats {
  display: flex;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 8);
  margin: 0;

}

.summary-stat { display: flex; flex-direction: column; gap: var(--default-grid-baseline); }
.summary-stat-label { color: var(--color-text-maxcontrast); font-size: 13px; }
.summary-stat-value { font-size: 22px; font-weight: 700; line-height: 1.2; }

.type-badge {
  min-width: 48px;
  height: 48px;
  padding: 0 6px;
  border-radius: var(--border-radius-element, 8px);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  background: var(--color-background-dark);
  flex-shrink: 0;

  &.small { min-width: 36px; height: 36px; font-size: 11px; }
}

.avatar-stack {
  display: inline-flex;
  > * + * { margin-inline-start: -6px; }
  :deep(.avatardiv) { box-shadow: 0 0 0 2px var(--color-main-background); }
}

.card-title {
  margin: 0;
  padding: calc(var(--default-grid-baseline) * 4);
  font-size: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  border-bottom: 1px solid var(--color-border);
}

.table-scroll { width: 100%; overflow-x: auto; }

.detail-table {
  width: 100%;
  min-width: 720px;
  table-layout: fixed;
  border-collapse: collapse;
  text-align: start;

  th, td {
    padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
  }

  th { font-size: 13px; font-weight: 600; white-space: nowrap; padding-block: calc(var(--default-grid-baseline) * 3); }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: var(--color-background-hover); }
  .num { text-align: end; }
  .nowrap { white-space: nowrap; }
}

.w-copy { width: 120px; }
.card-sub { margin: 0; padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4); color: var(--color-text-maxcontrast); border-bottom: 1px solid var(--color-border); }
.w-owner { width: 180px; }

.file-cell { display: flex; align-items: center; gap: calc(var(--default-grid-baseline) * 3); min-width: 0; }
.file-meta { min-width: 0; flex: 1; }
.file-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }

.owner-cell { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline) * 2); font-weight: 600; }

.path-cell {
  font-family: var(--font-family-monospace, monospace);
  font-size: 13px;
  color: var(--color-text-maxcontrast);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

</style>

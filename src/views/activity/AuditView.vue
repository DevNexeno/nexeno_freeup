<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-audit">
		<DataTableCard
			:loading="loading && auditLogs.length === 0"
			:loadingLabel="t('nexeno_freeup', 'Loading activity…')"
			:isEmpty="filteredLogs.length === 0"
			:emptyName="auditLogs.length === 0 ? t('nexeno_freeup', 'No activity recorded yet') : t('nexeno_freeup', 'No matching activity')"
			:emptyDescription="auditLogs.length === 0
				? t('nexeno_freeup', 'Proposals, approvals, disabled accounts and scans will appear here automatically.')
				: t('nexeno_freeup', 'Try changing your search or filter.')">
			<template #filters>
				<div class="toolbar-pills" role="group" :aria-label="t('nexeno_freeup', 'Filter activity')">
					<NcButton
						v-for="tab in tabs"
						:key="tab.id"
						class="filter-pill"
						:variant="activeCategory === tab.id ? 'primary' : 'secondary'"
						:pressed="activeCategory === tab.id"
						@click="activeCategory = tab.id">
						<span class="filter-pill__content">
							{{ tab.label }}
							<NcCounterBubble :count="tab.count" :type="activeCategory === tab.id ? 'highlighted' : undefined" />
						</span>
					</NcButton>
				</div>
			</template>

			<template #tools>
				<NcSelect
					v-model="rangeDays"
					class="range-select"
					inputId="freeup-activity-range"
					:aria-label-combobox="t('nexeno_freeup', 'Time range')"
					:options="rangeOptions"
					:reduce="(o) => o.value"
					:clearable="false"
					label="label" />
				<TableSearch
					v-model="searchQuery"
					:label="t('nexeno_freeup', 'Search activity')"
					:placeholder="t('nexeno_freeup', 'Search name, file or user')" />
			</template>

			<template #emptyIcon>
				<HistoryIcon :size="64" />
			</template>

			<div ref="listEl" class="activity-list">
				<section v-for="day in dayGroups" :key="day.key" class="activity-day">
					<h2 class="activity-day__head">
						<span class="activity-day__title">{{ day.label }}</span>
						<span class="activity-day__count">{{ n('nexeno_freeup', '%n entry', '%n entries', day.entries.length) }}</span>
					</h2>
					<ul class="activity-day__rows">
						<li
							v-for="entry in day.entries"
							:key="entry.id"
							class="activity-row"
							:class="{ 'is-expanded': isExpanded(entry) }">
							<div class="activity-row__main">
								<span class="activity-row__time">{{ formatTime(entry.createdAt) }}</span>
								<span class="activity-row__chip">
									<NcChip :text="entry.meta.category" :variant="entry.meta.variant" noClose />
								</span>
								<div class="activity-row__body">
									<div class="activity-row__title">
										{{ entry.title }}
									</div>
									<div class="activity-row__sub" :class="{ 'is-full': isExpanded(entry) }" :data-entry-id="entry.id">
										{{ entry.subtitle }}
									</div>
								</div>
								<span class="activity-row__who">
									<template v-if="entry.actorId && entry.actorId !== 'system'">
										<NcAvatar
											:user="entry.actorId"
											:displayName="entry.actorName"
											:size="24"
											:disableMenu="true"
											hideStatus />
										<span>{{ entry.actorName || entry.actorId }}</span>
									</template>
									<template v-else>
										<NcAvatar
											:displayName="t('nexeno_freeup', 'System')"
											:isNoUser="true"
											:size="24"
											:disableMenu="true"
											hideStatus />
										<span>{{ t('nexeno_freeup', 'System · automatic') }}</span>
									</template>
								</span>
								<NcButton
									v-if="hasMore(entry)"
									variant="tertiary"
									class="activity-row__toggle"
									:aria-expanded="isExpanded(entry)"
									:aria-label="isExpanded(entry) ? t('nexeno_freeup', 'Hide full details') : t('nexeno_freeup', 'Show full details')"
									@click="toggleExpanded(entry)">
									<template #icon>
										<ChevronUpIcon v-if="isExpanded(entry)" :size="20" />
										<ChevronDownIcon v-else :size="20" />
									</template>
								</NcButton>
							</div>
						</li>
					</ul>
				</section>
			</div>

			<TableLoadMore :shown="shown" :total="total" @more="loadMore" />
		</DataTableCard>

		<div class="retention-note">
			<div class="icon-wrapper">
				<ShieldCheckOutlineIcon :size="16" class="retention-icon" />
			</div>
			<p>{{ t('nexeno_freeup', 'Entries are deleted automatically after the retention period set in Settings (GDPR storage limitation).') }}</p>
		</div>
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import ShieldCheckOutlineIcon from 'vue-material-design-icons/ShieldCheckOutline.vue'
import DataTableCard from '../../components/table/DataTableCard.vue'
import TableLoadMore from '../../components/table/TableLoadMore.vue'
import TableSearch from '../../components/table/TableSearch.vue'
import { useProgressiveList } from '../../composables/useProgressiveList.js'
import { describeAuditEntry } from '../../services/auditEvents.js'
import { fmtTime, fmtWeekdayDate } from '../../services/format.js'

const props = defineProps({
	auditLogs: { type: Array, default: () => [] },
	loading: { type: Boolean, default: false },
})

const searchQuery = ref('')
const activeCategory = ref('all')
const rangeDays = ref(30)
const expanded = ref(new Set())
const isExpanded = (e) => expanded.value.has(e.id)

function toggleExpanded(e) {
	const next = new Set(expanded.value)
	if (next.has(e.id)) {
		next.delete(e.id)
	} else {
		next.add(e.id)
	}
	expanded.value = next
}

// Only truncated rows get the arrow.
const listEl = ref(null)
const truncated = ref(new Set())
const hasMore = (e) => truncated.value.has(String(e.id))

function measureTruncation() {
	const next = new Set()
	listEl.value?.querySelectorAll('[data-entry-id]').forEach((el) => {
		const id = el.dataset.entryId
		if (el.classList.contains('is-full') ? truncated.value.has(id) : el.scrollWidth > el.clientWidth + 1) {
			next.add(id)
		}
	})
	truncated.value = next
}

let resizeObserver = null
onMounted(() => {
	measureTruncation()
	if (window.ResizeObserver && listEl.value) {
		resizeObserver = new ResizeObserver(() => measureTruncation())
		resizeObserver.observe(listEl.value)
	}
})
onBeforeUnmount(() => resizeObserver?.disconnect())

const rangeOptions = computed(() => [
	{ value: 7, label: t('nexeno_freeup', 'Last 7 days') },
	{ value: 30, label: t('nexeno_freeup', 'Last 30 days') },
	{ value: 90, label: t('nexeno_freeup', 'Last 90 days') },
	{ value: 0, label: t('nexeno_freeup', 'All time') },
])

const dayKey = (iso) => (iso ? new Date(iso).toDateString() : '')

const entries = computed(() => [...props.auditLogs]
// Newest first; same-second entries ordered by id.
	.sort((a, b) => (new Date(b.createdAt || 0) - new Date(a.createdAt || 0)) || ((b.id || 0) - (a.id || 0)))
	.map((log) => {
		const m = describeAuditEntry(log)
		return { ...log, meta: m, title: m.title, subtitle: m.detail }
	}))

const inRange = computed(() => {
	if (!rangeDays.value) {
		return entries.value
	}
	const from = Date.now() - rangeDays.value * 86400000
	return entries.value.filter((e) => new Date(e.createdAt || 0).getTime() >= from)
})

const tabs = computed(() => {
	const count = (g) => inRange.value.filter((e) => e.meta.group === g).length
	return [
		{ id: 'all', label: t('nexeno_freeup', 'All'), count: inRange.value.length },
		{ id: 'accounts', label: t('nexeno_freeup', 'Accounts'), count: count('accounts') },
		{ id: 'duplicates', label: t('nexeno_freeup', 'Duplicates'), count: count('duplicates') },
		{ id: 'scans', label: t('nexeno_freeup', 'Scans'), count: count('scans') },
		{ id: 'reports', label: t('nexeno_freeup', 'Reports'), count: count('reports') },
	]
})

const filteredLogs = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return inRange.value
		.filter((e) => activeCategory.value === 'all' || e.meta.group === activeCategory.value)
		.filter((e) => !q || `${e.title} ${e.meta.category} ${e.actorName || ''} ${e.actorId || ''} ${e.targetUser || ''} ${e.targetName || ''} ${e.details || ''}`.toLowerCase().includes(q))
})

const { displayed: displayedLogs, shown, total, loadMore } = useProgressiveList(filteredLogs)

function dayLabel(iso) {
	const d = new Date(iso)
	const full = fmtWeekdayDate(d)
	const now = new Date()
	if (d.toDateString() === now.toDateString()) {
		return t('nexeno_freeup', 'Today · {date}', { date: full })
	}
	return full
}

const dayGroups = computed(() => {
	const groups = []
	for (const e of displayedLogs.value) {
		const key = dayKey(e.createdAt)
		let g = groups[groups.length - 1]
		if (!g || g.key !== key) {
			g = { key, label: dayLabel(e.createdAt), entries: [] }
			groups.push(g)
		}
		g.entries.push(e)
	}
	return groups
})

watch(dayGroups, () => nextTick(measureTruncation))

function formatTime(iso) {
	return fmtTime(iso)
}
</script>

<style lang="scss" scoped>
.freeup-view-audit {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
}

.toolbar-pills {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);
}

.filter-pill__content {
  display: inline-flex;
  flex-direction: row;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  white-space: nowrap;
}

.range-select { width: 180px; }

.activity-list { width: 100%; }

.activity-day__head {
  display: flex;
  align-items: baseline;
  gap: calc(var(--default-grid-baseline) * 2);
  margin: 0;
  padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
  font-size: var(--default-font-size);
  background: var(--color-background-dark);
  border-bottom: 1px solid var(--color-border);
}

.activity-day__title { font-weight: 600; }
.activity-day__count { color: var(--color-text-maxcontrast); font-weight: normal; }

.activity-day__rows { list-style: none; margin: 0; padding: 0; }

.activity-row {
  border-bottom: 1px solid var(--color-border);

  &:hover,
  &.is-expanded {
    background: var(--color-background-hover);

    // Neutral chips share the highlighted-row grey; keep them visible.
    .activity-row__chip :deep(.nc-chip--tertiary) { background-color: var(--color-background-dark); }
  }
}

.activity-day:last-child .activity-row:last-child { border-bottom: none; }

.activity-row__main {
  display: grid;
  grid-template-columns: 72px 130px minmax(0, 1fr) 220px 44px;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 3);
  padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
}

.activity-row__time { color: var(--color-text-maxcontrast); font-variant-numeric: tabular-nums; }
.activity-row__title { font-weight: 600; }

.activity-row__sub {
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;

  &.is-full { white-space: normal; overflow-wrap: anywhere; }
}

.activity-row__who {
  display: inline-flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  min-width: 0;
}

@media (max-width: 768px) {
  .range-select { width: 100%; }

  .activity-row__main {
    grid-template-columns: minmax(0, 1fr) 44px;
    grid-template-areas: 'chip toggle' 'body body' 'who who' 'time time';
    gap: calc(var(--default-grid-baseline) * 2);
  }

  .activity-row__chip { grid-area: chip; }
  .activity-row__body { grid-area: body; }
  .activity-row__who { grid-area: who; }
  .activity-row__time { grid-area: time; }
  .activity-row__toggle { grid-area: toggle; }
  .activity-row__sub { white-space: normal; }
}

.retention-note {
  display: flex;
  align-items: flex-start;
  gap: calc(var(--default-grid-baseline) * 2);
  margin: 0;
  .icon-wrapper{
    margin-top: 2px;
  }
  span{
     color: var(--color-text-maxcontrast);
    font-size: 13px;
    line-height: 1.5;
  }
}
</style>

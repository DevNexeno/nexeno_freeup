<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-licences">
		<div class="kpi-grid">
			<div class="kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Licences in use') }}
				</div>
				<div class="kpi-value">
					{{ fmtNumber(licencesNow) }}
				</div>
			</div>
			<div class="kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Paid but no login in {month}', { month: currentMonthName }) }}
				</div>
				<div class="kpi-value">
					{{ notUsedNow === null ? '–' : fmtNumber(notUsedNow) }}
				</div>
			</div>
			<div class="kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Licences freed by FreeUp') }}
				</div>
				<div class="kpi-value">
					{{ fmtNumber(licencesFreed) }}
				</div>
			</div>
		</div>

		<section class="chart-card">
			<LicenceChart :history="history" />
		</section>

		<div class="table-card">
			<div v-if="rows.length === 0" class="table-empty">
				<NcEmptyContent
					:name="t('nexeno_freeup', 'No history yet')"
					:description="t('nexeno_freeup', 'The first monthly figure is recorded when FreeUp runs its first scan.')">
					<template #icon>
						<ChartBarIcon :size="64" />
					</template>
				</NcEmptyContent>
			</div>
			<div v-else class="table-scroll">
				<table class="licence-table">
					<thead>
						<tr>
							<th>{{ t('nexeno_freeup', 'Month') }}</th>
							<th class="num">
								{{ t('nexeno_freeup', 'Paid licences') }}
							</th>
							<th class="num">
								{{ t('nexeno_freeup', 'Logged in') }}
							</th>
							<th class="num">
								{{ t('nexeno_freeup', 'No login') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in rows" :key="row.month">
							<td>{{ monthName(row.month) }}</td>
							<td class="num">
								{{ fmtNumber(row.licencesInUse) }}
							</td>
							<td class="num">
								{{ row.loggedIn === null ? '–' : fmtNumber(row.loggedIn) }}
							</td>
							<td class="num strong">
								{{ row.notUsed === null ? '–' : fmtNumber(row.notUsed) }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<div class="retention-note">
			<div class="icon-wrapper">
				<ShieldCheckOutlineIcon :size="16" class="retention-icon" />
			</div>
			<p>{{ t('nexeno_freeup', 'Only the last 12 months of data are retained. Older data is deleted automatically to comply with GDPR storage limitation requirements. Figures contain counts only, with no personal data.') }}</p>
		</div>
	</div>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import ShieldCheckOutlineIcon from 'vue-material-design-icons/ShieldCheckOutline.vue'
import LicenceChart from '../../components/charts/LicenceChart.vue'
import { fmtMonth, fmtMonthYear, fmtNumber } from '../../services/format.js'
import { licenceWindowMonths, monthKey, parseMonth } from '../../services/licenceWindow.js'

const props = defineProps({
	history: { type: Array, default: () => [] },
	overview: { type: Object, default: () => ({}) },
})

function monthName(key) {
	return fmtMonthYear(parseMonth(key))
}

const byMonth = computed(() => {
	const map = new Map()
	props.history.forEach((h) => {
		const key = h.month || h.snapshotMonth
		if (!key) {
			return
		}
		map.set(key, {
			month: key,
			licencesInUse: Number(h.licencesInUse ?? ((h.activeAccounts || 0) + (h.inactiveAccounts || 0))),
			loggedIn: h.loggedIn === null || h.loggedIn === undefined ? null : Number(h.loggedIn),
			notUsed: h.notUsed === null || h.notUsed === undefined ? null : Number(h.notUsed),
		})
	})
	return map
})

// Starts at the first recorded month, spans 12; rolls forward after a year.
const windowMonths = computed(() => licenceWindowMonths(props.history))

const rows = computed(() => windowMonths.value
	.map((key) => byMonth.value.get(key))
	.filter(Boolean)
	.reverse())

const currentKey = monthKey(new Date())
const current = computed(() => byMonth.value.get(currentKey) || null)

const licencesNow = computed(() => current.value?.licencesInUse ?? (Number(props.overview?.licencesInUse) || 0))
const notUsedNow = computed(() => current.value?.notUsed ?? null)
const licencesFreed = computed(() => Number(props.overview?.archivedAccounts) || 0)
const currentMonthName = computed(() => fmtMonth(new Date(), 'long'))

</script>

<style lang="scss" scoped>
.freeup-view-licences {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: calc(var(--default-grid-baseline) * 4);
}

.kpi-card,
.chart-card,
.table-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
}

.kpi-card { padding: calc(var(--default-grid-baseline) * 5); }
.kpi-label { color: var(--color-text-maxcontrast); margin-bottom: var(--default-grid-baseline); }
.kpi-value { font-size: 28px; font-weight: 700; line-height: 1.2; }

.chart-card { display: flex; flex-direction: column; min-width: 0; padding: calc(var(--default-grid-baseline) * 5); }

.table-scroll { overflow-x: auto; }

.licence-table {
  width: 100%;
  border-collapse: collapse;
  text-align: start;

  th, td {
    padding: calc(var(--default-grid-baseline) * 3);
    border-bottom: 1px solid var(--color-border);
  }

  th { font-size: 13px; font-weight: 600; white-space: nowrap; }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: var(--color-background-hover); }
  .num { text-align: end; }
  .strong { font-weight: 700; }
}

.table-empty { padding: calc(var(--default-grid-baseline) * 8) 0; }

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

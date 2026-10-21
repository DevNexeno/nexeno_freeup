<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="licence-chart">
		<ul class="chart-legend">
			<li><span class="swatch swatch-paid" />{{ t('nexeno_freeup', 'Paid licences') }}</li>
			<li><span class="swatch swatch-logged" />{{ t('nexeno_freeup', 'Logged in during the month') }}</li>
		</ul>

		<div class="chart-scroll">
			<div class="chart" role="group" :aria-label="t('nexeno_freeup', 'Licences in use per month')">
				<div class="y-axis" aria-hidden="true">
					<span v-for="tick in ticks" :key="tick" :style="{ bottom: (tick / axisMax) * 100 + '%' }">
						{{ axisLabel(tick) }}
					</span>
				</div>

				<div class="plot">
					<div
						v-for="tick in ticks.slice(1)"
						:key="'grid-' + tick"
						class="gridline"
						:style="{ bottom: (tick / axisMax) * 100 + '%' }" />
					<div
						v-for="(slot, index) in slots"
						:key="slot.month"
						class="month-col"
						:class="{ 'is-empty': !slot.row, 'is-active': active === index }"
						tabindex="0"
						:aria-label="slot.summary"
						@mouseenter="active = index"
						@mouseleave="active = null"
						@focus="active = index"
						@blur="active = null">
						<div class="bars">
							<span class="bar bar-paid" :style="{ height: barHeight(slot.paid) + '%' }" />
							<span class="bar bar-logged" :style="{ height: barHeight(slot.loggedIn) + '%' }" />
						</div>

						<span class="month-label">
							{{ slot.label }}
							<span v-if="slot.year" class="month-year">{{ slot.year }}</span>
						</span>

						<div
							v-if="active === index"
							class="chart-tooltip"
							:class="tooltipPosition(index)"
							role="tooltip">
							<div class="tooltip-title">
								{{ slot.fullLabel }}
							</div>
							<template v-if="slot.row">
								<div class="tooltip-row">
									<span class="swatch swatch-paid" />
									<span class="tooltip-label">{{ t('nexeno_freeup', 'Paid licences') }}</span>
									<span class="tooltip-value">{{ fmtNumber(slot.paid) }}</span>
								</div>
								<div class="tooltip-row">
									<span class="swatch swatch-logged" />
									<span class="tooltip-label">{{ t('nexeno_freeup', 'Logged in') }}</span>
									<span class="tooltip-value">{{ slot.row.loggedIn === null ? '–' : fmtNumber(slot.loggedIn) }}</span>
								</div>
								<div class="tooltip-row">
									<span class="swatch swatch-none" />
									<span class="tooltip-label">{{ t('nexeno_freeup', 'No login') }}</span>
									<span class="tooltip-value">{{ slot.row.notUsed === null ? '–' : fmtNumber(slot.row.notUsed) }}</span>
								</div>
							</template>
							<div v-else class="tooltip-empty">
								{{ slot.upcoming ? t('nexeno_freeup', 'Not recorded yet') : t('nexeno_freeup', 'No data recorded') }}
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { fmtCompact, fmtMonth, fmtMonthYear, fmtNumber } from '../../services/format.js'
import { licenceWindowMonths, monthKey, parseMonth } from '../../services/licenceWindow.js'

const props = defineProps({
	history: { type: Array, default: () => [] },
})

const active = ref(null)
const currentKey = monthKey(new Date())

const byMonth = computed(() => {
	const map = new Map()
	props.history.forEach((h) => {
		const key = h.month || h.snapshotMonth
		if (!key) {
			return
		}
		map.set(key, {
			licencesInUse: Number(h.licencesInUse ?? ((h.activeAccounts || 0) + (h.inactiveAccounts || 0))),
			loggedIn: h.loggedIn === null || h.loggedIn === undefined ? null : Number(h.loggedIn),
			notUsed: h.notUsed === null || h.notUsed === undefined ? null : Number(h.notUsed),
		})
	})
	return map
})

const slots = computed(() => licenceWindowMonths(props.history).map((key, index) => {
	const row = byMonth.value.get(key) || null
	const date = parseMonth(key)
	const fullLabel = fmtMonthYear(date)
	const paid = row?.licencesInUse ?? 0
	const loggedIn = row?.loggedIn ?? 0
	return {
		month: key,
		row,
		fullLabel,
		label: fmtMonth(date, 'short'),
		year: index === 0 || date.getMonth() === 0 ? String(date.getFullYear()) : '',
		upcoming: key > currentKey,
		paid,
		loggedIn,
		summary: row
			? t('nexeno_freeup', '{month}: {paid} paid licences, {logged} logged in', { month: fullLabel, paid: fmtNumber(paid), logged: row.loggedIn === null ? '–' : fmtNumber(loggedIn) })
			: t('nexeno_freeup', '{month}: no data', { month: fullLabel }),
	}
}))

// Axis top is 4 equal steps of a round number (1, 2, 5, 10 ...), so ticks are never fractions.
function niceMax(value) {
	if (value <= 0) {
		return 4
	}
	const raw = Math.max(1, value / 4)
	const pow = 10 ** Math.floor(Math.log10(raw))
	const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((x) => x >= raw && Number.isInteger(x)) || pow * 10
	return step * 4
}

const axisLabel = fmtCompact

const axisMax = computed(() => niceMax(Math.max(0, ...slots.value.map((s) => Math.max(s.paid, s.loggedIn)))))
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => (axisMax.value / 4) * i))

function barHeight(value) {
	return value > 0 ? Math.max(1, (value / axisMax.value) * 100) : 0
}

// Keep the tooltip inside the chart: left-aligned at the start, right-aligned at the end.
function tooltipPosition(index) {
	if (index <= 1) {
		return 'is-start'
	}
	if (index >= slots.value.length - 2) {
		return 'is-end'
	}
	return 'is-center'
}
</script>

<style lang="scss" scoped>
.licence-chart {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 5);
  list-style: none;
  margin: 0 0 calc(var(--default-grid-baseline) * 3);
  padding: 0;

  li { display: inline-flex; align-items: center; gap: calc(var(--default-grid-baseline) * 2); }
}

.swatch {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  display: inline-block;
  flex-shrink: 0;

  &.swatch-paid { background: var(--color-primary-element); }
  &.swatch-logged { background: var(--color-primary-element-light-hover, var(--color-primary-element-light)); }
  &.swatch-none { background: var(--color-background-dark); border: 1px solid var(--color-border-dark, var(--color-border)); }
}

.chart-scroll {
  display: flex;
  flex: 1;
  width: 100%;
  overflow-x: auto;
}

.chart {
  display: flex;
  flex: 1;
  min-width: 640px;
  min-height: 300px;
  // Room for axis labels; the scroll container clips anything outside.
  padding-top: 12px;
  padding-bottom: 44px;
}

.y-axis {
  position: sticky;
  inset-inline-start: 0;
  z-index: 1;
  background: var(--color-main-background);
  box-shadow: 0 44px 0 var(--color-main-background);
  width: 56px;
  flex-shrink: 0;
  color: var(--color-text-maxcontrast);
  font-size: 13px;

  span {
    position: absolute;
    right: calc(var(--default-grid-baseline) * 2);
    transform: translateY(50%);
  }
}

.plot {
  position: relative;
  flex: 1;
  display: flex;
  border-bottom: 1px solid var(--color-border-dark, var(--color-border));
}

.gridline {
  position: absolute;
  inset-inline: 0;
  border-top: 1px dotted var(--color-border-dark, var(--color-border));
}

.month-col {
  flex: 1;
  position: relative;
  display: flex;
  justify-content: center;
  min-width: 0;
  border-radius: var(--border-radius-small, 4px);
  outline: none;

  &.is-empty .month-label { opacity: .5; }
  &.is-active { background: var(--color-background-hover); }
  &:focus-visible { box-shadow: 0 0 0 2px var(--color-main-text); }
}

.bars {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 3px;
  width: 100%;
  height: 100%;
}

.bar {
  width: min(22px, 38%);
  border-radius: 3px 3px 0 0;

  &.bar-paid { background: var(--color-primary-element); }
  &.bar-logged { background: var(--color-primary-element-light-hover, var(--color-primary-element-light)); }
}

.month-label {
  position: absolute;
  top: calc(100% + 8px);
  font-size: 13px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.month-year { font-size: 11px; color: var(--color-text-maxcontrast); }

.chart-tooltip {
  position: absolute;
  top: 0;
  z-index: 10;
  min-width: 190px;
  padding: calc(var(--default-grid-baseline) * 3);
  background: var(--color-main-background);
  color: var(--color-main-text);
  border: 1px solid var(--color-border-dark, var(--color-border));
  border-radius: var(--border-radius-large);
  box-shadow: 0 2px 8px var(--color-box-shadow, rgba(0, 0, 0, .25));
  pointer-events: none;
  white-space: nowrap;
  display: flex;
  flex-direction: column;

  &.is-center { inset-inline-start: 50%; transform: translateX(-50%); }
  &.is-start { inset-inline-start: 0; }
  &.is-end { inset-inline-end: 0; }
}

.tooltip-title { font-weight: 700; margin-bottom: calc(var(--default-grid-baseline) * 2); }

.tooltip-row {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  padding-block: 2px;
}

.tooltip-label { color: var(--color-text-maxcontrast); }
.tooltip-value { margin-inline-start: auto; padding-inline-start: calc(var(--default-grid-baseline) * 4); font-weight: 700; }
.tooltip-empty { color: var(--color-text-maxcontrast); }
</style>

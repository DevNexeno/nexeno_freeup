<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-overview">
		<div class="overview-kpi-grid">
			<div class="overview-kpi-card">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Licences in use ({month})', { month: currentMonthShort }) }}
				</div>
				<div class="kpi-value">
					{{ displayLicencesInUse }}
				</div>
				<div v-if="notLoggedInThisMonth" class="kpi-subtext">
					{{ notLoggedInThisMonth }}
				</div>
			</div>

			<button type="button" class="overview-kpi-card is-clickable" @click="$emit('navigate', 'inactive-accounts')">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Inactive accounts') }}
				</div>
				<div class="kpi-value">
					{{ displayInactiveCandidates }}
				</div>
				<div class="kpi-subtext">
					{{ inactiveShareText }}
				</div>
			</button>

			<button type="button" class="overview-kpi-card is-clickable" @click="$emit('navigate', 'storage-accounts')">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Storage used by inactive accounts') }}
				</div>
				<div class="kpi-value">
					{{ displayInactiveStorage }}
				</div>
				<div class="kpi-subtext">
					{{ displayInactiveStorageSubtext }}
				</div>
			</button>

			<button type="button" class="overview-kpi-card is-clickable" @click="$emit('navigate', 'duplicates')">
				<div class="kpi-label">
					{{ t('nexeno_freeup', 'Space used by duplicate files') }}
				</div>
				<div class="kpi-value">
					{{ displayDuplicateWasted }}
				</div>
				<div class="kpi-subtext">
					{{ displayDuplicateCopiesSubtext }}
				</div>
			</button>
		</div>

		<div class="overview-main-grid">
			<div class="overview-panel-card chart-card">
				<div class="panel-header">
					<h2 class="panel-title">
						{{ t('nexeno_freeup', 'Licences in use over the last 12 months') }}
					</h2>
					<NcButton variant="secondary" @click="$emit('navigate', 'licences')">
						{{ t('nexeno_freeup', 'Details') }}
					</NcButton>
				</div>

				<LicenceChart :history="history" />
			</div>

			<div class="overview-panel-card attention-card">
				<h2 class="panel-title">
					{{ t('nexeno_freeup', 'What needs attention') }}
				</h2>

				<div class="attention-list">
					<div v-if="inactiveNumber > 0" class="attention-item">
						<div class="attention-icon-circle icon-amber">
							<AccountClockOutlineIcon :size="20" />
						</div>
						<div class="attention-text">
							<span class="attention-highlight">{{ n('nexeno_freeup', '{count} account', '{count} accounts', inactiveNumber, { count: displayInactiveCandidates }) }}</span>
							<span>&nbsp;{{ attentionInactiveDesc }}</span>
						</div>
						<NcButton variant="secondary" @click="$emit('navigate', 'inactive-accounts')">
							{{ t('nexeno_freeup', 'Review') }}
						</NcButton>
					</div>

					<div v-if="pendingNumber > 0" class="attention-item">
						<div class="attention-icon-circle icon-blue">
							<CheckCircleOutlineIcon :size="20" />
						</div>
						<div class="attention-text">
							<span class="attention-highlight">{{ n('nexeno_freeup', '{count} proposal', '{count} proposals', displayPendingApprovals, { count: displayPendingApprovals }) }}</span>
							<span>&nbsp;{{ t('nexeno_freeup', 'waiting for approval') }}</span>
						</div>
						<NcButton variant="secondary" @click="$emit('navigate', 'approvals')">
							{{ t('nexeno_freeup', 'Open') }}
						</NcButton>
					</div>

					<div v-if="duplicateBytes > 0" class="attention-item">
						<div class="attention-icon-circle icon-purple">
							<FileMultipleOutlineIcon :size="20" />
						</div>
						<div class="attention-text">
							<template v-for="(part, idx) in duplicateAttentionParts" :key="idx">
								<span v-if="part.isHighlight" class="attention-highlight">{{ part.text }}</span>
								<span v-else>{{ part.text }}</span>
							</template>
						</div>
						<NcButton variant="secondary" @click="$emit('navigate', 'duplicates')">
							{{ t('nexeno_freeup', 'See files') }}
						</NcButton>
					</div>

					<p v-if="!hasAttention" class="attention-empty">
						{{ t('nexeno_freeup', 'Nothing needs your attention right now.') }}
					</p>
				</div>

				<div class="recent-activity-section">
					<div class="activity-header">
						<div class="activity-header-label">
							{{ t('nexeno_freeup', 'Recent activity') }}
						</div>
						<NcButton v-if="isAdmin" variant="secondary" @click="$emit('navigate', 'audit')">
							{{ t('nexeno_freeup', 'View all') }}
						</NcButton>
					</div>

					<div v-if="recentActivities.length === 0" class="activity-empty">
						<span>{{ t('nexeno_freeup', 'No recent governance activity recorded.') }}</span>
					</div>
					<div v-else class="activity-list">
						<div
							v-for="(act, ai) in recentActivities"
							:key="'act-' + ai"
							class="activity-row">
							<div class="activity-date">
								{{ act.date }}
							</div>
							<div class="activity-desc">
								<div class="activity-title">
									{{ act.title }}
								</div>
								<div v-if="act.detail" class="activity-detail">
									{{ act.detail }}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import AccountClockOutlineIcon from 'vue-material-design-icons/AccountClockOutline.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import FileMultipleOutlineIcon from 'vue-material-design-icons/FileMultipleOutline.vue'
import LicenceChart from '../../components/charts/LicenceChart.vue'
import { apiRequest, formatRelative } from '../../services/api.js'
import { describeAuditEntry } from '../../services/auditEvents.js'
import { fmtBytes, fmtMonth, fmtNumber, fmtPercent } from '../../services/format.js'
import { notLoggedInText } from '../../services/licenceWindow.js'
import { buildOverviewPdf, downloadPdf, themeAccent } from '../../services/overviewPdf.js'
import { notifyError } from '../../services/toast.js'

const props = defineProps({
	overview: {
		type: Object,
		default: () => ({}),
	},
	history: {
		type: Array,
		default: () => [],
	},
	isAdmin: {
		type: Boolean,
		default: false,
	},
})

defineEmits(['navigate'])

const thresholdMonths = computed(() => props.overview?.inactivityThresholdMonths || 6)

const currentMonthShort = computed(() => fmtMonth(new Date(), 'short'))

const displayLicencesInUse = computed(() => {
	const count = props.overview?.licencesInUse ?? props.overview?.totalAccounts ?? 0
	return fmtNumber(count)
})

// Last month's final figure if it exists, else this month so far.
const notLoggedInThisMonth = computed(() => notLoggedInText(props.history))

const inactiveNumber = computed(() => props.overview?.inactiveCandidates ?? 0)

const inactiveShareText = computed(() => {
	const total = Number(props.overview?.totalAccounts) || 0
	const pct = total > 0 ? Math.round((inactiveNumber.value / total) * 100) : 0
	return t('nexeno_freeup', '{percent}% of all accounts', { percent: pct })
})

const attentionInactiveDesc = computed(() => t('nexeno_freeup', 'inactive for {threshold}+ months', { threshold: thresholdMonths.value }))

const displayInactiveCandidates = computed(() => {
	const count = props.overview?.inactiveCandidates ?? 0
	return fmtNumber(count)
})

const displayInactiveStorage = computed(() => {
	return fmtBytes(props.overview?.inactiveStorageBytes)
})

const displayInactiveStorageSubtext = computed(() => {
	const totalBytes = props.overview?.totalStorageBytes || 0
	const inactBytes = props.overview?.inactiveStorageBytes || 0
	const pct = totalBytes > 0 ? Math.round((inactBytes / totalBytes) * 100) : 0
	return t('nexeno_freeup', '{percent} of {total} used', {
		percent: fmtPercent(pct / 100, 0),
		total: fmtBytes(totalBytes),
	})
})

const displayDuplicateWasted = computed(() => {
	return fmtBytes(props.overview?.duplicateWastedBytes)
})

const displayDuplicateCopiesSubtext = computed(() => {
	const count = props.overview?.extraCopiesCount ?? 0
	return t('nexeno_freeup', '{count} extra copies', { count: fmtNumber(count) })
})

const pendingNumber = computed(() => Number(props.overview?.openApprovals ?? props.overview?.pendingApprovals ?? 0) || 0)
const duplicateBytes = computed(() => Number(props.overview?.duplicateWastedBytes ?? 0) || 0)
const duplicateAttentionParts = computed(() => {
	const full = t('nexeno_freeup', 'Duplicate files occupy {size}. Review them and propose a clean-up.', { size: '___SIZE___' })
	const [before, after] = full.split('___SIZE___')
	return [
		{ text: before, isHighlight: false },
		{ text: displayDuplicateWasted.value, isHighlight: true },
		{ text: after, isHighlight: false },
	]
})
const hasAttention = computed(() => inactiveNumber.value > 0 || pendingNumber.value > 0 || duplicateBytes.value > 0)

const displayPendingApprovals = computed(() => {
	return props.overview?.openApprovals ?? props.overview?.pendingApprovals ?? 0
})

const recentActivities = computed(() => {
	const auditList = props.overview?.recentActivity
	if (Array.isArray(auditList) && auditList.length > 0) {
		return auditList.slice(0, 5).map((item) => {
			const d = describeAuditEntry(item)
			return {
				date: item.createdAt ? formatRelative(item.createdAt) : t('nexeno_freeup', 'Recent'),
				title: d.title,
				detail: d.detail,
			}
		})
	}
	return []
})

// PDF uses fresh figures, not the 30 s cached ones.
const exporting = ref(false)

async function exportReport() {
	if (exporting.value) {
		return
	}
	exporting.value = true
	try {
		let overview = props.overview
		let history = props.history
		try {
			const fresh = await apiRequest('GET', '/api/v1/overview', null, { refresh: true })
			overview = fresh.overview || overview
			history = fresh.history || history
		} catch {
			// The report then uses the figures already on screen.
		}
		const bytes = await buildOverviewPdf({ overview, history, accent: themeAccent() })
		downloadPdf(bytes, `nexeno-freeup-overview-${new Date().toISOString().split('T')[0]}.pdf`)
	} catch (e) {
		// eslint-disable-next-line no-console -- the user sees a toast; the cause is kept for support
		console.error('[nexeno_freeup] PDF export failed', e)
		notifyError(t('nexeno_freeup', 'The report could not be created. Please try again.'))
	} finally {
		exporting.value = false
	}
}

defineExpose({
	exportReport,
	exporting,
})
</script>

<style lang="scss" scoped>
.freeup-view-overview {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.overview-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 20px;

  @media (max-width: 1024px) {
    grid-template-columns: repeat(2, 1fr);
  }

  @media (max-width: 600px) {
    grid-template-columns: 1fr;
  }
}

.overview-kpi-card {
  background: var(--color-main-background, #ffffff);
  border: 1px solid var(--theme-divider, var(--color-border, #e5e7eb));
  border-radius: var(--border-radius-large);
  padding: 18px 20px;
  box-sizing: border-box;
  text-align: start;
  font: inherit;
  color: inherit;
  display: block;
  width: 100%;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;

  &.is-clickable {
    cursor: pointer;

    &:hover,
    &:active,
    &:focus {
      background: var(--color-main-background);
    }

    &:focus-visible {
      outline: 2px solid var(--color-main-text);
      outline-offset: 2px;
    }

    &:hover {
      border-color: var(--color-primary-element, #0082c9);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
  }

  .kpi-label {
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .kpi-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--color-main-text);
    line-height: 1.15;
    letter-spacing: -0.3px;
    margin-bottom: 6px;
  }

  .kpi-subtext {
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.overview-main-grid {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: 16px;

  @media (max-width: 1024px) {
    grid-template-columns: 1fr;
  }
}

.overview-panel-card {
  background: var(--color-main-background, #ffffff);
  border: 1px solid var(--theme-divider, var(--color-border, #e5e7eb));
  border-radius: var(--border-radius-large);
  padding: 20px 22px;
  box-sizing: border-box;
  min-width: 0; // lets the chart scroll inside the card instead of widening the grid column
}

.chart-card {
  display: flex;
  flex-direction: column;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: calc(var(--default-grid-baseline) * 2);
  margin-bottom: calc(var(--default-grid-baseline) * 3);

  > * { flex-shrink: 0; }
  > .panel-title { flex-shrink: 1; min-width: 0; }
}

.panel-title {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--color-main-text);
}

.attention-card {
  display: flex;
  flex-direction: column;
}

.attention-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 14px;
}

.attention-empty {
  margin: 0;
  color: var(--color-text-maxcontrast);
}

.attention-item {
  display: flex;
  align-items: center;
  gap: 12px;

  .attention-icon-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &.icon-amber {
      background: color-mix(in srgb, var(--color-element-warning, var(--color-warning)) 20%, var(--color-main-background));
      color: var(--color-warning-text, var(--color-warning));
    }

    &.icon-blue {
      background: var(--color-primary-element-light);
      color: var(--color-primary-element-light-text);
    }

    &.icon-purple {
      background: var(--color-background-dark);
      color: var(--color-main-text);
    }
  }

  .attention-text {
    flex: 1;
    font-size: var(--default-font-size, 15px);
    color: var(--color-main-text);
    line-height: 1.4;

    .attention-highlight {
      font-weight: 700;
    }
  }

}

.recent-activity-section {
  margin-top: 24px;
  padding-top: 18px;
  border-top: 1px solid var(--theme-divider, var(--color-border, #f3f4f6));

  .activity-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }

  .activity-header-label {
    font-size: var(--default-font-size, 15px);
    font-weight: 700;
    color: var(--color-main-text);
  }

  .activity-empty {
    font-size: 12.5px;
    color: var(--color-text-maxcontrast);
    padding: 10px 0;
    font-style: italic;
  }

  /* One grid for all rows: the time column fits the longest time, so every time stays on one line and the texts line up. */
  .activity-list {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    column-gap: 16px;
    row-gap: 10px;
    align-items: baseline;
  }

  .activity-row {
    display: contents;
    font-size: 14px;
    line-height: 1.4;

    .activity-date {
      white-space: nowrap;
      color: var(--color-text-maxcontrast);
      font-weight: 500;
    }

    .activity-desc {
      color: var(--color-main-text);
      word-break: break-word;
      min-width: 0;
    }

    .activity-title { font-weight: 600; }
    .activity-detail { color: var(--color-text-maxcontrast); }
  }
}
</style>

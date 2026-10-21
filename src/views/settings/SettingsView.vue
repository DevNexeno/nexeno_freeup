<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-settings">
		<section class="settings-card">
			<header class="card-head">
				<h2 class="card-title">
					{{ t('nexeno_freeup', 'Finding inactive accounts') }}
				</h2>
				<p class="card-sub">
					{{ t('nexeno_freeup', 'Set when an account counts as inactive, and which accounts are never listed as inactive.') }}
				</p>
			</header>

			<div class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-inactivity">{{ t('nexeno_freeup', 'Inactive after') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Accounts with no login for longer than this are listed for review. For accounts that never logged in, the time counts from when the account was created.') }}
					</p>
				</div>
				<div class="row-control">
					<NcSelect
						v-model="inactivityMonths"
						inputId="freeup-inactivity"
						:aria-label-combobox="t('nexeno_freeup', 'Inactive after')"
						:options="inactivityOptions"
						:reduce="(o) => o.value"
						:clearable="false"
						label="label" />
				</div>
			</div>

			<div class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-exempt">{{ t('nexeno_freeup', 'Never list') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'These users, and everyone in these groups, are never listed as inactive. Their files still count in storage and duplicate results.') }}
					</p>
				</div>
				<div class="row-control">
					<PrincipalSelect
						v-model:groups="exemptGroups"
						v-model:users="exemptUsers"
						v-model:names="userNames"
						inputId="freeup-exempt"
						:label="t('nexeno_freeup', 'Never list')"
						:placeholder="t('nexeno_freeup', 'Add a user or group, then press Enter')" />
				</div>
			</div>
		</section>

		<section class="settings-card">
			<header class="card-head">
				<h2 class="card-title">
					{{ t('nexeno_freeup', 'Approvals') }}
				</h2>
				<p class="card-sub">
					{{ t('nexeno_freeup', 'Nothing is disabled or replaced until an approver approves it. Administrators can always approve.') }}
				</p>
			</header>

			<div class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-approver">{{ t('nexeno_freeup', 'Inactive accounts') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Users and groups who can approve disabling an account.') }}
					</p>
				</div>
				<div class="row-control">
					<PrincipalSelect
						v-model:groups="approverGroups"
						v-model:users="approverUsers"
						v-model:names="userNames"
						inputId="freeup-approver"
						:label="t('nexeno_freeup', 'Inactive accounts')"
						:placeholder="t('nexeno_freeup', 'Add a user or group, then press Enter')" />
				</div>
			</div>

			<div class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-cleanup-approver">{{ t('nexeno_freeup', 'Duplicate clean-ups') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Users and groups who can approve replacing extra copies with one shared copy.') }}
					</p>
				</div>
				<div class="row-control">
					<PrincipalSelect
						v-model:groups="cleanupApproverGroups"
						v-model:users="cleanupApproverUsers"
						v-model:names="userNames"
						inputId="freeup-cleanup-approver"
						:label="t('nexeno_freeup', 'Duplicate clean-ups')"
						:placeholder="t('nexeno_freeup', 'Same as inactive accounts')" />
				</div>
			</div>
		</section>

		<section class="settings-card">
			<header class="card-head">
				<h2 class="card-title">
					{{ t('nexeno_freeup', 'Notifying users') }}
				</h2>
				<p class="card-sub">
					{{ t('nexeno_freeup', 'Users are notified when a request is approved. FreeUp then waits the days you set below before disabling accounts or cleaning up duplicate files.') }}
				</p>
			</header>

			<div class="row">
				<div class="row-info">
					<span class="row-label">{{ t('nexeno_freeup', 'Notify users first') }}</span>
					<p class="row-help">
						<span class="help-line">{{ t('nexeno_freeup', 'On: Users receive an email and in-app notification when a request is approved. FreeUp waits for the configured period before disabling accounts or replacing copies.') }}</span>
						<span class="help-line">{{ t('nexeno_freeup', 'Off: No advance notifications are sent. Approved requests are processed during the next hourly run.') }}</span>
					</p>
				</div>
				<div class="row-control">
					<NcCheckboxRadioSwitch v-model="noticeEnabled" type="switch">
						{{ noticeEnabled ? t('nexeno_freeup', 'On · users are notified first') : t('nexeno_freeup', 'Off · users are not notified first') }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div v-if="noticeEnabled" class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-notice-days">{{ t('nexeno_freeup', 'Waiting period') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Days between notifying users and disabling accounts or replacing copies.') }}
					</p>
				</div>
				<div class="row-control">
					<div class="inline-field">
						<NcInputField
							id="freeup-notice-days"
							v-model="noticeDaysInput"
							class="number-input"
							type="number"
							:min="limits.noticeDays.min"
							:max="limits.noticeDays.max"
							labelOutside
							:label="t('nexeno_freeup', 'Waiting period')"
							:error="!noticeDaysValid"
							:helperText="rangeText(limits.noticeDays, 'days')" />
						<span class="unit">{{ t('nexeno_freeup', 'days') }}</span>
					</div>
				</div>
			</div>

			<div v-if="noticeEnabled" class="row">
				<div class="row-info">
					<span class="row-label">{{ t('nexeno_freeup', 'How users are notified') }}</span>
				</div>
				<div class="row-control">
					<p class="row-help">
						{{ t('nexeno_freeup', 'By email and a Nextcloud notification, when a request is approved and again when it is done.') }}
					</p>
					<NcNoteCard v-if="settings.mailConfigured === false" type="warning">
						{{ t('nexeno_freeup', 'No email server is set up in Nextcloud, so users only get a notification.') }}
					</NcNoteCard>
				</div>
			</div>
		</section>

		<section class="settings-card">
			<header class="card-head">
				<h2 class="card-title">
					{{ t('nexeno_freeup', 'Reports and history') }}
				</h2>
				<p class="card-sub">
					{{ t('nexeno_freeup', 'The monthly licence report and how long FreeUp keeps its activity history.') }}
				</p>
			</header>

			<div class="row">
				<div class="row-info">
					<span class="row-label">{{ t('nexeno_freeup', 'Monthly licence report') }}</span>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Licence totals on the 1st of every month. No personal data.') }}
					</p>
				</div>
				<div class="row-control">
					<NcCheckboxRadioSwitch v-model="financeEnabled" type="switch">
						{{ financeEnabled ? t('nexeno_freeup', 'On') : t('nexeno_freeup', 'Off') }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div v-if="financeEnabled" class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-finance-email">{{ t('nexeno_freeup', 'Send to') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Email address, for example your finance team.') }}
					</p>
				</div>
				<div class="row-control">
					<NcInputField
						id="freeup-finance-email"
						v-model="financeEmail"
						type="email"
						labelOutside
						:label="t('nexeno_freeup', 'Send to')"
						:placeholder="t('nexeno_freeup', 'billing@yourorganisation.com')"
						:error="financeEmail.trim() !== '' && !financeValid"
						:helperText="financeEmail.trim() !== '' && !financeValid ? t('nexeno_freeup', 'Enter a valid email address') : ''" />
					<NcNoteCard v-if="settings.mailConfigured === false" type="warning">
						{{ t('nexeno_freeup', 'No email server is set up in Nextcloud, so the report cannot be sent.') }}
					</NcNoteCard>
					<div class="report-actions">
						<NcButton variant="secondary" :disabled="sendingReport || financeDirty || !financeValid || financeEmail.trim() === ''" @click="sendReport">
							<template #icon>
								<NcLoadingIcon v-if="sendingReport" :size="20" />
								<EmailOutlineIcon v-else :size="20" />
							</template>
							{{ t('nexeno_freeup', 'Send a test report') }}
						</NcButton>
						<span v-if="financeEmail.trim() === ''" class="row-help">{{ t('nexeno_freeup', 'No address, so no report is sent.') }}</span>
						<span v-else-if="financeDirty" class="row-help">{{ t('nexeno_freeup', 'Save your changes first.') }}</span>
						<span v-else-if="lastReportMonth" class="row-help">{{ t('nexeno_freeup', 'Last sent for {month}', { month: lastReportMonth }) }}</span>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="row-info">
					<label class="row-label" for="freeup-retention">{{ t('nexeno_freeup', 'Keep activity history for') }}</label>
					<p class="row-help">
						{{ t('nexeno_freeup', 'Older entries are deleted automatically. Check your records policy before shortening it.') }}
					</p>
				</div>
				<div class="row-control">
					<NcSelect
						v-model="auditRetentionMonths"
						inputId="freeup-retention"
						:aria-label-combobox="t('nexeno_freeup', 'Keep activity history for')"
						:options="retentionOptions"
						:reduce="(o) => o.value"
						:clearable="false"
						label="label" />
				</div>
			</div>
		</section>

		<section class="settings-card">
			<header class="card-head">
				<h2 class="card-title">
					{{ t('nexeno_freeup', 'Dates, times and numbers') }}
				</h2>
				<!-- eslint-disable-next-line vue/no-v-html -- placeholders are escaped by localeIntro -->
				<p class="card-sub" v-html="localeIntro" />
			</header>

			<div class="row">
				<div class="row-info">
					<span class="row-label">{{ t('nexeno_freeup', 'Locale for Nexeno FreeUp') }}</span>
					<p class="row-help">
						{{ t('nexeno_freeup', 'How dates, times and numbers look in FreeUp, its exports and its emails.') }}
					</p>
				</div>
				<div class="row-control">
					<div class="radio-choice">
						<NcCheckboxRadioSwitch
							v-model="formatMode"
							type="radio"
							name="freeup-format-mode"
							value="person"
							:disabled="!!forcedLocale">
							{{ t('nexeno_freeup', 'Use Nextcloud locale') }}
						</NcCheckboxRadioSwitch>
						<NcChip
							class="recommended"
							variant="success"
							noClose
							:text="t('nexeno_freeup', 'Recommended')" />
					</div>
					<p class="radio-help">
						{{ t('nexeno_freeup', 'Each user sees dates and numbers in their own Nextcloud locale.') }}
					</p>
					<NcCheckboxRadioSwitch
						v-model="formatMode"
						type="radio"
						name="freeup-format-mode"
						value="fixed"
						:disabled="!!forcedLocale">
						{{ t('nexeno_freeup', 'Choose your own locale') }}
					</NcCheckboxRadioSwitch>
					<p class="radio-help">
						{{ t('nexeno_freeup', 'One locale for everyone in FreeUp, including exports and reports.') }}
					</p>
					<NcSelect
						v-if="formatMode === 'fixed' && !forcedLocale"
						v-model="formatLocale"
						inputId="freeup-format-locale"
						:aria-label-combobox="t('nexeno_freeup', 'Locale for Nexeno FreeUp')"
						:options="localeOptions"
						:reduce="(o) => o.value"
						:loading="localesLoading"
						:clearable="false"
						:placeholder="t('nexeno_freeup', 'Search for a locale')"
						label="label" />
					<NcNoteCard v-if="formatMode === 'fixed' && !forcedLocale && !formatLocale" type="warning">
						{{ t('nexeno_freeup', 'Pick the locale to use for everyone.') }}
					</NcNoteCard>
					<NcNoteCard v-if="forcedLocale" type="info">
						{{ t('nexeno_freeup', 'Your server forces one locale. It is always used, so the locale choices below are switched off.') }}
					</NcNoteCard>
				</div>
			</div>

			<template v-if="ownLocale">
				<div class="row">
					<div class="row-info">
						<label class="row-label" for="freeup-format-date">{{ t('nexeno_freeup', 'Date format') }}</label>
						<p class="row-help">
							{{ t('nexeno_freeup', 'The order of day, month and year always follows the locale.') }}
						</p>
					</div>
					<div class="row-control">
						<NcSelect
							v-model="formatDateStyle"
							inputId="freeup-format-date"
							:aria-label-combobox="t('nexeno_freeup', 'Date format')"
							:options="dateStyleOptions"
							:reduce="(o) => o.value"
							:clearable="false"
							label="label" />
					</div>
				</div>

				<div class="row">
					<div class="row-info">
						<span class="row-label">{{ t('nexeno_freeup', 'Time format') }}</span>
						<p class="row-help">
							{{ t('nexeno_freeup', 'Follow the locale, or always use one clock.') }}
						</p>
					</div>
					<div class="row-control clock-choice">
						<NcCheckboxRadioSwitch
							v-for="c in clockOptions"
							:key="c.value"
							v-model="formatClock"
							type="radio"
							name="freeup-format-clock"
							:value="c.value">
							{{ c.label }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<div class="row">
					<div class="row-info">
						<span class="row-label">{{ t('nexeno_freeup', 'Preview') }}</span>
						<p class="row-help">
							{{ previewHelp }}
						</p>
					</div>
					<div class="row-control">
						<div class="preview-box">
							<div class="config-item">
								<span class="config-label">{{ t('nexeno_freeup', 'Date') }}</span>
								<span>{{ preview.date }}</span>
							</div>
							<div class="config-item">
								<span class="config-label">{{ t('nexeno_freeup', 'Time') }}</span>
								<span>{{ preview.time }}</span>
							</div>
							<div class="config-item">
								<span class="config-label">{{ t('nexeno_freeup', 'Number') }}</span>
								<span>{{ preview.number }}</span>
							</div>
							<div class="config-item">
								<span class="config-label">{{ t('nexeno_freeup', 'Storage') }}</span>
								<span>{{ preview.storage }}</span>
							</div>
							<div class="config-item">
								<span class="config-label">{{ t('nexeno_freeup', 'Percentage') }}</span>
								<span>{{ preview.percent }}</span>
							</div>
						</div>
					</div>
				</div>
			</template>
		</section>

		<div class="settings-actions">
			<NcButton variant="primary" :disabled="saving || !isDirty || !noticeDaysValid || !financeValid || !formatValid" @click="save">
				<template #icon>
					<NcLoadingIcon v-if="saving" :size="20" />
					<CheckIcon v-else :size="20" />
				</template>
				{{ saving ? t('nexeno_freeup', 'Saving…') : t('nexeno_freeup', 'Save settings') }}
			</NcButton>
		</div>
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import PrincipalSelect from './PrincipalSelect.vue'
import { apiRequest } from '../../services/api.js'
import { dateStyleExamples, fmtMonthYear, previewFormats } from '../../services/format.js'

const props = defineProps({
	settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['saveSettings', 'sendReport'])

const DEFAULT_LIMITS = {
	inactivityMonths: { min: 1, max: 24 },
	auditRetentionMonths: { min: 1, max: 24 },
	noticeDays: { min: 1, max: 90 },
}
const limits = computed(() => ({ ...DEFAULT_LIMITS, ...(props.settings?.limits || {}) }))

const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v))
function inRange(value, range) {
	const v = Number(value)
	return Number.isInteger(v) && v >= range.min && v <= range.max
}
function toNumber(value, range, fallback) {
	const v = parseInt(value, 10)
	return Number.isFinite(v) ? clamp(v, range.min, range.max) : clamp(fallback, range.min, range.max)
}

function rangeText(range, unit) {
	return unit === 'days'
		? t('nexeno_freeup', 'Min {min} · Max {max} days', range)
		: t('nexeno_freeup', 'Min {min} · Max {max} months', range)
}

const RETENTION_CHOICES = [3, 6, 12, 18, 24]

function monthOptions(choices, range, current, labelFor) {
	const values = [...new Set([...choices.filter((v) => v >= range.min && v <= range.max), current])].sort((a, b) => a - b)
	return values.map((value) => ({ value, label: labelFor(value) }))
}

const inactivityChoices = computed(() => {
	const choices = props.settings?.limits?.inactivityMonths?.choices
	return Array.isArray(choices) && choices.length > 0 ? choices : [4, 6, 8, 12, 24]
})
const inactivityOptions = computed(() => inactivityChoices.value.map((m) => ({
	value: m,
	label: n('nexeno_freeup', '{count} month without login', '{count} months without login', m, { count: m }),
})))

const list = (value) => (Array.isArray(value) ? [...value] : [])

function snapToChoice(value, choices) {
	const list = Array.isArray(choices) && choices.length > 0 ? choices : [4, 6, 8, 12, 24]
	const v = Number(value) || 6
	return list.reduce((best, m) => (Math.abs(m - v) < Math.abs(best - v) ? m : best), list[0])
}

function fromSettings(s) {
	const l = { ...DEFAULT_LIMITS, ...(s.limits || {}) }
	return {
		inactivityMonths: snapToChoice(s.inactivityMonths, s.limits?.inactivityMonths?.choices),
		exemptGroups: Array.isArray(s.exemptGroups) ? [...s.exemptGroups] : ['admin'],
		exemptUsers: list(s.exemptUsers),
		approverGroups: Array.isArray(s.approverGroups) ? [...s.approverGroups] : ['admin'],
		approverUsers: list(s.approverUsers),
		cleanupApproverGroups: list(s.cleanupApproverGroups),
		cleanupApproverUsers: list(s.cleanupApproverUsers),
		auditRetentionMonths: toNumber(s.auditRetentionMonths, l.auditRetentionMonths, 12),
		noticeEnabled: s.noticeEnabled !== false,
		noticeDays: toNumber(s.noticeDays, l.noticeDays, 14),
		financeReportEnabled: s.financeReportEnabled === true,
		financeReportEmail: String(s.financeReportEmail || ''),
		formatMode: s.format?.mode === 'fixed' ? 'fixed' : 'person',
		formatLocale: String(s.format?.locale || ''),
		formatDateStyle: ['short', 'medium', 'long', 'full'].includes(s.format?.dateStyle) ? s.format.dateStyle : 'medium',
		formatClock: ['locale', '24', '12'].includes(s.format?.clock) ? s.format.clock : 'locale',
	}
}

const initial = fromSettings(props.settings)
const inactivityMonths = ref(initial.inactivityMonths)
const exemptGroups = ref(initial.exemptGroups)
const exemptUsers = ref(initial.exemptUsers)
const approverGroups = ref(initial.approverGroups)
const approverUsers = ref(initial.approverUsers)
const cleanupApproverGroups = ref(initial.cleanupApproverGroups)
const cleanupApproverUsers = ref(initial.cleanupApproverUsers)
const userNames = ref({ ...(props.settings?.userNames || {}) })
const auditRetentionMonths = ref(initial.auditRetentionMonths)
const retentionOptions = computed(() => monthOptions(
	RETENTION_CHOICES,
	limits.value.auditRetentionMonths,
	auditRetentionMonths.value,
	(m) => n('nexeno_freeup', '{count} month', '{count} months', m, { count: m }),
))

const noticeEnabled = ref(initial.noticeEnabled)
const noticeDaysInput = ref(String(initial.noticeDays))
const noticeDaysValid = computed(() => inRange(noticeDaysInput.value, limits.value.noticeDays))
const noticeDays = computed(() => toNumber(noticeDaysInput.value, limits.value.noticeDays, 14))
const financeEnabled = ref(initial.financeReportEnabled)
const financeEmail = ref(initial.financeReportEmail)
const financeValid = computed(() => !financeEnabled.value || financeEmail.value.trim() === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(financeEmail.value.trim()))
const formatMode = ref(initial.formatMode)
const formatLocale = ref(initial.formatLocale)
const formatDateStyle = ref(initial.formatDateStyle)
const formatClock = ref(initial.formatClock)
const sendingReport = ref(false)
const saved = ref(JSON.stringify(initial))

const saving = ref(false)

const current = computed(() => ({
	inactivityMonths: inactivityMonths.value,
	exemptGroups: exemptGroups.value,
	exemptUsers: exemptUsers.value,
	approverGroups: approverGroups.value,
	approverUsers: approverUsers.value,
	cleanupApproverGroups: cleanupApproverGroups.value,
	cleanupApproverUsers: cleanupApproverUsers.value,
	auditRetentionMonths: auditRetentionMonths.value,
	noticeEnabled: noticeEnabled.value,
	noticeDays: noticeDays.value,
	financeReportEnabled: financeEnabled.value,
	financeReportEmail: financeEmail.value.trim(),
	formatMode: formatMode.value,
	formatLocale: formatLocale.value,
	formatDateStyle: formatDateStyle.value,
	formatClock: formatClock.value,
}))

const isDirty = computed(() => JSON.stringify(current.value) !== saved.value)

// "Send a copy now" uses the saved address; save changes first.
const financeDirty = computed(() => {
	const was = JSON.parse(saved.value)
	return was.financeReportEnabled !== financeEnabled.value || (was.financeReportEmail || '') !== financeEmail.value.trim()
})

const formatInfo = computed(() => props.settings?.format || {})
const forcedLocale = computed(() => formatInfo.value.forcedLocale || '')
const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

const serverInfo = computed(() => ({
	yourLocale: '',
	yourLocaleName: '',
	...(formatInfo.value.server || {}),
}))

// Same wording and link as other Nexeno apps: the reader's own locale, and where to change it in Nextcloud.
const localeIntro = computed(() => t('nexeno_freeup', 'FreeUp uses the language and locale setting from Nextcloud. Your Nextcloud locale is {locale}. You can change it in {linkStart}Personal settings › Personal info › Locale{linkEnd}.', {
	locale: `<strong>${escapeHtml(serverInfo.value.yourLocaleName || serverInfo.value.yourLocale)} (${escapeHtml(serverInfo.value.yourLocale)})</strong>`,
	linkStart: `<a class="config-link-inline" href="${escapeHtml(generateUrl('/settings/user') + '#vue-locale-section')}">`,
	linkEnd: '</a>',
}, undefined, { escape: false }))

// Locale list (~750 entries) is only needed here, so fetch it lazily.
const locales = ref([])
const localesLoading = ref(false)
onMounted(async () => {
	localesLoading.value = true
	try {
		const res = await apiRequest('GET', '/api/v1/format/locales')
		locales.value = Array.isArray(res?.locales) ? res.locales : []
	} catch {
		locales.value = []
	} finally {
		localesLoading.value = false
	}
})

const localeName = (code) => locales.value.find((l) => l.code === code)?.name || code
const localeOptions = computed(() => locales.value.map((l) => ({ value: l.code, label: `${l.name} · ${l.code}` })))

// "Fixed" needs a locale unless the server forces one.
const formatValid = computed(() => !!forcedLocale.value || formatMode.value !== 'fixed' || formatLocale.value !== '')

const previewLocale = computed(() => {
	if (forcedLocale.value) {
		return forcedLocale.value
	}
	if (formatMode.value === 'fixed' && formatLocale.value) {
		return formatLocale.value
	}
	return serverInfo.value.yourLocale || 'en_GB'
})
const preview = computed(() => previewFormats(previewLocale.value, { dateStyle: formatDateStyle.value, clock: formatClock.value }))
// Date, time and email formats are FreeUp's own only with its own locale; with the Nextcloud locale they follow Nextcloud.
const ownLocale = computed(() => formatMode.value === 'fixed' && !forcedLocale.value)
const previewHelp = computed(() => t('nexeno_freeup', 'How it looks for everyone, with the locale {locale}.', { locale: localeName(previewLocale.value) }))

const dateStyleOptions = computed(() => {
	const examples = dateStyleExamples(previewLocale.value)
	return [
		{ value: 'short', label: t('nexeno_freeup', 'Short · {example}', { example: examples.short }) },
		{ value: 'medium', label: t('nexeno_freeup', 'Medium · {example}', { example: examples.medium }) },
		{ value: 'long', label: t('nexeno_freeup', 'Long · {example}', { example: examples.long }) },
		{ value: 'full', label: t('nexeno_freeup', 'Full · {example}', { example: examples.full }) },
	]
})

const clockOptions = computed(() => [
	{ value: 'locale', label: t('nexeno_freeup', 'Follow the locale') },
	{ value: '24', label: t('nexeno_freeup', '24-hour · {example}', { example: previewFormats(previewLocale.value, { clock: '24' }).time }) },
	{ value: '12', label: t('nexeno_freeup', '12-hour · {example}', { example: previewFormats(previewLocale.value, { clock: '12' }).time }) },
])

const lastReportMonth = computed(() => {
	const m = props.settings?.financeReportLastMonth
	if (!m) {
		return ''
	}
	const [y, mo] = m.split('-').map(Number)
	return fmtMonthYear(new Date(y, mo - 1, 1))
})

function sendReport() {
	sendingReport.value = true
	emit('sendReport', () => {
		sendingReport.value = false
	})
}

watch(() => props.settings, (s) => {
	if (!s || saving.value) {
		return
	}
	const next = fromSettings(s)
	inactivityMonths.value = next.inactivityMonths
	exemptGroups.value = next.exemptGroups
	exemptUsers.value = next.exemptUsers
	approverGroups.value = next.approverGroups
	approverUsers.value = next.approverUsers
	cleanupApproverGroups.value = next.cleanupApproverGroups
	cleanupApproverUsers.value = next.cleanupApproverUsers
	userNames.value = { ...(s.userNames || {}), ...userNames.value }
	auditRetentionMonths.value = next.auditRetentionMonths
	noticeEnabled.value = next.noticeEnabled
	noticeDaysInput.value = String(next.noticeDays)
	financeEnabled.value = next.financeReportEnabled
	financeEmail.value = next.financeReportEmail
	formatMode.value = next.formatMode
	formatLocale.value = next.formatLocale
	formatDateStyle.value = next.formatDateStyle
	formatClock.value = next.formatClock
	saved.value = JSON.stringify(next)
}, { deep: true })

function save() {
	saving.value = true
	emit('saveSettings', {
		inactivityMonths: inactivityMonths.value,
		exemptGroups: exemptGroups.value.filter(Boolean),
		exemptUsers: exemptUsers.value.filter(Boolean),
		approverGroups: approverGroups.value.length ? approverGroups.value.filter(Boolean) : ['admin'],
		approverUsers: approverUsers.value.filter(Boolean),
		cleanupApproverGroups: cleanupApproverGroups.value.filter(Boolean),
		cleanupApproverUsers: cleanupApproverUsers.value.filter(Boolean),
		auditRetentionMonths: auditRetentionMonths.value,
		noticeEnabled: noticeEnabled.value,
		noticeDays: noticeDays.value,
		financeReportEnabled: financeEnabled.value,
		financeReportEmail: financeEmail.value.trim(),
		formatMode: formatMode.value,
		formatLocale: formatLocale.value,
		formatDateStyle: formatDateStyle.value,
		formatClock: formatClock.value,
	}, (ok) => {
		saving.value = false
		if (ok) {
			saved.value = JSON.stringify(current.value)
		}
	})
}
</script>

<style lang="scss" scoped>
.freeup-view-settings {
  width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
}

.settings-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  overflow: hidden;
}

.card-head {
  padding: calc(var(--default-grid-baseline) * 4) calc(var(--default-grid-baseline) * 5);
}

.card-title { margin: 0; font-size: 18px; font-weight: 700; }

.card-sub {
  margin: var(--default-grid-baseline) 0 0;
  color: var(--color-text-maxcontrast);
}

.row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
  gap: calc(var(--default-grid-baseline) * 4) calc(var(--default-grid-baseline) * 6);
  align-items: start;
  padding: calc(var(--default-grid-baseline) * 4) calc(var(--default-grid-baseline) * 5);
  border-top: 1px solid var(--color-border);
}

.row-info { display: flex; flex-direction: column; gap: var(--default-grid-baseline); min-width: 0; }
.row-label { font-weight: 600; }
.row-help { margin: 0; color: var(--color-text-maxcontrast); }
.row-help .help-line { display: block; }
.row-help .help-line + .help-line { margin-top: var(--default-grid-baseline); }

.row-control {
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 3);
  min-width: 0;

  // Repeat the component's classes to beat Nextcloud's copy of these styles, loaded later.
  :deep(.nc-select.v-select.select) { width: 100%; margin: 0; }
  :deep(.input-field.input-field) { margin: 0; }
  :deep(.notecard.notecard) { margin: 0; }

  :deep(.checkbox-radio-switch) { margin-inline-start: calc(var(--default-grid-baseline) * -2); }
}

.inline-field { display: flex; align-items: flex-start; gap: calc(var(--default-grid-baseline) * 3); }
.inline-field .number-input.input-field {
  width: 140px;
  flex: 0 0 auto;

  :deep(.input-field__helper-text-message) { white-space: nowrap; }
}
.unit { padding-block-start: calc(var(--default-grid-baseline) * 2); }

.report-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 3);
}

.preview-box {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
  padding: calc(var(--default-grid-baseline) * 4);
  background: var(--color-background-dark);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
}

// Not <dl>: Nextcloud's global CSS lays those out inline.
.config-item { display: flex; flex-direction: column; gap: calc(var(--default-grid-baseline) / 2); min-width: 0; }
.config-label { font-size: 12px; font-weight: 700; color: var(--color-text-maxcontrast); }
.card-sub :deep(.config-link-inline) { color: var(--color-primary-element); text-decoration: underline; }

.radio-choice { display: flex; align-items: center; flex-wrap: wrap; gap: calc(var(--default-grid-baseline) * 2); }
.radio-help { margin: calc(var(--default-grid-baseline) * -2) 0 0 calc(var(--default-grid-baseline) * 10); color: var(--color-text-maxcontrast); }
.clock-choice { flex-direction: row; flex-wrap: wrap; gap: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4); }

@media (max-width: 600px) {
  .preview-box { grid-template-columns: minmax(0, 1fr); }
}

.settings-actions {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 3);
}

@media (max-width: 768px) {
  .row { grid-template-columns: minmax(0, 1fr); gap: calc(var(--default-grid-baseline) * 3); }
}
</style>

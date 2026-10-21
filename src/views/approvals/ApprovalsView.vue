<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="freeup-view-approvals">
		<div class="board-toolbar">
			<div class="toolbar-pills" role="group" :aria-label="t('nexeno_freeup', 'Filter requests')">
				<NcButton
					v-for="tab in kindTabs"
					:key="tab.id"
					:variant="kindFilter === tab.id ? 'primary' : 'secondary'"
					:pressed="kindFilter === tab.id"
					@click="kindFilter = tab.id">
					{{ tab.label }}
				</NcButton>
			</div>
			<TableSearch
				v-model="searchQuery"
				:label="t('nexeno_freeup', 'Search requests')"
				:placeholder="t('nexeno_freeup', 'Search user or file')" />
		</div>

		<div class="approval-board">
			<section class="board-column">
				<header class="column-header">
					<h2>{{ t('nexeno_freeup', 'Proposed · waiting for approval') }}</h2>
					<NcCounterBubble class="column-count" :count="shownProposed.length" />
				</header>
				<div v-if="canDecide && shownProposed.length > 0 && decidableProposed.length > 0" class="select-row">
					<NcCheckboxRadioSwitch
						class="header-select"
						:modelValue="allProposedSelected"
						:indeterminate="someProposedSelected"
						@update:modelValue="toggleAllProposed">
						{{ pickedProposed.length > 0
							? t('nexeno_freeup', '{count} selected', { count: pickedProposed.length })
							: t('nexeno_freeup', 'Select all') }}
					</NcCheckboxRadioSwitch>
					<div v-if="pickedProposed.length > 0" class="select-actions">
						<NcButton variant="primary" @click="openBulkDecision('approve')">
							{{ t('nexeno_freeup', 'Approve') }}
						</NcButton>
						<NcButton variant="secondary" @click="openBulkDecision('reject')">
							{{ t('nexeno_freeup', 'Dismiss') }}
						</NcButton>
					</div>
				</div>

				<div v-if="loading && shownProposed.length === 0" class="column-loading">
					<NcLoadingIcon :size="32" />
				</div>
				<p v-else-if="shownProposed.length === 0" class="column-empty">
					{{ proposed.length > 0 ? t('nexeno_freeup', 'No matching requests.') : t('nexeno_freeup', 'Nothing waiting for approval.') }}
				</p>
				<template v-else>
					<div v-scrollbar class="column-scroll">
						<ul class="board-cards">
							<li
								v-for="req in proposedList.displayed.value"
								:key="'p-' + req.id"
								class="board-card"
								:class="{ 'is-picked': isProposedPicked(req) }">
								<div class="card-head">
									<NcCheckboxRadioSwitch
										v-if="canDecide && canDecideReq(req)"
										class="card-check"
										:modelValue="isProposedPicked(req)"
										:aria-label="t('nexeno_freeup', 'Select {name}', { name: req.title })"
										@update:modelValue="toggleProposed(req)" />
									<div class="card-top">
										<span class="card-title" :title="req.title">{{ req.title }}</span>
										<span class="card-amount">{{ fmtBytes(req.storageBytes) }}</span>
									</div>
								</div>
								<p class="card-subtitle">
									{{ req.subtitle }}
								</p>
								<p v-if="canDecideReq(req)" class="card-meta">
									{{ t('nexeno_freeup', 'Approver: {who}', { who: req.approver }) }}
								</p>
								<p v-else class="card-locked">
									{{ req.kind === 'cleanup'
										? t('nexeno_freeup', 'Only authorized approvers can approve duplicate clean-ups.')
										: t('nexeno_freeup', 'Only authorized approvers can approve account removals.') }}
								</p>
								<p v-if="req.proposalReason" class="card-reason" :title="req.proposalReason">
									{{ req.proposalReason }}
								</p>
								<div v-if="canDecideReq(req)" class="card-actions">
									<NcButton variant="primary" :disabled="busyId === req.id" @click="openDecision(req, 'approve')">
										{{ t('nexeno_freeup', 'Approve') }}
									</NcButton>
									<NcButton variant="secondary" :disabled="busyId === req.id" @click="openDecision(req, 'reject')">
										{{ req.kind === 'cleanup' ? t('nexeno_freeup', 'Dismiss') : t('nexeno_freeup', 'Keep account') }}
									</NcButton>
								</div>
							</li>
						</ul>
						<TableLoadMore :shown="proposedList.shown.value" :total="proposedList.total.value" @more="proposedList.loadMore" />
					</div>
				</template>
			</section>

			<section class="board-column">
				<header class="column-header">
					<h2>{{ t('nexeno_freeup', 'Approved · scheduled') }}</h2>
					<NcCounterBubble class="column-count" :count="shownQueued.length" />
				</header>
				<NcCheckboxRadioSwitch
					v-if="isAdmin && shownQueued.length > 0 && runnable.length > 0"
					class="header-select"
					:modelValue="allRunnableSelected"
					:indeterminate="someRunnableSelected"
					@update:modelValue="toggleAllRunnable">
					{{ pickedItems.length > 0
						? t('nexeno_freeup', '{count} selected', { count: pickedItems.length })
						: t('nexeno_freeup', 'Select all') }}
				</NcCheckboxRadioSwitch>

				<div v-if="loading && shownQueued.length === 0" class="column-loading">
					<NcLoadingIcon :size="32" />
				</div>
				<p v-else-if="shownQueued.length === 0" class="column-empty">
					{{ queued.length > 0 ? t('nexeno_freeup', 'No matching requests.') : t('nexeno_freeup', 'Nothing scheduled.') }}
				</p>
				<template v-else>
					<div v-scrollbar class="column-scroll">
						<ul class="board-cards">
							<li
								v-for="req in queuedList.displayed.value"
								:key="'q-' + req.id"
								class="board-card is-queued"
								:class="{ 'is-picked': isPicked(req) }">
								<div class="card-head">
									<NcCheckboxRadioSwitch
										v-if="isAdmin && req.status === 'approved'"
										class="card-check"
										:modelValue="isPicked(req)"
										:aria-label="t('nexeno_freeup', 'Select {name}', { name: req.title })"
										@update:modelValue="togglePick(req)" />
									<div class="card-top">
										<span class="card-title" :title="req.title">{{ req.title }}</span>
										<span class="card-amount">{{ fmtBytes(req.storageBytes) }}</span>
									</div>
								</div>
								<p class="card-subtitle">
									{{ req.subtitle }}
								</p>
								<p class="card-meta">
									<ClockOutlineIcon :size="14" />
									{{ queueMeta(req) }}
								</p>
								<NcButton
									v-if="req.noticeSentAt && req.status === 'approved' && !isDue(req)"
									class="resend-btn"
									variant="secondary"
									:disabled="resendingId === req.id"
									@click="resend(req)">
									<template #icon>
										<NcLoadingIcon v-if="resendingId === req.id" :size="20" />
										<EmailOutlineIcon v-else :size="20" />
									</template>
									{{ t('nexeno_freeup', 'Send reminder') }}
								</NcButton>
							</li>
						</ul>
						<TableLoadMore :shown="queuedList.shown.value" :total="queuedList.total.value" @more="queuedList.loadMore" />
					</div>
				</template>

				<p v-if="shownQueued.length > 0" class="column-note">
					{{ dueNote }}
				</p>
			</section>

			<section class="board-column">
				<header class="column-header">
					<h2>{{ t('nexeno_freeup', 'Completed') }}</h2>
					<NcCounterBubble class="column-count count-done" :count="shownCompleted.length" />
				</header>

				<div v-if="loading && shownCompleted.length === 0" class="column-loading">
					<NcLoadingIcon :size="32" />
				</div>
				<p v-else-if="shownCompleted.length === 0" class="column-empty">
					{{ completed.length > 0 ? t('nexeno_freeup', 'No matching requests.') : t('nexeno_freeup', 'Nothing completed yet.') }}
				</p>
				<div v-else v-scrollbar class="column-scroll">
					<ul class="board-cards">
						<li v-for="req in completedList.displayed.value" :key="'c-' + req.id" class="board-card is-done">
							<div class="card-top">
								<span class="card-title" :title="req.title">{{ req.title }}</span>
								<span class="card-amount">{{ fmtBytes(req.storageBytes) }}</span>
							</div>
							<p class="card-subtitle">
								{{ req.subtitle }}
							</p>
							<p v-if="req.kind === 'cleanup'" class="card-meta">
								{{ n('nexeno_freeup', '{count} copy replaced', '{count} copies replaced', req.removedCopies, { count: req.removedCopies }) }}<template v-if="req.goneCopies > 0">
									· {{ t('nexeno_freeup', '{count} already deleted by owners', { count: req.goneCopies }) }}
								</template>
							</p>
							<p class="card-meta is-success">
								<CheckCircleOutlineIcon :size="14" />
								{{ req.kind === 'cleanup'
									? t('nexeno_freeup', 'Completed {when}', { when: formatRelative(req.archivedAt) || t('nexeno_freeup', 'recently') })
									: t('nexeno_freeup', 'Disabled {when}', { when: formatRelative(req.archivedAt) || t('nexeno_freeup', 'recently') }) }}
								<template v-if="req.kind === 'account' && !req.userMissing">
									· {{ t('nexeno_freeup', 'can be restored') }}
								</template>
							</p>
							<p v-if="req.kind === 'account' && req.userMissing" class="card-meta">
								{{ t('nexeno_freeup', 'This account was deleted from Nextcloud') }}
							</p>
							<NcButton
								v-else-if="req.kind === 'account' && isAdmin"
								variant="secondary"
								class="restore-btn"
								@click="$emit('restoreUser', req.userId)">
								<template #icon>
									<RestoreIcon :size="20" />
								</template>
								{{ t('nexeno_freeup', 'Restore account') }}
							</NcButton>
						</li>
					</ul>
					<TableLoadMore :shown="completedList.shown.value" :total="completedList.total.value" @more="completedList.loadMore" />
				</div>
			</section>
		</div>

		<NcDialog
			v-if="runDialog"
			:name="t('nexeno_freeup', 'Process now')"
			size="normal"
			:noClose="runningArchive"
			@closing="runDialog = false">
			<DialogBody>
				<DialogSummary>
					<DialogStat :label="n('nexeno_freeup', 'Item', 'Items', pickedItems.length)">
						{{ pickedItems.length }}
					</DialogStat>
					<DialogStat :label="t('nexeno_freeup', 'Storage to reclaim')">
						{{ pickedFree }}
					</DialogStat>
					<DialogStat v-if="pickedWaiting > 0 && pickedWaiting < pickedItems.length" :label="t('nexeno_freeup', 'In notice period')">
						{{ pickedWaiting }}
					</DialogStat>
					<DialogStat v-if="pickedWaiting > 0 && pickedWaiting < pickedItems.length" :label="t('nexeno_freeup', 'Ready now')">
						{{ pickedItems.length - pickedWaiting }}
					</DialogStat>
				</DialogSummary>

				<DialogList :rows="pickedRows" :total="pickedItems.length" />

				<NcNoteCard :type="pickedWaiting > 0 ? 'warning' : 'info'">
					{{ pickedWaiting === pickedItems.length
						? n('nexeno_freeup', 'Warning: This item is still in its notice period. Running now executes it earlier than the scheduled date users were given.', 'Warning: All %n selected items are still in their notice period. Running now executes them earlier than the scheduled date users were given.', pickedWaiting)
						: (pickedWaiting > 0
							? t('nexeno_freeup', 'Warning: {waiting} of {total} items are still in their notice period and will execute earlier than scheduled.', { waiting: pickedWaiting, total: pickedItems.length })
							: t('nexeno_freeup', 'These are ready. They will be done now instead of at the next hourly run.')) }}
				</NcNoteCard>
			</DialogBody>
			<template #actions>
				<NcButton variant="tertiary" :disabled="runningArchive" @click="runDialog = false">
					{{ t('nexeno_freeup', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary" :disabled="runningArchive" @click="confirmRun">
					<template #icon>
						<NcLoadingIcon v-if="runningArchive" :size="20" />
						<ArchiveOutlineIcon v-else :size="20" />
					</template>
					{{ t('nexeno_freeup', 'Process now') }}
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog
			v-if="decision"
			:name="decisionTitle"
			size="normal"
			:noClose="processing"
			@closing="closeDecision">
			<DialogBody>
				<DialogSummary>
					<template v-if="decision.items.length > 1">
						<DialogStat v-if="decisionAccounts.length > 0" :label="t('nexeno_freeup', 'Accounts')">
							{{ fmtNumber(decisionAccounts.length) }}
						</DialogStat>
						<DialogStat v-if="decisionCleanups.length > 0" :label="n('nexeno_freeup', 'File', 'Files', decisionCleanups.length)">
							{{ fmtNumber(decisionCleanups.length) }}
						</DialogStat>
					</template>
					<DialogStat v-if="decisionCleanup" :label="n('nexeno_freeup', 'File', 'Files', 1)">
						1
					</DialogStat>
					<DialogStat :label="t('nexeno_freeup', 'Could free')">
						{{ decisionFree }}
					</DialogStat>
					<template v-if="decisionCleanup">
						<DialogStat :label="t('nexeno_freeup', 'Copies replaced')">
							{{ fmtNumber(Math.max(0, (Number(decisionCleanup.copies) || 1) - 1)) }}
						</DialogStat>
						<DialogStat v-if="decisionCleanup.keeper" :label="t('nexeno_freeup', 'Copy kept')">
							<NcAvatar
								:user="decisionCleanup.keeper"
								:size="24"
								:disableMenu="true"
								hideStatus />
							@{{ decisionCleanup.keeper }}
						</DialogStat>
					</template>
					<template v-if="decision.items.length > 1">
						<DialogStat v-if="decisionCleanups.length > 0" :label="t('nexeno_freeup', 'Copies replaced')">
							{{ fmtNumber(decisionCopiesReplaced) }}
						</DialogStat>
						<DialogStat v-if="decision.action === 'approve'" :label="t('nexeno_freeup', 'Users notified')">
							{{ fmtNumber(decisionUsersNotified) }}
						</DialogStat>
					</template>
					<DialogStat v-if="decision.items.length === 1 && decision.items[0].kind === 'account'" :label="t('nexeno_freeup', 'Inactive for')">
						{{ decision.items[0].isNeverLoggedIn ? t('nexeno_freeup', 'Never logged in') : t('nexeno_freeup', '{m} months', { m: decision.items[0].inactiveMonths }) }}
					</DialogStat>
				</DialogSummary>

				<template v-if="decision.items.length > 1">
					<template v-if="decisionAccounts.length > 0 && decisionCleanups.length > 0">
						<h3 class="dialog-group">
							{{ t('nexeno_freeup', 'Accounts ({count})', { count: fmtNumber(decisionAccounts.length) }) }}
						</h3>
						<DialogList :rows="decisionAccountRows" :total="decisionAccounts.length" />
						<h3 class="dialog-group">
							{{ t('nexeno_freeup', 'Duplicate files ({count})', { count: fmtNumber(decisionCleanups.length) }) }}
						</h3>
						<DialogList :rows="decisionCleanupRows" :total="decisionCleanups.length" />
					</template>
					<DialogList v-else-if="decisionCleanups.length > 0" :rows="decisionCleanupRows" :total="decisionCleanups.length" />
					<DialogList v-else :rows="decisionAccountRows" :total="decisionAccounts.length" />
				</template>

				<template v-if="decisionCleanup">
					<p class="dialog-explain">
						{{ keeperReasonText(decisionCleanup.targetMeta?.keeperReason) }}
					</p>
					<DialogList :rows="cleanupOwnerRows" :total="cleanupOwnerRows.length" />
					<NcButton class="details-btn" variant="tertiary" @click="viewCleanupDetails">
						<template #icon>
							<OpenInNewIcon :size="20" />
						</template>
						{{ t('nexeno_freeup', 'View details') }}
					</NcButton>
				</template>

				<NcNoteCard :type="decision.action === 'approve' ? 'info' : 'warning'">
					{{ decisionNote }}
				</NcNoteCard>
				<NcNoteCard v-if="noEmailCount > 0" type="warning">
					{{ n('nexeno_freeup', '{count} account has no email address. After approval, this user will only receive a notification.', '{count} accounts have no email address. After approval, these users will only receive a notification.', noEmailCount, { count: noEmailCount }) }}
				</NcNoteCard>

				<NcTextArea
					v-model="decisionNotes"
					:label="t('nexeno_freeup', 'Decision notes')"
					:placeholder="t('nexeno_freeup', 'Optional note, shown in Recent activity…')"
					:disabled="processing"
					rows="3" />
			</DialogBody>

			<template #actions>
				<NcButton variant="tertiary" :disabled="processing" @click="closeDecision">
					{{ t('nexeno_freeup', 'Cancel') }}
				</NcButton>
				<NcButton :variant="decision.action === 'approve' ? 'primary' : 'error'" :disabled="processing" @click="confirmDecision">
					<template #icon>
						<NcLoadingIcon v-if="processing" :size="20" />
					</template>
					{{ decision.action === 'approve' ? t('nexeno_freeup', 'Approve') : t('nexeno_freeup', 'Confirm') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArchiveOutlineIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DialogBody from '../../components/dialog/DialogBody.vue'
import DialogList from '../../components/dialog/DialogList.vue'
import DialogStat from '../../components/dialog/DialogStat.vue'
import DialogSummary from '../../components/dialog/DialogSummary.vue'
import TableLoadMore from '../../components/table/TableLoadMore.vue'
import TableSearch from '../../components/table/TableSearch.vue'
import { useProgressiveList } from '../../composables/useProgressiveList.js'
import { formatRelative } from '../../services/api.js'
import { extensionOf, keeperReasonText } from '../../services/duplicates.js'
import { fmtBytes, fmtDate, fmtNumber } from '../../services/format.js'
import { getViewUrl } from '../../services/routes.js'
import { notifyWarning } from '../../services/toast.js'

const props = defineProps({
	proposed: { type: Array, default: () => [] },
	queued: { type: Array, default: () => [] },
	completed: { type: Array, default: () => [] },
	loading: { type: Boolean, default: false },
	isAdmin: { type: Boolean, default: false },
	canDecide: { type: Boolean, default: false },
	runningArchive: { type: Boolean, default: false },
	settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['approve', 'reject', 'decideMany', 'runArchive', 'restoreUser', 'resendNotice', 'selectionChange', 'viewDuplicate'])

const decision = ref(null)
const decisionNotes = ref('')
const processing = ref(false)
const busyId = ref(null)

const resendingId = ref(null)

const KIND_KEY = 'nexeno_freeup.approvals.kind'
const KINDS = ['all', 'account', 'cleanup']
function readKind() {
	try {
		const saved = window.localStorage.getItem(KIND_KEY)
		return KINDS.includes(saved) ? saved : 'all'
	} catch {
		return 'all'
	}
}
const kindFilter = ref(readKind())
const searchQuery = ref('')
watch(kindFilter, (kind) => {
	try {
		window.localStorage.setItem(KIND_KEY, kind)
	} catch {
		// Private windows can refuse storage; the filter then just resets next time.
	}
})

function matchesFilter(req) {
	if (kindFilter.value !== 'all' && req.kind !== kindFilter.value) {
		return false
	}
	const q = searchQuery.value.trim().toLowerCase()
	return !q || `${req.title || ''} ${req.subtitle || ''} ${req.userId || ''} ${req.proposalReason || ''}`.toLowerCase().includes(q)
}
const shownProposed = computed(() => props.proposed.filter(matchesFilter))
const shownQueued = computed(() => props.queued.filter(matchesFilter))
const shownCompleted = computed(() => props.completed.filter(matchesFilter))

const kindTabs = [
	{ id: 'all', label: t('nexeno_freeup', 'All') },
	{ id: 'account', label: t('nexeno_freeup', 'Accounts') },
	{ id: 'cleanup', label: t('nexeno_freeup', 'Duplicate files') },
]

const pickedIds = ref([])
const runDialog = ref(false)
const runnable = computed(() => shownQueued.value.filter((r) => r.status === 'approved'))
const pickedItems = computed(() => runnable.value.filter((r) => pickedIds.value.includes(r.id)))
const pickedWaiting = computed(() => pickedItems.value.filter((r) => !isDue(r)).length)
const allRunnableSelected = computed(() => runnable.value.length > 0 && pickedItems.value.length === runnable.value.length)
const someRunnableSelected = computed(() => pickedItems.value.length > 0 && !allRunnableSelected.value)

function isPicked(req) {
	return pickedIds.value.includes(req.id)
}

function togglePick(req) {
	pickedIds.value = isPicked(req) ? pickedIds.value.filter((id) => id !== req.id) : [...pickedIds.value, req.id]
}

function toggleAllRunnable(checked) {
	pickedIds.value = checked ? runnable.value.map((r) => r.id) : []
}

watch(pickedIds, (ids) => emit('selectionChange', ids))
// Ticked items hidden by the filter or search are dropped, so nothing is processed out of sight.
watch(runnable, () => {
	const valid = new Set(runnable.value.map((r) => r.id))
	const kept = pickedIds.value.filter((id) => valid.has(id))
	if (kept.length !== pickedIds.value.length) {
		pickedIds.value = kept
	}
})

function openRunDialog() {
	if (pickedItems.value.length > 0) {
		runDialog.value = true
	}
}

function confirmRun() {
	emit('runArchive', {
		requestIds: pickedIds.value,
		done: () => {
			runDialog.value = false
			pickedIds.value = []
		},
	})
}

defineExpose({ openRunDialog, pickedIds })

function isDue(req) {
	return !req.archiveAfter || new Date(req.archiveAfter) <= new Date()
}

const dueNote = computed(() => {
	const waiting = shownQueued.value.filter((r) => r.status === 'approved' && !isDue(r))
	const due = shownQueued.value.filter((r) => r.status === 'approved' && isDue(r)).length
	if (due > 0) {
		return n('nexeno_freeup', '{count} ready: it runs at the next hourly run, or tick it and run it now.', '{count} ready: they run at the next hourly run, or tick them and run them now.', due, { count: due })
	}
	if (waiting.length > 0) {
		const next = waiting.map((r) => new Date(r.archiveAfter)).sort((a, b) => a - b)[0]
		return t('nexeno_freeup', 'No actions are due yet. Next scheduled date: {date}. Select items to run them.', { date: formatDay(next.toISOString()) })
	}
	return ''
})

async function resend(req) {
	resendingId.value = req.id
	emit('resendNotice', {
		requestId: req.id,
		done: () => {
			resendingId.value = null
		},
	})
}

function formatDay(iso) {
	return fmtDate(iso)
}

function queueMeta(req) {
	if (req.status === 'archiving') {
		return t('nexeno_freeup', 'Processing now…')
	}
	if (req.archiveAfter && new Date(req.archiveAfter) > new Date()) {
		return req.kind === 'cleanup'
			? t('nexeno_freeup', 'Copies are replaced on {date}', { date: formatDay(req.archiveAfter) })
			: t('nexeno_freeup', 'Will be disabled on {date}', { date: formatDay(req.archiveAfter) })
	}
	return t('nexeno_freeup', 'Waiting for the next hourly run')
}

const pickedProposed = ref([])
const pickedProposedItems = computed(() => shownProposed.value.filter((r) => pickedProposed.value.includes(r.id)))
function openDecision(req, action) {
	openDecisionFor([req], action)
}

function openBulkDecision(action) {
	openDecisionFor(pickedProposedItems.value, action)
}

function canDecideReq(req) {
	return req.canDecide !== false
}

function denyText(req) {
	return req.kind === 'cleanup'
		? t('nexeno_freeup', 'Only authorized approvers can approve duplicate clean-ups. An administrator can change who approves in Settings.')
		: t('nexeno_freeup', 'Only authorized approvers can approve account removals. An administrator can change who approves in Settings.')
}

function openDecisionFor(all, action) {
	if (all.length === 0) {
		return
	}
	const items = all.filter(canDecideReq)
	if (items.length === 0) {
		notifyWarning(denyText(all[0]))
		return
	}
	if (items.length < all.length) {
		notifyWarning(n('nexeno_freeup', '{count} request was left out: you are not allowed to decide it.', '{count} requests were left out: you are not allowed to decide them.', all.length - items.length, { count: all.length - items.length }))
	}
	decision.value = { items, action }
	// Empty on purpose: the note goes to Recent activity, so only the manager's text.
	decisionNotes.value = ''
}

const decisionTitle = computed(() => {
	const d = decision.value
	if (!d) {
		return ''
	}
	if (d.items.length > 1) {
		const count = d.items.length
		const onlyCleanups = d.items.every((r) => r.kind === 'cleanup')
		const onlyAccounts = d.items.every((r) => r.kind !== 'cleanup')
		if (d.action === 'approve') {
			if (onlyCleanups) {
				return n('nexeno_freeup', 'Approve %n clean-up', 'Approve %n clean-ups', count)
			}
			if (onlyAccounts) {
				return n('nexeno_freeup', 'Approve %n account', 'Approve %n accounts', count)
			}
			return t('nexeno_freeup', 'Approve {count} requests', { count })
		}
		if (onlyCleanups) {
			return n('nexeno_freeup', 'Dismiss %n clean-up', 'Dismiss %n clean-ups', count)
		}
		if (onlyAccounts) {
			return n('nexeno_freeup', 'Keep %n account', 'Keep %n accounts', count)
		}
		return t('nexeno_freeup', 'Dismiss {count} requests', { count })
	}
	return d.action === 'approve'
		? t('nexeno_freeup', 'Approve: {title}', { title: d.items[0].title })
		: t('nexeno_freeup', 'Reject: {title}', { title: d.items[0].title })
})

const noEmailCount = computed(() => {
	const d = decision.value
	if (!d || d.action !== 'approve' || props.settings?.noticeEnabled === false) {
		return 0
	}
	return d.items.filter((r) => r.kind === 'account' && !r.email).length
})

const pickedFree = computed(() => fmtBytes(pickedItems.value.reduce((a, r) => a + (Number(r.storageBytes) || 0), 0)))
const pickedRows = computed(() => pickedItems.value.map((r) => ({ key: r.id, title: r.title, meta: queueMeta(r) })))
// Several requests: totals plus one short line per item, never every owner's name (a file can have hundreds of owners).
const decisionAccounts = computed(() => (decision.value?.items || []).filter((r) => r.kind !== 'cleanup'))
const decisionCleanups = computed(() => (decision.value?.items || []).filter((r) => r.kind === 'cleanup'))
const decisionCopiesReplaced = computed(() => decisionCleanups.value.reduce((a, r) => a + Math.max(0, (Number(r.copies) || 1) - 1), 0))

// Each user once, however many of the selected files they hold: the users who lose a copy, plus the account owners.
const decisionUsersNotified = computed(() => {
	const users = new Set(decisionAccounts.value.map((r) => r.userId))
	decisionCleanups.value.forEach((r) => (r.users || []).forEach((u) => {
		if (u !== r.keeper) {
			users.add(u)
		}
	}))
	return users.size
})

const decisionAccountRows = computed(() => decisionAccounts.value.map((r) => ({
	key: r.id,
	user: r.userId,
	title: r.title,
	sub: r.subtitle,
	meta: fmtBytes(r.storageBytes),
})))

const decisionCleanupRows = computed(() => decisionCleanups.value.map((r) => {
	const name = r.targetName || r.title
	const copies = Number(r.copies) || 1
	const checksum = checksumOf(r)
	return {
		key: r.id,
		badge: extensionOf(name),
		title: name,
		sub: n('nexeno_freeup', '{count} copy · {replaced} replaced', '{count} copies · {replaced} replaced', copies, { count: fmtNumber(copies), replaced: fmtNumber(Math.max(0, copies - 1)) }),
		meta: fmtBytes(r.storageBytes),
		href: checksum ? getViewUrl('duplicate-detail', checksum) : null,
	}
}))

// A single clean-up shows the kept copy and every owner; the file name is already in the title.
const decisionCleanup = computed(() => {
	const items = decision.value?.items || []
	return items.length === 1 && items[0].kind === 'cleanup' ? items[0] : null
})

const cleanupOwnerRows = computed(() => {
	const req = decisionCleanup.value
	if (!req) {
		return []
	}
	const keeper = req.keeper || ''
	const users = [...new Set(req.users || [])].sort((a, b) => (b === keeper) - (a === keeper))
	return users.map((u) => ({ key: u, user: u, title: '@' + u, meta: u === keeper ? t('nexeno_freeup', 'Kept') : t('nexeno_freeup', 'Replaced') }))
})

function checksumOf(req) {
	return req?.targetMeta?.checksums?.[0] || String(req?.targetRef || '').replace(/^file:/, '')
}

function viewCleanupDetails() {
	const checksum = checksumOf(decisionCleanup.value)
	closeDecision()
	if (checksum) {
		emit('viewDuplicate', checksum)
	}
}

const decisionFree = computed(() => fmtBytes((decision.value?.items || []).reduce((a, r) => a + (Number(r.storageBytes) || 0), 0)))

const decisionNote = computed(() => {
	const d = decision.value
	if (!d) {
		return ''
	}
	const kinds = new Set(d.items.map((r) => r.kind))
	const noticeOn = props.settings?.noticeEnabled !== false
	if (d.action === 'approve') {
		if (kinds.size === 1 && kinds.has('cleanup')) {
			return noticeOn
				? t('nexeno_freeup', 'The other owners will be notified in advance. After the notice period, their copies will be moved to their trash, while they retain access to one shared, read-only copy. They can restore their individual copies from the trash if needed.')
				: t('nexeno_freeup', 'The other owners\' copies move to their trash and they keep access to one shared, read-only copy. They can restore their own copy from the trash.')
		}
		if (kinds.size === 1) {
			return noticeOn
				? t('nexeno_freeup', 'The user will receive an email and an in-app notification and can log in to cancel the process. If no action is taken, the account will be disabled and its licence released. The user’s files will be retained, and the account can be restored if needed.')
				: t('nexeno_freeup', 'The account is disabled and its licence is freed. Files are kept and the account can be restored.')
		}
		return noticeOn
			? t('nexeno_freeup', 'Users are notified first. Then accounts are disabled and duplicate files are cleaned up. Everything can be restored.')
			: t('nexeno_freeup', 'Accounts are disabled and duplicate files are cleaned up. Everything can be restored.')
	}
	if (kinds.size === 1 && kinds.has('cleanup')) {
		return t('nexeno_freeup', 'The duplicate files stay exactly as they are.')
	}
	if (kinds.size === 1) {
		return t('nexeno_freeup', 'The accounts will remain active, and no changes will be made.')
	}
	return t('nexeno_freeup', 'Nothing changes: accounts stay active and duplicate files stay as they are.')
})

function closeDecision() {
	if (processing.value) {
		return
	}
	decision.value = null
}

function confirmDecision() {
	if (!decision.value) {
		return
	}
	const { items, action } = decision.value
	processing.value = true
	if (items.length === 1) {
		busyId.value = items[0].id
		emit(action === 'approve' ? 'approve' : 'reject', { requestId: items[0].id, notes: decisionNotes.value })
		setTimeout(() => {
			processing.value = false
			busyId.value = null
			decision.value = null
			pickedProposed.value = pickedProposed.value.filter((id) => id !== items[0].id)
		}, 400)
		return
	}
	emit('decideMany', {
		action,
		requestIds: items.map((r) => r.id),
		notes: decisionNotes.value,
		done: () => {
			processing.value = false
			decision.value = null
			pickedProposed.value = []
		},
	})
}

const decidableProposed = computed(() => shownProposed.value.filter(canDecideReq))
const allProposedSelected = computed(() => decidableProposed.value.length > 0 && pickedProposedItems.value.length === decidableProposed.value.length)
const someProposedSelected = computed(() => pickedProposedItems.value.length > 0 && !allProposedSelected.value)

function isProposedPicked(req) {
	return pickedProposed.value.includes(req.id)
}

function toggleProposed(req) {
	pickedProposed.value = isProposedPicked(req) ? pickedProposed.value.filter((id) => id !== req.id) : [...pickedProposed.value, req.id]
}

function toggleAllProposed(checked) {
	pickedProposed.value = checked ? decidableProposed.value.map((r) => r.id) : []
}

watch(shownProposed, () => {
	const valid = new Set(shownProposed.value.map((r) => r.id))
	const kept = pickedProposed.value.filter((id) => valid.has(id))
	if (kept.length !== pickedProposed.value.length) {
		pickedProposed.value = kept
	}
})

const BOARD_PAGE = 25
const proposedList = useProgressiveList(shownProposed, BOARD_PAGE)
const queuedList = useProgressiveList(shownQueued, BOARD_PAGE)
const completedList = useProgressiveList(shownCompleted, BOARD_PAGE)

const vScrollbar = {
	mounted(el) {
		let rafId = null
		const update = () => {
			cancelAnimationFrame(rafId)
			rafId = requestAnimationFrame(() => {
				const has = (el.scrollHeight - el.clientHeight) > 1
				el.classList.toggle('has-scrollbar', has)
			})
		}
		const ro = new ResizeObserver(update)
		ro.observe(el)
		if (el.firstElementChild) {
			ro.observe(el.firstElementChild)
		}
		const mo = new MutationObserver(update)
		mo.observe(el, { childList: true, subtree: true })
		el._cleanupScrollbar = () => {
			cancelAnimationFrame(rafId)
			ro.disconnect()
			mo.disconnect()
		}
		update()
	},
	updated(el) {
		const has = (el.scrollHeight - el.clientHeight) > 1
		el.classList.toggle('has-scrollbar', has)
	},
	unmounted(el) {
		el._cleanupScrollbar?.()
	},
}
</script>

<style lang="scss" scoped>
.dialog-explain {
  margin: 0;
  color: var(--color-text-maxcontrast);
}

.details-btn {
  align-self: flex-start;
}

.dialog-group {
  margin: 0;
  font-size: var(--default-font-size);
  font-weight: 600;
}

.freeup-view-approvals {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  flex: 1 0 auto;
}

/* Side by side, the board fills the screen and each column scrolls; stacked, the page scrolls instead. */
@media (min-width: 1367px) {
  .freeup-view-approvals {
    flex: 1 1 0;
    min-height: 0;
  }
}

.board-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 3);
  margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.toolbar-pills {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);
}

/* minmax(0, 1fr): long titles or reasons must never make one column wider than the others. */
.approval-board {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-template-rows: minmax(0, 1fr);
  gap: calc(var(--default-grid-baseline) * 4);
  align-items: stretch;
  flex: 1 1 0;
  min-height: 0;

  @media (max-width: 1366px) {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: none;
    flex: none;
  }
}

.board-column {
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: calc(var(--default-grid-baseline) * 4);
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 3);
  min-width: 0;
  min-height: 160px;

  @media (max-width: 1366px) { min-height: 0; }
}

/* Same height with or without the Select all checkbox, so the first cards line up across columns. */
.column-header {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  min-height: var(--default-clickable-area);

  h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    flex: 1;
  }
}

.column-count {
  font-weight: 700;
  background: var(--color-element-warning, var(--color-warning));
  color: #fff;
  flex-shrink: 0;

  &.count-done { background: var(--color-element-success, var(--color-success)); }
}

.column-loading {
  display: flex;
  justify-content: center;
  padding: calc(var(--default-grid-baseline) * 6) 0;
}

.column-empty {
  color: var(--color-text-maxcontrast);
  margin: 0;
}

.board-cards {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 3);
}

.board-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: calc(var(--default-grid-baseline) * 3);
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 1);
}

.card-top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: calc(var(--default-grid-baseline) * 2);
}

.card-title {
  font-weight: 600;
  min-width: 0;
  overflow-wrap: anywhere;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-amount {
  font-weight: 700;
  white-space: nowrap;
  flex-shrink: 0;
}

.card-subtitle,
.card-meta {
  margin: 0;
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}

.card-meta {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 1);

  > .material-design-icon {
    flex-shrink: 0;
  }

  &.is-success { color: var(--color-success-text, var(--color-element-success)); }
}

.card-locked {
  margin: 0;
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}

.card-reason {
  margin: 0;
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}

.card-actions {
  display: flex;
  gap: calc(var(--default-grid-baseline) * 2);
  margin-top: var(--default-grid-baseline);
}

.header-select {
  align-self: flex-start;
  font-size: 13px;

  :deep(.checkbox-content) {
    padding-inline-start: 0;
    padding-left: 0;
  }

  &,
  &:hover,
  &:focus-within {
    :deep(.checkbox-content),
    :deep(.checkbox-radio-switch__content) {
      background-color: transparent !important;
    }
  }
}

.select-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 2);

  .header-select { align-self: center; }
}

.select-actions {
  display: flex;
  gap: var(--default-grid-baseline);
}

.column-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  scrollbar-gutter: auto;
  padding-inline-end: 0;

  &.has-scrollbar {
    padding-inline-end: var(--default-grid-baseline);
  }

  @media (max-width: 1366px) {
    max-height: none;
    overflow-y: visible;
    scrollbar-gutter: auto;
    padding-inline-end: 0;

    &.has-scrollbar {
      padding-inline-end: 0;
    }
  }

  :deep(.table-load-more) { border-top: none; padding-block: calc(var(--default-grid-baseline) * 3); }
}

.card-head {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
}
.card-head .card-top {
  flex: 1;
  min-width: 0;
}
.card-check {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  margin: 0;

  :deep(.checkbox-content) {
    min-height: unset;
    height: auto;
    padding: 0;
    margin: 0;
  }

  :deep(.checkbox-content__icon) {
    margin: 0;
    display: inline-flex;
    align-items: center;
    line-height: 1;
  }
}
// Inset, because .column-scroll clips anything drawn outside the card.
.board-card.is-picked { border-color: var(--color-primary-element); box-shadow: inset 0 0 0 1px var(--color-primary-element); }

.column-note { margin: 0; color: var(--color-text-maxcontrast); }
.resend-btn,
.restore-btn {
  align-self: flex-start;
  margin-top: calc(var(--default-grid-baseline) * 2);
}

</style>

<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent appName="nexeno_freeup">
		<AppSidebar
			:currentView="currentView"
			:isAdmin="isAdmin"
			:inactiveCount="inactiveCount"
			:pendingApprovalsCount="pendingApprovalsCount"
			:isScanning="isScanning"
			:scanBusy="isScanning || duplicatesLoading"
			@navigate="navigate"
			@runScan="runFullScan" />

		<NcAppContent>
			<PageHeader :title="title" :subtitle="subtitle" :organisation="organisation">
				<template #actions>
					<PageActions
						:view="currentView"
						:isAdmin="isAdmin"
						:isApprover="isApprover"
						:exporting="overviewRef?.exporting === true"
						:selectedInactiveCount="selectedInactiveCount"
						:selectedDuplicateCount="selectedDuplicateCount"
						:pickedRunCount="pickedRunCount"
						:dueQueuedCount="dueQueuedCount"
						:runningArchive="runningArchive"
						@exportOverview="overviewRef?.exportReport?.()"
						@proposeRemoval="inactiveAccountsRef?.openProposeModal?.()"
						@proposeCleanup="duplicatesRef?.openProposeModal?.()"
						@runArchive="handleRunButton" />
				</template>
			</PageHeader>

			<div class="freeup-content">
				<OverviewView
					v-if="currentView === 'overview'"
					ref="overviewRef"
					:overview="overview"
					:isAdmin="isAdmin"
					:history="licenceHistory"
					@navigate="navigate" />

				<InactiveAccountsView
					v-else-if="currentView === 'inactive-accounts'"
					ref="inactiveAccountsRef"
					:accounts="accounts"
					:loading="accountsLoading"
					:settings="settings"
					@refresh="refreshInactive"
					@selectionChange="(ids) => (selectedInactiveCount = ids.length)" />

				<StorageByAccountView
					v-else-if="currentView === 'accounts'"
					:accounts="accounts"
					:overview="overview"
					:loading="accountsLoading"
					:isAdmin="isAdmin"
					@restoreUser="restoreUser" />

				<ApprovalsView
					v-else-if="currentView === 'approvals'"
					ref="approvalsRef"
					:proposed="approvals"
					:queued="queuedApprovals"
					:completed="completedApprovals"
					:loading="approvalsLoading"
					:isAdmin="isAdmin"
					:canDecide="isApprover"
					:runningArchive="runningArchive"
					:settings="settings"
					@runArchive="runArchive"
					@selectionChange="(ids) => (pickedRunCount = ids.length)"
					@resendNotice="resendNotice"
					@restoreUser="restoreUser"
					@approve="approve"
					@reject="reject"
					@decideMany="decideMany"
					@viewDuplicate="openDuplicateByChecksum" />

				<DuplicatesView
					v-else-if="currentView === 'duplicates'"
					ref="duplicatesRef"
					:duplicates="duplicates"
					:cleanupStatus="cleanupStatus"
					:loading="duplicatesLoading"
					:isAdmin="isAdmin"
					:isApprover="isApprover"
					:settings="settings"
					@selectionChange="(ids) => (selectedDuplicateCount = ids.length)"
					@viewDetails="openDuplicateDetail"
					@cleanupProposed="cleanupProposed" />

				<DuplicateDetailView
					v-else-if="currentView === 'duplicate-detail'"
					:item="selectedDuplicateItem"
					:loading="duplicatesLoading && !selectedDuplicateItem"
					:isAdmin="isAdmin"
					:isApprover="isApprover"
					:settings="settings"
					:cleanupStatus="cleanupStatus"
					@back="closeDuplicateDetail"
					@cleanupProposed="cleanupProposed" />

				<LicenceHistoryView
					v-else-if="currentView === 'licences'"
					:history="licenceHistory"
					:overview="overview" />

				<AuditView
					v-else-if="currentView === 'audit' && isAdmin"
					:auditLogs="auditLogs"
					:loading="auditLoading" />

				<SettingsView
					v-else-if="currentView === 'settings' && isAdmin"
					:settings="settings"
					@saveSettings="saveSettings"
					@sendReport="sendReport" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { onMounted, onUnmounted, ref, watch } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import AppSidebar from './components/layout/AppSidebar.vue'
import PageActions from './components/layout/PageActions.vue'
import PageHeader from './components/layout/PageHeader.vue'
import AuditView from './views/activity/AuditView.vue'
import ApprovalsView from './views/approvals/ApprovalsView.vue'
import DuplicateDetailView from './views/duplicates/DuplicateDetailView.vue'
import DuplicatesView from './views/duplicates/DuplicatesView.vue'
import InactiveAccountsView from './views/inactive-accounts/InactiveAccountsView.vue'
import LicenceHistoryView from './views/licences/LicenceHistoryView.vue'
import OverviewView from './views/overview/OverviewView.vue'
import SettingsView from './views/settings/SettingsView.vue'
import StorageByAccountView from './views/storage/StorageByAccountView.vue'
import { useAppNavigation } from './composables/useAppNavigation.js'
import { useAutomation } from './composables/useAutomation.js'
import { useDuplicateScan } from './composables/useDuplicateScan.js'
import { useFreeUpData } from './composables/useFreeUpData.js'
import { usePageHeader } from './composables/usePageHeader.js'
import { useWorkflowActions } from './composables/useWorkflowActions.js'
import { resolveDuplicateItemById } from './services/duplicates.js'
import { notifyWarning } from './services/toast.js'

const data = useFreeUpData()
const {
	overview,
	accounts,
	approvals,
	queuedApprovals,
	completedApprovals,
	duplicates,
	cleanupStatus,
	licenceHistory,
	auditLogs,
	settings,
	accountsLoading,
	approvalsLoading,
	duplicatesLoading,
	auditLoading,
	isAdmin,
	isApprover,
	inactiveCount,
	pendingApprovalsCount,
	dueQueuedCount,
} = data

const { currentView, selectedDuplicateItem, navigate, openDuplicateDetail, closeDuplicateDetail, start, stop } = useAppNavigation(data)
const {
	runningArchive,
	approve,
	reject,
	decideMany,
	runArchive,
	resendNotice,
	restoreUser,
	cleanupProposed,
	refreshInactive,
	sendReport,
	saveSettings,
} = useWorkflowActions(data, currentView)
const { isScanning, runFullScan } = useDuplicateScan(data)
const { title, subtitle, organisation } = usePageHeader(data, currentView)
const automation = useAutomation(data)

// header buttons open dialogs owned by these views
const overviewRef = ref(null)
const inactiveAccountsRef = ref(null)
const duplicatesRef = ref(null)
const approvalsRef = ref(null)

// selection counts for the header buttons, reset on page change
const selectedInactiveCount = ref(0)
const selectedDuplicateCount = ref(0)
const pickedRunCount = ref(0)

watch(currentView, (view) => {
	if (view !== 'inactive-accounts') {
		selectedInactiveCount.value = 0
	}
	if (view !== 'duplicates') {
		selectedDuplicateCount.value = 0
	}
	if (view !== 'approvals') {
		pickedRunCount.value = 0
	}
})

// Approvals only know a clean-up's checksum; the Breakdown page needs the duplicate from the latest scan.
async function openDuplicateByChecksum(checksum) {
	if (data.duplicates.value.length === 0) {
		await data.loadDuplicates(true)
	}
	const item = resolveDuplicateItemById(data.duplicates.value, checksum)
	if (item) {
		openDuplicateDetail(item)
	} else {
		notifyWarning(t('nexeno_freeup', 'This file is no longer in the latest duplicate scan.'))
	}
}

// ticked items need confirmation; otherwise run what is due
function handleRunButton() {
	if (pickedRunCount.value > 0) {
		approvalsRef.value?.openRunDialog?.()
	} else {
		runArchive({})
	}
}

onMounted(() => {
	data.applyInitialData()
	start()
	automation.start()
})

onUnmounted(() => {
	stop()
	automation.stop()
})
</script>

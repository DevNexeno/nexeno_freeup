<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigation :aria-label="t('nexeno_freeup', 'Nexeno FreeUp navigation')">
		<NcAppNavigationNew
			:text="isScanning ? t('nexeno_freeup', 'Scanning…') : t('nexeno_freeup', 'Run new scan')"
			:disabled="scanBusy"
			@click="$emit('runScan')">
			<template #icon>
				<NcLoadingIcon v-if="scanBusy" :size="20" />
				<RefreshIcon v-else :size="20" />
			</template>
		</NcAppNavigationNew>

		<ul class="app-navigation-list">
			<NcAppNavigationItem
				v-for="item in visibleItems"
				:key="item.view"
				:name="item.name"
				:active="item.active.includes(currentView)"
				:href="getViewUrl(item.view)"
				@click.prevent="$emit('navigate', item.target || item.view)">
				<template #icon>
					<component :is="item.icon" :size="20" />
				</template>
				<template v-if="item.view === 'inactive-accounts' && inactiveCount > 0" #counter>
					<NcCounterBubble :count="inactiveCount" />
				</template>
				<template v-else-if="item.view === 'approvals' && pendingApprovalsCount > 0" #counter>
					<NcCounterBubble type="highlighted" :count="pendingApprovalsCount" />
				</template>
			</NcAppNavigationItem>
		</ul>

		<template #footer>
			<ul v-if="isAdmin" class="app-navigation-entry__settings">
				<NcAppNavigationItem
					:name="t('nexeno_freeup', 'Settings')"
					:active="currentView === 'settings'"
					:href="getViewUrl('settings')"
					@click.prevent="$emit('navigate', 'settings')">
					<template #icon>
						<CogOutlineIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</ul>
		</template>
	</NcAppNavigation>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AccountClockOutlineIcon from 'vue-material-design-icons/AccountClockOutline.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import CogOutlineIcon from 'vue-material-design-icons/CogOutline.vue'
import FileMultipleOutlineIcon from 'vue-material-design-icons/FileMultipleOutline.vue'
import HistoryIcon from 'vue-material-design-icons/History.vue'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'
import ServerOutlineIcon from 'vue-material-design-icons/ServerOutline.vue'
import ViewDashboardOutlineIcon from 'vue-material-design-icons/ViewDashboardOutline.vue'
import { getViewUrl } from '../../services/routes.js'

const props = defineProps({
	currentView: { type: String, required: true },
	isAdmin: { type: Boolean, default: false },
	inactiveCount: { type: Number, default: 0 },
	pendingApprovalsCount: { type: Number, default: 0 },
	isScanning: { type: Boolean, default: false },
	scanBusy: { type: Boolean, default: false },
})

defineEmits(['navigate', 'runScan'])

// `active`: the pages that highlight the entry; `target`: what the click asks for when it differs from `view`.
const items = [
	{ view: 'overview', name: t('nexeno_freeup', 'Overview'), icon: ViewDashboardOutlineIcon, active: ['overview'] },
	{ view: 'inactive-accounts', name: t('nexeno_freeup', 'Inactive accounts'), icon: AccountClockOutlineIcon, active: ['inactive-accounts'] },
	{ view: 'accounts', target: 'storage-accounts', name: t('nexeno_freeup', 'Storage by account'), icon: ServerOutlineIcon, active: ['accounts'] },
	{ view: 'duplicates', name: t('nexeno_freeup', 'Duplicate files'), icon: FileMultipleOutlineIcon, active: ['duplicates', 'duplicate-detail'] },
	{ view: 'approvals', name: t('nexeno_freeup', 'Approvals'), icon: CheckCircleOutlineIcon, active: ['approvals'] },
	{ view: 'licences', name: t('nexeno_freeup', 'Licences per month'), icon: ChartBarIcon, active: ['licences'] },
	{ view: 'audit', name: t('nexeno_freeup', 'Recent activity'), icon: HistoryIcon, active: ['audit'], adminOnly: true },
]

const visibleItems = computed(() => items.filter((item) => !item.adminOnly || props.isAdmin))
</script>

<style lang="scss">
// Spacing as in the Files app: 8px from sidebar edges, 4px between items.
.app-navigation .app-navigation-list,
.app-navigation .app-navigation-entry__settings {
  display: flex;
  flex-direction: column;
  gap: var(--default-grid-baseline, 4px);
  padding: var(--app-navigation-padding, calc(var(--default-grid-baseline) * 2));
}

</style>

<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcButton
		v-if="view === 'overview'"
		variant="secondary"
		:title="t('nexeno_freeup', 'Download the report as a PDF')"
		:disabled="exporting"
		@click="$emit('exportOverview')">
		<template #icon>
			<NcLoadingIcon v-if="exporting" :size="20" />
			<FileDownloadOutlineIcon v-else :size="20" />
		</template>
		{{ t('nexeno_freeup', 'Export report') }}
	</NcButton>

	<NcButton
		v-else-if="(view === 'audit' && isAdmin) || view === 'licences'"
		variant="secondary"
		@click="downloadCsvReport(view)">
		<template #icon>
			<DownloadOutlineIcon :size="20" />
		</template>
		{{ t('nexeno_freeup', 'Download CSV') }}
	</NcButton>

	<NcButton
		v-else-if="view === 'duplicates' && isApprover"
		:variant="selectedDuplicateCount > 0 ? 'primary' : 'secondary'"
		:disabled="selectedDuplicateCount === 0"
		@click="$emit('proposeCleanup')">
		<template #icon>
			<SendOutlineIcon :size="20" />
		</template>
		{{ selectedDuplicateCount > 0 ? t('nexeno_freeup', 'Propose clean-up ({count})', { count: selectedDuplicateCount }) : t('nexeno_freeup', 'Propose clean-up') }}
	</NcButton>

	<NcButton
		v-else-if="view === 'approvals' && isAdmin"
		:variant="pickedRunCount > 0 ? 'primary' : 'secondary'"
		:disabled="runningArchive || (pickedRunCount === 0 && dueQueuedCount === 0)"
		@click="$emit('runArchive')">
		<template #icon>
			<NcLoadingIcon v-if="runningArchive" :size="20" />
			<ArchiveOutlineIcon v-else :size="20" />
		</template>
		{{ pickedRunCount > 0
			? t('nexeno_freeup', 'Process selected now ({count})', { count: pickedRunCount })
			: t('nexeno_freeup', 'Process approved now') }}
	</NcButton>

	<NcButton
		v-else-if="view === 'inactive-accounts'"
		:variant="selectedInactiveCount > 0 ? 'primary' : 'secondary'"
		:disabled="selectedInactiveCount === 0"
		:title="selectedInactiveCount === 0 ? t('nexeno_freeup', 'Select accounts below to propose removal') : t('nexeno_freeup', 'Propose removal for {count} selected account(s)', { count: selectedInactiveCount })"
		@click="$emit('proposeRemoval')">
		<template #icon>
			<SendOutlineIcon :size="20" />
		</template>
		{{ selectedInactiveCount > 0 ? t('nexeno_freeup', 'Propose removal ({count})', { count: selectedInactiveCount }) : t('nexeno_freeup', 'Propose removal') }}
	</NcButton>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ArchiveOutlineIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import DownloadOutlineIcon from 'vue-material-design-icons/DownloadOutline.vue'
import FileDownloadOutlineIcon from 'vue-material-design-icons/FileDownloadOutline.vue'
import SendOutlineIcon from 'vue-material-design-icons/SendOutline.vue'
import { downloadCsvReport } from '../../services/api.js'

defineProps({
	view: { type: String, required: true },
	isAdmin: { type: Boolean, default: false },
	isApprover: { type: Boolean, default: false },
	exporting: { type: Boolean, default: false },
	selectedInactiveCount: { type: Number, default: 0 },
	selectedDuplicateCount: { type: Number, default: 0 },
	/** Approvals: ticked board items and approved items past their notice period. */
	pickedRunCount: { type: Number, default: 0 },
	dueQueuedCount: { type: Number, default: 0 },
	runningArchive: { type: Boolean, default: false },
})

defineEmits(['exportOverview', 'proposeCleanup', 'runArchive', 'proposeRemoval'])
</script>

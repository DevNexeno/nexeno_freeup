<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog
		v-if="items && items.length > 0"
		:name="t('nexeno_freeup', 'Propose clean-up')"
		size="normal"
		:noClose="submitting"
		@closing="handleClose">
		<DialogBody>
			<DialogSummary>
				<DialogStat :label="n('nexeno_freeup', 'File', 'Files', items.length)">
					{{ fmtNumber(items.length) }}
				</DialogStat>
				<DialogStat :label="t('nexeno_freeup', 'Could free')">
					{{ fmtBytes(proposalWasted) }}
				</DialogStat>
				<DialogStat :label="t('nexeno_freeup', 'Copies replaced')">
					{{ fmtNumber(proposalReplaced) }}
				</DialogStat>
				<DialogStat v-if="items.length === 1 && keeperUserIdOf(items[0])" :label="t('nexeno_freeup', 'Copy kept')">
					<NcAvatar
						:user="keeperUserIdOf(items[0])"
						:size="24"
						:disableMenu="true"
						hideStatus />
					@{{ keeperUserIdOf(items[0]) }}
				</DialogStat>
			</DialogSummary>

			<DialogList :rows="proposalRows" :total="items.length" />

			<p class="dialog-explain">
				{{ items.length === 1 && keeperReasonOf(items[0])
					? keeperReasonText(keeperReasonOf(items[0]))
					: t('nexeno_freeup', 'For each file, we keep the oldest copy on an active account.') }}
			</p>

			<NcNoteCard type="info">
				{{ settings.noticeEnabled !== false
					? n('nexeno_freeup', 'Once an approver approves the request, users who have a duplicate file will be notified and given %n day to delete their copies. Otherwise, the duplicates will be moved to their trash. Everyone will retain access to one shared, read-only file.', 'Once an approver approves the request, users who have a duplicate file will be notified and given %n days to delete their copies. Otherwise, the duplicates will be moved to their trash. Everyone will retain access to one shared, read-only file.', Number(settings.noticeDays) || 14)
					: t('nexeno_freeup', 'Once an approver approves the request, the duplicates move to their users\' trash at the next run. Everyone keeps access to one shared, read-only file.') }}
			</NcNoteCard>

			<NcTextArea
				v-model="proposalReason"
				:label="t('nexeno_freeup', 'Reason for clean-up')"
				:disabled="submitting"
				rows="3" />
			<NcNoteCard v-if="proposalError" type="error">
				{{ proposalError }}
			</NcNoteCard>
		</DialogBody>
		<template #actions>
			<NcButton variant="tertiary" :disabled="submitting" @click="handleClose">
				{{ t('nexeno_freeup', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="submitting || proposalReason.trim() === ''" @click="submitProposal">
				<template #icon>
					<NcLoadingIcon v-if="submitting" :size="20" />
					<SendOutlineIcon v-else :size="20" />
				</template>
				{{ t('nexeno_freeup', 'Send for approval') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import SendOutlineIcon from 'vue-material-design-icons/SendOutline.vue'
import DialogBody from '../../components/dialog/DialogBody.vue'
import DialogList from '../../components/dialog/DialogList.vue'
import DialogStat from '../../components/dialog/DialogStat.vue'
import DialogSummary from '../../components/dialog/DialogSummary.vue'
import { apiRequest } from '../../services/api.js'
import { extensionOf, keeperReasonText } from '../../services/duplicates.js'
import { fmtBytes, fmtNumber } from '../../services/format.js'
import { getViewUrl } from '../../services/routes.js'

const props = defineProps({
	items: { type: Array, default: () => [] },
	settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'success'])

const proposalReason = ref('')
const suggestedReason = ref('')
const proposalError = ref('')
const submitting = ref(false)

const proposalWasted = computed(() => (props.items || []).reduce((a, i) => a + (Number(i.wastedBytes) || 0), 0))
const proposalReplaced = computed(() => (props.items || []).reduce((a, i) => a + Math.max(0, (Number(i.occurrences) || 1) - 1), 0))

const proposalRows = computed(() => (props.items || []).map((f) => {
	const key = f.uniqueKey || ('file-' + (f.id || f.data?.id))
	return {
		key,
		title: f.name || f.fileName || f.data?.fileName || t('nexeno_freeup', 'Duplicate file'),
		meta: fmtBytes(f.wastedBytes ?? f.data?.wastedBytes ?? 0),
		badge: extensionOf(f.name || f.fileName || f.data?.fileName),
		// A new tab, so the selection and this dialog stay as they are.
		href: getViewUrl('duplicate-detail', key),
	}
}))

function keeperUserIdOf(item) {
	if (!item?.keeper) {
		return ''
	}
	return typeof item.keeper === 'object' ? (item.keeper.userId || '') : String(item.keeper)
}

function keeperReasonOf(item) {
	if (!item?.keeper) {
		return ''
	}
	return typeof item.keeper === 'object' ? (item.keeper.reason || '') : ''
}

watch(
	() => props.items,
	(newItems) => {
		if (newItems && newItems.length > 0) {
			const occurrences = newItems[0].occurrences || newItems[0].instances?.length || 2
			proposalReason.value = newItems.length === 1
				? t('nexeno_freeup', '{count} identical copies found. Keep one shared copy.', { count: occurrences })
				: t('nexeno_freeup', 'Identical copies found for {count} files. Keep one shared copy of each.', { count: newItems.length })
			suggestedReason.value = proposalReason.value
			proposalError.value = ''
		} else {
			proposalReason.value = ''
			suggestedReason.value = ''
			proposalError.value = ''
		}
	},
	{ immediate: true },
)

function handleClose() {
	if (!submitting.value) {
		emit('close')
	}
}

async function submitProposal() {
	if (!props.items || props.items.length === 0) {
		return
	}
	submitting.value = true
	proposalError.value = ''
	try {
		const res = await apiRequest('POST', '/api/v1/cleanup/propose-bulk', {
			ids: props.items.map((i) => String(i.id || i.data?.id)),
			reason: proposalReason.value.trim(),
			reasonIsSuggestion: proposalReason.value.trim() === suggestedReason.value.trim(),
		})
		const failed = res.failed || []
		if (failed.length === 0) {
			emit('success', { failedIds: [] })
			emit('close')
		} else {
			const failedIds = failed.map((f) => String(f.id))
			proposalError.value = t('nexeno_freeup', '{ok} sent, {failed} not sent: {reason}', {
				ok: res.proposed || 0,
				failed: failed.length,
				reason: failed[0]?.error,
			})
			emit('success', { failedIds })
		}
	} catch (e) {
		proposalError.value = e?.response?.data?.error || e?.message || t('nexeno_freeup', 'Could not send the proposal.')
	} finally {
		submitting.value = false
	}
}
</script>

<style lang="scss" scoped>
.dialog-explain {
  margin: 0;
  color: var(--color-text-maxcontrast);
}
</style>

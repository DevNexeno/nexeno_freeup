<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="total > 0" class="table-load-more">
		<span class="load-more-range" role="status">
			{{ t('nexeno_freeup', 'Showing {shown} of {total}', {
				shown: fmtNumber(shown),
				total: fmtNumber(total),
			}) }}
		</span>
		<NcButton v-if="shown < total" variant="tertiary" @click="$emit('more')">
			{{ t('nexeno_freeup', 'Show more') }}
		</NcButton>
		<span
			v-if="shown < total"
			ref="sentinel"
			class="load-more-sentinel"
			aria-hidden="true" />
	</div>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { fmtNumber } from '../../services/format.js'

const props = defineProps({
	shown: { type: Number, required: true },
	total: { type: Number, required: true },
})

const emit = defineEmits(['more'])

const sentinel = ref(null)
let observer = null

function observe() {
	observer?.disconnect()
	if (!sentinel.value || typeof IntersectionObserver === 'undefined') {
		return
	}
	// Re-observing fires once straight away, so a short list keeps filling until it overflows.
	observer = new IntersectionObserver((entries) => {
		if (entries[0]?.isIntersecting) {
			emit('more')
		}
	}, { rootMargin: '300px' })
	observer.observe(sentinel.value)
}

onMounted(() => nextTick(observe))
watch(() => [props.shown, props.total], () => nextTick(observe))
onBeforeUnmount(() => observer?.disconnect())
</script>

<style lang="scss" scoped>
.table-load-more {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 3);
  padding: calc(var(--default-grid-baseline) * 3);
  border-top: 1px solid var(--color-border);
  color: var(--color-text-maxcontrast);
}

.load-more-sentinel {
  flex-basis: 100%;
  height: 1px;
  margin-top: -1px;
  pointer-events: none;
}
</style>

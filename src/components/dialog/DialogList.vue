<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dialog-list-container" :class="{ 'is-expanded': isExpanded }">
		<ul class="dialog-list">
			<li v-for="row in displayedRows" :key="row.key" class="dialog-list__row">
				<NcAvatar
					v-if="row.user"
					:user="row.user"
					:displayName="row.title"
					:size="28"
					:disableMenu="true"
					hideStatus />
				<span v-else-if="row.badge" class="dialog-list__badge" aria-hidden="true">{{ row.badge }}</span>
				<span v-if="row.sub" class="dialog-list__text">
					<span class="dialog-list__title" :title="row.title">{{ row.title }}</span>
					<span class="dialog-list__sub" :title="row.sub">{{ row.sub }}</span>
				</span>
				<span v-else class="dialog-list__title" :title="row.title">{{ row.title }}</span>
				<span v-if="row.meta" class="dialog-list__meta">{{ row.meta }}</span>
				<a
					v-if="row.href"
					class="dialog-list__link"
					:href="row.href"
					target="_blank"
					rel="noopener noreferrer"
					:aria-label="t('nexeno_freeup', 'Open details of {name} in a new tab', { name: row.title })"
					:title="t('nexeno_freeup', 'Open details in a new tab')">
					<OpenInNewIcon :size="18" />
				</a>
			</li>
		</ul>

		<div v-if="canExpand" class="dialog-list__toggle">
			<NcButton variant="tertiary-no-background" @click="isExpanded = !isExpanded">
				{{ isExpanded ? t('nexeno_freeup', 'Show less') : t('nexeno_freeup', '+{count} more', { count: hiddenCount }) }}
			</NcButton>
		</div>
	</div>
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

/** rows: [{ key, title, sub?, meta?, user?, badge?, href? }]; total = count of all items. */
const props = defineProps({
	rows: { type: Array, required: true },
	total: { type: Number, default: 0 },
	limit: { type: Number, default: 5 },
})

const isExpanded = ref(false)

const effectiveTotal = computed(() => Math.max(props.total || 0, props.rows?.length || 0))

const canExpand = computed(() => (props.rows?.length || 0) > props.limit || props.total > props.limit)

const displayedRows = computed(() => {
	if (isExpanded.value) {
		return props.rows
	}
	return (props.rows || []).slice(0, props.limit)
})

const hiddenCount = computed(() => Math.max(0, effectiveTotal.value - displayedRows.value.length))
</script>

<style scoped lang="scss">
.dialog-list-container {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  overflow: hidden;
  background: var(--color-main-background);
}

.dialog-list {
  margin: 0;
  padding: 0;
  list-style: none;
  max-height: 240px;
  overflow-y: auto;
}

.dialog-list__row {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 3);
  padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
  min-width: 0;
}

.dialog-list__row + .dialog-list__row {
  border-top: 1px solid var(--color-border);
}

.dialog-list__title {
  flex: 1;
  min-width: 0;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dialog-list__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.dialog-list__sub {
  color: var(--color-text-maxcontrast);
  font-size: var(--font-size-small, 13px);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dialog-list__link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: var(--default-clickable-area);
  height: var(--default-clickable-area);
  margin-block: calc(var(--default-grid-baseline) * -2);
  border-radius: var(--border-radius-element);
  color: var(--color-text-maxcontrast);

  &:hover,
  &:focus-visible {
    background: var(--color-background-hover);
    color: var(--color-main-text);
  }
}

.dialog-list__meta {
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
}

.dialog-list__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  padding-inline: var(--default-grid-baseline);
  border-radius: var(--border-radius-element);
  background: var(--color-background-dark);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  box-sizing: border-box;
}

.dialog-list__toggle {
  display: flex;
  justify-content: flex-start;
  padding: var(--default-grid-baseline) 0 var(--default-grid-baseline) calc(var(--default-grid-baseline) * 0.75);
  border-top: 1px solid var(--color-border);
}
</style>

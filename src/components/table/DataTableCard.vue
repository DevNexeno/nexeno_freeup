<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="data-table-card">
		<div class="data-table-card__toolbar">
			<span v-if="countLabel" class="data-table-card__count">{{ countLabel }}</span>
			<slot name="filters" />
			<div v-if="$slots.tools" class="data-table-card__tools">
				<slot name="tools" />
			</div>
		</div>

		<div v-if="loading" class="data-table-card__state">
			<NcLoadingIcon :size="40" :name="loadingLabel" />
		</div>

		<div v-else-if="isEmpty" class="data-table-card__state">
			<NcEmptyContent :name="emptyName" :description="emptyDescription">
				<template v-if="$slots.emptyIcon" #icon>
					<slot name="emptyIcon" />
				</template>
				<template v-if="$slots.emptyAction" #action>
					<slot name="emptyAction" />
				</template>
			</NcEmptyContent>
		</div>

		<div v-else class="data-table-card__scroll">
			<slot />
		</div>
	</div>
</template>

<script setup>
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

defineProps({
	countLabel: { type: String, default: '' },
	loading: { type: Boolean, default: false },
	loadingLabel: { type: String, default: '' },
	isEmpty: { type: Boolean, default: false },
	emptyName: { type: String, default: '' },
	emptyDescription: { type: String, default: '' },
})
</script>

<style lang="scss" scoped>
.data-table-card {
  background: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  overflow: hidden;
}

.data-table-card__toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 6);
  padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
  border-bottom: 1px solid var(--color-border);
}

.data-table-card__count { color: var(--color-text-maxcontrast); }

.data-table-card__tools {
  display: flex;
  align-items: center;
  flex: 1 1 auto;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: calc(var(--default-grid-baseline) * 2);
  margin-inline-start: auto;
  @media (max-width: 1563px) {
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  .data-table-card__toolbar {
    padding: calc(var(--default-grid-baseline) * 3);
    gap: calc(var(--default-grid-baseline) * 3);
  }

  .data-table-card__tools {
    flex: 1 1 100%;
    flex-wrap: nowrap;
    justify-content: stretch;
    margin-inline-start: 0;

    :deep(.table-search) { flex: 1 1 auto; width: auto; min-width: 0; }
  }
}

.data-table-card__state {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: calc(var(--default-grid-baseline) * 12) calc(var(--default-grid-baseline) * 4);
  text-align: center;

  :deep(.empty-content) { margin: 0; }
}

.data-table-card__scroll {
  width: 100%;
  overflow-x: auto;

  :deep(table) {
    width: 100%;
    border-collapse: collapse;
    text-align: start;
  }

  :deep(th) {
    padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
    font-size: 13px;
    font-weight: 600;
    background: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
    white-space: nowrap;
  }

  :deep(td) {
    padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
  }

  :deep(tbody tr:last-child td) { border-bottom: none; }
}
</style>

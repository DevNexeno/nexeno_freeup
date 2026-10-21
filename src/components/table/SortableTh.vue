<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<th
		class="trace-th-sortable"
		:class="[align === 'right' ? 'text-right' : '', thClass]"
		:aria-sort="isActive ? (sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'"
		role="columnheader"
		tabindex="0"
		@click="handleSort"
		@keydown.enter="handleSort"
		@keydown.space.prevent="handleSort">
		<span
			class="trace-th-label"
			:style="align === 'right' ? { justifyContent: 'flex-end', width: '100%' } : {}">
			<slot>{{ label }}</slot>
			<SortIcon :size="14" :active="isActive" />
		</span>
	</th>
</template>

<script setup>
import { computed } from 'vue'
import SortIcon from './SortIcon.vue'

const props = defineProps({
	column: {
		type: String,
		required: true,
	},
	sortColumn: {
		type: String,
		default: null,
	},
	sortDirection: {
		type: String,
		default: 'asc',
	},
	label: {
		type: String,
		default: '',
	},
	align: {
		type: String,
		default: 'left',
	},
	thClass: {
		type: String,
		default: '',
	},
})

const emit = defineEmits(['sort'])

const isActive = computed(() => props.sortColumn === props.column)

function handleSort() {
	emit('sort', props.column)
}
</script>

<style scoped>
.trace-th-sortable {
  cursor: pointer;
  user-select: none;
  background: transparent !important;
  background-color: transparent !important;
  box-shadow: none !important;
}

.trace-th-sortable:hover,
.trace-th-sortable:focus,
.trace-th-sortable:active {
  background: transparent !important;
  background-color: transparent !important;
  box-shadow: none !important;
  outline: none !important;
}

.trace-th-label {
  border: none !important;
  background: transparent !important;
  background-color: transparent !important;
  box-shadow: none !important;
  padding: 0 !important;
  margin: 0 !important;
  font: inherit;
  font-size: inherit;
  font-weight: inherit;
  color: inherit;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  line-height: inherit;
  text-align: left;
}

.trace-th-sortable:hover .trace-th-label {
  color: var(--color-main-text, #111827);
  background: transparent !important;
  background-color: transparent !important;
}

.trace-th-sortable:hover :deep(.trace-sort-icon-svg) {
  opacity: 1;
}

.text-right {
  text-align: right;
}
</style>

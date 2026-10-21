<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<svg
		:width="size"
		:height="size"
		viewBox="0 0 20 20"
		fill="currentColor"
		class="trace-sort-icon-svg"
		:class="{ 'trace-sort-icon-svg--active': active }"
		aria-hidden="true"
		focusable="false">
		<path d="M7.5 2.5L4.16667 5.825H6.66667V11.6667H8.33333V5.825H10.8333L7.5 2.5ZM13.3333 14.175V8.33333H11.6667V14.175H9.16667L12.5 17.5L15.8333 14.175H13.3333Z" />
	</svg>
</template>

<script setup>
defineProps({
	size: {
		type: Number,
		default: 14,
	},
	active: {
		type: Boolean,
		default: false,
	},
})
</script>

<style scoped>
.trace-sort-icon-svg {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
  color: var(--color-text-maxcontrast, #6b7280);
  opacity: 0.65;
  background: transparent !important;
  background-color: transparent !important;
  transition: opacity 0.15s ease, color 0.15s ease;
}

.trace-sort-icon-svg--active {
  color: var(--color-primary-element, #0082c9);
  opacity: 1;
}
</style>

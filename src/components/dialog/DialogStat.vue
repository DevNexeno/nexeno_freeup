<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dialog-stat">
		<span class="dialog-stat__label">{{ label }}</span>
		<span class="dialog-stat__value"><slot /></span>
	</div>
</template>

<script setup>
defineProps({
	label: { type: String, required: true },
})
</script>

<style scoped>
.dialog-stat {
  display: flex;
  flex-direction: column;
  gap: var(--default-grid-baseline);
  padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
  background: var(--color-main-background);
  min-width: 0;
}

.dialog-stat__label {
  color: var(--color-text-maxcontrast);
  font-size: 13px;
}

.dialog-stat__value {
  display: flex;
  align-items: center;
  gap: calc(var(--default-grid-baseline) * 2);
  min-height: 28px;
  font-size: 20px;
  font-weight: 700;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

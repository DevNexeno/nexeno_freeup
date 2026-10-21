<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dialog-body">
		<slot />
	</div>
</template>

<style scoped>
.dialog-body {
  display: flex;
  flex-direction: column;
  gap: calc(var(--default-grid-baseline) * 4);
  padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 3);
  box-sizing: border-box;
  min-width: 0;
}
</style>

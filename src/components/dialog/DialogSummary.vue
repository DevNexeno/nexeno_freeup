<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="dialog-summary">
		<slot />
	</div>
</template>

<style scoped>
.dialog-summary {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(min-content, 1fr);
  gap: 1px;
  background: var(--color-border);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large);
  overflow: hidden;
}

@media (max-width: 600px) {
  .dialog-summary {
    grid-auto-flow: row;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-columns: auto;
  }
}
</style>

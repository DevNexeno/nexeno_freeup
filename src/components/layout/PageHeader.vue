<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="trace-toolbar-header">
		<div class="toolbar-header-main">
			<h1 class="trace-toolbar-title">
				{{ title }}
			</h1>
			<div v-if="organisation" class="trace-toolbar-meta">
				<span class="sub-org">{{ organisation }}</span>
				<span class="sub-sep">·</span>
				<span>{{ subtitle }}</span>
			</div>
			<div v-else-if="subtitle" class="trace-toolbar-meta">
				<span>{{ subtitle }}</span>
			</div>
		</div>

		<div class="toolbar-header-actions">
			<slot name="actions" />
		</div>
	</div>
</template>

<script setup>
defineProps({
	title: { type: String, required: true },
	subtitle: { type: String, default: '' },
	organisation: { type: String, default: '' },
})
</script>

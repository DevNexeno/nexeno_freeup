<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcTextField
		:modelValue="modelValue"
		class="table-search"
		:label="label"
		:placeholder="placeholder"
		:showTrailingButton="modelValue !== ''"
		trailingButtonIcon="close"
		@update:modelValue="$emit('update:modelValue', $event)"
		@trailingButtonClick="$emit('update:modelValue', '')">
		<MagnifyIcon :size="18" />
	</NcTextField>
</template>

<script setup>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'

defineProps({
	modelValue: { type: String, default: '' },
	label: { type: String, required: true },
	placeholder: { type: String, default: '' },
})

defineEmits(['update:modelValue'])
</script>

<style scoped>
.table-search {
  width: 300px;
  max-width: 100%;
  flex: 0 1 300px;
}

@media (max-width: 512px) {
  .table-search {
    width: 100%;
    flex-basis: 100%;
  }
}
</style>

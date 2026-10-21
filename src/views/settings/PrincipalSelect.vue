<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSelectUsers
		:modelValue="selected"
		:options="options"
		:inputId="inputId"
		:inputLabel="label"
		labelOutside
		multiple
		:loading="loading"
		:placeholder="placeholder"
		@search="onSearch"
		@update:modelValue="onChange" />
</template>

<script setup>
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import { apiRequest } from '../../services/api.js'

/** `groups`: group ids; `users`: account ids; `names`: display names of users not in the search results. */
const props = defineProps({
	groups: { type: Array, default: () => [] },
	users: { type: Array, default: () => [] },
	names: { type: Object, default: () => ({}) },
	label: { type: String, required: true },
	inputId: { type: String, required: true },
	placeholder: { type: String, default: '' },
})

const emit = defineEmits(['update:groups', 'update:users', 'update:names'])

const loading = ref(false)
const found = ref([])
let timer = null

const groupOption = (id) => ({ id: `group:${id}`, displayName: id, subname: t('nexeno_freeup', 'Group'), isNoUser: true })
const userOption = (id, name, email = '') => ({ id: `user:${id}`, user: id, displayName: name || id, subname: email })

const selected = computed(() => [
	...props.groups.map(groupOption),
	...props.users.map((id) => userOption(id, props.names[id])),
])

const options = computed(() => {
	const chosen = new Set(selected.value.map((o) => o.id))
	const fromSearch = found.value
		.map((r) => (r.type === 'group' ? groupOption(r.id) : userOption(r.id, r.label, r.subname)))
		.filter((o) => !chosen.has(o.id))
	return [...selected.value, ...fromSearch]
})

async function load(search) {
	loading.value = true
	try {
		const res = await apiRequest('GET', '/api/v1/principals', null, { search })
		found.value = res.results || []
	} catch {
		found.value = []
	} finally {
		loading.value = false
	}
}

function onSearch(query) {
	clearTimeout(timer)
	timer = setTimeout(() => load(query), 250)
}

function onChange(value) {
	const list = Array.isArray(value) ? value : []
	const groups = list.filter((o) => o.id.startsWith('group:')).map((o) => o.id.slice(6))
	const users = list.filter((o) => o.id.startsWith('user:')).map((o) => o.id.slice(5))
	// Remember names of chosen people so chips show names, not ids.
	const names = { ...props.names }
	list.filter((o) => o.id.startsWith('user:')).forEach((o) => {
		names[o.user] = o.displayName
	})
	emit('update:names', names)
	emit('update:groups', groups)
	emit('update:users', users)
}

onMounted(() => load(''))
onBeforeUnmount(() => clearTimeout(timer))
</script>

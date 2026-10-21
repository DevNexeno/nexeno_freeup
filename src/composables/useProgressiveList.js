/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref, watch } from 'vue'

export const LIST_PAGE_SIZE = 50

/**
 * @param {import('vue').Ref<Array>} source filtered and sorted rows
 * @param {number} pageSize rows added per load
 */
export function useProgressiveList(source, pageSize = LIST_PAGE_SIZE) {
	const limit = ref(pageSize)

	const displayed = computed(() => source.value.slice(0, limit.value))
	const shown = computed(() => displayed.value.length)
	const total = computed(() => source.value.length)

	function loadMore() {
		if (limit.value < source.value.length) {
			limit.value += pageSize
		}
	}

	watch(source, () => {
		limit.value = pageSize
	})

	return { displayed, shown, total, loadMore }
}

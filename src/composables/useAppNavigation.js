/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref, watch } from 'vue'
import { resolveDuplicateItemById } from '../services/duplicates.js'
import { ADMIN_VIEWS, getViewUrl, parseViewFromUrl, VALID_VIEWS } from '../services/routes.js'

/**
 * @param {ReturnType<import('./useFreeUpData').useFreeUpData>} data
 */
export function useAppNavigation(data) {
	const currentView = ref('overview')
	const selectedDuplicateItem = ref(null)
	const selectedDuplicateItemId = ref(null)

	const viewUrl = (view, itemId = null) => getViewUrl(view, itemId || selectedDuplicateItemId.value)

	// a detail page opened by URL needs the duplicates loaded first
	watch(data.duplicates, () => {
		if (currentView.value === 'duplicate-detail' && selectedDuplicateItemId.value) {
			const resolved = resolveDuplicateItemById(data.duplicates.value, selectedDuplicateItemId.value)
			if (resolved) {
				selectedDuplicateItem.value = resolved
			}
		}
	})

	function navigate(view, updateHistory = true) {
		if (view === 'storage-accounts') {
			view = 'accounts'
		}
		if (!VALID_VIEWS.includes(view)) {
			view = 'overview'
		}
		if (view === 'duplicate-detail' && !selectedDuplicateItem.value && !selectedDuplicateItemId.value) {
			view = 'duplicates'
		}
		if (view !== 'duplicate-detail') {
			selectedDuplicateItem.value = null
			selectedDuplicateItemId.value = null
		}
		if (ADMIN_VIEWS.includes(view) && !data.isAdmin.value) {
			view = 'overview'
		}
		currentView.value = view

		if (updateHistory) {
			const targetUrl = viewUrl(view)
			const currentFull = window.location.pathname + (window.location.search || '')
			if (currentFull !== targetUrl || window.location.hash) {
				window.history.pushState({ view }, '', targetUrl)
			}
		}

		data.loadViewData(view, false)
	}

	function showDuplicateDetail(itemId) {
		selectedDuplicateItemId.value = itemId
		selectedDuplicateItem.value = resolveDuplicateItemById(data.duplicates.value, itemId)
		currentView.value = 'duplicate-detail'
		data.loadDuplicates()
	}

	function openDuplicateDetail(item) {
		const itemId = item.uniqueKey || (item.id ? `file-${item.id}` : null)
		selectedDuplicateItemId.value = itemId
		const resolved = itemId ? resolveDuplicateItemById(data.duplicates.value, itemId) : null
		selectedDuplicateItem.value = resolved || item
		currentView.value = 'duplicate-detail'
		window.history.pushState({ view: 'duplicate-detail', itemId }, '', viewUrl('duplicate-detail', itemId))
	}

	function closeDuplicateDetail() {
		selectedDuplicateItem.value = null
		selectedDuplicateItemId.value = null
		navigate('duplicates', true)
	}

	function onPopState() {
		const { view, itemId } = parseViewFromUrl()
		if (view === 'duplicate-detail' && itemId) {
			showDuplicateDetail(itemId)
		} else {
			selectedDuplicateItem.value = null
			selectedDuplicateItemId.value = null
			navigate(view, false)
		}
	}

	// call once settings are known, admin-only views depend on them
	function start() {
		const { view, itemId } = parseViewFromUrl()
		if (view === 'duplicate-detail' && itemId) {
			showDuplicateDetail(itemId)
		} else {
			navigate(ADMIN_VIEWS.includes(view) && !data.settings.value?.isAdmin ? 'overview' : view, false)
		}
		window.addEventListener('popstate', onPopState)
	}

	function stop() {
		window.removeEventListener('popstate', onPopState)
	}

	return {
		currentView,
		selectedDuplicateItem,
		navigate,
		openDuplicateDetail,
		closeDuplicateDetail,
		start,
		stop,
	}
}

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'

// must match appinfo/routes.php
export const VALID_VIEWS = ['overview', 'inactive-accounts', 'accounts', 'approvals', 'duplicates', 'licences', 'audit', 'settings', 'duplicate-detail']

export const ADMIN_VIEWS = ['audit', 'settings']

export function getViewUrl(view, itemId = null) {
	if (view === 'duplicate-detail') {
		return generateUrl('/apps/nexeno_freeup/duplicates' + (itemId ? '?id=' + encodeURIComponent(itemId) : ''))
	}
	if (view === 'inactive-accounts') {
		return generateUrl('/apps/nexeno_freeup/inactive-accounts')
	}
	return generateUrl('/apps/nexeno_freeup/' + (view === 'overview' ? '' : view))
}

/**
 * Reads the page (and the duplicate being shown, if any) from the current address.
 *
 * @return {{view: string, itemId: string|null}}
 */
export function parseViewFromUrl() {
	const searchParams = new URLSearchParams(window.location.search)
	const paramId = searchParams.get('id')

	const path = window.location.pathname || ''
	const match = path.match(/\/apps\/nexeno_freeup\/?([a-zA-Z0-9_-]+)?/)
	const pathView = match && match[1] && VALID_VIEWS.includes(match[1].toLowerCase()) ? match[1].toLowerCase() : null

	if (paramId && (pathView === 'duplicates' || !pathView)) {
		return { view: 'duplicate-detail', itemId: paramId }
	}

	if (pathView) {
		return { view: pathView, itemId: null }
	}

	const paramView = searchParams.get('view')
	if (paramView && VALID_VIEWS.includes(paramView.toLowerCase())) {
		if (paramView.toLowerCase() === 'duplicate-detail' || (paramView.toLowerCase() === 'duplicates' && paramId)) {
			return { view: 'duplicate-detail', itemId: paramId }
		}
		return { view: paramView.toLowerCase(), itemId: null }
	}

	return { view: 'overview', itemId: null }
}

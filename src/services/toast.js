/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Nextcloud exposes toasts as `OCP.Toast`
function toast(kind, message) {
	const api = window.OCP?.Toast
	if (api && typeof api[kind] === 'function') {
		api[kind](message)
	} else {
		// eslint-disable-next-line no-console -- only reached outside a Nextcloud page, where there is no toast to show
		console.warn(`[nexeno_freeup] ${message}`)
	}
}

export const notifySuccess = (message) => toast('success', message)
export const notifyError = (message) => toast('error', message)
export const notifyWarning = (message) => toast('warning', message)
export const notifyInfo = (message) => toast('info', message)

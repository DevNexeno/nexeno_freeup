/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n } from '@nextcloud/l10n'
import { apiRequest } from '../services/api.js'
import { notifyInfo } from '../services/toast.js'

const CHECK_EVERY = 15 * 60 * 1000

/**
 * Keeps automatic processing on time when Nextcloud's background jobs are late (no system cron): while an approver has
 * FreeUp open, the server does any overdue work.
 *
 * @param data
 */
export function useAutomation(data) {
	let timer = null

	async function check() {
		if (!data.isApprover.value) {
			return
		}
		try {
			const res = await apiRequest('POST', '/api/v1/catch-up')
			const done = (res.accounts || 0) + (res.cleanups || 0)
			if (done > 0) {
				notifyInfo(n('nexeno_freeup', '{count} overdue item was processed because background jobs were running late.', '{count} overdue items were processed because background jobs were running late.', done, { count: done }))
				data.forget('overview', 'accounts', 'duplicates', 'licences')
				await Promise.all([data.loadOverview(true), data.loadApprovals(true), data.loadAccounts(true)])
			}
		} catch {
			// the pages work without it
		}
	}

	function start() {
		check()
		timer = window.setInterval(check, CHECK_EVERY)
	}

	function stop() {
		window.clearInterval(timer)
		timer = null
	}

	return { start, stop }
}

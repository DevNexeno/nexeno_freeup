/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib'
import { fmtBytes, fmtDateTime, fmtMonth, fmtMonthYear, fmtNumber, fmtDate as formatDay } from './format.js'
import { licenceWindowMonths, notLoggedInText, parseMonth } from './licenceWindow.js'

// Privacy: counts and totals only, no names, e-mail addresses or file paths (same rule as the monthly licence report).

const PAGE = [595.28, 841.89]
const MARGIN = 40
const CONTENT_W = PAGE[0] - MARGIN * 2
const FOOTER_H = 34

const INK = rgb(0.11, 0.12, 0.14)
const MUTED = rgb(0.4, 0.43, 0.47)
const LINE = rgb(0.87, 0.88, 0.9)
const SOFT = rgb(0.96, 0.97, 0.98)
const WHITE = rgb(1, 1, 1)
const WARN = rgb(0.85, 0.52, 0.06)
const SLATE = rgb(0.72, 0.76, 0.81)

const DEFAULT_ACCENT = '#00679e'

function parseColour(value) {
	const v = String(value || '').trim()
	let m = v.match(/^#([0-9a-f]{3})$/i)
	if (m) {
		const [r, g, b] = m[1].split('').map((c) => parseInt(c + c, 16))
		return [r, g, b]
	}
	m = v.match(/^#([0-9a-f]{6})$/i)
	if (m) {
		return [0, 2, 4].map((i) => parseInt(m[1].slice(i, i + 2), 16))
	}
	m = v.match(/^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/i)
	if (m) {
		return [Number(m[1]), Number(m[2]), Number(m[3])]
	}
	return parseColour(DEFAULT_ACCENT)
}

const toRgb = ([r, g, b]) => rgb(r / 255, g / 255, b / 255)
const mix = ([r, g, b], amount) => [r, g, b].map((c) => Math.round(c + (255 - c) * amount))
const isDark = ([r, g, b]) => (0.299 * r + 0.587 * g + 0.114 * b) < 150

export function themeAccent() {
	try {
		const styles = window.getComputedStyle(document.documentElement)
		return (styles.getPropertyValue('--color-primary-element') || styles.getPropertyValue('--color-primary')).trim() || DEFAULT_ACCENT
	} catch {
		return DEFAULT_ACCENT
	}
}

function toDate(value) {
	if (!value) {
		return null
	}
	const d = typeof value === 'number' ? new Date(value < 1e12 ? value * 1000 : value) : new Date(value)
	return Number.isNaN(d.getTime()) ? null : d
}

/** `top` is the distance of the next element from the top edge of the page. */
class Sheet {
	constructor(doc, regular, bold) {
		this.doc = doc
		this.regular = regular
		this.bold = bold
		this.page = null
		this.top = 0
		this.newPage()
	}

	newPage() {
		this.page = this.doc.addPage(PAGE)
		this.top = MARGIN
		return this.page
	}

	ensure(height) {
		if (this.top > MARGIN && this.top + height > PAGE[1] - MARGIN - FOOTER_H) {
			this.newPage()
		}
	}

	// standard PDF fonts are Western-only: other characters become "?" instead of breaking the export
	safe(text, font) {
		let out = ''
		// narrow spaces some locales use for digit grouping are missing from the standard fonts
		for (const ch of String(text ?? '').replace(/[\u2007\u2009\u202f]/g, '\u00a0')) {
			try {
				font.encodeText(ch)
				out += ch
			} catch {
				out += '?'
			}
		}
		return out
	}

	width(text, size, bold = false) {
		const font = bold ? this.bold : this.regular
		return font.widthOfTextAtSize(this.safe(text, font), size)
	}

	fit(text, size, maxWidth, bold = false) {
		let s = String(text ?? '')
		if (this.width(s, size, bold) <= maxWidth) {
			return s
		}
		while (s.length > 1 && this.width(s + '…', size, bold) > maxWidth) {
			s = s.slice(0, -1)
		}
		return s + '…'
	}

	wrap(text, size, maxWidth, bold = false) {
		const words = String(text ?? '').split(/\s+/).filter(Boolean)
		const lines = []
		let line = ''
		for (const word of words) {
			const next = line ? `${line} ${word}` : word
			if (line && this.width(next, size, bold) > maxWidth) {
				lines.push(line)
				line = word
			} else {
				line = next
			}
		}
		if (line) {
			lines.push(line)
		}
		return lines
	}

	// `base` is the baseline distance from the top of the page
	text(text, x, base, { size = 10, bold = false, color = INK, align = 'left', page = this.page } = {}) {
		const font = bold ? this.bold : this.regular
		const safe = this.safe(text, font)
		const w = font.widthOfTextAtSize(safe, size)
		const px = align === 'right' ? x - w : align === 'center' ? x - w / 2 : x
		page.drawText(safe, { x: px, y: PAGE[1] - base, size, font, color })
	}

	rect(x, top, width, height, { fill, border, borderWidth = 0.75 } = {}) {
		this.page.drawRectangle({
			x,
			y: PAGE[1] - top - height,
			width,
			height,
			color: fill,
			borderColor: border,
			borderWidth: border ? borderWidth : 0,
		})
	}

	round(x, top, width, height, radius, { fill, border, borderWidth = 0.75 } = {}) {
		const r = Math.min(radius, width / 2, height / 2)
		const d = `M ${r} 0 H ${width - r} A ${r} ${r} 0 0 1 ${width} ${r} V ${height - r} A ${r} ${r} 0 0 1 ${width - r} ${height} H ${r} A ${r} ${r} 0 0 1 0 ${height - r} V ${r} A ${r} ${r} 0 0 1 ${r} 0 Z`
		this.page.drawSvgPath(d, {
			x,
			y: PAGE[1] - top,
			color: fill,
			borderColor: border,
			borderWidth: border ? borderWidth : 0,
		})
	}

	line(x1, x2, top, color = LINE, thickness = 0.75) {
		this.page.drawLine({ start: { x: x1, y: PAGE[1] - top }, end: { x: x2, y: PAGE[1] - top }, thickness, color })
	}
}

export async function buildOverviewPdf({ overview = {}, history = [], accent = DEFAULT_ACCENT, now = new Date() }) {
	const num = fmtNumber
	const accentRgb = parseColour(accent)
	const ACCENT = toRgb(accentRgb)
	const ACCENT_SOFT = toRgb(mix(accentRgb, 0.9))
	const onAccent = isDark(accentRgb) ? WHITE : INK
	const onAccentMuted = isDark(accentRgb) ? toRgb(mix(accentRgb, 0.78)) : rgb(0.25, 0.27, 0.3)

	const doc = await PDFDocument.create()
	const regular = await doc.embedFont(StandardFonts.Helvetica)
	const bold = await doc.embedFont(StandardFonts.HelveticaBold)
	const s = new Sheet(doc, regular, bold)

	const org = overview.instanceName || 'Nextcloud'
	const fmtDate = (d, withTime = false) => (withTime ? fmtDateTime(d) : formatDay(d))
	const lastScan = toDate(overview.lastScan)
	const threshold = Number(overview.inactivityThresholdMonths) || 6

	doc.setTitle(t('nexeno_freeup', 'Account and storage report'))
	doc.setAuthor('Nexeno FreeUp')
	doc.setSubject(org)
	doc.setCreationDate(now)

	s.rect(0, 0, PAGE[0], 120, { fill: ACCENT })
	s.text('NEXENO FREEUP', MARGIN, 44, { size: 9, bold: true, color: onAccentMuted })
	s.text(t('nexeno_freeup', 'Account and storage report'), MARGIN, 76, { size: 24, bold: true, color: onAccent })
	s.text(s.fit(org, 11, CONTENT_W - 200, false), MARGIN, 98, { size: 11, color: onAccent })
	s.text(
		t('nexeno_freeup', 'Created {date}', { date: fmtDate(now, true) }),
		PAGE[0] - MARGIN,
		44,
		{ size: 9, color: onAccentMuted, align: 'right' },
	)
	s.text(
		lastScan
			? t('nexeno_freeup', 'Last scan {date}', { date: fmtDate(lastScan, true) })
			: t('nexeno_freeup', 'No scan yet'),
		PAGE[0] - MARGIN,
		98,
		{ size: 9, color: onAccentMuted, align: 'right' },
	)
	s.top = 120 + 26

	// `needs`: height of what follows, so a heading is never orphaned at the page bottom
	const section = (title, note = '', needs = 18) => {
		s.ensure(28 + needs)
		s.rect(MARGIN, s.top, 3.5, 15, { fill: ACCENT })
		s.text(title, MARGIN + 11, s.top + 12, { size: 13, bold: true })
		if (note) {
			s.text(note, PAGE[0] - MARGIN, s.top + 12, { size: 8.5, color: MUTED, align: 'right' })
		}
		s.top += 28
	}

	const inactive = Number(overview.inactiveCandidates) || 0
	const total = Number(overview.totalAccounts) || 0
	const pct = (a, b) => (b > 0 ? Math.round((a / b) * 100) : 0)
	const inactiveLabel = t('nexeno_freeup', 'Inactive accounts')
	const currentMonthShort = fmtMonth(now, 'short')

	const kpis = [
		{
			label: t('nexeno_freeup', 'Licences in use ({month})', { month: currentMonthShort }),
			value: num(overview.licencesInUse ?? total),
			sub: notLoggedInText(history, now) || t('nexeno_freeup', '{count} active', { count: num(overview.activeAccounts) }),
		},
		{
			label: inactiveLabel,
			value: num(inactive),
			sub: t('nexeno_freeup', '{percent}% of all accounts', { percent: pct(inactive, total) }),
		},
		{
			label: t('nexeno_freeup', 'Storage used by inactive accounts'),
			value: fmtBytes(overview.inactiveStorageBytes),
			sub: t('nexeno_freeup', '{percent}% of {used} used', {
				percent: pct(Number(overview.inactiveStorageBytes) || 0, Number(overview.totalStorageBytes) || 0),
				used: fmtBytes(overview.totalStorageBytes),
			}),
		},
		{
			label: t('nexeno_freeup', 'Space used by duplicate files'),
			value: fmtBytes(overview.duplicateWastedBytes),
			sub: n('nexeno_freeup', '{count} extra copy', '{count} extra copies', Number(overview.extraCopiesCount) || 0, { count: num(overview.extraCopiesCount) }),
		},
	]

	const gap = 10
	const cardW = (CONTENT_W - gap * 3) / 4
	const cardH = 90
	const innerPad = 10
	const maxW = cardW - innerPad * 2
	kpis.forEach((k, i) => {
		const x = MARGIN + i * (cardW + gap)
		s.round(x, s.top, cardW, cardH, 7, { fill: WHITE, border: LINE })

		const labelLines = s.wrap(k.label, 8.5, maxW).slice(0, 2)
		if (labelLines.length === 1) {
			s.text(labelLines[0], x + innerPad, s.top + 20, { size: 8.5, color: MUTED })
		} else {
			labelLines.forEach((line, li) => {
				s.text(line, x + innerPad, s.top + 16 + li * 10, { size: 8.5, color: MUTED })
			})
		}

		s.text(s.fit(k.value, 20, maxW, true), x + innerPad, s.top + 51, { size: 20, bold: true })

		const subText = String(k.sub || '')
		if (s.width(subText, 8) <= maxW) {
			s.text(subText, x + innerPad, s.top + 74, { size: 8, color: MUTED })
		} else if (s.width(subText, 7.5) <= maxW) {
			s.text(subText, x + innerPad, s.top + 74, { size: 7.5, color: MUTED })
		} else {
			const subLines = s.wrap(subText, 7.5, maxW).slice(0, 2)
			if (subLines.length === 1) {
				s.text(subLines[0], x + innerPad, s.top + 74, { size: 7.5, color: MUTED })
			} else {
				s.text(subLines[0], x + innerPad, s.top + 68, { size: 7.5, color: MUTED })
				s.text(s.fit(subLines[1], 7.5, maxW), x + innerPad, s.top + 78, { size: 7.5, color: MUTED })
			}
		}
	})
	s.top += cardH + 14

	s.round(MARGIN, s.top, CONTENT_W, 56, 7, { fill: ACCENT_SOFT })
	s.rect(MARGIN, s.top + 8, 3.5, 40, { fill: ACCENT })
	s.text(t('nexeno_freeup', 'Could be freed in total'), MARGIN + 18, s.top + 22, { size: 8.5, bold: true, color: MUTED })
	s.text(fmtBytes(overview.potentialReclaimableBytes), MARGIN + 18, s.top + 46, { size: 22, bold: true, color: ACCENT })
	const freeDetail = t('nexeno_freeup', 'Inactive accounts {inactive} + duplicate files {duplicates}', {
		inactive: fmtBytes(overview.inactiveStorageBytes),
		duplicates: fmtBytes(overview.duplicateWastedBytes),
	})
	s.text(s.fit(freeDetail, 9, CONTENT_W - 160), PAGE[0] - MARGIN - 16, s.top + 33, { size: 9, color: MUTED, align: 'right' })
	s.top += 56 + 26

	section(t('nexeno_freeup', 'What needs attention'))
	const open = Number(overview.openApprovals ?? overview.pendingApprovals) || 0
	const dupBytes = Number(overview.duplicateWastedBytes) || 0
	const attention = []
	if (inactive > 0) {
		const inactiveDesc = t('nexeno_freeup', 'inactive for {threshold}+ months', { threshold })

		const inactiveCountText = n(
			'nexeno_freeup',
			'{count} account',
			'{count} accounts',
			inactive,
			{ count: num(inactive) },
		)

		attention.push({
			colour: WARN,
			text: `${inactiveCountText} ${inactiveDesc}`,
		})
	}
	if (open > 0) {
		const proposalCountText = n(
			'nexeno_freeup',
			'{count} proposal',
			'{count} proposals',
			open,
			{ count: num(open) },
		)
		attention.push({
			colour: ACCENT,
			text: `${proposalCountText} ${t('nexeno_freeup', 'waiting for approval')}`,
		})
	}
	if (dupBytes > 0) {
		attention.push({
			colour: ACCENT,
			text: t('nexeno_freeup', 'Duplicate files occupy {size}. Review them and propose a clean-up.', {
				size: fmtBytes(dupBytes),
			}),
		})
	}
	if (attention.length === 0) {
		s.text(t('nexeno_freeup', 'Nothing needs your attention right now.'), MARGIN + 11, s.top + 4, { size: 10, color: MUTED })
		s.top += 28
	} else {
		attention.forEach((a) => {
			s.page.drawCircle({ x: MARGIN + 14, y: PAGE[1] - s.top + 3.5, size: 3, color: a.colour })
			const lines = s.wrap(a.text, 10, CONTENT_W - 26)
			lines.forEach((line, li) => {
				s.text(line, MARGIN + 26, s.top + li * 14, { size: 10 })
			})
			s.top += Math.max(20, lines.length * 14 + 6)
		})
		s.top += 10
	}

	const byMonth = new Map((history || []).map((h) => [h.month || h.snapshotMonth, h]))
	const months = licenceWindowMonths(history)
	const slots = months.map((key) => ({ key, row: byMonth.get(key) || null }))
	const recorded = slots.filter((x) => x.row)

	const chartH = 160
	section(t('nexeno_freeup', 'Licences in use over the last 12 months'), '', chartH + 40)
	const plotLeft = MARGIN + 30
	const plotW = CONTENT_W - 30
	const plotTop = s.top + 28
	const plotH = chartH - 52

	s.rect(MARGIN, s.top + 2, 9, 9, { fill: ACCENT })
	s.text(t('nexeno_freeup', 'Paid licences'), MARGIN + 14, s.top + 10, { size: 8.5, color: MUTED })
	const legendX = MARGIN + 14 + s.width(t('nexeno_freeup', 'Paid licences'), 8.5) + 18
	s.rect(legendX, s.top + 2, 9, 9, { fill: toRgb(mix(accentRgb, 0.7)) })
	s.text(t('nexeno_freeup', 'Logged in during the month'), legendX + 14, s.top + 10, { size: 8.5, color: MUTED })

	const peak = Math.max(1, ...recorded.map((x) => Number(x.row.licencesInUse) || 0))
	// headroom so bar values do not collide with the legend or top grid line
	const rough = Math.max(1, (peak * 1.15) / 4)
	const pow = 10 ** Math.floor(Math.log10(rough))
	const step = [1, 2, 2.5, 3, 4, 5, 6, 8, 10].map((m) => m * pow).find((v) => v >= rough) || pow * 10
	const axisMax = step * 4
	for (let i = 0; i <= 4; i += 1) {
		const y = plotTop + plotH - (plotH * i) / 4
		s.line(plotLeft, plotLeft + plotW, y, i === 0 ? SLATE : LINE, i === 0 ? 1 : 0.5)
		s.text(num(step * i), plotLeft - 8, y + 2.5, { size: 7.5, color: MUTED, align: 'right' })
	}
	const slotW = plotW / months.length
	const barW = Math.min(18, slotW * 0.3)
	slots.forEach((slot, i) => {
		const cx = plotLeft + slotW * i + slotW / 2
		const date = parseMonth(slot.key)
		s.text(fmtMonth(date, 'short'), cx, plotTop + plotH + 13, { size: 8, color: slot.row ? INK : MUTED, align: 'center' })
		if (i === 0 || date.getMonth() === 0) {
			s.text(String(date.getFullYear()), cx, plotTop + plotH + 23, { size: 7, color: MUTED, align: 'center' })
		}
		if (!slot.row) {
			return
		}
		const paid = Number(slot.row.licencesInUse) || 0
		const logged = slot.row.loggedIn === null || slot.row.loggedIn === undefined ? null : Number(slot.row.loggedIn) || 0
		const paidH = (plotH * paid) / axisMax
		s.rect(cx - barW - 1.5, plotTop + plotH - paidH, barW, paidH, { fill: ACCENT })
		s.text(num(paid), cx - barW / 2 - 1.5, plotTop + plotH - paidH - 4, { size: 7.5, bold: true, align: 'center' })
		if (logged !== null) {
			const loggedH = (plotH * logged) / axisMax
			s.rect(cx + 1.5, plotTop + plotH - loggedH, barW, loggedH, { fill: toRgb(mix(accentRgb, 0.7)) })
			s.text(num(logged), cx + barW / 2 + 1.5, plotTop + plotH - loggedH - 4, { size: 7.5, bold: true, align: 'center' })
		}
	})
	s.top += chartH + 22

	if (recorded.length > 0) {
		const cols = [
			{ label: t('nexeno_freeup', 'Month'), x: MARGIN + 12, align: 'left' },
			{ label: t('nexeno_freeup', 'Paid licences'), x: MARGIN + CONTENT_W * 0.55, align: 'right' },
			{ label: t('nexeno_freeup', 'Logged in'), x: MARGIN + CONTENT_W * 0.77, align: 'right' },
			{ label: t('nexeno_freeup', 'No login'), x: MARGIN + CONTENT_W - 12, align: 'right' },
		]
		const rowH = 22
		const head = () => {
			s.rect(MARGIN, s.top, CONTENT_W, rowH, { fill: SOFT })
			cols.forEach((c) => s.text(c.label, c.x, s.top + 15, { size: 8.5, bold: true, color: MUTED, align: c.align }))
			s.top += rowH
		}
		s.ensure(rowH * 3)
		head();
		[...recorded].reverse().forEach((slot) => {
			if (s.top + rowH > PAGE[1] - MARGIN - FOOTER_H) {
				s.newPage()
				head()
			}
			const r = slot.row
			const cells = [
				fmtMonthYear(parseMonth(slot.key)),
				num(r.licencesInUse),
				r.loggedIn === null || r.loggedIn === undefined ? '–' : num(r.loggedIn),
				r.notUsed === null || r.notUsed === undefined ? '–' : num(r.notUsed),
			]
			cells.forEach((c, ci) => s.text(c, cols[ci].x, s.top + 15, { size: 9.5, bold: ci === 3, align: cols[ci].align }))
			s.line(MARGIN, MARGIN + CONTENT_W, s.top + rowH, LINE, 0.5)
			s.top += rowH
		})
		s.top += 26
	}

	const colGap = 20
	const colW = (CONTENT_W - colGap) / 2
	const accounts = [
		[t('nexeno_freeup', 'Total accounts'), num(total)],
		[t('nexeno_freeup', 'Active'), num(overview.activeAccounts)],
		[t('nexeno_freeup', 'Inactive for {threshold}+ months', { threshold }), num(inactive)],
		[t('nexeno_freeup', 'Waiting for a decision'), num(open)],
		[t('nexeno_freeup', 'Approved and queued'), num(overview.queuedArchives)],
		[t('nexeno_freeup', 'Disabled'), num((Number(overview.archivedAccounts) || 0) + (Number(overview.disabledAccounts) || 0))],
	]
	const storage = [
		[t('nexeno_freeup', 'Capacity'), fmtBytes(overview.storageCapacityBytes)],
		[t('nexeno_freeup', 'Used'), fmtBytes(overview.totalStorageBytes)],
		[t('nexeno_freeup', 'Available storage'), fmtBytes(overview.storageFreeBytes)],
		[t('nexeno_freeup', 'Held by inactive accounts'), fmtBytes(overview.inactiveStorageBytes)],
		[t('nexeno_freeup', 'Freed by FreeUp'), fmtBytes(overview.reclaimedStorageBytes)],
		[t('nexeno_freeup', 'Space used by duplicate files'), fmtBytes(overview.duplicateWastedBytes)],
	]
	const listH = 28 + Math.max(accounts.length, storage.length) * 22
	section(t('nexeno_freeup', 'Accounts and storage'), '', listH)
	const drawList = (x, title, rows) => {
		s.text(title, x, s.top + 12, { size: 9, bold: true, color: MUTED })
		s.line(x, x + colW, s.top + 20, LINE, 0.75)
		rows.forEach(([label, value], i) => {
			const base = s.top + 40 + i * 22
			const maxLabelW = colW - Math.max(50, s.width(value, 10, true) + 12)
			s.text(s.fit(label, 9.5, maxLabelW), x, base, { size: 9.5 })
			s.text(value, x + colW, base, { size: 10, bold: true, align: 'right' })
			s.line(x, x + colW, base + 8, LINE, 0.4)
		})
	}
	drawList(MARGIN, t('nexeno_freeup', 'ACCOUNTS'), accounts)
	drawList(MARGIN + colW + colGap, t('nexeno_freeup', 'STORAGE'), storage)
	s.top += listH + 14

	const parts = [
		{ label: t('nexeno_freeup', 'Active accounts'), bytes: Number(overview.activeStorageBytes) || 0, colour: ACCENT },
		{ label: t('nexeno_freeup', 'Inactive accounts'), bytes: Number(overview.inactiveStorageBytes) || 0, colour: WARN },
		{ label: t('nexeno_freeup', 'Disabled accounts'), bytes: (Number(overview.reclaimedStorageBytes) || 0) + (Number(overview.disabledStorageBytes) || 0), colour: SLATE },
		{ label: t('nexeno_freeup', 'Available storage'), bytes: Number(overview.storageFreeBytes) || 0, colour: rgb(0.92, 0.93, 0.95) },
	]
	const sum = parts.reduce((a, p) => a + p.bytes, 0)
	s.ensure(60)
	s.text(t('nexeno_freeup', 'Where Your Storage Goes'), MARGIN, s.top + 8, { size: 9, bold: true, color: MUTED })
	s.top += 18
	if (sum > 0) {
		let x = MARGIN
		parts.forEach((p) => {
			const w = p.bytes > 0 ? Math.max(3, (CONTENT_W * p.bytes) / sum) : 0
			if (w > 0) {
				s.rect(x, s.top, w, 12, { fill: p.colour })
			}
			x += w
		})
		let lx = MARGIN
		parts.forEach((p) => {
			s.rect(lx, s.top + 24, 8, 8, { fill: p.colour })
			const label = `${p.label} · ${fmtBytes(p.bytes)}`
			s.text(label, lx + 12, s.top + 31.5, { size: 8.5, color: MUTED })
			lx += 12 + s.width(label, 8.5) + 18
		})
	}
	s.top += 56

	section(t('nexeno_freeup', 'Duplicate files'), lastScan ? t('nexeno_freeup', 'Last scan {date}', { date: fmtDate(lastScan) }) : '', 58)
	const dupCards = [
		[t('nexeno_freeup', 'Files with Duplicates'), num(overview.duplicateCount)],
		[t('nexeno_freeup', 'Duplicate Copies'), num(overview.extraCopiesCount)],
		[t('nexeno_freeup', 'Potential storage savings'), fmtBytes(overview.duplicateWastedBytes)],
	]
	const dupW = (CONTENT_W - gap * 2) / 3
	dupCards.forEach(([label, value], i) => {
		const x = MARGIN + i * (dupW + gap)
		s.round(x, s.top, dupW, 58, 7, { fill: SOFT })
		s.text(label, x + 14, s.top + 20, { size: 8.5, color: MUTED })
		s.text(value, x + 14, s.top + 44, { size: 18, bold: true })
	})
	s.top += 58

	const pages = doc.getPages()
	pages.forEach((page, i) => {
		const base = PAGE[1] - MARGIN + 6
		page.drawLine({ start: { x: MARGIN, y: PAGE[1] - base + 14 }, end: { x: PAGE[0] - MARGIN, y: PAGE[1] - base + 14 }, thickness: 0.5, color: LINE })
		s.text('Nexeno FreeUp', MARGIN, base, { size: 8, bold: true, color: MUTED, page })
		s.text(t('nexeno_freeup', 'Totals only. This report contains no personal data.'), PAGE[0] / 2, base, { size: 8, color: MUTED, align: 'center', page })
		s.text(t('nexeno_freeup', 'Page {page} of {pages}', { page: i + 1, pages: pages.length }), PAGE[0] - MARGIN, base, { size: 8, color: MUTED, align: 'right', page })
	})

	return doc.save()
}

export function downloadPdf(bytes, filename) {
	const blob = new Blob([bytes], { type: 'application/pdf' })
	const url = window.URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.setAttribute('download', filename)
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	window.URL.revokeObjectURL(url)
}

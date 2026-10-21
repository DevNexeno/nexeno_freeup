/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCanonicalLocale, translatePlural as n, translate as t } from '@nextcloud/l10n'
import { reactive } from 'vue'

// Single place for date, time, number and size formatting; pages must not call toLocaleString / Intl directly.
// The locale follows Nextcloud unless an admin overrides it (setFormatConfig).

const DATE_STYLES = ['short', 'medium', 'long', 'full']
const CLOCKS = ['locale', '24', '12']

// reactive: displayed values update as soon as the admin saves
const config = reactive({ appLocale: null, dateStyle: 'medium', clock: 'locale' })

/**
 * @param {{appLocale?: ?string, dateStyle?: string, clock?: string}} format
 */
export function setFormatConfig(format) {
	config.appLocale = format?.appLocale || null
	config.dateStyle = DATE_STYLES.includes(format?.dateStyle) ? format.dateStyle : 'medium'
	config.clock = CLOCKS.includes(format?.clock) ? format.clock : 'locale'
}

// Nextcloud `de_DE` / `sr@latin` -> Intl `de-DE` / `sr-Latn`; unknown codes fall back to English
export function toIntlLocale(code) {
	const tag = String(code || '').replace('@latin', '-Latn').replace(/_/g, '-')
	try {
		return Intl.getCanonicalLocales(tag)[0] || 'en'
	} catch {
		return 'en'
	}
}

function timeOptions(clock) {
	if (clock === '24') {
		return { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }
	}
	if (clock === '12') {
		return { hour: '2-digit', minute: '2-digit', hour12: true }
	}
	return { timeStyle: 'short' }
}

const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB']

export function currentLocale() {
	if (config.appLocale) {
		return toIntlLocale(config.appLocale)
	}
	try {
		return getCanonicalLocale() || 'en'
	} catch {
		return 'en'
	}
}

// Intl objects are slow to create and lists render thousands of cells
const cache = new Map()

function intl(Ctor, options) {
	const locale = currentLocale()
	const key = Ctor.name + '|' + locale + '|' + JSON.stringify(options)
	let formatter = cache.get(key)
	if (!formatter) {
		try {
			formatter = new Ctor(locale, options)
		} catch {
			formatter = new Ctor('en', options)
		}
		cache.set(key, formatter)
	}
	return formatter
}

/**
 * Any Date, Unix timestamp (seconds or milliseconds), ISO string or SQL UTC string as a valid Date, else null.
 *
 * @param {Date|number|string} input
 * @return {Date|null}
 */
export function parseDate(input) {
	if (!input) {
		return null
	}
	if (input instanceof Date) {
		return isNaN(input.getTime()) ? null : input
	}
	if (typeof input === 'number') {
		const d = new Date(input < 10000000000 ? input * 1000 : input)
		return isNaN(d.getTime()) ? null : d
	}
	if (typeof input === 'string') {
		const trimmed = input.trim()
		if (!trimmed) {
			return null
		}
		const num = Number(trimmed)
		if (!isNaN(num) && num > 0) {
			const d = new Date(num < 10000000000 ? num * 1000 : num)
			return isNaN(d.getTime()) ? null : d
		}
		// SQL datetimes are UTC
		const iso = trimmed.replace(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})$/, '$1T$2Z')
		const d = new Date(iso)
		return isNaN(d.getTime()) ? null : d
	}
	return null
}

export function fmtNumber(value) {
	return intl(Intl.NumberFormat, {}).format(Number(value) || 0)
}

// chart axes: 18420 -> "18K", small numbers stay whole
export function fmtCompact(value) {
	const v = Number(value) || 0
	return v >= 10000
		? intl(Intl.NumberFormat, { notation: 'compact', maximumFractionDigits: 1 }).format(v)
		: fmtNumber(v)
}

export function fmtPercent(fraction, decimals = 1) {
	return intl(Intl.NumberFormat, { style: 'percent', maximumFractionDigits: decimals }).format(Number(fraction) || 0)
}

export function fmtBytes(bytes, decimals = 2) {
	const value = Number(bytes)
	if (!value || value < 0) {
		return fmtNumber(0) + ' B'
	}
	const i = Math.min(Math.floor(Math.log(value) / Math.log(1024)), UNITS.length - 1)
	const number = intl(Intl.NumberFormat, { maximumFractionDigits: i === 0 ? 0 : decimals }).format(value / 1024 ** i)
	return number + ' ' + UNITS[i]
}

function withDate(input, options, empty = '--') {
	const d = parseDate(input)
	return d ? intl(Intl.DateTimeFormat, options).format(d) : (input ? String(input) : empty)
}

export function fmtDate(input) {
	return withDate(input, { dateStyle: config.dateStyle })
}

export function fmtTime(input) {
	return withDate(input, timeOptions(config.clock), '')
}

export function fmtDateTime(input) {
	const d = parseDate(input)
	return d ? `${fmtDate(d)}, ${fmtTime(d)}` : (input ? String(input) : '--')
}

export function fmtWeekdayDate(input) {
	return withDate(input, { dateStyle: 'full' }, '')
}

export function fmtMonthYear(input, month = 'long') {
	return withDate(input, { month, year: 'numeric' }, '')
}

export function fmtMonth(input, month = 'long') {
	return withDate(input, { month }, '')
}

export function fmtDayMonth(input) {
	return withDate(input, { day: 'numeric', month: 'short' }, '')
}

// Audit text stores English sizes and dates ("Uses 2.18 GB", "Planned for 17 Oct 2026"); re-render them in the viewer's locale
export function localizeText(text) {
	if (!text) {
		return text
	}
	return String(text)
		.replace(/\b(\d{1,3}(?:\.\d+)?|\d+(?:\.\d+)?) (B|KB|MB|GB|TB|PB)\b/g, (m, num, unit) => {
			const digits = (num.split('.')[1] || '').length
			return intl(Intl.NumberFormat, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(num)) + ' ' + unit
		})
		.replace(/\b(\d{1,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) (\d{4})\b/g, (m, day, mon, year) => {
			const d = new Date(`${mon} ${day}, ${year} 12:00:00`)
			return isNaN(d.getTime()) ? m : fmtDate(d)
		})
}

/**
 * Formats for an explicit locale without touching app state (Settings live preview).
 *
 * @param {string} localeCode Nextcloud locale code such as `de_DE`
 * @param {{dateStyle?: string, clock?: string}} [choices]
 * @param {Date} [when]
 */
export function previewFormats(localeCode, { dateStyle = 'medium', clock = 'locale' } = {}, when = new Date(2026, 9, 12, 14, 30)) {
	const locale = toIntlLocale(localeCode)
	const num = (v, o = {}) => new Intl.NumberFormat(locale, o).format(v)
	return {
		date: new Intl.DateTimeFormat(locale, { dateStyle }).format(when),
		time: new Intl.DateTimeFormat(locale, timeOptions(clock)).format(when),
		number: num(18420),
		storage: num(1.24, { maximumFractionDigits: 2 }) + ' TB',
		percent: num(0.212, { style: 'percent', maximumFractionDigits: 1 }),
	}
}

export function dateStyleExamples(localeCode, when = new Date(2026, 9, 12, 14, 30)) {
	const locale = toIntlLocale(localeCode)
	return Object.fromEntries(DATE_STYLES.map((style) => [style, new Intl.DateTimeFormat(locale, { dateStyle: style }).format(when)]))
}

/**
 * @param {Date|number|string} input
 * @param {Date} [now]
 */
export function fmtRelativeDays(input, now = new Date()) {
	const d = parseDate(input)
	if (!d) {
		return ''
	}
	const midnight = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime()
	const days = Math.round((midnight(now) - midnight(d)) / 86400000)
	if (days <= 0) {
		return t('nexeno_freeup', 'Today')
	}
	if (days === 1) {
		return t('nexeno_freeup', 'Yesterday')
	}
	if (days < 30) {
		return n('nexeno_freeup', '%n day ago', '%n days ago', days)
	}
	if (days < 365) {
		return n('nexeno_freeup', '%n month ago', '%n months ago', Math.max(1, Math.round(days / 30.416)))
	}
	return n('nexeno_freeup', '%n year ago', '%n years ago', Math.max(1, Math.round(days / 365.25)))
}

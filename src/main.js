/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { imagePath } from '@nextcloud/router'
import { createApp } from 'vue'
import App from './App.vue'

import './assets/css/nexeno_freeup.scss'

function getContrastColor(bgColor) {
	if (!bgColor) {
		return '#ffffff'
	}
	let hex = bgColor.trim()
	if (hex.startsWith('rgb')) {
		const match = hex.match(/\d+/g)
		if (match && match.length >= 3) {
			const r = parseInt(match[0], 10)
			const g = parseInt(match[1], 10)
			const b = parseInt(match[2], 10)
			const yiq = (r * 299 + g * 587 + b * 114) / 1000
			return yiq >= 160 ? '#1e293b' : '#ffffff'
		}
	}
	if (hex.startsWith('#')) {
		hex = hex.replace('#', '')
		if (hex.length === 3) {
			hex = hex.split('').map((c) => c + c).join('')
		}
		if (hex.length === 6) {
			const r = parseInt(hex.substring(0, 2), 16)
			const g = parseInt(hex.substring(2, 4), 16)
			const b = parseInt(hex.substring(4, 6), 16)
			const yiq = (r * 299 + g * 587 + b * 114) / 1000
			return yiq >= 160 ? '#1e293b' : '#ffffff'
		}
	}
	return '#ffffff'
}

function resolveCurrentTheme() {
	let color = ''
	let isDark = false

	if (
		document.body?.hasAttribute('data-theme-dark')
		|| document.documentElement?.hasAttribute('data-theme-dark')
		|| document.body?.classList?.contains('theme--dark')
		|| document.documentElement?.classList?.contains('theme--dark')
	) {
		isDark = true
	}

	// theme colour sources, most specific first
	const themingInput = document.getElementById('initial-state-theming-data')
	if (themingInput && themingInput.value) {
		try {
			const data = JSON.parse(atob(themingInput.value))
			if (data.primaryColor && data.primaryColor !== 'transparent') {
				color = data.primaryColor
			}
			if (Array.isArray(data.enabledThemes) && data.enabledThemes.includes('dark')) {
				isDark = true
			}
		} catch {
			// malformed theming data
		}
	}

	if (!color) {
		const rootStyles = window.getComputedStyle(document.documentElement)
		const bodyStyles = window.getComputedStyle(document.body)
		color = (
			rootStyles.getPropertyValue('--color-primary-element')
			|| bodyStyles.getPropertyValue('--color-primary-element')
			|| rootStyles.getPropertyValue('--color-primary')
			|| bodyStyles.getPropertyValue('--color-primary')
		).trim()
	}

	if (!color) {
		const metaTheme = document.querySelector('meta[name="theme-color"]')
		if (metaTheme && metaTheme.content) {
			color = metaTheme.content.trim()
		}
	}

	if (!color) {
		const initialData = document.getElementById('nexeno_freeup-initial-data')
		color = initialData?.getAttribute('data-theme-color')
	}

	if (!color && window.OC?.theme?.color) {
		color = window.OC.theme.color
	}

	if (!color || color === 'transparent') {
		color = '#0082c9'
	}

	const fgColor = getContrastColor(color)

	return { color, fgColor, isDark }
}

function buildFaviconDataUri(bgColor, fgColor) {
	const bg = encodeURIComponent(bgColor)
	const fg = encodeURIComponent(fgColor)
	return `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32"><rect width="32" height="32" rx="6" ry="6" fill="${bg}"/><g transform="translate(4,4)"><path d="M4 3h16c1.1 0 2 .9 2 2v14c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2zm0 11v5h16v-5H4zm14 2.5c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1 1-.45 1-1zm-3.5 0c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1 1-.45 1-1zM12 4.8L7.2 9.6h3.3v3.2h3V9.6h3.3L12 4.8z" fill="${fg}"/></g></svg>`
}

let lastAppliedColor = null

function applyAppBranding() {
	const { color, fgColor } = resolveCurrentTheme()

	if (lastAppliedColor === color) {
		return
	}
	lastAppliedColor = color

	const freshTimestamp = Date.now()
	const faviconDataUri = buildFaviconDataUri(color, fgColor)

	const themingFaviconUrl = `/apps/theming/favicon/nexeno_freeup?v=${freshTimestamp}`
	const appIcon = typeof imagePath === 'function' ? imagePath('nexeno_freeup', 'app.svg') : '/custom_apps/nexeno_freeup/img/app.svg'
	const appIconUrl = appIcon + (appIcon.includes('?') ? '&' : '?') + `v=${freshTimestamp}`

	try {
		const existingFavicons = document.querySelectorAll("link[rel*='icon'], link[rel='apple-touch-icon']")
		existingFavicons.forEach((el) => el.remove())

		const linkData = document.createElement('link')
		linkData.rel = 'icon'
		linkData.type = 'image/svg+xml'
		linkData.href = faviconDataUri
		document.head.appendChild(linkData)

		// fallback for browsers without SVG favicon support
		const linkTheming = document.createElement('link')
		linkTheming.rel = 'alternate icon'
		linkTheming.type = 'image/x-icon'
		linkTheming.href = themingFaviconUrl
		document.head.appendChild(linkTheming)

		const linkTouch = document.createElement('link')
		linkTouch.rel = 'apple-touch-icon'
		linkTouch.href = themingFaviconUrl
		document.head.appendChild(linkTouch)
	} catch {
		// non-critical
	}

	const refreshHeaderIcons = () => {
		try {
			const selectors = [
				'header a[href*="nexeno_freeup"]',
				'#header-start__appmenu a[href*="nexeno_freeup"]',
				'.app-menu a[href*="nexeno_freeup"]',
				'#appmenu a[data-app-id="nexeno_freeup"]',
			]
			const entries = document.querySelectorAll(selectors.join(', '))
			entries.forEach((entry) => {
				const targetNodes = [entry, ...entry.querySelectorAll('*')]
				targetNodes.forEach((node) => {
					const style = window.getComputedStyle(node)
					if (style.backgroundImage && style.backgroundImage.includes('nexeno_freeup')) {
						node.style.backgroundImage = `url("${appIconUrl}")`
					}
					if (style.maskImage && style.maskImage.includes('nexeno_freeup')) {
						node.style.maskImage = `url("${appIconUrl}")`
					}
					if (style.webkitMaskImage && style.webkitMaskImage.includes('nexeno_freeup')) {
						node.style.webkitMaskImage = `url("${appIconUrl}")`
					}
				})
			})
		} catch {
			// non-critical
		}
	}

	refreshHeaderIcons()
}

function setupThemeObserver() {
	try {
		const observer = new MutationObserver(() => {
			lastAppliedColor = null
			applyAppBranding()
		})

		if (document.documentElement) {
			observer.observe(document.documentElement, {
				attributes: true,
				attributeFilter: ['class', 'style', 'data-theme-dark', 'data-theme-light'],
			})
		}

		if (document.body) {
			observer.observe(document.body, {
				attributes: true,
				attributeFilter: ['class', 'style', 'data-theme-dark', 'data-theme-light'],
			})
		}

		if (window.matchMedia) {
			window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
				lastAppliedColor = null
				applyAppBranding()
			})
		}
	} catch {
		// non-critical
	}
}

// Nextcloud 32 changed --color-success/-warning/-error/-info from solid colours into light backgrounds,
// which @nextcloud/vue 9 expects; the stylesheet corrects its components on older servers.
function markOldStatusColours() {
	const major = parseInt(window.OC?.config?.version ?? window.oc_config?.version ?? '', 10)
	if (major > 0 && major < 32) {
		document.body.classList.add('nexeno-freeup-old-status-colours')
	}
}

function mountApp() {
	const el = document.getElementById('nexeno_freeup-app')
	if (!el || el.__vue_app__) {
		return
	}

	markOldStatusColours()
	applyAppBranding()
	setupThemeObserver()

	// theme CSS may be inserted after mount
	setTimeout(() => {
		lastAppliedColor = null
		applyAppBranding()
	}, 150)
	setTimeout(() => {
		lastAppliedColor = null
		applyAppBranding()
	}, 500)
	setTimeout(() => {
		lastAppliedColor = null
		applyAppBranding()
	}, 1500)

	const app = createApp(App)
	app.mount(el)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mountApp)
} else {
	mountApp()
}

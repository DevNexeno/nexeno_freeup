/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { recommended } from '@nextcloud/eslint-config'

export default [
    {
        ignores: ['js/**', 'css/**', 'l10n/**', 'vendor/**', 'vendor-bin/**', 'build/**', 'tests/**', 'playwright.config.js'],
    },
    ...recommended,
    {
        rules: {
            'jsdoc/require-jsdoc': 'off',
            'jsdoc/require-param-description': 'off',
            'jsdoc/require-param-type': 'off',
            'jsdoc/check-param-names': 'off',
            'jsdoc/no-undefined-types': 'off',
            'jsdoc/require-property-description': 'off',
            'vue/multi-word-component-names': 'off',
        },
    },
]

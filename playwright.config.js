/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright end-to-end tests for Nexeno FreeUp.
 * They run against a Nextcloud server with the app enabled: NC_BASE_URL (default http://localhost:8080),
 * NEXTCLOUD_ADMIN_USER (default admin) and NEXTCLOUD_ADMIN_PASSWORD.
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 45 * 1000,
    expect: {
        timeout: 10000,
    },
    fullyParallel: false,
    workers: 1, // Run sequentially to avoid Nextcloud session conflicts
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }]
    ],
    use: {
        baseURL: process.env.NC_BASE_URL || 'http://localhost:8080',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});

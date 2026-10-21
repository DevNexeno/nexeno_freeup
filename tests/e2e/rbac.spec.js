/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '@playwright/test';

async function login(page, username, password) {
  await page.goto('/index.php/login');
  await page.waitForSelector('#user', { timeout: 10000 });
  await page.fill('#user', username);
  await page.fill('#password', password);
  const submitBtn = page.locator('#submit-form');
  if (await submitBtn.count() > 0) {
    await submitBtn.click();
  } else {
    await page.keyboard.press('Enter');
  }
  await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 });
}

test.describe('Nexeno FreeUp 3-Tier RBAC Security', () => {

  test.beforeEach(async ({ context }) => {
    await context.clearCookies();
  });

  test('Tier 1: Admin has full access to all tabs, Settings, and Audit Trail', async ({ page }) => {
    await login(page, process.env.NEXTCLOUD_ADMIN_USER || 'admin', process.env.NEXTCLOUD_ADMIN_PASSWORD);

    await page.goto('/index.php/apps/nexeno_freeup/');
    await page.waitForSelector('#nexeno_freeup-app', { state: 'attached', timeout: 15000 });

    const appNav = page.locator('nav[aria-label="Nexeno FreeUp navigation"]');

    await expect(appNav.locator('text=Overview')).toBeVisible();
    await expect(appNav.locator('text=Accounts & Storage')).toBeVisible();
    await expect(appNav.locator('text=Approval Queue')).toBeVisible();
    await expect(appNav.locator('text=Duplicate Files')).toBeVisible();
    await expect(appNav.locator('text=Licences in Use')).toBeVisible();

    await expect(appNav.locator('text=Audit Trail')).toBeVisible();
    await expect(appNav.locator('text=Settings')).toBeVisible();

    await page.goto('/index.php/apps/nexeno_freeup/audit');
    await page.waitForSelector('#nexeno_freeup-app', { state: 'attached', timeout: 15000 });
    await expect(page.locator('text=Governance & Compliance Audit Trail').first()).toBeVisible();
  });

  test('Tier 2: Governance Approver (priya) is blocked from Audit and Settings', async ({ page }) => {
    await login(page, 'priya', 'TestPriyaPass!');

    await page.goto('/index.php/apps/nexeno_freeup/');
    await page.waitForSelector('#nexeno_freeup-app', { state: 'attached', timeout: 15000 });

    const appNav = page.locator('nav[aria-label="Nexeno FreeUp navigation"]');

    await expect(appNav.locator('text=Overview')).toBeVisible();
    await expect(appNav.locator('text=Accounts & Storage')).toBeVisible();
    await expect(appNav.locator('text=Approval Queue')).toBeVisible();

    await expect(appNav.locator('text=Audit Trail')).toHaveCount(0);
    await expect(appNav.locator('text=Settings')).toHaveCount(0);

    await page.goto('/index.php/apps/nexeno_freeup/audit');
    await expect(page.locator('text=Access forbidden')).toBeVisible();
    await expect(page.locator('text=Access to Audit Trail is restricted to system administrators.')).toBeVisible();

    await page.goto('/index.php/apps/nexeno_freeup/settings');
    await expect(page.locator('text=Access forbidden')).toBeVisible();
    await expect(page.locator('text=Access to Settings is restricted to system administrators.')).toBeVisible();
  });

  test('Tier 3: Regular user (user1) is completely blocked with native 403 Forbidden', async ({ page }) => {
    await login(page, 'user1', 'TestUser1Pass!');

    await page.goto('/index.php/apps/nexeno_freeup/');

    await expect(page.locator('text=Access forbidden')).toBeVisible();
    await expect(page.locator('text=Access to Nexeno FreeUp is restricted to system administrators and designated governance approvers.')).toBeVisible();
  });

});

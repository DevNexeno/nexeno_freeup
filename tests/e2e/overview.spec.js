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

test('Verify Overview Monthly Licences table on initial load and after reload', async ({ page, context }) => {
  await context.clearCookies();
  await login(page, process.env.NEXTCLOUD_ADMIN_USER || 'admin', process.env.NEXTCLOUD_ADMIN_PASSWORD);
  await page.goto('/index.php/apps/nexeno_freeup/');
  await page.waitForSelector('#nexeno_freeup-app', { state: 'attached', timeout: 15000 });

  const emptyRow = page.locator('text=No historical licence data recorded');
  await expect(emptyRow).not.toBeVisible();

  const dataRows = page.locator('.freeup-card table tbody tr');
  const count1 = await dataRows.count();
  console.log('Row count on first load:', count1);
  expect(count1).toBeGreaterThanOrEqual(1);

  console.log('Testing page reload...');
  await page.reload();
  await page.waitForSelector('#nexeno_freeup-app', { state: 'attached', timeout: 15000 });

  await expect(emptyRow).not.toBeVisible();
  const count2 = await dataRows.count();
  console.log('Row count after reload:', count2);
  expect(count2).toBeGreaterThanOrEqual(1);
});

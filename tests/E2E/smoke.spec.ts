import { test, expect } from '@playwright/test';

/**
 * Phase 0 E2E smoke — wp-env is up and the admin login page is reachable.
 * Phase 1 replaces this with a wizard walkthrough.
 */
test('wp-admin login page loads', async ({ page }) => {
	await page.goto('/wp-login.php');
	await expect(page.locator('#loginform')).toBeVisible();
});

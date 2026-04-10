import { test, expect } from '@playwright/test';
import { wpLogin, wpAdminPage } from './helpers';

test.describe('Smoke tests', () => {
    test('wp-admin login page loads', async ({ page }) => {
        await page.goto('/wp-login.php');
        await expect(page.locator('#loginform')).toBeVisible();
    });

    test('can log in to wp-admin', async ({ page }) => {
        await wpLogin(page);
        await expect(page.locator('#wpadminbar')).toBeVisible();
    });

    test('Elementor Forge plugin is active', async ({ page }) => {
        await wpLogin(page);
        await wpAdminPage(page, 'plugins.php');
        const forgeRow = page.locator('[data-slug="elementor-forge"]');
        await expect(forgeRow).toBeVisible();
        await expect(forgeRow.locator('.deactivate')).toBeVisible();
    });
});

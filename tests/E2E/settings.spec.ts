import { test, expect } from '@playwright/test';
import { wpLogin, wpAdminPage } from './helpers';

test.describe('Settings page', () => {
    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
    });

    test('settings page loads without errors', async ({ page }) => {
        await wpAdminPage(page, 'admin.php?page=elementor-forge');
        await expect(page.locator('h1')).toContainText(/Elementor Forge/i);
    });

    test('safety mode selector is visible', async ({ page }) => {
        await wpAdminPage(page, 'admin.php?page=elementor-forge');
        // Look for the safety mode setting
        const safetySection = page.locator('text=Safety Mode').first();
        await expect(safetySection).toBeVisible();
    });

    test('MCP server toggle is visible', async ({ page }) => {
        await wpAdminPage(page, 'admin.php?page=elementor-forge');
        const mcpSection = page.locator('text=MCP Server').first();
        await expect(mcpSection).toBeVisible();
    });
});

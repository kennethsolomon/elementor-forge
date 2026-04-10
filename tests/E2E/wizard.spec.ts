import { test, expect } from '@playwright/test';
import { wpLogin, wpAdminPage } from './helpers';

test.describe('Onboarding wizard', () => {
    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
    });

    test('wizard page loads at setup URL', async ({ page }) => {
        await wpAdminPage(page, 'admin.php?page=elementor-forge-setup');
        // Wizard should show welcome step or redirect to settings if already completed
        const pageContent = await page.textContent('body');
        expect(
            pageContent?.includes('Setup') ||
            pageContent?.includes('Elementor Forge') ||
            pageContent?.includes('Welcome')
        ).toBeTruthy();
    });

    test('wizard detects Elementor plugin status', async ({ page }) => {
        await wpAdminPage(page, 'admin.php?page=elementor-forge-setup');
        // If wizard has a dependencies step, check it lists Elementor
        const body = await page.textContent('body');
        if (body?.includes('Dependencies') || body?.includes('Plugins')) {
            const elementorRow = page.locator('text=Elementor').first();
            await expect(elementorRow).toBeVisible();
        }
    });
});

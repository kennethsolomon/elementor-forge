import { test, expect } from '@playwright/test';
import { wpLogin, wpAdminPage } from './helpers';

test.describe('Custom Post Types', () => {
    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
    });

    test('Locations CPT is registered and accessible', async ({ page }) => {
        await wpAdminPage(page, 'edit.php?post_type=ef_location');
        await expect(page.locator('h1')).toContainText(/Location/i);
    });

    test('Services CPT is registered and accessible', async ({ page }) => {
        await wpAdminPage(page, 'edit.php?post_type=ef_service');
        await expect(page.locator('h1')).toContainText(/Service/i);
    });

    test('can create a new Location post', async ({ page }) => {
        await wpAdminPage(page, 'post-new.php?post_type=ef_location');
        const titleField = page.locator('#title, [name="post_title"], .editor-post-title__input');
        await expect(titleField.first()).toBeVisible({ timeout: 10000 });
    });

    test('can create a new Service post', async ({ page }) => {
        await wpAdminPage(page, 'post-new.php?post_type=ef_service');
        const titleField = page.locator('#title, [name="post_title"], .editor-post-title__input');
        await expect(titleField.first()).toBeVisible({ timeout: 10000 });
    });
});

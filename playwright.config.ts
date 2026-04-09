import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for Elementor Forge admin UI end-to-end tests.
 * Runs against wp-env (wp-env start); login via UI fixture before each suite.
 */
export default defineConfig({
	testDir: './tests/E2E',
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: [['list'], ['html', { open: 'never', outputFolder: 'tests/E2E/_report' }]],
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8889',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'admin',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});

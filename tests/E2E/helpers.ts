import { Page, expect } from '@playwright/test';

/**
 * WordPress admin login helper. Uses the default wp-env credentials.
 */
export async function wpLogin(page: Page): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
}

/**
 * Navigate to a WordPress admin page.
 */
export async function wpAdminPage(page: Page, path: string): Promise<void> {
    await page.goto(`/wp-admin/${path}`);
}

/**
 * Call an MCP tool via the WordPress REST API.
 */
export async function callMcpTool(
    page: Page,
    toolName: string,
    input: Record<string, unknown>
): Promise<Record<string, unknown>> {
    const response = await page.evaluate(
        async ({ toolName, input }) => {
            const res = await fetch('/wp-json/elementor-forge/v1/mcp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': (window as any).wpApiSettings?.nonce || '',
                },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    method: 'tools/call',
                    params: { name: toolName, arguments: input },
                    id: 1,
                }),
            });
            return res.json();
        },
        { toolName, input }
    );
    return response as Record<string, unknown>;
}

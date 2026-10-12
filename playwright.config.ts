import { defineConfig, devices } from '@playwright/test';

// Browser end-to-end tests for the storefront.
// Run against a running app: E2E_BASE_URL=http://127.0.0.1:8000 npx playwright test
export default defineConfig({
    testDir: 'tests/e2e/storefront',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
        { name: 'desktop-dark', use: { ...devices['Desktop Chrome'], colorScheme: 'dark' } },
    ],
});

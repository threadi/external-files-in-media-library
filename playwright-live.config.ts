/**
 * Playwright live tests against your development website with real accounts, see docs/live-tests.md.
 *
 * Run them with "composer test-live-e2e" (or "npx playwright test -c playwright-live.config.ts").
 */
// @ts-ignore
import { defineConfig } from '@playwright/test';
import { env } from './tests/Live/Playwright/env';

export default defineConfig({
    testDir: './tests/Live/Playwright',
    globalSetup: './tests/Live/Playwright/global-setup.ts',
    outputDir: './test-results/live',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report/live' }]],
    timeout: 180_000,
    expect: {
        timeout: 30_000,
    },
    use: {
        baseURL: env.siteUrl || undefined,
        // development websites often use self-signed certificates.
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        // e.g. "chrome" to use the installed Google Chrome instead of the browsers of Playwright.
        channel: env.get('EFML_LIVE_BROWSER_CHANNEL') || undefined,
    },
});

// @ts-ignore
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Playwright',
    fullyParallel: false,
    // @ts-ignore
    forbidOnly: !!process.env.CI,
    // @ts-ignore
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: 'html',
    timeout: 120_000,
    expect: {
        timeout: 30_000,
    },
    use: {
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
    },
});

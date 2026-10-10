// @ts-ignore
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Playwright',
    fullyParallel: false,
    // @ts-ignore
    forbidOnly: !!process.env.CI,
    // @ts-ignore
    retries: process.env.CI ? 1 : 0,
    // @ts-ignore
    maxFailures: process.env.CI ? 5 : 0,
    workers: 1,
    // @ts-ignore
    reporter: process.env.CI
        ? [['github'], ['list'], ['html', { open: 'never' }]]
        : 'html',
    timeout: 60_000,
    expect: {
        timeout: 30_000,
    },
    use: {
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
    },
});

import { test, expect } from './fixtures';

/**
 * Tests for the log of this plugin.
 */
test.describe('Logs', () => {
    // @ts-ignore
    test('Log entries are shown escaped', async ({ page, wp }) => {
        // create a log entry with HTML, which must be shown escaped.
        await wp.php(`
            \\ExternalFilesInMediaLibrary\\Plugin\\Log::get_instance()->create( 'Playwright test entry <script>window.efmlXss = 1;</script>', 'https://example.com/playwright.pdf', 'error' );
        `);

        await page.goto(wp.adminUrl('options-general.php?page=eml_settings&tab=eml_logs'));
        await expect(page.locator('body')).toContainText('Playwright test entry');
        // the URL is shown shortened.
        await expect(page.locator('body')).toContainText('example.com/playwright.pdf');

        // the script in the log entry must not be executed.
        expect(await page.evaluate(() => (window as unknown as { efmlXss?: number }).efmlXss)).toBeUndefined();
    });
});

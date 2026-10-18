import { test, expect } from './fixtures';

test.describe('Settings', () => {
    // @ts-ignore
    test('Loading settings page without error', async ({ page, wp }) => {
        await page.goto(wp.adminUrl('options-general.php?page=eml_settings'));
        await expect(page.getByRole('heading', { level: 1 })).toContainText('External files in Media Library');
        await expect(page.locator('body')).not.toContainText(/Fatal error|Warning:|Notice:|Deprecated:/);
    });

    // @ts-ignore
    test('Save timeout value in advanced tab', async ({ page, wp }) => {
        // remember the actual value to restore it after the test.
        const originalValue = await wp.getOption('eml_timeout');

        try {
            await page.goto(wp.adminUrl('options-general.php?page=eml_settings'));

            // switch to the tab via its link (and not a hardcoded URL), if the URL changes between versions.
            await page.getByRole('link', { name: 'Advanced' }).click();

            // the timeout is located in the sub-tab "Files" of the advanced settings.
            await page.getByRole('link', { name: 'Files', exact: true }).click();

            const timeoutInput = page.getByLabel('Max. Timeout in seconds');
            await timeoutInput.fill('45');
            await page.getByRole('button', { name: /save/i }).click();
            await expect(page.getByText('Settings saved')).toBeVisible();

            // the value must be saved: check it after reload and in the database.
            await page.reload();
            await expect(page.getByLabel('Max. Timeout in seconds')).toHaveValue('45');
            expect(String(await wp.getOption('eml_timeout'))).toBe('45');
        } finally {
            await wp.updateOption('eml_timeout', originalValue);
        }
    });
});

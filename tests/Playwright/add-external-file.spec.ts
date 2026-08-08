import { test, expect } from './fixtures';

const TEST_FILE_URL = '${cli.serverUrl}/tests/Data/example.pdf';

test('Add an external file via dialog', async ({ page, cli }) => {
    await page.goto(`${cli.serverUrl}/wp-admin/media-new.php`);

    // open the dialog.
    await page
        .locator('#plupload-upload-ui')
        .locator('a.efml-import-dialog')
        .click();

    // wait to load.
    const urlsField = page.locator('#urls');
    await urlsField.waitFor();

    // add the URL for the test file.
    await urlsField.fill(TEST_FILE_URL);

    // set checkbox.
    await page.getByLabel('I confirm that I will respect the copyrights of these external files.').check();

    // add the file.
    await page.getByRole('button', { name: 'Add URLs' }).click();

    // wait for success message.
    await expect(page.getByText(/Import has been executed/)).toBeVisible({ timeout: 60_000 });
});

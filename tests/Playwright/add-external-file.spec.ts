import { test, expect } from './fixtures';

test.describe('Add external files', () => {
    // remove all files added by these tests.
    // @ts-ignore
    test.afterEach(async ({ wp }) => {
        await wp.deleteAllAttachments();
    });

    // @ts-ignore
    test('Add an external file via dialog', async ({ page, wp }) => {
        // use the test file of this plugin, which is reachable via the Playground.
        const testFileUrl = wp.pluginFileUrl('tests/Data/example.pdf');
        const attachmentCount = await wp.getAttachmentCount();

        await page.goto(wp.adminUrl('media-new.php'));

        // open the dialog.
        await page.locator('#plupload-upload-ui').locator('a.efml-import-dialog').click();

        // wait to load.
        const urlsField = page.locator('#urls');
        await urlsField.waitFor();

        // add the URL for the test file.
        await urlsField.fill(testFileUrl);

        // set checkbox.
        await page.getByLabel('I confirm that I will respect the copyrights of these external files.').check();

        // add the file.
        await page.getByRole('button', { name: 'Add URLs' }).click();

        // wait for the message, which is shown after the import.
        await expect(page.getByText(/Import has been executed/)).toBeVisible({ timeout: 60_000 });

        // the file must be in the media library now (the message is also shown, if the import failed).
        expect(await wp.getAttachmentCount()).toBe(attachmentCount + 1);

        // and must be visible in the list view of the media library.
        await page.goto(wp.adminUrl('upload.php?mode=list'));
        await expect(page.locator('#the-list')).toContainText('example');
    });

    // @ts-ignore
    test('A not reachable URL is not added', async ({ page, wp }) => {
        const attachmentCount = await wp.getAttachmentCount();

        await page.goto(wp.adminUrl('media-new.php'));
        await page.locator('#plupload-upload-ui').locator('a.efml-import-dialog').click();
        const urlsField = page.locator('#urls');
        await urlsField.waitFor();
        await urlsField.fill(wp.pluginFileUrl('tests/Data/not-existing-file.pdf'));
        await page.getByLabel('I confirm that I will respect the copyrights of these external files.').check();
        await page.getByRole('button', { name: 'Add URLs' }).click();

        // wait for the dialog after the import.
        await expect(page.getByText(/Import has been executed/)).toBeVisible({ timeout: 60_000 });

        // no file must have been added.
        expect(await wp.getAttachmentCount()).toBe(attachmentCount);
    });

    // @ts-ignore
    test('The last import could be reverted', async ({ page, wp }) => {
        const originalValue = await wp.getOption('eml_revert');
        await wp.updateOption('eml_revert', 1);

        try {
            const attachmentCount = await wp.getAttachmentCount();

            // import the test file.
            await page.goto(wp.adminUrl('media-new.php'));
            await page.locator('#plupload-upload-ui').locator('a.efml-import-dialog').click();
            const urlsField = page.locator('#urls');
            await urlsField.waitFor();
            await urlsField.fill(wp.pluginFileUrl('tests/Data/example.pdf'));
            await page.getByLabel('I confirm that I will respect the copyrights of these external files.').check();
            await page.getByRole('button', { name: 'Add URLs' }).click();
            await expect(page.getByText(/Import has been executed/)).toBeVisible({ timeout: 60_000 });
            expect(await wp.getAttachmentCount()).toBe(attachmentCount + 1);

            // revert the import.
            await page.getByRole('button', { name: 'Undo this import' }).click();
            await expect(page.getByText('The last import has been reverted!')).toBeVisible();
            expect(await wp.getAttachmentCount()).toBe(attachmentCount);
        } finally {
            await wp.updateOption('eml_revert', originalValue);
        }
    });
});

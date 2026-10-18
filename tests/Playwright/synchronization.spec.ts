import { test, expect, phpString } from './fixtures';

/**
 * Tests for the configuration of the synchronization of an external source.
 */
test.describe('Synchronization', () => {
    let termId = 0;
    // an FTP directory is used, as its configuration dialog does not need a connection.
    const directory = 'ftp://ftp.example.com/efml-sync-test/';

    // @ts-ignore
    test.beforeAll(async ({ wp }) => {
        // enable the synchronization with the extension for file sizes.
        await wp.php(`
            update_option( 'eml_sync', 1 );
            update_option( 'eml_sync_extensions', array_values( array_unique( array_merge( \\ExternalFilesInMediaLibrary\\ExternalFiles\\SynchronizationDialog::get_instance()->get_default_extensions(), array( 'sync_by_size' ) ) ) ) );
        `);

        // create the external source.
        termId = parseInt(
            await wp.php(`
                wp_set_current_user( 1 );
                echo \\easyDirectoryListingForWordPress\\Taxonomy::get_instance()->add( 'ftp', ${phpString(directory)}, array( 'login' => array( 'value' => 'login' ), 'password' => array( 'value' => 'password' ) ) );
            `),
            10
        );
        expect(termId).toBeGreaterThan(0);
    });

    // @ts-ignore
    test.afterAll(async ({ wp }) => {
        await wp.php(`wp_delete_term( ${termId}, 'edlfw_archive' ); update_option( 'eml_sync', 0 );`);
    });

    // @ts-ignore
    test('File sizes in the configuration of a synchronization are saved', async ({ page, wp }) => {
        await page.goto(wp.adminUrl('edit-tags.php?taxonomy=edlfw_archive&post_type=attachment'));

        // open the configuration dialog.
        await page.locator(`span.efml-sync[data-term-id="${termId}"]`).click();
        const dialog = page.locator('.efml-sync-config');
        await expect(dialog).toBeVisible();

        // set the file sizes.
        await dialog.locator('#min_size').fill('100');
        await dialog.locator('#max_size').fill('5000');

        // confirm the copyrights (required field).
        await dialog.getByLabel('I confirm that I will respect the copyrights of these external files.').check();

        // save the configuration.
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page.getByText('The new configuration for this synchronization has been saved.')).toBeVisible();

        // the values must be saved on the external source.
        const sizes = JSON.parse(
            await wp.php(`echo wp_json_encode( array( get_term_meta( ${termId}, 'eml_sync_min_size', true ), get_term_meta( ${termId}, 'eml_sync_max_size', true ) ) );`)
        );
        expect(sizes.map(Number)).toEqual([100, 5000]);

        // and must be shown in the dialog again.
        await page.goto(wp.adminUrl('edit-tags.php?taxonomy=edlfw_archive&post_type=attachment'));
        await page.locator(`span.efml-sync[data-term-id="${termId}"]`).click();
        await expect(page.locator('.efml-sync-config #min_size')).toHaveValue('100');
        await expect(page.locator('.efml-sync-config #max_size')).toHaveValue('5000');
    });
});

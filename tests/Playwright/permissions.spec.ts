import { test, expect } from './fixtures';

/**
 * Tests for the permissions to use external files.
 */
test.describe('Permissions', () => {
    const login = 'efml-subscriber';
    const password = 'efml-test-password';

    // @ts-ignore
    test('Users without the capability do not see the import', async ({ browser, wp }) => {
        await wp.createUser(login, password, 'subscriber');

        // login as this user in a new context (the default context is logged in as administrator).
        const context = await browser.newContext();
        const page = await context.newPage();
        // wait until the scripts of the login page are loaded, as they could reset the fields.
        await page.goto(`${wp.url}/wp-login.php`, { waitUntil: 'networkidle' });
        await page.locator('#user_login').fill(login);
        await page.locator('#user_pass').fill(password);
        await expect(page.locator('#user_pass')).toHaveValue(password);
        await page.locator('#wp-submit').click();
        await page.waitForURL(/wp-admin/);

        // the page to add external files must not be accessible.
        await page.goto(wp.adminUrl('upload.php?page=efml_local_directories'));
        await expect(page.locator('body')).toContainText('Sorry, you are not allowed to access this page.');

        // the media library must not show the button for external files.
        await page.goto(wp.adminUrl('media-new.php'));
        await expect(page.locator('a.efml-import-dialog')).toHaveCount(0);

        await context.close();
    });
});

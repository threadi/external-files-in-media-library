import { test, expect } from './fixtures';

// @ts-ignore
test('Plugin active & list of external sources loading', async ({ page, cli }) => {
    await page.goto(`${cli.serverUrl}/wp-admin/upload.php?page=efml_local_directories`);
    await expect(page.locator('#wpbody-content')).toBeVisible();
    await expect(page).toHaveTitle(/Add External Files/);
});

// @ts-ignore
test('Plugin settings can be saved', async ({ page, cli }) => {
    await page.goto(`${cli.serverUrl}/wp-admin/options-general.php?page=eml_settings`);
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.getByText('Settings saved')).toBeVisible();
});

import { test, expect } from './fixtures';

// @ts-ignore
test('Plugin active & list of external sources loading', async ({ page, wp }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto(wp.adminUrl('upload.php?page=efml_local_directories'));
    await expect(page).toHaveTitle(/Add External Files/);

    // the page must not show a PHP error.
    await expect(page.locator('body')).not.toContainText(/Fatal error|Warning:|Notice:|Deprecated:/);

    // no JavaScript error must occur.
    expect(errors).toEqual([]);
});

// @ts-ignore
test('Plugin is listed as active plugin', async ({ page, wp }) => {
    await page.goto(wp.adminUrl('plugins.php'));
    // use only the row of the plugin, not the row with the hint about a new version (it has the same data-slug).
    await expect(page.locator('tr[data-slug="external-files-in-media-library"]:not(.plugin-update-tr)')).toHaveClass(/active/);
});

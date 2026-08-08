import { test, expect } from './fixtures';

test.describe('Settings', () => {
    test('Settings-Seite lädt ohne Fehler', async ({ page, cli }) => {
        await page.goto(`${cli.serverUrl}/wp-admin/options-general.php?page=eml_settings`);
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            'External files in Media Library'
        );
    });

    test('Timeout-Wert im Advanced-Tab kann gespeichert werden', async ({ page, cli }) => {
        await page.goto(`${cli.serverUrl}/wp-admin/options-general.php?page=eml_settings`);

        // Zum "Advanced"-Tab wechseln (semantischer Link statt hartkodierter URL,
        // falls sich die Tab-URL zwischen Versionen ändert)
        await page.getByRole('link', { name: 'Advanced' }).click();

        const timeoutInput = page.getByLabel('Max. Timeout in seconds');
        await timeoutInput.fill('45');
        await page.getByRole('button', { name: /save/i }).click();

        await expect(timeoutInput).toHaveValue('45');
    });
});

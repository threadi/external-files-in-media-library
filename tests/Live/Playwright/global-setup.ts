/**
 * Log in on the development website once, the tests use the saved sessions.
 */
// @ts-ignore
import { chromium, FullConfig } from '@playwright/test';
// @ts-ignore
import { mkdirSync } from 'node:fs';
// @ts-ignore
import path from 'node:path';
import { env } from './env';

// @ts-ignore
export const authDir = path.join(__dirname, '.auth');

/**
 * Log in with the given user and save the session.
 *
 * @param config   The configuration of Playwright.
 * @param user     The login.
 * @param password The password.
 * @param file     The file for the session.
 */
async function login(config: FullConfig, user: string, password: string, file: string): Promise<void> {
    const use = config.projects[0].use;
    const browser = await chromium.launch({ ...(use.launchOptions ?? {}), ...(use.channel ? { channel: use.channel } : {}) });
    const page = await browser.newPage({ ignoreHTTPSErrors: true });
    await page.goto(`${env.siteUrl}/wp-login.php`);
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
    await page.waitForURL(/wp-admin|action=confirm_admin_email/, { timeout: 30_000 }).catch(() => undefined);

    // confirm the email of the administrator, if WordPress asks for it.
    if (page.url().includes('action=confirm_admin_email')) {
        await page.locator('#correct-admin-email').click();
    }
    await page.waitForURL(/wp-admin/, { timeout: 30_000 }).catch(() => {
        throw new Error(`The login of ${user} on ${env.siteUrl} failed, check EFML_LIVE_SITE_* in ${env.file}.`);
    });
    await page.context().storageState({ path: file });
    await browser.close();
}

export default async function globalSetup(config: FullConfig): Promise<void> {
    // bail if the development website is not configured: the tests are skipped then.
    if (!env.isSiteConfigured) {
        console.warn(`\nLive tests: the development website is not configured (EFML_LIVE_SITE_URL, EFML_LIVE_SITE_ADMIN_USER and EFML_LIVE_SITE_ADMIN_PASSWORD in ${env.file}), no test has been run.\n`);
        return;
    }

    mkdirSync(authDir, { recursive: true });
    await login(config, env.get('EFML_LIVE_SITE_ADMIN_USER'), env.get('EFML_LIVE_SITE_ADMIN_PASSWORD'), path.join(authDir, 'admin.json'));
    if (env.isEditorConfigured) {
        await login(config, env.get('EFML_LIVE_SITE_EDITOR_USER'), env.get('EFML_LIVE_SITE_EDITOR_PASSWORD'), path.join(authDir, 'editor.json'));
    }
}

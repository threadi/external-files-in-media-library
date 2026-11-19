/**
 * Check the configuration and log in on the development website once, the tests use the saved sessions.
 *
 * The sessions are saved in tests/Live/Playwright/.auth/admin.json and editor.json. If the website is not configured,
 * empty sessions are saved, so the tests are skipped with the reason instead of failing.
 */
// @ts-ignore
import { chromium, request, FullConfig } from '@playwright/test';
// @ts-ignore
import { mkdirSync, writeFileSync } from 'node:fs';
// @ts-ignore
import os from 'node:os';
// @ts-ignore
import path from 'node:path';
import { env } from './env';

// @ts-ignore
export const authDir = path.join(__dirname, '.auth');

/**
 * The URL of the example configuration, which must be replaced.
 */
const exampleUrl = 'https://efml.local';

/**
 * Save an empty session in the given file.
 *
 * @param file The file.
 */
function saveEmptySession(file: string): void {
    writeFileSync(file, JSON.stringify({ cookies: [], origins: [] }));
}

/**
 * Return the keys of the development website, which have no value.
 */
function getMissingKeys(): string[] {
    return ['EFML_LIVE_SITE_URL', 'EFML_LIVE_SITE_ADMIN_USER', 'EFML_LIVE_SITE_ADMIN_PASSWORD'].filter((key) => !env.has(key));
}

/**
 * Return a message with the steps to fix the configuration.
 *
 * @param lines The lines of the message.
 */
function message(lines: string[]): string {
    return ['', 'Live tests (Playwright):', ...lines.map((line) => `  ${line}`), '  See docs/live-tests.md, section "Playwright".', ''].join('\n');
}

/**
 * Check that the development website is reachable from this computer.
 */
async function checkReachable(): Promise<void> {
    const url = `${env.siteUrl}/wp-login.php`;
    const context = await request.newContext({ ignoreHTTPSErrors: true });
    try {
        const response = await context.get(url, { maxRedirects: 5, timeout: 30_000 });
        if (response.status() >= 400) {
            throw new Error(message([
                `${url} answers with HTTP status ${response.status()}.`,
                `Check EFML_LIVE_SITE_URL in ${env.file}: it must be the address of the WordPress website (without "/wp-admin").`,
            ]));
        }
    } catch (error: any) {
        const text = String(error?.message ?? error);
        if (text.includes('Live tests (Playwright)')) {
            throw error;
        }
        const host = (() => {
            try {
                return new URL(env.siteUrl).host;
            } catch {
                return env.siteUrl;
            }
        })();
        const lines = [`${url} is not reachable from this computer (${os.hostname()}): ${text.split('\n')[0]}`];
        if (/ENOTFOUND|EAI_AGAIN|NAME_NOT_RESOLVED|getaddrinfo/i.test(text)) {
            lines.push(`The name "${host}" could not be resolved. The tests run on the computer where you start them, so the URL must be reachable from there (real domain or entry in the hosts file).`);
        } else if (/ECONNREFUSED|CONNECTION_REFUSED/i.test(text)) {
            lines.push(`Nothing answers at "${host}": check the web server and the port.`);
        } else if (/certificate|SSL|TLS/i.test(text)) {
            lines.push('The certificate could not be checked: use the URL of the website exactly as in its settings.');
        }
        if (env.siteUrl === exampleUrl) {
            lines.push(`"${exampleUrl}" is the value of the example: replace it with the URL of your development website.`);
        }
        lines.push(`Set EFML_LIVE_SITE_URL in ${env.file}.`);
        throw new Error(message(lines));
    } finally {
        await context.dispose();
    }
}

/**
 * Log in with the given user and save the session.
 *
 * @param config   The configuration of Playwright.
 * @param keyUser  The key of the login in ".env.live".
 * @param keyPass  The key of the password in ".env.live".
 * @param file     The file for the session.
 */
async function login(config: FullConfig, keyUser: string, keyPass: string, file: string): Promise<void> {
    const user = env.get(keyUser);
    const use = config.projects[0].use;
    const browser = await chromium.launch({ ...(use.launchOptions ?? {}), ...(use.channel ? { channel: use.channel } : {}) });
    try {
        const page = await browser.newPage({ ignoreHTTPSErrors: true });
        await page.goto(`${env.siteUrl}/wp-login.php`);

        // bail if this is not the login form of WordPress (e.g. a login protection plugin).
        if (0 === (await page.locator('#user_login').count())) {
            throw new Error(message([
                `${env.siteUrl}/wp-login.php shows no login form of WordPress (address: ${page.url()}).`,
                'Plugins, which change or protect the login (e.g. a hidden login URL), must allow the login there.',
            ]));
        }
        await page.locator('#user_login').fill(user);
        await page.locator('#user_pass').fill(env.get(keyPass));
        await page.locator('#wp-submit').click();
        await page.waitForURL(/wp-admin|action=confirm_admin_email/, { timeout: 30_000 }).catch(() => undefined);

        // confirm the email of the administrator, if WordPress asks for it.
        if (page.url().includes('action=confirm_admin_email')) {
            await page.locator('#correct-admin-email').click();
        }
        const loggedIn = await page.waitForURL(/wp-admin/, { timeout: 30_000 }).then(() => true).catch(() => false);
        if (!loggedIn) {
            const error = (await page.locator('#login_error').innerText().catch(() => '')).trim();
            throw new Error(message([
                `The login of "${user}" on ${env.siteUrl} failed${error ? `: ${error}` : ` (address after the login: ${page.url()})`}.`,
                `Check ${keyUser} and ${keyPass} in ${env.file}. Two-factor authentication or a captcha must be disabled for this user.`,
            ]));
        }
        await page.context().storageState({ path: file });
    } finally {
        await browser.close();
    }
}

export default async function globalSetup(config: FullConfig): Promise<void> {
    mkdirSync(authDir, { recursive: true });
    const adminFile = path.join(authDir, 'admin.json');
    const editorFile = path.join(authDir, 'editor.json');

    // save empty sessions, so the tests could start and skip themselves, if the website is not usable.
    saveEmptySession(adminFile);
    saveEmptySession(editorFile);

    // bail if the development website is not configured: the tests are skipped then.
    const missing = getMissingKeys();
    if (missing.length > 0) {
        const lines = env.exists
            ? [`These values are missing in ${env.file}: ${missing.join(', ')}.`, 'Remove the "#" at the start of their lines and set them.']
            : [`The file ${env.file} does not exist: copy .env.live.example to .env.live and set EFML_LIVE_SITE_URL, EFML_LIVE_SITE_ADMIN_USER and EFML_LIVE_SITE_ADMIN_PASSWORD.`];
        console.warn(message([...lines, 'No test has been run.']));
        return;
    }

    // bail if the URL is not the address of the website.
    if (!/^https?:\/\/[^/]+/i.test(env.siteUrl) || /\/(wp-admin|wp-login\.php)(\/|$)/i.test(env.siteUrl)) {
        throw new Error(message([
            `EFML_LIVE_SITE_URL in ${env.file} must be the address of the WordPress website with "http://" or "https://" and without "/wp-admin" or "/wp-login.php", e.g. https://dev.example.com (current value: ${env.siteUrl}).`,
        ]));
    }

    // check the website and log in.
    await checkReachable();
    await login(config, 'EFML_LIVE_SITE_ADMIN_USER', 'EFML_LIVE_SITE_ADMIN_PASSWORD', adminFile);
    if (env.isEditorConfigured) {
        await login(config, 'EFML_LIVE_SITE_EDITOR_USER', 'EFML_LIVE_SITE_EDITOR_PASSWORD', editorFile);
    } else if (env.has('EFML_LIVE_SITE_EDITOR_USER') || env.has('EFML_LIVE_SITE_EDITOR_PASSWORD')) {
        console.warn(message(['EFML_LIVE_SITE_EDITOR_USER and EFML_LIVE_SITE_EDITOR_PASSWORD must both be set, the tests as editor are skipped.']));
    }
}

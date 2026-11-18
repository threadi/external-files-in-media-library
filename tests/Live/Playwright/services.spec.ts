/**
 * Live tests of the services on the development website with real accounts: the directory listing, the import via
 * dialog, the export of uploads (public and private) and the delivery to visitors.
 *
 * The credentials come from the plugin "EFML Dev Credentials" on the website. A test runs only for a service, whose
 * credentials are complete in ".env.live" and which is active on the website, the others are skipped.
 */
// @ts-ignore
import { test, expect, Browser, Page } from '@playwright/test';
// @ts-ignore
import { copyFileSync, existsSync } from 'node:fs';
// @ts-ignore
import os from 'node:os';
// @ts-ignore
import path from 'node:path';
import { env } from './env';
import { Site, ServiceState, Status } from './site';
import { authDir } from './global-setup';

// @ts-ignore
const testImage = path.resolve(__dirname, '../../Data/live-example.jpg');

/**
 * The ID of this run, part of the names of all files the tests upload.
 */
const runId = `efml-live-${Date.now().toString(36)}`;

test.use({ storageState: path.join(authDir, 'admin.json') });

let status: Status | null = null;

test.beforeAll(async ({ request }) => {
    test.skip(!env.isSiteConfigured, 'The development website is not configured in .env.live.');
    status = await new Site(request).status();
});

/**
 * Return the state of the given service on the website, skip the test if it is not usable there.
 *
 * @param name The name of the service.
 */
function getServiceState(name: string): ServiceState {
    test.skip(null === status, 'The plugin "EFML Dev Credentials" is not active on the development website.');
    test.skip(!status!.enabled, 'The credentials are disabled on the development website (admin bar "EFML Dev").');
    const state = status!.services.find((service) => service.name === name);
    test.skip(!state || !state.active, `The credentials of this service are not used on the development website (disabled or incomplete in ${status!.file}).`);
    test.skip(!state!.available, `The service is not available on the development website: its extension ${state!.plugin} is not active.`);
    return state!;
}

/**
 * Upload the test image via "Add Media File" and return its attachment ID.
 *
 * @param page The page.
 * @param site The website.
 * @param name Part of the file name.
 */
async function uploadImage(page: Page, site: Site, name: string): Promise<number> {
    const file = path.join(os.tmpdir(), `${runId}-${name}.jpg`);
    if (!existsSync(file)) {
        copyFileSync(testImage, file);
    }
    const latestId = await site.getLatestMediaId();

    // upload it with the browser uploader of WordPress (the export runs during the upload).
    await page.goto(site.adminUrl('media-new.php?browser-uploader'));
    await page.locator('#async-upload').setInputFiles(file);
    await page.locator('#html-upload').click();
    await page.waitForURL(/upload\.php/, { timeout: 120_000 });

    const ids = await site.getMediaAfter(latestId);
    expect(ids.length, 'The image has not been uploaded.').toBeGreaterThan(0);
    return ids[0];
}

/**
 * Return the response of the given URL for a visitor (a new browser context without login).
 *
 * @param browser The browser.
 * @param url     The URL.
 */
async function loadAsVisitor(browser: Browser, url: string): Promise<{ status: number; type: string; size: number }> {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, storageState: { cookies: [], origins: [] } });
    try {
        const response = await context.request.get(url, { maxRedirects: 5 });
        return { status: response.status(), type: response.headers()['content-type'] ?? '', size: (await response.body()).length };
    } finally {
        await context.close();
    }
}

for (const service of env.configuredServices) {
    test.describe(service.label, () => {
        const sources: number[] = [];
        const attachments: number[] = [];

        test.afterEach(async ({ request }) => {
            const site = new Site(request);
            await site.deleteMedia(attachments.splice(0));
            await site.reset(sources.splice(0));
        });

        if (service.listing) {
            test('Open the directory listing and import a file via dialog', async ({ page, request, browser }) => {
                getServiceState(service.name);
                const site = new Site(request);
                const sourceId = await site.addSource(service.name);
                sources.push(sourceId);

                // open the external source.
                await page.goto(site.adminUrl(`upload.php?page=efml_local_directories&method=${service.name}&term=${sourceId}`));
                const importButtons = page.getByRole('button', { name: 'Import', exact: true });
                const details = page.locator('#easy-directory-listing-for-wordpress-details');
                await details.waitFor({ timeout: 60_000 }).catch(async () => {
                    throw new Error(`The directory listing could not be loaded: ${await page.locator('#wpbody-content').innerText()}`);
                });

                // open the first subdirectory, if the directory contains no files (up to 2 levels).
                for (let level = 0; level < 2 && 0 === (await importButtons.count()); level++) {
                    const subdirectory = page.locator('#easy-directory-listing-for-wordpress-listing li.open > ul > li > button').first();
                    if (0 === (await subdirectory.count())) {
                        break;
                    }
                    await subdirectory.click();
                    await importButtons.first().waitFor({ timeout: 30_000 }).catch(() => undefined);
                }
                test.skip(0 === (await importButtons.count()), 'The directory listing shows no files to import (an empty directory, see the screenshot).');

                // import the first file via the dialog.
                const latestId = await site.getLatestMediaId();
                await importButtons.first().click();
                await page.getByLabel('I confirm that I will respect the copyrights of these external files.').check();
                await page.getByRole('button', { name: 'Add URL', exact: true }).click();
                await expect(page.getByText(/Import has been executed/)).toBeVisible({ timeout: 120_000 });

                // it is in the media library as external file.
                const ids = await site.getMediaAfter(latestId);
                attachments.push(...ids);
                expect(ids.length, 'The file has not been imported, see the log of the plugin.').toBeGreaterThan(0);
                const file = await site.getFile(ids[0]);

                // visitors get it.
                const response = await loadAsVisitor(browser, file.public_url);
                expect(response.status, `The imported file is not delivered to visitors: ${file.public_url}`).toBe(200);
                expect(response.size).toBeGreaterThan(0);
            });
        }

        if (service.export) {
            test('Export an upload', async ({ page, request, browser }) => {
                const state = getServiceState(service.name);
                test.skip(!!service.export_url && !state.export_url_set, `The export to this service requires the public URL of its directory in ${service.export_url}.`);
                const site = new Site(request);
                const sourceId = await site.addSource(service.name);
                sources.push(sourceId);
                await site.prepareExport(sourceId);

                // upload an image, which is exported.
                const attachmentId = await uploadImage(page, site, service.name);
                attachments.push(attachmentId);
                const file = await site.getFile(attachmentId);
                expect(file.hosting, 'The upload has not been exported, see the log of the plugin.').toBe('external');

                // visitors get it.
                const response = await loadAsVisitor(browser, file.public_url);
                expect(response.status, `The exported file is not delivered to visitors: ${file.public_url}`).toBe(200);
                expect(response.type).toContain('image/');
            });
        }

        if (service.private) {
            test('Export an upload privately', async ({ page, request, browser }) => {
                getServiceState(service.name);
                const site = new Site(request);
                const sourceId = await site.addSource(service.name);
                sources.push(sourceId);
                await site.prepareExport(sourceId, true);

                // upload an image, which is exported as private file.
                const attachmentId = await uploadImage(page, site, `${service.name}-private`);
                attachments.push(attachmentId);
                const file = await site.getFile(attachmentId);
                expect(file.hosting, 'The upload has not been exported, see the log of the plugin.').toBe('external');

                // visitors get it via the website, not directly.
                const response = await loadAsVisitor(browser, file.public_url);
                expect(response.status, `The private file is not delivered to visitors: ${file.public_url}`).toBe(200);
                expect(response.type).toContain('image/');
                if (file.url && /^https?:/.test(file.url) && file.url !== file.public_url) {
                    expect((await loadAsVisitor(browser, file.url)).status, `The private file is public available: ${file.url}`).not.toBe(200);
                }
            });
        }

        if (service.export && env.isEditorConfigured) {
            test('Export an upload of an editor', async ({ browser, request }) => {
                const state = getServiceState(service.name);
                test.skip(!!service.export_url && !state.export_url_set, `The export to this service requires the public URL of its directory in ${service.export_url}.`);
                const site = new Site(request);
                const sourceId = await site.addSource(service.name);
                sources.push(sourceId);
                await site.prepareExport(sourceId);

                // upload an image as editor.
                const context = await browser.newContext({ ignoreHTTPSErrors: true, storageState: path.join(authDir, 'editor.json') });
                try {
                    const attachmentId = await uploadImage(await context.newPage(), site, `${service.name}-editor`);
                    attachments.push(attachmentId);
                    const file = await site.getFile(attachmentId);
                    expect(file.hosting, 'The upload of the editor has not been exported, see the log of the plugin.').toBe('external');
                } finally {
                    await context.close();
                }
            });
        }
    });
}

test.describe('Single URLs', () => {
    const attachments: number[] = [];

    test.afterEach(async ({ request }) => {
        await new Site(request).deleteMedia(attachments.splice(0));
    });

    for (const url of env.urls) {
        test(`Add ${url} via dialog`, async ({ page, request }) => {
            const site = new Site(request);
            const latestId = await site.getLatestMediaId();

            await page.goto(site.adminUrl('media-new.php'));
            await page.locator('#plupload-upload-ui').locator('a.efml-import-dialog').click();
            await page.locator('#urls').fill(url);
            await page.getByLabel('I confirm that I will respect the copyrights of these external files.').check();
            await page.getByRole('button', { name: 'Add URLs' }).click();
            await expect(page.getByText(/Import has been executed/)).toBeVisible({ timeout: 120_000 });

            const ids = await site.getMediaAfter(latestId);
            attachments.push(...ids);
            expect(ids.length, `The URL ${url} has not been added, see the log of the plugin.`).toBeGreaterThan(0);
        });
    }
});

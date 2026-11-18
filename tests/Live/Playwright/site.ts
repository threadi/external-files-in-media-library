/**
 * Helper for the development website: the REST API of WordPress, the abilities of the plugin and the endpoints of the
 * plugin "EFML Dev Credentials".
 */
// @ts-ignore
import { APIRequestContext, expect } from '@playwright/test';
import { env } from './env';

/**
 * The state of a service on the development website, see the endpoint "efml-dev/v1/status".
 */
export type ServiceState = {
    name: string;
    label: string;
    plugin: string;
    configured: boolean;
    active: boolean;
    available: boolean;
    missing: string[];
    listing: boolean;
    export: boolean;
    private: boolean;
    export_url_set: boolean;
};

/**
 * The state of the development website.
 */
export type Status = {
    version: string;
    enabled: boolean;
    file: string;
    environment: string;
    plugin: string;
    services: ServiceState[];
    urls: string[];
};

/**
 * The prefix of the abilities of the plugin.
 */
const abilityPrefix = 'external-files-in-media-library/';

export class Site {
    private nonce = '';

    constructor(private readonly request: APIRequestContext) {}

    /**
     * Return the URL of the REST API for the given route (works also without pretty permalinks).
     *
     * @param route The route, e.g. "/wp/v2/media".
     */
    restUrl(route: string): string {
        return `${env.siteUrl}/?rest_route=${encodeURIComponent(route)}`;
    }

    /**
     * Return the URL for the given path in wp-admin.
     *
     * @param adminPath The path.
     */
    adminUrl(adminPath: string): string {
        return `${env.siteUrl}/wp-admin/${adminPath}`;
    }

    /**
     * Return the headers for the REST API with the nonce of the session.
     */
    private async headers(): Promise<Record<string, string>> {
        if ('' === this.nonce) {
            const response = await this.request.get(`${env.siteUrl}/wp-admin/admin-ajax.php?action=rest-nonce`);
            this.nonce = (await response.text()).trim();
        }
        return { 'X-WP-Nonce': this.nonce };
    }

    /**
     * Return the state of the plugin "EFML Dev Credentials", null if it is not installed.
     */
    async status(): Promise<Status | null> {
        const response = await this.request.get(this.restUrl('/efml-dev/v1/status'), { headers: await this.headers() });
        return response.ok() ? ((await response.json()) as Status) : null;
    }

    /**
     * Run an ability of the plugin and return its result, fail with its error.
     *
     * @param name     The name of the ability without the prefix.
     * @param input    The input.
     * @param readonly Whether the ability is read-only.
     */
    async ability(name: string, input: Record<string, any>, readonly = false): Promise<any> {
        const url = this.restUrl(`/wp-abilities/v1/abilities/${abilityPrefix}${name}/run`);

        // read-only abilities require GET with the input as parameters.
        const response = readonly
            ? await this.request.get(url + Object.entries(input).map(([key, value]) => `&input[${encodeURIComponent(key)}]=${encodeURIComponent(String(value))}`).join(''), { headers: await this.headers() })
            : await this.request.post(url, { headers: await this.headers(), data: { input } });
        const body = await response.text();
        expect(response.ok(), `The ability ${name} failed: ${body}`).toBeTruthy();
        return JSON.parse(body);
    }

    /**
     * Save an external source of the given service with the credentials of the development website.
     *
     * @param service The name of the service.
     */
    async addSource(service: string): Promise<number> {
        const input: Record<string, any> = { service, fields: {} };

        // the local directory is given as path.
        if ('local' === service) {
            input.directory = 'file://' + env.get('EFML_LIVE_LOCAL_DIRECTORY').replace(/\/?$/, '/');
        }

        const result = await this.ability('add-source', input);
        expect(result.id, 'The external source could not be saved.').toBeGreaterThan(0);
        return result.id;
    }

    /**
     * Prepare the export to the given external source.
     *
     * @param sourceId  The ID of the external source.
     * @param isPrivate Whether the files should be saved privately.
     */
    async prepareExport(sourceId: number, isPrivate = false): Promise<any> {
        const response = await this.request.post(this.restUrl('/efml-dev/v1/prepare'), {
            headers: await this.headers(),
            data: { action: 'export', source_id: sourceId, private: isPrivate },
        });
        const body = await response.text();
        expect(response.ok(), `The export could not be prepared: ${body}`).toBeTruthy();
        return JSON.parse(body);
    }

    /**
     * Delete the given external sources and restore the settings of the export.
     *
     * @param sourceIds The IDs of the external sources.
     */
    async reset(sourceIds: number[]): Promise<void> {
        await this.request.post(this.restUrl('/efml-dev/v1/prepare'), {
            headers: await this.headers(),
            data: { action: 'reset', source_ids: sourceIds },
        });
    }

    /**
     * Return the external file of the given attachment.
     *
     * @param attachmentId The attachment ID.
     */
    async getFile(attachmentId: number): Promise<any> {
        return this.ability('get-file', { attachment_id: attachmentId }, true);
    }

    /**
     * Return the highest attachment ID.
     */
    async getLatestMediaId(): Promise<number> {
        const response = await this.request.get(this.restUrl('/wp/v2/media') + '&per_page=1&orderby=id&order=desc&context=edit', { headers: await this.headers() });
        expect(response.ok(), await response.text()).toBeTruthy();
        const media = (await response.json()) as any[];
        return media.length > 0 ? media[0].id : 0;
    }

    /**
     * Return the IDs of the attachments newer than the given ID.
     *
     * @param afterId The attachment ID.
     */
    async getMediaAfter(afterId: number): Promise<number[]> {
        const response = await this.request.get(this.restUrl('/wp/v2/media') + '&per_page=100&orderby=id&order=desc&context=edit', { headers: await this.headers() });
        expect(response.ok(), await response.text()).toBeTruthy();
        return ((await response.json()) as any[]).map((media) => media.id).filter((id: number) => id > afterId);
    }

    /**
     * Return the IDs of the attachments with the given text in their title (newest first).
     *
     * @param search The text.
     */
    async findMedia(search: string): Promise<number[]> {
        const response = await this.request.get(this.restUrl('/wp/v2/media') + `&search=${encodeURIComponent(search)}&per_page=100&context=edit`, { headers: await this.headers() });
        expect(response.ok(), await response.text()).toBeTruthy();
        return ((await response.json()) as any[]).map((media) => media.id);
    }

    /**
     * Delete the given attachments (also on their external source, if they have been exported).
     *
     * @param attachmentIds The attachment IDs.
     */
    async deleteMedia(attachmentIds: number[]): Promise<void> {
        for (const attachmentId of attachmentIds) {
            await this.request.delete(this.restUrl(`/wp/v2/media/${attachmentId}`) + '&force=true', { headers: await this.headers() });
        }
    }
}

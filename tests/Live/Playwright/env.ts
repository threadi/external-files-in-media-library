/**
 * Read the configuration of the live tests from ".env.live" (like tests/Live/Env.php).
 */
// @ts-ignore
import { existsSync, readFileSync } from 'node:fs';
// @ts-ignore
import path from 'node:path';

// @ts-ignore
const pluginRoot = path.resolve(__dirname, '../../..');

/**
 * A service of the live tests, see services.json.
 */
export type Service = {
    name: string;
    label: string;
    plugin: string;
    required: string[];
    optional?: string[];
    listing: boolean;
    export: boolean;
    private: boolean;
    export_url?: string;
    directory?: string;
};

/**
 * Return the path of ".env.live": from the environment variable EFML_LIVE_ENV, otherwise in the plugin directory.
 */
export function getEnvFile(): string {
    // @ts-ignore
    return process.env.EFML_LIVE_ENV || path.join(pluginRoot, '.env.live');
}

/**
 * Return the values of the given content of a ".env" file.
 *
 * @param content   The content.
 * @param directory The directory of the file.
 */
export function parseEnv(content: string, directory: string): Record<string, string> {
    const values: Record<string, string> = {};
    const files: Record<string, string> = {};
    for (let line of content.split(/\r\n|\r|\n/)) {
        line = line.trim();
        if ('' === line || line.startsWith('#')) {
            continue;
        }
        if (line.startsWith('export ')) {
            line = line.substring(7).trim();
        }
        const matches = line.match(/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);
        if (!matches) {
            continue;
        }
        let value = matches[2];
        if (value.length >= 2 && (('"' === value[0] && value.endsWith('"')) || ("'" === value[0] && value.endsWith("'")))) {
            value = value.substring(1, value.length - 1);
            if ('"' === matches[2][0]) {
                value = value.replace(/\\n/g, '\n').replace(/\\"/g, '"');
            }
        } else {
            value = value.replace(/\s+#.*$/, '').trim();
        }
        if (matches[1].endsWith('_FILE')) {
            files[matches[1].substring(0, matches[1].length - 5)] = value;
        }
        values[matches[1]] = value;
    }

    // read the files.
    for (const [key, file] of Object.entries(files)) {
        if (undefined !== values[key] || '' === file) {
            continue;
        }
        const filePath = path.isAbsolute(file) ? file : path.join(directory, file);
        if (existsSync(filePath)) {
            values[key] = readFileSync(filePath, 'utf8').trim();
        }
    }

    return values;
}

/**
 * The configuration of the live tests.
 */
class Env {
    private readonly values: Record<string, string> = {};
    readonly file: string;
    readonly exists: boolean;

    constructor() {
        this.file = getEnvFile();
        this.exists = existsSync(this.file);
        if (this.exists) {
            this.values = parseEnv(readFileSync(this.file, 'utf8'), path.dirname(this.file));
        }
    }

    /**
     * Return the value of the given key: from the environment, otherwise from the file.
     *
     * @param key          The key.
     * @param defaultValue The default value.
     */
    get(key: string, defaultValue = ''): string {
        // @ts-ignore
        const value = process.env[key];
        if (value) {
            return value;
        }
        return this.values[key] || defaultValue;
    }

    /**
     * Return whether the given key has a value.
     *
     * @param key The key.
     */
    has(key: string): boolean {
        return '' !== this.get(key);
    }

    /**
     * Return the URL of the development website without slash at the end.
     */
    get siteUrl(): string {
        return this.get('EFML_LIVE_SITE_URL').replace(/\/+$/, '');
    }

    /**
     * Return whether the development website is configured.
     */
    get isSiteConfigured(): boolean {
        return '' !== this.siteUrl && this.has('EFML_LIVE_SITE_ADMIN_USER') && this.has('EFML_LIVE_SITE_ADMIN_PASSWORD');
    }

    /**
     * Return whether an editor is configured.
     */
    get isEditorConfigured(): boolean {
        return this.has('EFML_LIVE_SITE_EDITOR_USER') && this.has('EFML_LIVE_SITE_EDITOR_PASSWORD');
    }

    /**
     * Return the services, whose credentials are complete.
     */
    get configuredServices(): Service[] {
        const data = JSON.parse(readFileSync(path.join(pluginRoot, 'tests/Live/services.json'), 'utf8'));
        return (data.services as Service[]).filter((service) => service.required.every((key) => this.has(key)));
    }

    /**
     * Return the single URLs to import.
     */
    get urls(): string[] {
        return this.get('EFML_LIVE_URLS').split(',').map((url) => url.trim()).filter((url) => '' !== url);
    }
}

export const env = new Env();

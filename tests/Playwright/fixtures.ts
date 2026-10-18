// @ts-ignore
import { test as base } from '@playwright/test';
// @ts-ignore
import { runCLI } from '@wp-playground/cli';
// @ts-ignore
import { execSync } from 'node:child_process';
// @ts-ignore
import { existsSync, statSync } from 'node:fs';
// @ts-ignore
import path from 'node:path';

// @ts-ignore
const pluginRoot = path.resolve(__dirname, '../..');
const autoloadPath = path.join(pluginRoot, 'vendor', 'autoload.php');
const lockPath = path.join(pluginRoot, 'composer.lock');

/**
 * The slug of this plugin (= the directory name in the Playground).
 */
export const pluginSlug = 'external-files-in-media-library';

/**
 * The path of this plugin in the Playground.
 */
export const pluginVfsPath = `/wordpress/wp-content/plugins/${pluginSlug}`;

function needsComposerInstall(): boolean {
    if (!existsSync(autoloadPath)) return true;
    if (!existsSync(lockPath)) return false;
    return statSync(lockPath).mtimeMs > statSync(autoloadPath).mtimeMs;
}

function ensureComposerDeps(): void {
    if (!needsComposerInstall()) {
        console.log('vendor/autoload.php is up to date – do not run composer install.');
        return;
    }
    console.log('Running composer install …');
    execSync('composer install --optimize-autoloader', {
        cwd: pluginRoot,
        stdio: 'inherit',
    });
}

/**
 * Return the given string as PHP string literal.
 *
 * @param value The string.
 */
export function phpString(value: string): string {
    return "'" + value.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

type CliServer = Awaited<ReturnType<typeof runCLI>>;

/**
 * Helper to prepare the WordPress in the Playground for single tests (options, users, files).
 *
 * Each call runs PHP directly in the Playground, so no UI interaction is necessary for the preparation.
 */
export class WordPress {
    constructor(private readonly cli: CliServer) {}

    /**
     * Return the URL of the WordPress.
     */
    get url(): string {
        return this.cli.serverUrl;
    }

    /**
     * Return the URL for the given path in wp-admin.
     *
     * @param adminPath The path, e.g. "options-general.php?page=eml_settings".
     */
    adminUrl(adminPath: string): string {
        return `${this.cli.serverUrl}/wp-admin/${adminPath}`;
    }

    /**
     * Return the public URL of a file of this plugin.
     *
     * @param file The path relative to the plugin directory.
     */
    pluginFileUrl(file: string): string {
        return `${this.cli.serverUrl}/wp-content/plugins/${pluginSlug}/${file}`;
    }

    /**
     * Run PHP code with loaded WordPress and return its output.
     *
     * @param code The PHP code (without "<?php").
     */
    async php(code: string): Promise<string> {
        // @ts-ignore
        const response = await this.cli.playground.run({
            code: `<?php require_once '/wordpress/wp-load.php'; ${code}`,
        });
        if (response.errors) {
            throw new Error(`PHP error: ${response.errors}`);
        }
        return response.text;
    }

    /**
     * Save an option.
     *
     * @param name The option name.
     * @param value The value (will be saved as it would be decoded from JSON).
     */
    async updateOption(name: string, value: unknown): Promise<void> {
        await this.php(`update_option( ${phpString(name)}, json_decode( ${phpString(JSON.stringify(value))}, true ) );`);
    }

    /**
     * Delete an option.
     *
     * @param name The option name.
     */
    async deleteOption(name: string): Promise<void> {
        await this.php(`delete_option( ${phpString(name)} );`);
    }

    /**
     * Return the value of an option.
     *
     * @param name The option name.
     */
    async getOption(name: string): Promise<unknown> {
        return JSON.parse(await this.php(`echo wp_json_encode( get_option( ${phpString(name)} ) );`));
    }

    /**
     * Create a user (if it does not exist) and return its ID.
     *
     * @param login The login name.
     * @param password The password.
     * @param role The role.
     */
    async createUser(login: string, password: string, role: string): Promise<number> {
        const id = await this.php(`
            $user = get_user_by( 'login', ${phpString(login)} );
            if ( $user ) {
                wp_set_password( ${phpString(password)}, $user->ID );
                $user->set_role( ${phpString(role)} );
                echo $user->ID;
            } else {
                echo wp_insert_user( array( 'user_login' => ${phpString(login)}, 'user_pass' => ${phpString(password)}, 'user_email' => ${phpString(login + '@example.com')}, 'role' => ${phpString(role)} ) );
            }
        `);
        return parseInt(id, 10);
    }

    /**
     * Write a file in the Playground.
     *
     * @param file The absolute path in the Playground.
     * @param content The content.
     */
    async writeFile(file: string, content: string): Promise<void> {
        // @ts-ignore
        await this.cli.playground.mkdir(path.posix.dirname(file));
        // @ts-ignore
        await this.cli.playground.writeFile(file, content);
    }

    /**
     * Return the count of attachments in the media library.
     */
    async getAttachmentCount(): Promise<number> {
        return parseInt(await this.php(`echo count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );`), 10);
    }

    /**
     * Delete all attachments.
     */
    async deleteAllAttachments(): Promise<void> {
        await this.php(`foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_attachment( $id, true ); }`);
    }
}

export const test = base.extend<{}, { cli: CliServer; wp: WordPress }>({
    // worker-scoped: runs once per test worker, not per test.
    cli: [
        // @ts-ignore
        async ({}, use) => {
            ensureComposerDeps();

            const cli = await runCLI({
                command: 'server',
                workers: 6,
                mount: [
                    {
                        // use the absolute path, so the tests could be started from any directory.
                        hostPath: pluginRoot,
                        vfsPath: pluginVfsPath,
                    },
                ],
                blueprint: {
                    // EFML_PLAYGROUND_WP allows to use another WordPress version or a local WordPress-ZIP (e.g., without internet access).
                    // @ts-ignore
                    preferredVersions: { php: '8.3', wp: process.env.EFML_PLAYGROUND_WP || 'latest' },
                    login: true,
                    steps: [
                        {
                            step: 'activatePlugin',
                            pluginPath: `${pluginSlug}/${pluginSlug}.php`,
                        },
                    ],
                },
            });

            await use(cli);

            await cli?.server?.close();
        },
        { scope: 'worker' },
    ],

    // helper to prepare the WordPress for single tests.
    wp: [
        // @ts-ignore
        async ({ cli }, use) => {
            await use(new WordPress(cli));
        },
        { scope: 'worker' },
    ],
});

// @ts-ignore
export { expect } from '@playwright/test';

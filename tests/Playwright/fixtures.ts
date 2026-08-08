import { test as base } from '@playwright/test';
import { runCLI } from '@wp-playground/cli';
import { execSync } from 'node:child_process';
import { existsSync, statSync } from 'node:fs';
import path from 'node:path';

const pluginRoot = path.resolve(__dirname, '../..');
const autoloadPath = path.join(pluginRoot, 'vendor', 'autoload.php');
const lockPath = path.join(pluginRoot, 'composer.lock');

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

type CliServer = Awaited<ReturnType<typeof runCLI>>;

export const test = base.extend<{}, { cli: CliServer }>({
    // worker-scoped: läuft einmal pro Test-Worker, nicht pro Test
    cli: [
        async ({}, use) => {
            ensureComposerDeps();

            const cli = await runCLI({
                command: 'server',
                mount: [
                    {
                        hostPath: './',
                        vfsPath: '/wordpress/wp-content/plugins/external-files-in-media-library',
                    },
                ],
                blueprint: {
                    preferredVersions: { php: '8.3', wp: 'latest' },
                    login: true,
                    steps: [
                        {
                            step: 'activatePlugin',
                            pluginPath: 'external-files-in-media-library/external-files-in-media-library.php',
                        },
                    ],
                },
            });

            await use(cli);

            await cli?.server?.close();
        },
        { scope: 'worker' },
    ],
});

export { expect } from '@playwright/test';

# AGENTS.md

Instructions for AI coding agents working on the **External files in Media Library** WordPress plugin repository (`threadi/external-files-in-media-library`).

This file is for **plugin development** in this repository. It is not end-user documentation.

## Skills in this repository

| Skill | For |
|-------|-----|
| `skills/external-files-in-media-library/` | Assistants helping site operators to use the plugin (not for development) |
| `skills/external-files-in-media-library-developer/` | Developers who extend the plugin from their own code (hooks, services, REST, WP-CLI, abilities) |
| `skills/external-files-in-media-library-code-review/` | Reviewing the code of this plugin topic by topic with a fixed checklist |

Run the review skill after larger changes. It lists the problems which came back before.

## Project overview

The plugin lets the media library of WordPress use files which are not stored in the uploads directory:

- **Import:** add files by their URL or from a service (HTTP, FTP, SFTP, local directory, Dropbox, YouTube, Vimeo, Imgur, another WordPress site, ZIP, URL lists, a multisite blog; with extensions AWS S3 and S3-compatible storages, Google Cloud Storage, Google Drive, WebDAV). The files stay on their external source and are delivered via a **proxy** of the website or directly.
- **Synchronization:** directories saved as **external sources** (taxonomy terms) are synchronized on a schedule.
- **Export (offloading):** uploads and existing files are exported to external sources and used as external files afterwards, together with their image sizes and their original image. Local URLs in contents keep working. Everything can be restored as normal local files.
- **Migration** from other offload plugins (WP Offload Media, Advanced Media Offloader, Stowaway, Offload Media – Cloud Storage).
- **Private files**, credentials via constants, protection of copies of the website, REST API, abilities (AI/MCP), WP-CLI.

Text domain: `external-files-in-media-library`. Namespace: `ExternalFilesInMediaLibrary\`. Main file: `external-files-in-media-library.php`. Hook prefix: `efml_` (older hooks `eml_` are deprecated). Option prefix: `eml_` (newer ones `efml_`).

## Requirements

- PHP >= 8.2
- WordPress >= 6.2 (plugin header), tested up to 7.2; ClassicPress 2.0
- Composer (Mozart copies selected dependencies into `app/Dependencies/`)
- Node/npm for the block, the command palette and the admin scripts

## Setup

```bash
composer install          # also runs mozart compose + dump-autoload
npm install
npm run build
```

Or `task prepare` (Taskfile) or Ant in `build/` (see `readme.md`). Activate the plugin in a local WordPress after the build.

The autoloader is class-map based: after adding a class run `composer dump-autoload`.

## Commands

```bash
composer test-install     # WordPress test suite (see the arguments in composer.json)
composer test             # PHPUnit
composer test-multisite   # multisite tests
bin/test-servers.sh       # FTP, FTPS, SFTP and HTTP test servers (tests without them are skipped)
vendor/bin/phpstan analyse
vendor/bin/phpcs .
vendor/bin/phpcbf .
npx playwright test       # end-to-end tests in tests/Playwright/
task release -- x.y.z     # checks and the release ZIP (bin/build-release.sh)
```

## Layout

| Path | Role |
|------|------|
| `external-files-in-media-library.php` | Bootstrap, constants `EFML_PLUGIN`, `EFML_PLUGIN_VERSION` |
| `inc/constants.php` | Further constants (e.g. `EFML_CAP_NAME`) |
| `app/Plugin/` | Init, install, update, uninstall, settings, setup (quickstart), intro, roles and capability sets, schedules, log, statistics, WP-CLI (`Cli.php`), site copy protection (`Site_Copy.php`), tables for log and queue |
| `app/ExternalFiles/` | The external file (`File`, `Files`), file types, import, protocols, results, proxy, export (`Export`, `Export_Base`), image sizes (`Sizes`), local URLs (`Local_Urls`), restore (`Restore`), uploads with client-side sizes (`Client_Uploads`), migrations, private files, synchronization, background runs (`Import_Runs`), REST, abilities |
| `app/ExternalFiles/Extensions/` | Optional features of import, sync and export (queue, real import, dates, size limits, export by file type or size, ZIP, …) |
| `app/Services/` | Services (tiles in "Add external files"), each with protocol and optional export object (`app/Services/<Service>/Export.php`) |
| `app/ThirdParty/` | Integrations with other plugins (WooCommerce, Polylang, Elementor, folders plugins, Enable Media Replace, Instant Images, …) |
| `app/Dependencies/` | Mozart output (do not edit by hand) |
| `blocks/`, `src/`, `js/`, `admin/` | Block, command palette and admin scripts and styles |
| `docs/` | User and developer documentation in English and German (`*_de.md`); `docs/hooks.md` is generated |
| `tests/Unit/` | PHPUnit tests; `tests/Servers/` test servers; `tests/Playwright/` browser tests |
| `skills/`, `AGENTS.md` | Instructions for AI agents; not part of the release ZIP |

## Domain model (keep this straight)

```
External source (term of the taxonomy `edlfw_archive` of easy-directory-listing, meta `type` = service)
   │  term metas: credentials (encrypted), path, efml_export, efml_export_url, …
   │
   ├── import / synchronization ──► Attachment (external file)
   │                                   metas: eml_external_file (URL), eml_fields, eml_service, …
   │
   └── export ◄── Attachment (local upload) ──► Attachment (exported file)
                                                metas: eml_exported_file, efml_export_sources,
                                                efml_export_sizes, efml_local_file, efml_local_sizes, …
```

- An attachment is an external file if `Files::get_instance()->get_file( $id )->is_valid()`. Read every value through the `File` object, not from the metas.
- `Files::get_meta_keys()` lists every meta of the plugin. **Add every new meta there**: restore, switch to local and uninstall rely on it.
- An exported file keeps the knowledge of its former local path (`Local_Urls`: `efml_local_file`, `efml_local_sizes`). Local URLs in contents are replaced during the output; the database is never changed.
- Image sizes of exported images: `Sizes` with the meta `efml_export_sizes` (`main` term and per term the sizes incl. `_original`).
- Local working copies of exported images for image optimizers: `Working_Copies` with the meta `efml_working_copy` (`Import_Runs` types `working_copy` and `working_finish`). While a working copy is active, `get_attached_file()` returns the local file and the metadata contains `sizes`; `_wp_attached_file` stays the URL. Finishing hands the files to `Local_Work` (mode `optimize`): changed files are overwritten on the target under their names, new variants and backups (`Variants`, kind `backup`, optional `name`) are added. Optimizers report the end via `Image_Optimizer_Base::done()`.

## Principles (critical)

**1. Updates never break anything.** Nothing changed may cause errors for any user after an update. Check in every change and name it in the proposal: data and options of older versions, extensions in older versions (call new methods only via base class defaults or `method_exists()`), runs in cron, REST and WP-CLI before all services are loaded, saved settings must never be lost.

**2. Media are handled cleanly and the WordPress way.** A deleted file is deleted everywhere (locally, on every external source, sizes, variants, original, backups, cache, queue). Hundreds of uploads must all be exportable. Optimizations of other plugins are taken into account. Renaming by other plugins is possible.

**3. Privacy (GDPR) via the proxy.** The proxy prevents that external URLs are embedded in the website. Files and their sizes are never delivered directly from the external source unless the user disabled the proxy for this file type. Private files are always delivered via the proxy (or signed links), never with a public URL.

**4. Exported files are never lost.** Uninstall and reset restore exported files to their original path and never delete them on the external sources. A restore which does not fit into the time budget leaves the files exported.

**5. Copies of a website do nothing on external sources.** On a copy (`Site_Copy::is_blocked()`), nothing is exported or deleted on external sources and schedules are paused.

## Export (critical)

- Entry points: upload (`Export::export_file_by_upload()` on `wp_generate_attachment_metadata` priority 20), "Export existing files" (background run of `Import_Runs`, type `export`), bulk action, REST, ability, WP-CLI. All end in `Export::export_file()`.
- The **main source** is exported first. If it cannot be used (`Export::get_export_source_problem()`), nothing is exported and the file stays local.
- An export object (`Export_Base`) per service: `export_file()`, `delete_exported_file()`, `supports_folders()`, `supports_private_files()`, `file_exists_on_target()`, `supports_sizes()`, `export_size()`, `delete_size()`. Extensions implement the same class. New methods need a default in `Export_Base` (principle 1).
- Sizes are exported in `Sizes::export_sizes()` before `save_as_exported()`. Local files are removed in `Export::update_attachment_metadata()` only below the uploads directory.
- Uploads whose sizes are created in the browser (WordPress 7.1, `generate_sub_sizes=false`) wait for `/finalize` (`Client_Uploads`).
- Background export uses the queue (`eml_queue`) and `Import_Runs`.
- Open tasks: re-export after editing (P17), fast background export (P18), optimizers (P19), deletion everywhere (P34), reliable upload export with retries and delay when optimizers are active (P35), renaming (P36).

## Delivery (critical)

- URLs come from `File::get_url()`; `get_url( true )` is the unproxied URL for internal use only. `File::get_public_url()` maps exported non-private files to the public URL of their external source.
- `File::is_proxy_required()` is true for private files and non-HTTP URLs. Do not add a way around it.
- The proxy (`Proxy`, slug `emlproxy`) delivers files and sizes (`-WxH`) from its cache; `Sizes::cache_size()` loads an exported size, otherwise a size is created from the cached original.
- `srcset` for exported images comes from `Sizes::add_sizes_to_srcset_meta()` and `Sizes::add_srcset_to_content_image()`.

## Restore and uninstall

- `Restore` downloads exported files to their original path, creates the sizes again with their former names and removes all metas of the plugin. Runs in the background (`Import_Runs`, type `localize`).
- `Uninstall` and the reset in the settings restore first (`Restore::restore_until()`), never delete exported files, and abort the reset if not everything could be restored (unless forced).

## Coding conventions

- ABSPATH guard on PHP files; singletons via `get_instance()`; typed properties and return types.
- WordPress Coding Standards (`phpcs.xml.dist`), PHPStan level as configured in `phpstan.neon`.
- Every user-facing text translatable with the text domain, `translators:` comments for placeholders.
- **Hooks of other plugins only in `app/ThirdParty/`** (rule of the author): every use of a hook or function of another plugin goes into its own class `app/ThirdParty/<Plugin>.php` (registered in `ThirdPartySupport::get_third_party_support()`, active only if the plugin is active). The central classes offer public methods for it (e.g. `Local_Work::open()`), they never contain code for a specific plugin.
- Every new hook gets a DocBlock (`docs/hooks.md` is generated from them). Prefix `efml_`.
- Changelog: every change in `changelog.md` under the next version (Added, Changed, Fixed). No entry for a fix of a feature which was never released; change the entry of that feature instead.
- Documentation in `docs/*.md` and `docs/*_de.md` with the same content.

## Tests

- PHPUnit with the WordPress test suite. Base class `tests/externalFilesTests.php`; its `tear_down()` resets the cache of `File_Types`.
- Tests must also pass in a full run, not only alone: IDs are reused after the rollback of each test; constants defined by other tests (e.g. `EFML_URL_IMPORT_RUNNING`) persist in the process — such tests run with `@runTestsInSeparateProcesses`.
- Tests with FTP/SFTP/HTTP need the test servers (`bin/test-servers.sh`), otherwise they are skipped.
- Each fixed problem gets a test which fails without the fix. Compare a full run with the run before the change: no new failures.

## Do not

- Delete anything without asking the author first: files, branches, attachments, external sources, options, tables, the test database.
- Deliver an external URL where principle 3 requires the proxy.
- Delete exported files on uninstall, reset or on a copy of the website.
- Read or write the metas of an external file directly where `File` offers a method.
- Add a meta without adding it to `Files::get_meta_keys()`.
- Call a new method of an export object or service of an extension without a default in the base class.
- Change `vendor/threadi/*` or `app/Dependencies/` here; change the library in its own repository.
- Commit credentials or `build/build.properties`.
- Ship `AGENTS.md` or `skills/` in the plugin ZIP: `bin/build-release.sh` lists the shipped paths explicitly.

## Human docs

Product docs: https://plugins.thomaszwirner.de/en/external-files-in-the-media-library/
Documentation in this repository: `docs/`. Contribution process: `CONTRIBUTING.md`. Setup and release: `readme.md`.

---
name: external-files-in-media-library-code-review
description: Review the code of the WordPress plugin "External files in Media Library" (repository threadi/external-files-in-media-library) topic by topic with a fixed checklist, so that old problems do not come back. Use it before a release, after larger changes or when asked to check the plugin. Not for site operators.
---

# Code review of "External files in Media Library"

This checklist is for AI agents and humans who review the **code** of the plugin. Nobody holds the whole plugin in mind at once, and a reviewer who reads by topic ignores what seems unrelated. So the review runs **one topic after the other**. Each topic names the files to read completely, the questions to answer and the problems which happened before.

The development instructions are in `AGENTS.md` in the root of the repository. Read them first.

## How to review

1. Work through the topics in the order of this file. Do not merge topics; do not skip a topic because an earlier one looked fine.
2. For each topic, read the named files **completely**, then follow every call into other files as far as it is needed to answer the questions. Search for further places with the given search terms (`grep`), as new code often lands outside the named files.
3. Answer each question with "ok", "finding" or "not applicable", and for each "ok" name the place in the code which shows it.
4. Report a finding only after you have checked it: name the file and line, the concrete situation (input, settings, state) and what goes wrong. Separate certain findings from suspicions.
5. Check every finding against the two principles of the plugin (below). A change which fixes a finding must keep both.
6. At the end, list the findings by severity: data loss or security, broken function, wrong behaviour in rare cases, code quality.

Use a WordPress test site with the plugin and its tests (`composer test`) where possible, and reproduce a finding before you report it as certain.

## The two principles

**Principle 1 – updates never break anything.** Nothing changed in the plugin may cause errors for any user after an update. For every change check:

- data and options of older versions (missing options, old formats, metas which did not exist before),
- extensions (AWS S3, Google Cloud Storage, Google Drive, WebDAV) in older versions, which do not know new methods or hooks,
- runs in cron, REST and WP-CLI before all services are loaded,
- saved settings, which must never be lost.

**Principle 2 – media are handled cleanly and the WordPress way.**

- A file which is deleted is deleted everywhere: locally, on every external source it was exported to, with its sizes, its variants (WebP/AVIF), the original image, the backups of the image editor, the proxy cache and the queue entries.
- When hundreds of files are uploaded, all of their files must be exportable (no file lost by time limits, rate limits or parallel requests; failures are repeated).
- Optimizations by other plugins (compressed files, WebP/AVIF) are taken into account.
- Renaming files by other plugins is possible.

## Topics

### 1. Update safety

Files: `app/Plugin/Update.php`, `app/Plugin/Install.php`, `inc/constants.php`, `app/Plugin/Init.php`, `app/ExternalFiles/Import_Runs.php` (table versions), `app/Plugin/Tables/Queue.php`, every class which reads an option or meta added in the current version.

Search: `get_option(`, `get_post_meta(`, `get_term_meta(`, `dbDelta`, `method_exists`, `EFML_UPDATE_RUNNING`.

- Does every new option have a default which matches the behaviour before the update? Does code cope with `false` for a missing option (e.g. `Local_Urls::is_active()` restores a missing option from the data)?
- Are old values migrated in `Update.php`, and is the migration safe to run twice?
- Do table changes have their own DB version and run `dbDelta`? A change of a `UNIQUE KEY` into a `PRIMARY KEY` needs its own `ALTER TABLE`.
- Does the main plugin call new methods of the extensions only after `method_exists()` or via a base class with a default (`Export_Base::supports_sizes()`)?
- Does a hook which used to fire still fire with the same arguments? Renamed hooks need `apply_filters_deprecated()` / `do_action_deprecated()`.
- Does anything depend on services or extensions being loaded (cron, REST, WP-CLI run early)? Is the result kept when a service is missing for a moment (e.g. export targets stay saved, `Export::get_export_source_problem()`)?
- Are the changelog entries in `changelog.md` correct (no entry for features which were never released)?

### 2. Bootstrap, hooks and their conditions

Files: `external-files-in-media-library.php`, `app/Plugin/Init.php`, `app/ExternalFiles/Files.php::init()`, `app/ExternalFiles/Export.php::init()`, `app/Services/Services.php`, `app/ThirdParty/ThirdPartySupport.php`.

- Which hooks are only registered under a condition (e.g. "export enabled")? For each of them: what happens to files which were handled while the condition was true, after it became false? Known problem: `delete_attachment` → `Export::delete_exported_file()` is only registered while the export is enabled, so exported files stay on the external source when the export is switched off (P34).
- Are singletons initialised once? Are hooks added twice (e.g. in tests or after `switch_to_blog()`)?
- Do priorities fit the order other plugins and WordPress need (`wp_generate_attachment_metadata` priority 20 for the export, optimizers before or after)?
- Is nothing heavy done on every request (database queries, remote requests) outside the code paths which need it?

### 3. Permissions and security

Files: `app/Plugin/Roles.php`, `app/Plugin/CapabilitySets*`, every `admin_action_*`, `wp_ajax_*`, REST route and ability, `app/ExternalFiles/Proxy.php`, `app/Services/Local.php`, `app/Plugin/Helper.php`, credential handling (`crypt-for-wordpress`).

Search: `admin_action_`, `wp_ajax_`, `register_rest_route`, `permission_callback`, `check_admin_referer`, `wp_verify_nonce`, `current_user_can`, `$_GET`, `$_POST`, `$_REQUEST`, `filter_input`, `file_get_contents`, `wp_remote_`, `unserialize`.

- Does every action which changes something check a nonce **and** a capability (`EFML_CAP_NAME` or the more specific one)? Is the capability checked for the object (e.g. `edit_post` for an attachment)?
- Are all inputs sanitized and all outputs escaped (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_post`)?
- Can a URL import reach internal addresses or files it must not (SSRF, `file://`, path traversal)? Does the local service stay inside its base directory (`efml_file_base`)?
- Does the proxy deliver only files of the media library, with the right MIME type, and never a file outside its cache directory?
- Are credentials stored encrypted, never logged, never returned by REST or abilities, and never sent to AI services?
- Is every encrypted value bound to a fixed context (no ID, nothing secret), and does a value saved by an older version without context stay readable?
- Is nothing deleted or exported on a copy of the website (`Site_Copy::is_blocked()`)?

### 4. The external file object

Files: `app/ExternalFiles/File.php`, `app/ExternalFiles/Files.php`, `app/ExternalFiles/File_Types.php`, `app/ExternalFiles/File_Types/*`, `app/ExternalFiles/File_Types_Base.php`.

- Does `Files::get_meta_keys()` list **every** meta of the plugin (also new ones like `efml_export_sizes`, `efml_local_sizes`, `efml_export_after_finalize`)? Restore, switch to local and uninstall rely on this list.
- `File::get_url()`, `get_url( true )`, `get_public_url()`, `is_proxy_required()`: is the proxy used whenever it is required (private files, non-HTTP URLs) and is a public URL only used when the proxy is explicitly disabled for the file type?
- Is the cache of `File_Types` per attachment ID invalidated when an attachment changes or is deleted (IDs can be reused)?
- `Files::get_attached_file()`: what does it return for exported, locally saved, private and unavailable files, and what do callers of `get_attached_file()` (WordPress core, other plugins) do with a URL instead of a path?

### 5. Import

Files: `app/ExternalFiles/Import.php`, `app/ExternalFiles/Protocols.php`, `app/ExternalFiles/Protocols/*`, `app/ExternalFiles/Protocol_Base.php`, `app/ExternalFiles/Results*`, `app/ExternalFiles/Forms.php`, `app/ExternalFiles/ImportDialog.php`, `app/ExternalFiles/Extensions/*` (Real_Import, Dates, Specific_Date, Queue, File_Size_Limits, Jobs, Zip).

- Are allowed MIME types and size limits checked before anything is saved?
- Is a duplicate detected (same URL) and reported, not imported twice?
- Is a temporary file always removed, also on errors (`cleanup_temp_file()`)?
- Does a failed URL among many stop the others? Is the reason shown and logged?
- Do the constants `EFML_URL_IMPORT_RUNNING`, `EFML_SYNC_RUNNING` affect only the import (they also stop the export of uploads in the same request)?

### 6. Synchronization

Files: `app/ExternalFiles/Synchronization.php`, `app/ExternalFiles/SynchronizationDialog.php`, `app/Plugin/Schedules/Synchronization.php`, `app/ExternalFiles/Extensions/Sync_By_*.php`.

- Are files deleted only when they really vanished on the external source (no deletion after a failed listing)?
- Does the synchronization never delete exported files or files of another external source?
- Are schedules paused on a copy of the website?

### 7. Delivery, proxy and privacy (GDPR)

Files: `app/ExternalFiles/Proxy.php`, `app/ExternalFiles/File_Types/Image.php` and the other file types, `app/ExternalFiles/Private_Files.php`, `app/ExternalFiles/Sizes.php` (`get_delivery_url()`, `cache_size()`), `app/ExternalFiles/Local_Urls.php`.

Rule of the author: the proxy prevents that external URLs are embedded in websites, which matters for the GDPR. Neither files nor their sizes may be loaded directly from the external source unless the user disabled the proxy for this file type. Private files are always delivered via the proxy.

- Is there any output (content, `srcset`, `wp_get_attachment_url`, REST, feeds, Open Graph of SEO plugins) which contains the external URL although the proxy is enabled?
- Do sizes follow exactly the same rule as the images?
- Does the proxy answer HEAD requests and redirects correctly, set cache headers and handle missing files without a fatal error?
- Is the proxy cache cleaned when the file is deleted or changed?

### 8. Export (offloading)

Files: `app/ExternalFiles/Export.php`, `app/ExternalFiles/Export_Base.php`, `app/ExternalFiles/ExportDialog.php`, `app/ExternalFiles/Client_Uploads.php`, `app/Services/*/Export.php`, `app/ExternalFiles/Extensions/Export_By_*.php`.

- Main source first, then the other sources; if the main source cannot be used, nothing is exported (the file stays local and a hint names the reason).
- Are the local files deleted only after **all** files (main file, sizes, original image, variants) are on every external source? A failed size must not be deleted locally.
- Are file names unique on the external source (`check_for_exported_filenames()`), also with folders by year and month?
- Does the export of an upload wait for everything which is still created after `wp_generate_attachment_metadata` (client-side media processing of WordPress 7.1, optimizers, renamers)?
- Is a failed export repeated (queue) instead of only logged?
- Background export: are entries processed in order and fast enough for hundreds of uploads (P18)?
- Does `update_attachment_metadata()` only delete files below the uploads directory and never a file on the external source (it once deleted the exported original of a local target)?

### 9. Image sizes and srcset

Files: `app/ExternalFiles/Sizes.php`, `Files::image_downsize()`, `Files::get_image_srcset()`, `Files::check_srcset_meta()`.

- Are all sizes of the metadata exported, also `original_image` and the `-scaled` image? Are sizes larger than the original absent on purpose (WordPress does not create them)?
- Does `find_size()` pick the right size for named sizes, exact dimensions and other dimensions?
- Does the `srcset` work for content images with local URLs, external URLs and proxy URLs, and only with URLs allowed by topic 7?
- Are sizes deleted on every external source together with the file?

### 10. Local URLs in contents

Files: `app/ExternalFiles/Local_Urls.php`, `app/ExternalFiles/Migrations.php` (`replace_urls()`).

- Are local URLs (also of sizes and of the original) replaced in every output: content, excerpt, widgets, blocks, REST, feeds?
- Is the database content never changed?
- Does the replacement stay active while any exported or migrated file exists (`efml_local_urls` option, self-healing)?

### 11. Deletion everywhere (principle 2)

Files: `Export::delete_exported_file()`, `Sizes::delete_sizes()`, `Files::delete_file_from_cache()`, `Files::mark_attachment_deletion()`, `app/ExternalFiles/Restore.php`, `app/Plugin/Uninstall.php`, `app/ExternalFiles/Synchronization.php` (deletion), `app/Plugin/Tables/Queue.php`.

Search: `delete_attachment`, `wp_delete_attachment`, `wp_delete_file`, `delete_exported_file`, `pre_delete_attachment`.

Build a table for these cases and check that nothing stays behind: export on/off, export of the source on/off, service extension active/inactive, "keep local files" on/off, private/public, with/without sizes, edited image (backup sizes), WebP/AVIF variants, deletion via media library, bulk action, REST, WP-CLI, synchronization, uninstall, reset.

- Is every file deleted on every external source the file was exported to, independent of the current export settings? Known problems: D1 local copies stay, D2 files stay when the export is off or the service is missing, D3 failures are only logged (P34).
- Is a failed deletion repeated later instead of being forgotten with the attachment?
- Exceptions which must stay: copy of the website, uninstall and reset (exported files are restored, never deleted), synchronization runs.

### 12. Background runs, queue and time limits

Files: `app/ExternalFiles/Import_Runs.php`, `app/ExternalFiles/Extensions/Queue.php`, `app/Plugin/Schedules/*`, `app/Plugin/Tables/Queue.php`, `app/Plugin/Cli.php`.

- Does every run respect a time budget and continue in the next request or cron run?
- Are entries of deleted attachments removed or skipped?
- Do runs work without a logged-in user (cron, WP-CLI)? Services which need a user (e.g. the local service, F7) must still be usable.
- Can two runs of the same type run at the same time? Is a run cancelled cleanly?
- Is the progress in the admin bar correct and does it end?

### 13. WordPress core flows and other plugins

Files: `app/ThirdParty/*`, `Export::export_file_by_upload()`, `Export::update_attachment_metadata()`, `Client_Uploads`, `Files::get_attached_file()`.

Check each flow with an exported image and with an image waiting for its export:

- upload in the media library, in the block editor (client-side media processing since WordPress 7.1: `/sideload`, `/finalize`), via REST, via WP-CLI;
- big images (`-scaled`, `original_image`), HEIC, PDF previews;
- image editor (crop, rotate, scale, restore original), `wp media regenerate`, Regenerate Thumbnails (P17);
- optimizers (Imagify, ShortPixel, Smush, EWWW, Modern Image Formats): do they find the local file, and are their files exported (P19, P35)? For already exported images: are working copies created, finished after the optimizer reports its end, and are backups exported (P19b)?
- renaming plugins (Media File Renamer, Phoenix Media Rename) and Enable Media Replace (P36);
- page builders and SEO plugins which read `get_attached_file()` or the metadata.
- Is every hook or function of another plugin used only in its own class in `app/ThirdParty/` (rule of the author)? Search the other folders for prefixes of other plugins (e.g. `mfrh_`, `woocommerce_`, `elementor/`, `wpml_`, `pll_`).

### 14. Restore, switch to local, deactivation, uninstall and reset

Files: `app/ExternalFiles/Restore.php`, `File::switch_to_local()`, `app/Plugin/Uninstall.php`, `app/Plugin/Settings.php` (reset), `Import_Runs` (type `localize`).

- Are exported files restored to their **original** path with their sizes and former names, so contents work without the plugin?
- Are exported files never deleted on uninstall or reset (F8)?
- Does the restore keep or delete the copies on the external sources as chosen?
- Does a restore which does not fit into the time budget leave the files exported (no data loss)?

### 15. Migration from other offload plugins

Files: `app/ExternalFiles/Migrations.php`, `app/ExternalFiles/Migrations/*`.

- Are the data of the other plugin kept, and can the migration be undone?
- Are old URLs in contents still delivered?

### 16. Services and the API for extensions

Files: `app/Services/Service_Base.php`, `app/Services/Services.php`, every service, `app/ExternalFiles/Export_Base.php`, `app/ExternalFiles/Protocol_Base.php`, and the extensions (`external-files-from-aws-s3`, `external-files-from-google-cloud-storage`, `external-files-from-google-drive`, `external-files-from-webdav`).

- Does every service implement the same contract (export, delete, sizes, private files, folders, `file_exists_on_target()`)? Does a service which cannot do something say so (`supports_*()` returns false) instead of failing?
- Do all services behave the same at the same place (F7: the local service was not available without a logged-in user)?
- Are credentials from constants respected (`Services::is_source_blocked_by_constants()`)?
- Are streams used for large files instead of loading them into memory (P12a, P22)?

### 17. Private files

Files: `app/ExternalFiles/Private_Files.php`, `File::is_private()`, the export objects of the services which support private files, `app/ThirdParty/WooCommerce.php`.

- Are private files always delivered via the proxy or via signed links with limited validity, never with a public URL?
- Do sizes of private files follow the same rule?

### 18. Multisite

Files: `app/Services/Multisite*`, `app/Plugin/Network/*`, every `switch_to_blog()`.

- Are options and tables per site where they must be, and network-wide where they must be?
- Is `restore_current_blog()` always called?

### 19. REST API, abilities and WP-CLI

Files: `app/ExternalFiles/Rest.php`, `app/ExternalFiles/Abilities.php`, `app/Plugin/Cli.php`, `docs/rest*.md`, `docs/abilities*.md`, `docs/cli*.md`.

- Does every function of the backend that should be automatable exist with the same rules in REST, abilities and WP-CLI (same checks, same results)?
- Do read-only abilities really change nothing?
- Is the documentation in sync with the code (parameters, names)?

### 20. Settings, setup, notices and help

Files: `app/Plugin/Settings.php`, `app/Plugin/Setup.php`, `app/Plugin/Intro.php`, `app/Plugin/Commands.php`, `app/Plugin/Admin/*`, `vendor/threadi/easy-settings-for-wordpress` (not to be changed here).

- Do labels, descriptions and help texts describe what really happens (e.g. what happens to exported files on uninstall)?
- Is every notice shown where the user sees it (not behind the intro) and dismissible?
- Are settings which depend on others shown and saved consistently?

### 21. Log and statistics

Files: `app/Plugin/Log.php`, `app/Plugin/Statistics.php`, `app/Plugin/Tables/Logs.php`.

- Does the log name the file and the reason for every failure, without secrets?
- Is the log size limited?

### 22. Translations and texts

Search: `__(`, `_e(`, `esc_html__(`, `_n(`, `sprintf(`.

- Is every text translatable with the text domain `external-files-in-media-library`, with `translators:` comments for placeholders?
- Are new or changed texts listed for translate.wordpress.org?
- Are the documentation files available in English and German (`docs/*.md`, `docs/*_de.md`) with the same content?

### 23. Performance

- Queries on `meta_value` without index (P21), queries in loops, missing `no_found_rows`/`fields => ids`, unbounded queries.
- Autoloaded options with large values.
- Remote requests on normal page views (outside the proxy).

### 24. Tests and tools

Files: `tests/`, `phpstan.neon`, `phpcs.xml.dist`.

- Does every fixed problem have a test which fails without the fix?
- Do tests clean up (`tear_down()`, files, options, `File_Types` cache) and work in a full run, not only alone? Tests which depend on constants defined by other tests run in separate processes.
- `composer test`, `vendor/bin/phpstan analyse`, `vendor/bin/phpcs .` without new findings.

## Known problems that came back before

Check these explicitly, they are easy to break again:

- F7: a service was only available with a logged-in user, so cron and WP-CLI could not export.
- F8: uninstall and reset deleted exported attachments.
- F10: the export of a block editor upload ran before the browser had created the sizes.
- P15: the replacement of local URLs was inactive because an option was missing after an update.
- P16: the deletion of local files after an export deleted the exported original on a local target; the proxy was forced for local targets although it was disabled.
- D1–D3, B1–B3, O1–O3, R1–R2: see the analysis "Grundsatz 2" in the ToDo of the author (deletion, uploads in bulk, optimizers, renaming).

---
name: external-files-in-media-library-developer
description: Help a developer who extends the WordPress plugin "External files in Media Library" from their own plugin or theme - add files by code, react to imports, exports and restores, change delivery and proxy, add a service or protocol, use the REST API, abilities and WP-CLI. Not for working on the repository of the plugin itself (see AGENTS.md there) and not for site operators.
---

# External files in Media Library (developers)

Guidance for AI assistants helping **developers who use or extend the plugin from their own code** (a plugin, a theme, an mu-plugin, a deployment script).
For changes to the plugin itself read `AGENTS.md` in its repository. For helping site operators use the skill `external-files-in-media-library`.

Repository: https://github.com/threadi/external-files-in-media-library
Documentation: `docs/` in the repository. All hooks with their parameters: `docs/hooks.md` (generated from the code, so it is complete). Prefer the code and these files over assumptions; hooks and methods not listed there do not exist.

## Basics

- Namespace `ExternalFilesInMediaLibrary\`, all main classes are singletons: `Files::get_instance()`, `Export::get_instance()`, `Services::get_instance()`, …
- Hook prefix `efml_`. Hooks with the old prefix `eml_` still work but are deprecated since 5.0.0.
- Capability to manage external files: `eml_manage_files` (constant `EFML_CAP_NAME`).
- Requires PHP 8.2 and WordPress 6.2 or newer. Check that the plugin is active before using its classes:

```php
if ( ! class_exists( '\ExternalFilesInMediaLibrary\ExternalFiles\Files' ) ) {
    return;
}
```

## The external file

```php
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

$file = Files::get_instance()->get_file( $attachment_id );
if ( $file->is_valid() ) {          // the attachment is an external (or exported) file
    $url        = $file->get_url();  // URL for output: proxy URL or public URL, as configured
    $is_private = $file->is_private();
    $is_local   = $file->is_locally_saved();
}
```

- Use the methods of `File` instead of reading the post metas; their format changes between versions.
- `get_url()` respects the privacy rules of the plugin: files are delivered via the proxy of the website unless the site operator disabled it for the file type, and private files always via the proxy. `get_url( true )` returns the unproxied URL and is only meant for internal use (downloads, checks). **Never print it in the frontend**: it would bypass the privacy setting of the site (GDPR).
- WordPress functions keep working: `wp_get_attachment_url()`, `wp_get_attachment_image()`, `wp_get_attachment_image_src()` and the `srcset` use the URLs of the plugin.
- `get_attached_file()` returns the external URL (not a path) for external files which are not saved locally. Code which expects a local path must check `file_exists()` and handle this case.

## Add files by code

```php
// returns the attachment ID (> 0) if added or already present, 0 on failure
$attachment_id = apply_filters( 'efml_add_url', 0, 'https://example.com/sample.pdf', 'login', 'password' );
```

See `docs/Add_File_by_Hook.md`. For many URLs or directories use the REST API, the abilities or WP-CLI (`wp eml import`), which run in the background and report the reason of every failed URL.

## React to what the plugin does

Look up the exact parameters in `docs/hooks.md`. Commonly used:

| Hook | Type | Use |
|------|------|-----|
| `efml_prevent_import`, `efml_prevent_file_import` | filter | Stop the import of a URL or file |
| `efml_file_import_title`, `efml_file_import_attachment` | filter | Change the attachment before it is saved |
| `efml_after_file_save` | action | Run after an external file was saved |
| `efml_file_types`, `efml_supported_mime_types` | filter | Add file types / MIME types |
| `efml_prevent_export` | filter | Return `true` to keep a file local (arguments: `false`, the `File` object) |
| `efml_export_before` | action | Run before a file is exported (argument: attachment ID) |
| `efml_export_object` | filter | Replace the service object used for an export |
| `efml_file_public_url` | filter | Change the public URL of an exported file (CDN) – only used when the proxy is disabled |
| `efml_file_restored` | action | Run after an exported file was restored as local file |
| `efml_restore_download_urls` | filter | Offer further URLs or paths to download a file for its restore |
| `efml_private_file_signed_url` | filter | Signed links for private files |
| `efml_protected_meta_keys` | filter | Metas which must be kept for external files |
| `efml_file_base` | filter | Base directory of the local service (only files below it are allowed) |
| `efml_proxy_slug`, `efml_proxy_path` | filter | Path of the proxy |
| `efml_abilities`, `efml_abilities_enabled`, `efml_abilities_mode` | filter | Change the abilities |
| `efml_services_support`, `efml_protocols`, `efml_extensions`, `efml_third_party_support` | filter | Register own services, protocols, extensions, integrations |

## Add a service

A service is a tile in Media → "Add external files" and the base of external sources.

- Create a class which extends `ExternalFilesInMediaLibrary\Services\Service_Base` (singleton with `get_instance()`) and add its class name with the filter `efml_services_support`.
- For reading files: a protocol which extends `ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base`, registered with `efml_protocols` (methods like `is_url_compatible()`, `get_url_infos()`, `get_temp_file()`, `check_availability()`, `get_file_list()`).
- For the export: an object which extends `ExternalFilesInMediaLibrary\ExternalFiles\Export_Base`, returned by `get_export_object()` of the service. Implement `export_file()` and `delete_exported_file()`; for image sizes `supports_sizes()`, `export_size()`, `delete_size()`; optionally `supports_folders()`, `supports_private_files()`, `file_exists_on_target()`. Methods you do not override keep the defaults of the base class ("not supported").
- Credentials: declare the fields of the service; they are stored encrypted and can be defined as constants (`EFML_<SERVICE>_<FIELD>`, see `docs/constants.md`).
- Use the extensions as examples: AWS S3, Google Cloud Storage, Google Drive and WebDAV on GitHub (`threadi/external-files-from-*`).
- Large files: stream uploads and downloads, do not load the whole file into memory.
- Your service must also work without a logged-in user (cron, WP-CLI) and on a copy of the website (the plugin blocks writing there, your code must not bypass it).

## REST API, abilities, WP-CLI

- REST: `/wp-json/efml/v1/file/` (POST add, GET check, DELETE remove), with an application password. See `docs/rest.md`.
- Abilities (WordPress 6.9+, also via the MCP Adapter): prefix `external-files-in-media-library/`, run via `/wp-json/wp-abilities/v1/abilities/external-files-in-media-library/<name>/run`. Read only by default; changing abilities need the mode "all" in the settings or the filter `efml_abilities_mode`. See `docs/abilities.md`.
- WP-CLI: `wp eml` (import, delete, check, export, export_sizes, localize, migration, site, reset_plugin, …). See `docs/cli.md`.

## Rules for code that uses the plugin

**Do**

- Go through `File` and the hooks; check `is_valid()` before treating an attachment as external.
- Keep the privacy rule: output only `get_url()` or WordPress' own functions, never the unproxied URL.
- Handle `get_attached_file()` returning a URL.
- Test with the proxy enabled and disabled, with private files and on a copy of the website.

**Don't**

- Write the metas of the plugin directly (`eml_external_file`, `eml_exported_file`, `efml_export_sizes`, …).
- Delete files on external sources yourself; delete the attachment with `wp_delete_attachment()` and let the plugin remove its files.
- Rely on classes or methods which are not public or not documented; they can change in any release.
- Delete anything (attachments, external sources, files) in examples or scripts without the developer confirming it first.

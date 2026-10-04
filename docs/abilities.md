# Abilities API (AI / MCP)

The plugin provides its functions as abilities via the Abilities API of WordPress
(available since WordPress 6.9). AI agents can use them to manage external files in
the media library, e.g., via the MCP Adapter of WordPress.

All abilities

* use the prefix `external-files-in-media-library/`
* are assigned to the category `efml`
* are available via REST API (`show_in_rest`) and marked as public for the MCP Adapter
* return a `WP_Error` with a readable reason if something fails

## Abilities

### Files

| Ability | Description | Input |
|---|---|---|
| `add` | Add a single URL, returns the attachment ID | `url`, `login`, `password` |
| `import` | Import multiple URLs or directories, returns the files and the reason for each failed URL | `urls`, `source_id`, `login`, `password`, `queue`, `real_import`, `use_dates` |
| `get-file` | Return the data of an external file | `attachment_id` or `url` |
| `list-files` | Paginated list of external files | `source_id`, `mime_type`, `hosting`, `availability`, `search`, `url_contains`, `page`, `per_page` |
| `delete` | Delete an external file from the media library | `attachment_id` or `url` |
| `check-availability` | Check whether files are still reachable; without a file all files are checked in batches | `attachment_id` or `url`, `limit`, `offset` |
| `switch-to-local` | Save an external file locally | `attachment_id` or `url` |
| `switch-to-external` | Use the external URL of a locally saved file again | `attachment_id` or `url` |
| `reset-thumbnails` | Delete the generated thumbnails of a file | `attachment_id` or `url` |
| `reset-proxy-cache` | Delete the proxy cache | - |

### Information

| Ability | Description | Input |
|---|---|---|
| `get-statistics` | Amount and size of the files by hosting, service and file type | - |
| `get-log` | Entries of the log, e.g., to find the reason for a failed import | `url`, `search`, `state`, `limit`, `offset` |
| `list-services` | Usable services with the fields they need | - |

### External sources and synchronization

| Ability | Description | Input |
|---|---|---|
| `list-sources` | Saved external sources of the actual user | `service` |
| `add-source` | Save a directory with its credentials as external source, the connection is tested before | `service`, `directory`, `fields` |
| `delete-source` | Delete an external source | `source_id` |
| `list-directory` | Files and subdirectories of an external source without importing them | `source_id`, `directory` |
| `sync-source` | Run the synchronization of an external source now | `source_id` |
| `get-sync-status` | Synchronization state, last run and next run of the external sources | `source_id` |
| `delete-synced-files` | Delete all synchronized files of an external source from the media library | `source_id` |

### Queue

| Ability | Description | Input |
|---|---|---|
| `queue-add` | Add URLs to the queue instead of importing them directly | `urls`, `source_id`, `login`, `password`, `real_import`, `use_dates` |
| `queue-status` | Amount of waiting and failed URLs and the newest entries | - |
| `queue-process` | Import the next URLs from the queue now | - |
| `queue-cleanup` | Remove failed URLs (`errors`) or every entry (`all`) | `mode` |

### Export

| Ability | Description | Input |
|---|---|---|
| `export-file` | Export a local file to the external sources which are enabled for the export | `attachment_id` |
| `set-export-state` | Enable or disable an external source as export target | `source_id`, `enabled` |

## Permissions

Each ability checks the capabilities which are also used in the backend:

* All abilities require the capability to manage external files (`eml_manage_files`).
* `get-log` additionally requires `manage_options`.
* `sync-source` and `delete-synced-files` require the capability for the synchronization.
* `export-file` and `set-export-state` require the capability for the export.
* `add-source` and `delete-source` require the capabilities to manage and to delete external sources.
* `import` with `real_import` requires the capability for the real import.
* `delete` and `export-file` additionally check whether the user is allowed to delete or edit the attachment.
* External sources saved by other users can only be used by administrators (or if the setting to show all sources is enabled).
* The service of an external source must be allowed for the actual user.

## Credentials

Credentials are never returned by any ability.

To prevent that credentials are part of the conversation with an AI agent, save them once as
external source (in the backend or with `add-source`) and use its `source_id` for `import`,
`queue-add`, `list-directory` and `sync-source`. The credentials are saved encrypted.

## Usage via REST API

The abilities can be run via the REST API of WordPress:

`/wp-json/wp-abilities/v1/abilities/external-files-in-media-library/<name>/run`

* Read-only abilities require `GET`, the input is sent as query parameter `input`.
* `delete`, `delete-source`, `delete-synced-files` and `queue-cleanup` require `DELETE`.
* All other abilities require `POST` with the input as JSON: `{"input":{"url":"https://example.com/file.pdf"}}`

## Customize the abilities

Use the hook `efml_abilities` to remove or change abilities before they are registered:

```
add_filter( 'efml_abilities', function( $abilities ) {
    unset( $abilities['delete-source'], $abilities['delete-synced-files'] );
    return $abilities;
});
```

## Notes

* With `source_id` the values returned as `url` by `list-directory` are used unchanged for `import` and `queue-add`,
  even if they are no real URLs (e.g., `DropBox/my file.pdf`). Paths relative to the directory of the external source
  are possible, too.
* The import and the synchronization of files from ZIP archives depend on the form in the backend and are not
  possible via abilities. The services YouTube and WordPress REST API read the used service from this form, too,
  so their imports might not work via abilities.
* The services are initialized for the actual user before an ability is executed, so services like DropBox are
  also usable if the user is not known during the initialization of WordPress (e.g., via an MCP server).
* Services which are connected via OAuth (e.g., DropBox) must be connected in the backend.
* The synchronization and the export must be enabled in the plugin settings to use their abilities.
* It is not possible to delete automatically synchronized files with `delete`. Use `delete-synced-files` for this.

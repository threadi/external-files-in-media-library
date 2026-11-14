# WP CLI commands

The plugin provides several WP CLI commands.

## Call up list

`wp eml`

## Import

`wp eml import [URL] [--login=<login> --password=<password>] [--queue] [--real_import] [--use_dates] [--use_specific_date=<value>]`

Parameter:

* <URL> - URL to import in media library.
* [--login=<value>] - Set authentication login to use for any added URL.
* [--password=<value>] - Set authentication password to use for any added URL.
* [--queue] - Adds the given URL(s) to the queue.
* [--real_import] - Import files from URLs as real files, not linked to external URL.
* [--use_dates] - Use the dates of the external.
* [--use_specific_date=<value>] - Use specific date for each file

## Delete

`wp eml delete [<URLs>]`

Deletes the given URLs from media library.

## Clear log

`wp eml clear_log`

## Check availability

`wp eml check`

## Reset plugin

The following command first executes the commands for deleting the plugin. An installation is then carried out.

Depending on your settings in the plugin, existing files may be removed from your media database.

`wp eml reset_plugin`

This function can also be performed in the backend under Settings > External files in the media library > Advanced.

## Add external source

`wp eml add_external_source <Name> --type=<value> --fields=<value>`

Parameter:
* <Name> - The name to use.
* [--type=<value>] - Set the type, e.g. "ftp".
* [--fields=<value>] - Set the configuration as JSON-string in one line. Format is depending on the used type.

## Delete external source

`wp eml delete_external_source <Names>`

Parameter
* <Names> - The names of the external sources to delete.

## Change export state

`wp eml change_export_state <Names> [--enable] [--disable]`

Parameter:
* <Names> - List of names of external sources to change.
* [--enable] - Marker to enable the given names for export.
* [--disable] - Marker to disable the given names for export.

## Cleanup queue

This will remove error URLs from the queue.

`wp eml cleanup_queue`

## Cleanup lists with URLs

This will delete the CSV and XML files, which have been uploaded or downloaded via the CSV or XML service and have not been used for the configured days. The last downloaded version of a URL saved as external source is always kept.

`wp eml cleanup_lists`

Optional parameters:

* `--service=csv` or `--service=xml` to only cleanup the files of one service
* `--days=3` to delete files, which have not been used for 3 days (instead of the value from the settings)
* `--force` to run the cleanup even if it is disabled in the settings
* `--dry-run` to only show the files, which would be deleted

## Clear queue

This will delete every entry in the queue.

`wp eml clear_queue`

## Export media files

This will export all local files in the media library, which are not exported yet and whose mime type is allowed in the plugin settings, to the external sources with enabled export. It is the same export as via the button "Export existing files" in the export settings, but all files are exported directly. Files, which could not be exported, stay local files, are marked in the media library and are listed as errors in the queue.

`wp eml export`

## Main website or copy

Shows whether this website is the main website or a copy of it (e.g. a staging site). On a copy, files are not exported to or deleted on external sources, and scheduled synchronizations are paused.

`wp eml site status`

Set this website as main website, e.g. after moving the live site to a new domain or in your deployment:

`wp eml site main`

Confirm this website as copy (the protection stays active, the hint in the backend is hidden):

`wp eml site copy`

## Migration from other offload plugins

Show the state of the migration of files from other offload plugins (see [migration](migration.md)):

`wp eml migration status`

Continue a running migration without time limit, e.g. if WP-Cron is disabled:

`wp eml migration run`

Revert the migration of all files or of a single attachment. The files are used with their local files and the data of the other plugin again, activate the other plugin afterwards:

`wp eml migration revert`

`wp eml migration revert --attachment=42`

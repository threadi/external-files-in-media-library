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

## Clear queue

This will delete every entry in the queue.

`wp eml clear_queue`

## Export media files

This will export all not external files in media library to the for export enabled external sources.

`wp eml export`

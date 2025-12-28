# WP CLI commands

The plugin provides a number of WP CLI commands.

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

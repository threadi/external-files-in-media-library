# WP CLI commands

The plugin provides a number of WP CLI commands.

## Call up list

`wp eml`

## Import

`wp eml import [URLs] [--login=<login> --password=<password>]`

* You can specify any number of URLs whose protocols are supported by the plugin here.
* The optional access data is used for each of these URLs.

## Delete

`wp eml delete`

* There are no parameters to influence the command.

## Clear log

`wp eml clear_log`

## Check availability

`wp eml check`

## Reset plugin

The following command first executes the commands for deleting the plugin. An installation is then carried out.

Depending on your settings in the plugin, existing files may be removed from your media database.

`wp eml reset_plugin`

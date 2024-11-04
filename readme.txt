=== External files in media library ===
Contributors: threadi
Tags: external files, media library, media
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 2.0.0

Add external files to your media library to link or embed them in your website. They will be integrated as if they were locally available.

== Description ==

Add one or more files under Media > "Add new media file". You can use the files in all places where the media library is used.

== Mass-Import ==

You can import complete directories from any of the supported TCP protocols. Just enter the directory as path to import
and the plugin will import any supported files from it.

== TCP Protocols ==

You can use the following TCP-protocols to import external files in your media library:

* `http://`
* `https://`
* `ftp://`
* `ftps://`
* `sftp://`
* `ssh://`
* `file://`

Some of them require credentials, for http it is optional.

== Checks ==

The plugin checks for you automatically on a regular basis whether the external files you have stored are still available.

== Settings ==

In the settings you can define whether image files are hosted locally in your hosting or externally.

== Repository and documentation ==

You find some documentations [here](https://github.com/threadi/external-files-in-media-library/docs/).

The development repository is on [GitHub](https://github.com/threadi/external-files-in-media-library/).

---

== Installation ==

1. Upload "external-files-in-media-library" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Can I prevent other WordPress-users from adding external files? =

Yes, you can select under Settings > External files in Media Library which roles gets the ability to add external files.

= Can I also embed password-protected external files? =

Yes, but these files will included locally and not from the external URL.

= Can I embed files from FTP? =

Yes, but these files will included locally and not from the external URL.

= Can I import complete directories? =

Yes, you can. Just enter the directory to import.

= Can I import from my local server? =

Yes, you can. Simply enter the absolute path with file-protocol, e.g.: file:///var/www/path/to/file.png

= Can I import external product images for WooCommerce? =

Yes, simply enable the setting under Settings > External files in Media Library > WooCommerce. Add your external URLs
for images in the CSV you want to import as it is already possible with WooCommerce. They will be handled as
external files by this plugin.

== Screenshots ==

1. Field to add external files in Media > Add New Media File.
2. Dialog to add URLs of external files.

== Changelog ==

= 1.0.0 =
* Initial release

= 1.0.1 =
* Updated compatibility-flag for WordPress 6.4
* Compatible with WordPress Coding Standards 3.0
* Fixed error in settings-save-process
* Fixed typo in translations

= 1.0.2 =
* Added hook documentation
* Added hint for hook documentation in settings
* Optimized handling of upload-form if nothing has been added there
* Removed language files from release

= 1.1.0 =
* Added multiple hooks
* Prevent usage of plugin with older PHP than required minimum
* Optimized content type detection
* Optimized attachment title handling with special chars
* Updated compatibility-flag for WordPress 6.4.3
* Updated dependencies

= 1.1.1 =
* Proxy-slug will now also be changed with simple permalinks
* Updated compatibility-flag for WordPress 6.5
* Updated hook documentation
* Fixed support for spaces in URLs
* Fixed typo in examples in hook-documentation
* Fixed possible notice in transient-handler
* Fixed usage of proxy with simple permalinks

= 1.1.2 =
* Fixed possible error during check for current screen
* Fixed usage of URLs with ampersand on AJAX-request

= 1.2.0 =
* New import dialog with progress and extended info about the import
* Show proxy hint on file only if proxy is enabled
* Optimized style for box with infos about external files
* Updated compatibility-flag for WordPress 6.5.2
* Updated dependencies

= 1.2.1 =
* Added support for hook of plugin "Download List Block with Icons" for mark external files with rel-external
* Updated compatibility-flag for WordPress 6.5.3
* Updated dependencies

= 1.2.2 =
* Updated compatibility-flag for WordPress 6.6
* Updated dependencies
* Fixed potential error on attachment pages

= 1.2.3 =
* Updated dependencies

= 1.3.0 =
* Added possibility to switch the hosting of images during local and extern on media edit page
* Added new column for marker of external files in media table
* Compatibility with plugin Prevent Direct Access: hide options for external fields
* Fixed some typos
* Fixed wrong proxied URL after successful import of images

= 2.0.0 =
* Revamped plugin
* Added queue for importing large amount of URLs
* Added support for import of directories with multiple files
* Added support for different tcp-protocols
* Added support for FTP-URLs
* Added support for SSH/SFTP-URLs
* Added support for file-URL (to import from local server)
* Added support for credentials for each tcp-protocol
* Added wrapper to support third party plugins or platforms, e.g. Imgur or Google Drive
* Added warning about old PHP-versions
* Added option to switch external files to local hosting during uninstallation of the plugin
* Added WP CLI option to switch hosting of all files to local or external
* Added documentation for each possible option in GitHub
* Added link to settings in plugin list
* Added migration tool to switch the external files from Exmage to this one
* Added thumbnail support for proxied images
* Added settings for videos which now can also be proxied
* Added import and export for plugin settings
* Added a handful help texts for WordPress-own help system
* Added multiple new hooks
* Added statistic about used files.
* Compatible with WordPress 6.7
* External files which are not provided via SSL will be saved local if actual website is using SSL
* Extended WP CLI support with documentation, progressbar, states and arguments
* Replaced settings management with optimized objects
* Optimized proxy url handling
* Optimized build process for releases
* Optimized transients of this plugin
* Optimized log table with much more options
* Replaced dialog library with new one
* Renamed internal transient prefix for better compatibility with other plugins
* Move support for already supported plugins in new wrapper
* Fixed some typos
* Fixed error with import of multiple files via WP CLI

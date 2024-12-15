=== External files in media library ===
Contributors: threadi
Tags: external files, media library, media
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Add external files to your media library to link or embed them in your website. They will be integrated as if they were locally available.

== Description ==

Add one or more files under Media > "Add new media file". You can use the files in all places where the media library is used.

== Mass-Import ==

You can import complete directories from any of the supported TCP protocols. Just enter the directory as path to import and the plugin will import any supported files from it.

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

In the settings you can define whether your files are hosted locally in your hosting or externally.

== Repository and documentation ==

You find some documentations [here](https://github.com/threadi/external-files-in-media-library/docs/).

The development repository is on [GitHub](https://github.com/threadi/external-files-in-media-library/).

= Known Issues =
* Local saved Youtube-Video could not be embedded via Elementor
* Youtube-Preview in Block Editor not working (but in frontend it does)

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

Yes, see [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/ftp.md).

= Can I import complete directories? =

Yes, you can. Just enter the directory to import.

= Can I import from my local server? =

Yes, you can. Simply enter the absolute path with file-protocol, e.g.: `file:///var/www/path/to/file.png` - see also [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/file.md).

= Can I import external product images for WooCommerce? =

Yes, simply enable the setting under Settings > External files in Media Library > WooCommerce. Add your external URLs
for images in the CSV you want to import as it is already possible with WooCommerce. They will be handled as
external files by this plugin. This also allows you to use all protocols supported by the plugin for importing these files.

= Is there a WP CLI command? =

Yes, there are many options on WP CLI, see [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md).

== Screenshots ==

1. Field to add external files in Media > Add New Media File.
2. Dialog to add URLs of external files.

== Changelog ==

## [2.0.2] - 2024-11-23

### Added

- Added option to use the external file date instead of the import date

### Fixed

- Fixed hook documentations

[older changes](https://github.com/threadi/external-files-in-media-library/changelog.md)

=== External files in media library ===
Contributors: threadi
Tags: external files, media library, media, embed
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Add external files to your media library to link or embed them in your website. They will be integrated as if they were locally available.

== Description ==

Add one or more files with their URLs under Media > "Add new media file". You can use this way added external files in all places where the media library is used.

Embed your files from Google Drive, YouTube, Vimeo, FTP, local paths or many other possible sources. Use them in your preferred editor such as Block Editor, Elementor, Divi, Classic Editor, WpBakery and many more.

Use an external data source for product images in your WooCommerce-based store.

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

Some of them require credentials, for http(s) it is optional.

== Checks ==

The plugin checks for you automatically on a regular basis whether the external hosted files you have embedded are still available.

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

Yes, you can select under Settings > "External files in Media Library" which roles gets the ability to add external URLs as files.

= Can I also embed password-protected external files? =

Yes, but these files are integrated locally and not from the external URL so that your visitors can access them without any problems.

= Can I embed files from FTP? =

Yes, see [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/ftp.md).

= Can I import complete directories? =

Yes, you can. Just enter the directory to import.

= Can I import from my local server? =

Yes, you can. Simply enter the absolute path with file-protocol, e.g.: `file:///var/www/path/to/file.png` - see also [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/file.md).

= Can I import external product images for WooCommerce? =

Yes, simply enable the setting under Settings > "External files in Media Library" > WooCommerce. Add your external URLs
for images in the CSV you want to import as it is already possible with WooCommerce. They will be handled as
external files by this plugin. This also allows you to use all protocols supported by the plugin for importing these files.

= Is there a WP CLI command? =

Yes, there are many options on WP CLI, see [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md).

== Screenshots ==

1. Field to add external files in Media > Add New Media File.
2. Dialog to add URLs of external files.

== Changelog ==

= @@VersionNumber@@ =

- Added Services to platforms which host files, usable for local host, FTP, YouTube and ZIP-files atm
- Added management for credentials of these external platforms
- Added possibility to add YouTube- and Vimeo-videos as external files
- Added possibility to import Youtube-Channel-Videos as external files via YouTube API
- Added marker for proxied files
- Added possibility to customize the handling of adding new URLs to media library with custom PHP
- Added more hooks
- Added GitHub action to build plugin releases
- Added log for deleting temp file during import
- Do not download files via http protocol which should not be hosted locally => speed up the import & reduce space usage
- Optimized check if HTTP-file should be saved locally by file type
- Optimized updating or installing log- and queue-tables during plugin update
- Optimized upload dialog regarding the credential usage if browser used autofill-functions
- Optimized FTP- and SFTP-handling for external files regarding its import
- Embed our own CSS- and JS-files in backend only if necessary (speeds up loading time there)
- Extended logging in debug mode
- Updated dependencies
- Moved changelog from readme.txt in GitHub-repository
- Reduced number of calls for external file thumbs
- Small optimizations on WooCommerce support during import for products via CSV
- Optimization of many texts
- Fixed potential error with import of YouTube videos
- Fixed output of hint it file is not available
- Fixed potential error if URL is already on media library
- Fixed partially wrong saving of meta-data on external media files
- Fixed wrong check for already existing thumbs of external files
- Fixed styling of single URL upload field
- Fixed logging of deleted URL with its proxied path
- Fixed wrong quick start documentation URL

[older changes](https://github.com/threadi/external-files-in-media-library/changelog.md)

=== External files in Media Library ===
Contributors: threadi
Tags: external files, media library, media, embed
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Add external files to your media library to link or embed them in your website. They will be integrated as if they were locally available.

== Description ==

Add one or more files with their URLs under Media > "Add new media file". You can use this external files in all places where the media library is used.

== Feature ==

Embed your files from Google Drive, DropBox, FTP, your local hosting, other WordPress REST APIs, YouTube or many other possible sources. Use them in your preferred editor such as Block Editor, Elementor, Divi, Classic Editor, WpBakery and many more as if the files were stored normally in your media library.

Automatically synchronize external directories containing files with your media library at intervals you specify.

And even more:

* Add the files with their external dates. This allows you to obtain the date, which is helpful for SEO, for example.
* Import them as real files instead of just linking to them in your media library. This allows you to import any number of files into your project.
* Check their availability (only for HTTP connections) to ensure that the external files are actually available.
* Configure which users in your project are allowed to use the external files options.

== Support for other plugins ==

Use an external data source for product images in your WooCommerce-based store.

Add external files on download lists of [Download Lists with Icons](https://wordpress.org/plugins/download-list-block-with-icons/).

Sort your external files in folder of [CatFolders](https://wordpress.org/plugins/catfolders/), [Filebird](https://wordpress.org/plugins/filebird/), [Folderly](https://wordpress.org/plugins/folderly/), [Folders](https://wordpress.org/plugins/folders/), [iFolders](https://wordpress.org/plugins/ifolders/) and [Media Library Organizer](https://wordpress.org/plugins/media-library-organizer/) or assign them into categories from [Enhanced Media Library](https://wpuxsolutions.com/plugins/enhanced-media-library/). You can import and synchronize them in these plugins.

== REST API ==

You can manage your external files with REST API requests as documented [here](https://github.com/threadi/external-files-in-media-library/tree/master/docs/rest.md).

== Mass-Import ==

You can import complete directories from any of the supported TCP protocols. Just enter the directory as path to import and the plugin will import any supported files from it or use the external source tools to navigate to the directory to import. For very large directories there is also an automatically processed queue.

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

== Repository, documentation and reliability ==

You find some documentations [here](https://github.com/threadi/external-files-in-media-library/tree/master/docs).

The development repository is on [GitHub](https://github.com/threadi/external-files-in-media-library/).

Each release of this plugin will only be published if it fulfills the following conditions:

* PHPStan check for possible bugs
* Compliance with WordPress Coding Standards

---

== Installation ==

1. Upload "external-files-in-media-library" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Can I prevent other WordPress-users from adding external files? =

Yes, you can select under Settings > "External files in Media Library" > Permissions which roles gets the
ability to add or delete external URLs in your media library.

= Can I also embed password-protected external files? =

Yes, but these files are integrated locally and not from the external URL so that your visitors can access them without any problems.

= Can I embed files from FTP? =

Yes, you can [add them manually](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/ftp.md) or with the FTP-tool in Media > "Add external files".

= Can I import complete directories? =

Yes, you can. Just enter the directory to import or use the tools under Media > "Add external files".

= Can I import from my local server? =

Yes, you can. Simply use the Local tool under Media > "Add external files" or enter the absolute path with file-protocol,
e.g.: `file:///var/www/path/to/file.png` - see also [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/file.md).

= Can I import external product images for WooCommerce? =

Yes, simply enable the setting under Settings > "External files in Media Library" > WooCommerce. Add your external URLs
for images in the CSV you want to import as it is already possible with WooCommerce. They will be handled as
external files by this plugin. This also allows you to use all protocols supported by the plugin as external source of your files.

= Is there a WP CLI command? =

Yes, there are many options on WP CLI, see [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md).

= Google tells me that the app is not verified when I connect Google Drive - why? =

According to Google guidelines, an app that is used to connect to the Google Drive API is only checked and confirmed once
it has 100 active users. As long as less than 100 active users use this function via this plugin, you will always see
this message. You can confirm it via "unsecure" and still complete the connection of your Google Drive with your
WordPress website.

== Screenshots ==

1. Field to add external files in Media > Add New Media File.
2. Dialog to add URLs of external files.

== Changelog ==

= @@VersionNumber@@ =

- Added REST API support for handling of external URLs from other WordPress-projects in your media library
- Added DropBox support for import of external files into your media library
- Added new URL-import dialog in backend
- Added option to delete synchronized files of single directory archive with one click
- Introduced file handling extensions and added 3 of them (date, queue, real_import)
- Added option to use the date of external files in add-dialog (2nd file handling extension)
- Added option to really import files in media library (this disables all external files functions for these files)
- Added these 2 new options also as parameter on WP CLI command to import URLs
- Added option to choose which of these extensions should be available for file handlings
- Added file type specific icons in directory listings
- Added unique identifier for each import to prevent To avoid confusion when multiple users and imports
  are occurring simultaneously
- Added import date for each external URL
- Added new table column in media library which shows basic URL information
- Added Taskfile as third way to build plugin release
- Added check for PHP strict usage on every release with PHPStan
- Added support for custom Download Lists of the plugin "Download Lists with icons" incl. sync of them
- Show processed file URLs during manual started synchronization
- Hide import button for unsupported files in directory archive
- Small optimizations on multiple codes
- Using fallback to default interval for each of our events if setting of not available
- Show hide and rating on directory archive listing
- Directory reload no shows the progress
- Optimized ZIP service
- Updated some unfavorable text descriptions
- Updated dependencies
- Active folder in directory listing is now marked
- Optimized styling of directory listings
- Standardize the usage of timestamp as last-modified date for each service and protocol
- Optimized check if a mime type is allowed in directory listing and during import
- Optimized detection of multiple URLs from textarea-field with different line breaks
- Optimized WooCommerce CSV-import with URLs for external files
- Re-arranged the settings for a better overview
- Multiple new hooks and updated hook documentation
- Updated settings object for better performance and more possibilities
- Extended documentation in GitHub for all services we provide
- Extended logging is automatically enabled if WordPress is running in development mode
- Moved availability check in extension
- Renamed filter "eml_import_url_before" to "eml_import_url"
- Renamed filter "eml_blacklist" to "eml_prevent_import"
- Hosting of files can now only be changed by users with the capability to upload external files
- Wrong usage of import URLs from directory archives if they are using a path after the domain
- Fixed wrong link to queue list in settings and in dialog
- Fixed missing file on FTP listing if for previous file not thumbnail could be created
- Fixed missing file preview if PHP-imagick-library is not used
- Fixed disabling of thumbnails on GoogleDrive view
- Fixed usage of ZIP service on single uploaded file
- Fixed wrong capability to access the directory archive for non-administrator users
- Removed hook "eml_import_fields" as we do not use fields in this form anymore
- Removed hook "eml_import_url_after" as it could be better used via the hook "eml_after_file_save"

[older changes](https://github.com/threadi/external-files-in-media-library/blob/master/changelog.md)

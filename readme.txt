=== External files in Media Library ===
Contributors: threadi
Tags: external files, media library, media, embed
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Requires CP:  2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Add external files to your media library to use them in your website. They are integrated as if they were available locally.

== Description ==

Add one or more files with their URLs under Media > "Add New Media File". You can use these external files anywhere the media library is used.

== Feature ==

Embed your files from AWS S3, DropBox, Google Drive, Google Cloud Storage, FTP, your local hosting, other WordPress REST APIs, YouTube or many other possible sources. Use them in your preferred editor such as Block Editor, Elementor, Divi, Classic Editor, WpBakery and many more as if the files were stored normally in your media library.

Automatically synchronize external directories containing files with your media library at intervals you specify.

And even more:

* Add the files with their external dates. This allows you to get the date, helpful for SEO, for example.
* Import them as real files instead of just linking to them in your media library. This allows you to import any amount files into your project.
* Check their availability (only for HTTP connections) to ensure that the external files are actually available.
* Configure, which users in your project are allowed to use the external files options.
* Extract ZIP files from any external source into your media library.

== Support for other plugins ==

Use external URLs when importing products via CSV in a [WooCommerce](https://wordpress.org/plugins/woocommerce/) store (including access data for these e.g., via AN FTP).

Add external files on download lists of [Download Lists with Icons](https://wordpress.org/plugins/download-list-block-with-icons/).

Sort your external files in a folder of [CatFolders](https://wordpress.org/plugins/catfolders/), [Filebird](https://wordpress.org/plugins/filebird/), [Folderly](https://wordpress.org/plugins/folderly/), [Folders](https://wordpress.org/plugins/folders/), [iFolders](https://wordpress.org/plugins/ifolders/), [Media Library Organizer](https://wordpress.org/plugins/media-library-organizer/) or assign them into categories from [Enhanced Media Library](https://wpuxsolutions.com/plugins/enhanced-media-library/) and [Real Media Library Lite](https://wordpress.org/plugins/real-media-library-lite/). You can import and synchronize them in these plugins.

And compatible with [Network Media Library](https://github.com/humanmade/network-media-library) for use in multisites.

The plugin is also compatible with a variety of other plugins not listed here. If, contrary to expectations, something does not work properly, please report it [in the support forum](https://wordpress.org/support/plugin/external-files-in-media-library/).

== REST API ==

You can manage your external files with REST API requests as documented [here](https://github.com/threadi/external-files-in-media-library/tree/master/docs/rest.md).

== Mass-Import ==

You can import complete directories from any of the supported TCP protocols. Just enter the directory as path to import, and the plugin will import any supported files from it or use the external source tools to navigate to the directory to import.

For large directories, there is also an automatically processed queue. You could also use the [WP CLI](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md) for large directories.

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

== Service plugins ==

Support for additional platforms as external sources is enabled by additional service plugins. These are now:

* [External files from Google Cloud Storage in Media Library](https://github.com/threadi/external-files-from-google-cloud-storage)
* [External files from Google Drive in Media Library](https://github.com/threadi/external-files-from-google-drive)

They can be installed manually or in the backend of your WordPress unter Media Library > External Sources.

== Use cases ==

Here are a few examples of how this plugin can help you:

* Store particularly large files in a different storage location so that you save storage space on your hosting.
* Import files that your graphic designer provides you in a shared directory.
* Automatically synchronize photos from your vacation for display on your website.
* Import regularly newly generated PDF files from a shared directory for output on your website.
* Get images for your products from a central directory.

== ClassicPress ==

This plugin is compatible with [ClassicPress](https://www.classicpress.net/).

== Repository, documentation and reliability ==

You find some documentations [on this plugin page](https://plugins.thomaszwirner.de/en/plugin/externe-dateien-in-der-mediathek/) and [in GitHub](https://github.com/threadi/external-files-in-media-library/tree/master/docs).

The development repository is on [GitHub](https://github.com/threadi/external-files-in-media-library/).

Each release of this plugin will only be published if it fulfills the following conditions:

* PHPStan check for possible bugs.
* Compliance with WordPress Coding Standards.
* No failures during PHP Compatibility check.
* No exceptions during PHP Unit Tests.

---

== Installation ==

1. Upload "external-files-in-media-library" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Why do you need to install additional plugins for some sources? =

Two reasons for this:

a) Some of the external sources use libraries whose licenses are not permitted in the WordPress repository. For example, the aws/aws-sdk-php library for AWS uses the Apache License. This is not compatible with GPL.

b) With all these libraries, the plugin would be too large to publish in the WordPress repository.

= Can I prevent other WordPress-users from adding external files? =

Yes, you can select under Settings > "External files in Media Library" > Permissions, which roles gets the ability to add or delete external URLs in your media library.

= Can I also embed password-protected external files? =

Yes, but these files are integrated locally and not from the external URL so that your visitors can access them without any problems.

= Can I embed files from an FTP? =

Yes, you can [add them manually](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/ftp.md) or with the FTP-tool in Media > "Add external files".

= Can I import complete directories? =

Yes, you can. Just enter the directory to import or use the tools under Media > "Add external files". All files in the directory will be imported.

= Do the upload size limits for files apply in the same way as for a normally uploaded file? =

No, there is no fixed size limit for external files. Limits are determined by the storage space available to you, depending on where the file is stored.

= Can I import from my local server? =

Yes, you can. Simply use the Local tool under Media > "Add external files" or enter the absolute path with file-protocol,
e.g.: `file:///var/www/path/to/file.png` - see also [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/file.md).

= Can I import external product images for WooCommerce? =

Yes, simply enable the setting under Settings > "External files in Media Library" > WooCommerce. Add your external URLs
for images in the CSV you want to import as it is already possible with WooCommerce. They will be handled as
external files by this plugin. This also allows you to use all protocols supported by the plugin as external source of your files.

= Is there a WP CLI command? =

Yes, we provide many options on WP CLI, see [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md).

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

- Added intro for first time users of this plugin
- Added REST API support for handling of external URLs from other WordPress-projects in your media library
- Added support for AWS S3 as external source of files
- Added support for DropBox as external source of files
- Added support for Google Cloud Storage as external source of files
- Added support for WebDav as external source of files, e.g. usable with your Nextcloud
- Added hook to import external URLs through third party plugins (for custom development)
- Added new URL-import dialog in backend
- Added option to delete synchronized files of single directory archive with one click
- Added option to export each newly uploaded file in media library to external sources which are reachable via web
-> supported for Dropbox, FTP, Google Drive, Google Cloud Storage, local, AWS S3 and WebDav
-> optionally, you can delete the local files, thereby outsourcing (offloading) all your files and saving storage space
- Added option to export each file in media library to external source as described above
- Introduced file handling extensions and added 3 of them (date, queue, real_import)
- Added option to use the date of external files in add-dialog (2nd file handling extension)
- Added option to really import files in media library (this disables all external files functions for these files)
- Added option to choose a specific date for each file to import
- Added paginated AJAX-import to prevent timeouts, supported for AWS S3 and Google Drive
- Added these 3 new options also as parameter on WP CLI command to import URLs
- Added option to choose which of these extensions should be available for file handlings
- Added file type specific icons in directory listings
- Added unique identifier for each import to prevent To avoid confusion when multiple users and imports
  are occurring simultaneously
- Added import date for each external URL
- Added new table column in media library which shows basic URL information
- Added Taskfile as third way to build plugin release
- Added check for PHP strict usage on every release with PHPStan
- Added check for compatibility with WordPress Plugin Checker on every release
- Added support for download lists of the plugin "Download Lists with icons" incl. sync of them
- Added support for plugin "Folders" to import external files in its folders incl. sync of them
- Added support for plugin "Media Library Organizer" to import external files in its categories incl. sync of them
- Added support for plugin "iFolders" to import external files in its folders incl. sync of them
- Added support for plugin "Real Media Library Lite" to import external files in its folders incl. sync of them
- Added support for plugin "Advanced Media Offloader" to prevent the offloading of already external files
- Added support for plugin "Media Library Assistant" to import external files in its folders incl. sync of them
- Added compatibility with plugin "Media Cloud Sync"
-> do not sync external files with external clouds
-> do sync real imported external files
- Added new file types "PDF" and "ZIP" for better supporting the handling of these files
- Added info about external files in attachment modal
- Added option to use the files dates during synchronization
- Added option to import real files during synchronization (they are just imported if they are no duplicate)
- Added privacy hint as checkbox in every import dialog, configurable in user settings
- Added WordPress Importer entry
- Added info in admin footer for pages provided by the plugin or for which it makes extensions on the called pages
- Added support for plugin "WP Extra File Types" to enabled additional possible file types to use as external files
- Added option to load upload directory via local service
- Added option to use our plugin name in each HTTP-header User Agent (default enabled)
- Added success sound after import has been run (can be disabled)
- Added option to reset the plugin in backend settings (in preparation for Cyber Resilience Act)
- Added support to import Google Drive files via WP CLI (without any timeouts)
- Added option to unzip from external password protected ZIP-files
- Added options to open and extract zip-files which are already saved in media library
- Added support to open and extract multiple zip formats: .zip, .gz, .tar.gz
- Added support for .avif files
- Added option to show what will be done in import dialog
- Added support for filenames in other writing systems (like Farsi)
- Added option to hide the review begging
- Added new extension to allow import and export of external files in JSON-format (default disabled)
- Added export and import of settings for external sources
- A unique job ID has been added to each imported file to enable filtering of imported external files in a single task
- Added PHP unit tests for essential functions of this plugin
- Added SBOM generation on GitHub for each release
- Changed ALL hooks to prefix with 4 characters to match WordPress Coding Standards
- Compatibility with WordPress 6.9
- Renamed "Directory Archive" to "Your external sources"
- Show processed file URLs during manual started synchronization
- Hide import button for unsupported files in directory archive
- Small optimizations on multiple codes
- Using fallback to default interval for each our events if setting of not available
- Show hide and rating on directory archive listing
- Directory reload no shows the progress
- Optimized ZIP service: no also allows to extract complete ZIP files in media library
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
- Synced files will be linked with its linked source in media library
- Prevent deletion of sources which has active synced or exported files
- Import of files during WooCommerce CSV supports now also usage of credentials (you could import files e.g. from FTP)
- Using new transient object in backend for hints and errors
- Cleanup the return value for external files via get_attached_file()
- File protocol uses now WP_Filesystem for each file interaction
- Enabled search field for URLs in logs
- Dropbox file URLs can now be imported without any API key if they are public available
- External sources are now saved user-specific
  -> only administrators see all entries
  -> advanced option allows to show all entries for alle users
- Settings for most services are now saved on user and not global, but can be set to global
- External sources can now get an individual name
- ZIP files can not also be opened via any supported TCP protocol
- Save used service on each external file
- Local path will be sanitized
- Optimized URL shortener
- Wrong usage of import URLs from directory archives if they are using a path after the domain
- Fixed wrong link to queue list in settings and in dialog
- Fixed missing file on FTP listing if for previous file not thumbnail could be created
- Fixed missing file preview if PHP-imagick-library is not used
- Fixed disabling of thumbnails on GoogleDrive view
- Fixed usage of ZIP service on single uploaded file
- Fixed wrong capability to access the directory archive for non-administrator users
- Fixed disabling of check files event
- Fixed detection of correct file type during import process
- Fixed potential error with attached files if they do not exist
- Fixed missing visible progress-bar during synchronization
- Fixed missing saving of actual availability of each file (all were available any time)
- Fixed REST API endpoints to not using WP_Error for responses
- Removed hook "eml_import_fields" as we do not use fields in this form anymore
- Removed hook "eml_import_url_after" as it could be better used via the hook "eml_after_file_save"

[older changes](https://github.com/threadi/external-files-in-media-library/blob/master/changelog.md)

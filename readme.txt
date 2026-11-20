=== External files in Media Library ===
Contributors: threadi
Tags: offload, external files, media library, cloud storage, embed
Requires at least: 6.2
Tested up to: 7.2
Requires PHP: 8.2
Requires CP:  2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Connect, import, synchronize and offload your media to virtually any external storage: cloud storage, FTP, WebDAV or other WordPress sites.

== Description ==

**The complete external media management solution for WordPress.**

Connect, import, synchronize and offload your media to virtually any external storage. Integrate files from cloud storage, FTP servers, WebDAV and other WordPress installations directly into your media library – they are used as if they were stored locally.

Add one or more files with their URLs under Media > "Add New Media File". You can use these external files anywhere the media library is used.

[youtube https://www.youtube.com/watch?v=abBPWvp1Luk]

== Features ==

Embed your files from _AWS S3_, _DropBox_, _Google Drive_, _Google Cloud Storage_, _FTP_, _your local hosting_, another website in your _multisite_, other _WordPress REST APIs_, _YouTube_ or many other possible sources. Use them in your preferred editor such as Block Editor, Elementor, Divi, Classic Editor, WpBakery and many more as if the files were stored normally in your media library.

Automatically synchronize external directories containing files with your media library at intervals you specify.

Offload your media: export new uploads and existing files to AWS S3, S3-compatible storages, Google Cloud Storage, FTP, Dropbox, WebDAV or other external sources to save storage space on your hosting. The files are then used as external files.

And even more:

✅ Add the files with their external dates. This allows you to get the date, helpful for SEO, for example.
✅ Import them as real files instead of just linking to them in your media library. This allows you to import any amount files into your project.
✅ Check their availability (only for HTTP connections) to ensure that the external files are actually available.
✅ Configure, which users in your project are allowed to use the external files options.
✅ Extract ZIP files from any external source into your media library.
✅ Import lists of URLs from CSV and XML files (e.g., sitemaps, feeds or exports), uploaded or from a URL.
✅ Import and synchronize large directories with thousands of files in the background.
✅ Save exported files in private storages and deliver them via the proxy or via temporary links, also for WooCommerce downloads.
✅ Sort exported files into folders by year and month.
✅ Keep access data out of your database: define them as constants in your wp-config.php, also for several connections per service.
✅ Work safely with copies of your website: on staging websites, nothing is exported to or deleted on your external sources.
✅ Get started step by step: the quickstart configures the most common tasks for you, from offloading your media library to synchronizing files from FTP.
✅ Switch from WP Offload Media, Advanced Media Offloader, Stowaway or Offload Media – Cloud Storage without uploading your files again.
✅ Find everything quickly: the tiles of the services show their features, and the command palette of WordPress (Ctrl + K) finds services, external sources and settings.

See [what is possible](https://github.com/threadi/external-files-in-media-library/blob/master/docs/features.md) for an overview of all features.

== Support for other plugins ==

✅ **Image optimizers:** Imagify, ShortPixel, Smush, EWWW Image Optimizer, TinyPNG, Converter for Media and Modern Image Formats – optimized files and their WebP and AVIF variants are exported, too.
✅ **Folders and galleries:** sort, import and synchronize external files with plugins like Filebird, Folders, Real Media Library Lite or NextGen Gallery.
✅ **Media tools:** Media Cleaner, Media File Renamer, Image Watermark, Media Bulk Download, Perfect Images (WP Retina 2x), Regenerate Thumbnails and Force Regenerate Thumbnails.
✅ **WooCommerce:** use external URLs in the product import via CSV (also with access data, e.g. for FTP) and external files as downloads.
✅ **More:** Instant Images (Unsplash, Pexels and GIPHY as external files), Polylang, Network Media Library, Download Lists with Icons, Document Embedder and Prevent Files / Folders Access.

See [all supported plugins](https://github.com/threadi/external-files-in-media-library/blob/master/docs/plugins.md) for details. If something does not work as expected, please report it [in the support forum](https://wordpress.org/support/plugin/external-files-in-media-library/).

== REST API ==

You can manage your external files with REST API requests as documented [here](https://github.com/threadi/external-files-in-media-library/tree/master/docs/rest.md).

== Mass-Import ==

Import complete directories from any supported source: your local hosting, FTP, FTPS, SFTP, Dropbox and, via service plugins, AWS S3 and S3-compatible storages, Google Cloud Storage, Google Drive and WebDAV. Browse to the directory in the plugin or simply enter its path or URL (see [supported protocols](https://github.com/threadi/external-files-in-media-library/blob/master/docs/protocols.md)).

Directories with thousands of files are imported in the background, with the progress shown in the admin bar. Files that are already in your media library are skipped. For scripted imports, use [WP CLI](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md).

You can also import lists of URLs from CSV and XML files, the videos of a YouTube channel, and the contents of ZIP files.

See [import of directories](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/directories.md) for details.

== Service plugins ==

Support for additional platforms as external sources is enabled by additional service plugins. These are now:

➕ [External files from AWS S3 in Media Library](https://github.com/threadi/external-files-from-aws-s3) (incl. support for AWS S3, Amazon CloudFront, Backblaze B2, Cloudflare R2, DigitalOcean Spaces, Hetzner Object Storage, IONOS, OVHcloud, Scaleway, Wasabi and any other S3-compatible storage like MinIO)
➕ [External files from Google Cloud Storage in Media Library](https://github.com/threadi/external-files-from-google-cloud-storage)
➕ [External files from Google Drive in Media Library](https://github.com/threadi/external-files-from-google-drive)
➕ [External files from WebDav in Media Library](https://github.com/threadi/external-files-from-webdav) (incl. any WebDav-provider like NextCloud or Seafile)

They can be installed manually or in the backend of your WordPress under Media Library > Add External Files.

== Use cases ==

Here are a few examples of how this plugin can help you:

💡 Store particularly large files in a different storage location so that you save storage space on your hosting.
💡 Import files that your graphic designer provides you in a shared directory.
💡 Automatically synchronize photos from your vacation for display on your website.
💡 Use regularly newly generated PDF files from a shared directory for output on your website.
💡 Get images for your products from a central directory.

Find more [here](https://plugins.thomaszwirner.de/en/external-files-in-the-media-library/)

== ClassicPress ==

This plugin is compatible with [ClassicPress](https://www.classicpress.net/).

== Upgrade Notice ==

= 6.0.0 =

Major update with enhanced export of files to external sources (offloading), private files, credentials via constants, protection of staging websites, a quickstart for the most common tasks and a new structure of the settings. Please be sure to create a backup before updating.

== Repository, documentation and reliability ==

You find some documentations [on this plugin page](https://plugins.thomaszwirner.de/en/plugin/externe-dateien-in-der-mediathek/) and [in GitHub](https://github.com/threadi/external-files-in-media-library/tree/master/docs).

The development repository is on [GitHub](https://github.com/threadi/external-files-in-media-library/).

Each release of this plugin will only be published if it fulfills the following conditions:

✅ PHPStan check for possible bugs.
✅ Compliance with WordPress Coding Standards.
✅ No failures during PHP Compatibility check.
✅ No exceptions during PHP Unit Tests.

---

== Installation ==

1. Upload "external-files-in-media-library" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Why do you need to install additional plugins for some sources? =

Two reasons for this:

a) Some of the external sources use libraries whose licenses are not permitted in the WordPress repository. For example, the aws/aws-sdk-php library for AWS uses the Apache License. This is not compatible with GPL.

b) With all these libraries, the plugin would be too large to publish in the WordPress repository.

The extensions are free and could be installed with one click in the list of services or in the quickstart. More about them in [the documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/extensions.md).

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
e.g., `file:///var/www/path/to/file.png` - see also [our documentation](https://github.com/threadi/external-files-in-media-library/blob/master/docs/import/file.md).

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
3. Success message after adding the URL of an external file.
4. An external file in the media library.
5. View directories and files in Google Drive.

== Changelog ==

= @@VersionNumber@@ =

- Updated dependencies
- Added quickstart for the most common tasks
- Added migration from other offload plugins
- Added private files and support for WooCommerce downloads
- Added credentials via constants and protection of staging websites
- Extended export: background export, image sizes, WebP/AVIF, folder structure, restore as local files
- Added support for image optimizers and further plugins
- Added search in the settings and in the command palette
- Restructured settings and improved performance
- Many bug fixes, especially for Dropbox, the proxy and large files

[older changes](https://github.com/threadi/external-files-in-media-library/blob/master/changelog.md)

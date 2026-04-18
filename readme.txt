=== External files in Media Library ===
Contributors: threadi
Tags: external files, media library, media, embed
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Requires CP:  2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Add external files to your media library to use them in your website. They are integrated as if they were available locally.

== Description ==

Add one or more files with their URLs under Media > "Add New Media File". You can use these external files anywhere the media library is used.

== Feature ==

Embed your files from _AWS S3_, _DropBox_, _Google Drive_, _Google Cloud Storage_, _FTP_, _your local hosting_, another website in your _multisite_, other _WordPress REST APIs_, _YouTube_ or many other possible sources. Use them in your preferred editor such as Block Editor, Elementor, Divi, Classic Editor, WpBakery and many more as if the files were stored normally in your media library.

Automatically synchronize external directories containing files with your media library at intervals you specify.

And even more:

✅ Add the files with their external dates. This allows you to get the date, helpful for SEO, for example.
✅ Import them as real files instead of just linking to them in your media library. This allows you to import any amount files into your project.
✅ Check their availability (only for HTTP connections) to ensure that the external files are actually available.
✅ Configure, which users in your project are allowed to use the external files options.
✅ Extract ZIP files from any external source into your media library.

== Support for other plugins ==

Use external URLs when importing products via CSV in a [WooCommerce](https://wordpress.org/plugins/woocommerce/) store (including access data for these e.g., via an FTP).

Add external files on download lists of [Download Lists with Icons](https://wordpress.org/plugins/download-list-block-with-icons/).

Sort your external files in a folder of [CatFolders](https://wordpress.org/plugins/catfolders/), [Filebird](https://wordpress.org/plugins/filebird/), [Folderly](https://wordpress.org/plugins/folderly/), [Folders](https://wordpress.org/plugins/folders/), [iFolders](https://wordpress.org/plugins/ifolders/), [Media Library Organizer](https://wordpress.org/plugins/media-library-organizer/) or assign them into categories from [Enhanced Media Library](https://wpuxsolutions.com/plugins/enhanced-media-library/) and [Real Media Library Lite](https://wordpress.org/plugins/real-media-library-lite/). You can import and synchronize them in these plugins.

And compatible with [Network Media Library](https://github.com/humanmade/network-media-library) for use in multisites.

And it is also compatible with multilingual plugins like Polylang to translate the media files.

The plugin is also compatible with a variety of other plugins not listed here. If, contrary to expectations, something does not work properly, please report it [in the support forum](https://wordpress.org/support/plugin/external-files-in-media-library/).

== REST API ==

You can manage your external files with REST API requests as documented [here](https://github.com/threadi/external-files-in-media-library/tree/master/docs/rest.md).

== Mass-Import ==

You can import complete directories from any of the supported TCP protocols. Just enter the directory as path to import, and the plugin will import any supported files from it or use the external source tools to navigate to the directory to import.

For large directories, there is also an automatically processed queue. You could also use the [WP CLI](https://github.com/threadi/external-files-in-media-library/blob/master/docs/cli.md) for large directories.

== TCP Protocols ==

You can use the following TCP-protocols to import external files in your media library:

📡 `http://`
📡 `https://`
📡 `ftp://`
📡 `ftps://`
📡 `sftp://`
📡 `ssh://`
📡 `file://`

Some of them require credentials, for http(s) it is optional.

== Service plugins ==

Support for additional platforms as external sources is enabled by additional service plugins. These are now:

➕ [External files from AWS S3 in Media Library](https://github.com/threadi/external-files-from-aws-s3) (incl. support for AWS S3, Backplaze S3, Cloudflare R2 and DigitalOcean Spaces)
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

= 5.0.0 =

Major update for the plugin. Please be sure to create a backup before updating.

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
3. Success message after adding the URL of an external file.
4. An external file in the media library.
5. View directories and files in Google Drive.

== Changelog ==

= @@VersionNumber@@ =

- Fixed some issues regarding composer packages used by this plugin

[older changes](https://github.com/threadi/external-files-in-media-library/blob/master/changelog.md)

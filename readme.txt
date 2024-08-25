=== External files in media library ===
Contributors: threadi
Tags: external files, media library, media
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.3.0

Add external files to your media library to link or embed them in your website. They will be integrated as if they were locally available.

== Description ==

Add one or more files under Media > "Add new media file". You can use the files in all places where the media library is used.

The plugin checks for you automatically on a regular basis whether the external files you have stored are still available.

In the settings you can define whether image files are hosted locally in your hosting or externally.

The development repository is on [GitHub](https://github.com/threadi/external-files-in-media-library/).

---

== Installation ==

1. Upload "external-files-in-media-library" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Can I prevent other users from adding external files? =

Yes, you can select under Settings > External files in Media Library which roles gets the ability to add external files.

= Can I also embed password-protected external files? =

No, only public files can be used.

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

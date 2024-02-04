=== External files in media library ===
Contributors: threadi
Tags: external files, media library, media
Requires at least: 6.2
Tested up to: 6.4.3
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.1.0

== Description ==

Add external files to your media database to link or embed them in your website. The plugin integrates them into your WordPress as if they were locally available. So you can use the files in all places where the media library is used like all other files.

The plugin checks for you automatically on a regular basis whether the external files you have stored are still available.

In the settings you can define whether image files are hosted locally in your hosting or externally.

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

1.

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

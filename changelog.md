# Changelog

## [Unreleased]

### Added

- Added intro for first time users of this plugin
- Added REST API support for handling of external URLs from other WordPress-projects in your media library
- Added support for AWS S3 as external source of files
- Added DropBox support for import of external files into your media library
- Added support for Google Cloud Storage as external source of files
- Added support for WebDav, e.g. usable with your Nextcloud
- Added hook to import external URLs through third party plugins (for custom development)
- Added new URL-import dialog in backend
- Added option to delete synchronized files of single directory archive with one click
- Introduced file handling extensions and added 3 of them (date, queue, real_import)
- Added option to use the date of external files in add-dialog (2nd file handling extension)
- Added option to really import files in media library (this disables all external files functions for these files)
- Added option to choose a specific date for each file to import
- Added these 3 new options also as parameter on WP CLI command to import URLs
- Added option to choose which of these extensions should be available for file handlings
- Added file type specific icons in directory listings
- Added unique identifier for each import to prevent To avoid confusion when multiple users and imports
  are occurring simultaneously
- Added import date for each external URL
- Added new table column in media library which shows basic URL information
- Added Taskfile as third way to build plugin release
- Added check for PHP strict usage on every release with PHPStan
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
- Added new file type "PDF" and "ZIP" for better supporting the handling of these files
- Added info about external files in attachment modal
- Added option to use the files dates during synchronization
- Added option to import real files during synchronization (they are just imported if they are no duplicate)
- Added privacy hint as checkbox in every import dialog, configurable in user settings
- Added WordPress Importer entry
- Added info in admin footer for pages provided by the plugin or for which it makes extensions
- Added support for plugin "WP Extra File Types" to enabled additional possible file types to use as external files
- Added option to load upload directory via local service
- Added option to use our plugin name in each HTTP-header User Agent (default enabled)

### Changed

- Renamed "Directory Archive" to "Your external sources"
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
- Synced files will be linked with its linked source in media library
- Import of files during WooCommerce CSV supports now also usage of credentials (you could import files e.g. from FTP)
- Using new transient object in backend for hints and errors
- Cleanup the return value for external files via get_attached_file()
- File protocol uses now WP_Filesystem for each file interaction
- Enabled search field for URLs in logs
- External sources are now saved user-specific
  -> only administrators see all entries

### Fixed

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

### Removed

- Removed hook "eml_import_fields" as we do not use fields in this form anymore
- Removed hook "eml_import_url_after" as it could be better used via the hook "eml_after_file_save"

## [4.0.0] - 2025-05-14

### Added

- Added option to synchronize directories with files on every supported protocol
- Added support for the plugins CatFolders, Filebird and Folderly to import files in specific folders of this plugins
- Added support for the plugin Enhanced Media Library to add imported files to their categories
- Added wrapper for all settings of this plugin for easier management in the future
- Added custom intervals for any cron event this plugin delivers
- Added option for import local files from chosen directory via queue (for very large directories)
- Added support to add external SVG files

### Changed

- PHP 8.1 is now minimum requirement
- Optimized styling of list of directory services
- Optimized handling of import through directory services
- Moved import tasks from general Files in own object Import which is now also a directory listing object
- Multiple code optimizations
- Extended support for YouTube channel imports
- Extended help for using Imgur images
- Renamed Directory Credentials to Directory Archive
- Optimized hint if PHP-module zip is missing for the ZIP service
- More hooks

### Fixed

- Fixed error on import on any files from local hosting
- Fixed to early loading of translations
- Fixed error on GoogleDrive import which would break if a duplicate is detected
- Fixed potential error of sodium encryption is used and failed
- Fixed faulty check for existing schedules
- Fixed missing check for duplicate YouTube videos during import of them
- Fixed preview of files in FTP service (which also prevents the usage of FTP-files as service)
- Fixed wrong "Go to logs" URL if import failed
- Fixed typos

## [3.1.1] - 2025-04-05

### Added

- Added support for trashed media files

### Changed

- Optimized check if a mime type is allowed
- Extended hook documentation
- Updated compatibility with WordPress 6.8
- Small style optimizations in directory listings
- Some text updates

### Removed

- Removed not necessary check if GoogleDrive is usable in hosting

## [3.1.0] - 2025-03-02

### Added

- Added support for image meta-data (like caption) for external hosted images
- Added more hooks
- Added Third Party support for Block Editor and Elementor
- Added link to support forum on our plugin in plugin list
- Added link to support forum in add URLs dialog

### Changed

- Optimized the settings objects for future independence from this plugin
- Usage of external embedded YouTube URLs in Block Editor is now possible
- Choose your YouTube videos from local media library in Elementors Video widget
- Remove some unused codes

### Fixed

- Fixed refreshing of permalinks (which as been run on every request until now)
- Fixed potential error on WP CLI function to add Google Drive connection
- Fixed missing support for plugin PreventDirectAccess
- Fixed some typos

## [3.0.0] - 2025-02-15

### Added

- Added Services to platforms which host files, usable for local host, FTP, YouTube and ZIP-files atm
- Added management for credentials of these external platforms
- Added possibility to import files from Google Drive as external files
- Added possibility to add YouTube- and Vimeo-videos as external files
- Added possibility to import Youtube-Channel-Videos as external files via YouTube API
- Added marker for proxied files
- Added possibility to customize the handling of adding new URLs to media library with custom PHP
- Added more hooks
- Added GitHub action to build plugin releases
- Added log for deleting temp file during import

### Changed

- Do not download files via http protocol which should not be hosted locally
=> speed up the import & reduce space usage
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

### Fixed

- Fixed potential error with import of YouTube videos
- Fixed output of hint it file is not available
- Fixed potential error if URL is already on media library
- Fixed partially wrong saving of meta-data on external media files
- Fixed wrong check for already existing thumbs of external files
- Fixed styling of single URL upload field
- Fixed logging of deleted URL with its proxied path
- Fixed wrong quick start documentation URL

## [2.0.4] - 2025-01-26

### Fixed

- Fixed missing WooCommerce settings on activation

## [2.0.3] - 2024-12-15

### Changed

- Internal Test-release (not published on wp.org)

## [2.0.2] - 2024-11-23

### Added

- Added option to use the external file date instead of the import date

### Fixed

- Fixed hook documentations

## [2.0.1] - 2024-11-11

### Changed

- Small optimizations on texts for better translations
- GPRD-hint is now also shown for old installations if it is not disabled

### Fixed

- Fixed update handler for WordPress 6.7
- Fixed setting of capabilities for Playground
- Fixed setting of capabilities on update

## [2.0.0] - 2024-11-10

### Added

- Revamped plugin
- Added queue for importing large amount of URLs
- Added support for import of directories with multiple files
- Added support for different tcp-protocols
- Added support for FTP-URLs
- Added support for SSH/SFTP-URLs
- Added support for file-URL (to import from local server)
- Added support for credentials for each tcp-protocol
- Added wrapper to support third party plugins or platforms, e.g. Imgur or Google Drive
- Added support for Rank Math
- Added warning about old PHP-versions
- Added option to switch external files to local hosting during uninstallation of the plugin
- Added WP CLI option to switch hosting of all files to local or external
- Added documentation for each possible option in GitHub
- Added link to settings in plugin list
- Added migration tool to switch the external files from Exmage to this one
- Added thumbnail support for proxied images
- Added settings for videos which now can also be proxied
- Added import and export for plugin settings
- Added a handful help texts for WordPress-own help system
- Added multiple new hooks
- Added statistic about used files.
- Added warning regarding the GPRD of the EU (could be disabled)

### Changed

- Compatible with WordPress 6.7
- External files which are not provided via SSL will be saved local if actual website is using SSL
- Extended WP CLI support with documentation, progressbar, states and arguments
- Replaced settings management with optimized objects
- Optimized proxy url handling
- Optimized build process for releases
- Optimized transients of this plugin
- Optimized log table with much more options
- Replaced dialog library with new one
- Renamed internal transient prefix for better compatibility with other plugins
- Move support for already supported plugins in new wrapper

### Fixed

- Fixed some typos
- Fixed error with import of multiple files via WP CLI

## [1.3.0] - 2024-08-25

### Added

- Added possibility to switch the hosting of images during local and extern on media edit page
- Added new column for marker of external files in media table

### Changed

- Compatibility with plugin Prevent Direct Access: hide options for external fields

### Fixed

- Fixed some typos
- Fixed wrong proxied URL after successful import of images

## [1.2.3] - 2024-08-17

### Changed

- Updated dependencies

## [1.2.2] - 2024-06-05

### Changed

- Updated compatibility-flag for WordPress 6.6
- Updated dependencies

### Fixed

- Fixed potential error on attachment pages

## [1.2.1] - 2024-05-05

### Added

- Added support for hook of plugin "Download List Block with Icons" for mark external files with rel-external

### Changed

- Updated compatibility-flag for WordPress 6.5.3
- Updated dependencies

## [1.2.0] - 2024-04-14

### Added

- New import dialog with progress and extended info about the import

### Changed

- Show proxy hint on file only if proxy is enabled
- Optimized style for box with infos about external files
- Updated compatibility-flag for WordPress 6.5.2
- Updated dependencies

## [1.1.2] - 2024-03-06

### Fixed

- Fixed possible error during check for current screen
- Fixed usage of URLs with ampersand on AJAX-request

## [1.1.1] - 2024-03-04

### Changed

- Proxy-slug will now also be changed with simple permalinks
- Updated compatibility-flag for WordPress 6.5
- Updated hook documentation

### Fixed

- Fixed support for spaces in URLs
- Fixed typo in examples in hook-documentation
- Fixed possible notice in transient-handler
- Fixed usage of proxy with simple permalinks

## [1.1.0] - 2024-02-04

### Added

- Added multiple hooks

### Changed

- Prevent usage of plugin with older PHP than required minimum
- Optimized content type detection
- Optimized attachment title handling with special chars
- Updated compatibility-flag for WordPress 6.4.3
- Updated dependencies

## [1.0.2] - 2024-01-14

### Added

- Added hook documentation
- Added hint for hook documentation in settings

### Changed

- Optimized handling of upload-form if nothing has been added there

### Removed

- Removed language files from release

# [1.0.1] - 2023-10-21

### Changed

- Updated compatibility-flag for WordPress 6.4
- Compatible with WordPress Coding Standards 3.0

### Fixed

- Fixed error in settings-save-process
- Fixed typo in translations

## [1.0.0] - 2023-09-04

### Added

- Initial release

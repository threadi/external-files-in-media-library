# Changelog

## [Unreleased]

### Added

- Added help "Where do I find these details?" to the forms of the services and to the quickstart: where to get the access data, which permissions they need and typical pitfalls, with links to the instructions of the providers
- Added the help for FTP, FTPS and SFTP, Dropbox, YouTube, CSV and XML lists and ZIP files
- Added methods "get_setup_guide()" and "get_setup_guide_html()" for services and hook "efml_service_setup_guide"
- Added support for Instant Images: images from Unsplash, Pexels and GIPHY could be used as external files
- Added option to export new files in the background via the queue, incl. retries of failed exports and markers in the media library
- Files in the queue for their export are exported in the background right away, the oldest first, independent of the interval of the queue (it still applies to imports)
- Further attempts of failed exports and deletions in the queue run after growing wait times (5 minutes, 15 minutes, 1 hour, then every 4 hours), independent of the interval of the queue; the queue shows the time of the next attempt
- Added hook "efml_queue_retry_delays" to change the wait times between the attempts
- Files, whose type is not allowed for the export in the settings or whose export is prevented, do not wait for their export and are not added to the queue anymore; such entries of older versions are removed from the queue without error
- Added possibility to define the credentials of all services via constants (e.g. in wp-config.php): FTP, FTPS, SFTP, REST API, ZIP, CSV, XML, YouTube and the app credentials of Dropbox
- Constants of a service are only used if the constants for all its required fields are defined
- Credentials from constants are only used for connections to the server defined via constant
- Settings and user profiles show hints instead of input fields for values defined via constants
- The location of the credentials is fixed to "One for this website" if the credentials of a service are defined via constants
- Saved external sources on other servers than the one defined via constants are not usable as long as the constants are defined (no opening, import, synchronization or export), files imported before are still delivered
- URLs on other servers of a service with credentials via constants could also not be imported manually
- Services from extensions could define further fields for the target of the constants (e.g. a bucket) and their own mode for constants
- Added possibility to define several connections per service via constants, e.g. two FTP servers (list of names in EFML_FTP_CONNECTIONS and constants like EFML_FTP_SHOP_SERVER)
- If several connections are defined, the form for a new external source shows a field to choose the connection, it is saved with the source and its files
- Without a chosen connection, the connection matching the server or bucket is used
- Added documentation for the constants in docs/constants.md
- Added check for existing files with the same name on the external sources before a file is exported
- Added more hooks
- Added live tests with real accounts of all services for development (PHPUnit and Playwright, credentials in .env.live, see docs/live-tests.md)
- Added protection of external sources on copies of the website (e.g. staging sites): files are not exported to or deleted on external sources, and scheduled synchronizations are paused, reading and delivering of files is not affected
- Copies are detected by a changed address of the website or by a production website used as staging or development website, the actual website is saved as main website without any question
- Added hint for copies in the backend with the options to use the website as main website or to confirm it as copy
- Added WP CLI command "wp eml site" to show or set whether the website is the main website or a copy
- Added hook "efml_is_website_copy" to change the detection of copies
- Added overview of all services with their logos and links to their settings on the page "Services" in the settings
- Added optional folder structure for exported files (e.g. year and month of the upload), applies to all external sources whose service supports folders (local, DropBox, FTP and the extensions)
- The folder structure requires the setting of WordPress to organize uploads in month- and year-based folders and could be individualized via placeholders
- Added hooks "efml_export_folder" and "efml_export_folder_placeholders" to change the folders of exported files
- Added option "Save files privately" in the export settings of external sources, whose service supports it (DropBox and the extensions for S3, Google Cloud Storage, Google Drive and WebDAV): the files do not have to be public available on the external source
- Private files are always delivered via the URL of your website: from the cache of the proxy if the proxy is enabled for their file type, otherwise via a redirect to a link with limited validity (e.g. a pre-signed URL)
- Added setting for the validity of these links in the export settings, the links stay the same for this time, so browsers and caches could reuse them
- Added temporary links of DropBox for private files
- External files from the media library, which are used as files of downloadable products in WooCommerce, are delivered after the checks of WooCommerce: private files via a link with short validity or from the cache, public files from their external URL
- Private files used as files of downloadable products could only be downloaded via WooCommerce, the proxy does not deliver them to visitors anymore
- The info dialog of external files shows whether a file is private and in which products it is used as download
- Added hooks "efml_export_private", "efml_private_files_lifetime", "efml_private_file_signed_url", "efml_proxy_allow_delivery" and "efml_woocommerce_download_lifetime"
- The info box of external files links the platform where the file is saved, if it is known (e.g. DropBox and the extensions for S3, Google Cloud Storage and Google Drive)
- Added hook "efml_file_platform_url" to change this link
- The directory listing of YouTube links to YouTube, the info box of YouTube videos also
- Added connection test ("Run connection test") in the directory listing of all services, which could be used to export files (local directories, DropBox, FTP, FTPS and the extensions for AWS S3-compatible platforms, Google Cloud Storage, Google Drive and WebDAV): connect and list the directory, upload a test file, check its public availability and delete it again, each step is logged
- Added hook "efml_connection_test_file_name" to change the name of the test file
- Directories with many files are imported file by file (local directories, DropBox, FTP, FTPS, SFTP and the extensions for AWS S3-compatible platforms, Google Cloud Storage, Google Drive and WebDAV): their files are listed page by page without loading them and added to the queue, files which are already in the media library are skipped
- In the import dialog directories with more than 500 files are imported in the background after the first 20 files, with a hint in the dialog; the import in the background uses WP Cron and is independent of the settings of the queue
- The admin bar shows the progress of imports in the background, a click on it opens the queue
- Synchronizations use the same way and are continued in the background if necessary, files are only deleted after the complete list of the external source has been loaded
- Added hooks "efml_background_import_threshold", "efml_background_import_direct_count", "efml_import_run_time_limit", "efml_file_list_page_size", "efml_import_run_files", "efml_import_run_finished", "efml_import_job_id" and "efml_sync_completed"
- Added documentation for the import of directories with many files in docs/import/directories.md
- Added icons for the features of each service on the tiles to choose a service (synchronization, export, private files, access data via constants), with a filter by feature and a legend
- The icons use the SVG icons of WordPress 7.2 and newer, older versions show the matching Dashicons
- Service plugins, which are not installed, are marked as extension on the tiles
- Added help tab "What is possible?" with an overview of the features, links to their settings and documentations
- Added documentation of the features in docs/features.md
- Added search for settings, pages and documentations of this plugin in the command palette of WordPress (Ctrl + K)
- Services are found in the command palette also by other terms, e.g. "Nextcloud" for WebDAV or "Hetzner" for the AWS S3 extension, incl. services of extensions and of not installed extensions
- Added hooks "efml_service_features", "efml_service_feature_definitions", "efml_service_search_keywords", "efml_command_palette_pages" and "efml_settings_moved_tabs"
- Added quickstart as second tile on the page to choose a service: it guides step by step through the most common tasks (offload the media library, protected downloads, embed and synchronize external files, import local files, synchronize files from FTP)
- The quickstart shows the services, which support the chosen task, asks for their access data, checks the connection and configures the external source and the needed settings
- Access data entered in the quickstart are saved encrypted for the time of the quickstart only, passwords are never sent back to the browser
- DropBox and Google Drive (requires its extension 1.4.0) could be connected within the quickstart, afterwards the quickstart continues
- The quickstart explains, if a service needs no further details, e.g. a DropBox, which is already connected
- Added hooks "efml_setup_config", "efml_setup_goals", "efml_setup_option_definitions", "efml_setup_option_available", "efml_setup_apply_option", "efml_setup_run_action", "efml_setup_process_completed_text" and "efml_service_oauth_return_url"
- Added documentation of the quickstart in docs/setup.md
- The results in the dialog after an import use the same icons as the connection test and the quickstart
- Added migration from other offload plugins (WP Offload Media, Advanced Media Offloader, Stowaway, Offload Media – Cloud Storage) as goal of the quickstart: their offloaded files are used as external files without uploading them again
- The bucket, the region and the access data are taken over from the settings of the other plugin, also from its constants
- The other plugin is deactivated at the start of the migration after confirmation, its data and the local copies of the files are kept
- Files are migrated in the background with progress in the admin bar, a hint shows the result and links to the log if files could not be taken over or the migration has been cancelled
- The quickstart shows a hint if a supported offload plugin is active, but has not offloaded any files yet
- Local URLs of migrated files and their thumbnails in contents are replaced by their actual URLs during the output, the contents are not changed
- Added WP CLI command "wp eml migration" to show the state, to continue and to revert the migration
- Added hooks "efml_migrations" and "efml_migration_batch_size"
- Services could support the migration via the new methods "get_offloaded_object_meta_key()" and "get_url_of_offloaded_object()"
- Added documentation of the migration in docs/migration.md
- Text areas with access data of services (e.g. the authentication JSON of Google Cloud Storage) could be entered in the quickstart
- The quickstart offers services of extensions, which are not installed yet, and installs and activates the needed extension after confirmation, also for the migration from other offload plugins
- Failed installations of extensions show their reason and are logged
- Added the reason, why some services are provided by extensions, to the help, the installation dialog and the documentation in docs/extensions.md
- Added hook "efml_service_plugin_provided_services" for the services, which a not installed extension provides
- Fixed: the access data for the import of WooCommerce product images were also used for following imports in the same request
- Added button "Export now" in the export settings to export all local files in the media library, which are not exported yet, without choosing them
- The files are listed page by page and exported one by one in the background via WP Cron, independent of the settings of the queue; the progress is shown in the admin bar together with imports, a click on it opens the queue
- Files, which could not be exported, stay local files, are marked in the media library and stay as errors in the queue, where they could be exported again; a hint shows the result and links to the log and to the queue
- The export of the existing files is paused on copies of the website and cancelled with a hint, if the export is disabled in the meantime
- The quickstart offers to export the files, which are already in the media library, too, when offloading the media library or setting up protected downloads
- Added hook "efml_export_run_page_size" to change the number of local files, which are listed at once for their export
- Added search in the settings: a field at the top right filters the settings of the actual tab, the tabs show the number of their matches and a list shows the matches in other tabs and pages of the plugin (requires easy-settings-for-wordpress 3.10.0)
- The command palette also finds single settings, incl. other terms (e.g. "offload" for the export), and opens them highlighted
- Local URLs of exported files in contents (e.g. images inserted before their export) are replaced with their actual URLs during the output, incl. their sizes; the contents in the database are not changed
- The local paths of files exported before are taken from their upload URL in the background, so their local URLs are replaced, too
- Added hooks "efml_local_url_hooks" and "efml_local_urls_backfill_size"
- Local URLs of exported files are also replaced in the header and background image of the theme, in the additional CSS, with escaped slashes (e.g. in JSON settings of page builders) and without domain (e.g. "/wp-content/uploads/…" in style attributes)
- Requests for local files of exported files, which do not exist anymore, are redirected to their actual URL (e.g. images in the block editor or in CSS files of page builders), with a check in Site Health whether the web server forwards such requests to WordPress
- Added hook "efml_redirect_missing_uploads" to disable this redirection
- The block editor and the classic editor show exported images also with their old local URL, if the web server does not pass requests for missing files to WordPress; only the display is changed, the contents stay unchanged
- The check in Site Health explains how to pass requests for missing files to WordPress (also for locations of images in nginx and for the rules of Converter for Media)
- The proxy sends headers for caching: browsers and CDNs could cache public files requested with their version for one day (not longer than the proxy caches them), public files without version (e.g. URLs in contents) are checked on every use, private files and protected downloads of WooCommerce could only be cached by the browser; with ETag and Last-Modified, unchanged files are answered with "304 Not Modified" without their content
- The URLs of the proxy, which WordPress creates for the output (e.g. in "srcset" and for featured images), contain the version of the file, which changes with every change of the file (e.g. by image optimizers or Image Watermark), so browsers load changed files at once
- Added hook "efml_proxy_file_version" and methods "get_version()", "set_changed()" and "add_version()" for the proxy
- Added hook "efml_export_is_upload" and methods "mark_as_upload()" and "is_upload()" for exports
- Files from 50 MB are exported in the background right after their upload (also via the bulk action), even if the export in background is not enabled, so the upload is not interrupted by time limits of the web server; added hook "efml_export_large_file_size" and methods "get_large_file_size()" and "is_large_file()" for exports
- Files, which are not exported due to the extension "Export by file size", are logged with their size and the limit and marked in the media library; the settings show the limits also in MB
- The log names the reason, why the export of a file has been prevented (e.g. its size or a protection plugin), added hook "efml_export_prevent_reason" and method "get_prevent_reason()" for exports
- Added hook "efml_proxy_cache_control" to change the caching of files delivered by the proxy
- Services, which save it with the file (AWS S3 and S3-compatible storages, Google Cloud Storage), get the header "Cache-Control" for exported files: one day, with object versioning (timestamp in the folders) one week, private files none
- Added methods "get_cache_control()" and "is_object_versioning_enabled()" for exports and hook "efml_export_cache_control"
- The timestamp in the structure of the folders for exported files is named "object versioning"
- Added support for the plugin "Media Cleaner": external files are no longer shown as broken because of their missing local file, exported files are checked via their former local URLs (incl. their sizes), files imported from external sources are considered as used, local copies and working copies of exported files are not shown in its filesystem scan and external sources like FTP are not contacted during its scans
- Added hook "efml_media_cleaner_check_external_files" and methods "get_all_local_files()" and "get_attachment_of_local_file()" for local URLs
- Added support for the plugin "Media Bulk Download": external files are added to its ZIP files with their real name
- Added support for the plugin "Image Watermark": the watermark could be added to and removed from exported images, via a working copy, its backups are exported beside the image; the image of the watermark is not exported, as the plugin reads it only from the uploads directory, and restored as local file, if it has been exported before
- Added support for the plugin "Prevent Files / Folders Access": files of the protected types are not exported, while the restriction is enabled
- Added hint in Site Health, if Simply Static is active without pretty permalinks, as it does not copy the files of the proxy then
- Supported plugins are marked in the list of plugins (could be disabled in the advanced settings), incl. hook "efml_supported_plugin_files" and method "get_plugin_files()" for each support of other plugins
- Added documentation of all supported plugins in docs/plugins.md
- Added hook "efml_table_column_local_file" to show infos for local files in the media library
- The Playwright live tests name missing or wrong settings exactly (e.g. a not resolvable URL or a failed login) and are skipped, if the website is not configured, instead of failing with a missing "admin.json"; their documentation has been extended
- The Playwright live tests work independent of the language of the backend, fail at once with the text of a dialog, which shows an error, and are not stopped by Composer after 300 seconds anymore
- Added hint in the backend, if new files are not exported, because the service of an external source for exports is not available (e.g. its extension is not active), incl. the name of the extension
- Added methods "get_export_source_problem()", "is_export_source_usable()", "get_main_export_source()" and "get_export_sources_problem()" for exports and hook "efml_export_source_usable"
- Added button "Restore now" in the export settings and WP CLI command "wp eml localize" to restore all exported files as local files at their original path in the background, incl. their sizes with their former names: they are normal local files afterward, so the contents work also without the plugin; the export is disabled for this, the copies on the external sources are kept or deleted as chosen
- Added dialog on "Deactivate" in the list of plugins, if exported files exist: it offers to restore them first
- Added restore of the exported files during the uninstallation and the reset of the plugin, as far as the time allows; the reset is cancelled, if files are left, and restores them in the background first ("wp eml reset_plugin --force" or the link in the hint resets the plugin nevertheless)
- Added methods "set_external_file()" and "get_external_file()" for protocols: the protocol of an external file knows the file, e.g. to use the access data of the file independent of the actual user
- Added methods "is_import_allowed_for_user()" for protocols and "is_import_permitted()" for services: imports via a protocol are only possible, if the user is allowed to use its service (e.g. Dropbox), as the protocols of services are known in each request
- Added hook "efml_http_temp_file" to load the file for a URL in another way than via HTTP (e.g. via the API of a service)
- Added hooks "efml_file_restored" and "efml_restore_download_urls" and post meta "efml_local_sizes" with the names of the sizes of exported images; exported files from Dropbox are downloaded via the API for their restore
- Added export of the sizes of images and their original image together with them to external sources, whose services support it (local directory, FTP/FTPS, Dropbox and the extensions for AWS S3, Google Cloud Storage, WebDAV and Google Drive)
- The sizes are delivered exactly like the images themselves: via the proxy, if it is enabled for images (it loads them from the external source instead of creating them), otherwise from the external source; images get a srcset with their sizes, also in contents
- Private files deliver their sizes always via the proxy, which loads the exported size via the API of the service into its cache (or creates it from the image, if that fails); they get a srcset with the proxy URLs of the sizes
- Sizes of exported images could be created again with WordPress ("wp media regenerate", "Regenerate Thumbnails", wp_update_image_subsizes()): the original image is downloaded for this moment, the new sizes are exported and the local files are removed again; replaced sizes are kept on the external sources and deleted together with the image; only for users, who are allowed to edit the image, and not if WordPress requests the original image only for information (e.g. its name in the media library)
- Exported images could be edited in the image editor of WordPress (crop, rotate, scale, "Restore image"): the edited image is exported under its new name with its sizes, the former files are kept as backup and deleted together with the image; the block editor could crop and rotate exported images, too
- Exported images could be renamed by other plugins: "Media File Renamer" (the files are loaded for this moment), other plugins with access to the files (e.g. local directory as external source); the renamed files are exported, the former files are deleted on the external sources, the proxy still delivers the image via its former names
- Added hook "efml_deletion_queue_max_attempts"
- Added hook "efml_local_work_allowed"
- Uploads are exported reliably: if their export fails (e.g. timeout or rate limit while uploading many files), they are added to the queue and exported again; sizes, which could not be exported, are exported again 3 times every 10 minutes
- The export of uploads waits 5 minutes, if a plugin is active, which optimizes or renames the local files after the upload (Imagify, ShortPixel, Smush, EWWW Image Optimizer, TinyPNG, Converter for Media, Media File Renamer), so their optimized or renamed files are exported
- Added hooks "efml_export_upload_delay", "efml_export_upload_ready" and "efml_sizes_retry_max_attempts"
- Variants of images in other formats (WebP, AVIF), which image optimizers create, are exported together with the image and its sizes, deleted with it, follow renamed files and are restored with it (Imagify, EWWW Image Optimizer, ShortPixel, TinyPNG, Converter for Media, Modern Image Formats)
- The proxy delivers these variants in the format the browser supports under the same URL ("Vary: Accept"); without proxy the images are delivered in their own format, the HTML is not changed
- Sizes in another format than the image (e.g. WebP sizes of a JPEG via Modern Image Formats) are delivered via the proxy with their correct content type
- The export of uploads waits until Imagify or ShortPixel report the end of their optimization (one hour after the upload at most), EWWW Image Optimizer lets it run right after its optimization
- Added hooks "efml_image_variants" and "efml_image_variant_formats"
- TinyPNG: its variants (e.g. "image.webp") are exported, too, and the export runs right after its compression
- Smush: its backup of the image before the optimization (e.g. "image.bak.jpg") is exported, deleted and restored together with the image, so the original image is not lost after the export
- The media library shows "Will be exported" for uploads, whose export waits (e.g. for image optimizers, for the sizes from the browser or in the queue)
- The filter for external files in the media library shows files, which wait for their export or whose export has failed, and it is now available in the grid view, too
- Added the actions "Export again" and "Show errors" in the media library for files, which wait for their export or whose export has failed
- Added button "Delete local copies" in the export settings and WP CLI command "wp eml delete_local_copies" to delete the local copies of exported files, which have been kept during their export; only files, which exist on the external source, are deleted
- Added hook "efml_local_copies_file_usable"
- Exported images could be optimized later by image optimizers via local working copies: buttons "Prepare exported images for image optimizers" and "Export optimized images" in the export settings and WP CLI command "wp eml working_copies <create|finish|status>"
- Working copies contain the image, its sizes, its original image and the backups of image optimizers saved before; they are finished on their own, as soon as EWWW Image Optimizer, Imagify, ShortPixel, TinyPNG or Smush report the end of their work for the image
- Optimized files are exported with their names to the external sources, unchanged files (also backups) are not uploaded again, new files (e.g. WebP) are added and the local files are removed afterward
- EWWW Image Optimizer shows its actions for exported images in the media library, "Optimize now!" and "Re-optimize" save the working copy for this moment; Smush and TinyPNG use working copies saved before, also if their actions in the media library are used
- The backups of EWWW Image Optimizer, Imagify, ShortPixel and TinyPNG are exported with the image (e.g. "image.ewww-backup.jpg") to save storage space, saved locally again in working copies, so the image optimizer could restore the image, and deleted on the external source, if the image optimizer removes them
- Images with working copy are marked in the media library
- Added post meta "efml_working_copy" and optional "name" for variants of the hook "efml_image_variants"
- External files are found by their URL via the index of the database (e.g. during imports, synchronizations, REST, WP CLI and abilities): each external file gets a protected post meta with the hash of its URL in its key ("_efml_url_…"), which is kept together with the URL
- External files of older versions get this post meta in the background after the update, until then they are found as before
- Added hook "efml_url_index_backfill_size"
- Saved access data of import runs and queue entries, which could not be read, are logged as error (before, the files were imported without access data without any hint)
- Planned single events of this plugin in WP-Cron (e.g. delayed exports, retries of sizes, imports in the background) are removed, if their file is deleted or the plugin is deactivated, and planned again after its activation
- Lost single events of waiting uploads (e.g. if another plugin saves the events of WP-Cron at the same time) are planned again in the backend, due uploads are exported directly during WP-Cron (every 5 minutes at most); uploads are exported directly, as soon as the image optimizer reports the end of its work
- Added button "Export sizes now" in the export settings and WP CLI command "wp eml export_sizes" to export the sizes of images, which have been exported before, in the background
- Added instructions for AI agents to the repository (AGENTS.md, skills for site operators, developers and code reviews); they are not part of the plugin ZIP
- Added methods "supports_sizes()", "export_size()" and "delete_size()" for export objects of services and post meta "efml_export_sizes"
- Added methods "is_crypt_context_supported()" and "get_user_setting_crypt_context()" for services: services of extensions return true in the first one, if they read their user settings with the context
- Errors of the encryption name the affected value in the log ("Affected value", e.g. "option:eml_youtube_api_key")

### Changed

- Support for "Prevent Direct Access": protected files are not exported, external files could not be protected anymore and the column of the plugin is hidden per row instead of loading all external files on every page of the media library
- The actions of the export settings for all files ("Export now", "Restore now", "Delete local copies now", "Export sizes now", working copies) and the actions of the queue require the capability to manage the settings in addition (also the abilities "queue-process" and "queue-cleanup")
- The quickstart and the connection test require the capability to manage external files in addition
- Added hook "efml_local_work_information_callers" for functions, which request the original image of exported images only for information
- Encrypted values are bound to their purpose, e.g. the access data of external sources, files, runs and queue entries, the access tokens of Dropbox and the credentials of the services: an encrypted value copied into another field cannot be decrypted there; values encrypted by older versions stay readable and are bound to their purpose when they are saved again
- New uploads are exported right after Smush has optimized them, instead of waiting 5 minutes
- Optimized the database table of the queue for many entries: IDs as BIGINT with primary key (before about 8.4 million entries during the lifetime of the website), state as VARCHAR and indexes for state and type and for attachment and type; existing entries are kept during the update
- The WP CLI command "wp eml export" uses the same export as the button in the settings: only files with an allowed mime type are exported, files waiting in the queue are skipped and failed files are marked and listed in the queue
- Now also compatible with Windows-based hostings
- The dialog to install or activate the extension for AWS S3 now lists all supported platforms and its new features
- Optimized the spacing of the buttons for the predefined configurations in the settings
- All files of a directory, which are imported file by file, belong to one job
- The hook "efml_after_sync" runs after each part of a synchronization, which is continued in the background, the new hook "efml_sync_completed" only once at its end
- The email after a synchronization is sent only once at the end of the synchronization
- The hooks for the import of directories of the single protocols (e.g. "efml_ftp_directory_import_files") are not used for directories, which are imported file by file
- Connections to FTP, FTPS and SFTP servers are reused during a request
- The queue table loads only the entries of the actual page
- Settings are grouped by topics: "Delivery" (file types and proxy), "Status" (logs, queue and statistics), "Integrations" (WooCommerce, Polylang and Instant Images) and the permissions in "Advanced"
- Links to the previous addresses of moved settings tabs are forwarded to their new addresses
- The search for services and external sources in the command palette is case-insensitive and matches all words of the search
- Files are not exported, as long as the primary external source for exports could not be used (e.g. because the extension of its service is not active), also not to the other external sources; they stay local files and could be exported later via "Export now"
- External sources for exports are kept in the settings while their service is not available and marked as not usable at the moment

### Fixed

- Fixed missing log entries for errors of the encryption, if the directory of the plugin has another name (e.g. after the installation of a ZIP file from GitHub)
- Fixed proxy URLs in contents of files, which are no external files anymore (e.g. after their real import or the restore of exported files), resulting in "not found": the proxy redirects them to the actual URL of the file now, also for their sizes (hook "efml_former_proxy_url_redirect")
- Fixed export of existing local files, if other plugins create their sizes again (e.g. Regenerate Thumbnails or Image Watermark while removing a watermark): only new uploads are exported via their metadata now, which also prevented the removal of watermarks
- Fixed the proxy delivering the front page with HTTP status 200 instead of HTTP status 404 for files, which could not be delivered: the 404 template of the theme is used now
- Fixed files remaining in Dropbox, if no shared link could be created for an exported file (e.g. without the permission "sharing.write" of the app): the uploaded file is removed again and the log names the reason
- Fixed removal of finished import runs and their progress with SQLite (e.g. WordPress Playground)
- Fixed exported files on Dropbox and imported shared links of Dropbox being saved with the temporary URL of their content, which expires after 4 hours (afterward e.g. the proxy could not load them anymore): their shared link is saved now; files exported before are loaded via the Dropbox API (for visitors, e.g. via the proxy, only if Dropbox is connected for the whole website)
- Fixed export to Dropbox failing, if the file has already a shared link
- Fixed import of shared links of Dropbox by users without the capability for Dropbox: the preview page of Dropbox was loaded instead of the file ("The presumed directory URL does not contain any linked files."); shared links are public URLs, which do not use the connection to Dropbox, so each user who is allowed to add external files could import them
- Fixed private files of Dropbox not being delivered via the proxy to visitors and users without the capability for Dropbox, and export of uploads to Dropbox by such users: the protocol of Dropbox is known in each request now, imports from Dropbox still require the capability for Dropbox
- Fixed exported files of Dropbox, if each user has its own connection to Dropbox: they are loaded, exported and deleted with the connection of the owner of their external source, independent of the actual user (e.g. for visitors via the proxy, in the background or for other users); before, the connection of the actual user was used, which failed without a connection or used another Dropbox
- Fixed missing check whether the user is allowed to edit or delete the single file in these actions: deletion via REST API, switching between local and external hosting, reset of thumbnails, export of a file, real import (also as bulk action), check of availability, download of the data of a file and the abilities "switch-to-local", "switch-to-external", "reset-thumbnails" and "check-availability"
- Fixed users on the list "Prevent access for these users" keeping the capabilities of the services and tools (e.g. synchronization, export, connection test), now they can not use any function of this plugin
- Fixed the directory listing loading an external source of another user, if its ID is given
- Fixed missing external source during the import of single URLs from an external source in the dialog: e.g. files of Cloudflare R2 on a custom domain were handled as files of DigitalOcean Spaces (error 500)
- Fixed the deletion of exported files: they are deleted on each external source they have been exported to, also if the export has been disabled in the meantime (globally or for the external source) or the extension of its service is not active; files, which could not be deleted, are added to the queue and deleted later
- Local copies of exported files (kept after the export) are deleted together with the file
- Fixed the image editor of WordPress for exported images: it saved the edited image directly on the external source (local directory) or failed (other services), its sizes were not known to the plugin and stayed on the external source after the deletion of the image
- Several sizes of an image with the same file (e.g. portrait images) are deleted on the external source only once
- Fixed missing choice of the size ("Resolution") in the block editor for external and exported images: the REST API returns their sizes now
- Fixed export of images uploaded in the block editor with WordPress 7.1 or newer, which creates the sizes in the browser: the file was exported before its sizes existed, which have been saved in a directory named like the URL of the exported file and were missing in the metadata (no srcset); the export starts now after the sizes have been saved (after one hour at the latest, then the missing sizes are created on the server)
- Sizes created in the browser are rejected for files which are already external files
- Fixed the deletion of the local files after the export of a scaled image to a local directory, which could delete a file with the name of its original image there
- Fixed exported files and files taken over from another offload plugin being deleted during the uninstallation or the reset of the plugin, incl. their copies on the external sources: they are kept now, the option "Delete all data on uninstallation" applies only to external files added via their URL
- Fixed the help of the option "Delete all data on uninstallation" showing the description of the allowed MIME types
- Fixed option "Export file" and the bulk action for the export in the media library being shown, although the service of the external source for exports is not available (e.g. its extension has been deactivated)
- Fixed missing possibility to switch off the export of an external source, whose service is not available (e.g. its extension has been deactivated): the list of external sources shows the state with the reason
- Fixed button "Export now" being shown, although files could not be exported at the moment
- Fixed export dialog in the media library naming the last external source with enabled export instead of the primary external source
- Fixed export of existing files, WP CLI and the abilities using external sources for exports, whose service is not available
- Fixed: errors during the request of the DropBox account infos (e.g. if DropBox is not reachable) interrupted the request, e.g. of the settings or the quickstart
- Fixed database errors on multisites, if an attachment is deleted on a website, where this plugin has not been installed (e.g. during the export to another website)
- Fixed copying of log entries to the clipboard in the DataView of the settings
- Fixed import of external files as local files if their title is not a file name with extension (e.g. after changing the title)
- Fixed exported files with the same name overwriting each other on the external source, as the filename was only checked in the actual upload directory
- Fixed export of large files via FTP and FTPS failing at the memory limit, as the file was loaded completely into memory
- Fixed caching and delivery of large files via the proxy failing at the memory limit, as the file was loaded completely into memory (FTP, FTPS and local files, delivery of images, PDF, ZIP and other files)
- Fixed files which could not be loaded by browsers (e.g. exported via FTP or to a local directory) being embedded with their FTP-URL or path if the proxy for their file type is disabled: they are delivered via the public URL of their external source now (the URL for exported files in its export settings) and via the proxy, if no public URL is known
- Fixed proxy URLs resulting in 404 after enabling a proxy, if the rewrite rules have been flushed while all proxies were disabled
- Fixed missing check for existing files with the same name on the external sources for uploads outside the backend (e.g. via REST API in the block editor, WP-CLI or cron)
- Fixed reading of queue entries if their state is changed or they are deleted
- Fixed download of external files in WooCommerce if they have been chosen from the media library with their proxy URL, which WooCommerce tried to load as local file
- Fixed missing folder of exported files in the info "Exported to" of the file, if the folder structure for exports is used
- Fixed redirect of proxy URLs to their variant with trailing slash, if the permalinks end with a slash
- Fixed HEAD requests for proxy URLs (e.g. from link checkers or CDNs) being answered with an empty HTML document instead of the headers of the file
- Fixed high memory usage during the import of large files from DropBox and their loading into the cache of the proxy, they are now saved directly into a local file
- Fixed remaining empty temporary files after loading files from DropBox into the cache of the proxy
- Fixed high memory usage during the import of large files via SFTP and via FTP without native FTP connection (e.g. FTP sockets), they are now saved directly into a local file
- The command palette showed all external sources incl. those of other users and did not filter them by the search

- Fixed imports, which were terminated shortly before the max execution time: the rest of the import is now really added to the queue, before only the log said so
- Fixed deletion of error entries in the queue, which deleted only as many entries as set for one cycle of the queue
- Fixed import from DropBox in scheduled tasks without logged-in user (e.g. via the queue or WP Cron)
## [5.6.2] - 06.11.2026

### Changed

- Updated dependencies

### Fixed

- Fixed detection of URLs if proxy on every file type is disabled
- Fixed URL encoding if

## [5.6.1] - 24.10.2026

### Changed

- Updated settings library to be compatible with WordPress 7.2
- Optimized the styling of the file table
- Mark as compatible with WordPress 7.2

## [5.6.0] - 22.10.2026

### Added

- Added optional limits for the minimum and maximum file size of external files: global, per service and per file type (a limit of the service or file type replaces the global one, if both apply the stricter one is used)
- Added hint about these limits in the import dialog and error message for each file, which does not match them
- Added marking of files in the directory listing of services, which do not match these limits
- Added hooks "efml_file_import_error", "efml_file_size_limits", "efml_file_size_limits_services_without_size", "efml_service_main_settings", "efml_service_dropbox_hide_file" and "efml_service_multisite_hide_file"
- Added more PHP Unit tests and Playwright tests
- Added the file "bin/build-release.sh", which is used by the GitHub Action, Ant and Task to create the same release zip
- Added error message if the list of files in an FTP directory could not be loaded
- Added a hook "efml_ftp_use_pasv_address" to use the address an FTP server returns for passive mode
- Added a hint for FTPS servers, which do not allow the reuse of the TLS session for the data connection
- Added PHP Unit tests with real FTP, FTPS, SFTP and HTTP servers and a simulated WordPress REST API (see bin/test-servers.sh)
- Added PHP Unit tests for the export, the settings of synchronizations, the proxy, the security checks of all AJAX requests, archives and the hosting of external files
- Added "composer test-coverage" to show the code coverage of the PHP Unit tests
- Added setting for the maximum size of a single file extracted from ZIP, .tar.gz or .gz archives (default: maximum upload size of WordPress) and hook "efml_zip_max_file_size"
- Added log entry if an exported file is not saved on the FTP server, as a file with this name already exists there
- Added PHP Unit tests for the synchronization of ZIP files, YouTube channels and websites in a multisite, the directory listing of archives, the export and the checks of schedules and proxy
- Added "composer test-multisite" to run the PHP Unit tests for multisites
- Added a hook "efml_dropbox_client" to use another DropBox client (e.g. for tests)
- Added services to import URLs from CSV and XML files: upload a file or enter its URL (optionally with Auth Basic credentials), choose the URLs and import them
- Added automatic detection of the fields with URLs and of alternative text, caption, description and date in CSV and XML files (incl. sitemaps, RSS and Atom feeds, product feeds and WordPress exports), changeable in the import dialog
- Added option "Import URLs" for CSV and XML files in the media library (can be disabled in the settings of each service)
- Added automatic deletion of CSV and XML files used via the directory listing after configurable days without usage (can be disabled in the settings of each service)
- Added hint about the automatic deletion of these CSV and XML files in the media modal and as meta box on their edit page
- Added WP CLI command "wp eml cleanup_lists" to delete used CSV and XML files manually
- Added synchronization for CSV and XML files from URLs, which are downloaded again only if they have been changed
- Added hooks "efml_url_list_check_interval", "efml_url_list_max_size", "efml_url_list_max_records" and "efml_url_list_role_names" for the import of URLs from lists

### Changed

- Credentials of REST API external sources are only sent to the host of the external source
- Special handling for Imgur and Dropbox is only used for URLs on their domains
- Synchronization, export and their configuration dialogs check whether the user is allowed to use the external source
- Files in archives are read in parts and only up to the maximum size, instead of loading them completely into memory
- Archives only list and extract files with a type allowed in the settings, like all other external sources
- Optimized sync and export settings regarding their dependence
- Changed the second param on hook 'get_attachet_file' from int to mixed for better compatibility with some non-complying third-party-plugins
- Updated dependencies

### Fixed

- Fixed files with a not supported file type being visible in the directory listing of DropBox and Multisite, although hiding them is enabled in the settings
- Fixed error in the directory listing and import of Multisite for files without metadata
- Fixed credentials and hooks of a synchronization being used for following imports in the same request (e.g. further synchronizations via cron), which disabled their duplicate check and saved HTTP files locally
- Fixed error during the synchronization of SFTP directories (wrong callback for the file counter)
- Fixed deprecation of finfo_close() and curl_close() with PHP 8.5
- Fixed missing hint for FTPS servers without reuse of the TLS session with PHP 8.4 and newer
- Fixed database errors during the installation of the queue table with MySQL 8 (no default value for TEXT columns)
- Fixed settings of a synchronized external source (file types, dates) being used for other external sources synchronized in the same request, which could delete their files
- Fixed minimum and maximum file size in the configuration of a synchronization not being saved
- Fixed export action in media library being hidden if only a minimum or maximum file size for exports is set
- Fixed revert of the last import continuing after the redirect
- Fixed FTP import reading local files if the connection to the FTP server could not be established
- Fixed check for unsafe URLs staying disabled after errors during loading from an external REST API
- Fixed detection of the own website in REST API service for URLs without protocol or in other case
- Fixed import of single files from .tar.gz archives and import of not allowed file types from them
- Fixed handling of damaged ZIP and .tar.gz files
- Fixed one file too many being loaded from ZIP files in the import dialog
- Fixed .gz files not being removed from the allowed file types if they are disabled
- Fixed closing of foreign output buffers while reading .gz files
- Fixed missing credentials during import of WooCommerce product images
- Fixed warning in WP CLI import with login but without password
- Fixed wrong name of the deprecated hook "eml_schedule_interval"
- Fixed query for files in Exmage migration
- Fixed not existing intervals being saved for availability check and synchronization
- Fixed hiding of generic directories (e.g. "vendor") in local directory listing
- Fixed credentials with the value "0" not being saved
- Fixed invalid dates resulting in 1970-01-01 as file date
- Fixed JSON import being used if it is explicitly disabled
- Fixed preview of the import in the dialog preventing following imports in the same request
- Fixed files in trash being delivered by the proxy
- Fixed title of files with content-disposition header without file name or with path in it
- Fixed protection against deletion of synchronized files being disabled after deleting synchronized files
- Fixed release zip of the local build containing dev packages and an outdated Driver.js
- Fixed empty directory listing of FTP servers which return an unreachable address for passive mode (e.g. behind NAT)
- Fixed port in FTP and SFTP URLs and in URLs of files from HTTP directory listings being ignored
- Fixed links with absolute paths in HTTP directory listings
- Fixed synchronization of HTTP directories deleting unchanged files, if the deletion of unused files is enabled
- Fixed synchronization of WordPress REST API sources not importing any files, also via cron and WP CLI
- Fixed import reporting success although no file could be saved, which could result in the deletion of synchronized files
- Fixed import via SFTP not working
- Fixed import of not existing files via FTP resulting in entries without file
- Fixed empty file being left on the FTP server after a failed export
- Fixed synchronization of ZIP files, YouTube channels and websites in a multisite via cron and WP CLI
- Fixed synchronization of ZIP files not importing the files in them
- Fixed synchronization of YouTube channels not importing any video
- Fixed deletion of unused files and update marker after synchronization of external sources, whose name is not their URL (e.g. YouTube channels)
- Fixed YouTube videos not being shown to visitors without login
- Fixed availability check of YouTube videos via cron
- Fixed only the first directory of ZIP and .tar.gz archives being reachable in their directory listing
- Fixed missing subdirectories of .tar.gz archives in their directory listing
- Fixed import of the file in a .gz archive from its directory listing
- Fixed size of files in .gz archives being shown and saved compressed
- Fixed check for files in .tar.gz archives
- Fixed first external source with enabled export not being saved as main export source
- Fixed import of files from local archives in the allowed directory for local files
- Fixed synchronization and import of DropBox folders other than the root not importing any file
- Fixed import of single files from the listing of a saved external source with URLs without protocol (e.g. DropBox) using a wrong URL
- Fixed FTP connections failing if no timeout is set in the settings
- Fixed proxy not caching any file if the directory wp-content/cache does not exist, although it logged success
- Fixed change of the proxy path to a directory with not existing parent directories
- Fixed sized images of external files remaining in the proxy cache after deleting the file
- Fixed deleting of external files from FTP resulting in anonymous login attempts on the FTP server
- Fixed files in subdirectories of .tar.gz archives not being imported

## [5.5.1] - 17.10.2026

### Changed

- Hide the button to save setting on log tab
- Extended the logging of errors from Dropbox
- Optimized the visibility of dependent export settings
- Update dependencies

### Fixed

- Import via the dialog or form failed for URLs containing special characters like umlauts (affected all sources).

## [5.5.0] - 10.10.2026

### Added

- Added mp3 as additional allowed external file
- Added search field in directory listing for search for file names in the complete directory tree
  of the external source
- Added list of file sizes per service in statistics
- Added abilities for AI/MCP to list and check external files, to import multiple URLs, to manage external sources,
  the synchronization, the queue and the export, see [documentation](docs/abilities.md)
- Added settings for the abilities: enable or disable them and choose between "read only" (default) and "all"
- Added status of the abilities and hint for the needed MCP plugin in the settings
- Added some new hooks & PHP Unit tests

### Changed

- Advanced settings are divided into the sub-tabs "Plugin", "Files", "Abilities" and "Miscellaneous"
- The intro has its own section in the advanced settings in the sub-tab "Plugin"
- The intro now uses Driver.js instead of Intro.js
- Links to the settings in the import dialog are now leading to the general settings
- Proxies does use the already present mime-type instead of getting it again and again
- Logging what we get via HTTP
- Proxy settings for each file type is only visible if the files are saved external
- Optimized the abilities for interactions with AI/MCP
- Abilities return an error with the reason instead of 0 or false if they fail
- The ability to delete a URL also accepts the attachment ID and checks if the user is allowed to delete the file
- Optimized some texts
- Format bytes in statistics
- Hide generic directories (e.g., "vendor" and "node_modules") in local service
- Log entries and import results are reduced to a small set of allowed HTML elements
- Requests to the proxy for unknown files are only logged in debug mode
- External sources saved by other users can only be used by administrators (or if the setting to show all sources is enabled)
- Export settings, and the bulk export require the capability to use the export tool
- Changing the global DropBox connection requires the capability to manage the settings
- DropBox OAuth requests are now protected by a state parameter
- Multisite service checks whether the user is allowed to access the requested website
- Our post meta fields on attachments are now protected against manual changes
- Local files used as export target or temp file must be located in the allowed base directory
- Credentials are no longer written to the debug log and only exported by administrators
- Updated dependencies

### Fixed

- Fixed the events after loading the import dialog and after the import: the intro stopped after opening the import dialog and the sound after the import was not played
- Fixed fatal error if the ability to add a URL is used without attachment ID or with a login only
- Fixed wrong name of the ability category
- Fixed real import and usage of external file dates during import, which only worked if they were enabled for the
  synchronization (the real import created broken files in the media library otherwise)
- Fixed detection of URLs of files in ZIP-files by the ZIP protocol
- Fixed sum of file sizes per service in statistics, which only showed the size of a single file
- Fixed possible XSS via HTTP header of external files in the import result dialog
- Fixed missing output escaping of URLs in the log table and media modal
- Fixed fatal error in the frontend with WordPress older than 6.8 using the page builder Brizy
- Fixed fatal error for shortcodes without attributes with WordPress older than 6.5
- Fixed only first YouTube or Vimeo video per page being rendered
- Fixed plugin installation from a ZIP not stopping on failed checks
- Fixed deletion of synchronized files if the external source could not be read
- Fixed queue being blocked by URLs without credentials
- Fixed deletion of files on export targets for files, which were never exported
- Fixed export not being paused during switch to local hosting
- Fixed already exported files being exported again via bulk action
- Fixed type errors on core image filters if other plugins return unexpected values
- Fixed type errors with Brizy and Folders
- Fixed proxy not finding files with a hyphen and an "x" in their name
- Fixed thumbnails of squared sizes being re-generated on every request
- Fixed statistics being calculated on every request if no external hosted files exist
- Fixed the interval "every 20 minutes" running every 15 minutes
- Fixed wrong version parameter on enqueued files
- Fixed missing sync settings if Download List Block is active without any list
- Fixed search in the log for URLs with special characters
- Fixed to show the debug information in site health

## [5.4.0] - 30.08.2026

### Added

- Added our own list of intervals for schedules as expendable objects
- Added a hook to add custom result handler
- Added setting for max age of log entries
- Added new site health check for accessibility of the proxy folder

### Changed

- Log any server-side error during loading of a directory
- Use screen reader text for open new window hint
- Remove filter to use unsafe URLs after they have been used
- Videos are not proxied by default anymore to save this space
- Updated settings and dialog library
- Optimized some hooks with more parameters
- Optimized the switch to external hosting incl. more log entries
- Send optimized HTTP header via proxy
- Optimized deletion of old log entries
- Our custom log table is now compatible with strict database systems
- Secure proxy folder also in versions before 5.2.0
- Optimized check for max execution time
- Show the availability of services to export files
- Do not export not allowed mime types
- Typos

### Fixed

- Fixed a wrong placeholder to use an external link to Dropbox
- Fixed wrong capabilities for some term handlings
- Fixed check for development mode in WordPress < 6.3
- Fixed missing set of fields during protocol detection for files
- Fixed JavaScript to show any error during hosting switch
- Fixed error in calculation for Audio files in proxy
- Do not load support for PreventDirectAccess on every page in the backend
- Fixed export of files to Dropbox
- Fixed missing thumbnails after image has been uploaded and exported to external service

## [5.3.0] - 16.08.2026

### Added

- Added support for image handling through the page builder Brizy
- Added smoke test to check if fresh released plugin can be activated
- Added logging of crypt errors
- Added a hint during loading of the listing

### Changed

- Now PHP 8.2 or newer is required
- Compatible with WordPress 7.1
- Set refresh of permalinks via notice action on installation
- Optimized support for plain permalinks with Dropbox
- Updated dialog lib to 2.0.0
- Updated directory listing lib to 4.1.3
- Updated settings lib to 3.1.2
- Updated crypt lib to 3.0.0
- Updated all other dependencies

### Fixed

- Fixed missing psr library in releases
- Fixed wrong check for capabilities on saving terms as external sources

## [5.2.3] - 29.07.2026

### Changed

- Use encryption for the globale Dropbox token
- Updated dependencies

### Fixed

- Fixed missing permission check of the abilities (thanks to [Philipp Beick](https://www.linkedin.com/in/philipp-beick/))

## [5.2.2] - 27.06.2026

### Changed

- Ignore protocol handler without support for availability check for file check schedule
- Use user object instead of get_user() for better compatibility with older WordPress
- Optimized usage of capability checks
- Updated dependencies

### Fixed

- Fixed handling of FTP-URLs

## [5.2.1] - 19.05.2026

### Changed

- Updated dependencies

### Fixed

- Fixed missing crypt library in release

## [5.2.0] - 06.05.2026

### Added

- Added files to secure the direct access to the cache directory of the proxy
- Added compatibility with the new WordPress desktop mode

### Changed

- Optimized documentation if check for duplicates is disabled during synchronisation (they are not imported twice, however)
- Optimized option to hide the welcome notice
- Optimized security for handling of local files in hosting
- Prevent usage of credentials for URLs in the import dialog if the website does not use SSL
- Extended the dialog after saving an external source with more options
- Optimized output of YouTube URLs
- Updated support for Abilities API
- Updated dependencies

## [5.1.0] - 25.04.2026

## Added

- Added support to import and synchronize external images in galleries
  from the plugins "NextGen Gallery" and "Robo Gallery"
- Added 2 command palette commands to quick jump to external services or sources
- Added support for Abilities API for add and remove URls from the media library
- Added PHPUnitTests for uninstallation of the plugin

## Changes

- Small optimizations for the multisite support
- Changed initialization of the plugin during activation and deactivation for better compatibility with other plugins
- Optimized the uninstallation of services
- Optimized the uninstallation of settings
- Optimized permalink refresh after plugin activation
- User-token for Dropbox as now also deleted on uninstallation
- Updated the blueprint.json for an optimal preview in the playground

### Fixed

- Fixed missing hint for not existing synchronisation support for local external sources
- Fixed wrong spelled plugin slugs in some hooks
- Fixed check for file type and size on sync even if these extensions are not enabled

## [5.0.3] - 18.04.2026

### Fixed

- Fixed some issues regarding composer packages used by this plugin

## [5.0.2] - 13.04.2026

### Changed

- Show warning if SSL is not available in hosting for using Dropbox (necessary for OAuth)
- Updated dependencies

### Fixed

- Fixed a potential error for missing function wp_get_current_user() if other plugins running "WP_Query" without being embed in any hook
- Fixed missing setting where Dropbox could be connected

## [5.0.1] - 29.03.2026

### Changed

- Some typos
- Using updated settings library
- Updated compatibility with PHPStan
- Update dependencies
- Set compatibility with WordPress 7.0

### Fixed

- Fixed the missing translation for some service titles

## [5.0.0] - 07.03.2026

### Added

- Added an intro for first time users of this plugin
- Added REST API support for handling of external URLs from other WordPress-projects in your media library
- Added support for a service plugin to use AWS S3 as external source of files
- Added support for DropBox as external source of files
- Added support for a service plugin to use Google Cloud Storage as external source of files
- Added support for a service plugin to use Google Drive as external source of files
- Added support to use files from other websites in your multisite
- Added support for a service plugin to use WebDav as external source of files, e.g., usable with your Nextcloud
- Added a hook to import external URLs through third party plugins (for custom development)
- Added new URL-import dialog in backend
- Added option to delete synchronized files of single directory archive with one click
- Added option to export each newly uploaded file in media library to external sources, which are reachable via web
-> optionally, you can delete the local files, thereby outsourcing (offloading) all your files and saving storage space
- Added option to export each file in media library to external source as described above
- Added option to export only files with specific file types or sizes, configurable as global setting for per external source
- Introduced file handling extensions and added 3 of them (date, queue, real import)
- Added option to use the date of external files in add-dialog (2nd file handling extension)
- Added option to actually import files in media library (this disables all external files functions for these files)
- Added option to choose a specific date for each file to import
- Added options to choose extensions for export, import and synchronization and there global or custom settings
- Added paginated AJAX-import to prevent timeouts for some supported external sources
- Added these 3 new options also as parameter on WP CLI command to import URLs
- Added new options for WP CLI to manage external sources
- Added option to choose, which of these extensions should be available for the handling of files
- Added file type specific icons in directory listings
- Added unique identifier for each import to prevent to avoid confusion when multiple users and imports
  are occurring simultaneously
- Added import date for each external URL
- Added new table column in media library, which shows basic URL information
- Added Taskfile as third way to build plugin release
- Added check for PHP strict usage on every release with PHPStan
- Added check for compatibility with WordPress Plugin Checker on every release
- Added support for download lists of the plugin "Download Lists with icons" incl. sync of them
- Added support for the plugin "Folders" to import external files in its folders incl. sync of them
- Added support for the plugin "Media Library Organizer" to import external files in its categories incl. sync of them
- Added support for the plugin "iFolders" to import external files in its folders incl. sync of them
- Added support for the plugin "Real Media Library Lite" to import external files in its folders incl. sync of them
- Added support for the plugin "Advanced Media Offloader" to prevent the offloading of already external files
- Added support for the plugin "Media Library Assistant" to import external files in its folders incl. sync of them
- Added compatibility with the plugin "Media Cloud Sync"
-> do not sync external files with external clouds
-> do sync real imported external files
- Added new file types "PDF" and "ZIP" for better supporting the handling of these files
- Added info about external files in attachment modal
- Added option to use the files dates during synchronization
- Added option to import real files during synchronization (they are just imported if they are no duplicate)
- Added option to run synchronisation for selected external sources via WP CLI
- Added privacy hint as checkbox in every import dialog, configurable in user settings
- Added WordPress Importer entry
- Added info in admin footer for pages provided by the plugin or for which it makes extensions on the called pages
- Added support for plugin "WP Extra File Types" to enabled additional possible file types to use as external files
- Added option to load upload directory via local service
- Added option to use our plugin name in each HTTP-header User Agent (default enabled)
- Added success sound after import has been run (can be disabled)
- Added option to reset the plugin in backend settings (in preparation for Cyber Resilience Act)
- Added option to unzip from an external password protected ZIP-files
- Added options to open and extract zip-files, which are already saved in media library
- Added support to open and extract multiple zip formats: .zip, .gz, .tar.gz
- Added support for .avif files
- Added option to show what will be done in import dialog
- Added support for filenames in other writing systems (like Farsi)
- Added option to hide the review begging
- Added new extension to allow import and export of external files in JSON-format (default disabled)
- Added export and import of settings for external sources
- Added options for manage export settings via WP CLI
- Added support to generate attachment entries for each external file with Polylang
- A unique job ID has been added to each imported file to enable filtering of imported external files in a single task
- Added PHP unit tests for essential functions of this plugin
- Added SBOM generation on GitHub for each release
- Added support to import service plugins, which provide support for more platforms not bundled in the main plugin
- Added support to install WordPress plugins from external sources
- Added support for multisite-installations: manage service plugins in your network
- Added capability sets to fast configuration of capabilities for each tool and service
- Added option to revert the last import
- Added configurations to quickly configure the plugin for a specific task
- Added output of all (not private) settings in site health report
- Added site health checks for cronjobs, and the availability of external sources
- Added option to export the installation hash key, which is used for any encryption

### Changed

- Changed ALL hooks to prefix with 4 characters to match WordPress Coding Standards
- Compatibility with WordPress 6.9
- Renamed "Directory Archive" to "Your external sources"
- Show processed file URLs during manual started synchronization
- Hide import button for unsupported files in directory archive
- Small optimizations on multiple codes
- Using fallback to default interval for each our events if setting of not available
- Show hide and rating on directory archive listing
- Show hint of user has configured Google Drive in a version before 5.0.0 to install the new separate plugin
- Directory reload no shows the progress
- Optimized ZIP service: no also allows to extract complete ZIP files in media library
- Updated some unfavorable text descriptions
- Updated crypt-methods for any privacy data
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
- Extended the documentation in GitHub for all services we provide
- Extended logging is automatically enabled if WordPress is running in development mode
- Moved availability check in extension
- Renamed filter "eml_import_url_before" to "eml_import_url"
- Renamed filter "eml_blacklist" to "eml_prevent_import"
- Hosting of files can now only be changed by users with the capability to upload external files
- Synced files will be linked with its linked source in media library
- Prevent deletion of sources, which has active synced or exported files
- Import of files during WooCommerce CSV supports now also usage of credentials (you could import files e.g. from FTP)
- Using new transient object in backend for hints and errors
- Cleanup the return value for external files via get_attached_file()
- File protocol uses now "WP_Filesystem" for each file interaction
- Enabled search field for URLs in logs
- Dropbox file URLs can now be imported without any API key if they are public available
- External sources are now saved user-specific
  -> only administrators see all entries
  -> advanced option allows to show all entries for alle users
- Settings for most services are now saved on user and not global, but can be set to global
- External sources can now get an individual name
- ZIP files cannot also be opened via any supported TCP protocol
- Save used service on each external file
- Any local path will be sanitized
- Optimized URL shortener
- Now also usable as "must use"-plugin (requires a additional file to enable it)

### Fixed

- Wrong usage of import URLs from directory archives if they are using a path after the domain
- Fixed wrong link to queue list in settings and in dialog
- Fixed missing file on FTP listing if the previous files thumbnail could not be created
- Fixed missing file preview if PHP-imagick-library is not used
- Fixed usage of ZIP service on single uploaded file
- Fixed wrong capability to access the directory archive for non-administrator users
- Fixed disabling of check files event
- Fixed detection of correct file type during import process
- Fixed potential error with attached files if they do not exist
- Fixed missing visible progress-bar during synchronization
- Fixed missing saving of actual availability of each file (all were available any time)
- Fixed REST API endpoints to not using WP_Error for responses
- Fixed WP CLI command to delete error entries from queue

### Removed

- Removed the hook "eml_import_fields" as we do not use fields in this form anymore
- Removed the hook "eml_import_url_after" as it could be better used via the hook "eml_after_file_save"
- Removed support for Google Drive in the main plugin, a second service plugin is now necessary to use it

## [4.0.0] - 2025-05-14

### Added

- Added option to synchronize directories with files on every supported protocol
- Added support for the plugins CatFolders, Filebird and Folderly to import files in specific folders of this plugins
- Added support for the plugin Enhanced Media Library to add imported files to their categories
- Added a wrapper for all settings of this plugin for easier management in the future
- Added custom intervals for any cron event this plugin delivers
- Added option for import local files from the chosen directory via the queue (for very large directories)
- Added support to add external SVG files

### Changed

- PHP 8.1 is now minimum requirement
- Optimized styling of list of directory services
- Optimized handling of import through directory services
- Moved import tasks from general Files in own object Import, which is now also a directory listing object
- Multiple code optimizations
- Extended support for YouTube channel imports
- Extended help for using Imgur images
- Renamed Directory Credentials to Directory Archive
- Optimized hint if PHP-module zip is missing for the ZIP service
- More hooks

### Fixed

- Fixed error on import on any files from local hosting
- Fixed to early loading of translations
- Fixed error on GoogleDrive import, which would break if a duplicate is detected
- Fixed potential error of sodium encryption is used and failed
- Fixed faulty check for existing schedules
- Fixed missing check for duplicate YouTube videos during import of them
- Fixed the preview of files in FTP service (which also prevents the usage of FTP-files as service)
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

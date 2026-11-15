# What is possible?

External Files in Media Library does more than adding single URLs to your media library. This page shows the features by goal and leads you to the settings and the detailed documentation.

**Not sure where to start?** The quickstart guides you step by step through the most common tasks and configures everything for you: Media → "Add External Files" → tile "Quickstart". See [quickstart](setup.md).

## I want to …

### … use files from other locations in my media library

Add files via their URLs or choose a service, e.g. FTP, Dropbox, YouTube, another WordPress website or, with extensions, AWS S3, Google Drive or WebDAV. The files are used like local files, but stay on their external source.

* In the backend: Media → "Add external files" or "Add New Media File"
* Documentation: [Quick start](quickstart.md), [protocols](protocols.md), [import of single files and directories](import/directories.md)

### … synchronize directories automatically

Save a directory as external source and let new, changed and deleted files be synchronized at an interval you choose. Large directories with thousands of files are imported in the background.

* In the backend: Media → "Your external sources", settings → "Synchronization"
* Documentation: [Directories](import/directories.md)

### … offload my media and save storage space

Export new uploads and existing files to an external source, e.g. AWS S3 and S3-compatible storages (Hetzner, IONOS, Wasabi …), Google Cloud Storage, FTP, Dropbox or WebDAV. Afterward, they are used as external files and take up no space on your hosting. Optionally, the files could be sorted into folders by year and month.

With the option "Export in background" in the export settings, new uploads are not exported during the upload, but right away in the background, the oldest first; until then, they are used as local files. Files, which are already in your media library, are exported with one click: settings → "Export" → "Export existing files". They are exported one by one in the background, the admin bar shows the progress. A click on it opens the queue, where you could see each file and export files again, which could not be exported. The quickstart offers this as option, too.

Images are exported together with their sizes and their original image, if the service of the external source supports it (local directory, FTP, Dropbox, AWS S3 and S3-compatible storages, Google Cloud Storage, WebDAV, Google Drive). The sizes are delivered exactly like the images themselves: via the proxy of your website, if it is enabled for images, otherwise from the external source. Your images get a `srcset` with their sizes. Private files deliver their sizes always via the proxy. Images, which have been exported before, get their sizes via settings → "Export" → "Export sizes of exported images". If you create the sizes of exported images again, e.g. with `wp media regenerate` or the plugin "Regenerate Thumbnails", the plugin downloads the original image for this moment, exports the new sizes and removes the local files again. Sizes which have been replaced stay on the external source, as your contents could still use them, and are deleted together with the image. The same applies to the image editor of WordPress (crop, rotate, scale and "Restore image"): the edited image is exported under its new name, the former files stay as backup on the external source. The block editor could crop and rotate exported images, too. Plugins, which rename files, are supported, too: "Media File Renamer" with every external source, other plugins if they could reach the files (e.g. with the local directory as external source or with local copies kept after the export). The renamed files are exported, the former files are deleted on the external source. If you delete an exported file, it is deleted everywhere: on each external source it has been exported to (also if its export has been switched off in the meantime), with its sizes and former files, and its local copies. Files, which could not be deleted at the moment (e.g. the extension of the service is not active), are deleted later via the queue (settings → "Status" → "Queue"). If the export of a new upload fails (e.g. because the external source is not reachable for a moment or many files are uploaded at once), the file stays local and is exported again via the queue; sizes, which could not be exported, are exported again three times every 10 minutes. If a plugin is active, which optimizes or renames the local files after the upload (Imagify, ShortPixel, Smush, EWWW Image Optimizer, TinyPNG, Converter for Media, Media File Renamer), new uploads are exported 5 minutes later, so the optimized or renamed files are exported. Image optimizers, which create variants of your images in modern formats (WebP, AVIF) beside them (Imagify, EWWW Image Optimizer, ShortPixel, TinyPNG, Converter for Media, Modern Image Formats), are supported, too: the variants are exported together with the image and its sizes, deleted with it and restored with it. The proxy delivers them to browsers, which support their format, under the same URL as the image; without proxy, the images are delivered in their own format. For Imagify and ShortPixel, which optimize via their API, the export waits until they report the end of their optimization. Uploads, whose export still waits, are marked with "Will be exported" in the media library. Since WordPress 7.1 the block editor creates the sizes of uploaded images in your browser: such uploads are exported as soon as the browser has saved their sizes (if the browser is closed before, after one hour with sizes created on the server).

Files, which you inserted into your contents before their export, keep working: their local URLs (also of their sizes) are replaced with their actual URLs during the output. Your contents in the database are not changed.

Do you want to stop the offloading or deactivate the plugin? Restore the exported files with one click: settings → "Export" → "Restore exported files". They are downloaded and saved as local files at their original path, their sizes are created again. Afterward they are normal local files and your contents work also without the plugin. The export is disabled for this, the copies on the external sources are kept or deleted, as you choose. If you deactivate the plugin while exported files exist, a dialog offers the restore first. During the uninstallation and the reset of the plugin the exported files are restored, too, and they are never deleted.

If the primary external source for exports could not be used (e.g. because the extension of its service has been deactivated), no files are exported, also not to the other external sources. They stay local files, a hint in the backend names the reason. If you do not need the external source anymore, switch off its export in the list of your external sources. Your settings are kept: as soon as the reason is gone, the export works again and "Export existing files" exports the files uploaded in the meantime.

* In the backend: settings → "Export", then enable the export for an external source
* Documentation: [Directories](import/directories.md) for the external sources, the documentations of the extensions for their services

### … switch from another offload plugin

Files, which WP Offload Media, Advanced Media Offloader, Stowaway or Offload Media – Cloud Storage have offloaded, could be used as external files without uploading them again. Local copies and the data of the other plugin are kept.

* In the backend: quickstart → "Switch from another offload plugin"
* Documentation: [migration](migration.md)

### … deliver files privately

Save exported files in storages, which are not public. They are delivered via the proxy of your website or via temporary links. This also works for downloads in WooCommerce.

* In the backend: "Save files privately" in the export settings of an external source, settings → "Delivery" → "Proxy"
* Documentation: [Private files](private-files.md)

### … keep access data out of the database

Define the access data of the services as constants in your `wp-config.php`, also for several connections per service (e.g. two FTP servers).

* In the backend: settings → "Services" shows which constants are used
* Documentation: [Credentials via constants](constants.md)

### … work safely with copies of my website

On copies of your website (e.g. a staging website), files are not exported to or deleted on external sources and scheduled synchronizations are paused. This works automatically. Reading and delivering files is not affected.

* In the backend: a hint appears on a copy, with the choice to use it as main website
* Documentation: [WP CLI](cli.md) (`wp eml site`)

### … automate tasks

* [WP CLI](cli.md) for imports, synchronizations and maintenance
* [REST API](rest.md) to manage external files from other systems
* [Abilities](abilities.md) for AI agents and the MCP adapter

## Find things quickly

* **Quickstart:** Media → "Add External Files" → tile "Quickstart" guides you through the most common tasks, see [quickstart](setup.md).
* **Choose a service:** Media → "Add external files" shows all services as tiles. Icons show which features each service offers (synchronization, export, private files, access data via constants). Use the filter "Feature" above the tiles to show only services with a specific feature. Services from extensions, which are not installed, are marked as "Extension" and could be installed there. Why some services need an extension is explained in [extensions](extensions.md).
* **Command palette:** Press `Ctrl + K` (`Cmd + K` on macOS) on any page of the backend and enter e.g. "FTP", "S3", "Export" or the name of one of your external sources. Services are also found by other names, e.g. "Nextcloud" for WebDAV or "Hetzner" for the S3 extension. The command palette is available on all pages of the backend since WordPress 6.9. It also finds single settings, e.g. "offload" or "timeout", and opens them highlighted.
* **Search in the settings:** The field at the top right of the settings filters the settings of the actual tab while typing. The tabs show the number of their matches, a list below the settings shows the matches in other tabs and pages of this plugin. Settings, which are only shown if another setting is enabled, are listed with a link to this setting.
* **Help:** The tab "Help" at the top right of the pages of this plugin starts with "What is possible?".

## Structure of the settings

| Tab | Contains |
|---|---|
| General Settings | Basic settings for the import and the dialog |
| Services | Overview of all services and their settings |
| Delivery | Settings for each file type (images, videos, PDF …) and for the proxy |
| Export | Export of files to external sources |
| Synchronization | Synchronization of external sources |
| Advanced | Files, plugin, abilities, miscellaneous, permissions |
| Status | Logs, queue, statistics |
| Integrations | Settings for other plugins like WooCommerce, Polylang or Instant Images (only if one of them is active) |

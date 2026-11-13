# What is possible?

External Files in Media Library does more than adding single URLs to your media library. This page shows the features by goal and leads you to the settings and the detailed documentation.

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

* In the backend: settings → "Export", then enable the export for an external source
* Documentation: [Directories](import/directories.md) for the external sources, the documentations of the extensions for their services

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

* **Choose a service:** Media → "Add external files" shows all services as tiles. Icons show which features each service offers (synchronization, export, private files, access data via constants). Use the filter "Feature" above the tiles to show only services with a specific feature. Services from extensions, which are not installed, are marked as "Extension" and could be installed there.
* **Command palette:** Press `Ctrl + K` (`Cmd + K` on macOS) on any page of the backend and enter e.g. "FTP", "S3", "Export" or the name of one of your external sources. Services are also found by other names, e.g. "Nextcloud" for WebDAV or "Hetzner" for the S3 extension. The command palette is available on all pages of the backend since WordPress 6.9.
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

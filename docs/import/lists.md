# Importing URLs from CSV and XML files

This section describes how you can import the URLs contained in a CSV or XML file into your media library.

## Supported files

* CSV files with any delimiter (comma, semicolon, tab or pipe). The first row is used as header if it does not contain any URL. Values in UTF-8, UTF-16 or Windows-1252 (e.g., from Excel) are supported. A cell may contain multiple URLs, separated by comma, semicolon, pipe or whitespace.
* XML files like sitemaps (also with images and videos), RSS and Atom feeds, product feeds (e.g., for Google Shopping), WordPress exports and any other XML file with URLs. XML files with a DOCTYPE are not supported for security reasons.

## Import via the services

1. Go to Media > Add External Files and click on the service "CSV" or "XML".
2. Upload a file or enter the URL of a file. Only one of both is possible.
   * An uploaded file is saved in your media library.
   * A file from a URL is downloaded into your media library. If the URL is protected via Auth Basic, enter the login and password. If you open the URL again, it is only downloaded again if it has been changed.
3. Each field with URLs (a column of the CSV or a node of the XML) is shown as directory. Fields with URLs of files are shown first.
4. Import single URLs, a complete directory or all URLs via "Import active directory" on the first directory.
5. In the import dialog you choose which fields contain the URLs to import and which fields are used for alternative text, caption, description and date of the imported files. The plugin detects them automatically. Your choice is saved with the file for the next imports.

The files in your media library are never changed by the plugin. Files, which have been uploaded or downloaded for the services, are deleted automatically once a day if they have not been used for 7 days (configurable in the settings of each service). The last downloaded version of a URL saved as external source is kept. Files you add to your media library yourself are never deleted. These files show a hint about the automatic deletion in their details. You can also delete them via WP CLI with `wp eml cleanup_lists` (see [WP CLI](../cli.md)).

## Import via the media library

Each CSV and XML file in your media library has the option "Import URLs" in the list view and in its details. It opens the import dialog for all URLs of this file. You can disable this option in the settings of each service.

## Synchronization

A file from a URL can be saved as your external source. It can then be synchronized automatically. During each synchronization the URL is checked for changes. If it has been changed, the new version is downloaded into your media library and used.

## Credentials

The login and password are saved with the external source. They are only sent to the host of the file with the URLs, never to other hosts (also not after redirects). Files from the list on the same host are loaded with the credentials and saved locally.

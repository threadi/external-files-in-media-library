# Extensions for further services

Some services are not provided by the plugin itself, but by a free extension. Install it with one click in the quickstart or in the list of services (Media → "Add external files").

## Why extensions?

The main reason is the license: plugins in the WordPress plugin directory must be compatible with the license of WordPress (GPLv2 or later), including all libraries they contain. Some providers offer their libraries for the access to their services only under licenses, which are not compatible with it. The AWS SDK for PHP for example uses the Apache License 2.0, which is not compatible with the GPLv2. Such libraries may not be part of this plugin, so the affected services are provided by extensions on GitHub.

In addition there is the size: with all libraries, the plugin would be too large for the WordPress plugin directory. The extensions only contain the libraries, which you really need for your service.

## The extensions

| Extension | Services | Source |
|---|---|---|
| External Files from AWS S3-compatible platforms | AWS S3, Cloudflare R2, Backblaze B2, DigitalOcean Spaces, Hetzner, IONOS, OVHcloud, Scaleway, Wasabi and further S3-compatible storages | [GitHub](https://github.com/threadi/external-files-from-aws-s3) |
| External Files from Google Cloud Storage | Google Cloud Storage | [GitHub](https://github.com/threadi/external-files-from-google-cloud-storage) |
| External Files from Google Drive in Media Library | Google Drive | [GitHub](https://github.com/threadi/external-files-from-google-drive) |
| External Files from WebDav | WebDAV, e.g. Nextcloud | [GitHub](https://github.com/threadi/external-files-from-webdav) |

## Installation

* **In the quickstart:** Choose the service, also if its extension is missing. In the next step you confirm the installation, the quickstart loads the newest release from GitHub, installs and activates it and continues with the access data afterwards. This also applies to the [migration from other offload plugins](migration.md).
* **In the list of services:** Services from extensions are marked as "Extension" there and could be installed with one click.
* **Manually:** Download the ZIP file of the newest release from the GitHub repository of the extension and install it under Plugins → Add New → Upload Plugin.

The installation requires the permission to install plugins. In a multisite network, the administration of the network installs the extensions. If the installation fails, e.g. because your server could not reach GitHub or the plugin directory is not writable, the reason is listed in the log of the plugin.

You can deactivate and delete an extension at any time. This removes the associated files from your media library.

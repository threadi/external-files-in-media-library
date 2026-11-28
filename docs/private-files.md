# Prevent direct access at the source

Exported files do not have to be public on the external source. With the option **"Prevent direct access at the source"** in the export settings of an external source, e.g. a bucket or a folder could stay closed. Nobody could load the files directly from the external source. Your website always delivers them via its own URL.

Requires _External Files in Media Library_ 6.0.0 or newer. Up to version 6.0 the option was called "Save files privately". In hooks and in the code of this plugin it is still called "private".

## What the option does and what not

The option decides **where** a file could be loaded: only via your website, not from the external source. It does not decide **who** could load it via your website. Without further rules everyone who knows the URL of your website could load the file.

* **WooCommerce:** files of downloadable products are only delivered to buyers, see [WooCommerce](#woocommerce) below.
* **Plugins to protect files** like Prevent Direct Access only protect files in the uploads directory, not external files (see [supported plugins](plugins.md)).
* **Own rules:** use the hook `efml_proxy_allow_delivery`, e.g. to deliver files only to logged-in users.

## Supported services

| Service | Delivery |
|---|---|
| AWS S3 and all S3-compatible platforms of the extension | redirect to a signed link or proxy cache |
| Google Cloud Storage | redirect to a signed link or proxy cache |
| DropBox | redirect to a temporary link or proxy cache (temporary links of DropBox are valid for 4 hours, so they are only used with a validity of up to 119 minutes) |
| Google Drive (only with credentials for the complete website) | proxy cache |
| WebDAV | proxy cache |

Files without direct access on FTP, SFTP and local directories are always delivered via the proxy. Other files there are delivered via the public URL of the external source (the URL for exported files in its export settings), if the proxy is disabled for their file type, otherwise also via the proxy.

## How the files are delivered

The media library and the content always use the fixed URL of your website, e.g. `https://example.com/emlproxy/file.pdf`. Search engines and visitors only see this URL.

* **Proxy enabled for the file type** (settings → delivery): your website loads the file once into its cache and delivers it itself. This is the safest way for search engines.
* **Proxy disabled for the file type:** your website redirects to a link with limited validity. The file is loaded directly from the provider, your website does not transfer it. If the service could not create such links, the cache is used.
* Specific sizes of images (e.g. `-150x150`) are always delivered from the cache. If the sizes have been exported together with the image, your website loads the requested size from the provider into its cache, otherwise it creates the size from the image. Images get a `srcset` with these sizes, it contains only URLs of your website.

## Validity of the links

Settings → Export → **"Validity of links to files without direct access at the source (in minutes)"**, default 60 minutes, maximum 5040 minutes (3.5 days, as signed links of S3 and Google Cloud Storage are valid for at most 7 days).

The links are created for fixed time windows of this length. During a time window the link is always the same, so browsers and caches could reuse the file. Each link is valid for two time windows, so it is valid at least for the configured time after each redirect. The browser may cache the redirect itself until the end of the time window.

## Checking the delivery

Proxy URLs are not redirected to a variant with trailing slash and also answer HEAD requests with the headers of the file (e.g. for link checkers and CDNs). A file without direct access and without proxy answers with `302` and the signed link in the header `Location`, e.g. checked with `curl -I https://example.com/emlproxy/file.pdf` or in the network tab of the developer tools of your browser.

## WooCommerce

External files from the media library could be chosen as files of downloadable products as usual. After the checks of WooCommerce (order, count, expiration) the file is delivered like this:

* **Files without direct access at the source:** via a signed link with a validity of 5 minutes or, if the service could not create it, from the cache. The proxy URL does not deliver these files to visitors anymore, only to users who are allowed to edit the file. So they could only be downloaded via WooCommerce.
* **Other external files:** from their external URL. Everyone who knows this URL could still load the file.

The info dialog of the file in the media library shows in which products it is used as download. The support depends on the setting "Enable support for WooCommerce" in the tab WooCommerce.

Hint: WooCommerce checks its "Approved download directories". If an administrator saves the product, the directory of the proxy URL is approved automatically.

## Hooks

* `efml_export_private` – whether direct access at an external source is prevented.
* `efml_private_files_lifetime` – validity of the links in seconds.
* `efml_private_file_signed_url` – the signed link for a file.
* `efml_proxy_allow_delivery` – whether the proxy may deliver a file.
* `efml_woocommerce_download_lifetime` – validity of the links for downloads in WooCommerce (default 300 seconds).

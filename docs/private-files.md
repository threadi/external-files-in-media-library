# Private files

Exported files do not have to be public on the external source. With the option **"Save files privately"** in the export settings of an external source, e.g. a bucket or a folder could stay private. Your website then always delivers the files via its own URL.

Requires _External Files in Media Library_ 6.0.0 or newer.

## Supported services

| Service | Delivery |
|---|---|
| AWS S3 and all S3-compatible platforms of the extension | redirect to a signed link or proxy cache |
| Google Cloud Storage | redirect to a signed link or proxy cache |
| DropBox | redirect to a temporary link or proxy cache (temporary links of DropBox are valid for 4 hours, so they are only used with a validity of up to 119 minutes) |
| Google Drive (only with credentials for the complete website) | proxy cache |
| WebDAV | proxy cache |

FTP, SFTP and local directories could not be reached via a URL in the browser anyway and are always delivered via the proxy.

## How the files are delivered

The media library and the content always use the fixed URL of your website, e.g. `https://example.com/emlproxy/file.pdf`. Search engines and visitors only see this URL.

* **Proxy enabled for the file type** (settings → delivery): your website loads the file once into its cache and delivers it itself. This is the safest way for search engines.
* **Proxy disabled for the file type:** your website redirects to a link with limited validity. The file is loaded directly from the provider, your website does not transfer it. If the service could not create such links, the cache is used.
* Specific sizes of images (e.g. `-150x150`) are always delivered from the cache.

## Validity of the links

Settings → Export → **"Validity of links to private files (in minutes)"**, default 60 minutes, maximum 5040 minutes (3.5 days, as signed links of S3 and Google Cloud Storage are valid for at most 7 days).

The links are created for fixed time windows of this length. During a time window the link is always the same, so browsers and caches could reuse the file. Each link is valid for two time windows, so it is valid at least for the configured time after each redirect. The browser may cache the redirect itself until the end of the time window.

## Checking the delivery

Proxy URLs are not redirected to a variant with trailing slash and also answer HEAD requests with the headers of the file (e.g. for link checkers and CDNs). A private file without proxy answers with `302` and the signed link in the header `Location`, e.g. checked with `curl -I https://example.com/emlproxy/file.pdf` or in the network tab of the developer tools of your browser.

## WooCommerce

External files from the media library could be chosen as files of downloadable products as usual. After the checks of WooCommerce (order, count, expiration) the file is delivered like this:

* **Private files:** via a signed link with a validity of 5 minutes or, if the service could not create it, from the cache. The proxy URL does not deliver these files to visitors anymore, only to users who are allowed to edit the file. So they could only be downloaded via WooCommerce.
* **Public external files:** from their external URL. Everyone who knows this URL could still load the file.

The info dialog of the file in the media library shows in which products it is used as download. The support depends on the setting "Enable support for WooCommerce" in the tab WooCommerce.

Hint: WooCommerce checks its "Approved download directories". If an administrator saves the product, the directory of the proxy URL is approved automatically.

## Hooks

* `efml_export_private` – whether an external source saves files privately.
* `efml_private_files_lifetime` – validity of the links in seconds.
* `efml_private_file_signed_url` – the signed link for a file.
* `efml_proxy_allow_delivery` – whether the proxy may deliver a file.
* `efml_woocommerce_download_lifetime` – validity of the links for downloads in WooCommerce (default 300 seconds).

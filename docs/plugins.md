# Other plugins

External Files in Media Library works together with many plugins. Plugins, which it supports, are marked in the list
of your plugins with "Supported by External Files in Media Library". You could hide this hint: settings → "Advanced" →
"Plugin" → "Mark supported plugins".

Developers could mark their own plugins via the hook `efml_supported_plugin_files` (list of plugin files like
`my-plugin/my-plugin.php`).

Here you find, how these plugins work with external files.

## Switch from other offload plugins

### Advanced Media Offloader

Files offloaded with Advanced Media Offloader could be taken over as external files without uploading them again (see
[migration](migration.md)). While both plugins are active, Advanced Media Offloader does not offload external files of
this plugin.

### Exmage – WordPress image links

Images, which have been added as links via Exmage, could be converted into external files of this plugin. A hint in the
backend offers this migration, as long as such images exist.

### Media Cloud Sync

Media Cloud Sync does not use the external files of this plugin for its own tasks, e.g. it does not offload them again.

Further offload plugins, whose files could be taken over: WP Offload Media (also Pro), Stowaway and Offload Media –
Cloud Storage. See [migration](migration.md).

## Folders, categories and galleries

These plugins organize your media library in folders, categories or galleries. When you import files from an external
source, you could choose the folder, category or gallery in the dialog of the import. For the synchronization of an
external directory, you could choose it in the settings of the synchronization: new files are saved there.

| Plugin | Choose during import | Choose for synchronization |
|---|---|---|
| CatFolders | folder | folder |
| Download List with Icons | download list | download list |
| Enhanced Media Library | category | category |
| FileBird Lite | folder | folder |
| Folderly | folder | folder |
| Folders | folder | folder |
| iFolders | folder | folder |
| Media Library Assistant | category and tag | category and tag |
| Media Library Organizer | category | category |
| NextGEN Gallery | – | gallery |
| Real Media Library (Lite) | folder | folder |
| Robo Gallery | – | gallery |

In the lists of Download List with Icons, external files get the attribute `rel="external"`.

## Image optimizers and modern image formats

Image optimizers work with the local files of uploads. If one of these plugins is active, the export of a new upload
waits until the image optimizer has finished its work. The optimized files are exported then, together with their
variants in other formats (e.g. WebP or AVIF) and the backups of the image optimizer. Images, which have been exported
already, could be optimized later via local working copies (see [features](features.md)).

- **Converter for Media:** its WebP and AVIF variants are exported beside the image. After a later optimization, use
  settings → "Export" → "Export optimized images".
- **EWWW Image Optimizer** (also the cloud version): optimized files, variants like `image.jpg.webp` and its backups are
  exported. "Optimize now!" and "Re-optimize" in the media library also work for exported images.
- **Imagify:** the export waits until its optimization via its API has finished. Variants like `image.jpg.webp` and
  `image.jpg.avif` are exported.
- **Modern Image Formats** of the WordPress Performance Team: sizes in WebP or AVIF are exported like other sizes,
  additional variants of the image in these formats are exported and delivered via the proxy.
- **ShortPixel Image Optimizer:** the export waits until its optimization via its API has finished. Variants like
  `image.webp` are exported.
- **Smush** (also Pro): optimized files and its backup of the original image (e.g. `image.bak.jpg`) are exported.
- **TinyPNG – JPEG, PNG & WebP image compression:** compressed files and variants like `image.webp` and `image.avif` are
  exported.

## Image editing and media management

### Enable Media Replace

Replacing files with Enable Media Replace works only with local files. For external files, its box in the attachment
page and its links in the media library are hidden.

### Export Media Library

Export Media Library packs the files of your media library into a ZIP file. External files are left out, as they have
no local file.

### Image Watermark

New uploads get their watermark before they are exported. For exported images, the watermark could be added or removed
later, too, for single images and via the bulk action in the media library: a local working copy is saved before, the
changed files are exported afterward. The backup of the original image, which Image Watermark saves, is exported beside
the image (as `image.iw-backup.jpg`), so the watermark could be removed later.

Image Watermark reads the image of the watermark only from your uploads directory. So this image is not exported. If it
has been exported already (e.g. directly after its upload in the settings of Image Watermark), it is restored as local
file, as soon as you save it as watermark.

Changed images get a new version in the URLs of the proxy, which WordPress creates for the output (e.g. in `srcset`), so
browsers load the changed file at once. This also applies to image optimizers and other plugins, which change files.

### Instant Images

Images, which you add from Unsplash or Pexels via Instant Images, could be saved as external files: they are delivered
from their platform instead of your uploads directory, as the guidelines of Unsplash require it. Images of Pixabay stay
local files, as Pixabay forbids this. A hint in the backend offers this support, as long as no platform is enabled.

### Media Bulk Download

Media Bulk Download packs the selected files of your media library into a ZIP file. External files are added, too:
during its requests, they are taken from the cache of the proxy or loaded temporarily from their external source. In the
ZIP file, they have their real name. The limits of the plugin (number of files, size) apply to them as well.

### Media Cleaner

Media Cleaner finds unused media and files. Exported files are checked via their former local URLs in your contents
(incl. their sizes), files imported from external sources are always considered as used (hook
`efml_media_cleaner_check_external_files`), local copies and working copies of exported files are not shown in its
filesystem scan, and external sources like FTP are not contacted during its scans. Deleting a file in Media Cleaner
deletes it on its external sources, too.

### Media File Renamer

Media File Renamer could also rename exported images (also Pro): their files are loaded before, the renamed files are
exported afterward. The proxy still finds the image via its former name.

### Perfect Images (formerly WP Retina 2x)

Its retina files (`image-300x200@2x.jpg`) and its WebP and AVIF files (`image-300x200.jpg.webp`) are exported together
with the image, deleted with it and restored with it. The proxy delivers the WebP and AVIF files to browsers, which
support them. The retina files are only used by Perfect Images, while the image is a local file.

"Regenerate" in its dashboard also works for exported images: their files are loaded before, the new sizes are exported
afterward. Its other functions for single images (replace the image, the optimized regeneration and the regeneration
via AI, create, delete or optimize the retina and WebP files) are not possible for external files, as they would change
the files on their external source directly. Perfect Images shows a hint for them.

### Regenerate Thumbnails, Force Regenerate Thumbnails and Regenerate Thumbnails Advanced

All three create the sizes of exported images again: their original image is loaded for this moment, the new sizes are
exported and the local files are removed afterward (see [features](features.md)). This also applies to the WP CLI
command of Force Regenerate Thumbnails.

Files, which you have imported from an external source, have no local files: their sizes are created by the proxy.
Regenerate Thumbnails shows a hint for them, Force Regenerate Thumbnails skips them and Regenerate Thumbnails Advanced
marks them as offloaded. So no sizes are created on their external source (e.g. in a local directory) and the external
source is not contacted (e.g. with an anonymous login on an FTP server).

### WP Extra File Types

File types, which you allow via WP Extra File Types, could also be used for external files.

## Protection of files

### Prevent Direct Access

Prevent Direct Access protects local files in the folder `_pda` of your uploads directory. This protection does not work
on external sources, so:

- Protected files are not exported, they stay local files. The media library shows a lock at them.
- Exported and other external files could not be protected by Prevent Direct Access. The option "Prevent direct access
  at the source" (see [prevent direct access at the source](private-files.md)) is no replacement: it only prevents that
  the files could be loaded directly from the external source, not via your website.

### Prevent Files / Folders Access

Prevent Files / Folders Access allows the access to files of the configured types in your uploads directory only for
logged-in users. This protection does not work on external sources, so files of these types are not exported, while the
restriction is enabled. The setting for the export names these types, the media library shows a lock at these files.

### WP Consent API

External Files in Media Library is registered with the WP Consent API, as it sets no cookies and collects no personal
data.

## Page builders

### Brizy

Brizy creates the images for its elements itself. For external images, it gets a local file for this, which is deleted
at the end of the request.

### Elementor

YouTube videos, which you have added to your media library, could be used in the video widget of Elementor: in the
frontend, they are shown as YouTube videos.

## Static websites

### Simply Static

Simply Static creates a static copy of your website. Files, which are delivered via the proxy, are copied into the
static website, as their URLs have the form of a file (e.g. `/emlproxy/image.jpg`). Files, which are delivered directly
from their external source, stay linked there.

This requires pretty permalinks: without them, the URLs of the proxy contain a query string (`?emlproxy=image.jpg`),
which Simply Static does not copy. Tools → "Site Health" shows a hint in this case. Files without direct access at the source are
not part of the static website, as it has no PHP to deliver them.

## SEO

### Rank Math and Yoast SEO

Both plugins manage the attachment pages of your media library themselves (e.g. a redirect to the file). So External
Files in Media Library does not change the links to attachment pages, while one of them is active. Its own setting for
attachment pages is read-only then and names the SEO plugin.

## Multilingual websites

### Polylang

New external files could be saved in all languages of your website at once (also with Polylang Pro), so they are
available in the media library of each language. Enable this in the settings: "Integrations" → "Polylang".

## Shops

### WooCommerce

- **Product import via CSV:** the importer of WooCommerce offers to import the product images and gallery images as
  external files: they are not uploaded into your media library, but delivered from their source.
- **Downloadable products:** external files could be used as downloads. Protected downloads are delivered by
  WooCommerce only to buyers, also if direct access at their external source is prevented (see
  [prevent direct access at the source](private-files.md)). CDNs do not cache them.

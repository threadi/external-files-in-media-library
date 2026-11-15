# Migration from other offload plugins

If you have offloaded your media library with another plugin, you could use these files as external files – without uploading them again. The migration runs via the [quickstart](setup.md) with the goal "Switch from another offload plugin". The goal is only shown if files of one of the supported plugins have been found. If one of the plugins is active, but has not offloaded any files yet, the quickstart shows a hint about it.

## Supported plugins

| Plugin | Providers | Needed extension |
|---|---|---|
| WP Offload Media (also Pro) | AWS S3, DigitalOcean Spaces, Google Cloud Storage | AWS S3 or Google Cloud Storage |
| Advanced Media Offloader | AWS S3, Cloudflare R2, Backblaze B2, DigitalOcean Spaces, Wasabi, S3-compatible (e.g. MinIO) | AWS S3 |
| Stowaway | AWS S3 | AWS S3 |
| Offload Media – Cloud Storage | AWS S3, DigitalOcean Spaces, Cloudflare R2, MinIO, Google Cloud Storage | AWS S3 or Google Cloud Storage |

The data are read directly from the database. The other plugin does not have to be active. If the needed extension is missing, the quickstart installs it after your confirmation (see [extensions](extensions.md)).

## Steps

1. **Choose the files:** The quickstart shows a tile with the amount of files per plugin, provider and bucket.
2. **Check the access data:** Bucket, region, endpoint, domain and access data are taken over from the settings of the other plugin, also from its constants in your `wp-config.php`. You could change them, afterwards the connection is checked.
3. **Choose the options:** Like for offloading, you choose whether new files are still exported to this bucket. If the other plugin is still active, you have to confirm that it is deactivated at the start of the migration. Both plugins must not change the URLs of your files at the same time. If you do not want this, cancel the quickstart.
4. **Migration:** The external source is created, the other plugin is deactivated and the files are taken over. With many files, the migration continues in the background, the progress is shown in the admin bar. At its end, a hint shows the result. If files could not be taken over or the migration has been cancelled, it links to the log, which lists the reason for each file.

## What happens with each file

* It is checked whether the object exists in the bucket. If it is missing, the attachment stays unchanged and the reason is logged.
* The attachment becomes an external file with the URL of the object, like after an export. Private files of the other plugin stay private and are delivered via your website.
* Nothing is uploaded, moved or deleted in the bucket. The thumbnails of the other plugin stay there, this plugin creates its thumbnails itself.
* Local copies of the files and the data of the other plugin are kept, so you could switch back.
* Files, which have already been taken over, are skipped. So you could repeat the migration, e.g. after an error.

## Old URLs in contents

The other plugins keep the local URLs in posts and pages (e.g. `/wp-content/uploads/2026/11/image.jpg`) and change them only during the output. This plugin does the same for the files, which have been taken over: in the output, their local URLs and the ones of their thumbnails are replaced by their actual URLs. The contents themselves are not changed.

The URLs are replaced in the output of `the_content`, `the_excerpt`, `widget_text_content` and `widget_block_content`. Add further hooks via `efml_local_url_hooks`.

## Switch back

Revert the migration with `wp eml migration revert` (see [WP CLI](cli.md)). The attachments then use their local files and the data of the other plugin again. Activate the other plugin afterwards.

## For developers

* `efml_migrations`: list of the classes, which read the data of other plugins (subclasses of `ExternalFilesInMediaLibrary\ExternalFiles\Migrations\Migration_Base`).
* `efml_migration_batch_size`: amount of files, which are read at once (default 50).
* `efml_local_url_hooks`: hooks, in whose output the local URLs of migrated and exported files are replaced.

Services support the migration via `Service_Base::get_offloaded_object_meta_key()` and `Service_Base::get_url_of_offloaded_object()`. The extensions for AWS S3 and Google Cloud Storage provide them.

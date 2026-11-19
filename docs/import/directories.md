# Import of directories with many files

This section describes how directories are imported, also if they contain tens of thousands of files.

## Supported services

This applies to directories of the following services:

* local directories
* FTP, FTPS and SFTP
* DropBox
* the extensions for AWS S3 and other S3-compatible platforms, Google Cloud Storage, Google Drive and WebDAV

Other imports (e.g. single URLs, lists of URLs, YouTube channels or ZIP files) are imported like before.

## How it works

1. The files of the directory are listed page by page, without loading them. Each file is added to the queue.
2. The files are imported one by one.

Files, which are already in your media library, are not added to the queue.

In the import dialog small directories are imported completely, the dialog shows the progress. If a directory contains
more than 500 files, the first 20 files are imported directly and all other files are imported in the background. The
dialog shows a hint about this.

The import in the background uses WP Cron and is independent of the interval of the queue. As long as files are imported
in the background, the admin bar shows the progress. A click on it shows the queue with the waiting files.

Files, which could not be imported, stay in the queue as errors. You can import them again or delete them in the queue.
If you clear the queue, the running imports are cancelled.

Imports via WP CLI import all files directly.

## Synchronization

Synchronizations use the same way. Files, which do not exist anymore on the external source, are only deleted after all
files of the directory have been listed and imported. If the list could not be loaded completely (e.g. by an error of
the external source), no files are deleted.

Large synchronizations are continued in the background. The hook `efml_after_sync` runs after each part of it, the new
hook `efml_sync_completed` only after the end of the synchronization.

## Hooks

* `efml_background_import_threshold` changes the count of files (default 500), from which on the import in the dialog
  is continued in the background.
* `efml_background_import_direct_count` changes the count of files (default 20), which are imported directly in the
  dialog, if the import is continued in the background.
* `efml_import_run_time_limit` changes the time in seconds (default 20), which a request could use for the import.
* `efml_file_list_page_size` changes the count of files (default 500), which are listed per request to the external
  source.
* `efml_import_run_files` filters the files of each page, before they are added to the queue.
* `efml_import_run_finished` runs after the import of a directory has been finished.
* `efml_sync_completed` runs after the end of a synchronization.

## Notes

* The hooks for the import of directories of the single protocols (e.g. `efml_ftp_directory_import_files`) are not used
  for these imports anymore, as each file is imported on its own.
* WP Cron must run regularly for the import in the background. If your website has only few visitors, use a cron job of
  your hosting.

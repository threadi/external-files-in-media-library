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

## Subdirectories during the synchronization

Each service has its own default, whether the files of subdirectories are synchronized:

| Service | Subdirectories by default |
|---|---|
| FTP, FTPS, SFTP, local directories | no |
| WebDAV | no |
| DropBox | yes |
| ZIP files | yes |
| AWS S3 and S3-compatible platforms, Google Cloud Storage | yes |
| Google Drive | yes for the main directory, no for folders |

Your media library has no folders: all synchronized files are on one level.

To change this, enable the option "Synchronize subdirectories" in the settings under "Synchronization". Then you could
choose for each external source in the configuration of its synchronization:

* **Default:** the global setting "Synchronize subdirectories" or, if it is disabled, the default of the service.
* **Yes:** the files of all subdirectories are synchronized.
* **No:** only the files directly in the directory are synchronized.

Without the enabled option, each service uses its default. External sources of services, which have used subdirectories
before (e.g. DropBox and ZIP files), have got the setting "Yes" with the update to version 6.1.0.

The import of a directory in the dialog is not changed by this setting. The extensions show the option, as soon as
they support it: until then, they keep their default.

For FTP, FTPS, SFTP, local directories, WebDAV and folders in Google Drive applies:

* Subdirectories are used up to a depth of 10 (hook `efml_subdirectories_max_depth`).
* The directories `node_modules`, `vendor`, `tmp`, `.git` and `.svn` are never used (hook
  `efml_subdirectories_excluded`). They are also hidden in the listing of local directories.
* Links to directories are never followed. Links to files in local directories are used like before.
* Subdirectories, which could not be read, are skipped and logged. Their files are not deleted during this
  synchronization. As files in Google Drive have no path in their URL, no file of the external source is deleted then.

DropBox, ZIP files, AWS S3 and S3-compatible platforms, Google Cloud Storage and the main directory of Google Drive use
all subdirectories like before, without these limits. Without subdirectories, they use only the files directly in the
directory (the root of the bucket or of Google Drive).

### Folder structure in folder plugins

With the option "Take over the folder structure" in the configuration of the synchronization, each file is assigned to
the folder of its subdirectory in your folder plugin, below the folder chosen for the external source (or on the top
level, if none is chosen). Missing folders are created. The option is shown, if a supported folder plugin is active,
and is only used, if subdirectories are synchronized.

* Supported: FileBird, CatFolders, Folders, Folderly, Media Library Organizer, Media Library Assistant and Enhanced Media
  Library. Real Media Library only in a version with subfolders (the Lite version does not support them). iFolders is
  not supported.
* For plugins with multiple categories per external source (Folderly, Media Library Organizer, Media Library Assistant,
  Enhanced Media Library) the structure is only used, if exactly one category is chosen.
* The folders are saved per external source: they are found again during the next synchronization, also if you have
  renamed them. A deleted folder is created again. Folders are never deleted automatically, also not if their
  directory does not exist anymore.
* Each synchronization assigns the files again to the folder of their directory.
* Folder plugins could be added via the hook `efml_folder_structure_integrations` (interface
  `ExternalFilesInMediaLibrary\ThirdParty\Folder_Structure_Interface`).

Please note:

* If you disable the subdirectories or lower the depth, the files of these subdirectories are deleted during the next
  synchronization, if unused files are deleted after the synchronization.
* Files, which you import from a subdirectory of an external source via its directory listing, are assigned to this
  external source. If its synchronization does not use subdirectories, these files are deleted during the next
  synchronization, if unused files are deleted after the synchronization.
* Files, which have been exported to the external source, are never synchronized, also not in subdirectories and not
  their image sizes.
* If a file is moved to another subdirectory, its URL changes: it is imported as new file and the former file is deleted.

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
* `efml_include_subdirectories` filters whether the files of subdirectories are used.
* `efml_subdirectories_max_depth` changes the maximum depth of subdirectories (default 10).
* `efml_subdirectories_excluded` filters the names of the directories, which are never used.
* `efml_subdirectory_skipped` runs if a subdirectory could not be read.
* `efml_folder_structure_integrations` filters the folder plugins, which could take over the folder structure.
* `efml_sync_supports_subdirectories` and `efml_sync_subdirectories_default` filter the support and the default of an
  external source.

## Notes

* The hooks for the import of directories of the single protocols (e.g. `efml_ftp_directory_import_files`) are not used
  for these imports anymore, as each file is imported on its own.
* WP Cron must run regularly for the import in the background. If your website has only few visitors, use a cron job of
  your hosting.

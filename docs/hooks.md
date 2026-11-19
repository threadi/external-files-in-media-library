# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `efml_file_delete`

*Run additional tasks for URL deletion.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The object, which has been deleted.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 351](ExternalFiles/Files.php#L351-L357)

### `efml_show_file_info`

*Add additional infos about this file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 774](ExternalFiles/Files.php#L774-L780)

### `efml_ftp_directory_import_start`

*Run action on beginning of presumed directory import via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 302](ExternalFiles/Protocols/Ftp.php#L302-L309)

### `efml_ftp_directory_import_files`

*Run action if we have files to check via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL to import.
`$file_list` | `array<string,mixed>` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 324](ExternalFiles/Protocols/Ftp.php#L324-L332)

### `efml_ftp_directory_import_file_check`

*Run action just before the file check via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 360](ExternalFiles/Protocols/Ftp.php#L360-L367)

### `efml_ftp_directory_import_file_before_to_list`

*Run action just before the file is added to the list via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.
`$file_list` | `array` | List of files to process.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 386](ExternalFiles/Protocols/Ftp.php#L386-L394)

### `efml_http_directory_import_start`

*Run action on beginning of presumed directory import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 293](ExternalFiles/Protocols/Http.php#L293-L300)

### `efml_http_directory_import_files`

*Run action if we have files to check via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 
`$matches[1]` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 357](ExternalFiles/Protocols/Http.php#L357-L365)

### `efml_http_directory_import_file_check`

*Run action just before the file check via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 415](ExternalFiles/Protocols/Http.php#L415-L422)

### `efml_http_directory_import_file_before_to_list`

*Run action just before the file is added to the list via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.
`$matches[1]` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 438](ExternalFiles/Protocols/Http.php#L438-L446)

### `efml_file_directory_import_start`

*Run action on beginning of presumed directory import via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 96](ExternalFiles/Protocols/File.php#L96-L103)

### `efml_file_directory_import_files`

*Run action if we have files to check via FILE-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL to import.
`$file_list` | `array<string,array<string,mixed>>` | List of files.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 124](ExternalFiles/Protocols/File.php#L124-L132)

### `efml_file_directory_import_file_check`

*Run action just before the file check via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 171](ExternalFiles/Protocols/File.php#L171-L178)

### `efml_file_directory_import_file_before_to_list`

*Run action just before the file is added to the list via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.
`$file_list` | `array<int\|string,mixed>` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 195](ExternalFiles/Protocols/File.php#L195-L203)

### `efml_sftp_directory_import_start`

*Run action on beginning of presumed directory import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 243](ExternalFiles/Protocols/Sftp.php#L243-L250)

### `efml_sftp_directory_import_files`

*Run action if we have files to check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 
`$file_list` | `array` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 263](ExternalFiles/Protocols/Sftp.php#L263-L271)

### `efml_sftp_directory_import_file_check`

*Run action just before the file check via SFTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 304](ExternalFiles/Protocols/Sftp.php#L304-L311)

### `efml_sftp_directory_import_file_before_to_list`

*Run action just before the file is added to the list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.
`$file_list` | `array` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 330](ExternalFiles/Protocols/Sftp.php#L330-L338)

### `efml_before_sync`

*Allow to add additional tasks before sync is running.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The used URL.
`$term_data` | `array<string,string>` | The term data.
`$term_id` | `int` | The used term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 602](ExternalFiles/Synchronization.php#L602-L610)

### `efml_after_sync`

*This action is documented in app/ExternalFiles/Synchronization.php*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  | 
`$term_data` |  | 
`$term_id` |  | 

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 652](ExternalFiles/Synchronization.php#L652-L653)

### `efml_before_sync`

*This action is documented in app/ExternalFiles/Synchronization.php*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  | 
`$this->get_term_data($term_id)` |  | 
`$term_id` |  | 

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 689](ExternalFiles/Synchronization.php#L689-L690)

### `efml_after_sync`

*This action is documented in app/ExternalFiles/Synchronization.php*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  | 
`$this->get_term_data($term_id)` |  | 
`$term_id` |  | 

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 712](ExternalFiles/Synchronization.php#L712-L713)

### `efml_after_sync`

*Allow to add additional tasks after sync has been running.*

If the synchronization is continued in the background, this hook runs after each part of it.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The used URL.
`$term_data` | `array<string,string>` | The term data.
`$term_id` | `int` | The used term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 749](ExternalFiles/Synchronization.php#L749-L759)

### `efml_sync_completed`

*Run tasks after the synchronization has been completed, also if it was continued in the background.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The used URL.
`$term_data` | `array<string,string>` | The term data.
`$term_id` | `int` | The used term ID.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 761](ExternalFiles/Synchronization.php#L761-L769)

### `efml_before_deleting_synced_files`

*Allow to add additional tasks before sync is running.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$term_id` | `int` | The used term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 1470](ExternalFiles/Synchronization.php#L1470-L1476)

### `efml_export_save_config`

*Run additional tasks after saving an export dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$term_id` | `int` | The used term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 331](ExternalFiles/ExportDialog.php#L331-L337)

### `efml_sync_save_config`

*Run additional tasks during saving a new sync configuration.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `array` | List of fields.
`$term_id` | `int` | The term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/SynchronizationDialog.php](ExternalFiles/SynchronizationDialog.php), [line 317](ExternalFiles/SynchronizationDialog.php#L317-L324)

### `efml_real_import_local`

*Run additional tasks to save an external file as local file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions/Real_Import.php](ExternalFiles/Extensions/Real_Import.php), [line 443](ExternalFiles/Extensions/Real_Import.php#L443-L449)

### `efml_queue_before_process`

*Run action before the queue is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from the queue, which will be processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 466](ExternalFiles/Extensions/Queue.php#L466-L472)

### `efml_queue_after_process`

*Run action after the queue is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from the queue, which has been processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 495](ExternalFiles/Extensions/Queue.php#L495-L501)

### `efml_before_import`

*Run any task before we start to import a given URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The given URL.
`$fields` | `array` | List of fields.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 198](ExternalFiles/Import.php#L198-L206)

### `efml_after_import`

*This action is documented in app/ExternalFiles/Import.php*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  | 
`$fields` |  | 

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 267](ExternalFiles/Import.php#L267-L268)

### `efml_before_file_list`

*Run action just before we go through the list of resulting files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  | 
`$files` |  | 

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 339](ExternalFiles/Import.php#L339-L342)

### `efml_before_file_save`

*Run additional tasks before new external file will be added.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_data` | `array` | The array with the file data.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 402](ExternalFiles/Import.php#L402-L408)

### `efml_file_import_before_save`

*Run action just before the file is saved in the database.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 463](ExternalFiles/Import.php#L463-L470)

### `efml_after_file_save`

*Run additional tasks after new external file has been added.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The object of the external file.
`$file_data` | `array<string,mixed>` | The array with the file data.
`$file_url` | `string` | The source URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 563](ExternalFiles/Import.php#L563-L571)

### `efml_after_file_save`

*Run additional tasks after new external file has been added.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The object of the external file.
`$file_data` | `array` | The array with the file data.
`$file_url` | `string` | The source URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 623](ExternalFiles/Import.php#L623-L631)

### `efml_after_import`

*Run tasks after the import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The given URL.
`$fields` | `array` | List of fields.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 645](ExternalFiles/Import.php#L645-L653)

### `efml_file_restored`

*Run additional tasks after an exported file has been restored as local file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attachment_id` | `int` | The attachment ID.
`$url` | `string` | The former external URL of the file.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Restore.php](ExternalFiles/Restore.php), [line 403](ExternalFiles/Restore.php#L403-L410)

### `efml_audio_meta_data`

*Run additional tasks to add custom metadata on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Audio.php](ExternalFiles/File_Types/Audio.php), [line 139](ExternalFiles/File_Types/Audio.php#L139-L145)

### `efml_video_meta_data`

*Run additional tasks to add custom metadata on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Video.php](ExternalFiles/File_Types/Video.php), [line 132](ExternalFiles/File_Types/Video.php#L132-L138)

### `efml_image_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.
`$image_meta` | `array` | The image meta data.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Image.php](ExternalFiles/File_Types/Image.php), [line 146](ExternalFiles/File_Types/Image.php#L146-L153)

### `efml_export_before`

*Run additional tasks before we export a given file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attachment_id` | `int` | The attachment ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1491](ExternalFiles/Export.php#L1491-L1497)

### `efml_export_before_on_service`

*Run additional tasks before we export a given file to a specific external source.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$listing_obj` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The used service object.
`$term_id` | `int` | The used term.
`$attachment_id` | `int` | The attachment ID.
`$file` | `string` | The absolute path to the file.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1572](ExternalFiles/Export.php#L1572-L1581)

### `efml_proxy_before`

*Run additional tasks before proxy tries to load a cached external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` |  | 

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 262](ExternalFiles/Proxy.php#L262-L265)

### `efml_import_ajax_start`

*Run additional tasks just before AJAX-related import of URLs is starting.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | List of URLs to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 476](ExternalFiles/Forms.php#L476-L482)

### `efml_import_ajax_end`

*Run additional tasks just before AJAX-related import of URLs is marked as completed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | List of URLs to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 606](ExternalFiles/Forms.php#L606-L612)

### `efml_import_run_finished`

*Run tasks after an import run has been finished.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$run` | `array<string,mixed>` | The run.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 2371](ExternalFiles/Import_Runs.php#L2371-L2377)

### `efml_filter_query`

*Filter the query.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array(&$query)` |  | 

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 309](ExternalFiles/Tables.php#L309-L315)

### `efml_table_column_local_file`

*Run additional tasks to show more infos for local files here (e.g. why they are not exported).*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attachment_id` | `int` | The ID of the attachment.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 542](ExternalFiles/Tables.php#L542-L548)

### `efml_table_column_source`

*Run additional tasks for show more infos here.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attachment_id` | `int` | The ID of the attachment.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 648](ExternalFiles/Tables.php#L648-L654)

### `efml_switch_to_local_before`

*Run tasks before we switch a file to local.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 978](ExternalFiles/File.php#L978-L984)

### `efml_switch_to_local_failed`

*Run additional tasks if the switch to local hosting of an external file failed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1002](ExternalFiles/File.php#L1002-L1008)

### `efml_switch_to_local_failed`

*Run additional tasks if the switch to local hosting of an external file failed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1037](ExternalFiles/File.php#L1037-L1043)

### `efml_switch_to_local_failed`

*Run additional tasks if the switch to local hosting of an external file failed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1063](ExternalFiles/File.php#L1063-L1069)

### `efml_switch_to_local_failed`

*Run additional tasks if the switch to local hosting of an external file failed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1094](ExternalFiles/File.php#L1094-L1100)

### `efml_switch_to_local_after`

*Run tasks after we switch a file to local.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_id()` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1160](ExternalFiles/File.php#L1160-L1167)

### `efml_switch_to_external_after`

*Run tasks after we switch a file to local.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_id()` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1290](ExternalFiles/File.php#L1290-L1297)

### `efml_service_main_settings`

*Run additional tasks after the main settings of a service have been added, e.g. to add further settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$section` | `\easySettingsForWordPress\Section` | The main settings section of this service.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 361](Services/Service_Base.php#L361-L368)

### `efml_url_list_import_files`

*Run action before the files of a list are checked.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The address.
`array_keys($entries)` |  | 

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 2137](Services/Url_List_Base.php#L2137-L2144)

### `efml_url_list_import_file_check`

*Run action before a single file of a list is checked.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL of the file.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 2196](Services/Url_List_Base.php#L2196-L2202)

### `efml_url_list_import_file_before_to_list`

*Run action just before the file is added to the list of files to import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL of the file.
`array_keys($entries)` |  | 

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 2228](Services/Url_List_Base.php#L2228-L2235)

### `efml_dropbox_directory_import_files`

*Run action if we have files to check via Dropbox-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL to import.
`$entries` | `array<int\|string,mixed>` | List of matches (the URLs).

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/DropBox/Protocol.php](Services/DropBox/Protocol.php), [line 167](Services/DropBox/Protocol.php#L167-L175)

### `efml_dropbox_directory_import_file_check`

*Run action just before the file check via Dropbox-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/DropBox/Protocol.php](Services/DropBox/Protocol.php), [line 188](Services/DropBox/Protocol.php#L188-L195)

### `efml_cli_arguments`

*Run additional tasks from extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$arguments` | `array` | List of CLI arguments.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Cli.php](Plugin/Cli.php), [line 82](Plugin/Cli.php#L82-L88)

### `efml_setup_apply_option`

*Save the chosen value of an option of the setup.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$option` | `string` | The name of the option.
`$value` | `string` | The chosen value.
`$term_id` | `int` | The ID of the created external source.
`$service_obj` | `\ExternalFilesInMediaLibrary\Services\Service_Base\|null` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 2469](Plugin/Setup.php#L2469-L2478)

### `efml_setup_run_action`

*Run further tasks at the end of the setup.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$action` | `string` | The action of the goal.
`$term_id` | `int` | The ID of the created external source.
`$goal` | `array<string,mixed>` | The goal.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 2670](Plugin/Setup.php#L2670-L2678)

## Filters

### `efml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$url` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 250](ExternalFiles/Files.php#L250-L250)

### `efml_files_query`

*Filter the query to load all external files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$query` | `array<string,mixed>` | The query.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 290](ExternalFiles/Files.php#L290-L296)

### `efml_protected_meta_keys`

*Filter the list of post meta keys of this plugin, which are protected against manual changes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$meta_keys` | `array<int,string>` | List of meta keys.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 477](ExternalFiles/Files.php#L477-L483)

### `efml_file_platform_url`

*Filter the URL of the platform, where the file is saved (e.g. the website of DropBox).*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$platform_url` | `string` | The URL (empty, if not available).
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Files` | The files object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 679](ExternalFiles/Files.php#L679-L687)

### `efml_files_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 1540](ExternalFiles/Files.php#L1540-L1540)

### `efml_former_proxy_url_redirect`

*Filter the URL, to which a request for the former proxy URL of a file is redirected. The file has been an
external file before, e.g. before its real import or the restore of an exported file.*

Return an empty string to prevent the redirect.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The actual URL of the file.
`$attachment_id` | `int` | The attachment ID.
`$name` | `string` | The requested name in the proxy.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Former_Proxy_Urls.php](ExternalFiles/Former_Proxy_Urls.php), [line 276](ExternalFiles/Former_Proxy_Urls.php#L276-L287)

### `efml_tools`

*Filter the list of main tools for external files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of tools.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Tools.php](ExternalFiles/Tools.php), [line 115](ExternalFiles/Tools.php#L115-L121)

### `efml_check_url`

*Filter the resulting for checking an external URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$return` | `bool` | The result of this check.
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 114](ExternalFiles/Protocols/Ftp.php#L114-L122)

### `efml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array<int,array<string,mixed>>` | List of files.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 429](ExternalFiles/Protocols/Ftp.php#L429-L436)

### `efml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$file_path` |  | 
`$response_headers` | `array` | The response header.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 520](ExternalFiles/Protocols/Ftp.php#L520-L529)

### `efml_ftp_use_pasv_address`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$use_pasv_address` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 641](ExternalFiles/Protocols/Ftp.php#L641-L641)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 700](ExternalFiles/Protocols/Ftp.php#L700-L700)

### `efml_check_url_before`

*Filter the URL before we check it.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available 5.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 71](ExternalFiles/Protocols/Http.php#L71-L77)

### `efml_check_url`

*Filter the resulting for checking an external URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$return` | `bool` | The result of this check.
`$url` | `string` | The requested external URL.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 92](ExternalFiles/Protocols/Http.php#L92-L100)

### `efml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 139](ExternalFiles/Protocols/Http.php#L139-L139)

### `efml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 158](ExternalFiles/Protocols/Http.php#L158-L158)

### `efml_check_url_availability`

*Filter the result of checking an external URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$return` | `bool` | The result of this check.
`$url` | `string` | The requested external URL.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 170](ExternalFiles/Protocols/Http.php#L170-L178)

### `efml_http_header_response`

*Filter the HTTP header response for an external URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$response` | `array\|\WP_Error` | The response.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\HTTP` | The actual object.
`$url` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 209](ExternalFiles/Protocols/Http.php#L209-L217)

### `efml_filter_url_response`

*Filter the URL with custom import methods.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` |  | 
`$this->get_url()` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http` | The actual protocol object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 263](ExternalFiles/Protocols/Http.php#L263-L271)

### `efml_http_directory_regex`

*Filter the content with regex via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$matches` |  | 
`$content` | `string` | The content to parse.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 331](ExternalFiles/Protocols/Http.php#L331-L342)

### `efml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array<int,array<string,mixed>>` | List of files.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 484](ExternalFiles/Protocols/Http.php#L484-L491)

### `efml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array<string,mixed>` | List of detected file settings.
`$url` | `string` | The requested external URL.
`$response_headers` | `array<string,mixed>` | The response header.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 581](ExternalFiles/Protocols/Http.php#L581-L590)

### `efml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 677](ExternalFiles/Protocols/Http.php#L677-L677)

### `efml_http_save_local`

*Filter if an HTTP-file should be saved local or not.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if file should be saved local.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 695](ExternalFiles/Protocols/Http.php#L695-L702)

### `efml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 733](ExternalFiles/Protocols/Http.php#L733-L733)

### `efml_http_save_local`

*Filter whether the HTTP-file should be saved local or not.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if file should be saved local.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 746](ExternalFiles/Protocols/Http.php#L746-L754)

### `efml_http_header_args`

*Filter the resulting header.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$args` | `array<string,mixed>` | List of headers.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\HTTP` | The protocol object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 789](ExternalFiles/Protocols/Http.php#L789-L796)

### `efml_http_states`

*Filter the list of allowed http states.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,int>` | List of http states.
`$url` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 826](ExternalFiles/Protocols/Http.php#L826-L833)

### `efml_locale_file_check`

*Filter to prevent locale file check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Must be true to prevent check.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 858](ExternalFiles/Protocols/Http.php#L858-L865)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 904](ExternalFiles/Protocols/Http.php#L904-L904)

### `efml_http_temp_file`

*Filter the temporary file for the given URL before it is downloaded via HTTP.*

Return the absolute path of a temporary file to use it instead of the download, e.g. if a service could
load the file via its API.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`null` |  | 
`$url` | `string` | The given URL.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http` | The protocol object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 913](ExternalFiles/Protocols/Http.php#L913-L924)

### `efml_filter_file_response`

*Filter the file with custom import methods.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` |  | 
`$url` | `string` | The URL to import.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File` | The actual protocol object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 69](ExternalFiles/Protocols/File.php#L69-L77)

### `efml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array<int,array<string,mixed>>` | List of files.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 233](ExternalFiles/Protocols/File.php#L233-L241)

### `efml_file_check_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$file_path` |  | 

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 357](ExternalFiles/Protocols/File.php#L357-L357)

### `efml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array<string,mixed>` | List of detected file settings.
`$file_path` |  | 
`$response_headers` | `array<string,mixed>` | The response header.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 417](ExternalFiles/Protocols/File.php#L417-L426)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 500](ExternalFiles/Protocols/File.php#L500-L500)

### `efml_file_base`

*Filter the allowed base directory for local files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$base` | `null\|string` | The detected base directory.

**Changelog**

Version | Description
------- | -----------
`5.2.0` | Available since 5.2.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 640](ExternalFiles/Protocols/File.php#L640-L646)

### `efml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array<int,array<string,mixed>>` | List of files.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 364](ExternalFiles/Protocols/Sftp.php#L364-L371)

### `efml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$url` | `string` | The requested external URL.
`$response_headers` | `array` | The response header.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 503](ExternalFiles/Protocols/Sftp.php#L503-L512)

### `efml_tcp_protocols`

*Filter the protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$tcp_protocols` | `array<string,int>` | List of protocols of this object (e.g., 'http' or 'ftp').
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The actual object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 114](ExternalFiles/Protocol_Base.php#L114-L121)

### `efml_file_list_page_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`500` |  | 
`$instance` |  | 

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 293](ExternalFiles/Protocol_Base.php#L293-L293)

### `efml_duplicate_check`

*Filter to prevent duplicate check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Must be true to prevent check.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 532](ExternalFiles/Protocol_Base.php#L532-L539)

### `efml_mime_type_for_multiple_files`

*Filter whether the given mime type could provide multiple files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Set to true for URL with multiple files.
`$mime_type` | `string` | The given mime type.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 716](ExternalFiles/Protocol_Base.php#L716-L725)

### `efml_import_info_timeout`

*Filter the timeout for the AJAX-info-request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$info_timeout` | `int` | The timeout in ms (default 200ms).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 278](ExternalFiles/Synchronization.php#L278-L284)

### `efml_prevent_sync`

*Filter whether the given URL should be synchronized.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True for prevent the synchronization.
`$url` | `string` | The URL to synchronize.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 570](ExternalFiles/Synchronization.php#L570-L580)

### `efml_private_files_lifetime`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$lifetime` |  | 

Source: [app/ExternalFiles/Private_Files.php](ExternalFiles/Private_Files.php), [line 151](ExternalFiles/Private_Files.php#L151-L151)

### `efml_private_file_signed_url`

*Filter the signed URL for a private file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The signed URL (empty, if not available).
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.
`$start` | `int` | The start of the validity as timestamp.
`$expires` | `int` | The end of the validity as timestamp.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Private_Files.php](ExternalFiles/Private_Files.php), [line 208](ExternalFiles/Private_Files.php#L208-L217)

### `efml_local_work_information_callers`

*Filter the functions, which request the path of the original image of an exported image only for
information (e.g. its name). For them, the files are not downloaded.*

Use "Class::method" for methods.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$callers` | `array<int,string>` | The list of functions and methods.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Local_Work.php](ExternalFiles/Local_Work.php), [line 149](ExternalFiles/Local_Work.php#L149-L158)

### `efml_local_work_allowed`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$allowed` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Local_Work.php](ExternalFiles/Local_Work.php), [line 226](ExternalFiles/Local_Work.php#L226-L226)

### `efml_export_upload_delay`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`0` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Delayed_Uploads.php](ExternalFiles/Delayed_Uploads.php), [line 123](ExternalFiles/Delayed_Uploads.php#L123-L123)

### `efml_export_upload_ready`

*Filter whether other plugins have finished their work with the local files of an upload.*

The export is postponed by 5 minutes, if not, but one hour after the upload at most.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`!$this->is_busy($attachment_id)` |  | 
`$attachment_id` | `int` | The attachment ID.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Delayed_Uploads.php](ExternalFiles/Delayed_Uploads.php), [line 266](ExternalFiles/Delayed_Uploads.php#L266-L275)

### `efml_export_dialog_extensions_default`

*Filter the list of default extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of names of the default extensions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 93](ExternalFiles/ExportDialog.php#L93-L99)

### `efml_export_config_dialog`

*Filter the dialog to configure an export.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog.
`$term_id` | `int` | The term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 221](ExternalFiles/ExportDialog.php#L221-L228)

### `efml_export_save_config_dialog`

*Filter the dialog after saving an updated export configuration.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array` | The dialog.
`$term_id` | `int` | The term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 360](ExternalFiles/ExportDialog.php#L360-L367)

### `efml_export_configuration_url_state`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` |  | 
`$url` |  | 

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 421](ExternalFiles/ExportDialog.php#L421-L421)

### `efml_sync_dialog_extensions_default`

*Filter the list of default extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of names of the default extensions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/SynchronizationDialog.php](ExternalFiles/SynchronizationDialog.php), [line 93](ExternalFiles/SynchronizationDialog.php#L93-L99)

### `efml_sync_configure_form`

*Filter the form to configure this external directory.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$form` | `string` | The form HTML-code.
`$term_id` | `int` | The term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/SynchronizationDialog.php](ExternalFiles/SynchronizationDialog.php), [line 188](ExternalFiles/SynchronizationDialog.php#L188-L195)

### `efml_sync_config_dialog`

*Filter the dialog to configure an export.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog.
`$term_id` | `int` | The term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/SynchronizationDialog.php](ExternalFiles/SynchronizationDialog.php), [line 231](ExternalFiles/SynchronizationDialog.php#L231-L238)

### `efml_sync_validate_config`

*Run additional tasks to validate given values during saving a new sync configuration.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True to prevent saving.
`$fields` | `array` | List of fields.
`$dialog` | `array` | The response dialog.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/SynchronizationDialog.php](ExternalFiles/SynchronizationDialog.php), [line 290](ExternalFiles/SynchronizationDialog.php#L290-L298)

### `efml_export_file_filename`

*File the filename for JSON-download of single file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$filename` | `string` | The generated filename.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions/Import_Export.php](ExternalFiles/Extensions/Import_Export.php), [line 352](ExternalFiles/Extensions/Import_Export.php#L352-L359)

### `efml_file_size_limits_services_without_size`

*Filter the list of services, whose files do not have a file size. They do not get settings for the
limits of the file size.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of service names.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/ExternalFiles/Extensions/File_Size_Limits.php](ExternalFiles/Extensions/File_Size_Limits.php), [line 315](ExternalFiles/Extensions/File_Size_Limits.php#L315-L322)

### `efml_file_size_limits`

*Filter the limits for the file size of a single file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$limits` | `array{min: array{size: int, source: string}, max: array{size: int, source: string}}` | The limits (in bytes) with their source.
`$mime_type` | `string` | The mime type of the file (empty if not known).
`$service` | `string` | The name of the used service (empty if no service is used).

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/ExternalFiles/Extensions/File_Size_Limits.php](ExternalFiles/Extensions/File_Size_Limits.php), [line 462](ExternalFiles/Extensions/File_Size_Limits.php#L462-L470)

### `efml_import_job_id`

*Filter the job ID for the files of this import, e.g. to use one job for all files of a directory, which
are imported one by one.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`uniqid('', true)` |  | 

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Extensions/Jobs.php](ExternalFiles/Extensions/Jobs.php), [line 107](ExternalFiles/Extensions/Jobs.php#L107-L114)

### `efml_import_options`

*Filter the options used for import of this URL via queue.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array` | List of options.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 387](ExternalFiles/Extensions/Queue.php#L387-L394)

### `efml_queue_urls`

*Filter the list of URLs from the queue before they are processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from the queue.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 455](ExternalFiles/Extensions/Queue.php#L455-L461)

### `efml_queue_retry_delays`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$delays` |  | 
`$type` |  | 

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 1511](ExternalFiles/Extensions/Queue.php#L1511-L1511)

### `efml_export_queue_max_attempts`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`3` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 1765](ExternalFiles/Extensions/Queue.php#L1765-L1765)

### `efml_deletion_queue_max_attempts`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`5` |  | 
`$term_id` |  | 

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 1948](ExternalFiles/Extensions/Queue.php#L1948-L1948)

### `efml_import_url`

*Filter the URL to import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.
`$fields` | `array` | The fields.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 173](ExternalFiles/Import.php#L173-L180)

### `efml_prevent_import`

*Prevent import of this single URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Return true if normal import should not be started.
`$url` | `string` | The given URL.
`$fields` | `array` | List of fields necessary for the connection.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 183](ExternalFiles/Import.php#L183-L194)

### `efml_file_import_user`

*Filter the user_id for a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$user_id` | `int` | The title generated by the importer.
`$url` | `string` | The requested external URL.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 326](ExternalFiles/Import.php#L326-L334)

### `efml_prevent_file_import`

*Prevent the import of this file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Set to true to prevent the import.
`$file_data` | `array` | The file data.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 355](ExternalFiles/Import.php#L355-L363)

### `efml_file_import_title`

*Filter the title for a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$title` | `string` | The title generated by the importer.
`$file_url` | `string` | The requested external URL.
`$file_data` | `array<string,mixed>` | List of file settings detected by the importer.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 417](ExternalFiles/Import.php#L417-L426)

### `efml_file_import_file_url`

*Filter the URL for a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The file URL.
`$file_data` | `array<string,mixed>` | List of file settings detected by the importer.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 428](ExternalFiles/Import.php#L428-L436)

### `efml_file_import_attachment`

*Filter the attachment settings*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_array` | `array<string,mixed>` | The attachment settings.
`$file_url` | `string` | The requested external URL.
`$file_data` | `array<string,mixed>` | List of file settings detected by the importer.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 449](ExternalFiles/Import.php#L449-L458)

### `efml_import_no_external_file`

*Filter whether we import no external files.*

Return true if we only import files in the media library without external URL settings.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$no_external_object` | `bool` | The marker, must be true to import the file of external URL as local file.
`$url` | `string` | The used URL.
`$file_data` | `array<string,mixed>` | The file data.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The resulting external file (without any configuration yet).

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 545](ExternalFiles/Import.php#L545-L559)

### `efml_file_import_error`

*Filter the error, which prevents the import of a single file.*

Return a text to prevent the import of this file. The text is shown as error to the user.
The filter is used before the content of the file is loaded (if the protocol supports it)
and before the file is saved in the media library.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`''` |  | 
`$file_data` | `array<string,mixed>` | The file data.
`$url` | `string` | The URL used for the import.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 679](ExternalFiles/Import.php#L679-L691)

### `efml_user_agent`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$string_to_add` |  | 

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 971](ExternalFiles/Import.php#L971-L971)

### `efml_extension_types`

*Filter the list of supported extension types.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of supported extension types.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extension_Types.php](ExternalFiles/Extension_Types.php), [line 73](ExternalFiles/Extension_Types.php#L73-L79)

### `efml_url_index_backfill_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`500` |  | 

Source: [app/ExternalFiles/Url_Index.php](ExternalFiles/Url_Index.php), [line 325](ExternalFiles/Url_Index.php#L325-L325)

### `efml_dialog_settings`

*Filter the given settings for the import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,mixed>` | The requested settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 106](ExternalFiles/ImportDialog.php#L106-L113)

### `efml_add_dialog`

*Filter the dialog. This is the main handling to extend the import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog configuration.
`$settings` | `array<string,mixed>` | The requested settings.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 153](ExternalFiles/ImportDialog.php#L153-L160)

### `efml_user_settings`

*Filter the possible user settings for import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,array<string,mixed>>` | List of settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 361](ExternalFiles/ImportDialog.php#L361-L367)

### `efml_user_settings`

*Filter the possible user settings for import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,array<string,mixed>>` | List of settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 430](ExternalFiles/ImportDialog.php#L430-L436)

### `efml_dialog_settings`

*Filter the given settings for the import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,mixed>` | The requested settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 682](ExternalFiles/ImportDialog.php#L682-L689)

### `efml_add_dialog`

*Filter the dialog. This is the main handling to extend the import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog configuration.
`$settings` | `array<string,mixed>` | The requested settings.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 694](ExternalFiles/ImportDialog.php#L694-L701)

### `efml_import_dialog_extensions_default`

*Filter the list of default extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of names of the default extensions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 778](ExternalFiles/ImportDialog.php#L778-L784)

### `efml_result_objects`

*Filter the list of possible result objects.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of result objects.

**Changelog**

Version | Description
------- | -----------
`5.4.0` | Available since 5.4.0.

Source: [app/ExternalFiles/Results.php](ExternalFiles/Results.php), [line 157](ExternalFiles/Results.php#L157-L163)

### `efml_file_type_compatibility_result`

*Filter the result of file type compatibility check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | The result (true or false).
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File\|false` | The external file object.
`$mime_type` | `string` | The used mime type (added in 3.0.0).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 99](ExternalFiles/File_Types_Base.php#L99-L108)

### `efml_file_type_supported_mime_types`

*Filter the supported mime types of single file type.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$mime_type` | `array` | List of mime types.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File\|false` | The file object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 139](ExternalFiles/File_Types_Base.php#L139-L146)

### `efml_restore_download_urls`

*Filter the URLs, from which an exported file is downloaded for its restore. They are tried one by one.*

E.g. Dropbox adds the path of the file for its API, as the public URL of an exported file could expire.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array($url)` |  | 
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Restore.php](ExternalFiles/Restore.php), [line 579](ExternalFiles/Restore.php#L579-L588)

### `efml_image_proxy_enabled`

*Filter whether the proxy is used for an image.*

The external file object is given if the check is for a specific file, otherwise it is false.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enabled` | `bool` | True if the proxy is enabled.
`$this->get_file()` |  | 

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/File_Types/Image.php](ExternalFiles/File_Types/Image.php), [line 174](ExternalFiles/File_Types/Image.php#L174-L183)

### `efml_protocols`

*Filter the list of available protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `string[]` | List of protocol handler.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols.php](ExternalFiles/Protocols.php), [line 68](ExternalFiles/Protocols.php#L68-L74)

### `efml_export_actions`

*Filter the actions for export handlings on the external source terms.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$actions` | `array<int,string>` | The action.
`$term_id` | `int` | The term ID.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 693](ExternalFiles/Export.php#L693-L700)

### `efml_export_source_usable`

*Filter whether an external source could be used for exports at the moment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if it could be used.
`$term_id` | `int` | The ID of the external source.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1012](ExternalFiles/Export.php#L1012-L1019)

### `efml_export_check_sources_on_screen`

*Filter whether the external sources for the export are checked on the actual page in the backend.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$is_screen` | `bool` | True, if they are checked.
`$screen` | `\WP_Screen` | The actual page.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1153](ExternalFiles/Export.php#L1153-L1160)

### `efml_export_prevent_reason`

*Filter the reason, why the export of a file is prevented via the hook "efml_prevent_export" or
"efml_prevent_export_on_service". It is shown in the log.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$reason` | `string` | The reason (a sentence) or an empty string.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.
`$term_id` | `int` | The ID of the external source or 0.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1359](ExternalFiles/Export.php#L1359-L1368)

### `efml_export_is_upload`

*Filter whether the metadata of an attachment are created for an upload, which is exported then.*

Return true to export also existing local files, whose metadata are created again (e.g. by a plugin to
regenerate the sizes of images), like before version 6.0.0.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$is_upload` | `bool` | True if this is an upload.
`$attachment_id` | `int` | The attachment ID.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1396](ExternalFiles/Export.php#L1396-L1406)

### `efml_prevent_export`

*Filter whether the given file should be exported.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True for prevent the export.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object to use.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1450](ExternalFiles/Export.php#L1450-L1457)

### `efml_prevent_export_on_service`

*Filter whether the given file should be exported.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True for prevent the export.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object to use.
`$term_id` | `int` | The ID of the external source.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1549](ExternalFiles/Export.php#L1549-L1557)

### `efml_prevent_export`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$external_file_obj` |  | 

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 2069](ExternalFiles/Export.php#L2069-L2069)

### `efml_export_object`

*Filter the detected service object for export views.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$listing_obj` | `object\|false` | The object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 2331](ExternalFiles/Export.php#L2331-L2337)

### `efml_export_unique_filename_max_tries`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`100` |  | 
`$filename` |  | 

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 2761](ExternalFiles/Export.php#L2761-L2761)

### `efml_export_cache_control`

*Filter the value of the header "Cache-Control" for exported files.*

Used by services, which save it with the file (e.g. AWS S3 and Google Cloud Storage). Return an empty
string to save no value.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cache_control` | `string` | The value of the header.
`$attachment_id` | `int` | The attachment ID.
`$is_private` | `bool` | Whether the file is saved privately.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3000](ExternalFiles/Export.php#L3000-L3011)

### `efml_export_private`

*Filter whether files should be saved privately on the given external source.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$private` | `bool` | True if files should be saved privately.
`$term_id` | `int` | The term ID of the external source.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3035](ExternalFiles/Export.php#L3035-L3042)

### `efml_export_folder_placeholders`

*Filter the placeholders for the structure of the folders for exported files.*

Each placeholder must be replaced via the hook "efml_export_folder".

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$placeholders` | `array<string,string>` | List of placeholders (without "%") with their labels.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3058](ExternalFiles/Export.php#L3058-L3066)

### `efml_export_folder`

*Filter the folder for an exported file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$folder` | `string` | The folder (e.g. "2026/11").
`$attachment_id` | `int` | The attachment ID (0 for a new file, which is uploaded now).
`$export_time` | `int` | The time of the export.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3133](ExternalFiles/Export.php#L3133-L3141)

### `efml_export_check_filename_on_target`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$term_id` |  | 

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3196](ExternalFiles/Export.php#L3196-L3196)

### `efml_export_large_file_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$size` |  | 

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3577](ExternalFiles/Export.php#L3577-L3577)

### `efml_export_in_background`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 3628](ExternalFiles/Export.php#L3628-L3628)

### `efml_proxy_allow_delivery`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$external_file_obj` |  | 

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 247](ExternalFiles/Proxy.php#L247-L247)

### `efml_proxy_cache_control`

*Filter the value of the header "Cache-Control" for a file delivered by the proxy.*

Use e.g. "private, no-cache" for files, which are only delivered to some users.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cache_control` | `string` | The value of the header.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 446](ExternalFiles/Proxy.php#L446-L455)

### `efml_proxy_file_version`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$version` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 493](ExternalFiles/Proxy.php#L493-L493)

### `efml_proxy_slug`

*Filter the slug for the proxy-URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$slug` | `string` | The slug.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 742](ExternalFiles/Proxy.php#L742-L749)

### `efml_proxy_path`

*Filter the cache directory.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$path` | `string` | The absolute path to the directory.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 768](ExternalFiles/Proxy.php#L768-L774)

### `efml_fs_chmod`

*Filter the filesystem permissions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$mode` | `int` | The mode as int, e.g., 0644.

**Changelog**

Version | Description
------- | -----------
`5.2.0` | Available since 5.2.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 946](ExternalFiles/Proxy.php#L946-L952)

### `efml_cache_file_index_php`

*Change content of index.php to prevent access of cache file directory.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$index_php` | `string` | The content of the file.

**Changelog**

Version | Description
------- | -----------
`5.2.0` | Available since 5.2.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 959](ExternalFiles/Proxy.php#L959-L965)

### `efml_cache_file_htaccess`

*Change content of .htaccess to prevent access of cache files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$htaccess` | `string` | The content of the file.

**Changelog**

Version | Description
------- | -----------
`5.2.0` | Available since 5.2.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 976](ExternalFiles/Proxy.php#L976-L982)

### `efml_cache_file_web_config`

*Change content of web.config to prevent access of cache files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$web_config` |  | 

**Changelog**

Version | Description
------- | -----------
`5.2.0` | Available since 5.2.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 993](ExternalFiles/Proxy.php#L993-L999)

### `efml_local_url_hooks`

*Filter the hooks, whose content is checked for local URLs of exported and migrated files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array('the_content', 'the_excerpt', 'widget_text_content', 'widget_block_content', 'theme_mod_header_image', 'theme_mod_background_image', 'wp_get_custom_css')` |  | 

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Local_Urls.php](ExternalFiles/Local_Urls.php), [line 125](ExternalFiles/Local_Urls.php#L125-L131)

### `efml_local_urls_backfill_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`500` |  | 

Source: [app/ExternalFiles/Local_Urls.php](ExternalFiles/Local_Urls.php), [line 351](ExternalFiles/Local_Urls.php#L351-L351)

### `efml_redirect_missing_uploads`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$file` |  | 

Source: [app/ExternalFiles/Local_Urls.php](ExternalFiles/Local_Urls.php), [line 587](ExternalFiles/Local_Urls.php#L587-L587)

### `efml_file_types`

*Filter the list of supported file types.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of class names for the file type handlers.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types.php](ExternalFiles/File_Types.php), [line 184](ExternalFiles/File_Types.php#L184-L191)

### `efml_import_info_timeout`

*Filter the timeout for the AJAX-info-request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$info_timeout` | `int` | The timeout in ms (default 200ms).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 136](ExternalFiles/Forms.php#L136-L142)

### `efml_import_urls`

*Filter the URLs for use for this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | The list of URLs to add.
`$urls` | `string` | The original list of URLs from request (since 5.0.0).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 490](ExternalFiles/Forms.php#L490-L497)

### `efml_import_fields`

*Filter the given fields array to import URLs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `array<string,mixed>` | List of fields.
`$url_array` | `array<int,string>` | List of URLs to import.
`$urls` | `string` | The original list of URLs from request.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available 5.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 499](ExternalFiles/Forms.php#L499-L507)

### `efml_import_url`

*Filter single URL before it will be added as external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 541](ExternalFiles/Forms.php#L541-L547)

### `efml_import_urls_errors`

*Filter the errors during an AJAX-request to add URLs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$errors` | `array` | List of errors.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 572](ExternalFiles/Forms.php#L572-L578)

### `efml_dialog_after_adding`

*Filter the dialog after adding files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog configuration.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 685](ExternalFiles/Forms.php#L685-L691)

### `efml_import_url`

*Filter single URL before it will be added as external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 852](ExternalFiles/Forms.php#L852-L858)

### `efml_import_urls_errors`

*Filter the errors during an AJAX-request to add URLs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$errors` | `array` | List of errors.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 875](ExternalFiles/Forms.php#L875-L881)

### `efml_abilities`

*Filter the list of abilities this plugin provides for the Abilities API.*

Remove an entry to prevent its registration.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$abilities` | `array<string,array<string,mixed>>` | List of abilities, the key is the name without the plugin prefix.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/Abilities.php](ExternalFiles/Abilities.php), [line 209](ExternalFiles/Abilities.php#L209-L217)

### `efml_abilities_enabled`

*Filter whether the abilities of this plugin are enabled.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enabled` | `bool` | True if they are enabled.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/Abilities.php](ExternalFiles/Abilities.php), [line 254](ExternalFiles/Abilities.php#L254-L260)

### `efml_abilities_mode`

*Filter the mode for the abilities of this plugin.*

Use "read" to provide only the abilities which do not change anything, or "all" for all abilities.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`is_string($mode) ? $mode : 'read'` |  | 

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/ExternalFiles/Abilities.php](ExternalFiles/Abilities.php), [line 271](ExternalFiles/Abilities.php#L271-L279)

### `efml_import_options`

*Filter the options used for the import of this URL. Same as for the queue.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1131](ExternalFiles/Import_Runs.php#L1131-L1138)

### `efml_import_run_time_limit`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`20` |  | 

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1244](ExternalFiles/Import_Runs.php#L1244-L1244)

### `efml_background_import_threshold`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`500` |  | 

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1271](ExternalFiles/Import_Runs.php#L1271-L1271)

### `efml_background_import_direct_count`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`20` |  | 

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1287](ExternalFiles/Import_Runs.php#L1287-L1287)

### `efml_import_run_files`

*Filter the files of a directory, which will be added to the queue of the import run.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array<int,array<string,mixed>>` | List of files with their URLs.
`$run` | `array<string,mixed>` | The run.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1519](ExternalFiles/Import_Runs.php#L1519-L1526)

### `efml_export_run_page_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`500` |  | 

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1587](ExternalFiles/Import_Runs.php#L1587-L1587)

### `efml_duplicate_check`

*Filter to prevent duplicate check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Must be true to prevent check.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Import_Runs.php](ExternalFiles/Import_Runs.php), [line 1785](ExternalFiles/Import_Runs.php#L1785-L1794)

### `efml_sizes_retry_max_attempts`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`3` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Sizes.php](ExternalFiles/Sizes.php), [line 364](ExternalFiles/Sizes.php#L364-L364)

### `efml_filter_options`

*Filter the possible options.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array<string,string>` | The list of possible options.

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 141](ExternalFiles/Tables.php#L141-L147)

### `efml_table_column_file_source_dialog`

*Filter the dialog for this file info.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 621](ExternalFiles/Tables.php#L621-L628)

### `efml_table_column_source_title`

*Filter the title for show in source column in the media table for external files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$title` | `string` | The title to use.
`$attachment_id` | `int` | The post ID of the attachment.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 633](ExternalFiles/Tables.php#L633-L640)

### `efml_local_copies_file_usable`

*Filter whether an exported file is usable on its external source, before its local copies are deleted.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if the file has been loaded from its external source.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Local_Copies.php](ExternalFiles/Local_Copies.php), [line 358](ExternalFiles/Local_Copies.php#L358-L365)

### `efml_extensions`

*Filter the list of available file handling extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of extensions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions.php](ExternalFiles/Extensions.php), [line 152](ExternalFiles/Extensions.php#L152-L158)

### `efml_file_prevent_proxied_url`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$instance` |  | 

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 141](ExternalFiles/File.php#L141-L141)

### `efml_file_public_url`

*Filter the public URL of a URL of an external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$public_url` | `string` | The public URL (the given URL, if none is known).
`$url` | `string` | The given URL.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 197](ExternalFiles/File.php#L197-L205)

### `efml_file_availability`

*Filter and return the availability of an external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->availability` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 340](ExternalFiles/File.php#L340-L348)

### `efml_file_proxy_required`

*Filter whether the given file must be delivered via the proxy, independent of the proxy setting
of its file type.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$required` | `bool` | True if the proxy is required.
`$this` |  | 

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 490](ExternalFiles/File.php#L490-L498)

### `efml_local_file_name`

*Filter the file name to use, if this file is saved locally.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_name` | `string` | The file name.
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1210](ExternalFiles/File.php#L1210-L1217)

### `efml_migrations`

*Filter the list of migrations from other offload plugins.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of class names of objects, which extend Migration_Base.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Migrations.php](ExternalFiles/Migrations.php), [line 131](ExternalFiles/Migrations.php#L131-L137)

### `efml_migration_batch_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`50` |  | 

Source: [app/ExternalFiles/Migrations.php](ExternalFiles/Migrations.php), [line 439](ExternalFiles/Migrations.php#L439-L439)

### `efml_image_variant_formats`

*Filter the formats, which could be used as variants of images (mime type => file extension).*

The order is the order of the delivery via the proxy: the first format the browser supports is used.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$formats` | `array<string,string>` | The formats.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Variants.php](ExternalFiles/Variants.php), [line 80](ExternalFiles/Variants.php#L80-L88)

### `efml_image_variants`

*Filter the variants of an image in other formats (e.g. WebP or AVIF), which exist locally.*

Each entry names the size (or "full" for the image itself), the absolute local path of the variant and its
mime type. Only existing files in the content directory of WordPress in the formats of the hook
"efml_image_variant_formats" are used. Entries with "kind" => "backup" are backups of the plugin (e.g. of
the image before its optimization) in any image format: they are exported, deleted and restored together
with the image, but never delivered. The optional "name" sets the name on the external source, e.g. for
backups with the same name as the file of the image in another directory.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 
`$attachment_id` | `int` | The attachment ID.
`$paths` | `array<string,string>` | The absolute paths of the files of the image (size name => path).

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ExternalFiles/Variants.php](ExternalFiles/Variants.php), [line 114](ExternalFiles/Variants.php#L114-L129)

### `efml_service_features`

*This filter is documented in app/Services/Service_Base.php*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$features` |  | 
`$instance` |  | 

Source: [app/Services/Service_Plugin_Base.php](Services/Service_Plugin_Base.php), [line 48](Services/Service_Plugin_Base.php#L48-L49)

### `efml_service_plugin_provided_services`

*Filter the services, which a not installed plugin provides.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$services` | `array<string,string>` | List of service names with their labels.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Plugin_Base` | The object of the plugin.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Plugin_Base.php](Services/Service_Plugin_Base.php), [line 227](Services/Service_Plugin_Base.php#L227-L234)

### `efml_services_support`

*Filter the list of third party support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of third party support.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 261](Services/Services.php#L261-L267)

### `efml_source_blocked_by_constants`

*Filter whether an external source is not usable, because the credentials of its service
are defined via constants for another server.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if the source is not usable.
`$term_id` | `int` | The ID of the external source.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 392](Services/Services.php#L392-L400)

### `efml_service_names_for_blocking`

*Filter the names of the services, to which URLs of a protocol belong. If the credentials of such a
service are defined via constants, URLs of this protocol could only be used on their server.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$names` | `array<int,string>` | List of service names.
`$name` | `string` | The name of the protocol.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 497](Services/Services.php#L497-L505)

### `efml_service_names_for_constants`

*Filter the names of the services, whose constants could be used for a service or protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$names` | `array<int,string>` | List of service names.
`$name` | `string` | The name of the service or protocol.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 530](Services/Services.php#L530-L537)

### `efml_service_feature_definitions`

*Filter the definitions of the features of services.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,array{label: string, icon: string, dashicon: string}>` | List of features.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 645](Services/Services.php#L645-L651)

### `efml_services_overview`

*Filter the list of services for the overview in the settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$services` | `array<int,\ExternalFilesInMediaLibrary\Services\Service_Base>` | List of services.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Services` | The services object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 768](Services/Services.php#L768-L775)

### `efml_export_service_filename`

*File the filename for JSON-download of a service file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$filename` | `string` | The generated filename.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 941](Services/Services.php#L941-L948)

### `efml_service_multisite_hide_file`

*Filter whether the given file of the other website should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$post->post_mime_type` |  | 
`$post` | `\WP_Post` | The attachment.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/Multisite.php](Services/Multisite.php), [line 207](Services/Multisite.php#L207-L218)

### `efml_multisite_blog_allowed`

*Filter whether the actual user is allowed to access the media files of the given blog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$allowed` | `bool` | True if the access is allowed.
`$blog_id` | `int` | The requested blog ID.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/Services/Multisite.php](Services/Multisite.php), [line 375](Services/Multisite.php#L375-L382)

### `efml_service_modes`

*Filter the list of possible modes of this service.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$modes` | `array<string,string>` | List of modes.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 405](Services/Service_Base.php#L405-L412)

### `efml_service_oauth_return_url`

*Filter the URL to return to after a service has been connected via OAuth.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 485](Services/Service_Base.php#L485-L492)

### `efml_connection_test_file_name`

*Filter the name of the test file of the connection test.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_name` | `string` | The file name.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 755](Services/Service_Base.php#L755-L762)

### `efml_service_fields`

*Filter the fields of a service incl. their values before they are used.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `array<string,array<string,mixed>>` | List of fields.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 1318](Services/Service_Base.php#L1318-L1325)

### `efml_service_setup_guide`

*Filter the help for the setup of a service, which explains where to find the access data.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$guide` | `array<string,mixed>` | The help with the keys intro, steps, hints, code and links.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 1638](Services/Service_Base.php#L1638-L1645)

### `efml_service_field_constant_name`

*Filter the name of the constant for a field of a service.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$constant_name` | `string` | The name of the constant.
`$field_name` | `string` | The name of the field.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.
`$connection` | `string` | The name of the connection ("default" for the constants without connection name).

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 1762](Services/Service_Base.php#L1762-L1771)

### `efml_service_connections_constant_name`

*Filter the name of the constant, which contains the list of named connections of a service.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$constant_name` | `string` | The name of the constant.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 1785](Services/Service_Base.php#L1785-L1792)

### `efml_service_required_fields_for_constants`

*Filter the list of fields of a service, which must all be defined via constants,
before any constant of this service is used.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$required_fields` | `array<int,string>` | List of field names.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 2058](Services/Service_Base.php#L2058-L2066)

### `efml_service_constants_connection`

*Filter the connection, whose constants should be used for the given fields and URL.*

Return null to use no constants.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `string\|null` | The name of the connection or null.
`$fields` | `array<string,mixed>` | List of fields.
`$url` | `string` | The URL.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 2293](Services/Service_Base.php#L2293-L2303)

### `efml_service_url_matching_constants`

*Filter whether the given URL is using the server defined via constants for this service.*

Only if true is returned, the values of the constants are used for this URL.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if the URL is matching.
`$url` | `string` | The URL.
`$server` | `string` | The server defined via constant.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 2424](Services/Service_Base.php#L2424-L2434)

### `efml_service_fields_matching_constants`

*Filter whether the given fields are for the target (e.g. a bucket) defined via constants.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if the fields are matching.
`$fields` | `array<string,mixed>` | List of fields.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.
`$connection` | `string` | The name of the connection ("default" for the constants without connection name).

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 2536](Services/Service_Base.php#L2536-L2545)

### `efml_service_search_keywords`

*Filter the search terms of a service, e.g. for the command palette of WordPress.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array_values(array_unique($keywords))` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 2827](Services/Service_Base.php#L2827-L2834)

### `efml_service_features`

*Filter the features of a service, which are shown e.g. on the tiles to choose a service.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$features` | `array<string,bool>` | List of features.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Service_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 2868](Services/Service_Base.php#L2868-L2875)

### `efml_service_ftp_hide_file`

*Filter whether given FTP file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$path` | `string` | Absolute path to the given file.
`$directory` | `string` | The requested directory.
`$is_dir` | `bool` | True if this entry is a directory.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 432](Services/Ftp.php#L432-L444)

### `efml_service_ftp_user_settings`

*Filter the list of possible user settings for the FTP.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,mixed>` | The list of settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 817](Services/Ftp.php#L817-L823)

### `efml_service_rest_hide_file`

*Filter whether given local file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$mime_type` | `string` | The used mime type.
`$directory` | `string` | The requested directory.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Rest.php](Services/Rest.php), [line 571](Services/Rest.php#L571-L582)

### `efml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$entry` |  | 
`$url` | `string` | The requested external URL.
`$response_headers` | `array<string,mixed>` | The response header.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/Services/Rest.php](Services/Rest.php), [line 1006](Services/Rest.php#L1006-L1015)

### `efml_directory_listing_rest_paths`

*Filter the possible WordPress REST API paths.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of possible WordPress REST API paths.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Rest.php](Services/Rest.php), [line 1070](Services/Rest.php#L1070-L1076)

### `efml_service_rest_default_endpoint`

*Filter the default REST API endpoint for service REST API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default_endpoint` | `string` | The default endpoint.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Rest.php](Services/Rest.php), [line 1144](Services/Rest.php#L1144-L1150)

### `efml_service_rest_file_data`

*Filter the REST API file results.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` |  | 
`$file_path` |  | 
`$file` |  | 

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0

Source: [app/Services/Rest.php](Services/Rest.php), [line 1241](Services/Rest.php#L1241-L1250)

### `efml_zip_objects`

*Filter the list of available zip object names.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$zip_objects` | `array<int,string>` | List of object names.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Zip.php](Services/Zip.php), [line 946](Services/Zip.php#L946-L952)

### `efml_zip_max_size_limit`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$limit` |  | 
`$settings` |  | 

Source: [app/Services/Zip.php](Services/Zip.php), [line 1131](Services/Zip.php#L1131-L1131)

### `efml_service_dropbox_hide_file`

*Filter whether the given DropBox entry should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$dropbox_entry['path_lower']` |  | 
`$directory` | `string` | The requested directory.
`'folder' === $dropbox_entry['.tag']` |  | 

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1077](Services/DropBox.php#L1077-L1089)

### `efml_dropbox_access_token_url`

*Filter the URL where Dropbox user will find their access token.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1918](Services/DropBox.php#L1918-L1924)

### `efml_dropbox_oauth_url`

*Filter the DropBox OAuth URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1998](Services/DropBox.php#L1998-L2004)

### `efml_dropbox_client`

*Filter the DropBox client to use, e.g. for tests. Return a client to use it instead of the default one.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`null` |  | 

**Changelog**

Version | Description
------- | -----------
`5.5.2` | Available since 5.5.2.

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 2132](Services/DropBox.php#L2132-L2138)

### `efml_dropbox_oauth_url_step_2`

*Filter the DropBox OAuth URL for step 2*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 2163](Services/DropBox.php#L2163-L2169)

### `efml_youtube_api_url`

*Filter the YouTube API URL to use.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$api_url` | `string` | The API URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 883](Services/Youtube.php#L883-L889)

### `efml_youtube_channel_url`

*Filter the YouTube channel URL to use.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$channel_url` | `string` | The API URL.

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 903](Services/Youtube.php#L903-L909)

### `efml_service_youtube_user_settings`

*Filter the list of possible user settings for YouTube.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,mixed>` | The list of settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 1235](Services/Youtube.php#L1235-L1241)

### `efml_url_list_check_interval`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`60` |  | 
`$url` |  | 

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1217](Services/Url_List_Base.php#L1217-L1217)

### `efml_url_list_max_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`wp_max_upload_size()` |  | 
`$url` |  | 

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1369](Services/Url_List_Base.php#L1369-L1369)

### `efml_url_list_max_records`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`100000` |  | 
`$attachment_id` |  | 

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1596](Services/Url_List_Base.php#L1596-L1596)

### `efml_url_list_role_names`

*Filter the possible names of fields for each role during the automatic detection of the mapping for lists.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$names` | `array<string,array<int,string>>` | List of names per role.
`$instance` | `\ExternalFilesInMediaLibrary\Services\Url_List_Base` | The service object.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1971](Services/Url_List_Base.php#L1971-L1978)

### `efml_zip_max_file_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$max_size` |  | 
`$instance` |  | 

Source: [app/Services/Zip/Zip_Base.php](Services/Zip/Zip_Base.php), [line 293](Services/Zip/Zip_Base.php#L293-L293)

### `efml_is_import_running_for_mcs`

*Prevent import of external URLs via Media Cloud Sync.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$prevent_import` | `bool` | True to prevent the import of external URLs in Media Cloud Sync.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ThirdParty/MediaCloudSync.php](ThirdParty/MediaCloudSync.php), [line 83](ThirdParty/MediaCloudSync.php#L83-L92)

### `efml_media_cleaner_check_external_files`

*Filter whether Media Cleaner may check the usage of files imported from external sources.*

Media Cleaner could not detect their usage via their external URL, so they are considered as used by
default. Return true to use the result of Media Cleaner (e.g. if they are only used via their ID).

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`false` |  | 
`$attachment_id` | `int` | The attachment ID.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ThirdParty/MediaCleaner.php](ThirdParty/MediaCleaner.php), [line 248](ThirdParty/MediaCleaner.php#L248-L258)

### `efml_woocommerce_download_lifetime`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`5 * MINUTE_IN_SECONDS` |  | 
`$external_file_obj` |  | 

Source: [app/ThirdParty/WooCommerce.php](ThirdParty/WooCommerce.php), [line 703](ThirdParty/WooCommerce.php#L703-L703)

### `efml_supported_plugin_files`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` |  | 

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 181](ThirdParty/ThirdPartySupport.php#L181-L181)

### `efml_third_party_support`

*Filter the list of third party support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `string[]` | List of third party support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 266](ThirdParty/ThirdPartySupport.php#L266-L272)

### `efml_instant_images_possible_providers`

*Filter the list of platforms of Instant Images, which could be used as external source.*

Only add platforms which allow the permanent usage of their image URLs.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,string>` | List of platforms (slug => label).

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ThirdParty/InstantImages.php](ThirdParty/InstantImages.php), [line 305](ThirdParty/InstantImages.php#L305-L313)

### `efml_instant_images_providers_without_proxy`

*Filter the list of platforms of Instant Images, whose images must not be delivered via proxy.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of platform slugs.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/ThirdParty/InstantImages.php](ThirdParty/InstantImages.php), [line 324](ThirdParty/InstantImages.php#L324-L330)

### `efml_set_template_directory`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$directory` |  | 

Source: [app/Plugin/Templates.php](Plugin/Templates.php), [line 81](Plugin/Templates.php#L81-L81)

### `efml_capability_sets`

*Filter the list of possible capability sets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of capability set objects.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/CapabilitySets.php](Plugin/CapabilitySets.php), [line 71](Plugin/CapabilitySets.php#L71-L77)

### `efml_hide_intro`

*Hide intro via hook.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Return true to hide the intro.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0

Source: [app/Plugin/Intro.php](Plugin/Intro.php), [line 71](Plugin/Intro.php#L71-L78)

### `efml_intro_pdf_url`

*Filter the intro PDF URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Intro.php](Plugin/Intro.php), [line 361](Plugin/Intro.php#L361-L367)

### `efml_is_settings_request`

*Filter whether the settings of this plugin are shown or saved in this request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$is_settings_request` | `bool` | True, if the settings are shown or saved.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Settings.php](Plugin/Settings.php), [line 121](Plugin/Settings.php#L121-L127)

### `efml_settings_moved_tabs`

*Filter the list of tabs, which are sub-tabs of other tabs, with the names of their parent tabs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,string>` | List of tab names (key) with the names of their parent tabs (value).

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Settings.php](Plugin/Settings.php), [line 291](Plugin/Settings.php#L291-L297)

### `efml_setting_description_attachment_pages`

*Filter the description to setting to disable the attachment pages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$description` | `string` | The description.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings.php](Plugin/Settings.php), [line 715](Plugin/Settings.php#L715-L721)

### `efml_command_palette_pages`

*Filter the settings, pages and documentations of this plugin, which could be found in the command palette.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}>` | List of entries.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Commands.php](Plugin/Commands.php), [line 272](Plugin/Commands.php#L272-L278)

### `efml_schedule_our_events`

*Filter the list of our own events,
e.g., to check if all, which are enabled in setting are active.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$our_events` | `array<string,array<string,mixed>>` | List of our own events in WP-cron.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 118](Plugin/Schedules.php#L118-L126)

### `efml_disable_cron_check`

*Disable the additional cron check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if check should be disabled.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 144](Plugin/Schedules.php#L144-L150)

### `efml_schedules`

*Add custom schedule-objects to use.*

This must be objects based on ExternalFilesInMediaLibrary\Plugin\Schedules_Base.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list_of_schedules` | `string[]` | List of additional schedules.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 281](Plugin/Schedules.php#L281-L290)

### `efml_is_website_copy`

*Filter whether the actual website is a copy of the main website (e.g. a staging site).*

On a copy, files are not exported to or deleted on external sources, and scheduled
synchronizations are paused.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$is_copy` | `bool` | True if the website is a copy.
`$fingerprint` | `array<string,string>` | The saved fingerprint of the main website.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Site_Copy.php](Plugin/Site_Copy.php), [line 236](Plugin/Site_Copy.php#L236-L246)

### `efml_supported_mime_types`

*Filter the possible mime types this plugin could support. This is the list used for the setting in the backend.*

To add files of type "your/mime" with file extension ".yourmime" use this example:

```
add_filter( 'efml_supported_mime_types', function( $list ) {
 $list['your/mime'] = array(
     'label' => 'Title of your mime',
     'ext' => 'yourmime'
 );
 return $list;
} );
```

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$mime_types` | `array<string,array<string,string>>` | List of supported mime types.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 256](Plugin/Helper.php#L256-L275)

### `efml_get_mime_types`

*Filter the list of possible mime types. This is the list used by the plugin during file-checks
and is invisible or editable in the backend.*

To add files of type "your/mime" with file extension ".yourmime" use this example:

```
add_filter( 'efml_get_mime_types', function( $mime_types ) {
 $mime_types[] = 'your/mime';
 return $mime_types;
} );
```

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$mime_types` | `string[]` | List of mime types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 440](Plugin/Helper.php#L440-L457)

### `efml_enqueued_file_version`

*Filter the used file version (for JS- and CSS-files, which get enqueued).*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$plugin_version` | `string` | The plugin-version.
`$filepath` | `string` | The absolute path to the requested file.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 1092](Plugin/Helper.php#L1092-L1100)

### `efml_file_base`

*Filter the allowed base directory for local files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$base` | `null\|string` | The detected base directory.

**Changelog**

Version | Description
------- | -----------
`5.2.0` | Available since 5.2.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 1251](Plugin/Helper.php#L1251-L1257)

### `efml_plugin_row_meta`

*Filter the links in row meta of our plugin in plugin list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$row_meta` | `array` | List of links.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/Plugin/Admin/Admin.php](Plugin/Admin/Admin.php), [line 272](Plugin/Admin/Admin.php#L272-L278)

### `efml_help_tabs`

*Filter the list of help tabs with its contents.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,mixed>` | List of help tabs.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Admin/Help_System.php](Plugin/Admin/Help_System.php), [line 214](Plugin/Admin/Help_System.php#L214-L220)

### `efml_plugin_sources`

*Filter the list of possible plugin sources for services of this plugin.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of plugin sources.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Admin/Plugins.php](Plugin/Admin/Plugins.php), [line 439](Plugin/Admin/Plugins.php#L439-L445)

### `efml_site_health_endpoints`

*Filter the endpoints for Site Health this plugin is using.*

Hint: these are just arrays, which define the endpoints.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,array<string,mixed>>` | List of endpoints.

Source: [app/Plugin/Admin/Site_Health.php](Plugin/Admin/Site_Health.php), [line 85](Plugin/Admin/Site_Health.php#L85-L92)

### `efml_directory_translations`

*Filter the translations to use for directory listings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$translations` | `array<string,mixed>` | List of translations.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 644](Plugin/Admin/Directory_Listing.php#L644-L650)

### `efml_service_plugins`

*Filter the list of available service plugins.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,\ExternalFilesInMediaLibrary\Services\Service_Plugin_Base>` | The list.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 1250](Plugin/Admin/Directory_Listing.php#L1250-L1256)

### `efml_configurations`

*Filter the list of possible configurations.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of configurations objects.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Configurations.php](Plugin/Configurations.php), [line 78](Plugin/Configurations.php#L78-L84)

### `efml_log_table_filter`

*Filter the list before output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of filter.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Tables/Logs.php](Plugin/Tables/Logs.php), [line 220](Plugin/Tables/Logs.php#L220-L226)

### `efml_queue_table_filter`

*Filter the list before output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,string>` | List of filter.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Tables/Queue.php](Plugin/Tables/Queue.php), [line 296](Plugin/Tables/Queue.php#L296-L302)

### `efml_schedule_interval`

*Filter the interval for a single schedule.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$interval` | `string` | The interval.
`$instance` | `\ExternalFilesInMediaLibrary\Plugin\Schedules_Base` | The schedule-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 79](Plugin/Schedules_Base.php#L79-L86)

### `efml_schedule_enabling`

*Filter whether to activate this schedule.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if this object should NOT be enabled.
`$instance` | `\ExternalFilesInMediaLibrary\Plugin\Schedules_Base` | Actual object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 237](Plugin/Schedules_Base.php#L237-L245)

### `efml_current_language`

*Filter the resulting language.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$wp_language` | `string` | The language-name (e.g., "en").

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Languages.php](Plugin/Languages.php), [line 80](Plugin/Languages.php#L80-L87)

### `efml_sizes_per_service`

*Filter the resulting list of file sizes per service.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$sizes` | `array<string,int>` | List of service name => size in bytes.

**Changelog**

Version | Description
------- | -----------
`5.5.0` | Available since 5.5.0.

Source: [app/Plugin/Statistics.php](Plugin/Statistics.php), [line 414](Plugin/Statistics.php#L414-L420)

### `efml_setup_config`

*Filter the configuration of the setup.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$config` | `array<string,mixed>` | The configuration.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 367](Plugin/Setup.php#L367-L373)

### `efml_setup_goals`

*Filter the goals of the setup.*

Each goal is an array with the keys label, description, icon, features, service, options, defaults
and action. See Setup::get_goals() for details.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$goals` | `array<string,array<string,mixed>>` | List of goals.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 544](Plugin/Setup.php#L544-L553)

### `efml_setup_option_definitions`

*Filter the options the goals of the setup could offer.*

Use the action "efml_setup_apply_option" to save the chosen value of an own option.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array<string,array<string,mixed>>` | List of options with their field configuration.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 650](Plugin/Setup.php#L650-L658)

### `efml_setup_option_available`

*Filter whether an option of the setup could be offered for the given service.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$available` | `bool` | True if the option could be offered.
`$option` | `string` | The name of the option.
`$service_obj` | `\ExternalFilesInMediaLibrary\Services\Service_Base\|null` | The service object.

**Changelog**

Version | Description
------- | -----------
`6.0.0` | Available since 6.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 685](Plugin/Setup.php#L685-L693)

### `efml_setup_process_completed_text`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$text` |  | 
`$this->process_messages` |  | 

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 2807](Plugin/Setup.php#L2807-L2807)

### `efml_intervals`

*Filter the list of possible intervals.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of our interval objects.

**Changelog**

Version | Description
------- | -----------
`5.4.0` | Available since 5.4.0.

Source: [app/Plugin/Intervals.php](Plugin/Intervals.php), [line 80](Plugin/Intervals.php#L80-L86)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


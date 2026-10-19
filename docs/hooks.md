# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 365](ExternalFiles/Extensions/Queue.php#L365-L371)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 394](ExternalFiles/Extensions/Queue.php#L394-L400)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 184](ExternalFiles/Import.php#L184-L192)

### `efml_before_file_list`

*Run action just before we go through the list of resulting files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  |
`$files` |  |

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 284](ExternalFiles/Import.php#L284-L287)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 329](ExternalFiles/Import.php#L329-L335)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 390](ExternalFiles/Import.php#L390-L397)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 490](ExternalFiles/Import.php#L490-L498)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 550](ExternalFiles/Import.php#L550-L558)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 572](ExternalFiles/Import.php#L572-L580)

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

Source: [app/ExternalFiles/File_Types/Image.php](ExternalFiles/File_Types/Image.php), [line 148](ExternalFiles/File_Types/Image.php#L148-L155)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 185](ExternalFiles/Tables.php#L185-L191)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 377](ExternalFiles/Tables.php#L377-L383)

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

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 354](ExternalFiles/ExportDialog.php#L354-L360)

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

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 816](ExternalFiles/Export.php#L816-L822)

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

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 888](ExternalFiles/Export.php#L888-L897)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 465](ExternalFiles/Forms.php#L465-L471)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 589](ExternalFiles/Forms.php#L589-L595)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 806](ExternalFiles/File.php#L806-L812)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 830](ExternalFiles/File.php#L830-L836)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 865](ExternalFiles/File.php#L865-L871)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 891](ExternalFiles/File.php#L891-L897)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 922](ExternalFiles/File.php#L922-L928)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 988](ExternalFiles/File.php#L988-L995)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 1071](ExternalFiles/File.php#L1071-L1078)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 130](ExternalFiles/Protocols/Sftp.php#L130-L137)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 150](ExternalFiles/Protocols/Sftp.php#L150-L158)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 191](ExternalFiles/Protocols/Sftp.php#L191-L198)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 217](ExternalFiles/Protocols/Sftp.php#L217-L225)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 189](ExternalFiles/Protocols/Ftp.php#L189-L196)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 211](ExternalFiles/Protocols/Ftp.php#L211-L219)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 247](ExternalFiles/Protocols/Ftp.php#L247-L254)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 273](ExternalFiles/Protocols/Ftp.php#L273-L281)

### `efml_proxy_before`

*Run additional tasks before proxy tries to load a cached external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` |  |

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 187](ExternalFiles/Proxy.php#L187-L190)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 617](ExternalFiles/Synchronization.php#L617-L625)

### `efml_after_sync`

*Allow to add additional tasks after sync has been running.*

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 704](ExternalFiles/Synchronization.php#L704-L712)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 1273](ExternalFiles/Synchronization.php#L1273-L1279)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 308](ExternalFiles/Files.php#L308-L314)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 644](ExternalFiles/Files.php#L644-L650)

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

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 2114](Services/Url_List_Base.php#L2114-L2121)

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

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 2173](Services/Url_List_Base.php#L2173-L2179)

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

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 2205](Services/Url_List_Base.php#L2205-L2212)

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

Source: [app/Services/DropBox/Protocol.php](Services/DropBox/Protocol.php), [line 148](Services/DropBox/Protocol.php#L148-L156)

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

Source: [app/Services/DropBox/Protocol.php](Services/DropBox/Protocol.php), [line 169](Services/DropBox/Protocol.php#L169-L176)

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

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 155](Services/Service_Base.php#L155-L163)

## Filters

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

Source: [app/Plugin/Statistics.php](Plugin/Statistics.php), [line 409](Plugin/Statistics.php#L409-L415)

### `efml_set_template_directory`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$directory` |  |

Source: [app/Plugin/Templates.php](Plugin/Templates.php), [line 81](Plugin/Templates.php#L81-L81)

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

Source: [app/Plugin/Settings.php](Plugin/Settings.php), [line 341](Plugin/Settings.php#L341-L347)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 255](Plugin/Helper.php#L255-L274)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 299](Plugin/Helper.php#L299-L316)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 843](Plugin/Helper.php#L843-L851)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 1004](Plugin/Helper.php#L1004-L1010)

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

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 548](Plugin/Admin/Directory_Listing.php#L548-L554)

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

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 1127](Plugin/Admin/Directory_Listing.php#L1127-L1133)

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

Source: [app/Plugin/Admin/Admin.php](Plugin/Admin/Admin.php), [line 265](Plugin/Admin/Admin.php#L265-L271)

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

Source: [app/Plugin/Admin/Plugins.php](Plugin/Admin/Plugins.php), [line 356](Plugin/Admin/Plugins.php#L356-L362)

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

Source: [app/Plugin/Admin/Help_System.php](Plugin/Admin/Help_System.php), [line 116](Plugin/Admin/Help_System.php#L116-L122)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 298](ExternalFiles/Extensions/Queue.php#L298-L305)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 354](ExternalFiles/Extensions/Queue.php#L354-L360)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 159](ExternalFiles/Import.php#L159-L166)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 169](ExternalFiles/Import.php#L169-L180)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 271](ExternalFiles/Import.php#L271-L279)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 300](ExternalFiles/Import.php#L300-L308)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 344](ExternalFiles/Import.php#L344-L353)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 355](ExternalFiles/Import.php#L355-L363)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 376](ExternalFiles/Import.php#L376-L385)

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

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 472](ExternalFiles/Import.php#L472-L486)

### `efml_user_agent`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$string_to_add` |  |

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 797](ExternalFiles/Import.php#L797-L797)

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

Source: [app/ExternalFiles/File_Types.php](ExternalFiles/File_Types.php), [line 179](ExternalFiles/File_Types.php#L179-L186)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 107](ExternalFiles/Tables.php#L107-L113)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 350](ExternalFiles/Tables.php#L350-L357)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 362](ExternalFiles/Tables.php#L362-L369)

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

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 92](ExternalFiles/File_Types_Base.php#L92-L101)

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

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 132](ExternalFiles/File_Types_Base.php#L132-L139)

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

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 215](ExternalFiles/ExportDialog.php#L215-L222)

### `efml_export_configuration_url_state`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` |  |
`$url` |  |

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 327](ExternalFiles/ExportDialog.php#L327-L327)

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

Source: [app/ExternalFiles/ExportDialog.php](ExternalFiles/ExportDialog.php), [line 383](ExternalFiles/ExportDialog.php#L383-L390)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 99](ExternalFiles/Protocol_Base.php#L99-L106)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 276](ExternalFiles/Protocol_Base.php#L276-L283)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 445](ExternalFiles/Protocol_Base.php#L445-L454)

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

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 483](ExternalFiles/Export.php#L483-L490)

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

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 789](ExternalFiles/Export.php#L789-L796)

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

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 871](ExternalFiles/Export.php#L871-L879)

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

Source: [app/ExternalFiles/Export.php](ExternalFiles/Export.php), [line 1344](ExternalFiles/Export.php#L1344-L1350)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 135](ExternalFiles/Forms.php#L135-L141)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 479](ExternalFiles/Forms.php#L479-L486)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 488](ExternalFiles/Forms.php#L488-L496)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 527](ExternalFiles/Forms.php#L527-L533)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 555](ExternalFiles/Forms.php#L555-L561)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 668](ExternalFiles/Forms.php#L668-L674)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 822](ExternalFiles/Forms.php#L822-L828)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 842](ExternalFiles/Forms.php#L842-L848)

### `efml_file_prevent_proxied_url`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$instance` |  |

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 134](ExternalFiles/File.php#L134-L134)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 269](ExternalFiles/File.php#L269-L277)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 576](ExternalFiles/Protocols/Http.php#L576-L585)

### `efml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$url` |  |

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 672](ExternalFiles/Protocols/Http.php#L672-L672)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 690](ExternalFiles/Protocols/Http.php#L690-L697)

### `efml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$url` |  |

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 728](ExternalFiles/Protocols/Http.php#L728-L728)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 741](ExternalFiles/Protocols/Http.php#L741-L749)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 782](ExternalFiles/Protocols/Http.php#L782-L789)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 818](ExternalFiles/Protocols/Http.php#L818-L825)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 850](ExternalFiles/Protocols/Http.php#L850-L857)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$url` |  |

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 896](ExternalFiles/Protocols/Http.php#L896-L896)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 251](ExternalFiles/Protocols/Sftp.php#L251-L258)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 363](ExternalFiles/Protocols/Sftp.php#L363-L372)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 279](ExternalFiles/Protocols/File.php#L279-L279)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 339](ExternalFiles/Protocols/File.php#L339-L348)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$url` |  |

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 422](ExternalFiles/Protocols/File.php#L422-L422)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 546](ExternalFiles/Protocols/File.php#L546-L552)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 316](ExternalFiles/Protocols/Ftp.php#L316-L323)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 402](ExternalFiles/Protocols/Ftp.php#L402-L411)

### `efml_ftp_use_pasv_address`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$use_pasv_address` |  |
`$url` |  |

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 520](ExternalFiles/Protocols/Ftp.php#L520-L520)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$url` |  |

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 579](ExternalFiles/Protocols/Ftp.php#L579-L579)

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

Source: [app/ExternalFiles/Extensions.php](ExternalFiles/Extensions.php), [line 151](ExternalFiles/Extensions.php#L151-L157)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 271](ExternalFiles/Proxy.php#L271-L278)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 297](ExternalFiles/Proxy.php#L297-L303)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 475](ExternalFiles/Proxy.php#L475-L481)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 488](ExternalFiles/Proxy.php#L488-L494)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 505](ExternalFiles/Proxy.php#L505-L511)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 522](ExternalFiles/Proxy.php#L522-L528)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 271](ExternalFiles/Synchronization.php#L271-L277)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 563](ExternalFiles/Synchronization.php#L563-L573)

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

### `efml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  |
`$url` |  |
`$attachment_id` |  |

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 207](ExternalFiles/Files.php#L207-L207)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 247](ExternalFiles/Files.php#L247-L253)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 417](ExternalFiles/Files.php#L417-L423)

### `efml_files_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  |
`$url` |  |

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 1381](ExternalFiles/Files.php#L1381-L1381)

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

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1453](Services/DropBox.php#L1453-L1459)

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

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1533](Services/DropBox.php#L1533-L1539)

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

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1667](Services/DropBox.php#L1667-L1673)

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

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1698](Services/DropBox.php#L1698-L1704)

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

Source: [app/Services/Multisite.php](Services/Multisite.php), [line 335](Services/Multisite.php#L335-L342)

### `efml_zip_max_file_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$max_size` |  |
`$instance` |  |

Source: [app/Services/Zip/Zip_Base.php](Services/Zip/Zip_Base.php), [line 293](Services/Zip/Zip_Base.php#L293-L293)

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

Source: [app/Services/Rest.php](Services/Rest.php), [line 564](Services/Rest.php#L564-L575)

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

Source: [app/Services/Rest.php](Services/Rest.php), [line 999](Services/Rest.php#L999-L1008)

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

Source: [app/Services/Rest.php](Services/Rest.php), [line 1063](Services/Rest.php#L1063-L1069)

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

Source: [app/Services/Rest.php](Services/Rest.php), [line 1137](Services/Rest.php#L1137-L1143)

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

Source: [app/Services/Rest.php](Services/Rest.php), [line 1234](Services/Rest.php#L1234-L1243)

### `efml_url_list_check_interval`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`60` |  |
`$url` |  |

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1194](Services/Url_List_Base.php#L1194-L1194)

### `efml_url_list_max_size`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`wp_max_upload_size()` |  |
`$url` |  |

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1346](Services/Url_List_Base.php#L1346-L1346)

### `efml_url_list_max_records`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`100000` |  |
`$attachment_id` |  |

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1573](Services/Url_List_Base.php#L1573-L1573)

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

Source: [app/Services/Url_List_Base.php](Services/Url_List_Base.php), [line 1948](Services/Url_List_Base.php#L1948-L1955)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 862](Services/Youtube.php#L862-L868)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 882](Services/Youtube.php#L882-L888)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 1180](Services/Youtube.php#L1180-L1186)

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

Source: [app/Services/Services.php](Services/Services.php), [line 195](Services/Services.php#L195-L201)

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

Source: [app/Services/Services.php](Services/Services.php), [line 440](Services/Services.php#L440-L447)

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

Source: [app/Services/Zip.php](Services/Zip.php), [line 922](Services/Zip.php#L922-L928)

### `efml_zip_max_size_limit`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$limit` |  |
`$settings` |  |

Source: [app/Services/Zip.php](Services/Zip.php), [line 1107](Services/Zip.php#L1107-L1107)

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

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 400](Services/Ftp.php#L400-L412)

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

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 781](Services/Ftp.php#L781-L787)

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

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 169](Services/Service_Base.php#L169-L176)

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

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 157](ThirdParty/ThirdPartySupport.php#L157-L163)

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

Source: [app/ThirdParty/MediaCloudSync.php](ThirdParty/MediaCloudSync.php), [line 74](ThirdParty/MediaCloudSync.php#L74-L83)

### `efml_file_import_error`

*Filter the error, which prevents the import of a single file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$error` | `string` | The error text (empty to allow the import).
`$file_data` | `array<string,mixed>` | The file data.
`$url` | `string` | The URL used for the import.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/ExternalFiles/Import.php](ExternalFiles/Import.php), [line 635](ExternalFiles/Import.php#L635-L648)

### `efml_file_size_limits`

*Filter the limits for the file size of a single file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$limits` | `array` | The limits (in bytes) with their source.
`$mime_type` | `string` | The mime type of the file (empty if not known).
`$service` | `string` | The name of the used service (empty if no service is used).

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/ExternalFiles/Extensions/File_Size_Limits.php](ExternalFiles/Extensions/File_Size_Limits.php), [line 462](ExternalFiles/Extensions/File_Size_Limits.php#L462-L470)

### `efml_file_size_limits_services_without_size`

*Filter the list of services, whose files do not have a file size. They do not get settings for the limits of the file size.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of service names.

**Changelog**

Version | Description
------- | -----------
`5.6.0` | Available since 5.6.0.

Source: [app/ExternalFiles/Extensions/File_Size_Limits.php](ExternalFiles/Extensions/File_Size_Limits.php), [line 314](ExternalFiles/Extensions/File_Size_Limits.php#L314-L322)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


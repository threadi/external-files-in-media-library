# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `eml_before_file_list`

*Run action just before we go through the list of resulting files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` |  | 
`$files` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 367](ExternalFiles/Files.php#L367-L370)

### `eml_before_file_save`

*Run additional tasks before new external file will be added.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_data` | `array` | The array with the file data.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 380](ExternalFiles/Files.php#L380-L386)

### `eml_file_import_before_save`

*Run action just before the file is saved in database.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_data['url']` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 418](ExternalFiles/Files.php#L418-L425)

### `eml_after_file_save`

*Run additional tasks after new external file has been added.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The object of the external file.
`$file_data` | `array` | The array with the file data.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 524](ExternalFiles/Files.php#L524-L531)

### `eml_file_delete`

*Run additional tasks for URL deletion.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The object which has been deleted.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 560](ExternalFiles/Files.php#L560-L566)

### `eml_sftp_directory_import_start`

*Run action on beginning of presumed directory import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 103](ExternalFiles/Protocols/Sftp.php#L103-L110)

### `eml_sftp_directory_import_files`

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 126](ExternalFiles/Protocols/Sftp.php#L126-L134)

### `eml_sftp_directory_import_file_before_to_list`

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 173](ExternalFiles/Protocols/Sftp.php#L173-L181)

### `eml_file_directory_import_start`

*Run action on beginning of presumed directory import via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 45](ExternalFiles/Protocols/File.php#L45-L52)

### `eml_file_directory_import_file_check`

*Run action just before the file check via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 87](ExternalFiles/Protocols/File.php#L87-L94)

### `eml_file_directory_import_file_before_to_list`

*Run action just before the file is added to the list via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.
`$file_list` | `array` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 108](ExternalFiles/Protocols/File.php#L108-L116)

### `eml_http_directory_import_start`

*Run action on beginning of presumed directory import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 236](ExternalFiles/Protocols/Http.php#L236-L243)

### `eml_http_directory_import_files`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 298](ExternalFiles/Protocols/Http.php#L298-L306)

### `eml_http_directory_import_file_check`

*Run action just before the file check via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 350](ExternalFiles/Protocols/Http.php#L350-L357)

### `eml_http_directory_import_file_before_to_list`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 370](ExternalFiles/Protocols/Http.php#L370-L378)

### `eml_ftp_directory_import_start`

*Run action on beginning of presumed directory import via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 155](ExternalFiles/Protocols/Ftp.php#L155-L162)

### `eml_ftp_directory_import_files`

*Run action if we have files to check via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 
`$file_list` | `array` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 186](ExternalFiles/Protocols/Ftp.php#L186-L194)

### `eml_ftp_directory_import_file_check`

*Run action just before the file check via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 219](ExternalFiles/Protocols/Ftp.php#L219-L226)

### `eml_ftp_directory_import_file_before_to_list`

*Run action just before the file is added to the list via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.
`$file_list_new` | `array` | List of files to process.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 242](ExternalFiles/Protocols/Ftp.php#L242-L250)

### `eml_image_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File_Types\File` | The external files object.
`$image_meta` | `array` | The image meta data.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Image.php](ExternalFiles/File_Types/Image.php), [line 144](ExternalFiles/File_Types/Image.php#L144-L151)

### `eml_video_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File_Types\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Audio.php](ExternalFiles/File_Types/Audio.php), [line 143](ExternalFiles/File_Types/Audio.php#L143-L149)

### `eml_video_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File_Types\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Video.php](ExternalFiles/File_Types/Video.php), [line 145](ExternalFiles/File_Types/Video.php#L145-L151)

### `eml_import_ajax_start`

*Run additional tasks just before AJAX-related import of URLs is started.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | List of URLs to import.
`$additional_fields` | `array` | List of additional fields from form (since 3.0.0).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 410](ExternalFiles/Forms.php#L410-L417)

### `eml_import_url_after`

*Run additional tasks for single URL after it has been successfully added as external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.
`$additional_fields` | `array` | List of additional fields from form.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 479](ExternalFiles/Forms.php#L479-L486)

### `eml_import_ajax_end`

*Run additional tasks just before AJAX-related import of URLs is marked as completed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | List of URLs to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 525](ExternalFiles/Forms.php#L525-L531)

### `eml_queue_before_process`

*Run action before queue is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from queue which will be processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Queue.php](ExternalFiles/Queue.php), [line 285](ExternalFiles/Queue.php#L285-L291)

### `eml_queue_after_process`

*Run action after queue is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from queue which has been processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Queue.php](ExternalFiles/Queue.php), [line 309](ExternalFiles/Queue.php#L309-L315)

## Filters

### `eml_current_language`

*Filter the resulting language.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$wp_language` | `string` | The language-name (e.g. "en").

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Languages.php](Plugin/Languages.php), [line 76](Plugin/Languages.php#L76-L83)

### `eml_crypt_methods`

*Filter the available crypt-methods.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$methods` | `array` | List of methods.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/Plugin/Crypt.php](Plugin/Crypt.php), [line 121](Plugin/Crypt.php#L121-L127)

### `eml_schedule_interval`

*Filter the interval for a single schedule.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$interval` | `string` | The interval.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Schedules_Base` | The schedule-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 75](Plugin/Schedules_Base.php#L75-L82)

### `eml_schedule_enabling`

*Filter whether to activate this schedule.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if this object should NOT be enabled.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Schedules_Base` | Actual object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 187](Plugin/Schedules_Base.php#L187-L197)

### `eml_set_template_directory`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$directory` |  | 

Source: [app/Plugin/Templates.php](Plugin/Templates.php), [line 82](Plugin/Templates.php#L82-L82)

### `eml_log_table_filter`

*Filter the list before output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of filter.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Tables/Logs.php](Plugin/Tables/Logs.php), [line 219](Plugin/Tables/Logs.php#L219-L225)

### `eml_queue_table_filter`

*Filter the list before output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of filter.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Tables/Queue.php](Plugin/Tables/Queue.php), [line 273](Plugin/Tables/Queue.php#L273-L279)

### `eml_supported_mime_types`

*Filter the possible mime types this plugin could support. This is the list used for the setting in backend.*

To add files of type "your/mime" with file extension ".yourmime" use this example:

```
add_filter( 'eml_supported_mime_types', function( $list ) {
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
`$mime_types` | `array` | List of supported mime types.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 248](Plugin/Helper.php#L248-L267)

### `eml_get_mime_types`

*Filter the list of possible mime types. This is the list used by the plugin during file-checks
and is not visible or editable in backend.*

To add files of type "your/mime" with file extension ".yourmime" use this example:

```
add_filter( 'eml_get_mime_types', function( $list ) {
 $list[] = 'your/mime';
} );
```

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of mime types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 289](Plugin/Helper.php#L289-L305)

### `eml_help_tabs`

*Filter the list of help tabs with its contents.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of help tabs.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Admin/Help_System.php](Plugin/Admin/Help_System.php), [line 112](Plugin/Admin/Help_System.php#L112-L118)

### `eml_plugin_row_meta`

*Filter the links in row meta of our plugin in plugin list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$row_meta` | `array` | List of links.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/Plugin/Admin/Admin.php](Plugin/Admin/Admin.php), [line 226](Plugin/Admin/Admin.php#L226-L232)

### `eml_schedule_our_events`

*Filter the list of our own events,
e.g. to check if all which are enabled in setting are active.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$our_events` | `array` | List of our own events in WP-cron.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 94](Plugin/Schedules.php#L94-L102)

### `eml_disable_cron_check`

*Disable the additional cron check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if check should be disabled.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 119](Plugin/Schedules.php#L119-L127)

### `eml_schedules`

*Add custom schedule-objects to use.*

This must be objects based on ExternalFilesInMediaLibrary\Plugin\Schedules_Base.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list_of_schedules` | `array` | List of additional schedules.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 229](Plugin/Schedules.php#L229-L238)

### `eml_file_types`

*Filter the list of available file types.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of file type handler.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types.php](ExternalFiles/File_Types.php), [line 72](ExternalFiles/File_Types.php#L72-L79)

### `eml_file_prevent_proxied_url`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$this` |  | 

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 114](ExternalFiles/File.php#L114-L114)

### `eml_file_availability`

*Filter and return the file availability.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->availability` |  | 
`$this` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 235](ExternalFiles/File.php#L235-L241)

### `eml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$url` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 178](ExternalFiles/Files.php#L178-L178)

### `eml_blacklist`

*Filter the given URL against custom blacklists.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Return true if blacklist matches.
`$url` | `string` | The given URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 275](ExternalFiles/Files.php#L275-L284)

### `eml_file_import_user`

*Filter the user_id for a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$user_id` | `int` | The title generated by importer.
`$url` | `string` | The requested external URL.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 357](ExternalFiles/Files.php#L357-L365)

### `eml_file_import_title`

*Filter the title for a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_data['title']` |  | 
`$file_data['url']` |  | 
`$file_data` | `array` | List of file settings detected by importer.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 388](ExternalFiles/Files.php#L388-L397)

### `eml_file_import_attachment`

*Filter the attachment settings*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_array` | `array` | The attachment settings.
`$file_data['url']` |  | 
`$file_data` | `array` | List of file settings detected by importer.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 407](ExternalFiles/Files.php#L407-L416)

### `eml_tcp_protocols`

*Filter the tcp protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$tcp_protocols` | `array` | List of tcp protocol of this object (e.g. 'http').
`$this` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 82](ExternalFiles/Protocol_Base.php#L82-L88)

### `eml_duplicate_check`

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 227](ExternalFiles/Protocol_Base.php#L227-L236)

### `eml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array` | List of files.
`$this` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 208](ExternalFiles/Protocols/Sftp.php#L208-L215)

### `eml_external_file_infos`

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 267](ExternalFiles/Protocols/Sftp.php#L267-L276)

### `eml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array` | List of files.
`$this` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 149](ExternalFiles/Protocols/File.php#L149-L156)

### `eml_file_check_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$file_path` |  | 

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 188](ExternalFiles/Protocols/File.php#L188-L188)

### `eml_external_file_infos`

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 216](ExternalFiles/Protocols/File.php#L216-L225)

### `eml_check_url`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 65](ExternalFiles/Protocols/Http.php#L65-L75)

### `eml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 114](ExternalFiles/Protocols/Http.php#L114-L114)

### `eml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 131](ExternalFiles/Protocols/Http.php#L131-L131)

### `eml_check_url_availability`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 140](ExternalFiles/Protocols/Http.php#L140-L150)

### `eml_filter_url_response`

*Filter the URL with custom import methods.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 215](ExternalFiles/Protocols/Http.php#L215-L222)

### `eml_http_directory_regex`

*Filter the content with regex via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 
`$content` | `string` | The content to parse.
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 270](ExternalFiles/Protocols/Http.php#L270-L280)

### `eml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array` | List of files.
`$this` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\HTTP` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 423](ExternalFiles/Protocols/Http.php#L423-L430)

### `eml_external_file_infos`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 508](ExternalFiles/Protocols/Http.php#L508-L519)

### `eml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 606](ExternalFiles/Protocols/Http.php#L606-L606)

### `eml_http_save_local`

*Filter if a http-file should be saved local or not.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if file should be saved local.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 620](ExternalFiles/Protocols/Http.php#L620-L627)

### `eml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 658](ExternalFiles/Protocols/Http.php#L658-L658)

### `eml_http_save_local`

*Filter if a http-file should be saved local or not.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if file should be saved local.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 667](ExternalFiles/Protocols/Http.php#L667-L674)

### `eml_http_header_args`

*Filter the resulting header.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$args` | `array` | List of headers.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 698](ExternalFiles/Protocols/Http.php#L698-L704)

### `eml_http_states`

*Filter the list of allowed http states.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of http states.
`$url` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 730](ExternalFiles/Protocols/Http.php#L730-L736)

### `eml_locale_file_check`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 759](ExternalFiles/Protocols/Http.php#L759-L768)

### `eml_check_url`

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 92](ExternalFiles/Protocols/Ftp.php#L92-L101)

### `eml_external_files_infos`

*Filter list of files during this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array` | List of files.
`$this` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The import object.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 286](ExternalFiles/Protocols/Ftp.php#L286-L293)

### `eml_external_file_infos`

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 338](ExternalFiles/Protocols/Ftp.php#L338-L347)

### `eml_protocols`

*Filter the list of available protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of protocol handler.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols.php](ExternalFiles/Protocols.php), [line 65](ExternalFiles/Protocols.php#L65-L71)

### `eml_import_info_timeout`

*Filter the timeout for AJAX-info-request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$info_timeout` | `int` | The timeout in ms (default 200ms).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 139](ExternalFiles/Forms.php#L139-L145)

### `eml_add_dialog`

*Filter the add-dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array` | The dialog configuration.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 243](ExternalFiles/Forms.php#L243-L249)

### `eml_import_add_to_queue`

*Get the queue setting for the import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Set to true to import the files via queue.
`$additional_fields` |  | 

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 397](ExternalFiles/Forms.php#L397-L405)

### `eml_import_urls`

*Filter the URLs for use for this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | The list of URLs to add.
`$additional_fields` | `array` | List of additional fields from form (since 3.0.0).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 424](ExternalFiles/Forms.php#L424-L431)

### `eml_import_url_before`

*Filter single URL before it will be added as external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.
`$additional_fields` | `array` | List of additional fields from form.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 457](ExternalFiles/Forms.php#L457-L464)

### `eml_import_urls_errors`

*Filter the errors during an AJAX-request to add URLs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$errors` | `array` | List of errors.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 494](ExternalFiles/Forms.php#L494-L500)

### `eml_import_fields`

*Filter the fields for the dialog. Additional fields must be marked with "eml-use-for-import" as class.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `array` | List of fields.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 793](ExternalFiles/Forms.php#L793-L799)

### `eml_import_fields`

*Filter the fields for the dialog. Additional fields must be marked with "eml-use-for-import" as class.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `array` | List of fields.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 808](ExternalFiles/Forms.php#L808-L815)

### `eml_file_type_compatibility_result`

*Filter the result of file type compatibility check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | The result (true or false).
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.
`$mime_type` | `string` | The used mime type (added in 3.0.0).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 88](ExternalFiles/File_Types_Base.php#L88-L97)

### `eml_file_type_supported_mime_types`

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

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 125](ExternalFiles/File_Types_Base.php#L125-L132)

### `eml_queue_urls`

*Filter the list of URLs from queue before they are processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from queue.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Queue.php](ExternalFiles/Queue.php), [line 277](ExternalFiles/Queue.php#L277-L283)

### `eml_proxy_slug`

*Filter the slug for the proxy-URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$slug` | `string` | The slug.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 224](ExternalFiles/Proxy.php#L224-L231)

### `eml_proxy_path`

*Filter the cache directory.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$path` | `string` | The absolute path to the directory.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 247](ExternalFiles/Proxy.php#L247-L253)

### `eml_services_support`

*Filter the list of third party support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of third party support.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 110](Services/Services.php#L110-L116)

### `eml_google_drive_client_id`

*Filter the Google OAuth Client ID for the app used to connect Google Drive.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$client_id` | `string` | The client ID.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 426](Services/GoogleDrive.php#L426-L432)

### `eml_google_drive_real_redirect_uri`

*Filter the real redirect URI to connect the Google OAuth Client.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$real_redirect_uri` | `string` | The real redirect URI.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 444](Services/GoogleDrive.php#L444-L450)

### `eml_google_drive_state`

*Filter the token to connect the Google OAuth Client.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$state` | `string` | The token.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 467](Services/GoogleDrive.php#L467-L473)

### `eml_google_drive_redirect_uri`

*Filter the redirect URI to connect the Google OAuth Client.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$redirect_uri` | `string` | The redirect URI.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 487](Services/GoogleDrive.php#L487-L493)

### `eml_google_drive_connector_params`

*Filter the params for Google OAuth request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$params` | `array` | The list of params.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 518](Services/GoogleDrive.php#L518-L524)

### `eml_google_drive_query_params`

*Filter the query to get files from Google Drive.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$query` | `array` | The list of params.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 694](Services/GoogleDrive.php#L694-L700)

### `eml_google_drive_files`

*Filter the list of files we got from Google Drive.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `array` | List of files.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 727](Services/GoogleDrive.php#L727-L733)

### `eml_google_drive_refresh_uri`

*Filter the redirect URI to connect the Google OAuth Client.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refresh_uri` | `string` | The redirect URI.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 920](Services/GoogleDrive.php#L920-L926)

### `eml_youtube_api_url`

*Filter the YouTube API URL to use.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$api_url` | `string` | The API URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 686](Services/Youtube.php#L686-L692)

### `eml_third_party_support`

*Filter the list of third party support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of third party support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 107](ThirdParty/ThirdPartySupport.php#L107-L113)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `eml_cli_arguments`

*Run additional tasks from extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$arguments` | `array` | List of CLI arguments.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Cli.php](Plugin/Cli.php), [line 61](Plugin/Cli.php#L61-L67)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 271](ExternalFiles/Files.php#L271-L277)

### `eml_show_file_info`

*Add additional infos about this file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 599](ExternalFiles/Files.php#L599-L605)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 364](ExternalFiles/Extensions/Queue.php#L364-L370)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 388](ExternalFiles/Extensions/Queue.php#L388-L394)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 117](ExternalFiles/Protocols/Sftp.php#L117-L124)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 134](ExternalFiles/Protocols/Sftp.php#L134-L142)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 181](ExternalFiles/Protocols/Sftp.php#L181-L189)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 54](ExternalFiles/Protocols/File.php#L54-L61)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 90](ExternalFiles/Protocols/File.php#L90-L97)

### `eml_file_directory_import_file_before_to_list`

*Run action just before the file is added to the list via file-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.
`$file_list` | `array<int,mixed>` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 111](ExternalFiles/Protocols/File.php#L111-L119)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 249](ExternalFiles/Protocols/Http.php#L249-L256)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 307](ExternalFiles/Protocols/Http.php#L307-L315)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 359](ExternalFiles/Protocols/Http.php#L359-L366)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 379](ExternalFiles/Protocols/Http.php#L379-L387)

### `eml_ftp_directory_import_start`

*Run action on beginning of presumed directory import via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 173](ExternalFiles/Protocols/Ftp.php#L173-L180)

### `eml_ftp_directory_import_files`

*Run action if we have files to check via FTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 
`$file_list` | `array<string,mixed>` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 192](ExternalFiles/Protocols/Ftp.php#L192-L200)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 225](ExternalFiles/Protocols/Ftp.php#L225-L232)

### `eml_ftp_directory_import_file_before_to_list`

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 248](ExternalFiles/Protocols/Ftp.php#L248-L256)

### `eml_table_column_content`

*Run additional tasks for show more infos here.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attachment_id` |  | 

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 211](ExternalFiles/Tables.php#L211-L214)

### `eml_image_meta_data`

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

Source: [app/ExternalFiles/File_Types/Image.php](ExternalFiles/File_Types/Image.php), [line 140](ExternalFiles/File_Types/Image.php#L140-L147)

### `eml_video_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Audio.php](ExternalFiles/File_Types/Audio.php), [line 142](ExternalFiles/File_Types/Audio.php#L142-L148)

### `eml_video_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Video.php](ExternalFiles/File_Types/Video.php), [line 144](ExternalFiles/File_Types/Video.php#L144-L150)

### `eml_import_ajax_start`

*Run additional tasks just before AJAX-related import of URLs is started.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | List of URLs to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 407](ExternalFiles/Forms.php#L407-L413)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 504](ExternalFiles/Forms.php#L504-L510)

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

Source: [app/Plugin/Languages.php](Plugin/Languages.php), [line 77](Plugin/Languages.php#L77-L84)

### `eml_crypt_methods`

*Filter the available crypt-methods.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$methods` | `string[]` | List of methods.

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
`$instance` | `\ExternalFilesInMediaLibrary\Plugin\Schedules_Base` | The schedule-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 76](Plugin/Schedules_Base.php#L76-L83)

### `eml_schedule_enabling`

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

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 189](Plugin/Schedules_Base.php#L189-L199)

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

Source: [app/Plugin/Tables/Logs.php](Plugin/Tables/Logs.php), [line 224](Plugin/Tables/Logs.php#L224-L230)

### `eml_queue_table_filter`

*Filter the list before output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,string>` | List of filter.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Tables/Queue.php](Plugin/Tables/Queue.php), [line 290](Plugin/Tables/Queue.php#L290-L296)

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
`$mime_types` | `array<string,array<string,string>>` | List of supported mime types.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 264](Plugin/Helper.php#L264-L283)

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
`$list` | `string[]` | List of mime types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 305](Plugin/Helper.php#L305-L321)

### `eml_help_tabs`

*Filter the list of help tabs with its contents.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,mixed>` | List of help tabs.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Admin/Help_System.php](Plugin/Admin/Help_System.php), [line 113](Plugin/Admin/Help_System.php#L113-L119)

### `eml_directory_translations`

*Filter the translations to use for directory listings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$translations` | `array<string,mixed>` | List of translations.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 380](Plugin/Admin/Directory_Listing.php#L380-L386)

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

Source: [app/Plugin/Admin/Admin.php](Plugin/Admin/Admin.php), [line 232](Plugin/Admin/Admin.php#L232-L238)

### `eml_schedule_our_events`

*Filter the list of our own events,
e.g. to check if all which are enabled in setting are active.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$our_events` | `array<string,array<string,mixed>>` | List of our own events in WP-cron.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 95](Plugin/Schedules.php#L95-L103)

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

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 120](Plugin/Schedules.php#L120-L128)

### `eml_schedules`

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

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 230](Plugin/Schedules.php#L230-L239)

### `eml_file_types`

*Filter the list of available file types.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `string[]` | List of file type handler.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types.php](ExternalFiles/File_Types.php), [line 262](ExternalFiles/File_Types.php#L262-L269)

### `eml_dialog_settings`

*Filter the given settings for the import dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,mixed>` | The requested settings.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 94](ExternalFiles/ImportDialog.php#L94-L101)

### `eml_add_dialog`

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 129](ExternalFiles/ImportDialog.php#L129-L136)

### `eml_file_prevent_proxied_url`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$instance` |  | 

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 123](ExternalFiles/File.php#L123-L123)

### `eml_file_availability`

*Filter and return the file availability.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->availability` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`1.0.0` | Available since 1.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 242](ExternalFiles/File.php#L242-L250)

### `eml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$url` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 173](ExternalFiles/Files.php#L173-L173)

### `eml_import_options`

*Get the options used for import of this URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array` | List of options.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 302](ExternalFiles/Extensions/Queue.php#L302-L309)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 356](ExternalFiles/Extensions/Queue.php#L356-L362)

### `eml_tcp_protocols`

*Filter the tcp protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$tcp_protocols` | `array<string,int>` | List of tcp protocol of this object (e.g. 'http').
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base` | The actual object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 86](ExternalFiles/Protocol_Base.php#L86-L93)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 232](ExternalFiles/Protocol_Base.php#L232-L241)

### `eml_mime_type_for_multiple_files`

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 399](ExternalFiles/Protocol_Base.php#L399-L408)

### `eml_external_files_infos`

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 211](ExternalFiles/Protocols/Sftp.php#L211-L218)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 292](ExternalFiles/Protocols/Sftp.php#L292-L301)

### `eml_external_files_infos`

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 147](ExternalFiles/Protocols/File.php#L147-L154)

### `eml_file_check_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$file_path` |  | 

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 189](ExternalFiles/Protocols/File.php#L189-L189)

### `eml_external_file_infos`

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 219](ExternalFiles/Protocols/File.php#L219-L228)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 74](ExternalFiles/Protocols/Http.php#L74-L84)

### `eml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 123](ExternalFiles/Protocols/Http.php#L123-L123)

### `eml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 139](ExternalFiles/Protocols/Http.php#L139-L139)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 146](ExternalFiles/Protocols/Http.php#L146-L156)

### `eml_filter_url_response`

*Filter the URL with custom import methods.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 
`$this->get_url()` |  | 
`$instance` | `\ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http` | The actual protocol object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 222](ExternalFiles/Protocols/Http.php#L222-L230)

### `eml_http_directory_regex`

*Filter the content with regex via HTTP-protocol.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 
`$content` | `string` | The content to parse.
`$url` | `string` | The used URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 284](ExternalFiles/Protocols/Http.php#L284-L295)

### `eml_external_files_infos`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 421](ExternalFiles/Protocols/Http.php#L421-L428)

### `eml_external_file_infos`

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 504](ExternalFiles/Protocols/Http.php#L504-L513)

### `eml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 600](ExternalFiles/Protocols/Http.php#L600-L600)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 614](ExternalFiles/Protocols/Http.php#L614-L621)

### `eml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 652](ExternalFiles/Protocols/Http.php#L652-L652)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 661](ExternalFiles/Protocols/Http.php#L661-L669)

### `eml_http_header_args`

*Filter the resulting header.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$args` | `array<string,mixed>` | List of headers.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 692](ExternalFiles/Protocols/Http.php#L692-L698)

### `eml_http_states`

*Filter the list of allowed http states.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `int[]` | List of http states.
`$url` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 724](ExternalFiles/Protocols/Http.php#L724-L731)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 754](ExternalFiles/Protocols/Http.php#L754-L763)

### `eml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 790](ExternalFiles/Protocols/Http.php#L790-L790)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 101](ExternalFiles/Protocols/Ftp.php#L101-L110)

### `eml_external_files_infos`

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 287](ExternalFiles/Protocols/Ftp.php#L287-L294)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 360](ExternalFiles/Protocols/Ftp.php#L360-L369)

### `eml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 518](ExternalFiles/Protocols/Ftp.php#L518-L518)

### `eml_protocols`

*Filter the list of available protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `string[]` | List of protocol handler.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols.php](ExternalFiles/Protocols.php), [line 65](ExternalFiles/Protocols.php#L65-L71)

### `eml_extensions`

*Filter the list of available file handling extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of extensions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions.php](ExternalFiles/Extensions.php), [line 133](ExternalFiles/Extensions.php#L133-L139)

### `eml_import_info_timeout`

*Filter the timeout for the AJAX-info-request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$info_timeout` | `int` | The timeout in ms (default 200ms).

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 146](ExternalFiles/Forms.php#L146-L152)

### `eml_import_add_to_queue`

*Mark this hook as deprecated as we do not use it anymore.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array(false, array())` |  | 
`'5.0.0'` |  | 

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 399](ExternalFiles/Forms.php#L399-L402)

### `eml_import_urls`

*Filter the URLs for use for this import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_array` | `array` | The list of URLs to add.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 418](ExternalFiles/Forms.php#L418-L424)

### `eml_import_url`

*Filter single URL before it will be added as external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The URL.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 454](ExternalFiles/Forms.php#L454-L460)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 474](ExternalFiles/Forms.php#L474-L480)

### `eml_dialog_after_adding`

*Filter the dialog after adding files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog configuration.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 582](ExternalFiles/Forms.php#L582-L588)

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
`$list` | `string[]` | List of third party support.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/Services.php](Services/Services.php), [line 189](Services/Services.php#L189-L195)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 473](Services/GoogleDrive.php#L473-L479)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 491](Services/GoogleDrive.php#L491-L497)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 522](Services/GoogleDrive.php#L522-L528)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 542](Services/GoogleDrive.php#L542-L548)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 573](Services/GoogleDrive.php#L573-L579)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 765](Services/GoogleDrive.php#L765-L771)

### `eml_google_drive_files`

*Filter the list of files we got from Google Drive.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$files` | `\Google\Service\Drive\DriveFile[]` | List of files.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 803](Services/GoogleDrive.php#L803-L809)

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

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 1012](Services/GoogleDrive.php#L1012-L1018)

### `eml_external_file_infos`

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

Source: [app/Services/Rest.php](Services/Rest.php), [line 612](Services/Rest.php#L612-L621)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 717](Services/Youtube.php#L717-L723)

### `eml_youtube_channel_url`

*Filter the YouTube channel URL to use.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$channel_url` | `string` | The API URL.

**Changelog**

Version | Description
------- | -----------
`4.0.0` | Available since 4.0.0.

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 734](Services/Youtube.php#L734-L740)

### `eml_third_party_support`

*Filter the list of third party support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `string[]` | List of third party support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 141](ThirdParty/ThirdPartySupport.php#L141-L147)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


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

Source: [app/Plugin/Cli.php](Plugin/Cli.php), [line 75](Plugin/Cli.php#L75-L81)

### `efml_switch_to_local_before`

*Run tasks before we switch a file to local.*


**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 710](ExternalFiles/File.php#L710-L715)

### `efml_switch_to_local_after`

*Run tasks after we switch a file to local.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_id()` |  | 

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 859](ExternalFiles/File.php#L859-L865)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 286](ExternalFiles/Files.php#L286-L292)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 566](ExternalFiles/Files.php#L566-L572)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 392](ExternalFiles/Extensions/Queue.php#L392-L398)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 186](ExternalFiles/Protocols/Sftp.php#L186-L193)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 212](ExternalFiles/Protocols/Sftp.php#L212-L220)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 284](ExternalFiles/Protocols/Http.php#L284-L291)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 348](ExternalFiles/Protocols/Http.php#L348-L356)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 403](ExternalFiles/Protocols/Http.php#L403-L410)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 426](ExternalFiles/Protocols/Http.php#L426-L434)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 187](ExternalFiles/Protocols/Ftp.php#L187-L194)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 209](ExternalFiles/Protocols/Ftp.php#L209-L217)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 245](ExternalFiles/Protocols/Ftp.php#L245-L252)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 271](ExternalFiles/Protocols/Ftp.php#L271-L279)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 183](ExternalFiles/Tables.php#L183-L189)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 367](ExternalFiles/Tables.php#L367-L373)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 615](ExternalFiles/Synchronization.php#L615-L623)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 1137](ExternalFiles/Synchronization.php#L1137-L1143)

### `efml_sync_save_config`

*Run additional tasks during saving a new sync configuration.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `array` | List of fields.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 1523](ExternalFiles/Synchronization.php#L1523-L1529)

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

Source: [app/ExternalFiles/File_Types/Image.php](ExternalFiles/File_Types/Image.php), [line 152](ExternalFiles/File_Types/Image.php#L152-L159)

### `efml_audio_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Audio.php](ExternalFiles/File_Types/Audio.php), [line 163](ExternalFiles/File_Types/Audio.php#L163-L169)

### `efml_video_meta_data`

*Run additional tasks to add custom meta data on external hostet files.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external files object.

**Changelog**

Version | Description
------- | -----------
`3.1.0` | Available since 3.1.0.

Source: [app/ExternalFiles/File_Types/Video.php](ExternalFiles/File_Types/Video.php), [line 156](ExternalFiles/File_Types/Video.php#L156-L162)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 460](ExternalFiles/Forms.php#L460-L466)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 584](ExternalFiles/Forms.php#L584-L590)

### `efml_proxy_before`

*Run additional tasks before proxy tries to load a cached external file.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` |  | 

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 161](ExternalFiles/Proxy.php#L161-L164)

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

Source: [app/Services/DropBox/Protocol.php](Services/DropBox/Protocol.php), [line 129](Services/DropBox/Protocol.php#L129-L137)

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

Source: [app/Services/DropBox/Protocol.php](Services/DropBox/Protocol.php), [line 150](Services/DropBox/Protocol.php#L150-L157)

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

Source: [app/Plugin/Schedules_Base.php](Plugin/Schedules_Base.php), [line 195](Plugin/Schedules_Base.php#L195-L203)

### `efml_set_template_directory`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$directory` |  | 

Source: [app/Plugin/Templates.php](Plugin/Templates.php), [line 85](Plugin/Templates.php#L85-L85)

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

Source: [app/Plugin/Tables/Logs.php](Plugin/Tables/Logs.php), [line 228](Plugin/Tables/Logs.php#L228-L234)

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

Source: [app/Plugin/Intro.php](Plugin/Intro.php), [line 73](Plugin/Intro.php#L73-L80)

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

Source: [app/Plugin/Intro.php](Plugin/Intro.php), [line 369](Plugin/Intro.php#L369-L375)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 214](Plugin/Helper.php#L214-L233)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 258](Plugin/Helper.php#L258-L275)

### `efml_own_cron_schedules`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`(string) $name` |  | 
`$interval` |  | 

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 412](Plugin/Helper.php#L412-L412)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 796](Plugin/Helper.php#L796-L804)

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

Source: [app/Plugin/Admin/Plugins.php](Plugin/Admin/Plugins.php), [line 340](Plugin/Admin/Plugins.php#L340-L346)

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

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 417](Plugin/Admin/Directory_Listing.php#L417-L423)

### `efml_service_plugins`

*Filter the list of available service plugins.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,\ExternalFilesInMediaLibrary\Services\Service_Plugin_Base>` | The list.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 897](Plugin/Admin/Directory_Listing.php#L897-L903)

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

Source: [app/Plugin/Admin/Admin.php](Plugin/Admin/Admin.php), [line 250](Plugin/Admin/Admin.php#L250-L256)

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

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 100](Plugin/Schedules.php#L100-L108)

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

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 126](Plugin/Schedules.php#L126-L132)

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

Source: [app/Plugin/Schedules.php](Plugin/Schedules.php), [line 255](Plugin/Schedules.php#L255-L264)

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

Source: [app/ExternalFiles/File_Types.php](ExternalFiles/File_Types.php), [line 180](ExternalFiles/File_Types.php#L180-L187)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 101](ExternalFiles/ImportDialog.php#L101-L108)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 148](ExternalFiles/ImportDialog.php#L148-L155)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 350](ExternalFiles/ImportDialog.php#L350-L356)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 419](ExternalFiles/ImportDialog.php#L419-L425)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 667](ExternalFiles/ImportDialog.php#L667-L674)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 679](ExternalFiles/ImportDialog.php#L679-L686)

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

### `efml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$url` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 185](ExternalFiles/Files.php#L185-L185)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 225](ExternalFiles/Files.php#L225-L231)

### `efml_files_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 1222](ExternalFiles/Files.php#L1222-L1222)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 251](ExternalFiles/Protocol_Base.php#L251-L258)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 420](ExternalFiles/Protocol_Base.php#L420-L429)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 246](ExternalFiles/Protocols/Sftp.php#L246-L253)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 353](ExternalFiles/Protocols/Sftp.php#L353-L362)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 332](ExternalFiles/Protocols/File.php#L332-L341)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 415](ExternalFiles/Protocols/File.php#L415-L415)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 83](ExternalFiles/Protocols/Http.php#L83-L91)

### `efml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 130](ExternalFiles/Protocols/Http.php#L130-L130)

### `efml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 149](ExternalFiles/Protocols/Http.php#L149-L149)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 161](ExternalFiles/Protocols/Http.php#L161-L169)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 200](ExternalFiles/Protocols/Http.php#L200-L208)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 254](ExternalFiles/Protocols/Http.php#L254-L262)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 322](ExternalFiles/Protocols/Http.php#L322-L333)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 472](ExternalFiles/Protocols/Http.php#L472-L479)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 558](ExternalFiles/Protocols/Http.php#L558-L567)

### `efml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 654](ExternalFiles/Protocols/Http.php#L654-L654)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 672](ExternalFiles/Protocols/Http.php#L672-L679)

### `efml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 710](ExternalFiles/Protocols/Http.php#L710-L710)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 723](ExternalFiles/Protocols/Http.php#L723-L731)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 763](ExternalFiles/Protocols/Http.php#L763-L770)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 799](ExternalFiles/Protocols/Http.php#L799-L806)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 831](ExternalFiles/Protocols/Http.php#L831-L838)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 870](ExternalFiles/Protocols/Http.php#L870-L870)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 112](ExternalFiles/Protocols/Ftp.php#L112-L120)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 314](ExternalFiles/Protocols/Ftp.php#L314-L321)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 391](ExternalFiles/Protocols/Ftp.php#L391-L400)

### `efml_save_temp_file`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 552](ExternalFiles/Protocols/Ftp.php#L552-L552)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 105](ExternalFiles/Tables.php#L105-L111)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 340](ExternalFiles/Tables.php#L340-L347)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 352](ExternalFiles/Tables.php#L352-L359)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 233](ExternalFiles/Synchronization.php#L233-L239)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 515](ExternalFiles/Synchronization.php#L515-L522)

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

Source: [app/ExternalFiles/Extensions.php](ExternalFiles/Extensions.php), [line 138](ExternalFiles/Extensions.php#L138-L144)

### `efml_extensions_default`

*Filter the list of default extensions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | List of names of the default extensions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/ExternalFiles/Extensions.php](ExternalFiles/Extensions.php), [line 160](ExternalFiles/Extensions.php#L160-L166)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 132](ExternalFiles/Forms.php#L132-L138)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 474](ExternalFiles/Forms.php#L474-L481)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 483](ExternalFiles/Forms.php#L483-L491)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 522](ExternalFiles/Forms.php#L522-L528)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 550](ExternalFiles/Forms.php#L550-L556)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 658](ExternalFiles/Forms.php#L658-L664)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 810](ExternalFiles/Forms.php#L810-L816)

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

Source: [app/ExternalFiles/Forms.php](ExternalFiles/Forms.php), [line 830](ExternalFiles/Forms.php#L830-L836)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 242](ExternalFiles/Proxy.php#L242-L249)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 268](ExternalFiles/Proxy.php#L268-L274)

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

Source: [app/Services/Service_Base.php](Services/Service_Base.php), [line 164](Services/Service_Base.php#L164-L171)

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

Source: [app/Services/Zip.php](Services/Zip.php), [line 860](Services/Zip.php#L860-L866)

### `efml_zip_max_size_limit`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$limit` |  | 
`$settings` |  | 

Source: [app/Services/Zip.php](Services/Zip.php), [line 1045](Services/Zip.php#L1045-L1045)

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

Source: [app/Services/DropBox.php](Services/DropBox.php), [line 1318](Services/DropBox.php#L1318-L1324)

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

Source: [app/Services/Services.php](Services/Services.php), [line 157](Services/Services.php#L157-L163)

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

Source: [app/Services/Services.php](Services/Services.php), [line 398](Services/Services.php#L398-L405)

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

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 333](Services/Ftp.php#L333-L345)

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

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 714](Services/Ftp.php#L714-L720)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 854](Services/Youtube.php#L854-L860)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 874](Services/Youtube.php#L874-L880)

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

Source: [app/Services/Youtube.php](Services/Youtube.php), [line 1172](Services/Youtube.php#L1172-L1178)

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

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 152](ThirdParty/ThirdPartySupport.php#L152-L158)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


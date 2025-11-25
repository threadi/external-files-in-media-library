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

Source: [app/Plugin/Cli.php](Plugin/Cli.php), [line 78](Plugin/Cli.php#L78-L84)

### `efml_file_delete`

*Run additional tasks for URL deletion.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The object which has been deleted.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 293](ExternalFiles/Files.php#L293-L299)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 573](ExternalFiles/Files.php#L573-L579)

### `efml_queue_before_process`

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

### `efml_queue_after_process`

*Run action after queue is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from queue which has been processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 391](ExternalFiles/Extensions/Queue.php#L391-L397)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 177](ExternalFiles/Tables.php#L177-L183)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 546](ExternalFiles/Synchronization.php#L546-L554)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 1438](ExternalFiles/Synchronization.php#L1438-L1444)

## Filters

### `efml_current_language`

*Filter the resulting language.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$wp_language` | `string` | The language-name (e.g. "en").

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

Source: [app/Plugin/Tables/Logs.php](Plugin/Tables/Logs.php), [line 227](Plugin/Tables/Logs.php#L227-L233)

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

Source: [app/Plugin/Tables/Queue.php](Plugin/Tables/Queue.php), [line 293](Plugin/Tables/Queue.php#L293-L299)

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

Source: [app/Plugin/Intro.php](Plugin/Intro.php), [line 344](Plugin/Intro.php#L344-L350)

### `efml_supported_mime_types`

*Filter the possible mime types this plugin could support. This is the list used for the setting in backend.*

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 197](Plugin/Helper.php#L197-L216)

### `efml_get_mime_types`

*Filter the list of possible mime types. This is the list used by the plugin during file-checks
and is not visible or editable in backend.*

To add files of type "your/mime" with file extension ".yourmime" use this example:

```
add_filter( 'efml_get_mime_types', function( $list ) {
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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 241](Plugin/Helper.php#L241-L257)

### `efml_own_cron_schedules`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`(string) $name` |  | 
`$interval` |  | 

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 394](Plugin/Helper.php#L394-L394)

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

Source: [app/Plugin/Admin/Directory_Listing.php](Plugin/Admin/Directory_Listing.php), [line 445](Plugin/Admin/Directory_Listing.php#L445-L451)

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

Source: [app/Plugin/Admin/Admin.php](Plugin/Admin/Admin.php), [line 246](Plugin/Admin/Admin.php#L246-L252)

### `efml_schedule_our_events`

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 351](ExternalFiles/ImportDialog.php#L351-L357)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 420](ExternalFiles/ImportDialog.php#L420-L426)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 671](ExternalFiles/ImportDialog.php#L671-L678)

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

Source: [app/ExternalFiles/ImportDialog.php](ExternalFiles/ImportDialog.php), [line 683](ExternalFiles/ImportDialog.php#L683-L690)

### `efml_file_prevent_proxied_url`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$instance` |  | 

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 127](ExternalFiles/File.php#L127-L127)

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

Source: [app/ExternalFiles/File.php](ExternalFiles/File.php), [line 254](ExternalFiles/File.php#L254-L262)

### `efml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$url` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 184](ExternalFiles/Files.php#L184-L184)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 227](ExternalFiles/Files.php#L227-L233)

### `efml_files_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 1212](ExternalFiles/Files.php#L1212-L1212)

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

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 297](ExternalFiles/Extensions/Queue.php#L297-L304)

### `efml_queue_urls`

*Filter the list of URLs from queue before they are processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$urls_to_import` | `array` | List of URLs to import from queue.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Extensions/Queue.php](ExternalFiles/Extensions/Queue.php), [line 353](ExternalFiles/Extensions/Queue.php#L353-L359)

### `efml_tcp_protocols`

*Filter the protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$tcp_protocols` | `array<string,int>` | List of protocols of this object (e.g. 'http' or 'ftp').
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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 350](ExternalFiles/Protocols/Sftp.php#L350-L359)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 222](ExternalFiles/Protocols/File.php#L222-L229)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 317](ExternalFiles/Protocols/File.php#L317-L326)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 439](ExternalFiles/Protocols/Http.php#L439-L446)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 525](ExternalFiles/Protocols/Http.php#L525-L534)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 783](ExternalFiles/Protocols/Http.php#L783-L792)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 301](ExternalFiles/Protocols/Ftp.php#L301-L308)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 378](ExternalFiles/Protocols/Ftp.php#L378-L387)

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

Source: [app/ExternalFiles/Tables.php](ExternalFiles/Tables.php), [line 104](ExternalFiles/Tables.php#L104-L110)

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

Source: [app/ExternalFiles/Synchronization.php](ExternalFiles/Synchronization.php), [line 449](ExternalFiles/Synchronization.php#L449-L456)

### `efml_service_webdav_path`

*Filter the WebDAV path.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$path` | `string` | The path to use after the given domain.
`$fields` | `array` | The login to use.
`$domain` | `string` | The domain to use.
`$directory` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/WebDav/Protocol.php](Services/WebDav/Protocol.php), [line 127](Services/WebDav/Protocol.php#L127-L137)

### `efml_service_webdav_settings`

*Filter the WebDAV settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,string>` | The settings to use.
`$domain` | `string` | The domain to use.
`$directory` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/WebDav/Protocol.php](Services/WebDav/Protocol.php), [line 146](Services/WebDav/Protocol.php#L146-L155)

### `efml_service_googledrive_hide_file`

*Filter whether given GoogleDrive file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$file_obj` | `\Google\Service\Drive\DriveFile` | The object with the file data.
`$directory` | `string` | The requested directory.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/GoogleDrive.php](Services/GoogleDrive.php), [line 784](Services/GoogleDrive.php#L784-L795)

### `efml_service_googlecloudstorage_hide_file`

*Filter whether given Google Cloud Storage file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$file_data` | `array<string,mixed>` | The object with the file data.
`$directory` | `string` | The requested directory.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/GoogleCloudStorage.php](Services/GoogleCloudStorage.php), [line 428](Services/GoogleCloudStorage.php#L428-L439)

### `efml_service_googlecloudstorage_hide_file`

*Filter whether given Google Cloud Storage file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$file_data` | `array<string,mixed>` | The object with the file data.
`$directory` | `string` | The requested directory.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/GoogleCloudStorage/Protocol.php](Services/GoogleCloudStorage/Protocol.php), [line 331](Services/GoogleCloudStorage/Protocol.php#L331-L342)

### `efml_service_webdav_path`

*Filter the WebDAV path.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$path` | `string` | The path to use after the given domain.
`$fields` | `array` | The fields to use.
`$domain` | `string` | The domain to use.
`$directory` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/WebDav.php](Services/WebDav.php), [line 326](Services/WebDav.php#L326-L336)

### `efml_service_webdav_settings`

*Filter the WebDAV settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array<string,string>` | The settings to use.
`$domain` | `string` | The domain to use.
`$directory` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/WebDav.php](Services/WebDav.php), [line 338](Services/WebDav.php#L338-L347)

### `efml_service_webdav_hide_file`

*Filter whether given WebDAV file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$settings` |  | 
`$file_name` | `string` | The requested file.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/WebDav.php](Services/WebDav.php), [line 388](Services/WebDav.php#L388-L399)

### `efml_service_webdav_client`

*Filter the WebDAV client connection object.*

E.g. to add proxy or other additional settings to reach the WebDAV.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$client` | `\Sabre\DAV\Client` | The WebDAV client object.
`$domain` | `string` | The domain to use.
`$directory` | `string` | The requested URL.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/WebDav.php](Services/WebDav.php), [line 653](Services/WebDav.php#L653-L664)

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

Source: [app/Services/Ftp.php](Services/Ftp.php), [line 338](Services/Ftp.php#L338-L350)

### `efml_service_s3_hide_file`

*Filter whether given AWS S3 file should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True if it should be hidden.
`$file` | `array<string,mixed>` | The array with the file data.
`$directory` | `string` | The requested directory.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/S3.php](Services/S3.php), [line 192](Services/S3.php#L192-L203)

### `efml_service_s3_regions`

*Filter the resulting list of AWS S3 regions.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,string>` | List of regions.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/S3.php](Services/S3.php), [line 665](Services/S3.php#L665-L671)

### `efml_service_s3_default_region`

*Filter the default AWS S3 region.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default_region` | `string` | The default region.
`$language` | `string` | The actual language.

**Changelog**

Version | Description
------- | -----------
`5.0.0` | Available since 5.0.0.

Source: [app/Services/S3.php](Services/S3.php), [line 712](Services/S3.php#L712-L719)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


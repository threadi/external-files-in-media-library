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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 363](ExternalFiles/Files.php#L363-L366)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 376](ExternalFiles/Files.php#L376-L382)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 426](ExternalFiles/Files.php#L426-L433)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 547](ExternalFiles/Files.php#L547-L554)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 105](ExternalFiles/Protocols/Sftp.php#L105-L112)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 129](ExternalFiles/Protocols/Sftp.php#L129-L137)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 177](ExternalFiles/Protocols/Sftp.php#L177-L185)

### `eml_file_directory_import_start`

*Run action on beginning of presumed directory import.*

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

*Run action just before the file check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 81](ExternalFiles/Protocols/File.php#L81-L88)

### `eml_file_directory_import_file_before_to_list`

*Run action just before the file is added to the list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_path` | `string` | The filepath to import.
`$file_list` | `array` | List of files.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 102](ExternalFiles/Protocols/File.php#L102-L110)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 225](ExternalFiles/Protocols/Http.php#L225-L232)

### `eml_http_directory_import_files`

*Run action if we have files to check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 
`$matches[1]` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 291](ExternalFiles/Protocols/Http.php#L291-L299)

### `eml_http_directory_import_file_check`

*Run action just before the file check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 345](ExternalFiles/Protocols/Http.php#L345-L352)

### `eml_http_directory_import_file_before_to_list`

*Run action just before the file is added to the list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.
`$matches[1]` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 365](ExternalFiles/Protocols/Http.php#L365-L373)

### `eml_ftp_directory_import_start`

*Run action on beginning of presumed directory import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 153](ExternalFiles/Protocols/Ftp.php#L153-L160)

### `eml_ftp_directory_import_files`

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 184](ExternalFiles/Protocols/Ftp.php#L184-L192)

### `eml_ftp_directory_import_file_check`

*Run action just before the file check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 218](ExternalFiles/Protocols/Ftp.php#L218-L225)

### `eml_ftp_directory_import_file_before_to_list`

*Run action just before the file is added to the list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_url` | `string` | The URL to import.
`$file_list_new` | `array` | List of files to process.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 241](ExternalFiles/Protocols/Ftp.php#L241-L249)

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

Source: [app/ExternalFiles/Queue.php](ExternalFiles/Queue.php), [line 275](ExternalFiles/Queue.php#L275-L281)

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

Source: [app/ExternalFiles/Queue.php](ExternalFiles/Queue.php), [line 312](ExternalFiles/Queue.php#L312-L318)

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

Source: [app/Plugin/Tables/Logs.php](Plugin/Tables/Logs.php), [line 215](Plugin/Tables/Logs.php#L215-L221)

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

Source: [app/Plugin/Tables/Queue.php](Plugin/Tables/Queue.php), [line 271](Plugin/Tables/Queue.php#L271-L277)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 261](Plugin/Helper.php#L261-L280)

### `eml_get_mime_types`

*Filter the list of possible mime types. This is the list used by the plugin during file-checks
and is not visible or editable in backend.*

To add files of type "your/mime" with file extension ".yourmime" use this example:

```
add_filter( 'eml_get_mime_types', function( $list ) {
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
`$list` | `array` | List of mime types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 302](Plugin/Helper.php#L302-L322)

### `eml_settings_section_name`

*Filter the name of a section object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$name` | `string` | The name.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Section.php](Plugin/Settings/Section.php), [line 65](Plugin/Settings/Section.php#L65-L72)

### `eml_settings_section_title`

*Filter the title of a section object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$title` | `string` | The title.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Section.php](Plugin/Settings/Section.php), [line 94](Plugin/Settings/Section.php#L94-L101)

### `eml_settings_section_setting`

*Filter the settings of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$setting` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Section.php](Plugin/Settings/Section.php), [line 123](Plugin/Settings/Section.php#L123-L130)

### `eml_settings_tab_name`

*Filter the name of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$name` | `string` | The name.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 93](Plugin/Settings/Tab.php#L93-L100)

### `eml_settings_tab_title`

*Filter the title of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$title` | `string` | The title.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 122](Plugin/Settings/Tab.php#L122-L129)

### `eml_settings_tab_settings`

*Filter the settings of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array` | The settings.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 185](Plugin/Settings/Tab.php#L185-L192)

### `eml_settings_tab_settings`

*Filter the sections of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$sections` | `array` | The settings.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 243](Plugin/Settings/Tab.php#L243-L250)

### `eml_settings_tab_settings`

*Filter the URL of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The settings.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 324](Plugin/Settings/Tab.php#L324-L331)

### `eml_settings_tab_settings`

*Filter the URL target of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_target` | `string` | The URL target.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 353](Plugin/Settings/Tab.php#L353-L360)

### `eml_settings_tab_settings`

*Filter the class of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$tab_class` | `string` | The tab class.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 388](Plugin/Settings/Tab.php#L388-L395)

### `eml_settings_tabs`

*Filter the list of setting tabs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$tabs` | `array` | List of tabs.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 139](Plugin/Settings/Settings.php#L139-L146)

### `eml_settings_title`

*Filter the title of settings object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$title` | `string` | The title.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 191](Plugin/Settings/Settings.php#L191-L198)

### `eml_settings_title`

*Filter the menu title of settings object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$menu_title` | `string` | The menu title.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 220](Plugin/Settings/Settings.php#L220-L227)

### `eml_settings_menu_slug`

*Filter the menu slug of settings object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$menu_slug` | `string` | The menu slug.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 249](Plugin/Settings/Settings.php#L249-L256)

### `eml_settings_menu_icon`

*Filter the menu slug of settings object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$menu_icon` | `string` | The menu icon.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 607](Plugin/Settings/Settings.php#L607-L614)

### `eml_settings_parent_menu_slug`

*Filter the menu slug of settings object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$parent_menu_slug` | `string` | The parent menu slug.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Settings` | The settings-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 636](Plugin/Settings/Settings.php#L636-L643)

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

Source: [app/ExternalFiles/File_Types.php](ExternalFiles/File_Types.php), [line 66](ExternalFiles/File_Types.php#L66-L73)

### `eml_attachment_link`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` |  | 
`$url` |  | 
`$attachment_id` |  | 

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 185](ExternalFiles/Files.php#L185-L185)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 279](ExternalFiles/Files.php#L279-L288)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 353](ExternalFiles/Files.php#L353-L361)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 396](ExternalFiles/Files.php#L396-L405)

### `eml_file_import_attachment`

*Filter the attachment settings*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_array` | `string` | The attachment settings.
`$file_data['url']` |  | 
`$file_data` | `array` | List of file settings detected by importer.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 415](ExternalFiles/Files.php#L415-L424)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 81](ExternalFiles/Protocol_Base.php#L81-L87)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 226](ExternalFiles/Protocol_Base.php#L226-L233)

### `eml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$file_path` |  | 

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 272](ExternalFiles/Protocols/Sftp.php#L272-L280)

### `eml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$file_path` |  | 

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 193](ExternalFiles/Protocols/File.php#L193-L201)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 60](ExternalFiles/Protocols/Http.php#L60-L70)

### `eml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 110](ExternalFiles/Protocols/Http.php#L110-L110)

### `eml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 128](ExternalFiles/Protocols/Http.php#L128-L128)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 137](ExternalFiles/Protocols/Http.php#L137-L147)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 213](ExternalFiles/Protocols/Http.php#L213-L220)

### `eml_http_directory_regex`

*Filter the content with regex.*

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 264](ExternalFiles/Protocols/Http.php#L264-L272)

### `eml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$url` | `string` | The requested external URL.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 489](ExternalFiles/Protocols/Http.php#L489-L499)

### `eml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 585](ExternalFiles/Protocols/Http.php#L585-L585)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 602](ExternalFiles/Protocols/Http.php#L602-L609)

### `eml_http_ssl`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 640](ExternalFiles/Protocols/Http.php#L640-L640)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 646](ExternalFiles/Protocols/Http.php#L646-L653)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 677](ExternalFiles/Protocols/Http.php#L677-L683)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 709](ExternalFiles/Protocols/Http.php#L709-L715)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 63](ExternalFiles/Protocols/Ftp.php#L63-L72)

### `eml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$file_path` |  | 

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 327](ExternalFiles/Protocols/Ftp.php#L327-L335)

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

### `eml_file_type_compatibility_result`

*Filter the result of file type compatibility check.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | The result (true or false).
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The external file object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 64](ExternalFiles/File_Types_Base.php#L64-L71)

### `eml_file_type_supported_mime_types`

*Filter the supported mime types of single file type.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$mime_type` | `array` | List of mime types.
`$external_file_obj` | `\ExternalFilesInMediaLibrary\ExternalFiles\File` | The file object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/File_Types_Base.php](ExternalFiles/File_Types_Base.php), [line 99](ExternalFiles/File_Types_Base.php#L99-L106)

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

Source: [app/ExternalFiles/Queue.php](ExternalFiles/Queue.php), [line 267](ExternalFiles/Queue.php#L267-L273)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 205](ExternalFiles/Proxy.php#L205-L212)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 225](ExternalFiles/Proxy.php#L225-L231)

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

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 88](ThirdParty/ThirdPartySupport.php#L88-L94)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


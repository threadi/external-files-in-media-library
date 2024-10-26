# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 317](ExternalFiles/Files.php#L317-L324)

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

Source: [app/Plugin/Languages.php](Plugin/Languages.php), [line 83](Plugin/Languages.php#L83-L90)

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

### `eml_supported_mime_types`

*Filter the possible mime types this plugin could support. This is the list used for the setting in backend.*

To add files of type "your/mime" with extension "yourmime" use this example:

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 238](Plugin/Helper.php#L238-L257)

### `eml_get_mime_types`

*Filter the list of possible mime types. This is the list used by the plugin during file-checks.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of mime types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 274](Plugin/Helper.php#L274-L280)

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

Source: [app/Plugin/Settings/Section.php](Plugin/Settings/Section.php), [line 58](Plugin/Settings/Section.php#L58-L65)

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

Source: [app/Plugin/Settings/Section.php](Plugin/Settings/Section.php), [line 87](Plugin/Settings/Section.php#L87-L94)

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

Source: [app/Plugin/Settings/Section.php](Plugin/Settings/Section.php), [line 116](Plugin/Settings/Section.php#L116-L123)

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

*Filter the url of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url` | `string` | The settings.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 321](Plugin/Settings/Tab.php#L321-L328)

### `eml_settings_tab_settings`

*Filter the url target of a tabs object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$url_target` | `string` | The url target.
`$this` | `\ExternalFilesInMediaLibrary\Plugin\Settings\Tab` | The tab-object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 350](Plugin/Settings/Tab.php#L350-L357)

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

Source: [app/Plugin/Settings/Tab.php](Plugin/Settings/Tab.php), [line 385](Plugin/Settings/Tab.php#L385-L392)

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

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 131](Plugin/Settings/Settings.php#L131-L138)

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

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 183](Plugin/Settings/Settings.php#L183-L190)

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

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 212](Plugin/Settings/Settings.php#L212-L219)

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

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 241](Plugin/Settings/Settings.php#L241-L248)

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

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 576](Plugin/Settings/Settings.php#L576-L583)

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

Source: [app/Plugin/Settings/Settings.php](Plugin/Settings/Settings.php), [line 605](Plugin/Settings/Settings.php#L605-L612)

### `eml_blacklist`

*Filter the given URL against custom blacklists.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Return true if blacklist matches.
`$url` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 141](ExternalFiles/Files.php#L141-L148)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 191](ExternalFiles/Files.php#L191-L199)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 212](ExternalFiles/Files.php#L212-L221)

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

Source: [app/ExternalFiles/Files.php](ExternalFiles/Files.php), [line 231](ExternalFiles/Files.php#L231-L240)

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

Source: [app/ExternalFiles/Protocol_Base.php](ExternalFiles/Protocol_Base.php), [line 74](ExternalFiles/Protocol_Base.php#L74-L80)

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

Source: [app/ExternalFiles/Protocols/Sftp.php](ExternalFiles/Protocols/Sftp.php), [line 212](ExternalFiles/Protocols/Sftp.php#L212-L220)

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

Source: [app/ExternalFiles/Protocols/File.php](ExternalFiles/Protocols/File.php), [line 132](ExternalFiles/Protocols/File.php#L132-L140)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 59](ExternalFiles/Protocols/Http.php#L59-L69)

### `eml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 109](ExternalFiles/Protocols/Http.php#L109-L109)

### `eml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 127](ExternalFiles/Protocols/Http.php#L127-L127)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 136](ExternalFiles/Protocols/Http.php#L136-L146)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 363](ExternalFiles/Protocols/Http.php#L363-L373)

### `eml_http_save_local`

*Filter if a http-file should be saved local or not.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if file should be saved local.
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 455](ExternalFiles/Protocols/Http.php#L455-L462)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 488](ExternalFiles/Protocols/Http.php#L488-L495)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 519](ExternalFiles/Protocols/Http.php#L519-L525)

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

Source: [app/ExternalFiles/Protocols/Http.php](ExternalFiles/Protocols/Http.php), [line 551](ExternalFiles/Protocols/Http.php#L551-L557)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 61](ExternalFiles/Protocols/Ftp.php#L61-L70)

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

Source: [app/ExternalFiles/Protocols/Ftp.php](ExternalFiles/Protocols/Ftp.php), [line 252](ExternalFiles/Protocols/Ftp.php#L252-L260)

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

Source: [app/ExternalFiles/Proxy.php](ExternalFiles/Proxy.php), [line 207](ExternalFiles/Proxy.php#L207-L214)

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

Source: [app/ThirdParty/ThirdPartySupport.php](ThirdParty/ThirdPartySupport.php), [line 84](ThirdParty/ThirdPartySupport.php#L84-L90)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


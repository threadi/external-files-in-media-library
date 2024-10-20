# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `eml_after_file_save`

*Run additional tasks after new external file has been added.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$external_file_obj` | `\threadi\eml\Controller\External_File` | The object of the external file.
`$file_data` | `array` | The array with the file data.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 300](eml/Controller/class-external-files.php#L300-L307)

## Filters

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

Source: [classes/eml/Controller/class-crypt.php](eml/Controller/class-crypt.php), [line 124](eml/Controller/class-crypt.php#L124-L130)

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

Source: [classes/eml/Controller/Protocols/class-ftp.php](eml/Controller/Protocols/class-ftp.php), [line 57](eml/Controller/Protocols/class-ftp.php#L57-L66)

### `eml_external_file_infos`

*Filter the data of a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$results` | `array` | List of detected file settings.
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [classes/eml/Controller/Protocols/class-ftp.php](eml/Controller/Protocols/class-ftp.php), [line 181](eml/Controller/Protocols/class-ftp.php#L181-L189)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 59](eml/Controller/Protocols/class-http.php#L59-L69)

### `eml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 109](eml/Controller/Protocols/class-http.php#L109-L109)

### `eml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$url` |  | 

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 127](eml/Controller/Protocols/class-http.php#L127-L127)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 136](eml/Controller/Protocols/class-http.php#L136-L146)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 363](eml/Controller/Protocols/class-http.php#L363-L373)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 455](eml/Controller/Protocols/class-http.php#L455-L462)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 488](eml/Controller/Protocols/class-http.php#L488-L495)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 519](eml/Controller/Protocols/class-http.php#L519-L525)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 551](eml/Controller/Protocols/class-http.php#L551-L557)

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

Source: [classes/eml/Controller/class-proxy.php](eml/Controller/class-proxy.php), [line 210](eml/Controller/class-proxy.php#L210-L217)

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

Source: [classes/eml/Controller/class-third-party-support.php](eml/Controller/class-third-party-support.php), [line 79](eml/Controller/class-third-party-support.php#L79-L85)

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
`1.4.0` | Available since 1.4.0.

Source: [classes/eml/Controller/class-protocol-base.php](eml/Controller/class-protocol-base.php), [line 74](eml/Controller/class-protocol-base.php#L74-L80)

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

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 126](eml/Controller/class-external-files.php#L126-L133)

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

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 172](eml/Controller/class-external-files.php#L172-L180)

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

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 195](eml/Controller/class-external-files.php#L195-L204)

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

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 214](eml/Controller/class-external-files.php#L214-L223)

### `eml_protocols`

*Filter the list of available protocols.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of protocol handler.

**Changelog**

Version | Description
------- | -----------
`1.4.0` | Available since 1.4.0.

Source: [classes/eml/Controller/class-protocols.php](eml/Controller/class-protocols.php), [line 63](eml/Controller/class-protocols.php#L63-L69)

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

Source: [classes/eml/class-helper.php](eml/class-helper.php), [line 240](eml/class-helper.php#L240-L259)

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

Source: [classes/eml/class-helper.php](eml/class-helper.php), [line 276](eml/class-helper.php#L276-L282)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


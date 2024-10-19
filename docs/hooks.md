# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

*This project does not contain any WordPress actions.*

## Filters

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

Source: [classes/eml/Controller/Protocols/class-ftp.php](eml/Controller/Protocols/class-ftp.php), [line 55](eml/Controller/Protocols/class-ftp.php#L55-L64)

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

Source: [classes/eml/Controller/Protocols/class-ftp.php](eml/Controller/Protocols/class-ftp.php), [line 179](eml/Controller/Protocols/class-ftp.php#L179-L187)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 72](eml/Controller/Protocols/class-http.php#L72-L82)

### `eml_http_check_content_type_existence`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$this->get_url()` |  | 

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 120](eml/Controller/Protocols/class-http.php#L120-L120)

### `eml_http_check_content_type`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$true` |  | 
`$this->get_url()` |  | 

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 138](eml/Controller/Protocols/class-http.php#L138-L138)

### `eml_check_url_availability`

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 147](eml/Controller/Protocols/class-http.php#L147-L157)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 210](eml/Controller/Protocols/class-http.php#L210-L220)

### `eml_http_save_local`

*Force to save a http-file local or not.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | True if file should be saved local.
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 296](eml/Controller/Protocols/class-http.php#L296-L303)

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

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 327](eml/Controller/Protocols/class-http.php#L327-L333)

### `eml_http_states`

*Filter the list of allowed http states.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array` | List of http states.
`$this->get_url()` |  | 

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/eml/Controller/Protocols/class-http.php](eml/Controller/Protocols/class-http.php), [line 357](eml/Controller/Protocols/class-http.php#L357-L364)

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

Source: [classes/eml/Controller/class-proxy.php](eml/Controller/class-proxy.php), [line 241](eml/Controller/class-proxy.php#L241-L248)

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

Source: [classes/eml/Controller/class-third-party-support.php](eml/Controller/class-third-party-support.php), [line 81](eml/Controller/class-third-party-support.php#L81-L87)

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

Source: [classes/eml/Controller/class-protocol-base.php](eml/Controller/class-protocol-base.php), [line 76](eml/Controller/class-protocol-base.php#L76-L82)

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

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 123](eml/Controller/class-external-files.php#L123-L130)

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

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 183](eml/Controller/class-external-files.php#L183-L191)

### `eml_file_import_title`

*Filter the title for a single file during import.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$file_data['title']` |  | 
`$url` | `string` | The requested external URL.
`$file_data` | `array` | List of file settings detected by importer.

**Changelog**

Version | Description
------- | -----------
`1.1.0` | Available since 1.1.0

Source: [classes/eml/Controller/class-external-files.php](eml/Controller/class-external-files.php), [line 207](eml/Controller/class-external-files.php#L207-L216)

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

*Filter the possible mime types this plugin could support.*

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

Source: [classes/eml/class-helper.php](eml/class-helper.php), [line 235](eml/class-helper.php#L235-L254)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


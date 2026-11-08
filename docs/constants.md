# Credentials via constants

The credentials of the services can be defined as constants, e.g. in your `wp-config.php`. They are then not saved in the database, are never sent to the browser and are used for every connection to this service: in the directory listing, during import, synchronization and export, and when files are delivered via the proxy.

## Names

`EFML_` + name of the service + `_` + name of the field, all in uppercase. Hyphens are replaced by underscores.

Example: the field `password` of the service `ftp` is defined via `EFML_FTP_PASSWORD`.

## Rules

* **All required fields:** The constants of a service are only used if a constant is defined for **all required fields** of this service. If one is missing, no constant of this service is used. The settings page of the service shows which constants are missing.
* **Optional fields:** They could be defined as constants in addition, e.g. the Auth Basic credentials for the REST API.
* **Constants win:** Their values replace the values from the settings, the user profiles and the saved external sources.
* **Only for the defined server:** For services with a server field, the credentials are only used for connections to the host defined in the constant for the server. They are never used for other servers.
* **Secret values in the browser:** Credentials are not shown in the form, the name of the constant is shown instead. The server stays visible, so it is clear which one is used.
* **Location fixed to "One for this website":** If the constants of a service are complete, the location of the credentials is fixed to "One for this website" and could not be changed. The previously saved location is kept and is used again as soon as the constants are removed.
* **Settings and user profiles:** For "One for this website" and "User-specific", hints on the constants are shown instead of the input fields. The values saved in the database are not changed and are used again as soon as the constants are removed.

## Saved external sources

* **Sources on the server of the constants** keep working. Their saved credentials are ignored, the constants are used.
* **Sources on other servers** are not usable as long as the constants are defined: opening, import, synchronization and export are blocked, nothing is written to or deleted on these servers. Each attempt is logged. The list of external sources shows a hint, editing, renaming and deleting stay possible.
* **Nothing is deleted:** The sources and their settings are kept and work again as soon as the constants are removed.
* **Files imported before** from these sources are still delivered with their own credentials.
* **Manual entries:** URLs on other servers of this service could also not be imported via "Add URL", even with own credentials (e.g. `ftp://` or `sftp://` if the FTP constants are defined). Normal `http(s)://` URLs are not affected, also not by the constants of the REST API.

## Constants of the services

### FTP, FTPS and SFTP

| Constant | Required | Content |
|---|---|---|
| `EFML_FTP_SERVER` | yes | Server with protocol, e.g. `ftps://ftp.example.com` or `sftp://example.com:2222` |
| `EFML_FTP_LOGIN` | yes | Login |
| `EFML_FTP_PASSWORD` | yes | Password |

```php
define( 'EFML_FTP_SERVER', 'ftps://ftp.example.com' );
define( 'EFML_FTP_LOGIN', 'my-login' );
define( 'EFML_FTP_PASSWORD', 'my-password' );
```

### WordPress REST API

| Constant | Required | Content |
|---|---|---|
| `EFML_REST_SERVER` | yes | URL of the WordPress website, e.g. `https://example.com` |
| `EFML_REST_LOGIN` | no | Auth Basic login |
| `EFML_REST_PASSWORD` | no | Auth Basic password |

### ZIP

| Constant | Required | Content |
|---|---|---|
| `EFML_ZIP_SERVER` | yes | URL of the ZIP file |
| `EFML_ZIP_LOGIN` | no | Auth Basic login |
| `EFML_ZIP_PASSWORD` | no | Auth Basic password |
| `EFML_ZIP_ZIP_PASSWORD` | no | Password of the ZIP file |

### CSV and XML lists

| Constant | Required | Content |
|---|---|---|
| `EFML_CSV_LIST_URL` or `EFML_XML_LIST_URL` | yes | URL of the list |
| `EFML_CSV_LOGIN` or `EFML_XML_LOGIN` | no | Auth Basic login |
| `EFML_CSV_PASSWORD` or `EFML_XML_PASSWORD` | no | Auth Basic password |

### YouTube

| Constant | Required | Content |
|---|---|---|
| `EFML_YOUTUBE_CHANNEL_ID` | yes | ID of the channel |
| `EFML_YOUTUBE_API_KEY` | yes | API key |

### Dropbox

Dropbox uses OAuth. Only the credentials of the Dropbox app could be defined as constants. The connection itself is still made via "Connect", the tokens are saved in the database, as Dropbox refreshes them regularly.

| Constant | Required | Content |
|---|---|---|
| `EFML_DROPBOX_API_KEY` | yes | App key |
| `EFML_DROPBOX_API_SECRET` | yes | App secret |

### Services from extensions

Services from extensions (e.g. AWS S3, Google Drive, WebDAV) use the same scheme. The documentation of each extension lists its constants.

The services Local and Multisite do not support constants, as they do not use credentials.

## Hooks for developers

* `efml_service_field_constant_name`: change the name of a constant.
* `efml_service_required_fields_for_constants`: change the list of fields, which must all be defined as constants.
* `efml_service_url_matching_constants`: decide whether a URL matches the server of the constants.
* `efml_service_names_for_constants`: define which services provide their constants for a protocol (e.g. `sftp` → `ftp`, `http` → `rest`).
* `efml_service_fields`: change the fields of a service incl. their values.
* `efml_service_names_for_blocking`: define to which services the URLs of a protocol belong, so they are limited to the server of their constants (e.g. `sftp` → `ftp`).
* `efml_service_fields_matching_constants`: decide whether fields match the target of the constants (e.g. the same bucket).
* `efml_source_blocked_by_constants`: decide whether a saved external source is blocked because of the constants.

For your own service without credentials, set `protected bool $fields_via_constants = false;`.

Further options for services from extensions:

* `protected array $constants_target_fields = array( 'bucket' );`: fields, which define the target besides the server. Saved sources and manual entries with other values are blocked, the constants are never used for them.
* `protected bool $constants_in_fields = false;`: the constants are no values of the fields (e.g. API credentials for OAuth), override `get_constant_field_definitions()` for them.
* `get_mode_for_constants()`: location of the credentials used if the constants are defined (default `global`).
* `get_required_fields_for_constants()`: change the list of required fields.
* `add_constant_hint_setting()`: show a hint instead of a setting, if the value is defined via constant. If the service has a field with the URL of the server, set `protected string $constants_host_field = '<field name>';`, so the credentials are only used for this server.

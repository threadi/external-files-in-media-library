# WordPress REST API

With the WordPress REST API, you can manage external files in the media library
remotely as well. The plugin provides an endpoint for this purpose
that can be addressed using various methods.

Access is only possible with valid authorization. This can be done, for example, with the
application password provided by WordPress.

## The endpoint

`/wp-json/efml/file/`

## Methods

### POST

* Creates a new entry for an external URL in the media library
* Parameters:
  * “url” with the external URL
  * “login” with a login (optional)
  * “password” with the corresponding password (optional)

### GET

* Returns whether a specified external URL exists in the media library
* Parameters:
  * “url” with the external URL

### DELETE

* Deletes a specified external URL
* Parameters:
  * “url” with the external URL

## Notes

* All protocols supported by the plugin are supported in this way.
* It is not possible to delete automatically synchronized files in this way.

# Importing from WordPress REST API

This section describes how you can import files from another WordPress project via REST API.

## Requirements

The other WordPress project must have the REST API publicly accessible. You can check this by calling up its URL.
Example:

https://example.com/wp-json/wp/v2/media

## Importing files individually

To import files from an external REST API individually, go to your WordPress backend,
select Media > Add External Files, and click on the “WordPress REST API” service. Enter the domain
or path to the REST API of the other project in the field. Wait until the view has loaded. You can then
select the desired files individually for your own media library.

### Note

The loading process may be interrupted if the external media library is very large. In this case, your hosting timeouts will apply,
which the plugin cannot bypass.

## Import all files

### In the backend #1

Proceed as described above for individual files and click on the button to import all files in the service.

### In the backend #2

1. Go to Media > Add Media File.
2. Click on “Add External Files”.
3. Enter the URL of the other WordPress project here.
4. Click on “Add URLs” and wait until the process is complete.

### Via WP CLI

Call up the following command:

`wp eml import https://example.com/wp-json/wp/v2/media`

Note: It is important to specify the complete path to the REST API here, otherwise the plugin will only scan the
home page and attempt to import it.

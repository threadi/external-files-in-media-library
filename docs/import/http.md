# Import from HTTP

This section describes how you can import files from an HTTP URL.

## Prerequisites

The URL must be accessible under one of the following protocols:

* `http://`
* `https://`

The file must have one of the permitted formats. You can define these in the plugin settings.

The hosting must support the WordPress functions wp_remote_* (e.g. with curl or alternative libraries).

## Note

If the source file is delivered without SSL, but your website uses SSL, the file is always
saved locally to avoid error messages for visitors in the frontend.

## Access data

Optionally, you can also specify access data for AuthBasic authentication for the import. Files imported in this way
are generally saved locally, as they would otherwise not be accessible to website visitors.

## Examples

### Single file

For the import, enter the URL under which your file can be accessed, e.g.

`https://example.com/example.png`

### Import directory

For the import, enter the URL of the directory whose files you want to import completely. Prerequisite
for this import is that DirectoryListing is activated under the URL _ If necessary, contact the hoster's support.

Example: `https://example.com/directory-name/`

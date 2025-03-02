# Import from FTP

This section describes how you can import files from an FTP directory.

## Prerequisites

The FTP directory must be accessible for the import with one of the following protocols:

* `ftp://`
* `ftps://`

Access data is always required. The import of anonymous FTP directories is not supported.

The hosting must provide the PHP libraries _ftp_connect_ or _ftp_ssl_connect_. If you have question about this contact
your hosting support.

## Note

The specification of a URL with visible access data is _not_ supported.

Example: `ftp://login:password@example.com/example.png`

## Examples

### Single file

To import a single file from an FTP account, enter the FTP URL of this file together with the FTP access data.

Example of a URL: `ftp://example.com/example.png`

### Import directory

To import all files from an FTP directory, enter the FTP URL of this directory together with the FTP access data.

Example of a URL: `ftp://example.com/directory-name/`

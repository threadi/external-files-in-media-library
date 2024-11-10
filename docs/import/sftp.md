# Import from SFTP/SSH

This section describes how you can import files from an SFTP/SSH directory.

## Prerequisites

The SFTP/SSH directory must be accessible for import with one of the following protocols:

* `sftp://`

Access data is always required. The import of anonymous FTP directories is not supported.

The hosting must provide the PHP libraries _ssh2_connect_.

## Note

The specification of a URL with visible access data is _not_ supported.

Example: `sftp://login:password@example.com/example.png`

## Examples

### Single file

To import a single file from an SFTP/SSH account, enter the SFTP/SSH URL of this file together with the SFTP/SSH access data.

Example of a URL: `sftp://example.com/example.png`

### Import directory

To import all files of an SFTP/SSH directory, enter the SFTP/SSH URL of this directory together with the SFTP/SSH access data.

Example of a URL: `sftp://example.com/directory-name/`

# Quick start

To import external files into your media library, you must enter the URLs of the files in the input field.

## Requirements

* The URLs must be accessible from your WordPress.
* The file types (e.g. PDF, JPEG ...) behind the URLs must be permitted in the plugin settings.

## Example

`https://example.com/file.pdf`

**You can also specify multiple URLs (one per line), even from different domains:**

`https://example.com/file.pdf`

`https://example-instrustry.com/file-trust.pdf`

Then click on the button to add the files. Wait a moment until the response is displayed.

# The URL requires access credentials?

Below the input field for the URLs, you can enter access data if the URLs require it. The login is supported for:

* AuthBasic via HTTP/HTTPS
* FTP
* SFTP
* SSH

There is also support for individual services that require their own authentication:

* AWS S3
* Dropbox
* Google Drive
* Google Cloud Storage
* WebDav

And also for services without authorization:

* Local directory
* Content of ZIP-files

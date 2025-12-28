# Add file via hook

## Who this guide is for

These instructions are intended for developers of plugins or themes who want to use the features
of External Files for Media Library for themselves.

## Requirements

* Your own WordPress plugin or theme.
* Knowledge of PHP.

## Using the hook

### Structure

* Type: Filter
* Name: efml_add_url
* Parameters:
  * ID of the media file (0 to add a new file)
  * URL to be added
  * Login to access the URL (optional).
  * Password to access the URL (optional).
* Return:
  * equal to "0" if the URL was not added and does not exist in the media library.
  * Greater than "0" if the URL was added or already exists in the media library

### Example:

`apply_filters( 'efml_add_url', 0, ‘https://example.com/sample.pdf’, 'login', 'password' );`

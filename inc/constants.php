<?php
/**
 * File which holds all constants this plugin is using.
 *
 * @package external-files-in-media-library
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Name of the post-field where the external url of a file resides.
 */
const EFML_POST_META_URL = 'eml_external_file';

/**
 * State of external file url (available or not).
 */
const EFML_POST_META_AVAILABILITY = 'eml_external_file_state';

/**
 * Import-Marker.
 */
const EFML_POST_IMPORT_MARKER = 'eml_imported';

/**
 * Name of our own capability.
 */
const EFML_CAP_NAME = 'eml_manage_files';

/**
 * Options-list of transients.
 */
const EFML_TRANSIENT_LIST = 'eml_transients';

/**
 * Name of our openssl hash.
 */
const EFML_HASH = 'eml_hash';

/**
 * Name of our sodium hash.
 */
const EFML_SODIUM_HASH = 'eml_sodium_hash';

/**
 * URL of the service URL for Google OAuth.
 */
const EFML_GOOGLE_OAUTH_SERVICE_URL = 'https://www.thomaszwirner.de/google-oauth-service/';

/**
 * URL to refresh as token.
 */
const EFML_GOOGLE_OAUTH_REFRESH_URL = 'https://www.thomaszwirner.de/google-refresh-service/';

/**
 * The Client ID for our OAuth app.
 */
const EFML_GOOGLE_OAUTH_CLIENT_ID = '319161798172-2oheqcov8cjl5kucbcqkae72pakaf35a.apps.googleusercontent.com';

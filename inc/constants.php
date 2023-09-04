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
const EML_POST_META_URL = 'eml_external_file';

/**
 * State of external file url (available or not).
 */
const EML_POST_META_AVAILABILITY = 'eml_external_file_state';

/**
 * Import-Marker.
 */
const EML_POST_IMPORT_MARKER = 'eml_imported';

/**
 * Name of our own capability.
 */
const EML_CAP_NAME = 'eml_manage_files';

/**
 * Options-list of transients.
 */
const EML_TRANSIENT_LIST = 'eml_transients';

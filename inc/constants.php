<?php
/**
 * File which holds all constants this plugin is using.
 *
 * @package external-files-in-media-library
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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

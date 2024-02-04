<?php
/**
 * Plugin Name:       External files in Media Library
 * Description:       Enables the Media Library to use external files.
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Version:           @@VersionNumber@@
 * Author:            Thomas Zwirner
 * Author URI:        https://www.thomaszwirner.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       external-files-in-media-library
 *
 * @package external-files-in-media-library
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// do nothing if PHP-version is not 8.0 or newer.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	return;
}

// get plugin-path.
const EML_PLUGIN         = __FILE__;

// set plugin-version.
const EML_PLUGIN_VERSION = '@@VersionNumber@@';

// include necessary file.
require_once 'inc/constants.php';
require_once 'inc/autoload.php';
require_once 'inc/admin.php';

// initialize plugin.
$eml = threadi\eml\Controller\init::get_instance();
$eml->init();

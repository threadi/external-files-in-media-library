<?php
/**
 * Tasks to run during uninstallation of this plugin.
 *
 * @package external-files-in-media-library
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

use threadi\eml\Controller\Uninstall;

// get plugin-path.
const EML_PLUGIN = __FILE__;

// include necessary files.
require_once 'inc/autoload.php';
require_once 'inc/constants.php';

// run the uninstaller-methods.
Uninstall::get_instance()->run();

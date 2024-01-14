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

// include necessary files.
use threadi\eml\Controller\Uninstall;

// get plugin-path.
const EML_PLUGIN = __FILE__;

require_once 'inc/autoload.php';
require_once 'inc/constants.php';

// run the uninstaller-methods.
$uninstall_obj = Uninstall::get_instance();
$uninstall_obj->run();

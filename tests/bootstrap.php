<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package external-files-in-media-library
 */

define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'UNIT_TESTS_DATA_PLUGIN_DIR', TESTS_PLUGIN_DIR . '/tests/Data/' ); // Customize.

// Define WP_CORE_DIR if not already defined
if ( ! defined( 'WP_CORE_DIR' ) ) {
	$_wp_core_dir = getenv( 'WP_CORE_DIR' );
	if ( ! $_wp_core_dir ) {
		$_wp_core_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress';
	}
	define( 'WP_CORE_DIR', $_wp_core_dir );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __FILE__, 2 ) . '/external-files-in-media-library.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// provide all abilities in tests, not only the read-only abilities (which is the default).
tests_add_filter( 'efml_abilities_mode', static fn() => 'all' );

// use GD before Imagick for images in the tests: Imagick is considerably slower with the large test images.
// Set EFML_TEST_IMAGE_EDITOR=imagick to test with Imagick first, like WordPress does it by default.
if ( 'imagick' !== getenv( 'EFML_TEST_IMAGE_EDITOR' ) ) {
	tests_add_filter( 'wp_image_editors', static fn(): array => array( 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick' ) );
}

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// The database is installed now. Tests in separate processes (@runInSeparateProcess, @runTestsInSeparateProcesses)
// inherit this variable and do not install it again: the installation would delete and create all tables of WordPress,
// while the main process is still connected to the database and waits for the separate process without any time limit.
putenv( 'WP_TESTS_SKIP_INSTALL=1' );

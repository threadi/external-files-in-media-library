<?php
/**
 * Bootstrap of the live tests with real accounts.
 *
 * Reads ".env.live" (or the file in the environment variable EFML_LIVE_ENV), defines its values as constants of
 * the plugin and loads the extensions, which "composer test-install-live" has installed. Without any configured
 * service it shows a hint and ends without running a test.
 *
 * @package external-files-in-media-library
 */

use ExternalFilesInMediaLibrary\Tests\Live\Env;

require_once __DIR__ . '/Env.php';

// get the configuration.
$efml_live_env = Env::get_instance();

// bail if nothing is configured: show the hint and do nothing more.
if ( ! $efml_live_env->is_configured() ) {
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	echo PHP_EOL . 'Live tests: no service is configured, no test has been run.' . PHP_EOL;
	echo $efml_live_env->exists()
		? '  The file ' . $efml_live_env->get_file() . ' contains no complete credentials of any service.' . PHP_EOL
		: '  The file ' . Env::get_file_path() . ' does not exist (or set its path in the environment variable EFML_LIVE_ENV).' . PHP_EOL;
	echo '  Copy .env.live.example to .env.live and enter the credentials of the services you want to test, see docs/live-tests.md.' . PHP_EOL . PHP_EOL;
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 0 );
}

// define the constants of the plugin and its extensions.
foreach ( $efml_live_env->get_constants() as $efml_live_name => $efml_live_value ) {
	if ( ! defined( $efml_live_name ) ) {
		define( $efml_live_name, $efml_live_value );
	}
}

// the paths like in the bootstrap of the unit tests.
define( 'TESTS_PLUGIN_DIR', dirname( __DIR__, 2 ) );
define( 'UNIT_TESTS_DATA_PLUGIN_DIR', TESTS_PLUGIN_DIR . '/tests/Data/' );
define( 'EFML_LIVE_TESTS_RUNNING', true );

if ( ! defined( 'WP_CORE_DIR' ) ) {
	$efml_live_core_dir = getenv( 'WP_CORE_DIR' );
	define( 'WP_CORE_DIR', $efml_live_core_dir ? $efml_live_core_dir : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress' );
}

$efml_live_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $efml_live_tests_dir ) {
	$efml_live_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$efml_live_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $efml_live_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $efml_live_polyfills_path );
}

if ( ! file_exists( "{$efml_live_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$efml_live_tests_dir}/includes/functions.php, have you run composer test-install-live?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// give access to tests_add_filter() function.
require_once "{$efml_live_tests_dir}/includes/functions.php";

/**
 * Return the main files of the installed extensions (see "composer test-install-live").
 *
 * @return array<string,string> The slug of the extension with the path to its main file.
 */
function efml_live_get_installed_addons(): array {
	$addons = array();
	foreach ( array_keys( Env::get_addons() ) as $slug ) {
		$file = rtrim( WP_CORE_DIR, '/\\' ) . '/wp-content/plugins/' . $slug . '/' . $slug . '.php';
		if ( file_exists( $file ) && file_exists( dirname( $file ) . '/vendor/autoload.php' ) ) {
			$addons[ $slug ] = $file;
		}
	}
	return $addons;
}

/**
 * Load the plugin. The extensions are loaded by WordPress afterward as active plugins (see below).
 *
 * @return void
 */
function efml_live_load_plugins(): void {
	require TESTS_PLUGIN_DIR . '/external-files-in-media-library.php';
}
tests_add_filter( 'muplugins_loaded', 'efml_live_load_plugins' );

// mark the plugin and the installed extensions as active: WordPress loads the extensions from its plugin directory,
// the extensions check whether the plugin is active.
tests_add_filter(
	'option_active_plugins',
	static function ( $plugins ) {
		$plugins   = is_array( $plugins ) ? $plugins : array();
		$plugins[] = 'external-files-in-media-library/external-files-in-media-library.php';
		foreach ( array_keys( efml_live_get_installed_addons() ) as $slug ) {
			$plugins[] = $slug . '/' . $slug . '.php';
		}
		return array_values( array_unique( $plugins ) );
	}
);

// allow requests to servers in the local network (e.g. MinIO or WebDAV in a container), if requested.
if ( $efml_live_env->get( 'EFML_LIVE_ALLOW_LOCAL_HOSTS' ) ) {
	tests_add_filter( 'http_request_host_is_external', '__return_true' );
	tests_add_filter(
		'http_request_args',
		static function ( $args ) {
			$args['reject_unsafe_urls'] = false;
			return $args;
		},
		PHP_INT_MAX
	);
}

// accept self-signed certificates (e.g. of a WebDAV server in a container), if requested.
if ( $efml_live_env->get( 'EFML_LIVE_IGNORE_SSL' ) ) {
	tests_add_filter( 'https_ssl_verify', '__return_false' );
	tests_add_filter( 'https_local_ssl_verify', '__return_false' );
	tests_add_filter( 'pre_option_eml_webdav_ignore_ssl', static fn() => 1 );
}

// allow the local directory of the tests as local files: the plugin allows only files in the hosting root.
if ( $efml_live_env->has( 'EFML_LIVE_LOCAL_DIRECTORY' ) ) {
	tests_add_filter(
		'efml_file_base',
		static function ( $base ) use ( $efml_live_env ) {
			$directory = (string) realpath( $efml_live_env->get( 'EFML_LIVE_LOCAL_DIRECTORY' ) );
			if ( '' === $directory || ! is_string( $base ) || '' === $base ) {
				return $base;
			}

			// use the common parent directory of both.
			$base_parts      = explode( '/', trim( wp_normalize_path( $base ), '/' ) );
			$directory_parts = explode( '/', trim( wp_normalize_path( $directory ), '/' ) );
			$common          = array();
			foreach ( $base_parts as $i => $part ) {
				if ( ! isset( $directory_parts[ $i ] ) || $directory_parts[ $i ] !== $part ) {
					break;
				}
				$common[] = $part;
			}
			return '/' . implode( '/', $common );
		}
	);
}

// provide all abilities in the tests, not only the read-only abilities (which is the default).
tests_add_filter( 'efml_abilities_mode', static fn() => 'all' );

// start up the WP testing environment.
require "{$efml_live_tests_dir}/includes/bootstrap.php";

// load the base class of the tests.
require_once __DIR__ . '/Live_Test_Base.php';

// show which services are tested.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo PHP_EOL . 'Live tests with ' . $efml_live_env->get_file() . PHP_EOL;
foreach ( Env::get_service_definitions() as $efml_live_service ) {
	$efml_live_missing = $efml_live_env->get_missing_keys( $efml_live_service );
	if ( ! empty( $efml_live_missing ) ) {
		continue;
	}
	$efml_live_addon = (string) ( $efml_live_service['plugin'] ?? '' );
	echo '  ' . str_pad( (string) $efml_live_service['label'], 50 ) . ( '' === $efml_live_addon || isset( efml_live_get_installed_addons()[ $efml_live_addon ] ) ? 'tested' : 'skipped, extension ' . $efml_live_addon . ' is not installed (composer test-install-live)' ) . PHP_EOL;
}
if ( $efml_live_env->has( 'EFML_LIVE_URLS' ) ) {
	echo '  ' . str_pad( 'Single URLs', 50 ) . 'tested' . PHP_EOL;
}
echo PHP_EOL;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

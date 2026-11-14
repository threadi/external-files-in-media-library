<?php
/**
 * Tests for the installation of extensions in the quickstart.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources_Base;
use ExternalFilesInMediaLibrary\Plugin\Setup;
use ExternalFilesInMediaLibrary\Services\Service_Plugin_Base;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;
use ZipArchive;

/**
 * Object of a not installed extension for the tests.
 */
class Setup_Test_Extension extends Service_Plugin_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'efml-test-service';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Test Service';

	/**
	 * The features of the extension.
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_features = array( 'export' );

	/**
	 * The source of the extension.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'             => 'efml-test-source',
		'plugin_slug'      => 'efml-test-extension',
		'plugin_main_file' => 'efml-test-extension/efml-test-extension.php',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Return the label of the plugin.
	 *
	 * @return string
	 */
	public function get_plugin_label(): string {
		return 'EFML Test Extension';
	}
}

/**
 * Source of the extension for the tests.
 */
class Setup_Test_Source extends Plugin_Sources_Base {
	/**
	 * The path of the ZIP file, which is returned as download.
	 *
	 * @var string
	 */
	public static string $zip = '';

	/**
	 * Return the name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'efml-test-source';
	}

	/**
	 * Return the ZIP file.
	 *
	 * @param array<string,string> $config The configuration.
	 *
	 * @return string
	 */
	public function get_file( array $config ): string {
		return self::$zip;
	}

	/**
	 * Return the description.
	 *
	 * @param array<string,string> $config The configuration.
	 *
	 * @return string
	 */
	public function get_description( array $config ): string {
		return '<p>Loaded from the test source.</p>';
	}
}

/**
 * Object to test the installation of extensions in the quickstart.
 */
class SetupExtensions extends externalFilesTests {
	/**
	 * The original request URI.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * The test extension.
	 *
	 * @var Setup_Test_Extension
	 */
	private Setup_Test_Extension $extension;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// use an administrator, who could install plugins.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// add the test extension and its source.
		$this->extension = new Setup_Test_Extension();
		add_filter( 'efml_service_plugins', array( $this, 'add_extension' ) );
		add_filter( 'efml_plugin_sources', array( $this, 'add_source' ) );

		// create the package of the extension.
		Setup_Test_Source::$zip = $this->create_package();

		// prevent the checks for updates after the installation.
		remove_action( 'upgrader_process_complete', 'wp_version_check' );
		remove_action( 'upgrader_process_complete', 'wp_update_plugins' );
		remove_action( 'upgrader_process_complete', 'wp_update_themes' );

		// mark the requests as requests of the setup.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . Setup::NAME;

		// initialize the setup for this request.
		Setup::get_instance()->clear_answers();
		Setup::get_instance()->init_setup();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Setup::get_instance()->clear_answers();
		remove_filter( 'efml_service_plugins', array( $this, 'add_extension' ) );
		remove_filter( 'efml_plugin_sources', array( $this, 'add_source' ) );
		remove_all_filters( 'user_has_cap' );

		// remove the extension.
		deactivate_plugins( 'efml-test-extension/efml-test-extension.php', true );
		$dir = WP_PLUGIN_DIR . '/efml-test-extension';
		if ( is_dir( $dir ) ) {
			wp_delete_file( $dir . '/efml-test-extension.php' );
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( '' !== Setup_Test_Source::$zip && file_exists( Setup_Test_Source::$zip ) ) {
			wp_delete_file( Setup_Test_Source::$zip );
		}

		// restore the request.
		if ( is_null( $this->request_uri ) ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		parent::tear_down();
	}

	/**
	 * Add the test extension.
	 *
	 * @param array<string,Service_Plugin_Base> $plugins The extensions.
	 *
	 * @return array<string,Service_Plugin_Base>
	 */
	public function add_extension( array $plugins ): array {
		$plugins['efml-test-extension'] = $this->extension;
		return $plugins;
	}

	/**
	 * Add the test source.
	 *
	 * @param array<int,string> $sources The sources.
	 *
	 * @return array<int,string>
	 */
	public function add_source( array $sources ): array {
		$sources[] = Setup_Test_Source::class;
		return $sources;
	}

	/**
	 * Create the ZIP package of the test extension and return its path.
	 *
	 * @return string
	 */
	private function create_package(): string {
		$path = trailingslashit( get_temp_dir() ) . 'efml-test-extension-' . wp_generate_password( 6, false ) . '.zip';
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE );
		$zip->addFromString( 'efml-test-extension/efml-test-extension.php', "<?php\n/**\n * Plugin Name: EFML Test Extension\n * Version: 1.0.0\n */\n" );
		$zip->close();
		return $path;
	}

	/**
	 * Set a value of the setup.
	 *
	 * @param string $field_name The name of the field.
	 * @param mixed  $value      The value.
	 *
	 * @return void
	 */
	private function set_value( string $field_name, mixed $value ): void {
		\easySetupForWordPress\Setup::get_instance()->set_field_value( Setup::NAME, $field_name, $value );
	}

	/**
	 * Build the steps again for the actual values and return them, like the setup requests them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_steps(): array {
		$request = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . Setup::NAME );
		$request->set_param( 'config_name', Setup::NAME );
		Setup::get_instance()->prepare_check( null, null, $request );
		return \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
	}

	/**
	 * Return the values of the cards in the given field.
	 *
	 * @param array<string,mixed> $field The field.
	 *
	 * @return array<int,string>
	 */
	private function get_card_values( array $field ): array {
		return array_column( (array) $field['options'], 'value' );
	}

	/**
	 * Test the extensions, which provide services.
	 *
	 * @return void
	 */
	public function test_service_plugins(): void {
		// the S3 extension provides all S3 platforms.
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Services\AwsS3::class, Setup::get_instance()->get_service_plugin_for( 'cloudflare-r2' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Services\AwsS3::class, Setup::get_instance()->get_service_plugin_for( 'wasabi' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Services\GoogleCloudStorage::class, Setup::get_instance()->get_service_plugin_for( 'google-cloud-storage' ) );

		// Google Drive could be set up in the quickstart (it is connected there via its dialog).
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Services\GoogleDrive::class, Setup::get_instance()->get_service_plugin_for( 'google-drive' ) );

		// plugins, which could not be set up in the quickstart, provide no services.
		$this->assertNull( Setup::get_instance()->get_service_plugin_for( 'hello-dolly' ) );

		// unknown services.
		$this->assertNull( Setup::get_instance()->get_service_plugin_for( 'unknown' ) );
		$this->assertNull( Setup::get_instance()->get_service_plugin_for( '' ) );
	}

	/**
	 * Test the installation of an extension in the quickstart.
	 *
	 * @return void
	 */
	public function test_install_extension(): void {
		// the service of the extension is offered.
		$this->set_value( Setup::FIELD_GOAL, 'offload' );
		$steps = $this->get_steps();
		$this->assertContains( 'efml-test-service', $this->get_card_values( $steps[2][ Setup::FIELD_SERVICE ] ) );
		$this->assertContains( 'cloudflare-r2', $this->get_card_values( $steps[2][ Setup::FIELD_SERVICE ] ) );
		$this->assertStringContainsString( 'Why an extension?', $steps[2]['help']['text'] );
		$this->assertStringStartsWith( '<details class="efml-setup-guide efml-extension-reason">', $steps[2]['help']['text'] );

		// choose it: the steps to install the extension follow.
		$this->set_value( Setup::FIELD_SERVICE, 'efml-test-service' );
		$steps = $this->get_steps();
		$this->assertArrayHasKey( Setup::FIELD_INSTALL, $steps[3] );
		$this->assertTrue( $steps[3][ Setup::FIELD_INSTALL ]['required'] );
		$this->assertStringContainsString( 'EFML Test Extension', $steps[3]['install']['text'] );
		$this->assertStringContainsString( 'Loaded from the test source.', $steps[3]['install']['text'] );
		$this->assertStringContainsString( 'GPL', $steps[3]['install']['text'] );
		$this->assertArrayHasKey( 'efml_setup_not_installed', $steps[4] );

		// confirm it: it is installed and activated.
		$this->set_value( Setup::FIELD_INSTALL, 1 );
		$steps = $this->get_steps();
		$this->assertTrue( is_plugin_active( 'efml-test-extension/efml-test-extension.php' ) );
		$this->assertStringContainsString( 'efml-setup-check-success', $steps[4]['install']['text'] );

		// the steps stay, so the numbers of the steps do not change.
		$steps = $this->get_steps();
		$this->assertCount( 7, $steps );
		$this->assertStringContainsString( 'efml-setup-check-success', $steps[3]['install']['text'] );
		$this->assertArrayNotHasKey( Setup::FIELD_INSTALL, $steps[3] );

		// the service could not be set up here, as the test extension does not provide it: a hint is shown.
		$this->assertArrayHasKey( 'efml_setup_not_usable', $steps[5] );
	}

	/**
	 * Test a failing installation of an extension in the quickstart.
	 *
	 * @return void
	 */
	public function test_install_extension_failed(): void {
		// the download fails.
		wp_delete_file( Setup_Test_Source::$zip );
		Setup_Test_Source::$zip = '';

		// choose the service and confirm the installation.
		$this->set_value( Setup::FIELD_GOAL, 'offload' );
		$this->set_value( Setup::FIELD_SERVICE, 'efml-test-service' );
		$this->set_value( Setup::FIELD_INSTALL, 1 );
		$steps = $this->get_steps();

		// the error is shown, prevents to continue and is logged.
		$this->assertFalse( is_plugin_active( 'efml-test-extension/efml-test-extension.php' ) );
		$this->assertStringContainsString( 'efml-setup-check-failed', $steps[4]['install']['text'] );
		$this->assertStringContainsString( 'could not be downloaded', $steps[4]['install']['text'] );
		$this->assertArrayHasKey( 'efml_setup_install_failed', $steps[4] );
		$this->assertNotEmpty( $this->get_log_entries_with( 'EFML Test Extension could not be installed' ) );

		// a new try must be confirmed again.
		$this->assertEquals( 0, absint( \easySetupForWordPress\Setup::get_instance()->get_field_value( Setup::NAME, Setup::FIELD_INSTALL, '' ) ) );
		$this->assertArrayHasKey( Setup::FIELD_INSTALL, $steps[3] );
	}

	/**
	 * Test the quickstart for users, who could not install plugins.
	 *
	 * @return void
	 */
	public function test_without_permission(): void {
		add_filter(
			'user_has_cap',
			static function ( array $caps ): array {
				$caps['install_plugins'] = false;
				return $caps;
			}
		);

		// the service of the extension is not offered.
		$this->set_value( Setup::FIELD_GOAL, 'offload' );
		$steps = $this->get_steps();
		$this->assertNotContains( 'efml-test-service', $this->get_card_values( $steps[2][ Setup::FIELD_SERVICE ] ) );
		$this->assertStringContainsString( 'Missing your service?', $steps[2]['help']['text'] );

		// a chosen service of an extension does not lead to the installation.
		$this->set_value( Setup::FIELD_SERVICE, 'efml-test-service' );
		$this->set_value( Setup::FIELD_INSTALL, 1 );
		$this->get_steps();
		$this->assertFalse( is_plugin_active( 'efml-test-extension/efml-test-extension.php' ) );
	}

	/**
	 * Return the log entries, which contain the given text.
	 *
	 * @param string $text The text.
	 *
	 * @return array<int,mixed>
	 */
	private function get_log_entries_with( string $text ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE `log` LIKE %s', $wpdb->prefix . 'eml_logs', '%' . $wpdb->esc_like( $text ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

<?php
/**
 * Tests for the setup.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Commands;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WPAjaxDieContinueException;
use WP_REST_Request;

/**
 * Object to test the setup.
 */
class Setup extends externalFilesTests {
	/**
	 * The directory with files for the tests.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * The original URI of the request.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// use an administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// mark the requests as requests of the setup.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . \ExternalFilesInMediaLibrary\Plugin\Setup::NAME;

		// create a directory with a file.
		$this->directory = trailingslashit( wp_upload_dir()['basedir'] ) . 'efml-setup-' . wp_generate_password( 6, false ) . '/';
		wp_mkdir_p( $this->directory );
		copy( TESTS_PLUGIN_DIR . '/tests/Data/example.pdf', $this->directory . 'example.pdf' );

		// initialize the setup for this request.
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->clear_answers();
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->init_setup();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->clear_answers();
		unset( $_GET['page'], $_GET[ \ExternalFilesInMediaLibrary\Plugin\Setup::URL_PARAMETER ] );
		if ( is_null( $this->request_uri ) ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'pre_http_request', array( $this, 'answer_http_request' ), 5 );
		remove_all_filters( 'efml_setup_goals' );
		foreach ( (array) glob( $this->directory . '*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		if ( is_dir( $this->directory ) ) {
			rmdir( $this->directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		parent::tear_down();
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
		\easySetupForWordPress\Setup::get_instance()->set_field_value( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, $field_name, $value );
	}

	/**
	 * Build the steps again for the actual values and return them, like the setup requests them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_steps(): array {
		// get the request for the fields.
		$request = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		$request->set_param( 'config_name', \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );

		// run the preparation, which checks the access data and builds the steps again.
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->prepare_check( null, null, $request );

		// return the steps.
		return \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
	}

	/**
	 * Run a request of the setup library, which ends with JSON, and return the JSON.
	 *
	 * @param callable $callback The callback.
	 *
	 * @return array<string,mixed>
	 */
	private function run_json_request( callable $callback ): array {
		// stop at the end of the request instead of exit.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPAjaxDieContinueException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);

		// run the request.
		ob_start();
		try {
			$callback();
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// the request has been finished.
		}
		$output = (string) ob_get_clean();

		// return the result.
		$result = json_decode( $output, true );
		$this->assertIsArray( $result, 'No JSON returned: ' . $output );
		return $result;
	}

	/**
	 * Answer HTTP requests with "200 OK".
	 *
	 * @return array<string,mixed>
	 */
	public function answer_http_request(): array {
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Test the goals and their filter.
	 *
	 * @return void
	 */
	public function test_goals(): void {
		$goals = \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_goals();
		$this->assertEquals( array( 'offload', 'downloads', 'sync', 'local', 'ftp' ), array_keys( $goals ) );
		$this->assertEquals( 'local', $goals['local']['service'] );
		$this->assertEquals( array( 'export', 'private' ), $goals['downloads']['features'] );

		// add an own goal and an invalid one.
		add_filter(
			'efml_setup_goals',
			static function ( array $goals ): array {
				$goals['own']     = array(
					'label'    => 'Own goal',
					'features' => array( 'sync' ),
				);
				$goals['invalid'] = array( 'description' => 'Without label' );
				return $goals;
			}
		);
		$goals = \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_goals();
		$this->assertArrayHasKey( 'own', $goals );
		$this->assertArrayNotHasKey( 'invalid', $goals );
		$this->assertEquals( '', $goals['own']['service'] );
		$this->assertEquals( array(), $goals['own']['options'] );
	}

	/**
	 * Test the steps depending on the chosen goal and service.
	 *
	 * @return void
	 */
	public function test_steps(): void {
		// without a goal: goal, service, access data, options and process.
		$steps = $this->get_steps();
		$this->assertCount( 5, $steps );
		$this->assertEquals( 'CardControl', $steps[1][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL ]['type'] );
		$this->assertEquals( 'ProgressBar', $steps[5]['runSetup']['type'] );

		// the goal defines the service: no step to choose it.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'local' );
		$steps = $this->get_steps();
		$this->assertCount( 4, $steps );
		$this->assertEquals( 'TextControl', $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PATH ]['type'] );

		// the fields of FTP, the password as password field.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'ftp' );
		$steps = $this->get_steps();
		$this->assertCount( 4, $steps );
		$this->assertEquals( 'TextControl', $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__server' ]['type'] );
		$this->assertEquals( 'PasswordControl', $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__password' ]['type'] );
		$this->assertTrue( $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__password' ]['required'] );

		// a goal without a fixed service shows the services, which support its features.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'offload' );
		$steps    = $this->get_steps();
		$services = array_column( $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_SERVICE ]['options'], 'value' );
		$this->assertContains( 'local', $services );
		$this->assertContains( 'ftp', $services );
		$this->assertNotContains( 'youtube', $services );
	}

	/**
	 * Test the result of the check of the access data in the step with the options.
	 *
	 * @return void
	 */
	public function test_check(): void {
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'local' );

		// a directory, which does not exist, prevents to continue.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PATH, $this->directory . 'missing' );
		$steps = $this->get_steps();
		$this->assertStringContainsString( 'efml-setup-check-failed', $steps[3]['check']['text'] );
		$this->assertTrue( $steps[3]['efml_setup_check_failed']['required'] );
		$this->assertArrayNotHasKey( \ExternalFilesInMediaLibrary\Plugin\Setup::OPTION_PREFIX . 'real_import', $steps[3] );

		// an existing directory shows the options.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PATH, $this->directory );
		$steps = $this->get_steps();
		$this->assertStringContainsString( 'efml-setup-check-success', $steps[3]['check']['text'] );
		$this->assertArrayNotHasKey( 'efml_setup_check_failed', $steps[3] );
		$this->assertEquals( 'CheckboxControl', $steps[3][ \ExternalFilesInMediaLibrary\Plugin\Setup::OPTION_PREFIX . 'real_import' ]['type'] );
		$this->assertEquals( 0, $steps[3][ \ExternalFilesInMediaLibrary\Plugin\Setup::OPTION_PREFIX . 'real_import' ]['default'] );

		// an FTP server without details fails without connecting.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'ftp' );
		$steps = $this->get_steps();
		$this->assertStringContainsString( 'efml-setup-check-failed', $steps[3]['check']['text'] );
	}

	/**
	 * Test the handling of passwords: stored encrypted and as entered, never sent to the browser.
	 *
	 * @return void
	 */
	public function test_passwords(): void {
		// choose FTP, so the steps contain its password field.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'ftp' );
		$this->get_steps();
		$field    = \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__password';
		$password = 'Pass%41<word> ';

		// save the values like the setup does it.
		$request = new WP_REST_Request( 'POST', '/easy-setup-for-wordpress/v1/save-values/' );
		$request->set_param( 'config_name', \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		$request->set_param(
			'values',
			array(
				\ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL => 'ftp',
				\ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__login' => 'user',
				$field => $password,
			)
		);
		$result = $this->run_json_request( fn() => \easySetupForWordPress\Setup::get_instance()->save_values_by_request( $request ) );

		// the password is not sent back, but saved.
		$this->assertArrayNotHasKey( $field, $result );
		$this->assertEquals( 'user', $result[ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__login' ] );
		$this->assertTrue( \easySetupForWordPress\Setup::get_instance()->is_password_field( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, $field ) );

		// it is stored encrypted, but used as entered.
		$stored = \easySetupForWordPress\Setup::get_instance()->get_field_values_of( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		$this->assertNotEquals( $password, $stored[ $field ] );
		$this->assertStringNotContainsString( 'Pass', (string) $stored[ $field ] );
		$this->assertEquals( $password, \easySetupForWordPress\Setup::get_instance()->get_field_value( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, $field ) );

		// the values for the browser do not contain the password.
		$values = $this->run_json_request(
			function () {
				$request = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/values/' . \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
				$request->set_param( 'config_name', \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
				\easySetupForWordPress\Setup::get_instance()->get_values_by_request( $request );
			}
		);
		$this->assertArrayNotHasKey( $field, $values );
		$this->assertEquals( 'ftp', $values[ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL ] );

		// saving the step again with an empty or missing password keeps it.
		$request->set_param(
			'values',
			array(
				\ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL => 'ftp',
				$field => '',
			)
		);
		$this->run_json_request( fn() => \easySetupForWordPress\Setup::get_instance()->save_values_by_request( $request ) );
		$this->assertEquals( $password, \easySetupForWordPress\Setup::get_instance()->get_field_value( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, $field ) );
		$request->set_param( 'values', array( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL => 'ftp' ) );
		$this->run_json_request( fn() => \easySetupForWordPress\Setup::get_instance()->save_values_by_request( $request ) );
		$this->assertEquals( $password, \easySetupForWordPress\Setup::get_instance()->get_field_value( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, $field ) );

		// other values are still sanitized.
		$request->set_param(
			'values',
			array(
				\ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL => 'ftp',
				\ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__login' => '<b>user</b>',
			)
		);
		$this->run_json_request( fn() => \easySetupForWordPress\Setup::get_instance()->save_values_by_request( $request ) );
		$this->assertEquals( 'user', \easySetupForWordPress\Setup::get_instance()->get_field_value( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PREFIX . 'ftp__login' ) );
	}

	/**
	 * Test the process for the import of local files.
	 *
	 * @return void
	 */
	public function test_process_local_import(): void {
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'local' );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PATH, $this->directory );
		$this->get_steps();

		// run the process.
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->run_process( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		$messages = \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_process_messages();
		$this->assertNotEmpty( $messages );
		foreach ( $messages as $message ) {
			$this->assertTrue( $message['success'], $message['text'] );
		}

		// the external source has been created.
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::get_instance()->get_name(),
				'hide_empty' => false,
				'meta_key'   => 'type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'local', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$this->assertIsArray( $terms );
		$paths = array_map( static fn( $term ) => (string) get_term_meta( $term->term_id, 'path', true ), $terms );
		$this->assertContains( 'file://' . $this->directory, $paths );

		// the file has been imported.
		$this->assertStringContainsString( '1 file has been imported', $messages[ count( $messages ) - 1 ]['text'] );

		// the values of the setup have been removed.
		$this->assertEmpty( \easySetupForWordPress\Setup::get_instance()->get_field_values_of( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME ) );
	}

	/**
	 * Test the process for the offloading to a local directory.
	 *
	 * @return void
	 */
	public function test_process_offload(): void {
		// the URL of the exported files is reachable.
		add_filter( 'pre_http_request', array( $this, 'answer_http_request' ), 5 );
		update_option( 'eml_export', 0 );
		update_option( 'eml_export_main_source', 0 );

		// choose the goal, the service and the options.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'offload' );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_SERVICE, 'local' );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_PATH, $this->directory );
		$steps = $this->get_steps();
		$this->assertArrayHasKey( \ExternalFilesInMediaLibrary\Plugin\Setup::OPTION_PREFIX . 'export', $steps[4] );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::OPTION_PREFIX . 'export', 1 );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::OPTION_PREFIX . 'export_main', 1 );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_EXPORT_URL, trailingslashit( wp_upload_dir()['baseurl'] ) . basename( $this->directory ) . '/' );

		// run the process.
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->run_process( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );

		// the export is enabled for the new external source, which is the main export.
		$term_id = absint( get_option( 'eml_export_main_source' ) );
		$this->assertGreaterThan( 0, $term_id );
		$this->assertEquals( 1, absint( get_option( 'eml_export' ) ) );
		$this->assertNotEmpty( get_term_meta( $term_id, 'efml_export', true ) );
		$this->assertEquals( 'local', get_term_meta( $term_id, 'type', true ) );

		// the result points to the bulk action for existing files.
		$texts = implode( ' ', array_column( \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_process_messages(), 'text' ) );
		$this->assertStringContainsString( 'mode=list', $texts );
	}

	/**
	 * Test the check of URLs for exported files.
	 *
	 * @return void
	 */
	public function test_export_url_check(): void {
		$this->assertNotEquals( '', ExportDialog::get_instance()->check_url( '' ) );
		$this->assertNotEquals( '', ExportDialog::get_instance()->check_url( 'no url' ) );

		// a reachable URL on this website.
		add_filter( 'pre_http_request', array( $this, 'answer_http_request' ), 5 );
		$this->assertEquals( '', ExportDialog::get_instance()->check_url( trailingslashit( wp_upload_dir()['baseurl'] ) . 'files/' ) );
	}

	/**
	 * Test the return to the setup after connecting a service via OAuth.
	 *
	 * @return void
	 */
	public function test_oauth_return_url(): void {
		$setup = \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance();

		// without marker no return.
		$this->assertEquals( '', $setup->get_oauth_return_url( 'dropbox' ) );

		// with marker for this service the setup continues once.
		update_user_meta(
			get_current_user_id(),
			\ExternalFilesInMediaLibrary\Plugin\Setup::OAUTH_RETURN_META,
			array(
				'service' => 'dropbox',
				'time'    => time(),
			)
		);
		$this->assertEquals( '', $setup->get_oauth_return_url( 'google-drive' ) );
		$url = $setup->get_oauth_return_url( 'dropbox' );
		$this->assertStringContainsString( \ExternalFilesInMediaLibrary\Plugin\Setup::CONTINUE_PARAMETER . '=1', $url );
		$this->assertEquals( '', $setup->get_oauth_return_url( 'dropbox' ) );

		// an old marker is ignored.
		update_user_meta(
			get_current_user_id(),
			\ExternalFilesInMediaLibrary\Plugin\Setup::OAUTH_RETURN_META,
			array(
				'service' => 'dropbox',
				'time'    => time() - DAY_IN_SECONDS,
			)
		);
		$this->assertEquals( '', $setup->get_oauth_return_url( 'dropbox' ) );
	}

	/**
	 * Test the tile for the setup on the page to choose a service.
	 *
	 * @return void
	 */
	public function test_tile(): void {
		// the setup is the second tile.
		ob_start();
		Directory_Listing::get_instance()->render_view_directory_page();
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'efml-setup-tile', $output );
		preg_match_all( '/<li class="([^"]*)"/', $output, $matches );
		$this->assertEquals( 'efml-setup-tile', $matches[1][1] );

		// not for users without the capability to manage the settings.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		Directory_Listing::get_instance()->render_view_directory_page();
		$this->assertStringNotContainsString( 'efml-setup-tile', (string) ob_get_clean() );
	}

	/**
	 * Test the page of the setup.
	 *
	 * @return void
	 */
	public function test_setup_page(): void {
		set_current_screen( 'media_page_efml_local_directories' );
		$_GET['page'] = 'efml_local_directories';
		$_GET[ \ExternalFilesInMediaLibrary\Plugin\Setup::URL_PARAMETER ] = '1';
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->is_setup_page() );

		// the page shows the setup instead of the tiles.
		ob_start();
		Directory_Listing::get_instance()->render_view_directory_page();
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="easy-setup-for-wordpress"', $output );
		$this->assertStringNotContainsString( 'efml-directory-listing-services', $output );

		// opening the page starts a fresh setup.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'local' );
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->maybe_clear_answers();
		$this->assertEmpty( \easySetupForWordPress\Setup::get_instance()->get_field_values_of( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME ) );

		// but not, if it should be continued.
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'local' );
		$_GET[ \ExternalFilesInMediaLibrary\Plugin\Setup::CONTINUE_PARAMETER ] = '1';
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->maybe_clear_answers();
		unset( $_GET[ \ExternalFilesInMediaLibrary\Plugin\Setup::CONTINUE_PARAMETER ] );
		$this->assertNotEmpty( \easySetupForWordPress\Setup::get_instance()->get_field_values_of( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME ) );
		set_current_screen( 'front' );
	}

	/**
	 * Test the entry in the command palette.
	 *
	 * @return void
	 */
	public function test_command_palette(): void {
		$this->assertContains( 'setup', array_column( Commands::get_instance()->get_pages(), 'name' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertNotContains( 'setup', array_column( Commands::get_instance()->get_pages(), 'name' ) );
	}
}

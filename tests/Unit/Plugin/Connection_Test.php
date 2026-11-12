<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Connection_Test and the connection test of services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Services\Local;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WPAjaxDieContinueException;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Connection_Test.
 */
class Connection_Test extends externalFilesTests {
	/**
	 * The local directory for the tests.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->directory = trailingslashit( wp_upload_dir()['basedir'] ) . 'efml-connection-test-' . wp_generate_password( 6, false ) . '/';
		wp_mkdir_p( $this->directory );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		foreach ( (array) glob( $this->directory . '*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		rmdir( $this->directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		Local::get_instance()->set_connection_test_directory( '' );
		parent::tear_down();
	}

	/**
	 * Run the AJAX request for the connection test and return the resulting dialog.
	 *
	 * @param array<string,mixed> $request The request data.
	 *
	 * @return array<string,mixed>
	 */
	private function run_ajax( array $request ): array {
		// prepare the request.
		$request  = array_merge( array( 'nonce' => wp_create_nonce( 'efml_connection_test' ) ), $request );
		$_POST    = $request;
		$_REQUEST = $request;

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
			\ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance()->run_via_ajax();
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// the request has been finished.
		}
		$output = (string) ob_get_clean();

		// return the dialog.
		$result = json_decode( $output, true );
		$this->assertIsArray( $result, 'No JSON returned: ' . $output );
		return $result;
	}

	/**
	 * Test if the returned object is always the same and its hooks are registered.
	 *
	 * @return void
	 */
	public function test_get_instance_and_init(): void {
		$instance = \ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance();
		$this->assertSame( $instance, \ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance() );
		$this->assertIsInt( has_action( 'wp_ajax_efml_connection_test', array( $instance, 'run_via_ajax' ) ) );
		$this->assertIsInt( has_action( 'admin_enqueue_scripts', array( $instance, 'add_script' ) ) );
	}

	/**
	 * Test if the script is only loaded on the supported pages.
	 *
	 * @return void
	 */
	public function test_add_script(): void {
		\ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance()->add_script( 'index.php' );
		$this->assertFalse( wp_script_is( 'efml-connection-test' ) );

		\ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance()->add_script( 'media_page_efml_local_directories' );
		$this->assertTrue( wp_script_is( 'efml-connection-test' ) );
		$this->assertStringEndsWith( 'admin/connection-test.js', wp_scripts()->registered['efml-connection-test']->src );
		$this->assertTrue( wp_style_is( 'efml-connection-test' ) );
		$this->assertStringEndsWith( 'admin/connection-test.css', wp_styles()->registered['efml-connection-test']->src );
		wp_dequeue_script( 'efml-connection-test' );
		wp_dequeue_style( 'efml-connection-test' );
	}

	/**
	 * Test the lookup of services: only services with support for the connection test are returned.
	 *
	 * @return void
	 */
	public function test_get_service_by_name(): void {
		$instance = \ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance();
		$this->assertSame( Local::get_instance(), $instance->get_service_by_name( 'local' ) );
		$this->assertFalse( $instance->get_service_by_name( 'youtube' ) );
		$this->assertFalse( $instance->get_service_by_name( 'unknown' ) );
	}

	/**
	 * Test the complete connection test for a local directory via AJAX.
	 *
	 * @return void
	 */
	public function test_run_via_ajax_for_local_directory(): void {
		$this->do_login();

		$dialog = $this->run_ajax(
			array(
				'type'      => 'local',
				'directory' => $this->directory,
			)
		);

		$this->assertEquals( 'Connection test successful', $dialog['detail']['title'] );
		$this->assertStringContainsString( 'Upload a test file', $dialog['detail']['texts'][0] );

		// each step is shown with its state as icon and as text for screen readers.
		$this->assertStringContainsString( '<ul class="efml-connection-test-steps">', $dialog['detail']['texts'][0] );
		$this->assertSame( 3, substr_count( $dialog['detail']['texts'][0], 'class="efml-connection-test-success"' ) );
		$this->assertSame( 1, substr_count( $dialog['detail']['texts'][0], 'class="efml-connection-test-skipped"' ) );
		$this->assertStringContainsString( '<span class="screen-reader-text">Successful</span>', $dialog['detail']['texts'][0] );
		$this->assertStringContainsString( 'Skipped', $dialog['detail']['texts'][0] );
		$this->assertStringNotContainsString( 'Failed', $dialog['detail']['texts'][0] );

		// the test file has been deleted again.
		$this->assertEmpty( glob( $this->directory . '*' ) );
	}

	/**
	 * Test the connection test for a local directory, which does not exist.
	 *
	 * @return void
	 */
	public function test_run_via_ajax_with_failure(): void {
		$this->do_login();

		$dialog = $this->run_ajax(
			array(
				'type'      => 'local',
				'directory' => $this->directory . 'missing/',
			)
		);

		$this->assertEquals( 'Connection test failed', $dialog['detail']['title'] );
		$this->assertStringContainsString( 'does not exist', $dialog['detail']['texts'][0] );
		$this->assertStringContainsString( 'class="efml-connection-test-failed"', $dialog['detail']['texts'][0] );
	}

	/**
	 * Test the connection test with the directory of a saved external source.
	 *
	 * @return void
	 */
	public function test_run_via_ajax_with_external_source(): void {
		$this->do_login();
		$term    = wp_insert_term( 'Local test', \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() );
		$term_id = is_array( $term ) ? absint( $term['term_id'] ) : 0;
		update_term_meta( $term_id, 'path', $this->directory );
		update_term_meta( $term_id, 'type', 'local' );

		$dialog = $this->run_ajax(
			array(
				'type'      => 'local',
				'term_id'   => $term_id,
				'directory' => '/not/used/',
			)
		);

		$this->assertEquals( 'Connection test successful', $dialog['detail']['title'] );
	}

	/**
	 * Test the connection test without the capability, for an unknown service and for a service without support.
	 *
	 * @return void
	 */
	public function test_run_via_ajax_not_allowed(): void {
		// a user without capability.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$dialog = $this->run_ajax( array( 'type' => 'local' ) );
		$this->assertEquals( 'Connection test', $dialog['detail']['title'] );
		$this->assertStringContainsString( 'could not be run', $dialog['detail']['texts'][0] );

		// an unknown service and a service without support.
		$this->do_login();
		foreach ( array( 'unknown-service', 'youtube' ) as $type ) {
			$dialog = $this->run_ajax( array( 'type' => $type ) );
			$this->assertStringContainsString( 'could not be run', $dialog['detail']['texts'][0] );
		}
	}

	/**
	 * Test the global actions: only services with support for the connection test offer it.
	 *
	 * @return void
	 */
	public function test_global_actions(): void {
		$this->assertContains( 'Run connection test', array_column( Local::get_instance()->get_global_actions(), 'label' ) );

		$method = new \ReflectionMethod( \ExternalFilesInMediaLibrary\Services\Youtube::get_instance(), 'get_global_actions' );
		$this->assertNotContains( 'Run connection test', array_column( $method->invoke( \ExternalFilesInMediaLibrary\Services\Youtube::get_instance() ), 'label' ) );
	}
}

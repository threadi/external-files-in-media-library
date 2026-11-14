<?php
/**
 * Tests for the handling of external sources for exports, which could not be used at the moment.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionProperty;

/**
 * Object to test the export with external sources, whose service is not available (e.g. its extension is not active).
 */
class ExportSources extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * The term ID of the local external source.
	 *
	 * @var int
	 */
	private int $local_term_id = 0;

	/**
	 * The term ID of an external source, whose service is provided by a not active extension.
	 *
	 * @var int
	 */
	private int $r2_term_id = 0;

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator with the capability to export files in the backend.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = get_user_by( 'id', $user_id );
		$this->assertInstanceOf( \WP_User::class, $user );
		$user->add_cap( 'efml_cap_tools_export' );
		$user->add_cap( EFML_CAP_NAME );
		wp_set_current_user( $user_id );
		set_current_screen( 'upload' );
		update_option( 'efml_directory_listing_used', 1 );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-export-sources-' . uniqid() . '/';
		wp_mkdir_p( $this->export_directory );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );

		// make sure the local service is used as export object.
		if ( false === has_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) ) ) {
			add_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) );
		}

		// create the local external source.
		$this->local_term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $this->export_directory, array( 'path' => array( 'value' => $this->export_directory ) ) );
		$this->assertGreaterThan( 0, $this->local_term_id );
		wp_update_term( $this->local_term_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(), array( 'name' => 'Local target' ) );
		update_term_meta( $this->local_term_id, 'efml_export_url', 'https://example.com/export/' );

		// create an external source of Cloudflare R2, whose extension is not active in the tests.
		$this->r2_term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'cloudflare-r2', 'https://example.r2.cloudflarestorage.com/bucket/', array() );
		$this->assertGreaterThan( 0, $this->r2_term_id );
		wp_update_term( $this->r2_term_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(), array( 'name' => 'R2 target' ) );

		// enable the export for both, R2 as main external source.
		update_option( 'eml_export_main_source', 0 );
		Export::get_instance()->set_state_for_term( $this->r2_term_id, 1 );
		Export::get_instance()->set_state_for_term( $this->local_term_id, 1 );
		$this->assertEquals( $this->r2_term_id, absint( get_option( 'eml_export_main_source' ) ) );

		// enable the export.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 0 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efml_export_source_usable' );
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		Transients::get_instance()->get_transient_by_name( 'efml_export_sources_problem' )->delete();
		$this->set_row_action_sources( null );
		$this->set_services_initialized( true );
		parent::tear_down();
	}

	/**
	 * Return the export directory.
	 *
	 * @return string
	 */
	public function get_export_directory(): string {
		return $this->export_directory;
	}

	/**
	 * Set the cached external sources of the row actions.
	 *
	 * @param array<string,string>|null $value The value.
	 *
	 * @return void
	 */
	private function set_row_action_sources( ?array $value ): void {
		$property = new ReflectionProperty( Export::class, 'row_action_sources' );
		$property->setAccessible( true );
		$property->setValue( Export::get_instance(), $value );
	}

	/**
	 * Set the marker for initialized services.
	 *
	 * @param bool $value The value.
	 *
	 * @return void
	 */
	private function set_services_initialized( bool $value ): void {
		$property = new ReflectionProperty( Services::class, 'initialized' );
		$property->setAccessible( true );
		$property->setValue( Services::get_instance(), $value );
	}

	/**
	 * Return the export actions for a new local file in the media library.
	 *
	 * @return array<string,string>
	 */
	private function get_row_actions(): array {
		$this->set_row_action_sources( null );
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$post          = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $post );
		return Export::get_instance()->change_media_row_actions( array(), $post );
	}

	/**
	 * Test the check of a single external source.
	 *
	 * @return void
	 */
	public function test_source_problem(): void {
		$export_obj = Export::get_instance();

		// the local source could be used.
		$this->assertTrue( Services::get_instance()->is_initialized() );
		$this->assertEquals( '', $export_obj->get_export_source_problem( $this->local_term_id ) );
		$this->assertTrue( $export_obj->is_export_source_usable( $this->local_term_id ) );

		// the R2 source not, the reason names the service and the extension.
		$problem = $export_obj->get_export_source_problem( $this->r2_term_id );
		$this->assertStringContainsString( 'Cloudflare R2', $problem );
		$this->assertStringContainsString( 'External Files from AWS S3-compatible platforms', $problem );
		$this->assertFalse( $export_obj->is_export_source_usable( $this->r2_term_id ) );
		$this->assertEquals( array( $this->local_term_id ), $export_obj->get_usable_export_terms() );

		// unknown services are named by their type.
		update_term_meta( $this->r2_term_id, 'type', 'unknown-service' );
		$this->assertStringContainsString( 'unknown-service', $export_obj->get_export_source_problem( $this->r2_term_id ) );

		// the result could be changed via hook.
		add_filter( 'efml_export_source_usable', '__return_true' );
		$this->assertTrue( $export_obj->is_export_source_usable( $this->r2_term_id ) );
	}

	/**
	 * Test that nothing is checked for the service, before the services are initialized (as before 6.0.0).
	 *
	 * @return void
	 */
	public function test_before_services_are_initialized(): void {
		$this->set_services_initialized( false );
		$this->assertEquals( '', Export::get_instance()->get_export_source_problem( $this->r2_term_id ) );
		$this->assertEquals( '', Export::get_instance()->get_export_sources_problem() );
	}

	/**
	 * Test that files are not exported, if the main external source is not usable (also not to the other sources).
	 *
	 * @return void
	 */
	public function test_no_export_without_main_source(): void {
		$export_obj = Export::get_instance();

		// the reason names the main external source.
		$problem = $export_obj->get_export_sources_problem();
		$this->assertStringContainsString( 'R2 target', $problem );
		$this->assertEquals( $problem, $export_obj->get_export_run_problem() );

		// the file is not exported and stays local.
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$this->assertFalse( $export_obj->export_file( $attachment_id ) );
		$this->assertFileDoesNotExist( $this->export_directory . 'example.pdf' );
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );

		// the settings are not changed.
		$this->assertEquals( $this->r2_term_id, absint( get_option( 'eml_export_main_source' ) ) );
		$this->assertEquals( $this->r2_term_id, $export_obj->get_main_export_source() );
		$this->assertNotEmpty( get_term_meta( $this->r2_term_id, 'efml_export', true ) );

		// as soon as the service is available again, the file is exported.
		add_filter( 'efml_export_source_usable', '__return_true' );
		$this->assertEquals( '', $export_obj->get_export_sources_problem() );
	}

	/**
	 * Test that files are exported to the usable external sources, if no main external source is set.
	 *
	 * @return void
	 */
	public function test_export_without_main_source_setting(): void {
		update_option( 'eml_export_main_source', 0 );
		$export_obj = Export::get_instance();
		$this->assertEquals( 0, $export_obj->get_main_export_source() );
		$this->assertEquals( '', $export_obj->get_export_sources_problem() );

		// the file is exported to the local external source.
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$this->assertTrue( $export_obj->export_file( $attachment_id ) );
		$this->assertFileExists( $this->export_directory . basename( (string) get_attached_file( $attachment_id ) ) );
	}

	/**
	 * Test that no external source is usable, if all services are missing.
	 *
	 * @return void
	 */
	public function test_without_usable_sources(): void {
		Export::get_instance()->set_state_for_term( $this->local_term_id, 0 );
		update_option( 'eml_export_main_source', 0 );
		$problem = Export::get_instance()->get_export_sources_problem();
		$this->assertStringContainsString( 'R2 target', $problem );
		$this->assertStringContainsString( 'Cloudflare R2', $problem );

		// without enabled external sources.
		Export::get_instance()->set_state_for_term( $this->r2_term_id, 0 );
		$this->assertEquals( 'No external source is enabled for the export.', Export::get_instance()->get_export_sources_problem() );
	}

	/**
	 * Test the actions in the media library.
	 *
	 * @return void
	 */
	public function test_media_library_actions(): void {
		$export_obj = Export::get_instance();

		// no actions while the main external source is not usable.
		$this->assertArrayNotHasKey( 'eml-export-file', $this->get_row_actions() );
		$this->assertArrayNotHasKey( 'efml-export', $export_obj->add_bulk_action( array() ) );

		// with a usable main external source the dialog names it.
		update_option( 'eml_export_main_source', $this->local_term_id );
		$actions = $this->get_row_actions();
		$this->assertArrayHasKey( 'eml-export-file', $actions );
		$this->assertStringContainsString( 'Local target', $actions['eml-export-file'] );
		$this->assertStringNotContainsString( 'R2 target', $actions['eml-export-file'] );
		$this->assertArrayHasKey( 'efml-export', $export_obj->add_bulk_action( array() ) );
	}

	/**
	 * Test that the dialog names the main external source, also if it is not the last one in the list.
	 *
	 * @return void
	 */
	public function test_dialog_names_main_source(): void {
		// add a second local external source, which is sorted behind the first one.
		$second_directory = $this->export_directory . 'second/';
		wp_mkdir_p( $second_directory );
		$second_term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $second_directory, array( 'path' => array( 'value' => $second_directory ) ) );
		wp_update_term( $second_term_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(), array( 'name' => 'Zulu target' ) );
		Export::get_instance()->set_state_for_term( $this->r2_term_id, 0 );
		Export::get_instance()->set_state_for_term( $second_term_id, 1 );
		update_option( 'eml_export_main_source', $this->local_term_id );

		// the dialog names the main external source.
		$actions = $this->get_row_actions();
		$this->assertArrayHasKey( 'eml-export-file', $actions );
		$this->assertStringContainsString( 'Local target', $actions['eml-export-file'] );
		$this->assertStringNotContainsString( 'Zulu target', $actions['eml-export-file'] );
	}

	/**
	 * Test that the settings show all external sources with enabled export and mark the not usable ones.
	 *
	 * @return void
	 */
	public function test_settings_list(): void {
		$method = new \ReflectionMethod( Export::class, 'get_external_sources_as_name_list' );
		$method->setAccessible( true );

		// the settings get all, the main external source last.
		$all = $method->invoke( Export::get_instance(), false );
		$this->assertEquals( array( (string) $this->local_term_id, (string) $this->r2_term_id ), array_keys( $all ) );
		$this->assertStringContainsString( 'R2 target', $all[ (string) $this->r2_term_id ] );
		$this->assertNotEquals( 'R2 target', $all[ (string) $this->r2_term_id ] );
		$this->assertEquals( 'Local target', $all[ (string) $this->local_term_id ] );

		// the others only the usable ones.
		$this->assertEquals( array( (string) $this->local_term_id => 'Local target' ), $method->invoke( Export::get_instance(), true ) );
	}

	/**
	 * Test the hint in the backend.
	 *
	 * @return void
	 */
	public function test_hint(): void {
		$export_obj = Export::get_instance();

		// the hint is shown while the main external source is not usable.
		$export_obj->add_hint_for_unusable_sources();
		$transient_obj = Transients::get_instance()->get_transient_by_name( 'efml_export_sources_problem' );
		$this->assertTrue( $transient_obj->is_set() );
		$this->assertStringContainsString( 'Cloudflare R2', $transient_obj->get_message() );
		$this->assertStringContainsString( 'plugins.php', $transient_obj->get_message() );

		// the hint is removed, as soon as the files could be exported again.
		add_filter( 'efml_export_source_usable', '__return_true' );
		$export_obj->add_hint_for_unusable_sources();
		$this->assertFalse( Transients::get_instance()->get_transient_by_name( 'efml_export_sources_problem' )->is_set() );
	}

	/**
	 * Test the export of new uploads, while the main external source is not usable.
	 *
	 * @return void
	 */
	public function test_upload_stays_local(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$metadata      = array( 'file' => 'example.pdf' );
		$this->assertEquals( $metadata, Export::get_instance()->export_file_by_upload( $metadata, $attachment_id ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_export_queued', true ) );
	}
}

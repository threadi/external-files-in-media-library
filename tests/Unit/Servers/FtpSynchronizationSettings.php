<?php
/**
 * Tests for the settings of synchronized external sources with a real FTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test schedules, the deletion of synchronized files and the protection against their deletion.
 */
class FtpSynchronizationSettings extends externalFilesTests {
	/**
	 * The data of the used server.
	 *
	 * @var array<string,mixed>
	 */
	private array $server = array();

	/**
	 * The URL of the synchronized directory.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * The term ID of the external source.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// use the hooks of the synchronization (they are only added during init if the synchronization is enabled).
		update_option( 'eml_sync', 1 );
		add_filter( 'pre_delete_attachment', array( Synchronization::get_instance(), 'prevent_deletion' ), 10, 2 );
		add_action( 'pre_delete_term', array( Synchronization::get_instance(), 'delete_synced_files_on_term_deletion' ), 10, 2 );
		add_action( 'pre_delete_term', array( Synchronization::get_instance(), 'delete_schedule' ), 10, 2 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_delete_attachment', array( Synchronization::get_instance(), 'prevent_deletion' ) );
		remove_action( 'pre_delete_term', array( Synchronization::get_instance(), 'delete_synced_files_on_term_deletion' ) );
		remove_action( 'pre_delete_term', array( Synchronization::get_instance(), 'delete_schedule' ) );
		update_option( 'eml_sync', 0 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );

		// remove the schedule, if one has been created.
		if ( $this->term_id > 0 ) {
			$schedule = Synchronization::get_instance()->get_schedule_by_term_id( $this->term_id );
			if ( $schedule ) {
				$schedule->delete();
			}
		}
		parent::tear_down();
	}

	/**
	 * Create the external source and synchronize it once.
	 *
	 * @param bool $sync True to synchronize it now.
	 *
	 * @return void
	 */
	private function create_source( bool $sync = true ): void {
		$this->server  = TestServers::get( 'ftp', $this );
		$this->url     = TestServers::get_url( $this->server, TestServers::create_directory( $this->server ) );
		$this->term_id = Taxonomy::get_instance()->add( 'ftp', $this->url, TestServers::get_fields( $this->server ) );
		$this->assertGreaterThan( 0, $this->term_id );

		if ( $sync ) {
			$this->sync();
			$this->assertInstanceOf( File::class, $this->get_file( 'example-red.jpg' ) );
		}
	}

	/**
	 * Synchronize the external source.
	 *
	 * @return void
	 */
	private function sync(): void {
		@Synchronization::get_instance()->sync( $this->url, Taxonomy::get_instance()->get_entry( $this->term_id ), $this->term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Return the external file object of the given file name in the synchronized directory.
	 *
	 * @param string $filename The file name.
	 *
	 * @return File|false
	 */
	private function get_file( string $filename ): File|false {
		return Files::get_instance()->get_file_by_url( $this->url . $filename );
	}

	/**
	 * Test that enabling the synchronization creates a schedule, which synchronizes the source.
	 *
	 * @return void
	 */
	public function test_schedule_synchronizes_source(): void {
		$this->create_source( false );

		// enable the synchronization: this creates the schedule.
		Synchronization::get_instance()->set_state( $this->term_id, 1 );
		$schedule = Synchronization::get_instance()->get_schedule_by_term_id( $this->term_id );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Plugin\Schedules\Synchronization::class, $schedule );
		$this->assertContains( $this->term_id, Synchronization::get_instance()->get_sync_terms() );

		// run it like cron does.
		@$schedule->run(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertInstanceOf( File::class, $this->get_file( 'example-red.jpg' ) );
		$this->assertInstanceOf( File::class, $this->get_file( 'example-blue.png' ) );

		// disabling the synchronization removes the schedule.
		Synchronization::get_instance()->set_state( $this->term_id, 0 );
		$this->assertFalse( Synchronization::get_instance()->get_schedule_by_term_id( $this->term_id ) );
		$this->assertNotContains( $this->term_id, Synchronization::get_instance()->get_sync_terms() );
	}

	/**
	 * Test that deleting the external source deletes its schedule.
	 *
	 * @return void
	 */
	public function test_deleting_source_deletes_schedule(): void {
		$this->create_source( false );
		Synchronization::get_instance()->set_state( $this->term_id, 1 );
		$this->assertNotFalse( Synchronization::get_instance()->get_schedule_by_term_id( $this->term_id ) );

		wp_delete_term( $this->term_id, Taxonomy::get_instance()->get_name() );
		$this->assertFalse( Synchronization::get_instance()->get_schedule_by_term_id( $this->term_id ) );
	}

	/**
	 * Test that deleting the external source deletes its synchronized files, if this is enabled.
	 *
	 * @return void
	 */
	public function test_deleting_source_deletes_synchronized_files_if_enabled(): void {
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
		$this->create_source();
		$attachment_id = $this->get_file( 'example-red.jpg' )->get_id();

		@wp_delete_term( $this->term_id, Taxonomy::get_instance()->get_name() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFalse( $this->get_file( 'example-red.jpg' ) );

		// the files on the server are not touched.
		$this->assertFileExists( $this->server['root'] . wp_parse_url( $this->url, PHP_URL_PATH ) . 'example-red.jpg' );
	}

	/**
	 * Test that deleting the external source keeps its synchronized files, if this is disabled.
	 *
	 * @return void
	 */
	public function test_deleting_source_keeps_synchronized_files_if_disabled(): void {
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );
		$this->create_source();
		$attachment_id = $this->get_file( 'example-red.jpg' )->get_id();

		wp_delete_term( $this->term_id, Taxonomy::get_instance()->get_name() );
		$this->assertInstanceOf( \WP_Post::class, get_post( $attachment_id ) );
	}

	/**
	 * Test that synchronized files can not be deleted in the media library, if this is enabled.
	 *
	 * @return void
	 */
	public function test_synchronized_files_are_protected_against_deletion(): void {
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
		$this->create_source();
		$attachment_id = $this->get_file( 'example-red.jpg' )->get_id();

		// the deletion is prevented.
		$this->assertFalse( wp_delete_attachment( $attachment_id, true ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $attachment_id ) );

		// the delete action is removed from the media library.
		$actions = Synchronization::get_instance()->remove_delete_action( array( 'delete' => 'Delete', 'edit' => 'Edit' ), get_post( $attachment_id ) );
		$this->assertArrayNotHasKey( 'delete', $actions );
		$this->assertArrayHasKey( 'edit', $actions );

		// other files can be deleted.
		$other_id = self::factory()->attachment->create_object( array( 'file' => 'other.jpg', 'post_mime_type' => 'image/jpeg' ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $other_id, true ) );
	}

	/**
	 * Test that synchronized files can be deleted in the media library, if the protection is disabled.
	 *
	 * @return void
	 */
	public function test_synchronized_files_can_be_deleted_if_protection_disabled(): void {
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );
		$this->create_source();
		$attachment_id = $this->get_file( 'example-red.jpg' )->get_id();

		$this->assertInstanceOf( \WP_Post::class, @wp_delete_attachment( $attachment_id, true ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * Test that a synchronization without changes on the server keeps all files and their IDs.
	 *
	 * @return void
	 */
	public function test_repeated_synchronization_keeps_files(): void {
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		$this->create_source();
		$ids = array();
		foreach ( array( 'example-red.jpg', 'example-green.jpg', 'example-blue.png' ) as $filename ) {
			$ids[ $filename ] = $this->get_file( $filename )->get_id();
		}

		// synchronize twice more.
		$this->sync();
		$this->sync();
		foreach ( $ids as $filename => $id ) {
			$this->assertInstanceOf( File::class, $this->get_file( $filename ), $filename );
			$this->assertSame( $id, $this->get_file( $filename )->get_id(), $filename );
		}
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
	}
}

<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Revert.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use Exception;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Revert.
 */
class Revert extends externalFilesTests {
	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// stop each redirect with an exception instead of exit.
		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		unset( $_REQUEST['nonce'] );
		parent::tear_down();
	}

	/**
	 * Stop the redirect.
	 *
	 * @param string $location The target.
	 *
	 * @return string
	 * @throws Exception To stop the request.
	 */
	public function stop_redirect( string $location ): string {
		throw new Exception( 'redirect' );
	}

	/**
	 * Create an attachment with the given job ID.
	 *
	 * @param string $job_id The job ID.
	 *
	 * @return int
	 */
	private function create_attachment( string $job_id ): int {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'application/pdf' ) );
		update_post_meta( $attachment_id, 'eml_job_id', $job_id );
		return $attachment_id;
	}

	/**
	 * Run the revert as the given user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return void
	 */
	private function run_revert( int $user_id ): void {
		wp_set_current_user( $user_id );
		$_REQUEST['nonce'] = wp_create_nonce( 'efml-revert' );

		try {
			\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Revert::get_instance()->revert_by_request();
		} catch ( Exception $e ) {
			$this->assertEquals( 'redirect', $e->getMessage() );
		}
	}

	/**
	 * Test if only the files of the last job of the user are deleted.
	 *
	 * @return void
	 */
	public function test_revert_deletes_only_files_of_last_job(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_user_meta( $user_id, 'efml_last_job_id', 'job-1' );

		$file_of_job   = $this->create_attachment( 'job-1' );
		$file_of_other = $this->create_attachment( 'job-2' );
		$file_without  = $this->create_attachment( '' );

		$this->run_revert( $user_id );

		$this->assertNull( get_post( $file_of_job ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $file_of_other ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $file_without ) );
	}

	/**
	 * Test if nothing is deleted, if the user does not have a last job.
	 *
	 * @return void
	 */
	public function test_revert_without_job_deletes_nothing(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$file_without = $this->create_attachment( '' );

		$this->run_revert( $user_id );

		$this->assertInstanceOf( \WP_Post::class, get_post( $file_without ) );
	}

	/**
	 * Test if a user without the capability could not revert anything.
	 *
	 * @return void
	 */
	public function test_revert_requires_capability(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $user_id, 'efml_last_job_id', 'job-1' );

		$file_of_job = $this->create_attachment( 'job-1' );

		$this->run_revert( $user_id );

		$this->assertInstanceOf( \WP_Post::class, get_post( $file_of_job ) );
	}

	/**
	 * Test if the revert button is only added to the dialog, if the revert is enabled.
	 *
	 * @return void
	 */
	public function test_change_dialog_depends_on_setting(): void {
		$dialog = array( 'buttons' => array() );

		update_option( 'eml_revert', 0 );
		$this->assertSame( $dialog, \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Revert::get_instance()->change_dialog( $dialog ) );

		update_option( 'eml_revert', 1 );
		$this->assertNotSame( $dialog, \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Revert::get_instance()->change_dialog( $dialog ) );
	}
}

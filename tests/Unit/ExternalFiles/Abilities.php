<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Abilities.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Abilities.
 */
class Abilities extends externalFilesTests {
	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// the abilities are only available with the Abilities API (WordPress 6.9 or newer).
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API is not available in this WordPress version.' );
		}

		// run all tests as administrator.
		wp_set_current_user( 1 );
	}

	/**
	 * Run an ability of this plugin.
	 *
	 * @param string $name The name without the plugin prefix.
	 * @param mixed  $input The input.
	 *
	 * @return mixed
	 */
	private function run_ability( string $name, mixed $input = null ): mixed {
		$ability = wp_get_ability( \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::PREFIX . $name );
		$this->assertInstanceOf( \WP_Ability::class, $ability, $name );
		return $ability->execute( $input );
	}

	/**
	 * Test if all abilities are registered in our own category.
	 *
	 * @return void
	 */
	public function test_abilities_are_registered(): void {
		$abilities = \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance()->get_abilities();
		$this->assertNotEmpty( $abilities );

		foreach ( array_keys( $abilities ) as $name ) {
			$ability = wp_get_ability( \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::PREFIX . $name );
			$this->assertInstanceOf( \WP_Ability::class, $ability, $name );
			$this->assertSame( \ExternalFilesInMediaLibrary\ExternalFiles\Files::ABILITY_CATEGORY, $ability->get_category(), $name );
		}
	}

	/**
	 * Test if only the read-only abilities are provided in the mode "read only".
	 *
	 * @return void
	 */
	public function test_read_only_mode_provides_only_read_only_abilities(): void {
		add_filter( 'efml_abilities_mode', static fn() => 'read', 20 );

		$abilities_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance();
		$this->assertTrue( $abilities_obj->is_read_only_mode() );

		$abilities = $abilities_obj->get_active_abilities();
		$this->assertNotEmpty( $abilities );
		$this->assertLessThan( count( $abilities_obj->get_abilities() ), count( $abilities ) );
		foreach ( $abilities as $name => $ability ) {
			$this->assertTrue( $abilities_obj->is_read_only_ability( $ability ), $name );
		}
		$this->assertArrayHasKey( 'get-file', $abilities );
		$this->assertArrayNotHasKey( 'add', $abilities );
		$this->assertArrayNotHasKey( 'delete', $abilities );
	}

	/**
	 * Test if all abilities are provided in the mode "all".
	 *
	 * @return void
	 */
	public function test_mode_all_provides_all_abilities(): void {
		add_filter( 'efml_abilities_mode', static fn() => 'all', 20 );

		$abilities_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance();
		$this->assertSame( array_keys( $abilities_obj->get_abilities() ), array_keys( $abilities_obj->get_active_abilities() ) );
	}

	/**
	 * Test if an unknown mode is handled as "read only".
	 *
	 * @return void
	 */
	public function test_unknown_mode_is_read_only(): void {
		add_filter( 'efml_abilities_mode', static fn() => 'unknown', 20 );

		$this->assertSame( 'read', \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance()->get_mode() );
	}

	/**
	 * Test if no abilities are provided if they are disabled.
	 *
	 * @return void
	 */
	public function test_no_abilities_if_disabled(): void {
		update_option( 'efml_abilities_enabled', 0 );

		$abilities_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance();
		$this->assertFalse( $abilities_obj->is_enabled() );
		$this->assertEmpty( $abilities_obj->get_active_abilities() );

		update_option( 'efml_abilities_enabled', 1 );
		$this->assertTrue( $abilities_obj->is_enabled() );
	}

	/**
	 * Test if a URL can be added with the URL as only parameter and if this is idempotent.
	 *
	 * @return void
	 */
	public function test_add_with_url_only(): void {
		$attachment_id = $this->run_ability( 'add', array( 'url' => self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertIsInt( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );

		// the same URL returns the same attachment.
		$this->assertSame( $attachment_id, $this->run_ability( 'add', array( 'url' => self::get_test_file( 'pdf', 'http' ) ) ) );

		// a login without a password must not result in an error.
		$this->assertSame(
			$attachment_id,
			$this->run_ability(
				'add',
				array(
					'url'   => self::get_test_file( 'pdf', 'http' ),
					'login' => 'example',
				)
			)
		);
	}

	/**
	 * Test if a not usable URL results in an error instead of 0.
	 *
	 * @return void
	 */
	public function test_add_returns_error_for_faulty_url(): void {
		$result = $this->run_ability( 'add', array( 'url' => self::get_faulty_test_file( 'pdf', 'http' ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'efml_import_failed', $result->get_error_code() );

		// the URL is required.
		$this->assertWPError( $this->run_ability( 'add', array() ) );
	}

	/**
	 * Test to get the data of a file, to list it and to delete it.
	 *
	 * @return void
	 */
	public function test_get_list_and_delete_file(): void {
		$url           = self::get_test_file( 'pdf', 'http' );
		$attachment_id = $this->run_ability( 'add', array( 'url' => $url ) );
		$this->assertIsInt( $attachment_id );

		// get the file by its URL and by its ID.
		$file = $this->run_ability( 'get-file', array( 'url' => $url ) );
		$this->assertIsArray( $file );
		$this->assertSame( $attachment_id, $file['attachment_id'] );
		$this->assertSame( $url, $file['url'] );
		$this->assertSame( 'application/pdf', $file['mime_type'] );
		$this->assertSame( 'external', $file['hosting'] );
		$this->assertSame( $file, $this->run_ability( 'get-file', array( 'attachment_id' => $attachment_id ) ) );

		// the file is part of the list.
		$list = $this->run_ability( 'list-files', array( 'mime_type' => 'application/pdf' ) );
		$this->assertIsArray( $list );
		$this->assertContains( $attachment_id, array_column( $list['files'], 'attachment_id' ) );
		$this->assertGreaterThan( 0, $list['total'] );

		// the list could be requested without any input.
		$this->assertIsArray( $this->run_ability( 'list-files' ) );

		// the statistics count it.
		$statistics = $this->run_ability( 'get-statistics' );
		$this->assertIsArray( $statistics );
		$this->assertGreaterThan( 0, $statistics['files']['total'] );

		// delete it.
		$this->assertTrue( $this->run_ability( 'delete', array( 'url' => $url ) ) );
		$this->assertNull( get_post( $attachment_id ) );

		// it does not exist anymore.
		$this->assertWPError( $this->run_ability( 'get-file', array( 'attachment_id' => $attachment_id ) ) );
		$this->assertWPError( $this->run_ability( 'delete', array( 'url' => $url ) ) );

		// a file is required.
		$this->assertWPError( $this->run_ability( 'delete', array() ) );
	}

	/**
	 * Test the import of multiple URLs with the reason for each failed URL.
	 *
	 * @return void
	 */
	public function test_import_returns_files_and_errors(): void {
		$faulty_url = self::get_faulty_test_file( 'pdf', 'http' );
		$result     = $this->run_ability(
			'import',
			array(
				'urls' => array( self::get_test_file( 'pdf', 'http' ), $faulty_url, 'not a url' ),
			)
		);
		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['imported_count'] );
		$this->assertSame( self::get_test_file( 'pdf', 'http' ), $result['imported'][0]['url'] );
		$this->assertSame( 0, $result['queued'] );
		$this->assertContains( $faulty_url, array_column( $result['errors'], 'url' ) );
		$this->assertCount( 2, array_unique( array_column( $result['errors'], 'url' ) ) );
	}

	/**
	 * Test to add URLs to the queue.
	 *
	 * @return void
	 */
	public function test_queue(): void {
		$url    = self::get_test_file( 'pdf', 'http' );
		$result = $this->run_ability( 'queue-add', array( 'urls' => array( $url ) ) );
		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['queued'] );
		$this->assertSame( 0, $result['imported_count'] );

		// the URL is not imported, but in the queue.
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $url ) );
		$status = $this->run_ability( 'queue-status' );
		$this->assertIsArray( $status );
		$this->assertSame( 1, $status['new'] );
		$this->assertSame( $url, $status['entries'][0]['url'] );

		// the option for the queue is removed from the request after the ability has been run.
		$this->assertArrayNotHasKey( 'add_to_queue', $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// the same URL is not added twice.
		$result = $this->run_ability( 'queue-add', array( 'urls' => array( $url ) ) );
		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['queued'] );
		$this->assertContains( $url, array_column( $result['errors'], 'url' ) );

		// the cleanup removes only failed URLs.
		$cleanup = $this->run_ability( 'queue-cleanup' );
		$this->assertIsArray( $cleanup );
		$this->assertSame( 0, $cleanup['removed'] );
		$this->assertSame( 1, $cleanup['remaining'] );
	}

	/**
	 * Test if the saved credentials of an external source are never returned.
	 *
	 * @return void
	 */
	public function test_sources_do_not_return_credentials(): void {
		$password = 'efml-secret-password';
		$term_id  = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add(
			'ftp',
			'ftp://login:' . $password . '@example.com/files/',
			array(
				'server'   => array( 'value' => 'ftp://example.com' ),
				'login'    => array( 'value' => 'login' ),
				'password' => array( 'value' => $password ),
			)
		);
		$this->assertGreaterThan( 0, $term_id );

		// the external source is in the list.
		$sources = $this->run_ability( 'list-sources' );
		$this->assertIsArray( $sources );
		$this->assertContains( $term_id, array_column( $sources['sources'], 'id' ) );

		// but its credentials are not in any response.
		$this->assertStringNotContainsString( $password, (string) wp_json_encode( $sources ) );
		$this->assertStringNotContainsString( $password, (string) wp_json_encode( $this->run_ability( 'get-sync-status' ) ) );
		$this->assertStringNotContainsString( $password, (string) wp_json_encode( $this->run_ability( 'list-services' ) ) );

		// an unknown external source results in an error.
		$this->assertWPError( $this->run_ability( 'list-directory', array( 'source_id' => $term_id + 1000 ) ) );
	}

	/**
	 * Test if the abilities are not usable without the necessary capability.
	 *
	 * @return void
	 */
	public function test_abilities_require_capability(): void {
		wp_set_current_user( 0 );

		foreach ( array_keys( \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance()->get_abilities() ) as $name ) {
			$ability = wp_get_ability( \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::PREFIX . $name );
			$this->assertInstanceOf( \WP_Ability::class, $ability, $name );
			$this->assertNotTrue( $ability->check_permissions( array() ), $name );
		}

		// nothing is added without permission.
		$count = $this->get_media_file_count();
		$this->assertWPError( $this->run_ability( 'add', array( 'url' => self::get_test_file( 'pdf', 'http' ) ) ) );
		$this->assertSame( $count, $this->get_media_file_count() );
	}

	/**
	 * Test if the directory of an external source is only added to values, which do not contain it,
	 * and if values, which are no real URLs (e.g., from DropBox), are not changed.
	 *
	 * @return void
	 */
	public function test_urls_are_completed_with_directory_of_source(): void {
		$method = new \ReflectionMethod( \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::class, 'get_urls_for_source' );
		$abilities_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Abilities::get_instance();

		// a source like DropBox, whose files are no real URLs and could contain spaces.
		$this->assertSame(
			array( 'DropBox/erste schritte.pdf', 'DropBox/erste schritte.pdf', 'dropbox/Erste Schritte.pdf', 'DropBox/folder/file.pdf', 'DropBox/' ),
			$method->invoke( $abilities_obj, array( 'DropBox/erste schritte.pdf', 'erste schritte.pdf', 'dropbox/Erste Schritte.pdf', '/folder/file.pdf', 'DropBox/' ), 'DropBox/' )
		);

		// a source with a URL as directory.
		$this->assertSame(
			array( 'ftp://example.com/files/a.pdf', 'ftp://example.com/files/sub/b.pdf', 'ftp://example.com/other/c.pdf', 'https://example.org/d.pdf', 'ftp://example.com/files/ftp-e.pdf' ),
			$method->invoke( $abilities_obj, array( 'ftp://example.com/files/a.pdf', 'sub/b.pdf', '/other/c.pdf', 'https://example.org/d.pdf', 'ftp-e.pdf' ), 'ftp://example.com/files/' )
		);
	}

	/**
	 * Test the import of a file with an external source: with the URL as it is returned by the
	 * directory listing and with a relative path.
	 *
	 * @return void
	 */
	public function test_import_with_source(): void {
		add_filter( 'efml_file_base', array( $this, 'set_plugin_path_as_base' ) );

		// add the directory of our test files as external source.
		$url       = self::get_test_file( 'pdf', 'file' );
		$directory = trailingslashit( dirname( $url ) );
		$term_id   = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', $directory, array() );
		$this->assertGreaterThan( 0, $term_id );

		// import the file with its complete URL, the directory must not be added again.
		$result = $this->run_ability(
			'import',
			array(
				'source_id' => $term_id,
				'urls'      => array( $url ),
			)
		);
		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['imported_count'] );
		$this->assertSame( $url, $result['imported'][0]['url'] );
		$this->assertSame( $term_id, $result['imported'][0]['source_id'] );

		// the relative path results in the same URL, which already exists.
		$result = $this->run_ability(
			'import',
			array(
				'source_id' => $term_id,
				'urls'      => array( basename( $url ) ),
			)
		);
		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['imported_count'] );
		$this->assertContains( $url, array_column( $result['errors'], 'url' ) );

		remove_filter( 'efml_file_base', array( $this, 'set_plugin_path_as_base' ) );
	}

	/**
	 * Test if the services are initialized for the actual user before an ability is executed. Without this
	 * the protocols of services like DropBox or ZIP are missing, if the user was not known during the
	 * initialization of the plugin (e.g., if the ability is called via an MCP server).
	 *
	 * @return void
	 */
	public function test_services_are_initialized_for_the_user(): void {
		$protocol_name = 'ExternalFilesInMediaLibrary\Services\Zip\Protocol';

		// simulate that the services have been initialized without a known user.
		remove_filter( 'efml_protocols', array( \ExternalFilesInMediaLibrary\Services\Zip::get_instance(), 'add_protocol' ) );
		$this->assertNotContains( $protocol_name, \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocols() );

		// run an ability as another user, which is allowed to use the service.
		$user_id = wp_insert_user(
			array(
				'user_login' => 'efml-abilities-test',
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $user_id );
		wp_set_current_user( $user_id );
		$this->assertIsArray( $this->run_ability( 'get-statistics' ) );

		// the protocol of the service is available now.
		$this->assertContains( $protocol_name, \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocols() );
	}
}

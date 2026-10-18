<?php
/**
 * Tests for the security checks of all AJAX handlers of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test that each AJAX handler checks the nonce and the capabilities before doing anything.
 *
 * The handlers read their data via filter_input( INPUT_POST, ... ), which PHPUnit can not set,
 * so only these checks at the beginning of each handler can be tested here.
 */
class AjaxSecurity extends externalFilesTests {
	/**
	 * Return the list of all AJAX handlers with the action of their nonce.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function get_handlers(): array {
		return array(
			'export config dialog'      => array( 'ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog', 'get_dialog', 'efml-export-config-nonce' ),
			'export config save'        => array( 'ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog', 'save_via_ajax', 'efml-export-save-config-nonce' ),
			'sync'                      => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Synchronization', 'sync_via_ajax', 'efml-sync-nonce' ),
			'sync info'                 => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Synchronization', 'sync_info', 'efml-sync-info-nonce' ),
			'delete synced files'       => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Synchronization', 'delete_synced_via_ajax', 'efml-deleted-synced-nonce' ),
			'delete synced files info'  => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Synchronization', 'delete_synced_info', 'efml-deleted-synced-info-nonce' ),
			'sync state'                => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Synchronization', 'sync_state_change_via_ajax', 'efml-sync-state-nonce' ),
			'switch hosting'            => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Files', 'switch_hosting_via_ajax', 'eml-switch-hosting-nonce' ),
			'export state'              => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Export', 'export_state_change_via_ajax', 'efml-export-state-nonce' ),
			'import urls'               => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Forms', 'add_urls_by_ajax', 'eml-urls-upload-nonce' ),
			'import info'               => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Forms', 'get_external_urls_import_info', 'eml-url-upload-info-nonce' ),
			'sync config dialog'        => array( 'ExternalFilesInMediaLibrary\ExternalFiles\SynchronizationDialog', 'get_dialog', 'efml-sync-config-nonce' ),
			'sync config save'          => array( 'ExternalFilesInMediaLibrary\ExternalFiles\SynchronizationDialog', 'save_via_ajax', 'efml-sync-save-config-nonce' ),
			'reset proxy'               => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Proxy', 'reset_via_ajax', 'eml-reset-proxy-nonce' ),
			'import dialog'             => array( 'ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog', 'get_import_dialog', 'efml-import-dialog-nonce' ),
			'availability check'        => array( 'ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability', 'check_file_availability_via_ajax', 'eml-availability-check-nonce' ),
			'dropbox connection'        => array( 'ExternalFilesInMediaLibrary\Services\DropBox', 'add_connection_by_ajax', 'efml-dropbox-save-access-token' ),
			'dropbox token removal'     => array( 'ExternalFilesInMediaLibrary\Services\DropBox', 'remove_access_token_by_ajax', 'efml-dropbox-remove-access-token' ),
			'import external source'    => array( 'ExternalFilesInMediaLibrary\Services\Services', 'import_external_source_json_via_ajax', 'efml-import-external-source' ),
			'intro closed'              => array( 'ExternalFilesInMediaLibrary\Plugin\Intro', 'closed', 'efml-intro-closed' ),
			'intro started'             => array( 'ExternalFilesInMediaLibrary\Plugin\Intro', 'started', 'efml-intro-started' ),
			'add external source'       => array( 'ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing', 'add_archive_via_ajax', 'eml-add-archive-nonce' ),
			'delete external source'    => array( 'ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing', 'delete_archive_via_ajax', 'eml-delete-archive-nonce' ),
			'rename external source'    => array( 'ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing', 'save_new_listing_name_via_ajax', 'efml-change-term-name' ),
			'install plugin'            => array( 'ExternalFilesInMediaLibrary\Plugin\Admin\Plugins', 'install_and_activate_plugin_by_ajax', 'efml-install-and-activate-plugin' ),
			'install plugin info'       => array( 'ExternalFilesInMediaLibrary\Plugin\Admin\Plugins', 'get_info_about_install_and_activate_service_plugin_via_ajax', 'efml-get-install-and-activate-plugin-info-nonce' ),
		);
	}

	/**
	 * Test that each handler ends without a valid nonce, also for administrators.
	 *
	 * @dataProvider get_handlers
	 *
	 * @param string $class_name The class of the handler.
	 * @param string $method The method of the handler.
	 * @param string $nonce_action The action of its nonce.
	 *
	 * @return void
	 */
	public function test_handler_requires_nonce( string $class_name, string $method, string $nonce_action ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$handler = array( $class_name::get_instance(), $method );

		// without nonce.
		$this->assertSame( '-1', $this->call_ajax( $handler )['die'] );

		// with the nonce of another action.
		$this->assertSame( '-1', $this->call_ajax( $handler, array( 'nonce' => wp_create_nonce( 'another-action' ) ) )['die'] );
	}

	/**
	 * Test that each handler refuses users without capabilities of this plugin, also with a valid nonce.
	 *
	 * @dataProvider get_handlers
	 *
	 * @param string $class_name The class of the handler.
	 * @param string $method The method of the handler.
	 * @param string $nonce_action The action of its nonce.
	 *
	 * @return void
	 */
	public function test_handler_requires_capability( string $class_name, string $method, string $nonce_action ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $this->call_ajax( array( $class_name::get_instance(), $method ), array( 'nonce' => wp_create_nonce( $nonce_action ) ) );

		// the handler must not end with the nonce error, as the nonce is valid.
		$this->assertNotSame( '-1', $result['die'] );

		// allowed are: no output, an empty answer, an error or an error dialog.
		$output = trim( $result['output'] );
		if ( '' === $output ) {
			$this->assertTrue( true );
			return;
		}
		$json = json_decode( $output, true );
		$this->assertIsArray( $json, $output );
		$this->assertNotTrue( $json['success'] ?? null, $output );
		if ( ! empty( $json ) && ! isset( $json['success'] ) ) {
			$this->assertMatchesRegularExpression( '/could not/i', (string) ( $json['detail']['title'] ?? '' ), $output );
		}
	}

	/**
	 * Test that the proxy cache is only reset by users with the capability.
	 *
	 * @return void
	 */
	public function test_reset_proxy(): void {
		$proxy = \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance();
		$file  = $proxy->get_cache_directory() . 'efml-test-cache-file.jpg';
		file_put_contents( $file, 'cached' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// a subscriber can not reset it.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->call_ajax( array( $proxy, 'reset_via_ajax' ), array( 'nonce' => wp_create_nonce( 'eml-reset-proxy-nonce' ) ) );
		$this->assertFileExists( $file );

		// an administrator can.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->call_ajax( array( $proxy, 'reset_via_ajax' ), array( 'nonce' => wp_create_nonce( 'eml-reset-proxy-nonce' ) ) );
		$this->assertFileDoesNotExist( $file );
	}
}

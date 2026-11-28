<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories as Sync_Subdirectories_Extension;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Update;
use ExternalFilesInMediaLibrary\Services\DropBox\Protocol as DropBox_Protocol;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories.
 */
class Sync_Subdirectories extends externalFilesTests {
	/**
	 * Create an external source of the given service.
	 *
	 * @param string $type The name of the service.
	 * @param string $url The URL.
	 *
	 * @return int
	 */
	private function create_source( string $type, string $url ): int {
		$term_id = Taxonomy::get_instance()->add( $type, $url, array() );
		$this->assertGreaterThan( 0, $term_id );
		return $term_id;
	}

	/**
	 * Test the order of the settings: the external source, then the global setting, then the default of the service.
	 *
	 * @return void
	 */
	public function test_is_enabled_for_term(): void {
		$extension = Sync_Subdirectories_Extension::get_instance();
		$term_id   = $this->create_source( 'ftp', 'ftp://example.com/files/' );

		// without any setting, the default of the service is used.
		$this->assertFalse( $extension->is_enabled_for_term( $term_id, false ) );
		$this->assertTrue( $extension->is_enabled_for_term( $term_id, true ) );

		// the global setting enables it for all external sources without own setting.
		update_option( Sync_Subdirectories_Extension::SETTING, 1 );
		$this->assertTrue( $extension->is_enabled_for_term( $term_id, false ) );

		// the setting of the external source wins.
		update_term_meta( $term_id, Sync_Subdirectories_Extension::SETTING, Sync_Subdirectories_Extension::NO );
		$this->assertFalse( $extension->is_enabled_for_term( $term_id, true ) );
		update_option( Sync_Subdirectories_Extension::SETTING, 0 );
		update_term_meta( $term_id, Sync_Subdirectories_Extension::SETTING, Sync_Subdirectories_Extension::YES );
		$this->assertTrue( $extension->is_enabled_for_term( $term_id, false ) );

		// invalid values are ignored.
		update_term_meta( $term_id, Sync_Subdirectories_Extension::SETTING, 'maybe' );
		$this->assertSame( '', $extension->get_term_setting( $term_id ) );
		$this->assertFalse( $extension->is_enabled_for_term( $term_id, false ) );
	}

	/**
	 * Test that the setting is only used during the synchronization of the external source.
	 *
	 * @return void
	 */
	public function test_setting_is_only_used_during_synchronization(): void {
		$extension = Sync_Subdirectories_Extension::get_instance();
		$term_id   = $this->create_source( 'ftp', 'ftp://example.com/files/' );
		update_term_meta( $term_id, Sync_Subdirectories_Extension::SETTING, Sync_Subdirectories_Extension::YES );
		$protocol_obj = new Ftp( 'ftp://example.com/files/' );

		// outside the synchronization, the default of the protocol is used (like for each import of a directory).
		$this->assertFalse( $protocol_obj->is_subdirectories_enabled() );

		// during the synchronization, the setting of the external source is used.
		$extension->add_hooks_before_sync( 'ftp://example.com/files/', array(), $term_id );
		$this->assertTrue( $protocol_obj->is_subdirectories_enabled() );

		// after the synchronization, the default is used again.
		$extension->remove_hooks_after_sync();
		$this->assertFalse( $protocol_obj->is_subdirectories_enabled() );
	}

	/**
	 * Test the defaults of the protocols: DropBox has always used subdirectories, FTP not, HTTP does not support them.
	 *
	 * @return void
	 */
	public function test_defaults_of_protocols(): void {
		$dropbox = new DropBox_Protocol( 'DropBox/photos' );
		$this->assertTrue( $dropbox->supports_subdirectories() );
		$this->assertTrue( $dropbox->get_subdirectories_default() );
		$this->assertTrue( $dropbox->is_subdirectories_enabled() );

		// the setting could disable it for DropBox.
		add_filter( 'efml_include_subdirectories', '__return_false' );
		$this->assertFalse( $dropbox->is_subdirectories_enabled() );

		// protocols without support are not changed by the setting.
		add_filter( 'efml_include_subdirectories', '__return_true', 20 );
		$this->assertFalse( ( new Http( 'https://example.com/files/' ) )->is_subdirectories_enabled() );

		$ftp = new Ftp( 'ftp://example.com/files/' );
		$this->assertTrue( $ftp->supports_subdirectories() );
		$this->assertFalse( $ftp->get_subdirectories_default() );
	}

	/**
	 * Test the field in the configuration dialog: only for services with support of subdirectories.
	 *
	 * @return void
	 */
	public function test_form_in_dialog(): void {
		$extension = Sync_Subdirectories_Extension::get_instance();

		// FTP supports subdirectories.
		$ftp_term = $this->create_source( 'ftp', 'ftp://example.com/files/' );
		update_term_meta( $ftp_term, Sync_Subdirectories_Extension::SETTING, Sync_Subdirectories_Extension::NO );
		$form = $extension->extend_sync_form_in_dialog( '', $ftp_term );
		$this->assertStringContainsString( 'id="sync_subdirectories"', $form );
		$this->assertStringContainsString( 'value="no" selected', $form );

		// YouTube does not support them.
		$youtube_term = $this->create_source( 'youtube', 'https://www.youtube.com/channel/example' );
		$this->assertSame( '', $extension->extend_sync_form_in_dialog( '', $youtube_term ) );
	}

	/**
	 * Test the saving of the setting from the configuration dialog.
	 *
	 * @return void
	 */
	public function test_save_sync_config(): void {
		$extension = Sync_Subdirectories_Extension::get_instance();
		$term_id   = $this->create_source( 'ftp', 'ftp://example.com/files/' );

		$extension->save_sync_config( array( 'sync_subdirectories' => Sync_Subdirectories_Extension::YES ), $term_id );
		$this->assertSame( Sync_Subdirectories_Extension::YES, $extension->get_term_setting( $term_id ) );

		// without the field (e.g. for services without support), the setting is not changed.
		$extension->save_sync_config( array(), $term_id );
		$this->assertSame( Sync_Subdirectories_Extension::YES, $extension->get_term_setting( $term_id ) );

		// the default removes the setting.
		$extension->save_sync_config( array( 'sync_subdirectories' => '' ), $term_id );
		$this->assertSame( '', (string) get_term_meta( $term_id, Sync_Subdirectories_Extension::SETTING, true ) );
	}

	/**
	 * Test the migration: external sources of services, which have always used subdirectories, keep them.
	 *
	 * @return void
	 */
	public function test_migration(): void {
		$ftp_term     = $this->create_source( 'ftp', 'ftp://example.com/files/' );
		$dropbox_term = $this->create_source( 'dropbox', 'DropBox/photos' );
		$zip_term     = $this->create_source( 'zip', 'https://example.com/archive.zip' );
		$set_term     = $this->create_source( 'dropbox', 'DropBox/other' );
		update_term_meta( $set_term, Sync_Subdirectories_Extension::SETTING, Sync_Subdirectories_Extension::NO );

		// the defaults of the services.
		$this->assertFalse( Synchronization::get_instance()->get_subdirectories_default( $ftp_term ) );
		$this->assertTrue( Synchronization::get_instance()->get_subdirectories_default( $dropbox_term ) );
		$this->assertTrue( Synchronization::get_instance()->get_subdirectories_default( $zip_term ) );

		Update::get_instance()->migrate_subdirectories_setting();

		$this->assertSame( '', (string) get_term_meta( $ftp_term, Sync_Subdirectories_Extension::SETTING, true ) );
		$this->assertSame( Sync_Subdirectories_Extension::YES, get_term_meta( $dropbox_term, Sync_Subdirectories_Extension::SETTING, true ) );
		$this->assertSame( Sync_Subdirectories_Extension::YES, get_term_meta( $zip_term, Sync_Subdirectories_Extension::SETTING, true ) );

		// an existing setting is not changed.
		$this->assertSame( Sync_Subdirectories_Extension::NO, get_term_meta( $set_term, Sync_Subdirectories_Extension::SETTING, true ) );
	}
}

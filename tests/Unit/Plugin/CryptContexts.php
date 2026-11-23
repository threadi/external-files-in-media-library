<?php
/**
 * Tests for the contexts of the encrypted values (P37).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\Ftp;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Youtube;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionMethod;

/**
 * Object to test the contexts of the encrypted values.
 */
class CryptContexts extends externalFilesTests {
	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// initialize the crypt object: warnings of its initialization are no errors of a value.
		Crypt::get_instance()->get_method();
	}

	/**
	 * Return the number of logged errors of the encryption.
	 *
	 * @return int
	 */
	private function count_crypt_errors(): int {
		return count( $this->get_crypt_errors() );
	}

	/**
	 * Return the logged errors of the encryption.
	 *
	 * @return array<int,string>
	 */
	private function get_crypt_errors(): array {
		$errors = array();
		foreach ( Log::get_instance()->get_logs() as $entry ) {
			$text = (string) wp_json_encode( $entry );
			if ( str_contains( $text, 'Error in encryption' ) ) {
				$errors[] = $text;
			}
		}
		return $errors;
	}

	/**
	 * Return an external file for the tests.
	 *
	 * @return File
	 */
	private function get_external_file(): File {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $attachment_id, 'eml_external_file', 'https://example.com/crypt-context.jpg' );
		return Files::get_instance()->get_file( $attachment_id );
	}

	/**
	 * Test the fields of a file: new values are bound to the context, values saved before stay readable.
	 *
	 * @return void
	 */
	public function test_file_fields(): void {
		$fields = array( 'login' => array( 'value' => 'user' ) );
		$errors = $this->count_crypt_errors();

		// new value.
		$file = $this->get_external_file();
		$file->set_fields( $fields );
		$this->assertEquals( $fields, $file->get_fields() );
		$this->assertEquals( wp_json_encode( $fields ), Crypt::get_instance()->decrypt( (string) get_post_meta( $file->get_id(), 'eml_fields', true ), File::FIELDS_CONTEXT ) );

		// value saved by an older version (without context).
		update_post_meta( $file->get_id(), 'eml_fields', Crypt::get_instance()->encrypt( (string) wp_json_encode( $fields ) ) );
		$this->assertEquals( $fields, $file->get_fields() );

		// no error has been logged.
		$this->assertEquals( $errors, $this->count_crypt_errors() );
	}

	/**
	 * Test the fields of a run, which have been saved before the context has been used.
	 *
	 * @return void
	 */
	public function test_run_fields(): void {
		$fields = array( 'login' => array( 'value' => 'run-user' ) );
		$method = new ReflectionMethod( Import_Runs::get_instance(), 'get_fields' );
		$errors = $this->count_crypt_errors();

		// a value saved by an older version and a new one.
		$this->assertEquals( $fields, $method->invoke( Import_Runs::get_instance(), array( 'fields' => Crypt::get_instance()->encrypt( (string) wp_json_encode( $fields ) ) ) ) );
		$this->assertEquals( $fields, $method->invoke( Import_Runs::get_instance(), array( 'fields' => Crypt::get_instance()->encrypt( (string) wp_json_encode( $fields ), Import_Runs::FIELDS_CONTEXT ) ) ) );

		// another context does not decrypt a new value.
		$this->assertEquals( '', Crypt::get_instance()->decrypt( Crypt::get_instance()->encrypt( 'x', Import_Runs::FIELDS_CONTEXT ), Queue::FIELDS_CONTEXT ) );
		$this->assertEquals( $errors + 1, $this->count_crypt_errors() );
	}

	/**
	 * Test the global access token of Dropbox, which has been saved before the context has been used.
	 *
	 * @return void
	 */
	public function test_dropbox_global_token(): void {
		update_option( 'eml_dropbox_credentials_vault', 'global' );
		$token  = array(
			'access_token' => 'token',
			'expires'      => time() + DAY_IN_SECONDS,
		);
		$errors = $this->count_crypt_errors();

		// saved by an older version.
		update_option( 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( (string) wp_json_encode( $token ) ) );
		$this->assertEquals( $token, DropBox::get_instance()->get_access_token() );

		// saved with context.
		update_option( 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( (string) wp_json_encode( $token ), DropBox::ACCESS_TOKENS_OPTION_CONTEXT ) );
		$this->assertEquals( $token, DropBox::get_instance()->get_access_token() );

		$this->assertEquals( $errors, $this->count_crypt_errors() );
		update_option( 'eml_dropbox_credentials_vault', 'manually' );
	}

	/**
	 * Test the global settings of FTP and YouTube, saved before the context has been used and with it.
	 *
	 * @return void
	 */
	public function test_global_settings(): void {
		update_option( 'eml_ftp_credentials_vault', 'global' );
		$method = new ReflectionMethod( Ftp::get_instance(), 'get_field_values' );
		$errors = $this->count_crypt_errors();

		// saved by an older version.
		update_option( 'efml_ftp_login', Crypt::get_instance()->encrypt( 'old-login' ) );
		$this->assertEquals( 'old-login', $method->invoke( Ftp::get_instance() )['login'] );

		// saved via the callback of the setting.
		$save = Crypt::get_instance()->get_save_callback( 'option:efml_ftp_login' );
		update_option( 'efml_ftp_login', $save( 'new-login', '', 'efml_ftp_login' ) );
		$this->assertEquals( 'new-login', $method->invoke( Ftp::get_instance() )['login'] );

		$this->assertEquals( $errors, $this->count_crypt_errors() );
		update_option( 'eml_ftp_credentials_vault', 'manually' );
	}

	/**
	 * Test the user settings: services of this plugin use the context, values saved before stay readable.
	 *
	 * @return void
	 */
	public function test_user_settings(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_option( 'eml_youtube_credentials_vault', 'user' );
		$method = new ReflectionMethod( Youtube::get_instance(), 'get_field_values' );
		$errors = $this->count_crypt_errors();

		// the services of this plugin use the context.
		$this->assertEquals( 'usermeta:efml_youtube_api_key', Youtube::get_instance()->get_user_setting_crypt_context( 'youtube_api_key' ) );

		// saved by an older version.
		update_user_meta( $user_id, 'efml_youtube_api_key', Crypt::get_instance()->encrypt( 'old-key' ) );
		$this->assertEquals( 'old-key', $method->invoke( Youtube::get_instance() )['api_key'] );

		// saved with the context.
		update_user_meta( $user_id, 'efml_youtube_api_key', Crypt::get_instance()->encrypt( 'new-key', 'usermeta:efml_youtube_api_key' ) );
		$this->assertEquals( 'new-key', $method->invoke( Youtube::get_instance() )['api_key'] );

		$this->assertEquals( $errors, $this->count_crypt_errors() );
		update_option( 'eml_youtube_credentials_vault', 'manually' );
	}

	/**
	 * Test that services of extensions do not use the context for user settings, unless they support it:
	 * older versions of extensions read their user settings themselves, without context.
	 *
	 * @return void
	 */
	public function test_user_settings_of_extensions(): void {
		// a service of an extension in an older version.
		$service = new class() extends Service_Base {};
		$this->assertFalse( $service->is_crypt_context_supported() );
		$this->assertEquals( '', $service->get_user_setting_crypt_context( 's3_access_key' ) );

		// a service of an extension, which supports it.
		$service = new class() extends Service_Base {
			/**
			 * Return whether the context is used.
			 *
			 * @return bool
			 */
			public function is_crypt_context_supported(): bool {
				return true;
			}
		};
		$this->assertEquals( 'usermeta:efml_s3_access_key', $service->get_user_setting_crypt_context( 's3_access_key' ) );
	}

	/**
	 * Test that a value encrypted with another key is logged once, with the affected value.
	 *
	 * @return void
	 */
	public function test_error_names_the_value(): void {
		update_option( 'eml_youtube_credentials_vault', 'global' );
		$method = new ReflectionMethod( Youtube::get_instance(), 'get_field_values' );
		$before = $this->get_crypt_errors();

		// a value encrypted with another key, e.g. on a copy of another website.
		$crypt_obj = new \CryptForWordPress\Crypt( EFML_PLUGIN );
		$crypt_obj->set_slug( 'another-website-for-efml-tests' );
		update_option( 'eml_youtube_api_key', $crypt_obj->encrypt( 'foreign' ) );

		$this->assertEquals( '', $method->invoke( Youtube::get_instance() )['api_key'] );
		$logged = array_values( array_diff( $this->get_crypt_errors(), $before ) );
		$this->assertCount( 1, $logged );
		$this->assertStringContainsString( 'option:eml_youtube_api_key', $logged[0] );

		update_option( 'eml_youtube_credentials_vault', 'manually' );
	}

	/**
	 * Test the fields of an external source in the taxonomy, saved by an older version.
	 *
	 * @return void
	 */
	public function test_source_fields(): void {
		$fields  = array( 'login' => array( 'value' => 'source-user' ) );
		$errors  = $this->count_crypt_errors();
		$term_id = Taxonomy::get_instance()->add( 'ftp', 'ftp://crypt-context.example.com/', array() );
		update_term_meta( $term_id, 'fields', Crypt::get_instance()->encrypt( (string) wp_json_encode( $fields ) ) );
		$this->assertEquals( $fields, Taxonomy::get_instance()->get_entry( $term_id )['fields'] );
		$this->assertEquals( $errors, $this->count_crypt_errors() );
	}
}

<?php
/**
 * Tests for the usage of constants for the fields of services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp as Ftp_Protocol;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http as Http_Protocol;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Sftp as Sftp_Protocol;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\Ftp;
use ExternalFilesInMediaLibrary\Services\Local;
use ExternalFilesInMediaLibrary\Services\Multisite;
use ExternalFilesInMediaLibrary\Services\Rest;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Youtube;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the usage of constants for the fields of services.
 *
 * The constants of PHP could not be removed after they have been defined. Therefore, each test
 * uses its own prefix for the constant names via the hook "efml_service_field_constant_name".
 */
class Constants extends externalFilesTests {
	/**
	 * The prefix for the constants of the actual test.
	 *
	 * @var string
	 */
	private string $prefix = '';

	/**
	 * Counter for unique prefixes.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// use a unique prefix for the constants of this test.
		++self::$counter;
		$this->prefix = 'EFMLTEST' . self::$counter . '_';
		add_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );

		// reset the fields of the used services.
		Ftp::get_instance()->set_fields( array() );
		Rest::get_instance()->set_fields( array() );
		Youtube::get_instance()->set_fields( array() );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		Ftp::get_instance()->set_fields( array() );
		Rest::get_instance()->set_fields( array() );
		Youtube::get_instance()->set_fields( array() );
		parent::tear_down();
	}

	/**
	 * Use the prefix of the actual test for the constant names.
	 *
	 * @param string $constant_name The name of the constant.
	 *
	 * @return string
	 */
	public function set_constant_name( string $constant_name ): string {
		return $this->prefix . $constant_name;
	}

	/**
	 * Define a constant for the actual test.
	 *
	 * @param string $name  The name of the constant without prefix (e.g. "EFML_FTP_LOGIN").
	 * @param string $value The value.
	 *
	 * @return void
	 */
	private function define_constant( string $name, string $value ): void {
		define( $this->prefix . $name, $value );
	}

	/**
	 * Define all constants for the FTP service.
	 *
	 * @return void
	 */
	private function define_ftp_constants(): void {
		$this->define_constant( 'EFML_FTP_SERVER', 'ftps://ftp.example.com' );
		$this->define_constant( 'EFML_FTP_LOGIN', 'constant-login' );
		$this->define_constant( 'EFML_FTP_PASSWORD', 'constant-password' );
	}

	/**
	 * Test that constants are enabled for services with credentials and disabled for the others.
	 *
	 * @return void
	 */
	public function test_constants_enabled_for_services(): void {
		$this->assertTrue( Ftp::get_instance()->is_fields_via_constants_enabled() );
		$this->assertTrue( Rest::get_instance()->is_fields_via_constants_enabled() );
		$this->assertTrue( Youtube::get_instance()->is_fields_via_constants_enabled() );
		$this->assertTrue( DropBox::get_instance()->is_fields_via_constants_enabled() );
		$this->assertFalse( Local::get_instance()->is_fields_via_constants_enabled() );
		$this->assertFalse( Multisite::get_instance()->is_fields_via_constants_enabled() );
	}

	/**
	 * Test the required fields of the FTP service.
	 *
	 * @return void
	 */
	public function test_required_fields(): void {
		$this->assertEquals( array( 'server', 'login', 'password' ), Ftp::get_instance()->get_required_fields_for_constants() );
		$this->assertEquals( array( 'server' ), Rest::get_instance()->get_required_fields_for_constants() );
		$this->assertEquals( array( 'channel_id', 'api_key' ), Youtube::get_instance()->get_required_fields_for_constants() );
	}

	/**
	 * Test that no constant is used if no constant is defined.
	 *
	 * @return void
	 */
	public function test_without_constants(): void {
		$ftp = Ftp::get_instance();
		$this->assertEmpty( $ftp->get_constant_values() );
		$this->assertEmpty( $ftp->get_defined_constants_for_fields() );
		$this->assertEmpty( $ftp->get_missing_constants_for_fields() );
		foreach ( $ftp->get_fields() as $field ) {
			$this->assertArrayNotHasKey( 'constant', $field );
		}
	}

	/**
	 * Test the FTP service with all constants defined and empty fields.
	 *
	 * @return void
	 */
	public function test_ftp_with_all_constants(): void {
		$this->define_ftp_constants();

		$fields = Ftp::get_instance()->get_fields();
		$this->assertEquals( 'ftps://ftp.example.com', $fields['server']['value'] );
		$this->assertEquals( 'constant-login', $fields['login']['value'] );
		$this->assertEquals( 'constant-password', $fields['password']['value'] );
		$this->assertTrue( $fields['password']['readonly'] );
		$this->assertEquals( $this->prefix . 'EFML_FTP_PASSWORD', $fields['password']['constant'] );
		$this->assertEquals( 'ftps://ftp.example.com', Ftp::get_instance()->get_directory() );
		$this->assertEmpty( Ftp::get_instance()->get_missing_constants_for_fields() );
	}

	/**
	 * Test that the constants replace the values entered in the fields.
	 *
	 * @return void
	 */
	public function test_ftp_constants_replace_values(): void {
		$this->define_ftp_constants();

		Ftp::get_instance()->set_fields(
			array(
				'server'   => array( 'value' => 'ftp://other.example.com' ),
				'login'    => array( 'value' => 'entered-login' ),
				'password' => array( 'value' => 'entered-password' ),
			)
		);
		$fields = Ftp::get_instance()->get_fields();
		$this->assertEquals( 'ftps://ftp.example.com', $fields['server']['value'] );
		$this->assertEquals( 'constant-login', $fields['login']['value'] );
		$this->assertEquals( 'constant-password', $fields['password']['value'] );
	}

	/**
	 * Test that no constant is used if not all required constants are defined.
	 *
	 * @return void
	 */
	public function test_ftp_with_partial_constants(): void {
		$this->define_constant( 'EFML_FTP_LOGIN', 'constant-login' );
		$this->define_constant( 'EFML_FTP_PASSWORD', 'constant-password' );

		Ftp::get_instance()->set_fields(
			array(
				'server'   => array( 'value' => 'ftp://other.example.com' ),
				'login'    => array( 'value' => 'entered-login' ),
				'password' => array( 'value' => 'entered-password' ),
			)
		);
		$fields = Ftp::get_instance()->get_fields();
		$this->assertEquals( 'entered-login', $fields['login']['value'] );
		$this->assertEquals( 'entered-password', $fields['password']['value'] );
		$this->assertArrayNotHasKey( 'constant', $fields['login'] );
		$this->assertEmpty( Ftp::get_instance()->get_constant_values() );
		$this->assertEquals( array( $this->prefix . 'EFML_FTP_SERVER' ), Ftp::get_instance()->get_missing_constants_for_fields() );
		$this->assertCount( 2, Ftp::get_instance()->get_defined_constants_for_fields() );
	}

	/**
	 * Test that secret values are not sent to the browser.
	 *
	 * @return void
	 */
	public function test_ftp_config_without_secrets(): void {
		$this->define_ftp_constants();

		$config = Ftp::get_instance()->get_config();
		$this->assertEquals( 'ftps://ftp.example.com', $config['fields']['server']['value'] );
		$this->assertEquals( '', $config['fields']['login']['value'] );
		$this->assertEquals( '', $config['fields']['password']['value'] );
		$this->assertTrue( $config['fields']['password']['not_required'] );
		$this->assertStringNotContainsString( 'constant-password', (string) wp_json_encode( $config ) );
	}

	/**
	 * Test the FTP protocol with fields from a saved external source without values (e.g. for an export).
	 *
	 * @return void
	 */
	public function test_ftp_protocol_with_matching_host(): void {
		$this->define_ftp_constants();

		$protocol = new Ftp_Protocol( 'ftp://ftp.example.com/folder/file.jpg' );
		$protocol->set_fields(
			array(
				'server'   => array( 'value' => '' ),
				'login'    => array( 'value' => '' ),
				'password' => array( 'value' => '' ),
			)
		);
		$fields = $protocol->get_fields();
		$this->assertEquals( 'constant-login', $fields['login']['value'] );
		$this->assertEquals( 'constant-password', $fields['password']['value'] );

		// missing fields are added.
		$protocol->set_fields( array() );
		$fields = $protocol->get_fields();
		$this->assertEquals( 'constant-password', $fields['password']['value'] );
	}

	/**
	 * Test that the constants are never used for other servers.
	 *
	 * @return void
	 */
	public function test_ftp_protocol_with_other_host(): void {
		$this->define_ftp_constants();

		// fields entered for another server stay unchanged.
		$protocol = new Ftp_Protocol( 'ftp://other.example.com/file.jpg' );
		$protocol->set_fields(
			array(
				'login'    => array( 'value' => 'entered-login' ),
				'password' => array( 'value' => 'entered-password' ),
			)
		);
		$fields = $protocol->get_fields();
		$this->assertEquals( 'entered-login', $fields['login']['value'] );
		$this->assertEquals( 'entered-password', $fields['password']['value'] );

		// values from constants (e.g. set by the service object) are removed for other servers.
		$protocol->set_fields( Ftp::get_instance()->get_fields() );
		$fields = $protocol->get_fields();
		$this->assertEquals( '', $fields['login']['value'] );
		$this->assertEquals( '', $fields['password']['value'] );
	}

	/**
	 * Test that the SFTP protocol uses the constants of the FTP service.
	 *
	 * @return void
	 */
	public function test_sftp_protocol(): void {
		$this->define_constant( 'EFML_FTP_SERVER', 'sftp://sftp.example.com' );
		$this->define_constant( 'EFML_FTP_LOGIN', 'constant-login' );
		$this->define_constant( 'EFML_FTP_PASSWORD', 'constant-password' );

		$protocol = new Sftp_Protocol( 'sftp://sftp.example.com/file.jpg' );
		$fields   = $protocol->get_fields();
		$this->assertEquals( 'constant-login', $fields['login']['value'] );

		$protocol = new Sftp_Protocol( 'sftp://other.example.com/file.jpg' );
		$this->assertEmpty( $protocol->get_fields() );
	}

	/**
	 * Test the HTTP protocol with the constants of the REST API service.
	 *
	 * @return void
	 */
	public function test_http_protocol_with_rest_constants(): void {
		$this->define_constant( 'EFML_REST_SERVER', 'https://wp.example.com' );
		$this->define_constant( 'EFML_REST_LOGIN', 'constant-login' );
		$this->define_constant( 'EFML_REST_PASSWORD', 'constant-password' );

		// matching host: the credentials are used.
		$protocol = new Http_Protocol( 'https://wp.example.com/wp-content/uploads/file.jpg' );
		$args     = $protocol->get_header_args();
		$this->assertArrayHasKey( 'headers', $args );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$this->assertEquals( 'Basic ' . base64_encode( 'constant-login:constant-password' ), $args['headers']['Authorization'] );

		// other host for the same object: the credentials are not used.
		$args = $protocol->get_header_args( 'https://other.example.com/file.jpg' );
		$this->assertArrayNotHasKey( 'headers', $args );

		// other host: the credentials are not used.
		$protocol = new Http_Protocol( 'https://other.example.com/file.jpg' );
		$this->assertArrayNotHasKey( 'headers', $protocol->get_header_args() );
	}

	/**
	 * Test that optional constants are used, if the required constants are defined.
	 *
	 * @return void
	 */
	public function test_rest_with_only_required_constant(): void {
		$this->define_constant( 'EFML_REST_SERVER', 'https://wp.example.com' );

		$fields = Rest::get_instance()->get_fields();
		$this->assertEquals( 'https://wp.example.com', $fields['server']['value'] );
		$this->assertArrayNotHasKey( 'constant', $fields['login'] );
		$this->assertEquals( 'https://wp.example.com', Rest::get_instance()->get_directory() );
	}

	/**
	 * Test the YouTube service, which does not use a server.
	 *
	 * @return void
	 */
	public function test_youtube(): void {
		$this->define_constant( 'EFML_YOUTUBE_CHANNEL_ID', 'UC123' );
		$this->define_constant( 'EFML_YOUTUBE_API_KEY', 'api-key' );

		$fields = Youtube::get_instance()->get_fields();
		$this->assertEquals( 'UC123', $fields['channel_id']['value'] );
		$this->assertEquals( 'api-key', $fields['api_key']['value'] );

		// the constants are used for every URL.
		$this->assertTrue( Youtube::get_instance()->is_url_matching_constants( 'https://www.youtube.com/watch?v=123' ) );
	}

	/**
	 * Test the API credentials of DropBox.
	 *
	 * @return void
	 */
	public function test_dropbox(): void {
		$dropbox = DropBox::get_instance();

		// only the key is defined: it is not used.
		$this->define_constant( 'EFML_DROPBOX_API_KEY', 'app-key' );
		$this->assertNull( $dropbox->get_constant_value( 'api_key' ) );
		$this->assertEquals( array( $this->prefix . 'EFML_DROPBOX_API_SECRET' ), $dropbox->get_missing_constants_for_fields() );

		// both are defined.
		$this->define_constant( 'EFML_DROPBOX_API_SECRET', 'app-secret' );
		$this->assertEquals( 'app-key', $dropbox->get_api_key() );
		$this->assertEquals( 'app-secret', $dropbox->get_api_secret() );

		// no fields are added to the form.
		$this->assertEmpty( $dropbox->get_fields() );
	}

	/**
	 * Test the global settings of FTP: the values defined via constants are not editable.
	 *
	 * @return void
	 */
	public function test_ftp_global_settings(): void {
		$this->define_ftp_constants();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'eml_ftp_credentials_vault', 'global' );

		$ftp = Ftp::get_instance();
		$ftp->add_base_settings();
		$ftp->init_ftp();

		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();

		// the hint with the list of constants is shown.
		$hint = $settings_obj->get_setting( 'eml_ftp_constants_hint' );
		$this->assertNotFalse( $hint );
		$this->assertStringContainsString( $this->prefix . 'EFML_FTP_PASSWORD', $hint->get_field()->get_description() );

		// the server is shown, the password only by the name of its constant.
		$server = $settings_obj->get_setting( 'eml_ftp_server_constant_hint' );
		$this->assertNotFalse( $server );
		$this->assertStringContainsString( 'ftps://ftp.example.com', $server->get_field()->get_description() );
		$password = $settings_obj->get_setting( 'eml_ftp_password_constant_hint' );
		$this->assertNotFalse( $password );
		$this->assertStringNotContainsString( 'constant-password', $password->get_field()->get_description() );

		update_option( 'eml_ftp_credentials_vault', 'manually' );
	}

	/**
	 * Test the user settings of FTP: the values defined via constants are not editable and not saved.
	 *
	 * @return void
	 */
	public function test_ftp_user_settings(): void {
		$this->define_ftp_constants();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'eml_ftp_credentials_vault', 'user' );

		$method = new \ReflectionMethod( Ftp::get_instance(), 'get_user_settings_table' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( Ftp::get_instance(), $user_id );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'name="efml_ftp_password"', $output );
		$this->assertStringContainsString( $this->prefix . 'EFML_FTP_PASSWORD', $output );
		$this->assertStringNotContainsString( 'constant-password', $output );

		update_option( 'eml_ftp_credentials_vault', 'manually' );
	}

	/**
	 * Test that the mode is not forced without constants.
	 *
	 * @return void
	 */
	public function test_mode_without_constants(): void {
		update_option( 'eml_ftp_credentials_vault', 'user' );
		$this->assertFalse( Ftp::get_instance()->is_mode_forced_by_constants() );
		$this->assertEquals( 'user', get_option( 'eml_ftp_credentials_vault' ) );
		update_option( 'eml_ftp_credentials_vault', 'manually' );
	}

	/**
	 * Test that the mode is fixed to "global" with constants, without changing the saved mode.
	 *
	 * @return void
	 */
	public function test_mode_with_constants(): void {
		update_option( 'eml_ftp_credentials_vault', 'user' );
		$this->define_ftp_constants();

		$ftp = Ftp::get_instance();
		$this->assertTrue( $ftp->is_mode_forced_by_constants() );
		$this->assertEquals( 'global', get_option( 'eml_ftp_credentials_vault' ) );

		// saving another mode (e.g. via the hidden field of the settings) does not change the saved mode.
		update_option( 'eml_ftp_credentials_vault', 'global' );
		$method = new \ReflectionMethod( $ftp, 'get_saved_mode' );
		$method->setAccessible( true );
		$this->assertEquals( 'user', $method->invoke( $ftp ) );

		// the setting is not editable.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();
		$this->remove_setting( $settings_obj, 'eml_ftp_credentials_vault' );
		$ftp->add_base_settings();
		$setting = $settings_obj->get_setting( 'eml_ftp_credentials_vault' );
		$this->assertNotFalse( $setting );
		$this->assertTrue( $setting->get_field()->is_readonly() );
	}

	/**
	 * Remove a setting from the settings object, so it could be added again.
	 *
	 * @param object $settings_obj The settings object.
	 * @param string $name         The name of the setting.
	 *
	 * @return void
	 */
	private function remove_setting( object $settings_obj, string $name ): void {
		$by_name = new \ReflectionProperty( $settings_obj, 'settings_by_name' );
		$by_name->setAccessible( true );
		$list = $by_name->getValue( $settings_obj );
		unset( $list[ $name ] );
		$by_name->setValue( $settings_obj, $list );

		$settings = new \ReflectionProperty( $settings_obj, 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( $settings_obj, array_values( array_filter( $settings->getValue( $settings_obj ), static fn( $setting ) => $setting->get_name() !== $name ) ) );
	}

	/**
	 * Test which URLs are blocked for the protocols.
	 *
	 * @return void
	 */
	public function test_blocking_of_urls(): void {
		$this->define_ftp_constants();
		$this->define_constant( 'EFML_REST_SERVER', 'https://wp.example.com' );

		// FTP and SFTP on other servers are blocked.
		$this->assertInstanceOf( Ftp::class, ( new Ftp_Protocol( 'ftp://other.example.com/file.jpg' ) )->get_service_blocking_by_constants() );
		$this->assertInstanceOf( Ftp::class, ( new Sftp_Protocol( 'sftp://other.example.com/file.jpg' ) )->get_service_blocking_by_constants() );

		// FTP on the server of the constants is not blocked.
		$this->assertFalse( ( new Ftp_Protocol( 'ftp://ftp.example.com/file.jpg' ) )->get_service_blocking_by_constants() );

		// HTTP is never blocked by the constants of the REST API.
		$this->assertFalse( ( new Http_Protocol( 'https://other.example.com/file.jpg' ) )->get_service_blocking_by_constants() );
		$this->assertEquals( array( 'http' ), Services::get_instance()->get_service_names_for_blocking( 'http' ) );
	}

	/**
	 * Return a service object with a bucket as target field (like the S3 platforms).
	 *
	 * @return Service_Base
	 */
	private function get_bucket_service(): Service_Base {
		return new class() extends Service_Base {
			/**
			 * The name.
			 *
			 * @var string
			 */
			protected string $name = 'efml-test-bucket';

			/**
			 * The target fields.
			 *
			 * @var array<int,string>
			 */
			protected array $constants_target_fields = array( 'bucket' );

			/**
			 * Return the fields.
			 *
			 * @return array<string,array<string,mixed>>
			 */
			public function get_fields(): array {
				if ( empty( $this->fields ) ) {
					$this->fields = array(
						'secret' => array(
							'name'       => 'secret',
							'type'       => 'password',
							'credential' => true,
							'value'      => '',
						),
						'bucket' => array(
							'name'  => 'bucket',
							'type'  => 'text',
							'value' => 'bucket-from-settings',
						),
					);
				}
				return parent::get_fields();
			}
		};
	}

	/**
	 * Test the target fields: the constants are only used for fields of the same bucket.
	 *
	 * @return void
	 */
	public function test_target_fields(): void {
		$this->define_constant( 'EFML_EFML_TEST_BUCKET_SECRET', 'constant-secret' );
		$this->define_constant( 'EFML_EFML_TEST_BUCKET_BUCKET', 'constant-bucket' );
		$service = $this->get_bucket_service();

		// fields from the settings: the constants win.
		$fields = $service->get_fields();
		$this->assertEquals( 'constant-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'constant-secret', $fields['secret']['value'] );

		// fields of the same bucket (e.g. of a saved external source): the constants are used.
		$service->set_fields(
			array(
				'secret' => array( 'value' => 'own-secret' ),
				'bucket' => array( 'value' => 'Constant-Bucket' ),
			)
		);
		$this->assertEquals( 'constant-secret', $service->get_fields()['secret']['value'] );
		$this->assertFalse( $service->is_target_blocked_by_constants( '', $service->get_fields() ) );

		// fields of another bucket: the constants are not used, the source is blocked.
		$other_fields = array(
			'secret' => array( 'value' => 'own-secret' ),
			'bucket' => array( 'value' => 'other-bucket' ),
		);
		$service->set_fields( $other_fields );
		$fields = $service->get_fields();
		$this->assertEquals( 'own-secret', $fields['secret']['value'] );
		$this->assertEquals( 'other-bucket', $fields['bucket']['value'] );
		$this->assertTrue( $service->is_target_blocked_by_constants( '', $other_fields ) );

		// values from constants are removed for another bucket (e.g. by a protocol).
		$fields = $service->apply_constants_to_fields(
			array(
				'secret' => array(
					'value'    => 'constant-secret',
					'constant' => 'X',
				),
				'bucket' => array( 'value' => 'other-bucket' ),
			),
			'https://example.com/file.jpg'
		);
		$this->assertEquals( '', $fields['secret']['value'] );

		// empty bucket in the fields: the constants are used.
		$this->assertTrue( $service->is_fields_matching_constants( array( 'bucket' => array( 'value' => '' ) ) ) );
	}

	/**
	 * Test the mapping of protocols to services.
	 *
	 * @return void
	 */
	public function test_service_names_for_constants(): void {
		$services = Services::get_instance();
		$this->assertEquals( array( 'sftp', 'ftp' ), $services->get_service_names_for_constants( 'sftp' ) );
		$this->assertEquals( array( 'http', 'rest' ), $services->get_service_names_for_constants( 'http' ) );
		$this->assertEquals( array( 'ftp' ), $services->get_service_names_for_constants( 'ftp' ) );
		$this->assertInstanceOf( Service_Base::class, $services->get_service_by_name( 'ftp' ) );
	}
}

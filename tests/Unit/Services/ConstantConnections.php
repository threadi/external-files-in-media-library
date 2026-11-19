<?php
/**
 * Tests for several connections defined via constants.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Abilities;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp as Ftp_Protocol;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Sftp as Sftp_Protocol;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\Ftp;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Error;

/**
 * Object to test several connections defined via constants (e.g. "EFML_FTP_CONNECTIONS" and "EFML_FTP_SHOP_SERVER").
 *
 * The constants of PHP could not be removed after they have been defined. Therefore, each test
 * uses its own prefix for the constant names via the hooks for the constant names.
 */
class ConstantConnections extends externalFilesTests {
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
		$this->prefix = 'EFMLCONN' . self::$counter . '_';
		add_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		add_filter( 'efml_service_connections_constant_name', array( $this, 'set_constant_name' ) );

		// reset the fields of the used services.
		Ftp::get_instance()->set_fields( array() );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		remove_filter( 'efml_service_connections_constant_name', array( $this, 'set_constant_name' ) );
		Ftp::get_instance()->set_fields( array() );
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
	 * Define the constants for a connection of the FTP service.
	 *
	 * @param string $connection The name of the connection in uppercase ("" for the default connection).
	 * @param string $host       The host of the server.
	 * @param bool   $complete   Whether all required constants should be defined.
	 *
	 * @return void
	 */
	private function define_ftp_connection( string $connection, string $host, bool $complete = true ): void {
		$prefix = 'EFML_FTP_' . ( '' !== $connection ? $connection . '_' : '' );
		$this->define_constant( $prefix . 'SERVER', 'ftps://' . $host );
		$this->define_constant( $prefix . 'LOGIN', strtolower( $host ) . '-login' );
		if ( $complete ) {
			$this->define_constant( $prefix . 'PASSWORD', strtolower( $host ) . '-password' );
		}
	}

	/**
	 * Define the default connection and the connections "Shop" and "archiv" for the FTP service.
	 *
	 * @return void
	 */
	private function define_three_ftp_connections(): void {
		$this->define_ftp_connection( '', 'ftp.example.com' );
		$this->define_constant( 'EFML_FTP_CONNECTIONS', 'Shop, archiv' );
		$this->define_ftp_connection( 'SHOP', 'shop.example.com' );
		$this->define_ftp_connection( 'ARCHIV', 'archiv.example.com' );
	}

	/**
	 * Return the fields to choose the given connection.
	 *
	 * @param string $connection The name of the connection.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_fields_for_connection( string $connection ): array {
		return array(
			Service_Base::CONNECTION_FIELD => array( 'value' => $connection ),
			'server'                       => array( 'value' => '' ),
			'login'                        => array( 'value' => '' ),
			'password'                     => array( 'value' => '' ),
		);
	}

	/**
	 * Invoke a non-public method of the given object.
	 *
	 * @param object       $obj    The object.
	 * @param string       $method The name of the method.
	 * @param array<mixed> $args   The arguments.
	 *
	 * @return mixed
	 */
	private function invoke( object $obj, string $method, array $args = array() ): mixed {
		$reflection = new \ReflectionMethod( $obj, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $obj, $args );
	}

	/**
	 * Return a service object with a bucket as target field and an optional field (like the S3 platforms).
	 *
	 * @param string $name The name of the service.
	 *
	 * @return Service_Base
	 */
	private function get_bucket_service( string $name = 'efml-conn-bucket' ): Service_Base {
		$service = new class() extends Service_Base {
			/**
			 * The name.
			 *
			 * @var string
			 */
			protected string $name = '';

			/**
			 * The target fields.
			 *
			 * @var array<int,string>
			 */
			protected array $constants_target_fields = array( 'bucket' );

			/**
			 * Set the name.
			 *
			 * @param string $name The name.
			 *
			 * @return void
			 */
			public function set_name( string $name ): void {
				$this->name = $name;
			}

			/**
			 * Return the fields.
			 *
			 * @return array<string,array<string,mixed>>
			 */
			public function get_fields(): array {
				if ( empty( $this->fields ) ) {
					$this->fields = array(
						'secret'            => array(
							'name'       => 'secret',
							'type'       => 'password',
							'credential' => true,
							'value'      => '',
						),
						'bucket'            => array(
							'name'  => 'bucket',
							'type'  => 'text',
							'value' => '',
						),
						'domain'            => array(
							'name'         => 'domain',
							'type'         => 'text',
							'value'        => '',
							'not_required' => true,
						),
						'cloudfront_domain' => array(
							'name'         => 'cloudfront_domain',
							'type'         => 'text',
							'value'        => '',
							'not_required' => true,
						),
					);
				}
				return parent::get_fields();
			}
		};
		$service->set_name( $name );
		return $service;
	}

	/**
	 * Test the parsing of the list of connection names.
	 *
	 * @return void
	 */
	public function test_connection_names(): void {
		$ftp = Ftp::get_instance();
		$this->assertEquals( $this->prefix . 'EFML_FTP_CONNECTIONS', $ftp->get_connections_constant_name() );
		$this->assertEmpty( $ftp->get_connection_names() );

		$this->define_constant( 'EFML_FTP_CONNECTIONS', ' Shop, archiv ,,default, sh op, shop, ä, my-site ' );
		$this->assertEquals(
			array(
				'shop'    => 'Shop',
				'archiv'  => 'archiv',
				'my-site' => 'my-site',
			),
			$ftp->get_connection_names()
		);
		$this->assertEquals( array( 'default', 'sh op', 'shop', 'ä' ), $ftp->get_invalid_connection_names() );

		// the names are used in the constant names.
		$this->assertEquals( $this->prefix . 'EFML_FTP_SHOP_LOGIN', $ftp->get_constant_name_for_field( 'login', 'shop' ) );
		$this->assertEquals( $this->prefix . 'EFML_FTP_MY_SITE_LOGIN', $ftp->get_constant_name_for_field( 'login', 'my-site' ) );
		$this->assertEquals( $this->prefix . 'EFML_FTP_LOGIN', $ftp->get_constant_name_for_field( 'login' ) );
	}

	/**
	 * Test that names, whose constants would be equal to other constants, could not be used.
	 *
	 * @return void
	 */
	public function test_connection_names_with_colliding_constants(): void {
		$service = $this->get_bucket_service( 'efml-conn-collision' );

		// "cloudfront" + "domain" would be the constant of the field "cloudfront_domain" of the default connection.
		// "a_cloudfront" + "domain" would be the constant of the field "cloudfront_domain" of the connection "a".
		$this->define_constant( 'EFML_EFML_CONN_COLLISION_CONNECTIONS', 'shop,cloudfront,a,a_cloudfront' );
		$this->assertEquals( array( 'shop', 'a' ), array_keys( $service->get_connection_names() ) );
		$this->assertEquals( array( 'cloudfront', 'a_cloudfront' ), $service->get_invalid_connection_names() );
	}

	/**
	 * Test that services without fields for their constants (like DropBox) do not support named connections.
	 *
	 * @return void
	 */
	public function test_no_connections_for_oauth_services(): void {
		$this->define_constant( 'EFML_DROPBOX_CONNECTIONS', 'shop' );
		$this->assertFalse( DropBox::get_instance()->supports_constant_connections() );
		$this->assertEmpty( DropBox::get_instance()->get_connection_names() );
		$this->assertTrue( Ftp::get_instance()->supports_constant_connections() );
	}

	/**
	 * Test a single named connection without the default connection: it is used like the default connection.
	 *
	 * @return void
	 */
	public function test_single_named_connection(): void {
		$this->define_constant( 'EFML_FTP_CONNECTIONS', 'shop,archiv' );
		$this->define_ftp_connection( 'SHOP', 'shop.example.com' );
		$this->define_ftp_connection( 'ARCHIV', 'archiv.example.com', false );

		$ftp = Ftp::get_instance();
		$this->assertEquals( array( 'shop' ), array_keys( $ftp->get_connections() ) );
		$this->assertTrue( $ftp->is_mode_forced_by_constants() );

		// no field to choose the connection, the values of the only connection are used.
		$fields = $ftp->get_fields();
		$this->assertArrayNotHasKey( Service_Base::CONNECTION_FIELD, $fields );
		$this->assertEquals( 'shop.example.com-login', $fields['login']['value'] );
		$this->assertEquals( $this->prefix . 'EFML_FTP_SHOP_PASSWORD', $fields['password']['constant'] );
		$this->assertEquals( 'ftps://shop.example.com', $ftp->get_directory() );

		// the incomplete connection is not used.
		$this->assertEquals( array( $this->prefix . 'EFML_FTP_ARCHIV_PASSWORD' ), $ftp->get_missing_constants_for_fields( true, 'archiv' ) );
		$this->assertEmpty( $ftp->get_constant_values( 'archiv' ) );
	}

	/**
	 * Test the field to choose the connection and the usage of the chosen connection.
	 *
	 * @return void
	 */
	public function test_connection_field(): void {
		$this->define_three_ftp_connections();
		$ftp = Ftp::get_instance();
		$this->assertEquals( array( 'default', 'shop', 'archiv' ), array_keys( $ftp->get_connections() ) );

		// the field is the first field, with the default connection as preselection.
		$fields = $ftp->get_fields();
		$this->assertEquals( Service_Base::CONNECTION_FIELD, array_key_first( $fields ) );
		$field = $fields[ Service_Base::CONNECTION_FIELD ];
		$this->assertEquals( 'select', $field['type'] );
		$this->assertEquals( 'default', $field['value'] );
		$this->assertEquals(
			array(
				array(
					'value' => 'default',
					'label' => 'Default (ftp.example.com)',
				),
				array(
					'value' => 'shop',
					'label' => 'Shop (shop.example.com)',
				),
				array(
					'value' => 'archiv',
					'label' => 'archiv (archiv.example.com)',
				),
			),
			$field['options']
		);
		$this->assertEquals( 'ftp.example.com-login', $fields['login']['value'] );

		// the chosen connection is used.
		$ftp->set_fields( $this->get_fields_for_connection( 'archiv' ) );
		$fields = $ftp->get_fields();
		$this->assertEquals( 'archiv', $fields[ Service_Base::CONNECTION_FIELD ]['value'] );
		$this->assertEquals( 'archiv.example.com-login', $fields['login']['value'] );
		$this->assertEquals( 'archiv.example.com-password', $fields['password']['value'] );
		$this->assertEquals( $this->prefix . 'EFML_FTP_ARCHIV_LOGIN', $fields['login']['constant'] );
		$this->assertEquals( 'ftps://archiv.example.com', $ftp->get_directory() );
		$this->assertEquals( 'archiv.example.com-password', $ftp->get_constant_value( 'password' ) );

		// the name is not case-sensitive.
		$ftp->set_fields( $this->get_fields_for_connection( 'SHOP' ) );
		$this->assertEquals( 'shop.example.com-login', $ftp->get_fields()['login']['value'] );

		// an unknown connection: the first connection is used.
		$ftp->set_fields( $this->get_fields_for_connection( 'unknown' ) );
		$fields = $ftp->get_fields();
		$this->assertEquals( 'default', $fields[ Service_Base::CONNECTION_FIELD ]['value'] );
		$this->assertEquals( 'ftp.example.com-login', $fields['login']['value'] );
	}

	/**
	 * Test the service with fields without a chosen connection (e.g. of a source saved before the connections were
	 * defined): the connection is detected by the directory of the source.
	 *
	 * @return void
	 */
	public function test_service_with_directory_of_a_connection(): void {
		$this->define_three_ftp_connections();
		$ftp = Ftp::get_instance();

		// the directory of the connection "archiv" is used, as it is set (e.g. from a saved source).
		$ftp->set_fields(
			array(
				'server'   => array( 'value' => '' ),
				'login'    => array( 'value' => '' ),
				'password' => array( 'value' => '' ),
			)
		);
		$ftp->set_directory( 'ftps://archiv.example.com/folder/' );
		$fields = $ftp->get_fields();
		$this->assertEquals( 'archiv', $fields[ Service_Base::CONNECTION_FIELD ]['value'] );
		$this->assertEquals( 'archiv.example.com-login', $fields['login']['value'] );

		// a directory on another server: no constants are used.
		$ftp->set_directory( 'ftps://other.example.com/' );
		$this->assertEquals( '', $ftp->get_fields()['login']['value'] );
		$ftp->set_directory( '' );
	}

	/**
	 * Test that the values of the previous connection are removed, if another connection is chosen.
	 *
	 * @return void
	 */
	public function test_switching_the_connection(): void {
		$service = $this->get_bucket_service( 'efml-conn-switch' );
		$this->define_constant( 'EFML_EFML_CONN_SWITCH_CONNECTIONS', 'one,two' );
		$this->define_constant( 'EFML_EFML_CONN_SWITCH_ONE_SECRET', 'secret-one' );
		$this->define_constant( 'EFML_EFML_CONN_SWITCH_ONE_BUCKET', 'bucket-one' );
		$this->define_constant( 'EFML_EFML_CONN_SWITCH_ONE_DOMAIN', 'cdn.one.example.com' );
		$this->define_constant( 'EFML_EFML_CONN_SWITCH_TWO_SECRET', 'secret-two' );
		$this->define_constant( 'EFML_EFML_CONN_SWITCH_TWO_BUCKET', 'bucket-two' );

		// the fields with the values of the first connection.
		$fields = $service->get_fields();
		$this->assertEquals( 'one', $fields[ Service_Base::CONNECTION_FIELD ]['value'] );
		$this->assertEquals( 'cdn.one.example.com', $fields['domain']['value'] );
		$this->assertEquals( 'one (bucket-one)', $fields[ Service_Base::CONNECTION_FIELD ]['options'][0]['label'] );

		// choose the second connection: the optional value of the first connection is removed.
		$fields[ Service_Base::CONNECTION_FIELD ]['value'] = 'two';
		$fields = $service->apply_constants_to_fields( $fields, 'https://example.com/file.jpg' );
		$this->assertEquals( 'secret-two', $fields['secret']['value'] );
		$this->assertEquals( 'bucket-two', $fields['bucket']['value'] );
		$this->assertEquals( '', $fields['domain']['value'] );
		$this->assertArrayNotHasKey( 'constant', $fields['domain'] );
	}

	/**
	 * Test the target fields with several connections: the connection is detected by the bucket.
	 *
	 * @return void
	 */
	public function test_target_fields_with_connections(): void {
		$service = $this->get_bucket_service( 'efml-conn-target' );
		$this->define_constant( 'EFML_EFML_CONN_TARGET_SECRET', 'secret-default' );
		$this->define_constant( 'EFML_EFML_CONN_TARGET_BUCKET', 'bucket-default' );
		$this->define_constant( 'EFML_EFML_CONN_TARGET_CONNECTIONS', 'two' );
		$this->define_constant( 'EFML_EFML_CONN_TARGET_TWO_SECRET', 'secret-two' );
		$this->define_constant( 'EFML_EFML_CONN_TARGET_TWO_BUCKET', 'bucket-two' );

		// fields of a saved source without a chosen connection (e.g. saved before the connections were defined).
		$fields = array(
			'secret' => array( 'value' => '' ),
			'bucket' => array( 'value' => 'Bucket-Two' ),
		);
		$this->assertEquals( 'two', $service->get_connection_for_fields( $fields ) );
		$this->assertEquals( 'secret-two', $service->apply_constants_to_fields( $fields )['secret']['value'] );
		$this->assertFalse( $service->is_target_blocked_by_constants( '', $fields ) );
		$this->assertTrue( $service->is_fields_matching_constants( $fields ) );

		// another bucket is blocked.
		$fields['bucket']['value'] = 'bucket-other';
		$this->assertNull( $service->get_connection_for_fields( $fields ) );
		$this->assertTrue( $service->is_target_blocked_by_constants( '', $fields ) );
		$this->assertFalse( $service->is_fields_matching_constants( $fields ) );
		$this->assertEquals( '', $service->apply_constants_to_fields( $fields )['secret']['value'] );

		// the chosen connection must match the bucket.
		$fields = array(
			Service_Base::CONNECTION_FIELD => array( 'value' => 'default' ),
			'secret'                       => array( 'value' => '' ),
			'bucket'                       => array( 'value' => 'bucket-two' ),
		);
		$this->assertNull( $service->get_connection_for_fields( $fields ) );
		$this->assertTrue( $service->is_target_blocked_by_constants( '', $fields ) );
		$this->assertTrue( $service->is_fields_matching_connection( $fields, 'two' ) );
		$this->assertFalse( $service->is_fields_matching_connection( $fields, 'default' ) );
	}

	/**
	 * Test that no values from constants are sent to the browser, if several connections are defined.
	 *
	 * @return void
	 */
	public function test_config_with_several_connections(): void {
		$this->define_three_ftp_connections();

		$config = Ftp::get_instance()->get_config();
		$this->assertEquals( 'default', $config['fields'][ Service_Base::CONNECTION_FIELD ]['value'] );
		foreach ( array( 'server', 'login', 'password' ) as $name ) {
			$this->assertEquals( '', $config['fields'][ $name ]['value'] );
			$this->assertTrue( $config['fields'][ $name ]['readonly'] );
			$this->assertTrue( $config['fields'][ $name ]['not_required'] );
			$this->assertEquals( 'Defined via the chosen connection', $config['fields'][ $name ]['placeholder'] );
		}
		$json = (string) wp_json_encode( $config );
		foreach ( array( 'ftp.example.com-password', 'shop.example.com-password', 'archiv.example.com-password', 'archiv.example.com-login' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $json );
		}
	}

	/**
	 * Test the protocols with several connections: the connection is detected by the host or is chosen in the fields.
	 *
	 * @return void
	 */
	public function test_protocols_with_connections(): void {
		$this->define_three_ftp_connections();

		// without chosen connection: the connection for the host of the URL is used.
		$protocol = new Ftp_Protocol( 'ftp://archiv.example.com/folder/file.jpg' );
		$fields   = $protocol->get_fields();
		$this->assertEquals( 'archiv.example.com-login', $fields['login']['value'] );
		$this->assertFalse( $protocol->get_service_blocking_by_constants() );

		$protocol = new Sftp_Protocol( 'sftp://shop.example.com/file.jpg' );
		$this->assertEquals( 'shop.example.com-password', $protocol->get_fields()['password']['value'] );

		// with the chosen connection on its server.
		$protocol = new Ftp_Protocol( 'ftp://shop.example.com/folder/file.jpg' );
		$protocol->set_fields( $this->get_fields_for_connection( 'shop' ) );
		$this->assertEquals( 'shop.example.com-login', $protocol->get_fields()['login']['value'] );
		$this->assertFalse( $protocol->get_service_blocking_by_constants() );

		// with the chosen connection on the server of another connection: no constants are used and the URL is blocked.
		$protocol = new Ftp_Protocol( 'ftp://archiv.example.com/folder/file.jpg' );
		$protocol->set_fields( $this->get_fields_for_connection( 'shop' ) );
		$this->assertEquals( '', $protocol->get_fields()['login']['value'] );
		$this->assertInstanceOf( Ftp::class, $protocol->get_service_blocking_by_constants() );

		// other servers are blocked.
		$this->assertInstanceOf( Ftp::class, ( new Ftp_Protocol( 'ftp://other.example.com/file.jpg' ) )->get_service_blocking_by_constants() );
		$this->assertTrue( Ftp::get_instance()->is_url_blocked_by_constants( 'ftp://other.example.com/' ) );
		$this->assertFalse( Ftp::get_instance()->is_url_blocked_by_constants( 'ftp://shop.example.com/' ) );
		$this->assertFalse( Ftp::get_instance()->is_url_blocked_by_constants( 'ftp://ftp.example.com/' ) );
	}

	/**
	 * Test saved external sources with a chosen connection.
	 *
	 * @return void
	 */
	public function test_external_sources_with_connections(): void {
		$this->define_three_ftp_connections();

		// a source of the chosen connection is usable.
		$term_id = Taxonomy::get_instance()->add( 'ftp', 'ftps://archiv.example.com/folder/', $this->get_fields_for_connection( 'archiv' ) );
		$this->assertGreaterThan( 0, $term_id );
		$this->assertFalse( Services::get_instance()->is_source_blocked_by_constants( $term_id ) );

		// a source with a chosen connection on the server of another connection is blocked.
		$term_id = Taxonomy::get_instance()->add( 'ftp', 'ftps://archiv.example.com/other-folder/', $this->get_fields_for_connection( 'shop' ) );
		$this->assertTrue( Services::get_instance()->is_source_blocked_by_constants( $term_id ) );

		// a source without a chosen connection on the server of a connection is usable.
		$term_id = Taxonomy::get_instance()->add( 'ftp', 'ftps://shop.example.com/', array() );
		$this->assertFalse( Services::get_instance()->is_source_blocked_by_constants( $term_id ) );

		// a source on another server is blocked.
		$term_id = Taxonomy::get_instance()->add( 'ftp', 'ftps://other.example.com/', array() );
		$this->assertTrue( Services::get_instance()->is_source_blocked_by_constants( $term_id ) );
	}

	/**
	 * Test the hints in the settings with several connections.
	 *
	 * @return void
	 */
	public function test_settings_hints(): void {
		$this->define_ftp_connection( '', 'ftp.example.com' );
		$this->define_constant( 'EFML_FTP_CONNECTIONS', 'Shop,incomplete,default' );
		$this->define_ftp_connection( 'SHOP', 'shop.example.com' );
		$this->define_ftp_connection( 'INCOMPLETE', 'incomplete.example.com', false );
		$ftp = Ftp::get_instance();

		// the hint of the service.
		$hint = (string) $this->invoke( $ftp, 'get_constants_settings_hint' );
		$this->assertStringContainsString( $this->prefix . 'EFML_FTP_CONNECTIONS', $hint );
		$this->assertStringContainsString( 'Default (ftp.example.com):', $hint );
		$this->assertStringContainsString( 'Shop (shop.example.com):', $hint );
		$this->assertStringContainsString( $this->prefix . 'EFML_FTP_SHOP_PASSWORD', $hint );
		$this->assertStringContainsString( 'the following constants are missing: <code>' . $this->prefix . 'EFML_FTP_INCOMPLETE_PASSWORD</code>', $hint );
		$this->assertStringContainsString( '<em>default</em>', $hint );
		$this->assertStringNotContainsString( 'shop.example.com-password', $hint );

		// the hint for a field.
		$hint = (string) $this->invoke( $ftp, 'get_constant_hint_for_field', array( 'server' ) );
		$this->assertStringContainsString( 'Default (ftp.example.com)', $hint );
		$this->assertStringContainsString( 'Shop (shop.example.com)', $hint );
		$this->assertTrue( $ftp->is_field_defined_via_constants( 'password' ) );
		$this->assertFalse( $ftp->is_field_defined_via_constants( 'unknown' ) );
	}

	/**
	 * Test the hint in the settings with the default connection only (unchanged behavior).
	 *
	 * @return void
	 */
	public function test_settings_hint_without_named_connections(): void {
		$this->define_ftp_connection( '', 'ftp.example.com' );
		$hint = (string) $this->invoke( Ftp::get_instance(), 'get_constants_settings_hint' );
		$this->assertStringContainsString( 'They are used for every connection to this service', $hint );
		$this->assertStringContainsString( $this->prefix . 'EFML_FTP_PASSWORD', $hint );

		$hint = (string) $this->invoke( Ftp::get_instance(), 'get_constant_hint_for_field', array( 'server' ) );
		$this->assertStringContainsString( '<code>ftps://ftp.example.com</code>', $hint );
	}

	/**
	 * Test the abilities with several connections: the options are listed and unknown connections are rejected.
	 *
	 * @return void
	 */
	public function test_abilities_with_connections(): void {
		$this->define_three_ftp_connections();

		// the options are listed without any secret values.
		$fields = $this->invoke( Abilities::get_instance(), 'get_service_fields', array( Ftp::get_instance() ) );
		$this->assertEquals( Service_Base::CONNECTION_FIELD, $fields[0]['name'] );
		$this->assertEquals( array( 'default', 'shop', 'archiv' ), array_column( $fields[0]['options'], 'value' ) );
		$this->assertStringNotContainsString( '-password', (string) wp_json_encode( $fields ) );

		// an unknown connection is rejected.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$result = Abilities::get_instance()->add_source(
			array(
				'service' => 'ftp',
				'fields'  => array( Service_Base::CONNECTION_FIELD => 'unknown' ),
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'efml_invalid_field', $result->get_error_code() );
	}
}

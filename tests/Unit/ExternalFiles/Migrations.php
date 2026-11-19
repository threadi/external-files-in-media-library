<?php
/**
 * Tests for the migration of files from other offload plugins.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations\AdvancedMediaOffloader;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations\OffloadMediaCloudStorage;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations\Stowaway;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations\WpOffloadMedia;
use ExternalFilesInMediaLibrary\Plugin\Setup;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Error;
use WP_REST_Request;

/**
 * A service for the tests, whose objects are "saved" on bucket.example.com.
 */
class Migration_Test_Service extends Service_Base {
	/**
	 * The name of the service.
	 *
	 * @var string
	 */
	protected string $name = 'test-offload';

	/**
	 * Return the label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Test offload';
	}

	/**
	 * Return the directory.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return 'https://bucket.example.com/';
	}

	/**
	 * Check the login.
	 *
	 * @param string $directory The directory.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		return true;
	}

	/**
	 * Return the name of the post meta for the name of an object.
	 *
	 * @return string
	 */
	public function get_offloaded_object_meta_key(): string {
		return 'efml_test_key';
	}

	/**
	 * Return the URL of an object, objects with "missing" in their name do not exist.
	 *
	 * @param string $key        The name.
	 * @param bool   $is_private Whether it is private.
	 *
	 * @return string|WP_Error
	 */
	public function get_url_of_offloaded_object( string $key, bool $is_private ): string|WP_Error {
		if ( str_contains( $key, 'missing' ) ) {
			return new WP_Error( 'efml_test_missing', 'The object is missing.' );
		}
		return 'https://bucket.example.com/' . $key;
	}
}

/**
 * A migration for the tests, which reads the data of WP Offload Media for the test service.
 */
class Migration_Test_Migration extends WpOffloadMedia {
	/**
	 * The internal name of this migration.
	 *
	 * @var string
	 */
	protected string $name = 'test-migration';

	/**
	 * Return the name of the service for the given group.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return string
	 */
	public function get_service_name( array $source ): string {
		return 'test-offload';
	}

	/**
	 * Return the fields of the service.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return array<string,string>
	 */
	public function get_fields( array $source ): array {
		return array();
	}
}

/**
 * Object to test the migrations.
 */
class Migrations extends externalFilesTests {
	/**
	 * The test service.
	 *
	 * @var Migration_Test_Service|null
	 */
	private ?Migration_Test_Service $service = null;

	/**
	 * The original URI of the request.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Create the tables of the other plugins.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		global $wpdb;

		// the tables of WP Offload Media.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}as3cf_items ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, provider VARCHAR(18) NOT NULL, region VARCHAR(255) NOT NULL, bucket VARCHAR(255) NOT NULL, path VARCHAR(1024) NOT NULL, original_path VARCHAR(1024) NOT NULL, is_private BOOLEAN NOT NULL DEFAULT 0, source_type VARCHAR(18) NOT NULL, source_id BIGINT(20) UNSIGNED NOT NULL, source_path VARCHAR(1024) NOT NULL, original_source_path VARCHAR(1024) NOT NULL, extra_info LONGTEXT, originator TINYINT UNSIGNED NOT NULL DEFAULT 0, is_verified BOOLEAN NOT NULL DEFAULT 1, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}as3cf_files ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, item_id BIGINT(20) UNSIGNED NOT NULL, path VARCHAR(1024) NOT NULL, is_private BOOLEAN NOT NULL DEFAULT 0, private_prefix VARCHAR(1024) NOT NULL DEFAULT '', source_path VARCHAR(1024) NOT NULL, size VARCHAR(255) NOT NULL, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB

		// the table of Stowaway.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stowaway_items ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, attachment_id bigint(20) unsigned NOT NULL, bucket varchar(255) NOT NULL, region varchar(32) NOT NULL, key_prefix varchar(1024) NOT NULL, source_dir varchar(1024) NOT NULL, files longtext NOT NULL, origin varchar(20) NOT NULL DEFAULT 'stowaway', created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB

		// the table of Offload Media – Cloud Storage.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}acoofm_items ( id BIGINT(20) NOT NULL AUTO_INCREMENT, provider varchar(18) NOT NULL, region varchar(255) NOT NULL, bucket varchar(255) NOT NULL, source_id bigint(20) NOT NULL, source_path varchar(1024) NOT NULL, source_type varchar(18) NOT NULL, url varchar(1024) NOT NULL, path varchar(1024) NOT NULL, is_private tinyint(1) NOT NULL DEFAULT 0, extra_info longtext DEFAULT NULL, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Remove the tables of the other plugins.
	 *
	 * @return void
	 */
	public static function tear_down_after_class(): void {
		global $wpdb;
		foreach ( array( 'as3cf_items', 'as3cf_files', 'stowaway_items', 'acoofm_items' ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB
		}
		parent::tear_down_after_class();
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// use an administrator, who could use the test service.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'user_has_cap', array( $this, 'allow_test_service' ) );

		// register the test service.
		$this->service = new Migration_Test_Service();
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_test_service' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'user_has_cap', array( $this, 'allow_test_service' ) );
		remove_filter( 'efml_directory_listing_objects', array( $this, 'add_test_service' ) );
		remove_all_filters( 'efml_migrations' );
		\ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->cancel();
		delete_option( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::COUNT_OPTION );
		if ( ! is_null( $this->request_uri ) ) {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
			$this->request_uri      = null;
		}
		parent::tear_down();
	}

	/**
	 * Allow the test service.
	 *
	 * @param array<string,bool> $caps The capabilities.
	 *
	 * @return array<string,bool>
	 */
	public function allow_test_service( array $caps ): array {
		$caps['efml_cap_test-offload'] = true;
		return $caps;
	}

	/**
	 * Add the test service to the services.
	 *
	 * @param array<int,mixed> $list The services.
	 *
	 * @return array<int,mixed>
	 */
	public function add_test_service( array $list ): array {
		$list[] = $this->service;
		return $list;
	}

	/**
	 * Create an image attachment with sizes.
	 *
	 * @return int
	 */
	private function create_image(): int {
		// create the image.
		$file  = trailingslashit( get_temp_dir() ) . 'efml-migration-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );

		// add it to the media library.
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Add an item of WP Offload Media for the given attachment.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $key           The name of the object.
	 * @param bool   $is_private    Whether it is private.
	 * @param bool   $with_file     Whether the entry of version 3.4 should be added.
	 *
	 * @return void
	 */
	private function add_wpom_item( int $attachment_id, string $key, bool $is_private = false, bool $with_file = true ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'as3cf_items',
			array(
				'provider'             => 'aws',
				'region'               => 'eu-central-1',
				'bucket'               => 'my-bucket',
				'path'                 => $with_file ? '' : $key,
				'original_path'        => '',
				'is_private'           => $is_private ? 1 : 0,
				'source_type'          => 'media-library',
				'source_id'            => $attachment_id,
				'source_path'          => '',
				'original_source_path' => '',
				'extra_info'           => $is_private && ! $with_file ? serialize( array( 'private_prefix' => 'private' ) ) : '', // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			)
		); // phpcs:ignore WordPress.DB
		if ( $with_file ) {
			$wpdb->insert(
				$wpdb->prefix . 'as3cf_files',
				array(
					'item_id'     => $wpdb->insert_id,
					'path'        => $key,
					'is_private'  => $is_private ? 1 : 0,
					'source_path' => '',
					'size'        => '__as3cf_primary',
				)
			); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Create an external source of the test service.
	 *
	 * @return int
	 */
	private function create_source(): int {
		$term_id = Taxonomy::get_instance()->add( 'test-offload', 'https://bucket.example.com/' . wp_generate_password( 6, false ) . '/', array() );
		$this->assertGreaterThan( 0, $term_id );
		return $term_id;
	}

	/**
	 * Test the data of WP Offload Media: the groups, the names of the objects (also of older versions and
	 * private ones) and the access data.
	 *
	 * @return void
	 */
	public function test_wp_offload_media(): void {
		$migration = new WpOffloadMedia();
		$this->add_wpom_item( 11, 'wp-content/uploads/2026/11/a.jpg' );
		$this->add_wpom_item( 12, 'private/wp-content/uploads/2026/11/b.jpg', true );
		$this->add_wpom_item( 13, 'wp-content/uploads/2026/11/c.jpg', true, false );

		// one group with three files.
		$sources = $migration->get_sources();
		$this->assertCount( 1, $sources );
		$this->assertEquals( 3, $sources[0]['count'] );
		$this->assertEquals( 'aws-s3', $migration->get_service_name( $sources[0] ) );

		// the names of the objects, incl. the prefix for private files of older versions.
		$items = $migration->get_items( $sources[0], 0, 10 );
		$this->assertEquals( array( 11, 12, 13 ), array_column( $items, 'attachment_id' ) );
		$this->assertEquals( 'wp-content/uploads/2026/11/a.jpg', $items[0]['key'] );
		$this->assertFalse( $items[0]['private'] );
		$this->assertEquals( 'private/wp-content/uploads/2026/11/b.jpg', $items[1]['key'] );
		$this->assertTrue( $items[1]['private'] );
		$this->assertEquals( 'private/wp-content/uploads/2026/11/c.jpg', $items[2]['key'] );

		// the next part.
		$this->assertEquals( array( 13 ), array_column( $migration->get_items( $sources[0], 12, 10 ), 'attachment_id' ) );

		// the access data.
		update_option(
			'tantan_wordpress_s3',
			array(
				'access-key-id'          => 'KEY',
				'secret-access-key'      => 'SECRET',
				'enable-delivery-domain' => 1,
				'delivery-domain'        => 'cdn.example.com',
			)
		);
		$fields = $migration->get_fields( $sources[0] );
		$this->assertEquals( 'KEY', $fields['access_key'] );
		$this->assertEquals( 'SECRET', $fields['secret'] );
		$this->assertEquals( 'my-bucket', $fields['bucket'] );
		$this->assertEquals( 'eu-central-1', $fields['region'] );
		$this->assertEquals( 'cdn.example.com', $fields['cloudfront_domain'] );
	}

	/**
	 * Test the data of Advanced Media Offloader.
	 *
	 * @return void
	 */
	public function test_advanced_media_offloader(): void {
		$migration = new AdvancedMediaOffloader();
		foreach ( array( 21, 22 ) as $post_id ) {
			update_post_meta( $post_id, 'advmo_offloaded', '1' );
			update_post_meta( $post_id, 'advmo_provider', 'Cloudflare R2' );
			update_post_meta( $post_id, 'advmo_bucket', 'r2-bucket' );
			update_post_meta( $post_id, 'advmo_path', 'wp-content/uploads/2026/11/' );
			update_post_meta( $post_id, '_wp_attached_file', '2026/11/file-' . $post_id . '.jpg' );
		}
		update_option(
			'advmo_credentials',
			array(
				'cloudflare_r2' => array(
					'key'      => 'R2KEY',
					'secret'   => 'R2SECRET',
					'endpoint' => 'https://abc123.r2.cloudflarestorage.com',
					'domain'   => 'https://media.example.com',
				),
			)
		);

		// one group.
		$sources = $migration->get_sources();
		$this->assertCount( 1, $sources );
		$this->assertEquals( 2, $sources[0]['count'] );
		$this->assertEquals( 'cloudflare-r2', $migration->get_service_name( $sources[0] ) );

		// the names of the objects.
		$items = $migration->get_items( $sources[0], 0, 10 );
		$this->assertEquals( 'wp-content/uploads/2026/11/file-21.jpg', $items[0]['key'] );

		// the access data.
		$fields = $migration->get_fields( $sources[0] );
		$this->assertEquals( 'R2KEY', $fields['access_key'] );
		$this->assertEquals( 'abc123', $fields['account_id'] );
		$this->assertEquals( '0', $fields['eu'] );
		$this->assertEquals( 'r2-bucket', $fields['bucket'] );
	}

	/**
	 * Test the data of Stowaway.
	 *
	 * @return void
	 */
	public function test_stowaway(): void {
		global $wpdb;
		$migration = new Stowaway();
		update_post_meta( 31, '_wp_attached_file', '2026/11/photo.jpg' );
		$wpdb->insert(
			$wpdb->prefix . 'stowaway_items',
			array(
				'attachment_id' => 31,
				'bucket'        => 'stow-bucket',
				'region'        => 'eu-west-1',
				'key_prefix'    => 'wp-content/uploads/2026/11/',
				'source_dir'    => '2026/11/',
				'files'         => '["photo.jpg"]',
				'created_at'    => '2026-11-14 10:00:00',
				'updated_at'    => '2026-11-14 10:00:00',
			)
		); // phpcs:ignore WordPress.DB
		update_option(
			'stowaway_credentials',
			array(
				'access_key_id'     => 'SKEY',
				'secret_access_key' => 'SSECRET',
			)
		);

		$sources = $migration->get_sources();
		$this->assertCount( 1, $sources );
		$this->assertEquals( 'aws-s3', $migration->get_service_name( $sources[0] ) );
		$this->assertEquals( 'wp-content/uploads/2026/11/photo.jpg', $migration->get_items( $sources[0], 0, 10 )[0]['key'] );
		$fields = $migration->get_fields( $sources[0] );
		$this->assertEquals( 'SKEY', $fields['access_key'] );
		$this->assertEquals( 'eu-west-1', $fields['region'] );
	}

	/**
	 * Test the data of Offload Media – Cloud Storage.
	 *
	 * @return void
	 */
	public function test_offload_media_cloud_storage(): void {
		global $wpdb;
		$migration = new OffloadMediaCloudStorage();
		$wpdb->insert(
			$wpdb->prefix . 'acoofm_items',
			array(
				'provider'    => 'ocean',
				'region'      => 'fra1',
				'bucket'      => 'space',
				'source_id'   => 41,
				'source_path' => '2026/11/doc.pdf',
				'source_type' => 'media-library',
				'url'         => 'https://space.fra1.digitaloceanspaces.com/2026/11/doc.pdf',
				'path'        => '2026/11/doc.pdf',
				'is_private'  => 0,
			)
		); // phpcs:ignore WordPress.DB
		update_option(
			'acoofm_settings',
			array(
				'credentials' => array(
					'access_key'  => 'DKEY',
					'secret_key'  => 'DSECRET',
					'bucket_name' => 'space',
				),
			)
		);

		$sources = $migration->get_sources();
		$this->assertCount( 1, $sources );
		$this->assertEquals( 'digital-ocean-spaces', $migration->get_service_name( $sources[0] ) );
		$this->assertEquals( '2026/11/doc.pdf', $migration->get_items( $sources[0], 0, 10 )[0]['key'] );
		$fields = $migration->get_fields( $sources[0] );
		$this->assertEquals( 'DSECRET', $fields['secret'] );
		$this->assertEquals( 'fra1', $fields['region'] );
	}

	/**
	 * Test the migration of a single file, the replacement of its local URLs and the revert.
	 *
	 * @return void
	 */
	public function test_migrate_file_and_revert(): void {
		// create an image with sizes.
		$attachment_id = $this->create_image();
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->assertNotEmpty( $metadata['sizes'] );
		$local_file = get_attached_file( $attachment_id );
		$this->assertIsString( $local_file );
		$size       = (array) reset( $metadata['sizes'] );
		$local_url  = wp_get_attachment_url( $attachment_id );
		$size_url   = trailingslashit( dirname( (string) $local_url ) ) . $size['file'];
		$term_id    = $this->create_source();

		// migrate it.
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->migrate_file(
			new WpOffloadMedia(),
			$this->service,
			$term_id,
			array(),
			array(
				'attachment_id' => $attachment_id,
				'key'           => 'wp-content/uploads/' . $attached_file,
				'private'       => true,
			)
		);
		$this->assertEquals( 'done', $result );

		// it is an exported external file with the URL of the object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertTrue( $external_file_obj->is_valid() );
		$this->assertEquals( 'https://bucket.example.com/wp-content/uploads/' . $attached_file, $external_file_obj->get_url( true ) );
		$this->assertTrue( $external_file_obj->is_private() );
		$this->assertEquals( 'test-offload', $external_file_obj->get_service_name() );
		$this->assertEquals( 'wp-content/uploads/' . $attached_file, get_post_meta( $attachment_id, 'efml_test_key', true ) );
		$this->assertEquals( array( $term_id ), wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) ) );
		$this->assertNotEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );

		// the local files are kept.
		$this->assertFileExists( $local_file );
		$this->assertFileExists( trailingslashit( dirname( $local_file ) ) . $size['file'] );

		// the former local files are known.
		$files = get_post_meta( $attachment_id, \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::FILE_META );
		$this->assertContains( $attached_file, $files );
		$this->assertContains( trailingslashit( dirname( $attached_file ) ) . $size['file'], $files );

		// a second migration skips the file.
		$this->assertEquals( 'skipped', \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->migrate_file( new WpOffloadMedia(), $this->service, $term_id, array(), array( 'attachment_id' => $attachment_id, 'key' => 'x.jpg', 'private' => false ) ) );

		// the local URLs in contents are replaced.
		$content = '<p><img src="' . $local_url . '"><img src="' . $size_url . '"><a href="' . str_replace( 'http:', '', (string) $local_url ) . '">x</a> <img src="https://example.com/other.jpg"></p>';
		$output  = (string) \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->replace_urls( $content );
		$this->assertStringNotContainsString( (string) $local_url, $output );
		$this->assertStringNotContainsString( $size_url, $output );
		$this->assertStringContainsString( 'https://example.com/other.jpg', $output );

		// revert the migration.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->revert( $attachment_id ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEquals( $attached_file, get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertEquals( $metadata['sizes'], wp_get_attachment_metadata( $attachment_id )['sizes'] );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_test_key', true ) );
		$this->assertEmpty( wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) ) );
		$this->assertEquals( $local_url, wp_get_attachment_url( $attachment_id ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->revert( $attachment_id ) );
	}

	/**
	 * Test the migration of a file, whose object does not exist.
	 *
	 * @return void
	 */
	public function test_missing_object(): void {
		$attachment_id = $this->create_image();
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );

		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->migrate_file( new WpOffloadMedia(), $this->service, $this->create_source(), array(), array( 'attachment_id' => $attachment_id, 'key' => 'missing.jpg', 'private' => false ) );
		$this->assertEquals( 'failed', $result );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEquals( $attached_file, get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}

	/**
	 * Test the migration of a group in parts and in the background.
	 *
	 * @return void
	 */
	public function test_run(): void {
		add_filter( 'efml_migrations', static fn() => array( Migration_Test_Migration::class ) );
		add_filter( 'efml_migration_batch_size', static fn() => 2 );

		// three files, one of them without object.
		$ids = array( $this->create_image(), $this->create_image(), $this->create_image() );
		$this->add_wpom_item( $ids[0], 'one.jpg' );
		$this->add_wpom_item( $ids[1], 'missing.jpg' );
		$this->add_wpom_item( $ids[2], 'three.jpg' );

		// get the group.
		$sources = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_sources();
		$this->assertCount( 1, $sources );
		$source = reset( $sources );
		$this->assertEquals( 'test-offload', $source['service'] );

		// start it with a deadline in the past: nothing is done directly, the rest runs in the background.
		add_filter( 'efml_import_run_time_limit', '__return_zero' );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 10;
		$result                        = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->start( $source, $this->create_source() );
		remove_filter( 'efml_import_run_time_limit', '__return_zero' );
		$this->assertEquals( 3, $result['total'] );
		$this->assertNotEmpty( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_run() );
		$this->assertNotFalse( wp_next_scheduled( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::CRON_HOOK ) );

		// process it in the background.
		\ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->work( microtime( true ) + 30 );
		$this->assertEmpty( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_run() );
		$this->assertFalse( wp_next_scheduled( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::CRON_HOOK ) );
		$last = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_last_result();
		$this->assertEquals( 2, $last['done'] );
		$this->assertEquals( 1, $last['failed'] );
		$this->assertTrue( Files::get_instance()->get_file( $ids[0] )->is_valid() );
		$this->assertFalse( Files::get_instance()->get_file( $ids[1] )->is_valid() );
		$this->assertTrue( Files::get_instance()->get_file( $ids[2] )->is_valid() );
		$this->assertCount( 2, \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_migrated_attachments() );

		// the hint about the result links to the log, as a file failed.
		$transient_obj = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'efml_migration_finished' );
		$this->assertStringContainsString( '2 files taken over, 0 skipped, 1 failed', $transient_obj->get_message() );
		$this->assertStringContainsString( esc_url( \ExternalFilesInMediaLibrary\Plugin\Helper::get_log_url() ), $transient_obj->get_message() );
		$transient_obj->delete();
	}

	/**
	 * Test the hint, if the migration is cancelled in the background.
	 *
	 * @return void
	 */
	public function test_cancelled_run(): void {
		add_filter( 'efml_migrations', static fn() => array( Migration_Test_Migration::class ) );
		$attachment_id = $this->create_image();
		$this->add_wpom_item( $attachment_id, 'one.jpg' );
		$sources = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_sources();

		// start it with a deadline in the past, so it runs in the background.
		add_filter( 'efml_import_run_time_limit', '__return_zero' );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 10;
		\ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->start( reset( $sources ), $this->create_source() );
		remove_filter( 'efml_import_run_time_limit', '__return_zero' );

		// the service is not available anymore.
		remove_filter( 'efml_directory_listing_objects', array( $this, 'add_test_service' ) );
		\ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->work( microtime( true ) + 30 );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_test_service' ) );

		// the migration has been cancelled with a hint, which links to the log.
		$this->assertEmpty( \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance()->get_run() );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$transient_obj = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'efml_migration_finished' );
		$this->assertEquals( 'error', $transient_obj->get_type() );
		$this->assertStringContainsString( 'has been cancelled', $transient_obj->get_message() );
		$this->assertStringContainsString( esc_url( \ExternalFilesInMediaLibrary\Plugin\Helper::get_log_url() ), $transient_obj->get_message() );
		$transient_obj->delete();
	}

	/**
	 * Test the hint in the quickstart, if another offload plugin is active, but has not offloaded any files.
	 *
	 * @return void
	 */
	public function test_quickstart_hint_without_files(): void {
		add_filter( 'efml_migrations', static fn() => array( Migration_Test_Migration::class ) );
		// mark the requests as requests of the setup.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . Setup::NAME;
		Setup::get_instance()->clear_answers();
		Setup::get_instance()->init_setup();
		$request = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . Setup::NAME );
		$request->set_param( 'config_name', Setup::NAME );

		// without files and without the active plugin: neither goal nor hint.
		$this->assertArrayNotHasKey( 'migrate', Setup::get_instance()->get_goals() );
		$steps = \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
		$this->assertArrayNotHasKey( 'migration_hint', reset( $steps ) );

		// without files, but with the active plugin: no goal, but the hint.
		$active = static fn() => array( 'amazon-s3-and-cloudfront/wordpress-s3.php' );
		add_filter( 'pre_option_active_plugins', $active );
		Setup::get_instance()->init_setup();
		Setup::get_instance()->prepare_check( null, null, $request );
		$this->assertArrayNotHasKey( 'migrate', Setup::get_instance()->get_goals() );
		$steps = \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
		$step  = reset( $steps );
		$this->assertArrayHasKey( 'migration_hint', $step );
		$this->assertStringContainsString( 'WP Offload Media is active, but has not offloaded any files', $step['migration_hint']['text'] );

		// with files: the goal, but no hint.
		$this->add_wpom_item( $this->create_image(), 'hint.jpg' );
		Setup::get_instance()->init_setup();
		Setup::get_instance()->prepare_check( null, null, $request );
		$this->assertArrayHasKey( 'migrate', Setup::get_instance()->get_goals() );
		$steps = \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
		$this->assertArrayNotHasKey( 'migration_hint', reset( $steps ) );
		remove_filter( 'pre_option_active_plugins', $active );
		Setup::get_instance()->clear_answers();
	}

	/**
	 * Test the quickstart with the goal to switch from another offload plugin.
	 *
	 * @return void
	 */
	public function test_quickstart(): void {
		add_filter( 'efml_migrations', static fn() => array( Migration_Test_Migration::class ) );
		$attachment_id = $this->create_image();
		$this->add_wpom_item( $attachment_id, 'quick.jpg' );

		// mark the requests as requests of the setup.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . Setup::NAME;
		Setup::get_instance()->clear_answers();
		Setup::get_instance()->init_setup();
		$setup_obj = \easySetupForWordPress\Setup::get_instance();
		$request   = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . Setup::NAME );
		$request->set_param( 'config_name', Setup::NAME );

		// the goal is offered.
		$this->assertArrayHasKey( 'migrate', Setup::get_instance()->get_goals() );

		// the files are offered instead of the services.
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_GOAL, 'migrate' );
		Setup::get_instance()->prepare_check( null, null, $request );
		$steps   = $setup_obj->get_fields( $request );
		$options = $steps[2][ Setup::FIELD_MIGRATION_SOURCE ]['options'];
		$this->assertCount( 1, $options );

		// choose them: the connection is checked and the migration is described.
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_MIGRATION_SOURCE, $options[0]['value'] );
		Setup::get_instance()->prepare_check( null, null, $request );
		$steps = $setup_obj->get_fields( $request );
		$this->assertStringContainsString( 'efml-setup-check-success', $steps[4]['check']['text'] );
		$this->assertArrayHasKey( 'migration', $steps[4] );

		// run the process.
		Setup::get_instance()->run_process( Setup::NAME );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEquals( 'https://bucket.example.com/quick.jpg', Files::get_instance()->get_file( $attachment_id )->get_url( true ) );
		Setup::get_instance()->clear_answers();
	}

	/**
	 * Test the quickstart with files, whose service is provided by an extension, which is not installed.
	 *
	 * @return void
	 */
	public function test_quickstart_with_extension(): void {
		add_filter( 'efml_migrations', static fn() => array( WpOffloadMedia::class ) );
		$this->add_wpom_item( $this->create_image(), 'extension.jpg' );

		// mark the requests as requests of the setup.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . Setup::NAME;
		Setup::get_instance()->clear_answers();
		Setup::get_instance()->init_setup();
		$setup_obj = \easySetupForWordPress\Setup::get_instance();
		$request   = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . Setup::NAME );
		$request->set_param( 'config_name', Setup::NAME );

		// the files are offered with the installation of the extension for AWS S3.
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_GOAL, 'migrate' );
		Setup::get_instance()->prepare_check( null, null, $request );
		$steps   = $setup_obj->get_fields( $request );
		$options = $steps[2][ Setup::FIELD_MIGRATION_SOURCE ]['options'];
		$this->assertCount( 1, $options );
		$this->assertStringContainsString( 'needs the free extension', $options[0]['description'] );

		// choose them: the installation of the extension follows.
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_MIGRATION_SOURCE, $options[0]['value'] );
		Setup::get_instance()->prepare_check( null, null, $request );
		$steps = $setup_obj->get_fields( $request );
		$this->assertArrayHasKey( Setup::FIELD_INSTALL, $steps[3] );

		// without the permission to install plugins, only a hint is shown.
		$deny = static function ( array $caps ): array {
			$caps['install_plugins'] = false;
			return $caps;
		};
		add_filter( 'user_has_cap', $deny );
		Setup::get_instance()->init_setup();
		Setup::get_instance()->prepare_check( null, null, $request );
		$steps = $setup_obj->get_fields( $request );
		$this->assertEmpty( $steps[2][ Setup::FIELD_MIGRATION_SOURCE ]['options'] );
		$this->assertStringContainsString( 'Please ask your administrator to install it.', $steps[2]['help']['text'] );
		remove_filter( 'user_has_cap', $deny );
		Setup::get_instance()->clear_answers();
	}

}

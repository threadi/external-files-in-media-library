<?php
/**
 * File to read the offloaded files of Advanced Media Offloader.
 *
 * The files are marked via post meta on each attachment: "advmo_offloaded", "advmo_provider" (the name of the
 * provider), "advmo_bucket" and "advmo_path" (the prefix of the object, the name of the file is added to it).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Migrations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to read the data of Advanced Media Offloader.
 */
class AdvancedMediaOffloader extends Migration_Base {
	/**
	 * The internal name of this migration.
	 *
	 * @var string
	 */
	protected string $name = 'advanced-media-offloader';

	/**
	 * The name of the other plugin.
	 *
	 * @var string
	 */
	protected string $label = 'Advanced Media Offloader';

	/**
	 * List of possible main files of the other plugin.
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_files = array(
		'advanced-media-offloader/advanced-media-offloader.php',
	);

	/**
	 * Return the providers of the other plugin by the name it saves on the attachments: the key of its access data
	 * and the name of our service.
	 *
	 * @return array<string,array{key: string, service: string}>
	 */
	private function get_providers(): array {
		return array(
			'Amazon S3'           => array(
				'key'     => 'aws',
				'service' => 'aws-s3',
			),
			'Cloudflare R2'       => array(
				'key'     => 'cloudflare_r2',
				'service' => 'cloudflare-r2',
			),
			'Backblaze B2'        => array(
				'key'     => 'backblaze_b2',
				'service' => 'backblaze-s3',
			),
			'DigitalOcean Spaces' => array(
				'key'     => 'dos',
				'service' => 'digital-ocean-spaces',
			),
			'Wasabi'              => array(
				'key'     => 'wasabi',
				'service' => 'wasabi',
			),
		);
	}

	/**
	 * Return the provider of the other plugin for the given name.
	 *
	 * The name of S3-compatible storages contains further examples, so it is checked by its beginning.
	 *
	 * @param string $name The name.
	 *
	 * @return array{key: string, service: string}|null
	 */
	private function get_provider( string $name ): ?array {
		// return the known provider.
		$providers = $this->get_providers();
		if ( isset( $providers[ $name ] ) ) {
			return $providers[ $name ];
		}

		// return the S3-compatible storage.
		if ( str_starts_with( $name, 'Any S3' ) ) {
			return array(
				'key'     => 'minio',
				'service' => 's3-compatible',
			);
		}

		// return null for unknown providers.
		return null;
	}

	/**
	 * Return the groups of offloaded files of the other plugin, one per provider and bucket.
	 *
	 * @return array<int,array{provider: string, bucket: string, region: string, count: int}>
	 */
	public function get_sources(): array {
		global $wpdb;

		// get the groups.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT p.`meta_value` AS `provider`, b.`meta_value` AS `bucket`, COUNT(*) AS `count` FROM %i o INNER JOIN %i p ON p.`post_id` = o.`post_id` AND p.`meta_key` = %s INNER JOIN %i b ON b.`post_id` = o.`post_id` AND b.`meta_key` = %s WHERE o.`meta_key` = %s AND o.`meta_value` = %s GROUP BY p.`meta_value`, b.`meta_value`', $wpdb->postmeta, $wpdb->postmeta, 'advmo_provider', $wpdb->postmeta, 'advmo_bucket', 'advmo_offloaded', '1' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery

		// collect them.
		$list = array();
		foreach ( (array) $results as $result ) {
			$list[] = array(
				'provider' => (string) $result['provider'],
				'bucket'   => (string) $result['bucket'],
				'region'   => '',
				'count'    => absint( $result['count'] ),
			);
		}

		// return the list.
		return $list;
	}

	/**
	 * Return the name of the service of this plugin, which is used for the given group.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return string
	 */
	public function get_service_name( array $source ): string {
		$provider = $this->get_provider( $source['provider'] );
		return is_array( $provider ) ? $provider['service'] : '';
	}

	/**
	 * Return a value of the access data of the other plugin: from its constant, otherwise from the database.
	 *
	 * @param string $key   The key of the provider.
	 * @param string $field The name of the field.
	 *
	 * @return string
	 */
	private function get_credential( string $key, string $field ): string {
		// get the value of the constant (e.g. ADVMO_CLOUDFLARE_R2_KEY).
		$constant = 'ADVMO_' . strtoupper( $key ) . '_' . strtoupper( $field );
		if ( defined( $constant ) ) {
			$value = constant( $constant );
			if ( is_bool( $value ) ) {
				return $value ? '1' : '0';
			}
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		// get the value from the database.
		$credentials = get_option( 'advmo_credentials', array() );
		if ( ! is_array( $credentials ) || ! isset( $credentials[ $key ] ) || ! is_array( $credentials[ $key ] ) ) {
			return '';
		}

		// return the value.
		return $this->get_string( $credentials[ $key ], $field );
	}

	/**
	 * Return the values for the fields of the service of this plugin.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return array<string,string>
	 */
	public function get_fields( array $source ): array {
		// get the provider.
		$provider = $this->get_provider( $source['provider'] );

		// bail if the provider is unknown.
		if ( is_null( $provider ) ) {
			return array();
		}

		// get the common values.
		$key      = $provider['key'];
		$endpoint = $this->get_credential( $key, 'endpoint' );
		$host     = $this->get_host( $endpoint );
		$fields   = array(
			'access_key' => $this->get_credential( $key, 'key' ),
			'secret'     => $this->get_credential( $key, 'secret' ),
			'bucket'     => $source['bucket'],
		);

		// add the values of the provider.
		switch ( $provider['service'] ) {
			case 'aws-s3':
				$fields['region']            = $this->get_credential( $key, 'region' );
				$fields['cloudfront_domain'] = $this->get_host( $this->get_credential( $key, 'domain' ) );
				break;
			case 'cloudflare-r2':
				// the account ID is the first part of the endpoint (e.g. "<account>.r2.cloudflarestorage.com").
				$fields['account_id']    = (string) strtok( $host, '.' );
				$fields['eu']            = str_contains( $host, '.eu.r2.' ) ? '1' : '0';
				$fields['public_domain'] = $this->get_credential( $key, 'domain' );
				break;
			case 'backblaze-s3':
				$fields['region']        = '' !== $this->get_credential( $key, 'region' ) ? $this->get_credential( $key, 'region' ) : (string) preg_replace( '/^s3\.([^.]+)\..*$/', '$1', $host );
				$fields['public_domain'] = $this->get_credential( $key, 'domain' );
				break;
			case 'digital-ocean-spaces':
				// the region is the first part of the endpoint (e.g. "fra1.digitaloceanspaces.com").
				$fields['region']        = (string) strtok( $host, '.' );
				$fields['public_domain'] = $this->get_credential( $key, 'domain' );
				break;
			case 'wasabi':
				$fields['region'] = $this->get_credential( $key, 'region' );
				break;
			case 's3-compatible':
				$fields['endpoint']   = $endpoint;
				$fields['region']     = $this->get_credential( $key, 'region' );
				$fields['path_style'] = '1' === $this->get_credential( $key, 'path_style_endpoint' ) ? '1' : '0';
				$fields['public_url'] = $this->get_credential( $key, 'domain' );
				break;
		}

		// return the fields.
		return $fields;
	}

	/**
	 * Return the next offloaded files of the given group, ordered by their attachment ID.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source  The group.
	 * @param int                                                                 $last_id The last attachment ID of the previous call.
	 * @param int                                                                 $limit   The max. amount of entries.
	 *
	 * @return array<int,array{attachment_id: int, key: string, private: bool}>
	 */
	public function get_items( array $source, int $last_id, int $limit ): array {
		global $wpdb;

		// get the entries with their prefix and their file.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT o.`post_id`, pa.`meta_value` AS `path`, f.`meta_value` AS `file` FROM %i o INNER JOIN %i p ON p.`post_id` = o.`post_id` AND p.`meta_key` = %s AND p.`meta_value` = %s INNER JOIN %i b ON b.`post_id` = o.`post_id` AND b.`meta_key` = %s AND b.`meta_value` = %s LEFT JOIN %i pa ON pa.`post_id` = o.`post_id` AND pa.`meta_key` = %s LEFT JOIN %i f ON f.`post_id` = o.`post_id` AND f.`meta_key` = %s WHERE o.`meta_key` = %s AND o.`meta_value` = %s AND o.`post_id` > %d ORDER BY o.`post_id` LIMIT %d', $wpdb->postmeta, $wpdb->postmeta, 'advmo_provider', $source['provider'], $wpdb->postmeta, 'advmo_bucket', $source['bucket'], $wpdb->postmeta, 'advmo_path', $wpdb->postmeta, '_wp_attached_file', 'advmo_offloaded', '1', $last_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery

		// collect the entries.
		$list = array();
		foreach ( (array) $results as $result ) {
			$list[] = array(
				'attachment_id' => absint( $result['post_id'] ),
				'key'           => ltrim( (string) $result['path'] . wp_basename( (string) $result['file'] ), '/' ),
				'private'       => false,
			);
		}

		// return the list.
		return $list;
	}
}

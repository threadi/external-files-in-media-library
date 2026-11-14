<?php
/**
 * File to read the offloaded files of Stowaway.
 *
 * The files are saved in the table "stowaway_items" with bucket, region and the prefix of their objects. The name of
 * the file is added to the prefix. Stowaway only supports AWS S3.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Migrations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to read the data of Stowaway.
 */
class Stowaway extends Migration_Base {
	/**
	 * The internal name of this migration.
	 *
	 * @var string
	 */
	protected string $name = 'stowaway';

	/**
	 * The name of the other plugin.
	 *
	 * @var string
	 */
	protected string $label = 'Stowaway';

	/**
	 * List of possible main files of the other plugin.
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_files = array(
		'stowaway/stowaway.php',
	);

	/**
	 * Return the groups of offloaded files of the other plugin, one per bucket and region.
	 *
	 * @return array<int,array{provider: string, bucket: string, region: string, count: int}>
	 */
	public function get_sources(): array {
		global $wpdb;

		// get the table.
		$table = $this->get_table( 'stowaway_items' );

		// bail if the table does not exist.
		if ( '' === $table ) {
			return array();
		}

		// get the groups.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `bucket`, `region`, COUNT(*) AS `count` FROM %i GROUP BY `bucket`, `region`', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// collect them.
		$list = array();
		foreach ( (array) $results as $result ) {
			$list[] = array(
				'provider' => 'aws',
				'bucket'   => (string) $result['bucket'],
				'region'   => (string) $result['region'],
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
		return 'aws-s3';
	}

	/**
	 * Return the values for the fields of the service of this plugin.
	 *
	 * The access keys are used from the constants of Stowaway, otherwise from the database, otherwise from the
	 * constant of WP Offload Media (like Stowaway does it).
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return array<string,string>
	 */
	public function get_fields( array $source ): array {
		// get the settings.
		$settings = get_option( 'stowaway_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// get the access keys from the constants.
		if ( defined( 'STOWAWAY_ACCESS_KEY_ID' ) && defined( 'STOWAWAY_SECRET_ACCESS_KEY' ) ) {
			$access_key = (string) constant( 'STOWAWAY_ACCESS_KEY_ID' );
			$secret     = (string) constant( 'STOWAWAY_SECRET_ACCESS_KEY' );
		} else {
			// get them from the database.
			$credentials = get_option( 'stowaway_credentials', array() );
			if ( ! is_array( $credentials ) ) {
				$credentials = array();
			}
			$access_key = $this->get_string( $credentials, 'access_key_id' );
			$secret     = $this->get_string( $credentials, 'secret_access_key' );

			// get them from the constant of WP Offload Media.
			if ( '' === $access_key ) {
				$wpom       = $this->get_constant_settings( 'AS3CF_SETTINGS' );
				$access_key = $this->get_string( $wpom, 'access-key-id' );
				$secret     = $this->get_string( $wpom, 'secret-access-key' );
			}
		}

		// return the fields.
		return array(
			'access_key'        => $access_key,
			'secret'            => $secret,
			'bucket'            => $source['bucket'],
			'region'            => $source['region'],
			'cloudfront_domain' => $this->get_host( $this->get_string( $settings, 'delivery_domain' ) ),
		);
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

		// get the table.
		$table = $this->get_table( 'stowaway_items' );

		// bail if the table does not exist.
		if ( '' === $table ) {
			return array();
		}

		// get the entries with their file.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT i.`attachment_id`, i.`key_prefix`, f.`meta_value` AS `file` FROM %i i LEFT JOIN %i f ON f.`post_id` = i.`attachment_id` AND f.`meta_key` = %s WHERE i.`bucket` = %s AND i.`region` = %s AND i.`attachment_id` > %d ORDER BY i.`attachment_id` LIMIT %d', $table, $wpdb->postmeta, '_wp_attached_file', $source['bucket'], $source['region'], $last_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// collect the entries.
		$list = array();
		foreach ( (array) $results as $result ) {
			$list[] = array(
				'attachment_id' => absint( $result['attachment_id'] ),
				'key'           => (string) $result['key_prefix'] . wp_basename( (string) $result['file'] ),
				'private'       => false,
			);
		}

		// return the list.
		return $list;
	}
}

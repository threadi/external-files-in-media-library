<?php
/**
 * File to read the offloaded files of WP Offload Media (also known as "Amazon S3 and CloudFront").
 *
 * The files are saved in the table "as3cf_items" (provider, region, bucket and attachment) and since version 3.4 in
 * the table "as3cf_files" (the name of the object for each size). Before 3.4, the name of the object is saved in
 * "as3cf_items" itself.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Migrations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to read the data of WP Offload Media.
 */
class WpOffloadMedia extends Migration_Base {
	/**
	 * The internal name of this migration.
	 *
	 * @var string
	 */
	protected string $name = 'wp-offload-media';

	/**
	 * The name of the other plugin.
	 *
	 * @var string
	 */
	protected string $label = 'WP Offload Media';

	/**
	 * List of possible main files of the other plugin (e.g. of its free and its pro version).
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_files = array(
		'amazon-s3-and-cloudfront/wordpress-s3.php',
		'amazon-s3-and-cloudfront-pro/amazon-s3-and-cloudfront-pro.php',
	);

	/**
	 * Return the groups of offloaded files of the other plugin, one per provider, bucket and region.
	 *
	 * @return array<int,array{provider: string, bucket: string, region: string, count: int}>
	 */
	public function get_sources(): array {
		global $wpdb;

		// get the table.
		$table = $this->get_table( 'as3cf_items' );

		// bail if the table does not exist.
		if ( '' === $table ) {
			return array();
		}

		// get the groups.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `provider`, `bucket`, `region`, COUNT(*) AS `count` FROM %i WHERE `source_type` = %s GROUP BY `provider`, `bucket`, `region`', $table, 'media-library' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// collect them.
		$list = array();
		foreach ( (array) $results as $result ) {
			$list[] = array(
				'provider' => (string) $result['provider'],
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
		return match ( $source['provider'] ) {
			'aws' => 'aws-s3',
			'do' => 'digital-ocean-spaces',
			'gcp' => 'google-cloud-storage',
			default => '',
		};
	}

	/**
	 * Return the settings of the other plugin: from the constant AS3CF_SETTINGS, otherwise from the database.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		// get the settings from the database (on multisites the settings of the network are used).
		$settings = is_multisite() ? get_site_option( 'tantan_wordpress_s3', array() ) : get_option( 'tantan_wordpress_s3', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// the values of the constant win.
		return array_merge( $settings, $this->get_constant_settings( 'AS3CF_SETTINGS' ) );
	}

	/**
	 * Return the values for the fields of the service of this plugin.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return array<string,string>
	 */
	public function get_fields( array $source ): array {
		// get the settings.
		$settings = $this->get_settings();

		// get the domain for the delivery, if it is enabled.
		$domain = ! empty( $settings['enable-delivery-domain'] ) ? $this->get_host( $this->get_string( $settings, 'delivery-domain' ) ) : '';

		// return the fields for the service.
		return match ( $source['provider'] ) {
			'aws' => array(
				'access_key'        => $this->get_string( $settings, 'access-key-id' ),
				'secret'            => $this->get_string( $settings, 'secret-access-key' ),
				'bucket'            => $source['bucket'],
				'region'            => $source['region'],
				'cloudfront_domain' => $domain,
			),
			'do' => array(
				'access_key'    => $this->get_string( $settings, 'access-key-id' ),
				'secret'        => $this->get_string( $settings, 'secret-access-key' ),
				'bucket'        => $source['bucket'],
				'region'        => $source['region'],
				'public_domain' => $domain,
			),
			'gcp' => array(
				'authentication_json' => $this->get_key_file( $settings ),
				'bucket'              => $source['bucket'],
			),
			default => array(),
		};
	}

	/**
	 * Return the key of the Google service account as JSON.
	 *
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return string
	 */
	private function get_key_file( array $settings ): string {
		// use the saved key.
		if ( ! empty( $settings['key-file'] ) ) {
			// return it as JSON, if it is saved as array.
			if ( is_array( $settings['key-file'] ) ) {
				return (string) wp_json_encode( $settings['key-file'] );
			}

			// return it, if it is a string.
			if ( is_string( $settings['key-file'] ) ) {
				return $settings['key-file'];
			}
		}

		// otherwise use the file.
		return $this->get_key_file_content( $this->get_string( $settings, 'key-file-path' ) );
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

		// get the tables.
		$items_table = $this->get_table( 'as3cf_items' );
		$files_table = $this->get_table( 'as3cf_files' );

		// bail if the table of the items does not exist.
		if ( '' === $items_table ) {
			return array();
		}

		// get the entries with the main file from the table of the files (since 3.4), otherwise from the items.
		if ( '' !== $files_table ) {
			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT i.`source_id`, i.`path`, i.`is_private`, i.`extra_info`, f.`path` AS `file_path`, f.`is_private` AS `file_is_private` FROM %i i LEFT JOIN %i f ON f.`item_id` = i.`id` AND f.`size` = %s WHERE i.`source_type` = %s AND i.`provider` = %s AND i.`bucket` = %s AND i.`region` = %s AND i.`source_id` > %d ORDER BY i.`source_id` LIMIT %d', $items_table, $files_table, '__as3cf_primary', 'media-library', $source['provider'], $source['bucket'], $source['region'], $last_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `source_id`, `path`, `is_private`, `extra_info` FROM %i WHERE `source_type` = %s AND `provider` = %s AND `bucket` = %s AND `region` = %s AND `source_id` > %d ORDER BY `source_id` LIMIT %d', $items_table, 'media-library', $source['provider'], $source['bucket'], $source['region'], $last_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// collect the entries.
		$list = array();
		foreach ( (array) $results as $result ) {
			// use the entry of the table of the files, which contains the name with the prefix for private files.
			if ( ! empty( $result['file_path'] ) ) {
				$list[] = array(
					'attachment_id' => absint( $result['source_id'] ),
					'key'           => (string) $result['file_path'],
					'private'       => ! empty( $result['file_is_private'] ),
				);
				continue;
			}

			// get the name and the private state of older versions.
			$key     = (string) $result['path'];
			$private = ! empty( $result['is_private'] );

			// add the prefix for private files of older versions.
			if ( $private ) {
				$extra_info = maybe_unserialize( (string) $result['extra_info'] );
				if ( is_array( $extra_info ) && ! empty( $extra_info['private_prefix'] ) && is_string( $extra_info['private_prefix'] ) ) {
					$key = trailingslashit( $extra_info['private_prefix'] ) . $key;
				}
			}

			// add the entry.
			$list[] = array(
				'attachment_id' => absint( $result['source_id'] ),
				'key'           => $key,
				'private'       => $private,
			);
		}

		// return the list.
		return $list;
	}
}

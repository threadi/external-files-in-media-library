<?php
/**
 * File to read the offloaded files of Offload Media – Cloud Storage.
 *
 * The files are saved in the table "acoofm_items" with provider, region, bucket, attachment and the name of the
 * object. The access data are saved in the option "acoofm_settings" or in the constant ACOOFM_SETTINGS.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Migrations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to read the data of Offload Media – Cloud Storage.
 */
class OffloadMediaCloudStorage extends Migration_Base {
	/**
	 * The internal name of this migration.
	 *
	 * @var string
	 */
	protected string $name = 'offload-media-cloud-storage';

	/**
	 * The name of the other plugin.
	 *
	 * @var string
	 */
	protected string $label = 'Offload Media – Cloud Storage';

	/**
	 * List of possible main files of the other plugin.
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_files = array(
		'offload-media-cloud-storage/start.php',
	);

	/**
	 * Return the groups of offloaded files of the other plugin, one per provider, bucket and region.
	 *
	 * @return array<int,array{provider: string, bucket: string, region: string, count: int}>
	 */
	public function get_sources(): array {
		global $wpdb;

		// get the table.
		$table = $this->get_table( 'acoofm_items' );

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
			's3' => 'aws-s3',
			'ocean' => 'digital-ocean-spaces',
			'r2' => 'cloudflare-r2',
			'minio' => 's3-compatible',
			'google' => 'google-cloud-storage',
			default => '',
		};
	}

	/**
	 * Return the access data of the other plugin: from its constant, if it is configured to use it, otherwise from
	 * the database.
	 *
	 * @return array<string,mixed>
	 */
	private function get_credentials(): array {
		// get the settings.
		$settings = get_option( 'acoofm_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// get the access data.
		$credentials = isset( $settings['credentials'] ) && is_array( $settings['credentials'] ) ? $settings['credentials'] : array();

		// bail if the constant is not used.
		if ( 'wp_config' !== $this->get_string( $credentials, 'connection_method' ) ) {
			return $credentials;
		}

		// get the values from the constant, which uses other names.
		$constant = $this->get_constant_settings( 'ACOOFM_SETTINGS' );
		return array_merge(
			$credentials,
			array_filter(
				array(
					'access_key' => $this->get_string( $constant, 'access-key-id' ),
					'secret_key' => $this->get_string( $constant, 'secret-access-key' ),
					'endpoint'   => $this->get_string( $constant, 'endpoint' ),
					'account_id' => $this->get_string( $constant, 'account-id' ),
					'file_path'  => $this->get_string( $constant, 'key-file-path' ),
				)
			)
		);
	}

	/**
	 * Return the values for the fields of the service of this plugin.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return array<string,string>
	 */
	public function get_fields( array $source ): array {
		// get the access data.
		$credentials = $this->get_credentials();

		// return the fields of Google Cloud Storage.
		if ( 'google' === $source['provider'] ) {
			return array(
				'authentication_json' => $this->get_key_file_content( $this->get_string( $credentials, 'file_path' ) ),
				'bucket'              => $source['bucket'],
			);
		}

		// get the common fields.
		$fields = array(
			'access_key' => $this->get_string( $credentials, 'access_key' ),
			'secret'     => $this->get_string( $credentials, 'secret_key' ),
			'bucket'     => $source['bucket'],
		);

		// add the fields of the provider.
		switch ( $source['provider'] ) {
			case 's3':
			case 'ocean':
				$fields['region'] = $source['region'];
				break;
			case 'r2':
				$fields['account_id'] = $this->get_string( $credentials, 'account_id' );
				break;
			case 'minio':
				$fields['endpoint'] = $this->get_string( $credentials, 'endpoint' );
				$fields['region']   = $source['region'];
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

		// get the table.
		$table = $this->get_table( 'acoofm_items' );

		// bail if the table does not exist.
		if ( '' === $table ) {
			return array();
		}

		// get the entries.
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `source_id`, `path`, `is_private` FROM %i WHERE `source_type` = %s AND `provider` = %s AND `bucket` = %s AND `region` = %s AND `source_id` > %d ORDER BY `source_id` LIMIT %d', $table, 'media-library', $source['provider'], $source['bucket'], $source['region'], $last_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// collect the entries.
		$list = array();
		foreach ( (array) $results as $result ) {
			$list[] = array(
				'attachment_id' => absint( $result['source_id'] ),
				'key'           => ltrim( (string) $result['path'], '/' ),
				'private'       => ! empty( $result['is_private'] ),
			);
		}

		// return the list.
		return $list;
	}
}

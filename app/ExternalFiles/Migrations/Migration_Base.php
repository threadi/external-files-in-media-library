<?php
/**
 * File as base for each migration from another offload plugin.
 *
 * A migration reads the data of another offload plugin directly from the database. It does not use any class of the
 * other plugin, so it also works if the other plugin is not active anymore. Its data is never changed.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Migrations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object as base for each migration.
 */
abstract class Migration_Base {
	/**
	 * The internal name of this migration.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The name of the other plugin.
	 *
	 * @var string
	 */
	protected string $label = '';

	/**
	 * List of possible main files of the other plugin (e.g. of its free and its pro version).
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_files = array();

	/**
	 * Return the internal name of this migration.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the name of the other plugin.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Return the main file of the other plugin, if it is active, otherwise an empty string.
	 *
	 * @return string
	 */
	public function get_active_plugin_file(): string {
		// load the functions to check the plugins, if they are not loaded.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php'; // @phpstan-ignore requireOnce.fileNotFound
		}

		// check each possible file.
		foreach ( $this->plugin_files as $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				return $plugin_file;
			}
		}

		// return empty string if the plugin is not active.
		return '';
	}

	/**
	 * Return the groups of offloaded files of the other plugin, one per provider, bucket and region.
	 *
	 * @return array<int,array{provider: string, bucket: string, region: string, count: int}>
	 */
	abstract public function get_sources(): array;

	/**
	 * Return the name of the service of this plugin, which is used for the given group.
	 *
	 * Returns an empty string, if the provider is not supported.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return string
	 */
	abstract public function get_service_name( array $source ): string;

	/**
	 * Return the values for the fields of the service of this plugin, as far as they are known from the
	 * settings of the other plugin (e.g. bucket, region and access data).
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source The group.
	 *
	 * @return array<string,string>
	 */
	abstract public function get_fields( array $source ): array;

	/**
	 * Return the next offloaded files of the given group, ordered by their attachment ID.
	 *
	 * @param array{provider: string, bucket: string, region: string, count: int} $source  The group.
	 * @param int                                                                 $last_id The last attachment ID of the previous call.
	 * @param int                                                                 $limit   The max. amount of entries.
	 *
	 * @return array<int,array{attachment_id: int, key: string, private: bool}>
	 */
	abstract public function get_items( array $source, int $last_id, int $limit ): array;

	/**
	 * Return the full name of a table of the actual website, if it exists, otherwise an empty string.
	 *
	 * @param string $name The name without prefix.
	 *
	 * @return string
	 */
	protected function get_table( string $name ): string {
		global $wpdb;

		// return the full name, if the table exists (checked each time, as the other plugin could remove it).
		return Helper::is_table_available( $name, false ) ? $wpdb->prefix . $name : '';
	}

	/**
	 * Return the value of a constant, which contains serialized settings (e.g. AS3CF_SETTINGS), as array.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_constant_settings( string $name ): array {
		// bail if the constant is not defined.
		if ( ! defined( $name ) ) {
			return array();
		}

		// get its value.
		$value = constant( $name );

		// unserialize it, if it is a string, without allowing any objects.
		if ( is_string( $value ) ) {
			$value = unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		}

		// return the settings, if they are an array.
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Return a value of the given array as string.
	 *
	 * @param array<mixed> $values The values.
	 * @param string       $key    The key.
	 *
	 * @return string
	 */
	protected function get_string( array $values, string $key ): string {
		return isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? trim( (string) $values[ $key ] ) : '';
	}

	/**
	 * Return the host of the given URL or domain (e.g. "cdn.example.com" for "https://cdn.example.com/").
	 *
	 * @param string $url The URL or domain.
	 *
	 * @return string
	 */
	protected function get_host( string $url ): string {
		// bail if empty.
		if ( '' === $url ) {
			return '';
		}

		// add a scheme, if it is missing.
		if ( ! str_contains( $url, '://' ) ) {
			$url = 'https://' . $url;
		}

		// return the host.
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	/**
	 * Return the content of a JSON file with the key of a Google service account, if it is readable.
	 *
	 * @param string $path The absolute path to the file.
	 *
	 * @return string
	 */
	protected function get_key_file_content( string $path ): string {
		// bail if the file is not readable or no JSON file.
		if ( '' === $path || ! str_ends_with( strtolower( $path ), '.json' ) || ! is_readable( $path ) ) {
			return '';
		}

		// get the content.
		$content = Helper::get_wp_filesystem()->get_contents( $path );

		// return the content, if it is valid JSON.
		return is_string( $content ) && is_array( json_decode( $content, true ) ) ? $content : '';
	}
}

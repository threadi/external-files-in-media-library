<?php
/**
 * This file contains the abilities this plugin provides via the Abilities API of WordPress.
 *
 * They are the interface for AI agents (e.g., via the MCP Adapter) to manage external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Init;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use Throwable;
use WP_Error;
use WP_Query;
use WP_Term;

/**
 * Object to handle the abilities of this plugin.
 */
class Abilities {
	/**
	 * The prefix for the names of all abilities of this plugin.
	 */
	public const PREFIX = 'external-files-in-media-library/';

	/**
	 * The max amount of files we return in a single response.
	 */
	private const MAX_FILES_IN_RESPONSE = 50;

	/**
	 * Instance of actual object.
	 *
	 * @var Abilities|null
	 */
	private static ?Abilities $instance = null;

	/**
	 * The ID of the user the services have been initialized for, -1 if this has not been done yet.
	 *
	 * @var int
	 */
	private int $services_initialized_for = -1;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Abilities
	 */
	public static function get_instance(): Abilities {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'add_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'add_abilities' ) );
		add_action( 'wp_before_execute_ability', array( $this, 'init_services' ) );
	}

	/**
	 * Initialize the services for the actual user before one of our abilities is executed.
	 *
	 * Some services (e.g., DropBox or ZIP) only register their hooks, like their own protocol, if the actual
	 * user is allowed to use them. Depending on the way an ability is called (e.g., via an MCP server), the user
	 * is not known yet when the services are initialized. The URLs of these services would then not be usable.
	 * At this point the user is known, as the permission for the ability has already been checked.
	 *
	 * @param mixed $ability_name The name of the ability, empty to initialize the services in any case.
	 *
	 * @return void
	 */
	public function init_services( mixed $ability_name = '' ): void {
		// bail if this is an ability of another plugin.
		if ( is_string( $ability_name ) && '' !== $ability_name && ! str_starts_with( $ability_name, self::PREFIX ) ) {
			return;
		}

		// bail if the services have already been initialized for this user.
		if ( get_current_user_id() === $this->services_initialized_for ) {
			return;
		}

		// initialize the services. Their hooks are only registered once, even if they have been initialized before.
		Services::get_instance()->init_services();

		// remember the user.
		$this->services_initialized_for = get_current_user_id();
	}

	/**
	 * Add our own ability category.
	 *
	 * @return void
	 */
	public function add_category(): void {
		// bail if function does not exist.
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		// bail if abilities are disabled.
		if ( ! $this->is_enabled() ) {
			return;
		}

		// add the category.
		wp_register_ability_category(
			Files::ABILITY_CATEGORY,
			array(
				'label'       => __( 'External Files in Media Library', 'external-files-in-media-library' ),
				'description' => __( 'Abilities for managing external files, their external sources, the synchronization, the queue and the export in the media library.', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Register the abilities this plugin provides.
	 *
	 * @return void
	 */
	public function add_abilities(): void {
		// bail if function does not exist.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// register each active ability.
		foreach ( $this->get_active_abilities() as $name => $ability ) {
			// bail if the configuration is not usable.
			if ( empty( $ability['execute_callback'] ) || empty( $ability['permission_callback'] ) ) {
				continue;
			}

			// create the configuration.
			$args = array(
				'label'               => $ability['label'],
				'description'         => $ability['description'],
				'category'            => Files::ABILITY_CATEGORY,
				'output_schema'       => $ability['output_schema'],
				'execute_callback'    => $ability['execute_callback'],
				'permission_callback' => $ability['permission_callback'],
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ), // expose via the MCP Adapter of WordPress.
					'annotations'  => $ability['annotations'],
				),
			);

			// add the input schema, if this ability uses any input.
			if ( ! empty( $ability['input_schema'] ) ) {
				$args['input_schema'] = $ability['input_schema'];
			}

			// register it. The name is used in lowercase, as the Abilities API does not allow any other names.
			wp_register_ability( strtolower( self::PREFIX . $name ), $args );
		}
	}

	/**
	 * Return the list of abilities this plugin provides.
	 *
	 * The key is the name of the ability without the prefix of this plugin.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_abilities(): array {
		$abilities = array_merge(
			$this->get_file_abilities(),
			$this->get_info_abilities(),
			$this->get_source_abilities(),
			$this->get_queue_abilities(),
			$this->get_export_abilities()
		);

		/**
		 * Filter the list of abilities this plugin provides for the Abilities API.
		 *
		 * Remove an entry to prevent its registration.
		 *
		 * @since 5.5.0 Available since 5.5.0.
		 * @param array<string,array<string,mixed>> $abilities List of abilities, the key is the name without the plugin prefix.
		 */
		return apply_filters( 'efml_abilities', $abilities );
	}

	/**
	 * Return the list of abilities which should be provided depending on the settings.
	 *
	 * Returns an empty list if the abilities are disabled. In the mode "read only" only abilities,
	 * which are marked as "readonly" in their annotations, are returned.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_active_abilities(): array {
		// bail if abilities are disabled.
		if ( ! $this->is_enabled() ) {
			return array();
		}

		// get all abilities.
		$abilities = $this->get_abilities();

		// return all if all abilities should be provided.
		if ( ! $this->is_read_only_mode() ) {
			return $abilities;
		}

		// return only the read-only abilities.
		return array_filter( $abilities, array( $this, 'is_read_only_ability' ) );
	}

	/**
	 * Return whether the abilities are enabled in the settings.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$enabled = 1 === absint( get_option( 'efml_abilities_enabled', 1 ) );

		/**
		 * Filter whether the abilities of this plugin are enabled.
		 *
		 * @since 5.5.0 Available since 5.5.0.
		 * @param bool $enabled True if they are enabled.
		 */
		return (bool) apply_filters( 'efml_abilities_enabled', $enabled );
	}

	/**
	 * Return the mode for the abilities: "read" for read only (the default) or "all".
	 *
	 * @return string
	 */
	public function get_mode(): string {
		$mode = get_option( 'efml_abilities_mode', 'read' );

		/**
		 * Filter the mode for the abilities of this plugin.
		 *
		 * Use "read" to provide only the abilities which do not change anything, or "all" for all abilities.
		 *
		 * @since 5.5.0 Available since 5.5.0.
		 * @param string $mode The mode.
		 */
		$mode = apply_filters( 'efml_abilities_mode', is_string( $mode ) ? $mode : 'read' );

		// use "read" for any unknown value, so changes are only allowed if this is explicitly enabled.
		return 'all' === $mode ? 'all' : 'read';
	}

	/**
	 * Return whether the abilities are used in the mode "read only".
	 *
	 * @return bool
	 */
	public function is_read_only_mode(): bool {
		return 'read' === $this->get_mode();
	}

	/**
	 * Return whether the given ability configuration is marked as "readonly".
	 *
	 * Abilities without this annotation are handled as abilities which change data.
	 *
	 * @param array<string,mixed> $ability The ability configuration.
	 *
	 * @return bool
	 */
	public function is_read_only_ability( array $ability ): bool {
		return isset( $ability['annotations'] ) && is_array( $ability['annotations'] ) && ! empty( $ability['annotations']['readonly'] );
	}

	/**
	 * Show the hint for the settings of the abilities.
	 *
	 * @return void
	 */
	public function show_settings_hint(): void {
		echo '<p>' . esc_html__( 'Abilities enable AI agents to manage your external files, e.g., to list them, to check their availability or to import new files. They are provided via the Abilities API of WordPress.', 'external-files-in-media-library' ) . '</p>';
	}

	/**
	 * Show the status of the abilities in the settings.
	 *
	 * @return void
	 */
	public function show_status(): void {
		// collect the rows for the status table.
		$rows = array();

		// check the Abilities API.
		$api_available = function_exists( 'wp_register_ability' );
		$rows[]        = array(
			'label' => __( 'Abilities API', 'external-files-in-media-library' ),
			'state' => $api_available,
			'text'  => $api_available ? __( 'available', 'external-files-in-media-library' ) : __( 'not available, it is part of WordPress 6.9 or newer', 'external-files-in-media-library' ),
		);

		// check the setting.
		$rows[] = array(
			'label' => __( 'Abilities of this plugin', 'external-files-in-media-library' ),
			'state' => $this->is_enabled(),
			'text'  => $this->is_enabled() ? __( 'enabled', 'external-files-in-media-library' ) : __( 'disabled', 'external-files-in-media-library' ),
		);

		// show the mode.
		if ( $this->is_enabled() ) {
			$rows[] = array(
				'label' => __( 'Mode', 'external-files-in-media-library' ),
				'state' => true,
				'text'  => $this->is_read_only_mode() ? __( 'read only', 'external-files-in-media-library' ) : __( 'all (read, change and delete)', 'external-files-in-media-library' ),
			);
		}

		// get the provided abilities.
		$all_abilities      = $this->get_abilities();
		$provided_abilities = $api_available ? $this->get_registered_ability_names() : array();
		$rows[]             = array(
			'label' => __( 'Provided abilities', 'external-files-in-media-library' ),
			'state' => ! empty( $provided_abilities ),
			/* translators: %1$d and %2$d will be replaced by numbers. */
			'text'  => sprintf( __( '%1$d of %2$d', 'external-files-in-media-library' ), count( $provided_abilities ), count( $all_abilities ) ),
		);

		// output the table.
		echo '<table class="widefat striped efml-abilities-status"><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td><span class="dashicons ' . ( $row['state'] ? 'dashicons-yes' : 'dashicons-no-alt' ) . '" aria-hidden="true"></span> ' . esc_html( $row['text'] ) . '</td></tr>';
		}
		echo '</tbody></table>';

		// show the list of provided abilities.
		if ( ! empty( $provided_abilities ) ) {
			echo '<details><summary>' . esc_html__( 'Show the provided abilities', 'external-files-in-media-library' ) . '</summary><ul>';
			foreach ( $provided_abilities as $ability_name ) {
				echo '<li><code>' . esc_html( $ability_name ) . '</code></li>';
			}
			echo '</ul></details>';
		}

		// show the hint for the MCP plugin.
		echo '<p><strong>' . esc_html__( 'Important:', 'external-files-in-media-library' ) . '</strong> ' . esc_html__( 'This plugin only provides the abilities. To use them with an AI agent (e.g., via an MCP client in Claude, ChatGPT or your IDE), you need an MCP plugin of your choice which supports the Abilities API of WordPress, e.g., the MCP Adapter of WordPress. Without such a plugin AI agents will not be able to reach the abilities.', 'external-files-in-media-library' ) . '</p>';
		/* translators: %1$s will be replaced by a URL, %2$s by the hint for the new window. */
		echo '<p>' . wp_kses_post( sprintf( __( 'You will find the list of all abilities and how to use them <a href="%1$s" target="_blank">in our documentation%2$s</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_abilities_doc_url() ), Helper::get_a11n_window_hint() ) ) . '</p>';
	}

	/**
	 * Return the names of the abilities of this plugin, which are registered in the Abilities API.
	 *
	 * @return array<int,string>
	 */
	private function get_registered_ability_names(): array {
		// bail if function does not exist.
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		// collect the names.
		$names = array();
		foreach ( wp_get_abilities() as $ability ) {
			// bail if this is not an ability of this plugin.
			if ( ! str_starts_with( $ability->get_name(), self::PREFIX ) ) {
				continue;
			}

			// add the name to the list.
			$names[] = $ability->get_name();
		}

		// return the list.
		return $names;
	}

	/**
	 * Return the abilities to handle single external files.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_file_abilities(): array {
		return array(
			'add'                => array(
				'label'               => __( 'Add an external URL in media library', 'external-files-in-media-library' ),
				'description'         => __( 'Adds a single external file by its URL to the media library and returns the attachment ID. If the URL already exists in the media library its existing attachment ID is returned. Use the ability "import" for multiple URLs, directories, saved external sources or the queue.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'url'           => array(
							'type'        => 'string',
							'description' => __( 'The URL of the external file to add, e.g. https://example.com/file.pdf.', 'external-files-in-media-library' ),
						),
						'login'         => array(
							'type'        => 'string',
							'description' => __( 'The login, only if the URL requires authentication. Prefer the ability "import" with a saved external source instead of sending credentials.', 'external-files-in-media-library' ),
						),
						'password'      => array(
							'type'        => 'string',
							'description' => __( 'The password, only if the URL requires authentication.', 'external-files-in-media-library' ),
						),
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => __( 'Deprecated, only for backward compatibility. Leave it empty or use 0.', 'external-files-in-media-library' ),
						),
					),
					'required'             => array( 'url' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'integer',
					'description' => __( 'The attachment ID of the external file.', 'external-files-in-media-library' ),
				),
				'execute_callback'    => array( $this, 'add_url' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'delete'             => array(
				'label'               => __( 'Delete an external URL in media library', 'external-files-in-media-library' ),
				'description'         => __( 'Deletes a single external file from the media library. The original file on the external source stays untouched. If the file is used on the website it is not visible there anymore.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $this->get_file_reference_properties(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'boolean',
					'description' => __( 'True if the external file has been deleted.', 'external-files-in-media-library' ),
				),
				'execute_callback'    => array( $this, 'delete_url' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, true, true ),
			),
			'get-file'           => array(
				'label'               => __( 'Get an external file', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the data of a single external file in the media library: its URL, title, mime type, size, hosting (external or local), availability, used service and external source.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $this->get_file_reference_properties(),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_file_schema(),
				'execute_callback'    => array( $this, 'get_file' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'list-files'         => array(
				'label'               => __( 'List external files', 'external-files-in-media-library' ),
				'description'         => __( 'Returns a paginated list of the external files in the media library. The list can be filtered by external source, mime type, hosting, availability, title and URL.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source_id'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Only files of this external source, see the ability "list-sources".', 'external-files-in-media-library' ),
						),
						'mime_type'    => array(
							'type'        => 'string',
							'description' => __( 'Only files with this mime type, e.g. "image", "image/jpeg", "application/pdf" or "video".', 'external-files-in-media-library' ),
						),
						'hosting'      => array(
							'type'        => 'string',
							'enum'        => array( 'external', 'local' ),
							'description' => __( 'Only files which are hosted external or which are saved locally.', 'external-files-in-media-library' ),
						),
						'availability' => array(
							'type'        => 'string',
							'enum'        => array( 'available', 'unavailable' ),
							'description' => __( 'Only files which were available or not available during the last availability check.', 'external-files-in-media-library' ),
						),
						'search'       => array(
							'type'        => 'string',
							'description' => __( 'Search term for the title of the files.', 'external-files-in-media-library' ),
						),
						'url_contains' => array(
							'type'        => 'string',
							'description' => __( 'Only files whose external URL contains this string, e.g. a domain.', 'external-files-in-media-library' ),
						),
						'page'         => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'default'     => 1,
							'description' => __( 'The page of the list.', 'external-files-in-media-library' ),
						),
						'per_page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
							'description' => __( 'The amount of files per page.', 'external-files-in-media-library' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'files'       => array(
							'type'  => 'array',
							'items' => $this->get_file_schema(),
						),
						'total'       => array(
							'type'        => 'integer',
							'description' => __( 'The amount of all files matching the filter.', 'external-files-in-media-library' ),
						),
						'page'        => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'list_files' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'import'             => array(
				'label'               => __( 'Import external URLs in media library', 'external-files-in-media-library' ),
				'description'         => __( 'Imports one or more URLs in the media library. A URL can be a single file or a directory with multiple files. Use "source_id" to use the saved credentials of an external source instead of sending credentials. Returns the imported files and the reason for each URL which could not be imported.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $this->get_import_properties(),
					'required'             => array( 'urls' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_import_result_schema(),
				'execute_callback'    => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'check-availability' => array(
				'label'               => __( 'Check the availability of external files', 'external-files-in-media-library' ),
				'description'         => __( 'Checks whether external files are still reachable on their external source and saves the result on each file. Without a file all external files are checked in batches: repeat the call with the returned "next_offset" until it is 0.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$this->get_file_reference_properties(),
						array(
							'limit'  => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'maximum'     => 200,
								'default'     => 50,
								'description' => __( 'The amount of files to check per call, if no single file is given.', 'external-files-in-media-library' ),
							),
							'offset' => array(
								'type'        => 'integer',
								'minimum'     => 0,
								'default'     => 0,
								'description' => __( 'The amount of files to skip, if no single file is given.', 'external-files-in-media-library' ),
							),
						)
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'checked'           => array(
							'type'        => 'integer',
							'description' => __( 'The amount of checked files.', 'external-files-in-media-library' ),
						),
						'available'         => array( 'type' => 'integer' ),
						'unavailable'       => array( 'type' => 'integer' ),
						'skipped'           => array(
							'type'        => 'integer',
							'description' => __( 'The amount of files which could not be checked, as their protocol does not support it.', 'external-files-in-media-library' ),
						),
						'total'             => array(
							'type'        => 'integer',
							'description' => __( 'The amount of all external files.', 'external-files-in-media-library' ),
						),
						'next_offset'       => array(
							'type'        => 'integer',
							'description' => __( 'The offset for the next call, 0 if all files have been checked.', 'external-files-in-media-library' ),
						),
						'unavailable_files' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'attachment_id' => array( 'type' => 'integer' ),
									'url'           => array( 'type' => 'string' ),
									'title'         => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'check_availability' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'switch-to-local'    => array(
				'label'               => __( 'Switch an external file to local hosting', 'external-files-in-media-library' ),
				'description'         => __( 'Downloads an external file and saves it in the local media library. The file keeps its external URL and can be switched back to external hosting. Not possible for files which require credentials.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $this->get_file_reference_properties(),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_file_schema(),
				'execute_callback'    => array( $this, 'switch_to_local' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'switch-to-external' => array(
				'label'               => __( 'Switch an external file to external hosting', 'external-files-in-media-library' ),
				'description'         => __( 'Deletes the local copy of an external file, so it is loaded from its external URL again. Only possible for files which have been saved locally before.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $this->get_file_reference_properties(),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_file_schema(),
				'execute_callback'    => array( $this, 'switch_to_external' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'reset-thumbnails'   => array(
				'label'               => __( 'Reset the thumbnails of an external file', 'external-files-in-media-library' ),
				'description'         => __( 'Deletes the generated thumbnails of an external file. They are generated again as soon as they are requested. Helpful if the external file has been changed.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $this->get_file_reference_properties(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'boolean',
					'description' => __( 'True if the thumbnails have been reset.', 'external-files-in-media-library' ),
				),
				'execute_callback'    => array( $this, 'reset_thumbnails' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'reset-proxy-cache'  => array(
				'label'               => __( 'Reset the proxy cache', 'external-files-in-media-library' ),
				'description'         => __( 'Deletes all files in the proxy cache of external files. The cache is filled again as soon as the files are requested. Helpful if external files have been changed and the website still delivers old versions.', 'external-files-in-media-library' ),
				'output_schema'       => array(
					'type'        => 'boolean',
					'description' => __( 'True if the proxy cache has been reset.', 'external-files-in-media-library' ),
				),
				'execute_callback'    => array( $this, 'reset_proxy_cache' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
		);
	}

	/**
	 * Return the abilities to get information about the state of the plugin.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_info_abilities(): array {
		return array(
			'get-log'        => array(
				'label'               => __( 'Get log entries', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the newest entries of the log of this plugin. Use it to find out why an import, a synchronization or an export failed. The log can be filtered by URL and state.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'url'    => array(
							'type'        => 'string',
							'description' => __( 'Only entries for exactly this URL.', 'external-files-in-media-library' ),
						),
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Only entries whose URL contains this string. Is ignored if "url" is set.', 'external-files-in-media-library' ),
						),
						'state'  => array(
							'type'        => 'string',
							'enum'        => array( 'error', 'success', 'info' ),
							'description' => __( 'Only entries with this state.', 'external-files-in-media-library' ),
						),
						'limit'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 20,
							'description' => __( 'The max amount of entries.', 'external-files-in-media-library' ),
						),
						'offset' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'default'     => 0,
							'description' => __( 'The amount of entries to skip.', 'external-files-in-media-library' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'entries' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'      => array( 'type' => 'integer' ),
									'date'    => array(
										'type'        => 'string',
										'description' => __( 'The date of the entry in UTC.', 'external-files-in-media-library' ),
									),
									'state'   => array( 'type' => 'string' ),
									'url'     => array( 'type' => 'string' ),
									'message' => array( 'type' => 'string' ),
								),
							),
						),
						'total'   => array(
							'type'        => 'integer',
							'description' => __( 'The amount of all entries matching the filter.', 'external-files-in-media-library' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_log' ),
				'permission_callback' => array( $this, 'can_read_log' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'get-statistics' => array(
				'label'               => __( 'Get statistics about external files', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the amount and size of all external files in the media library, grouped by hosting, service and file type, and the amount of external sources and entries in the queue.', 'external-files-in-media-library' ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'files'      => array(
							'type'       => 'object',
							'properties' => array(
								'total'       => array( 'type' => 'integer' ),
								'external'    => array( 'type' => 'integer' ),
								'local'       => array( 'type' => 'integer' ),
								'unavailable' => array( 'type' => 'integer' ),
								'size_bytes'  => array( 'type' => 'integer' ),
							),
						),
						'services'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'       => array( 'type' => 'string' ),
									'label'      => array( 'type' => 'string' ),
									'files'      => array( 'type' => 'integer' ),
									'size_bytes' => array( 'type' => 'integer' ),
								),
							),
						),
						'file_types' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array( 'type' => 'string' ),
									'files' => array( 'type' => 'integer' ),
								),
							),
						),
						'sources'    => array( 'type' => 'integer' ),
						'queue'      => array(
							'type'       => 'object',
							'properties' => array(
								'new'   => array( 'type' => 'integer' ),
								'error' => array( 'type' => 'integer' ),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_statistics' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'list-services'  => array(
				'label'               => __( 'List services for external files', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the services (e.g., FTP, local directory, DropBox, YouTube) which can be used as external source, whether they are usable for the actual user, whether they support synchronization and export, and which fields they need to add them as external source.', 'external-files-in-media-library' ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'services' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'            => array( 'type' => 'string' ),
									'label'           => array( 'type' => 'string' ),
									'available'       => array( 'type' => 'boolean' ),
									'supports_sync'   => array( 'type' => 'boolean' ),
									'supports_export' => array( 'type' => 'boolean' ),
									'fields'          => array(
										'type'  => 'array',
										'items' => array(
											'type'       => 'object',
											'properties' => array(
												'name'     => array( 'type' => 'string' ),
												'label'    => array( 'type' => 'string' ),
												'type'     => array( 'type' => 'string' ),
												'required' => array( 'type' => 'boolean' ),
											),
										),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_services' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
		);
	}

	/**
	 * Return the abilities to handle external sources and their synchronization.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_source_abilities(): array {
		// define the input to request a single external source.
		$source_input = array(
			'type'                 => 'object',
			'properties'           => array(
				'source_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The ID of the external source, see the ability "list-sources".', 'external-files-in-media-library' ),
				),
			),
			'required'             => array( 'source_id' ),
			'additionalProperties' => false,
		);

		return array(
			'list-sources'        => array(
				'label'               => __( 'List external sources', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the saved external sources (directories with their saved credentials) the actual user is allowed to use, incl. their synchronization and export state. The credentials are never returned.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'service' => array(
							'type'        => 'string',
							'description' => __( 'Only external sources of this service, e.g. "ftp", see the ability "list-services".', 'external-files-in-media-library' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'sources' => array(
							'type'  => 'array',
							'items' => $this->get_source_schema(),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_sources' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'add-source'          => array(
				'label'               => __( 'Add an external source', 'external-files-in-media-library' ),
				'description'         => __( 'Saves a directory of a service with its credentials as external source. The connection is tested before the source is saved. The credentials are saved encrypted and are never returned. Use the ability "list-services" to get the names of the services and their fields.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'service'   => array(
							'type'        => 'string',
							'description' => __( 'The name of the service, e.g. "ftp" or "local".', 'external-files-in-media-library' ),
						),
						'directory' => array(
							'type'        => 'string',
							'description' => __( 'The directory or URL to use, e.g. "ftps://example.com/files/" or "file:///var/www/files/". Can be empty if the service gets it from its fields.', 'external-files-in-media-library' ),
						),
						'fields'    => array(
							'type'                 => 'object',
							'description'          => __( 'The values for the fields of the service as key-value-pairs, e.g. {"server":"ftps://example.com","login":"name","password":"secret"}.', 'external-files-in-media-library' ),
							'additionalProperties' => array(
								'type' => 'string',
							),
						),
					),
					'required'             => array( 'service' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_source_schema(),
				'execute_callback'    => array( $this, 'add_source' ),
				'permission_callback' => array( $this, 'can_manage_sources' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'delete-source'       => array(
				'label'               => __( 'Delete an external source', 'external-files-in-media-library' ),
				'description'         => __( 'Deletes a saved external source with its credentials and its synchronization schedule. Depending on the plugin settings the synchronized files of this source are also deleted from the media library. The files on the external source stay untouched.', 'external-files-in-media-library' ),
				'input_schema'        => $source_input,
				'output_schema'       => array(
					'type'        => 'boolean',
					'description' => __( 'True if the external source has been deleted.', 'external-files-in-media-library' ),
				),
				'execute_callback'    => array( $this, 'delete_source' ),
				'permission_callback' => array( $this, 'can_delete_sources' ),
				'annotations'         => $this->get_annotations( false, true, true ),
			),
			'list-directory'      => array(
				'label'               => __( 'List the files of an external source', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the files and subdirectories of a saved external source without importing them. Use the returned URL of a file with the ability "import" and the same "source_id" to import it.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source_id' => $source_input['properties']['source_id'],
						'directory' => array(
							'type'        => 'string',
							'description' => __( 'A subdirectory of the external source as returned in "directories". Without it the main directory of the external source is used.', 'external-files-in-media-library' ),
						),
					),
					'required'             => array( 'source_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'directory'   => array( 'type' => 'string' ),
						'files'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'          => array( 'type' => 'string' ),
									'url'           => array( 'type' => 'string' ),
									'filesize'      => array( 'type' => 'integer' ),
									'mime_type'     => array( 'type' => 'string' ),
									'last_modified' => array( 'type' => 'string' ),
								),
							),
						),
						'directories' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'      => array( 'type' => 'string' ),
									'directory' => array( 'type' => 'string' ),
								),
							),
						),
						'truncated'   => array(
							'type'        => 'boolean',
							'description' => __( 'True if the directory contains more files than returned.', 'external-files-in-media-library' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_directory' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'sync-source'         => array(
				'label'               => __( 'Synchronize an external source', 'external-files-in-media-library' ),
				'description'         => __( 'Runs the synchronization of a saved external source now: new files are added to the media library, existing files are updated and, depending on the plugin settings, files which do not exist on the source anymore are deleted from the media library. This can take some time for big directories.', 'external-files-in-media-library' ),
				'input_schema'        => $source_input,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'source'       => $this->get_source_schema(),
						'synced_count' => array(
							'type'        => 'integer',
							'description' => __( 'The amount of files which have been added or updated.', 'external-files-in-media-library' ),
						),
						'files'        => array(
							'type'  => 'array',
							'items' => $this->get_file_schema(),
						),
						'truncated'    => array( 'type' => 'boolean' ),
						'errors'       => $this->get_errors_schema(),
					),
				),
				'execute_callback'    => array( $this, 'sync_source' ),
				'permission_callback' => array( $this, 'can_sync' ),
				'annotations'         => $this->get_annotations( false, true, false ),
			),
			'get-sync-status'     => array(
				'label'               => __( 'Get the synchronization status', 'external-files-in-media-library' ),
				'description'         => __( 'Returns for the external sources whether their automatic synchronization is enabled, its interval, the next scheduled run, the last synchronization and the amount of synchronized files.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Only the status of this external source. Without it the status of all external sources is returned.', 'external-files-in-media-library' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'enabled' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the synchronization is enabled in the plugin settings.', 'external-files-in-media-library' ),
						),
						'running' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether a manually started synchronization is running at the moment.', 'external-files-in-media-library' ),
						),
						'sources' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'source'         => $this->get_source_schema(),
									'sync_supported' => array( 'type' => 'boolean' ),
									'synced_files'   => array( 'type' => 'integer' ),
									'last_sync'      => array(
										'type'        => 'string',
										'description' => __( 'The date of the last synchronized file in ISO 8601 format (UTC), empty if nothing has been synchronized.', 'external-files-in-media-library' ),
									),
									'next_run'       => array(
										'type'        => 'string',
										'description' => __( 'The date of the next scheduled synchronization in ISO 8601 format (UTC), empty if none is scheduled.', 'external-files-in-media-library' ),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_sync_status' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'delete-synced-files' => array(
				'label'               => __( 'Delete the synchronized files of an external source', 'external-files-in-media-library' ),
				'description'         => __( 'Deletes all files from the media library which have been synchronized from the given external source. The original files on the external source stay untouched. If the files are used on the website they are not visible there anymore.', 'external-files-in-media-library' ),
				'input_schema'        => $source_input,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array(
							'type'        => 'integer',
							'description' => __( 'The amount of deleted files.', 'external-files-in-media-library' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'delete_synced_files' ),
				'permission_callback' => array( $this, 'can_sync' ),
				'annotations'         => $this->get_annotations( false, true, true ),
			),
		);
	}

	/**
	 * Return the abilities to handle the queue.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_queue_abilities(): array {
		// get the properties for the queue: the same as for the import, but without the queue option.
		$queue_properties = $this->get_import_properties();
		unset( $queue_properties['queue'] );

		return array(
			'queue-add'     => array(
				'label'               => __( 'Add URLs to the import queue', 'external-files-in-media-library' ),
				'description'         => __( 'Adds one or more URLs to the queue instead of importing them directly. The queue is processed in the background by a schedule or with the ability "queue-process". Use it for many or big files to prevent timeouts.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $queue_properties,
					'required'             => array( 'urls' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_import_result_schema(),
				'execute_callback'    => array( $this, 'queue_add' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'queue-status'  => array(
				'label'               => __( 'Get the status of the import queue', 'external-files-in-media-library' ),
				'description'         => __( 'Returns the amount of waiting and failed URLs in the queue, the amount of URLs which are processed per run, the next scheduled run and the newest entries. The saved credentials of the entries are never returned.', 'external-files-in-media-library' ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'new'           => array(
							'type'        => 'integer',
							'description' => __( 'The amount of URLs waiting for their import.', 'external-files-in-media-library' ),
						),
						'error'         => array(
							'type'        => 'integer',
							'description' => __( 'The amount of URLs which could not be imported.', 'external-files-in-media-library' ),
						),
						'limit_per_run' => array( 'type' => 'integer' ),
						'next_run'      => array(
							'type'        => 'string',
							'description' => __( 'The date of the next scheduled run in ISO 8601 format (UTC), empty if none is scheduled.', 'external-files-in-media-library' ),
						),
						'entries'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'    => array( 'type' => 'integer' ),
									'url'   => array( 'type' => 'string' ),
									'state' => array( 'type' => 'string' ),
									'date'  => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'queue_status' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( true, false, true ),
			),
			'queue-process' => array(
				'label'               => __( 'Process the import queue', 'external-files-in-media-library' ),
				'description'         => __( 'Imports the next waiting URLs from the queue now. Only a limited amount of URLs is processed per call: repeat the call until "remaining" is 0.', 'external-files-in-media-library' ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'processed'      => array(
							'type'        => 'integer',
							'description' => __( 'The amount of URLs which have been processed in this run.', 'external-files-in-media-library' ),
						),
						'imported_count' => array(
							'type'        => 'integer',
							'description' => __( 'The amount of files which have been added to the media library in this run.', 'external-files-in-media-library' ),
						),
						'failed'         => array(
							'type'        => 'integer',
							'description' => __( 'The amount of URLs which failed in this run.', 'external-files-in-media-library' ),
						),
						'remaining'      => array(
							'type'        => 'integer',
							'description' => __( 'The amount of URLs still waiting in the queue.', 'external-files-in-media-library' ),
						),
						'errors'         => $this->get_errors_schema(),
					),
				),
				'execute_callback'    => array( $this, 'queue_process' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, false, false ),
			),
			'queue-cleanup' => array(
				'label'               => __( 'Clean up the import queue', 'external-files-in-media-library' ),
				'description'         => __( 'Removes the failed URLs from the queue. With the mode "all" the complete queue is deleted, incl. all URLs waiting for their import.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'mode' => array(
							'type'        => 'string',
							'enum'        => array( 'errors', 'all' ),
							'default'     => 'errors',
							'description' => __( '"errors" removes only the failed URLs, "all" removes every entry.', 'external-files-in-media-library' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'removed'   => array( 'type' => 'integer' ),
						'remaining' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'queue_cleanup' ),
				'permission_callback' => array( $this, 'can_manage_files' ),
				'annotations'         => $this->get_annotations( false, true, true ),
			),
		);
	}

	/**
	 * Return the abilities to handle the export of files.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_export_abilities(): array {
		return array(
			'export-file'      => array(
				'label'               => __( 'Export a local file to the external sources', 'external-files-in-media-library' ),
				'description'         => __( 'Exports a local file of the media library to the external sources which are enabled for the export. The local file is deleted afterwards and the file is used from the external source as external file.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'The attachment ID of the local file in the media library.', 'external-files-in-media-library' ),
						),
					),
					'required'             => array( 'attachment_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_file_schema(),
				'execute_callback'    => array( $this, 'export_file' ),
				'permission_callback' => array( $this, 'can_export' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
			'set-export-state' => array(
				'label'               => __( 'Enable or disable the export to an external source', 'external-files-in-media-library' ),
				'description'         => __( 'Enables or disables an external source as target for the export of files. New uploaded files are exported to all enabled external sources. Sources which require a public URL must be configured once in the backend before they can be enabled.', 'external-files-in-media-library' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'The ID of the external source, see the ability "list-sources".', 'external-files-in-media-library' ),
						),
						'enabled'   => array(
							'type'        => 'boolean',
							'description' => __( 'True to enable the export to this external source, false to disable it.', 'external-files-in-media-library' ),
						),
					),
					'required'             => array( 'source_id', 'enabled' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $this->get_source_schema(),
				'execute_callback'    => array( $this, 'set_export_state' ),
				'permission_callback' => array( $this, 'can_configure_export' ),
				'annotations'         => $this->get_annotations( false, false, true ),
			),
		);
	}

	/**
	 * Return the annotations for an ability.
	 *
	 * @param bool $is_readonly Whether the ability does not change anything.
	 * @param bool $destructive Whether the ability deletes data.
	 * @param bool $idempotent Whether repeated calls have no additional effect.
	 *
	 * @return array<string,bool>
	 */
	private function get_annotations( bool $is_readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'readonly'    => $is_readonly,
			'destructive' => $destructive,
			'idempotent'  => $idempotent,
		);
	}

	/**
	 * Return the properties to reference a single external file.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_file_reference_properties(): array {
		return array(
			'attachment_id' => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The attachment ID of the external file. Either this or "url" is required.', 'external-files-in-media-library' ),
			),
			'url'           => array(
				'type'        => 'string',
				'description' => __( 'The external URL of the file as it is saved in the media library. Either this or "attachment_id" is required.', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Return the properties for the import of URLs.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_import_properties(): array {
		return array(
			'urls'        => array(
				'type'        => 'array',
				'minItems'    => 1,
				'maxItems'    => 50,
				'items'       => array(
					'type' => 'string',
				),
				'description' => __( 'The URLs of the files or directories to import. With "source_id" use the values returned as "url" by the ability "list-directory" unchanged (they do not have to be real URLs, e.g. "DropBox/my file.pdf") or paths relative to the directory of the external source.', 'external-files-in-media-library' ),
			),
			'source_id'   => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The ID of a saved external source. Its saved credentials are used and the files are assigned to it. See the ability "list-sources".', 'external-files-in-media-library' ),
			),
			'login'       => array(
				'type'        => 'string',
				'description' => __( 'The login, only if the URLs require authentication and no "source_id" is used.', 'external-files-in-media-library' ),
			),
			'password'    => array(
				'type'        => 'string',
				'description' => __( 'The password, only if the URLs require authentication and no "source_id" is used.', 'external-files-in-media-library' ),
			),
			'queue'       => array(
				'type'        => 'boolean',
				'description' => __( 'True to add the URLs to the queue instead of importing them directly.', 'external-files-in-media-library' ),
			),
			'real_import' => array(
				'type'        => 'boolean',
				'description' => __( 'True to import the files as real local files without any connection to their external URL.', 'external-files-in-media-library' ),
			),
			'use_dates'   => array(
				'type'        => 'boolean',
				'description' => __( 'True to use the dates of the external files as dates in the media library.', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Return the schema for the result of an import.
	 *
	 * @return array<string,mixed>
	 */
	private function get_import_result_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'imported_count' => array(
					'type'        => 'integer',
					'description' => __( 'The amount of files which have been added to the media library.', 'external-files-in-media-library' ),
				),
				'imported'       => array(
					'type'  => 'array',
					'items' => $this->get_file_schema(),
				),
				'truncated'      => array(
					'type'        => 'boolean',
					'description' => __( 'True if more files have been imported than returned in "imported".', 'external-files-in-media-library' ),
				),
				'queued'         => array(
					'type'        => 'integer',
					'description' => __( 'The amount of URLs which have been added to the queue.', 'external-files-in-media-library' ),
				),
				'errors'         => $this->get_errors_schema(),
			),
		);
	}

	/**
	 * Return the schema for a list of errors.
	 *
	 * @return array<string,mixed>
	 */
	private function get_errors_schema(): array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'url'     => array( 'type' => 'string' ),
					'message' => array( 'type' => 'string' ),
				),
			),
		);
	}

	/**
	 * Return the schema for a single external file.
	 *
	 * @return array<string,mixed>
	 */
	private function get_file_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'attachment_id' => array( 'type' => 'integer' ),
				'url'           => array(
					'type'        => 'string',
					'description' => __( 'The external URL of the file. Empty if the file has been imported as real local file.', 'external-files-in-media-library' ),
				),
				'public_url'    => array(
					'type'        => 'string',
					'description' => __( 'The URL which is used for this file on the website, e.g. the proxied URL.', 'external-files-in-media-library' ),
				),
				'title'         => array( 'type' => 'string' ),
				'mime_type'     => array( 'type' => 'string' ),
				'file_type'     => array( 'type' => 'string' ),
				'filesize'      => array(
					'type'        => 'integer',
					'description' => __( 'The size of the file in bytes.', 'external-files-in-media-library' ),
				),
				'hosting'       => array(
					'type' => 'string',
					'enum' => array( 'external', 'local' ),
				),
				'available'     => array(
					'type'        => 'boolean',
					'description' => __( 'The result of the last availability check.', 'external-files-in-media-library' ),
				),
				'proxied'       => array( 'type' => 'boolean' ),
				'protocol'      => array( 'type' => 'string' ),
				'service'       => array( 'type' => 'string' ),
				'source_id'     => array(
					'type'        => 'integer',
					'description' => __( 'The ID of the external source of this file, 0 if it has none.', 'external-files-in-media-library' ),
				),
				'source_name'   => array( 'type' => 'string' ),
				'date'          => array(
					'type'        => 'string',
					'description' => __( 'The date of the import in ISO 8601 format (UTC).', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Return the schema for a single external source.
	 *
	 * @return array<string,mixed>
	 */
	private function get_source_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'                 => array( 'type' => 'integer' ),
				'name'               => array( 'type' => 'string' ),
				'directory'          => array( 'type' => 'string' ),
				'service'            => array( 'type' => 'string' ),
				'service_label'      => array( 'type' => 'string' ),
				'files'              => array(
					'type'        => 'integer',
					'description' => __( 'The amount of files in the media library which are assigned to this external source.', 'external-files-in-media-library' ),
				),
				'sync_enabled'       => array( 'type' => 'boolean' ),
				'sync_interval'      => array( 'type' => 'string' ),
				'export_enabled'     => array( 'type' => 'boolean' ),
				'main_export_source' => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Return whether the actual user is allowed to manage external files.
	 *
	 * @return bool
	 */
	public function can_manage_files(): bool {
		return current_user_can( EFML_CAP_NAME );
	}

	/**
	 * Return whether the actual user is allowed to read the log.
	 *
	 * @return bool
	 */
	public function can_read_log(): bool {
		return current_user_can( EFML_CAP_NAME ) && current_user_can( 'manage_options' );
	}

	/**
	 * Return whether the actual user is allowed to add external sources.
	 *
	 * @return bool
	 */
	public function can_manage_sources(): bool {
		return current_user_can( EFML_CAP_NAME ) && current_user_can( $this->get_source_capability( 'manage_terms' ) );
	}

	/**
	 * Return whether the actual user is allowed to delete external sources.
	 *
	 * @return bool
	 */
	public function can_delete_sources(): bool {
		return current_user_can( EFML_CAP_NAME ) && current_user_can( $this->get_source_capability( 'delete_terms' ) );
	}

	/**
	 * Return whether the actual user is allowed to use the synchronization.
	 *
	 * @return bool
	 */
	public function can_sync(): bool {
		return current_user_can( EFML_CAP_NAME ) && current_user_can( 'efml_cap_tools_sync' );
	}

	/**
	 * Return whether the actual user is allowed to export files.
	 *
	 * @return bool
	 */
	public function can_export(): bool {
		return current_user_can( EFML_CAP_NAME ) && current_user_can( 'efml_cap_tools_export' );
	}

	/**
	 * Return whether the actual user is allowed to configure the export for external sources.
	 *
	 * @return bool
	 */
	public function can_configure_export(): bool {
		return $this->can_manage_sources() && current_user_can( 'efml_cap_tools_export' );
	}

	/**
	 * Add a single URL to the media library.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return int|WP_Error The attachment ID or an error with the reason.
	 */
	public function add_url( mixed $input = null ): int|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// keep the former behavior: if an attachment ID is given, return it.
		$attachment_id = $this->get_int( $input, 'attachment_id' );
		if ( $attachment_id > 0 ) {
			return $attachment_id;
		}

		// get the URL.
		$url = $this->get_string( $input, 'url' );

		// bail if no URL is given.
		if ( '' === $url ) {
			return $this->get_error( 'efml_missing_url', __( 'No URL given.', 'external-files-in-media-library' ), 400 );
		}

		// if this URL already exists as external file, return its ID.
		$external_file_obj = $this->get_file_by_url( $url );
		if ( $external_file_obj instanceof File ) {
			return $external_file_obj->get_id();
		}

		// import the URL.
		$result = $this->run_import( array( $url ), $this->get_credential_fields( $input ) );

		// return the ID of the file with this URL, if it exists now.
		$external_file_obj = $this->get_file_by_url( $url );
		if ( $external_file_obj instanceof File ) {
			return $external_file_obj->get_id();
		}

		// otherwise return the first imported file (e.g., if the URL has been changed during the import).
		if ( ! empty( $result['imported_ids'] ) ) {
			return absint( $result['imported_ids'][0] );
		}

		// return the reason why the URL could not be added.
		return $this->get_error( 'efml_import_failed', $this->get_error_text( $result['errors'], __( 'The URL could not be added to the media library.', 'external-files-in-media-library' ) ), 400 );
	}

	/**
	 * Delete a single external file from the media library.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return bool|WP_Error
	 */
	public function delete_url( mixed $input = null ): bool|WP_Error {
		// get the external file.
		$external_file_obj = $this->get_file_by_input( $this->get_input( $input ) );

		// bail if the external file could not be loaded.
		if ( is_wp_error( $external_file_obj ) ) {
			return $external_file_obj;
		}

		// bail if the actual user is not allowed to delete this attachment.
		if ( ! current_user_can( 'delete_post', $external_file_obj->get_id() ) ) {
			return $this->get_error( 'efml_not_allowed', __( 'You are not allowed to delete this file.', 'external-files-in-media-library' ), 403 );
		}

		// get the ID before we delete the file.
		$attachment_id = $external_file_obj->get_id();

		// delete it.
		$external_file_obj->delete();

		// bail if the attachment still exists.
		if ( get_post( $attachment_id ) instanceof \WP_Post ) {
			// synchronized files are protected against their deletion, depending on the plugin settings.
			if ( absint( get_post_meta( $attachment_id, 'eml_synced_time', true ) ) > 0 ) {
				return $this->get_error( 'efml_delete_failed', __( 'The file could not be deleted, as it is a synchronized file. Use the ability "delete-synced-files" to delete the synchronized files of its external source.', 'external-files-in-media-library' ), 400 );
			}

			// return a general error.
			return $this->get_error( 'efml_delete_failed', __( 'The file could not be deleted.', 'external-files-in-media-library' ), 500 );
		}

		// return true as it has been deleted.
		return true;
	}

	/**
	 * Return the data of a single external file.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_file( mixed $input = null ): array|WP_Error {
		// get the external file.
		$external_file_obj = $this->get_file_by_input( $this->get_input( $input ) );

		// bail if the external file could not be loaded.
		if ( is_wp_error( $external_file_obj ) ) {
			return $external_file_obj;
		}

		// return its data.
		return $this->get_file_data( $external_file_obj );
	}

	/**
	 * Return a list of external files.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>
	 */
	public function list_files( mixed $input = null ): array {
		// get the input as array.
		$input = $this->get_input( $input );

		// get the pagination.
		$page     = max( 1, $this->get_int( $input, 'page' ) );
		$per_page = $this->get_int( $input, 'per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		$per_page = min( 100, $per_page );

		// prepare the filter for external files.
		$url_filter = array(
			'key'     => EFML_POST_META_URL,
			'compare' => 'EXISTS',
		);

		// filter by a part of the URL.
		$url_contains = $this->get_string( $input, 'url_contains' );
		if ( '' !== $url_contains ) {
			$url_filter = array(
				'key'     => EFML_POST_META_URL,
				'value'   => $url_contains,
				'compare' => 'LIKE',
			);
		}

		// create the query.
		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
				'relation' => 'AND',
				$url_filter,
			),
		);

		// filter by hosting.
		$hosting = $this->get_string( $input, 'hosting' );
		if ( in_array( $hosting, array( 'external', 'local' ), true ) ) {
			$query['meta_query'][] = $this->get_flag_meta_query( 'eml_locally_saved', 'local' === $hosting );
		}

		// filter by availability.
		$availability = $this->get_string( $input, 'availability' );
		if ( in_array( $availability, array( 'available', 'unavailable' ), true ) ) {
			$query['meta_query'][] = $this->get_flag_meta_query( EFML_POST_META_AVAILABILITY, 'available' === $availability );
		}

		// filter by mime type.
		$mime_type = $this->get_string( $input, 'mime_type' );
		if ( '' !== $mime_type ) {
			$query['post_mime_type'] = sanitize_mime_type( $mime_type );
		}

		// filter by title.
		$search = $this->get_string( $input, 'search' );
		if ( '' !== $search ) {
			$query['s'] = sanitize_text_field( $search );
		}

		// filter by external source.
		$source_id = $this->get_int( $input, 'source_id' );
		if ( $source_id > 0 ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filter for term.
				array(
					'taxonomy' => Taxonomy::get_instance()->get_name(),
					'field'    => 'term_id',
					'terms'    => $source_id,
				),
			);
		}

		// run the query.
		$result = new WP_Query( $query );

		// collect the files.
		$files = array();
		foreach ( $this->get_posts_of_query( $result ) as $attachment_id ) {
			// get the external file object.
			$external_file_obj = Files::get_instance()->get_file( absint( $attachment_id ) );

			// bail if this is not an external file.
			if ( ! $external_file_obj->is_valid() ) {
				continue;
			}

			// add its data to the list.
			$files[] = $this->get_file_data( $external_file_obj );
		}

		// return the list.
		return array(
			'files'       => $files,
			'total'       => absint( $result->found_posts ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => absint( $result->max_num_pages ),
		);
	}

	/**
	 * Import URLs in the media library.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function import( mixed $input = null ): array|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// get the URLs.
		$urls = $this->get_urls( $input );

		// bail if no URLs are given.
		if ( empty( $urls ) ) {
			return $this->get_error( 'efml_missing_url', __( 'No URL given.', 'external-files-in-media-library' ), 400 );
		}

		// get the credentials from the request.
		$fields    = $this->get_credential_fields( $input );
		$source_id = $this->get_int( $input, 'source_id' );

		// use the external source, if given.
		if ( $source_id > 0 ) {
			// get the external source.
			$term = $this->get_source_by_id( $source_id );

			// bail if the external source could not be used.
			if ( is_wp_error( $term ) ) {
				return $term;
			}

			// get its data.
			$term_data = Taxonomy::get_instance()->get_entry( $source_id );

			// bail if no data could be loaded.
			if ( empty( $term_data ) ) {
				return $this->get_error( 'efml_source_without_configuration', __( 'The external source does not have any configuration.', 'external-files-in-media-library' ), 400 );
			}

			// complete relative paths with the directory of the external source.
			$urls = $this->get_urls_for_source( $urls, (string) $term_data['directory'] );

			// use its credentials.
			$fields = is_array( $term_data['fields'] ) ? $term_data['fields'] : array();
		}

		// collect the options.
		$options = array();
		foreach ( array( 'queue', 'real_import', 'use_dates' ) as $option ) {
			if ( isset( $input[ $option ] ) ) {
				$options[ $option ] = $this->get_bool( $input, $option );
			}
		}

		// bail if the real import is requested, but not allowed for this user.
		if ( ! empty( $options['real_import'] ) && ! current_user_can( 'efml_cap_tools_real_import' ) ) {
			return $this->get_error( 'efml_not_allowed', __( 'You are not allowed to import external files as real files.', 'external-files-in-media-library' ), 403 );
		}

		// run the import.
		$result = $this->run_import( $urls, $fields, $source_id, $options );

		// collect the data of the imported files.
		$imported = array();
		foreach ( array_slice( $result['imported_ids'], 0, self::MAX_FILES_IN_RESPONSE ) as $attachment_id ) {
			$imported[] = $this->get_file_data( Files::get_instance()->get_file( $attachment_id ) );
		}

		// return the result.
		return array(
			'imported_count' => count( $result['imported_ids'] ),
			'imported'       => $imported,
			'truncated'      => count( $result['imported_ids'] ) > count( $imported ),
			'queued'         => $result['queued'],
			'errors'         => $result['errors'],
		);
	}

	/**
	 * Check the availability of external files.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function check_availability( mixed $input = null ): array|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// prepare the result.
		$result = array(
			'checked'           => 0,
			'available'         => 0,
			'unavailable'       => 0,
			'skipped'           => 0,
			'total'             => 0,
			'next_offset'       => 0,
			'unavailable_files' => array(),
		);

		// collect the files to check.
		$files = array();

		if ( $this->get_int( $input, 'attachment_id' ) > 0 || '' !== $this->get_string( $input, 'url' ) ) {
			// get the single requested file.
			$external_file_obj = $this->get_file_by_input( $input );

			// bail if the external file could not be loaded.
			if ( is_wp_error( $external_file_obj ) ) {
				return $external_file_obj;
			}

			// use only this file.
			$files[]         = $external_file_obj;
			$result['total'] = 1;
		} else {
			// get the limits.
			$limit = $this->get_int( $input, 'limit' );
			if ( $limit < 1 ) {
				$limit = 50;
			}
			$limit  = min( 200, $limit );
			$offset = $this->get_int( $input, 'offset' );

			// get the next external files.
			$query = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => array( 'inherit', 'trash' ),
					'posts_per_page' => $limit,
					'offset'         => $offset,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
						array(
							'key'     => EFML_POST_META_URL,
							'compare' => 'EXISTS',
						),
					),
				)
			);

			// add them to the list.
			foreach ( $this->get_posts_of_query( $query ) as $attachment_id ) {
				$files[] = Files::get_instance()->get_file( absint( $attachment_id ) );
			}

			// set the values for the next call.
			$result['total'] = absint( $query->found_posts );
			if ( $offset + $limit < $result['total'] ) {
				$result['next_offset'] = $offset + $limit;
			}
		}

		// check each file.
		foreach ( $files as $external_file_obj ) {
			// bail if this is not an external file.
			if ( ! $external_file_obj->is_valid() ) {
				++$result['skipped'];
				continue;
			}

			// get the protocol handler for this file.
			$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

			// bail if this file could not be checked.
			if ( ! $protocol_handler_obj instanceof Protocol_Base || ! $protocol_handler_obj->can_check_availability() ) {
				++$result['skipped'];
				continue;
			}

			// check and save its availability.
			$available = $protocol_handler_obj->check_availability( $external_file_obj->get_url( true ) );
			$external_file_obj->set_availability( $available );

			// log the result.
			Log::get_instance()->create( __( 'Result of availability check:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $available ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

			// add it to the result.
			++$result['checked'];
			if ( $available ) {
				++$result['available'];
				continue;
			}
			++$result['unavailable'];
			$result['unavailable_files'][] = array(
				'attachment_id' => $external_file_obj->get_id(),
				'url'           => $this->mask_credentials( $external_file_obj->get_url( true ) ),
				'title'         => $external_file_obj->get_title(),
			);
		}

		// return the result.
		return $result;
	}

	/**
	 * Switch the hosting of an external file to local.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function switch_to_local( mixed $input = null ): array|WP_Error {
		return $this->switch_hosting( $this->get_input( $input ), true );
	}

	/**
	 * Switch the hosting of an external file to external.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function switch_to_external( mixed $input = null ): array|WP_Error {
		return $this->switch_hosting( $this->get_input( $input ), false );
	}

	/**
	 * Reset the thumbnails of an external file.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return bool|WP_Error
	 */
	public function reset_thumbnails( mixed $input = null ): bool|WP_Error {
		// get the external file.
		$external_file_obj = $this->get_file_by_input( $this->get_input( $input ) );

		// bail if the external file could not be loaded.
		if ( is_wp_error( $external_file_obj ) ) {
			return $external_file_obj;
		}

		// delete the thumbs of this file.
		$external_file_obj->delete_thumbs();

		// return success.
		return true;
	}

	/**
	 * Reset the proxy cache.
	 *
	 * @return bool
	 */
	public function reset_proxy_cache(): bool {
		// reset by deleting the directory.
		Proxy::get_instance()->delete_cache_directory();

		// log this event.
		Log::get_instance()->create( __( 'The proxy cache has been reset via ability.', 'external-files-in-media-library' ), '', 'info', 2 );

		// return success.
		return true;
	}

	/**
	 * Return entries from the log.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>
	 */
	public function get_log( mixed $input = null ): array {
		// get the input as array.
		$input = $this->get_input( $input );

		// get the limit.
		$limit = $this->get_int( $input, 'limit' );
		if ( $limit < 1 ) {
			$limit = 20;
		}

		// create the filter.
		$args = array(
			'state'  => $this->get_string( $input, 'state' ),
			'limit'  => min( 100, $limit ),
			'offset' => $this->get_int( $input, 'offset' ),
		);

		// filter by URL.
		$url    = $this->get_string( $input, 'url' );
		$search = $this->get_string( $input, 'search' );
		if ( '' !== $url ) {
			$args['url'] = $url;
		} elseif ( '' !== $search ) {
			$args['url']      = $search;
			$args['url_like'] = true;
		}

		// get the log object.
		$log = Log::get_instance();

		// return the entries.
		return array(
			'entries' => $this->get_log_entries( $log->get_entries( $args ) ),
			'total'   => $log->get_entry_count( $args ),
		);
	}

	/**
	 * Return statistics about the external files.
	 *
	 * @return array<string,mixed>
	 */
	public function get_statistics(): array {
		// prepare the result.
		$files      = array(
			'total'       => 0,
			'external'    => 0,
			'local'       => 0,
			'unavailable' => 0,
			'size_bytes'  => 0,
		);
		$services   = array();
		$file_types = array();

		// get all external files. We request the complete posts to fill the cache for each of them with a single query.
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'trash' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
					array(
						'key'     => EFML_POST_META_URL,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		// check each file.
		foreach ( $this->get_posts_of_query( $query ) as $post ) {
			// bail if this is not a post.
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			// get the external file object.
			$external_file_obj = Files::get_instance()->get_file( $post->ID );

			// bail if this is not an external file.
			if ( ! $external_file_obj->is_valid() ) {
				continue;
			}

			// get its values.
			$filesize = absint( $external_file_obj->get_filesize() );
			$is_local = $external_file_obj->is_locally_saved();

			// count the file.
			++$files['total'];
			++$files[ $is_local ? 'local' : 'external' ];
			$files['size_bytes'] += $filesize;
			if ( ! $external_file_obj->is_available() ) {
				++$files['unavailable'];
			}

			// count it for its service.
			$service_name = $external_file_obj->get_service_name();
			if ( ! isset( $services[ $service_name ] ) ) {
				$services[ $service_name ] = array(
					'name'       => $service_name,
					'label'      => $this->get_service_label( $service_name ),
					'files'      => 0,
					'size_bytes' => 0,
				);
			}
			++$services[ $service_name ]['files'];
			$services[ $service_name ]['size_bytes'] += $filesize;

			// count it for its file type.
			$file_type = $external_file_obj->get_file_type_obj()->get_name();
			if ( ! isset( $file_types[ $file_type ] ) ) {
				$file_types[ $file_type ] = array(
					'name'  => $file_type,
					'files' => 0,
				);
			}
			++$file_types[ $file_type ]['files'];
		}

		// get the queue object.
		$queue = Queue::get_instance();

		// return the statistics.
		return array(
			'files'      => $files,
			'services'   => array_values( $services ),
			'file_types' => array_values( $file_types ),
			'sources'    => count( Directory_Listing::get_instance()->get_external_sources() ),
			'queue'      => array(
				'new'   => count( $queue->get_urls( 'new', true ) ),
				'error' => count( $queue->get_urls( 'error', true ) ),
			),
		);
	}

	/**
	 * Return the list of services.
	 *
	 * @return array<string,mixed>
	 */
	public function list_services(): array {
		// collect the services.
		$services = array();

		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $service_obj ) {
			// bail if this is the import of single URLs, which is not a service for external sources.
			if ( $service_obj instanceof Import ) {
				continue;
			}

			// get the name and the label.
			$name  = $service_obj->get_name();
			$label = wp_strip_all_tags( $service_obj->get_label() );

			// bail if one of them is empty.
			if ( '' === $name || '' === $label ) {
				continue;
			}

			// check whether this service can be used by the actual user.
			$available = ! $service_obj->is_disabled() && current_user_can( $service_obj->get_permission_name() );

			// get the object which is used for the export to this service.
			$export_service_obj = Export::get_instance()->get_service_object_by_type( $name );

			// add the service to the list.
			$services[] = array(
				'name'            => $name,
				'label'           => $label,
				'available'       => $available,
				'supports_sync'   => $service_obj instanceof Service_Base && ! $service_obj->is_sync_disabled(),
				'supports_export' => $export_service_obj instanceof Service_Base && $export_service_obj->get_export_object() instanceof Export_Base,
				'fields'          => $available ? $this->get_service_fields( $service_obj ) : array(),
			);
		}

		// return the list.
		return array(
			'services' => $services,
		);
	}

	/**
	 * Return the list of external sources.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>
	 */
	public function list_sources( mixed $input = null ): array {
		// get the requested service.
		$service = $this->get_string( $this->get_input( $input ), 'service' );

		// collect the external sources.
		$sources = array();
		foreach ( $this->get_allowed_sources() as $term ) {
			// bail if this is not the requested service.
			if ( '' !== $service && get_term_meta( $term->term_id, 'type', true ) !== $service ) {
				continue;
			}

			// add it to the list.
			$sources[] = $this->get_source_data( $term );
		}

		// return the list.
		return array(
			'sources' => $sources,
		);
	}

	/**
	 * Add an external source.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function add_source( mixed $input = null ): array|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// get the service.
		$service_obj = $this->get_service_for_user( $this->get_string( $input, 'service' ) );

		// bail if the service could not be used.
		if ( is_wp_error( $service_obj ) ) {
			return $service_obj;
		}

		// get the given values for the fields.
		$values = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();

		// get the fields this service is using.
		$fields = $service_obj->get_fields();

		// set the values on the fields.
		foreach ( $values as $key => $value ) {
			// bail if this field is unknown for this service.
			if ( ! isset( $fields[ $key ] ) ) {
				/* translators: %1$s will be replaced by a field name, %2$s by a list of field names. */
				return $this->get_error( 'efml_unknown_field', sprintf( __( 'The field %1$s is unknown for this service. Possible fields: %2$s', 'external-files-in-media-library' ), sanitize_key( (string) $key ), implode( ', ', array_keys( $fields ) ) ), 400 );
			}

			// bail if the value is not usable.
			if ( ! is_scalar( $value ) ) {
				/* translators: %1$s will be replaced by a field name. */
				return $this->get_error( 'efml_invalid_field', sprintf( __( 'The value of the field %1$s must be a string.', 'external-files-in-media-library' ), sanitize_key( (string) $key ) ), 400 );
			}

			// set the value.
			$fields[ $key ]['value'] = (string) $value;
		}

		// secure the newest log entry and the amount of errors on the service before we start to get only the errors of this run.
		$last_log_id  = $this->get_last_log_id();
		$error_offset = count( $service_obj->get_errors() );

		try {
			// configure the service.
			$service_obj->set_fields( $fields );
			$service_obj->set_directory( $this->get_string( $input, 'directory' ) );

			// get the directory to use from the service.
			$directory = $service_obj->get_directory();

			// bail if no directory is given.
			if ( '' === $directory ) {
				return $this->get_error( 'efml_missing_directory', __( 'No directory given for this external source.', 'external-files-in-media-library' ), 400 );
			}

			// bail if the connection with these settings is not possible.
			if ( ! $service_obj->do_login( $directory ) ) {
				return $this->get_error( 'efml_login_failed', $this->get_error_text( $this->get_errors_since( $last_log_id ), $this->get_service_error_text( $service_obj, __( 'The connection to the external source failed. Check the directory and the credentials.', 'external-files-in-media-library' ), $error_offset ) ), 400 );
			}

			// save the external source.
			$term_id = Taxonomy::get_instance()->add( $service_obj->get_name(), $directory, $fields );
		} catch ( Throwable $e ) {
			return $this->get_error( 'efml_source_not_saved', wp_strip_all_tags( $e->getMessage() ), 500 );
		} finally {
			// remove the credentials from the service object.
			$service_obj->set_fields( array() );
			$service_obj->set_directory( '' );
		}

		// bail if the external source could not be saved.
		if ( 0 === $term_id ) {
			return $this->get_error( 'efml_source_not_saved', __( 'The external source could not be saved.', 'external-files-in-media-library' ), 500 );
		}

		// get the saved external source.
		$term = $this->get_source_by_id( $term_id );

		// bail if it could not be loaded.
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// return its data.
		return $this->get_source_data( $term );
	}

	/**
	 * Delete an external source.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return bool|WP_Error
	 */
	public function delete_source( mixed $input = null ): bool|WP_Error {
		// get the external source.
		$term = $this->get_source_by_id( $this->get_int( $this->get_input( $input ), 'source_id' ) );

		// bail if the external source could not be used.
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// bail if the external source could not be deleted.
		if ( ! Taxonomy::get_instance()->delete( $term->term_id ) ) {
			return $this->get_error( 'efml_source_not_deleted', __( 'The external source could not be deleted.', 'external-files-in-media-library' ), 500 );
		}

		// return success.
		return true;
	}

	/**
	 * Return the files and directories of an external source.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_directory( mixed $input = null ): array|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// get the external source.
		$term = $this->get_source_by_id( $this->get_int( $input, 'source_id' ) );

		// bail if the external source could not be used.
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// get the service of this external source.
		$service_obj = $this->get_service_for_user( (string) get_term_meta( $term->term_id, 'type', true ) );

		// bail if the service could not be used.
		if ( is_wp_error( $service_obj ) ) {
			return $service_obj;
		}

		// get the configuration of the external source.
		$term_data = Taxonomy::get_instance()->get_entry( $term->term_id );

		// bail if no configuration could be loaded.
		if ( empty( $term_data ) ) {
			return $this->get_error( 'efml_source_without_configuration', __( 'The external source does not have any configuration.', 'external-files-in-media-library' ), 400 );
		}

		// get the object for the directory listings and its actual preview setting.
		$listing_init    = Init::get_instance();
		$preview_enabled = $listing_init->is_preview_enabled();

		// secure the newest log entry and the amount of errors on the service before we start to get only the errors of this run.
		$last_log_id  = $this->get_last_log_id();
		$error_offset = count( $service_obj->get_errors() );

		try {
			// configure the service.
			$service_obj->set_fields( is_array( $term_data['fields'] ) ? $term_data['fields'] : array() );
			$service_obj->set_directory( (string) $term_data['directory'] );

			// get the main directory of this external source.
			$base_directory = $service_obj->get_directory();

			// get the requested directory.
			$directory = $this->get_string( $input, 'directory' );
			if ( '' === $directory ) {
				$directory = $base_directory;
			}

			// bail if the requested directory is not part of the external source.
			if ( ! $this->is_directory_in_source( $directory, $base_directory ) ) {
				return $this->get_error( 'efml_directory_not_allowed', __( 'The requested directory is not part of this external source.', 'external-files-in-media-library' ), 400 );
			}

			// bail if the connection is not possible.
			if ( ! $service_obj->do_login( $base_directory ) ) {
				return $this->get_error( 'efml_login_failed', $this->get_error_text( $this->get_errors_since( $last_log_id ), $this->get_service_error_text( $service_obj, __( 'The connection to the external source failed.', 'external-files-in-media-library' ), $error_offset ) ), 400 );
			}

			// do not generate previews for the files, we do not use them here.
			$listing_init->set_preview_state( false );

			// get the listing.
			$listing = $service_obj->get_directory_listing( $directory );

			// bail on any new error.
			if ( count( $service_obj->get_errors() ) > $error_offset ) {
				return $this->get_error( 'efml_directory_not_loaded', $this->get_error_text( $this->get_errors_since( $last_log_id ), $this->get_service_error_text( $service_obj, __( 'The directory could not be loaded.', 'external-files-in-media-library' ), $error_offset ) ), 400 );
			}
		} catch ( Throwable $e ) {
			return $this->get_error( 'efml_directory_not_loaded', wp_strip_all_tags( $e->getMessage() ), 500 );
		} finally {
			// restore the preview setting and remove the credentials from the service object.
			$listing_init->set_preview_state( $preview_enabled );
			$service_obj->set_fields( array() );
			$service_obj->set_directory( '' );
		}

		// get the entries of the requested directory.
		$entry = $this->get_directory_entry( $listing, $directory );

		// collect the files.
		$files = array();
		foreach ( $entry['files'] as $file ) {
			// bail if this is not a usable entry.
			if ( ! is_array( $file ) || empty( $file['file'] ) || ! is_scalar( $file['file'] ) ) {
				continue;
			}

			// get the URL to use for the import of this file.
			$url = (string) $file['file'];
			if ( 'zip' === $service_obj->get_name() && ! str_starts_with( $url, $base_directory ) ) {
				$url = $base_directory . $url;
			}

			// add the file to the list.
			$files[] = array(
				'name'          => wp_strip_all_tags( $this->get_scalar_as_string( $file['title'] ?? '' ) ),
				'url'           => $this->mask_credentials( $url ),
				'filesize'      => absint( $file['filesize'] ?? 0 ),
				'mime_type'     => $this->get_scalar_as_string( $file['mime-type'] ?? '' ),
				'last_modified' => wp_strip_all_tags( $this->get_scalar_as_string( $file['last-modified'] ?? '' ) ),
			);
		}

		// collect the directories.
		$directories = array();
		foreach ( $entry['dirs'] as $path => $dir ) {
			$directories[] = array(
				'name'      => wp_strip_all_tags( is_array( $dir ) ? $this->get_scalar_as_string( $dir['title'] ?? '' ) : '' ),
				'directory' => $this->mask_credentials( (string) $path ),
			);
		}

		// return the result with a limited amount of files.
		$max_files = 10 * self::MAX_FILES_IN_RESPONSE;
		return array(
			'directory'   => $this->mask_credentials( $directory ),
			'files'       => array_slice( $files, 0, $max_files ),
			'directories' => $directories,
			'truncated'   => count( $files ) > $max_files,
		);
	}

	/**
	 * Run the synchronization of an external source.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function sync_source( mixed $input = null ): array|WP_Error {
		// bail if the synchronization is not enabled.
		if ( 1 !== absint( get_option( 'eml_sync' ) ) ) {
			return $this->get_error( 'efml_sync_disabled', __( 'Synchronisation is not enabled in the plugin settings.', 'external-files-in-media-library' ), 400 );
		}

		// get the external source.
		$term = $this->get_source_by_id( $this->get_int( $this->get_input( $input ), 'source_id' ) );

		// bail if the external source could not be used.
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// get the service of this external source.
		$service_obj = Services::get_instance()->get_service_by_name( (string) get_term_meta( $term->term_id, 'type', true ) );

		// bail if the service is unknown.
		if ( ! $service_obj instanceof Directory_Listing_Base ) {
			return $this->get_error( 'efml_unknown_service', __( 'The service of this external source is unknown.', 'external-files-in-media-library' ), 400 );
		}

		// bail if the service does not support the synchronization.
		if ( ! $service_obj instanceof Service_Base || $service_obj->is_sync_disabled() ) {
			return $this->get_error( 'efml_sync_not_supported', __( 'The service of this external source does not support the synchronization.', 'external-files-in-media-library' ), 400 );
		}

		// get the configuration of the external source.
		$term_data = Taxonomy::get_instance()->get_entry( $term->term_id );

		// bail if no configuration could be loaded.
		if ( empty( $term_data ) ) {
			return $this->get_error( 'efml_source_without_configuration', __( 'The external source does not have any configuration.', 'external-files-in-media-library' ), 400 );
		}

		// get the URL.
		$url = $service_obj->get_url( (string) $term_data['directory'] );

		// collect the synchronized files.
		$synced_ids = array();
		$collector  = static function ( File $external_file_obj ) use ( &$synced_ids ): void {
			$synced_ids[ $external_file_obj->get_id() ] = $external_file_obj->get_id();
		};
		add_action( 'efml_after_file_save', $collector, 100 );

		// mark sync as running and reset its counter.
		update_option( 'eml_sync_running', time() );
		update_option( 'eml_sync_url_count', 0 );
		update_option( 'eml_sync_url_max', 0 );
		update_option( 'eml_sync_title', __( 'Sync of files starting ...', 'external-files-in-media-library' ) );

		// secure the newest log entry before we start to get only the errors of this run.
		$last_log_id = $this->get_last_log_id();

		// secure the options in the request, as the extensions set them there for the synchronization.
		$original_values = $this->set_request_options(
			array(
				'add_to_queue'      => null,
				'real_import'       => null,
				'use_dates'         => null,
				'use_specific_date' => null,
			)
		);

		// get the synchronization object.
		$sync_obj = Synchronization::get_instance();

		try {
			// run the synchronization.
			$sync_obj->sync( $url, $term_data, $term->term_id );
		} catch ( Throwable $e ) {
			return $this->get_error( 'efml_sync_failed', wp_strip_all_tags( $e->getMessage() ), 500 );
		} finally {
			// mark sync as not running.
			delete_option( 'eml_sync_running' );

			// remove our collector.
			remove_action( 'efml_after_file_save', $collector, 100 );

			// remove the hooks the synchronization added, as they would also be used for every import
			// which follows in the same request (e.g., if multiple abilities are executed in one process).
			remove_filter( 'efml_duplicate_check', array( $sync_obj, 'disable_duplicate_check' ) );
			remove_filter( 'efml_file_import_attachment', array( $sync_obj, 'get_attachment_id' ) );
			remove_action( 'efml_after_file_save', array( $sync_obj, 'mark_as_synced' ) );

			// restore the original values in the request.
			$this->restore_request_options( $original_values );

			// remove the configuration of this run from the import object.
			Import::get_instance()->set_fields( array() );
			Import::get_instance()->set_term_id( 0 );
		}

		// collect the data of the synchronized files.
		$synced_ids = array_values( $synced_ids );
		$files      = array();
		foreach ( array_slice( $synced_ids, 0, self::MAX_FILES_IN_RESPONSE ) as $attachment_id ) {
			$files[] = $this->get_file_data( Files::get_instance()->get_file( $attachment_id ) );
		}

		// return the result.
		return array(
			'source'       => $this->get_source_data( $term ),
			'synced_count' => count( $synced_ids ),
			'files'        => $files,
			'truncated'    => count( $synced_ids ) > count( $files ),
			'errors'       => $this->get_errors_since( $last_log_id ),
		);
	}

	/**
	 * Return the synchronization status of external sources.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_sync_status( mixed $input = null ): array|WP_Error {
		// get the requested external source.
		$source_id = $this->get_int( $this->get_input( $input ), 'source_id' );

		// get the external sources to use.
		$terms = array();
		if ( $source_id > 0 ) {
			// get the single external source.
			$term = $this->get_source_by_id( $source_id );

			// bail if the external source could not be used.
			if ( is_wp_error( $term ) ) {
				return $term;
			}

			// use only this one.
			$terms[] = $term;
		} else {
			$terms = $this->get_allowed_sources();
		}

		// collect the status of each external source.
		$sources = array();
		foreach ( $terms as $term ) {
			// get the service of this external source.
			$service_obj = Services::get_instance()->get_service_by_name( (string) get_term_meta( $term->term_id, 'type', true ) );

			// get the newest synchronized file of this external source.
			$query = $this->get_synced_files_query( $term->term_id, 1 );
			$posts = $this->get_posts_of_query( $query );

			// get the time of its synchronization.
			$last_sync = 0;
			if ( ! empty( $posts ) ) {
				$last_sync = absint( get_post_meta( absint( $posts[0] ), 'eml_synced_time', true ) );
			}

			// add the status to the list.
			$sources[] = array(
				'source'         => $this->get_source_data( $term ),
				'sync_supported' => $service_obj instanceof Service_Base && ! $service_obj->is_sync_disabled(),
				'synced_files'   => absint( $query->found_posts ),
				'last_sync'      => $this->get_iso_date( $last_sync ),
				'next_run'       => $this->get_iso_date( $this->get_next_sync_run( $term->term_id ) ),
			);
		}

		// return the status.
		return array(
			'enabled' => 1 === absint( get_option( 'eml_sync' ) ),
			'running' => absint( get_option( 'eml_sync_running' ) ) > 0,
			'sources' => $sources,
		);
	}

	/**
	 * Delete the synchronized files of an external source.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function delete_synced_files( mixed $input = null ): array|WP_Error {
		// get the external source.
		$term = $this->get_source_by_id( $this->get_int( $this->get_input( $input ), 'source_id' ) );

		// bail if the external source could not be used.
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// get the amount of synchronized files before we delete them.
		$before = absint( $this->get_synced_files_query( $term->term_id, 1 )->found_posts );

		// delete them.
		Synchronization::get_instance()->delete_synced_files( $term->term_id );

		// get the amount of synchronized files which still exist.
		$after = absint( $this->get_synced_files_query( $term->term_id, 1 )->found_posts );

		// return the amount of deleted files.
		return array(
			'deleted' => max( 0, $before - $after ),
		);
	}

	/**
	 * Add URLs to the queue.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function queue_add( mixed $input = null ): array|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// force the usage of the queue.
		$input['queue'] = true;

		// and run the import with it.
		return $this->import( $input );
	}

	/**
	 * Return the status of the queue.
	 *
	 * @return array<string,mixed>
	 */
	public function queue_status(): array {
		// get the queue object.
		$queue = Queue::get_instance();

		// get all entries.
		$all_entries = $queue->get_urls( '', true );

		// collect the newest entries without their credentials.
		$entries = array();
		foreach ( array_slice( $all_entries, 0, self::MAX_FILES_IN_RESPONSE ) as $entry ) {
			$entries[] = array(
				'id'    => absint( $entry['id'] ?? 0 ),
				'url'   => $this->mask_credentials( $this->get_scalar_as_string( $entry['url'] ?? '' ) ),
				'state' => $this->get_scalar_as_string( $entry['state'] ?? '' ),
				'date'  => $this->get_scalar_as_string( $entry['date'] ?? '' ),
			);
		}

		// return the status.
		return array(
			'new'           => count( $queue->get_urls( 'new', true ) ),
			'error'         => count( $queue->get_urls( 'error', true ) ),
			'limit_per_run' => absint( get_option( 'eml_queue_limit' ) ),
			'next_run'      => $this->get_iso_date( absint( wp_next_scheduled( 'eml_queue' ) ) ),
			'entries'       => $entries,
		);
	}

	/**
	 * Process the queue.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function queue_process(): array|WP_Error {
		// get the queue object.
		$queue = Queue::get_instance();

		// get the state of the queue before we process it.
		$new_before   = count( $queue->get_urls( 'new', true ) );
		$error_before = count( $queue->get_urls( 'error', true ) );

		// collect the imported files.
		$imported_ids = array();
		$collector    = static function ( File $external_file_obj ) use ( &$imported_ids ): void {
			$imported_ids[ $external_file_obj->get_id() ] = $external_file_obj->get_id();
		};
		add_action( 'efml_after_file_save', $collector, 100 );

		// mark the import as running to prevent the export of the imported files.
		if ( ! defined( 'EFML_URL_IMPORT_RUNNING' ) ) {
			define( 'EFML_URL_IMPORT_RUNNING', true );
		}

		// secure the newest log entry before we start to get only the errors of this run.
		$last_log_id = $this->get_last_log_id();

		try {
			// process the queue.
			$queue->process_queue();
		} catch ( Throwable $e ) {
			return $this->get_error( 'efml_queue_failed', wp_strip_all_tags( $e->getMessage() ), 500 );
		} finally {
			// remove our collector.
			remove_action( 'efml_after_file_save', $collector, 100 );

			// remove the credentials of the last entry from the import object.
			Import::get_instance()->set_fields( array() );
		}

		// get the state of the queue after the processing.
		$new_after   = count( $queue->get_urls( 'new', true ) );
		$error_after = count( $queue->get_urls( 'error', true ) );

		// return the result.
		return array(
			'processed'      => max( 0, $new_before - $new_after ),
			'imported_count' => count( $imported_ids ),
			'failed'         => max( 0, $error_after - $error_before ),
			'remaining'      => $new_after,
			'errors'         => $this->get_errors_since( $last_log_id ),
		);
	}

	/**
	 * Clean up the queue.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>
	 */
	public function queue_cleanup( mixed $input = null ): array {
		// get the queue object.
		$queue = Queue::get_instance();

		// get the amount of entries before the cleanup.
		$before = count( $queue->get_urls( '', true ) );

		if ( 'all' === $this->get_string( $this->get_input( $input ), 'mode' ) ) {
			// delete every entry.
			$queue->clear();
		} else {
			// delete the error entries. They are only deleted in parts, so repeat it until all are deleted.
			$remaining_errors = count( $queue->get_urls( 'error', true ) );
			while ( $remaining_errors > 0 ) {
				$queue->clear_error_entries();

				// bail if nothing has been deleted in this run to prevent an endless loop.
				$errors = count( $queue->get_urls( 'error', true ) );
				if ( $errors >= $remaining_errors ) {
					break;
				}
				$remaining_errors = $errors;
			}
		}

		// get the amount of entries after the cleanup.
		$after = count( $queue->get_urls( '', true ) );

		// return the result.
		return array(
			'removed'   => max( 0, $before - $after ),
			'remaining' => $after,
		);
	}

	/**
	 * Export a local file to the external sources.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function export_file( mixed $input = null ): array|WP_Error {
		// bail if export is not enabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return $this->get_error( 'efml_export_disabled', __( 'Export is not enabled in the plugin settings.', 'external-files-in-media-library' ), 400 );
		}

		// bail if the export of local files is not enabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			return $this->get_error( 'efml_export_disabled', __( 'Export for local files is not enabled!', 'external-files-in-media-library' ), 400 );
		}

		// bail if no external source is enabled for the export.
		if ( empty( Export::get_instance()->get_export_terms() ) ) {
			return $this->get_error( 'efml_no_export_source', __( 'No external source is enabled for the export.', 'external-files-in-media-library' ), 400 );
		}

		// get the attachment ID.
		$attachment_id = $this->get_int( $this->get_input( $input ), 'attachment_id' );

		// bail if this is not an attachment.
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return $this->get_error( 'efml_file_not_found', __( 'The given attachment does not exist in the media library.', 'external-files-in-media-library' ), 404 );
		}

		// bail if the actual user is not allowed to edit this attachment.
		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return $this->get_error( 'efml_not_allowed', __( 'You are not allowed to edit this file.', 'external-files-in-media-library' ), 403 );
		}

		// bail if this is already an external file.
		if ( Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			return $this->get_error( 'efml_already_external', __( 'Given file for export is already saved as external file.', 'external-files-in-media-library' ), 400 );
		}

		// secure the newest log entry before we start to get only the errors of this run.
		$last_log_id = $this->get_last_log_id();

		// log this event.
		Log::get_instance()->create( __( 'Checking if local file should be exported by request.', 'external-files-in-media-library' ), '', 'info', 2 );

		// export the file.
		try {
			$exported = Export::get_instance()->export_file( $attachment_id );
		} catch ( Throwable $e ) {
			return $this->get_error( 'efml_export_failed', wp_strip_all_tags( $e->getMessage() ), 500 );
		}

		// bail if the file has not been exported.
		if ( ! $exported ) {
			return $this->get_error( 'efml_export_failed', $this->get_error_text( $this->get_errors_since( $last_log_id ), __( 'The file could not be exported.', 'external-files-in-media-library' ) ), 400 );
		}

		// return the data of the file, which is an external file now.
		return $this->get_file_data( Files::get_instance()->get_file( $attachment_id ) );
	}

	/**
	 * Enable or disable the export to an external source.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_export_state( mixed $input = null ): array|WP_Error {
		// get the input as array.
		$input = $this->get_input( $input );

		// bail if export is not enabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return $this->get_error( 'efml_export_disabled', __( 'Export is not enabled in the plugin settings.', 'external-files-in-media-library' ), 400 );
		}

		// get the external source.
		$term = $this->get_source_by_id( $this->get_int( $input, 'source_id' ) );

		// bail if the external source could not be used.
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// get the requested state.
		$enabled = $this->get_bool( $input, 'enabled' );

		// get the actual state.
		$is_enabled = ! empty( get_term_meta( $term->term_id, 'efml_export', true ) );

		// only change the state if it is another one, as the enabled state would be saved multiple times otherwise.
		if ( $enabled !== $is_enabled ) {
			if ( $enabled ) {
				// get the service of this external source.
				$service_obj = Export::get_instance()->get_service_object_by_type( (string) get_term_meta( $term->term_id, 'type', true ) );

				// bail if the service does not support the export.
				if ( ! $service_obj instanceof Service_Base || ! $service_obj->get_export_object() instanceof Export_Base ) {
					return $this->get_error( 'efml_export_not_supported', __( 'Export to this external source is not supported.', 'external-files-in-media-library' ), 400 );
				}

				// bail if the service requires a URL for the exported files and none is configured.
				if ( $service_obj->get_export_object()->is_url_required() && empty( get_term_meta( $term->term_id, 'efml_export_url', true ) ) ) {
					return $this->get_error( 'efml_export_not_configured', __( 'This external source requires a public URL for the exported files. Configure the export for it once in the backend under Media > External Sources before enabling it.', 'external-files-in-media-library' ), 400 );
				}
			}

			// save the new state.
			Export::get_instance()->set_state_for_term( $term->term_id, $enabled ? 1 : 0 );
		}

		// return the data of the external source.
		return $this->get_source_data( $term );
	}

	/**
	 * Run the import of the given URLs.
	 *
	 * @param array<int,string>   $urls The URLs to import.
	 * @param array<string,mixed> $fields The fields with the credentials to use.
	 * @param int                 $source_id The ID of the external source the files should be assigned to.
	 * @param array<string,bool>  $options The options for this import ("queue", "real_import", "use_dates").
	 *
	 * @return array{imported_ids:array<int,int>,queued:int,errors:array<int,array<string,string>>}
	 */
	private function run_import( array $urls, array $fields, int $source_id = 0, array $options = array() ): array {
		// make sure the services are initialized for the actual user, also if this is not called by an ability.
		$this->init_services();

		// get the import object.
		$import = Import::get_instance();

		// collect the results.
		$imported_ids = array();
		$queued       = 0;
		$errors       = array();
		$failed       = array();

		// collect each file which is saved during this import.
		$collector = static function ( File $external_file_obj ) use ( &$imported_ids ): void {
			$imported_ids[ $external_file_obj->get_id() ] = $external_file_obj->get_id();
		};
		add_action( 'efml_after_file_save', $collector, 100 );

		// the extensions for the import read their options from the request, so we set them there for this
		// import and restore the original values afterwards.
		$original_values = $this->set_request_options(
			array(
				'add_to_queue' => ! empty( $options['queue'] ) ? 1 : null,
				'real_import'  => isset( $options['real_import'] ) ? ( $options['real_import'] ? 1 : 0 ) : null,
				'use_dates'    => isset( $options['use_dates'] ) ? ( $options['use_dates'] ? 1 : 0 ) : null,
			)
		);

		// mark the import as running to prevent the export of the imported files.
		if ( ! defined( 'EFML_URL_IMPORT_RUNNING' ) ) {
			define( 'EFML_URL_IMPORT_RUNNING', true );
		}

		// get the URLs which are already in the queue.
		$queue_before = ! empty( $options['queue'] ) ? $this->get_queued_urls() : array();

		// secure the newest log entry before we start to get only the errors of this run.
		$last_log_id = $this->get_last_log_id();

		try {
			// set the credentials and the external source.
			$import->set_fields( $fields );
			$import->set_term_id( $source_id );

			foreach ( $urls as $url ) {
				// convert spaces to HTML-encoded spaces, but only in HTTP-URLs. Other protocols use paths,
				// which are not URLs (e.g., "DropBox/my file.pdf"), and must get them unchanged. Whether the
				// given string is usable is decided by the protocols during the import.
				if ( 0 === stripos( $url, 'http://' ) || 0 === stripos( $url, 'https://' ) ) {
					$url = str_replace( ' ', '%20', $url );
				}

				// bail if this URL already exists as external file, as not every protocol checks this itself.
				if ( $this->get_file_by_url( $url ) instanceof File ) {
					$errors[] = array(
						'url'     => $this->mask_credentials( $url ),
						'message' => __( 'The specified URL already exist in your media library.', 'external-files-in-media-library' ),
					);
					continue;
				}

				try {
					// import the URL and remember it, if this was not successfully.
					if ( ! $import->add_url( $url ) ) {
						$failed[] = $url;
					}
				} catch ( Throwable $e ) {
					$errors[] = array(
						'url'     => $this->mask_credentials( $url ),
						'message' => wp_strip_all_tags( $e->getMessage() ),
					);
				}
			}
		} finally {
			// remove our collector.
			remove_action( 'efml_after_file_save', $collector, 100 );

			// restore the original values in the request.
			$this->restore_request_options( $original_values );

			// remove the configuration of this import from the import object.
			$import->set_fields( array() );
			$import->set_term_id( 0 );
		}

		// check which of the not imported URLs are in the queue now.
		if ( ! empty( $options['queue'] ) ) {
			$queue_after = $this->get_queued_urls();
			foreach ( $failed as $index => $url ) {
				// bail if this URL is not in the queue.
				if ( ! in_array( $url, $queue_after, true ) ) {
					continue;
				}

				// this URL did not fail.
				unset( $failed[ $index ] );

				// count it, if it has been added during this import.
				if ( ! in_array( $url, $queue_before, true ) ) {
					++$queued;
					continue;
				}

				// otherwise show that it was already in the queue.
				$errors[] = array(
					'url'     => $this->mask_credentials( $url ),
					'message' => __( 'URL is already in the queue.', 'external-files-in-media-library' ),
				);
			}
		}

		// get the reasons for each URL from the log entries, which have been created during this import. We use all
		// of them, as not every protocol adds the identifier of the import to its entries.
		$log_errors = $this->get_errors_since( $last_log_id, '', 100 );
		foreach ( $log_errors as $entry ) {
			// bail if this is the info about a URL which is already in the queue, as we have added it above.
			if ( ! empty( $options['queue'] ) && in_array( $entry['url'], $queue_before, true ) ) {
				continue;
			}

			// add the reason.
			$errors[] = array(
				'url'     => $this->mask_credentials( $entry['url'] ),
				'message' => $entry['message'],
			);
		}

		// add a hint for each failed URL without any known reason.
		$urls_with_reason = array_column( $errors, 'url' );
		foreach ( $failed as $url ) {
			// bail if a reason is already known.
			if ( in_array( $this->mask_credentials( $url ), $urls_with_reason, true ) ) {
				continue;
			}

			// add the hint.
			$errors[] = array(
				'url'     => $this->mask_credentials( $url ),
				'message' => empty( $log_errors ) ? __( 'No files to import were found.', 'external-files-in-media-library' ) : __( 'The URL could not be added to the media library.', 'external-files-in-media-library' ),
			);
		}

		// return the result.
		return array(
			'imported_ids' => array_values( $imported_ids ),
			'queued'       => $queued,
			'errors'       => $errors,
		);
	}

	/**
	 * Set options for an import in the request and return their original values.
	 *
	 * The extensions for the import (e.g., queue, real import, dates) read their options from the request.
	 *
	 * @param array<string,int|null> $options The options, null to remove an option from the request.
	 *
	 * @return array<string,array<int,mixed>|null> The original values to use with restore_request_options().
	 */
	private function set_request_options( array $options ): array {
		$original_values = array();
		foreach ( $options as $key => $value ) {
			// secure the original value.
			$original_values[ $key ] = array_key_exists( $key, $_POST ) ? array( $_POST[ $key ] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Only saved to restore the value after the import.

			// remove the option from the request.
			unset( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			// and set the new one.
			if ( ! is_null( $value ) ) {
				$_POST[ $key ] = $value;
			}
		}
		return $original_values;
	}

	/**
	 * Restore the original values of the options in the request.
	 *
	 * @param array<string,array<int,mixed>|null> $original_values The values from set_request_options().
	 *
	 * @return void
	 */
	private function restore_request_options( array $original_values ): void {
		foreach ( $original_values as $key => $value ) {
			unset( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_array( $value ) ) {
				$_POST[ $key ] = $value[0];
			}
		}
	}

	/**
	 * Switch the hosting of an external file.
	 *
	 * @param array<string,mixed> $input The input of the ability.
	 * @param bool                $to_local True to switch to local hosting, false for external hosting.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function switch_hosting( array $input, bool $to_local ): array|WP_Error {
		// get the external file.
		$external_file_obj = $this->get_file_by_input( $input );

		// bail if the external file could not be loaded.
		if ( is_wp_error( $external_file_obj ) ) {
			return $external_file_obj;
		}

		// do nothing if the file is already hosted as requested.
		if ( $external_file_obj->is_locally_saved() === $to_local ) {
			return $this->get_file_data( $external_file_obj );
		}

		// secure the newest log entry before we start to get only the errors of this run.
		$last_log_id = $this->get_last_log_id();

		// switch the hosting.
		try {
			$switched = $to_local ? $external_file_obj->switch_to_local() : $external_file_obj->switch_to_external();
		} catch ( Throwable $e ) {
			return $this->get_error( 'efml_switch_failed', wp_strip_all_tags( $e->getMessage() ), 500 );
		}

		// bail if the switch failed.
		if ( ! $switched ) {
			$message = $to_local ? __( 'Error during the switch to local hosting.', 'external-files-in-media-library' ) : __( 'Error during the switch to external hosting.', 'external-files-in-media-library' );
			return $this->get_error( 'efml_switch_failed', $this->get_error_text( $this->get_errors_since( $last_log_id, $external_file_obj->get_url( true ) ), $message ), 400 );
		}

		// log this event.
		Log::get_instance()->create( __( 'File has been switched the hosting.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'success' );

		// return the data of the file from a fresh object, as the used one contains the old values.
		return $this->get_file_data( Files::get_instance()->get_file( $external_file_obj->get_id() ) );
	}

	/**
	 * Return the input of an ability as array.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>
	 */
	private function get_input( mixed $input ): array {
		return is_array( $input ) ? $input : array();
	}

	/**
	 * Return a value from the input as trimmed string.
	 *
	 * @param array<string,mixed> $input The input.
	 * @param string              $key The key to use.
	 *
	 * @return string
	 */
	private function get_string( array $input, string $key ): string {
		return isset( $input[ $key ] ) ? trim( $this->get_scalar_as_string( $input[ $key ] ) ) : '';
	}

	/**
	 * Return a value from the input as positive integer.
	 *
	 * @param array<string,mixed> $input The input.
	 * @param string              $key The key to use.
	 *
	 * @return int
	 */
	private function get_int( array $input, string $key ): int {
		return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
	}

	/**
	 * Return a value from the input as boolean.
	 *
	 * @param array<string,mixed> $input The input.
	 * @param string              $key The key to use.
	 *
	 * @return bool
	 */
	private function get_bool( array $input, string $key ): bool {
		// get the value.
		$value = $input[ $key ] ?? false;

		// return it if it is already a boolean.
		if ( is_bool( $value ) ) {
			return $value;
		}

		// check strings, as they are used in GET requests.
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true' ), true );
		}

		// everything else is only true if it is 1.
		return 1 === $value;
	}

	/**
	 * Return the results of a query, which has already been run.
	 *
	 * @param WP_Query $query The query object.
	 *
	 * @return array<int,int|\WP_Post>
	 */
	private function get_posts_of_query( WP_Query $query ): array {
		return is_array( $query->posts ) ? $query->posts : array(); // @phpstan-ignore function.alreadyNarrowedType
	}

	/**
	 * Return a scalar value as string, everything else as empty string.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 */
	private function get_scalar_as_string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Return the list of URLs from the input.
	 *
	 * @param array<string,mixed> $input The input.
	 *
	 * @return array<int,string>
	 */
	private function get_urls( array $input ): array {
		// bail if no list is given.
		if ( empty( $input['urls'] ) || ! is_array( $input['urls'] ) ) {
			return array();
		}

		// collect the URLs.
		$urls = array();
		foreach ( $input['urls'] as $url ) {
			// get the URL as string.
			$url = trim( $this->get_scalar_as_string( $url ) );

			// bail if it is empty.
			if ( '' === $url ) {
				continue;
			}

			// add it to the list.
			$urls[] = $url;
		}

		// return the list without duplicates.
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Complete paths with the directory of an external source.
	 *
	 * Values are used unchanged if they are complete URLs or already start with the directory of the
	 * external source, like the values returned by the ability "list-directory" (e.g., "DropBox/file.pdf"
	 * for the directory "DropBox/"). All other values are used as paths in this directory.
	 *
	 * @param array<int,string> $urls The URLs or paths.
	 * @param string            $directory The directory of the external source.
	 *
	 * @return array<int,string>
	 */
	private function get_urls_for_source( array $urls, string $directory ): array {
		// get the parts of the directory.
		$directory_url = wp_parse_url( $directory );

		// get the directory without trailing slash to compare it with the given values.
		$directory_prefix = untrailingslashit( $directory );

		foreach ( $urls as $index => $url ) {
			// get the parts of the given value.
			$parse_url = wp_parse_url( $url );

			// bail if this is a complete URL.
			if ( ! empty( $parse_url ) && ! empty( $parse_url['scheme'] ) ) {
				continue;
			}

			// bail if this is the directory of the external source itself or a path, which already contains it.
			if ( '' !== $directory_prefix && ( 0 === strcasecmp( $url, $directory_prefix ) || 0 === stripos( $url, $directory_prefix . '/' ) ) ) {
				continue;
			}

			// use the given value as absolute path on the host of the external source ..
			if ( is_array( $directory_url ) && ! empty( $directory_url['scheme'] ) && ! empty( $directory_url['host'] ) && str_starts_with( $url, '/' ) ) {
				$urls[ $index ] = $directory_url['scheme'] . '://' . $directory_url['host'] . ( ! empty( $directory_url['port'] ) ? ':' . $directory_url['port'] : '' ) . $url;
				continue;
			}

			// .. or as path relative to its directory.
			$urls[ $index ] = trailingslashit( $directory ) . ltrim( $url, '/' );
		}

		// return the resulting list.
		return $urls;
	}

	/**
	 * Return the fields for the credentials from the input.
	 *
	 * @param array<string,mixed> $input The input.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function get_credential_fields( array $input ): array {
		// get the credentials without any changes, as e.g. a password could contain any character.
		$login    = isset( $input['login'] ) ? $this->get_scalar_as_string( $input['login'] ) : '';
		$password = isset( $input['password'] ) ? $this->get_scalar_as_string( $input['password'] ) : '';

		// return the fields, we assume it is an HTTP- or FTP-connection.
		return array(
			'login'    => array(
				'value' => $login,
			),
			'password' => array(
				'value' => $password,
			),
		);
	}

	/**
	 * Return an error object.
	 *
	 * @param string $code The error code.
	 * @param string $message The message.
	 * @param int    $status The HTTP status to use.
	 *
	 * @return WP_Error
	 */
	private function get_error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Return the text for an error from a list of reasons.
	 *
	 * @param array<int,array<string,string>> $errors The list of reasons.
	 * @param string                          $fallback The text to use if no reason is known.
	 *
	 * @return string
	 */
	private function get_error_text( array $errors, string $fallback ): string {
		// collect the reasons.
		$messages = array();
		foreach ( $errors as $error ) {
			if ( ! empty( $error['message'] ) ) {
				$messages[] = $error['message'];
			}
		}

		// bail if no reason is known.
		if ( empty( $messages ) ) {
			return $fallback;
		}

		// return the text with the reasons.
		return $fallback . ' ' . implode( ' ', array_unique( $messages ) );
	}

	/**
	 * Return the text for an error from the errors a service collected.
	 *
	 * @param Directory_Listing_Base $service_obj The service object.
	 * @param string                 $fallback The text to use if no reason is known.
	 * @param int                    $offset The amount of errors to skip, as the service object collects them over its complete lifetime.
	 *
	 * @return string
	 */
	private function get_service_error_text( Directory_Listing_Base $service_obj, string $fallback, int $offset = 0 ): string {
		// collect the reasons.
		$errors = array();
		foreach ( array_slice( $service_obj->get_errors(), $offset ) as $error ) {
			// add each message of this error.
			foreach ( $error->get_error_messages() as $message ) {
				$errors[] = array(
					'message' => trim( wp_strip_all_tags( (string) $message ) ),
				);
			}
		}

		// return the text.
		return $this->get_error_text( $errors, $fallback );
	}

	/**
	 * Return the external file object for a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return File|false
	 */
	private function get_file_by_url( string $url ): File|false {
		// get the file by the given URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );

		// if no file has been found, try the decoded URL, as URLs are saved decoded in the media library.
		if ( ! $external_file_obj instanceof File && urldecode( $url ) !== $url ) {
			$external_file_obj = Files::get_instance()->get_file_by_url( urldecode( $url ) );
		}

		// return the result.
		return $external_file_obj instanceof File ? $external_file_obj : false;
	}

	/**
	 * Return the external file object which is requested with "attachment_id" or "url".
	 *
	 * @param array<string,mixed> $input The input.
	 *
	 * @return File|WP_Error
	 */
	private function get_file_by_input( array $input ): File|WP_Error {
		// get the file by its ID.
		$attachment_id = $this->get_int( $input, 'attachment_id' );
		if ( $attachment_id > 0 ) {
			// get the external file object.
			$external_file_obj = Files::get_instance()->get_file( $attachment_id );

			// bail if this is not an external file.
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! $external_file_obj->is_valid() ) {
				return $this->get_error( 'efml_file_not_found', __( 'Given file does not exist or is not an external file!', 'external-files-in-media-library' ), 404 );
			}

			// return the object.
			return $external_file_obj;
		}

		// get the URL.
		$url = $this->get_string( $input, 'url' );

		// bail if no file is requested.
		if ( '' === $url ) {
			return $this->get_error( 'efml_missing_file', __( 'No file given. Either "attachment_id" or "url" is required.', 'external-files-in-media-library' ), 400 );
		}

		// get the file by its URL.
		$external_file_obj = $this->get_file_by_url( $url );

		// bail if no external file exists for this URL.
		if ( ! $external_file_obj instanceof File ) {
			return $this->get_error( 'efml_file_not_found', __( 'Given file does not exist or is not an external file!', 'external-files-in-media-library' ), 404 );
		}

		// return the object.
		return $external_file_obj;
	}

	/**
	 * Return the data of an external file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return array<string,mixed>
	 */
	private function get_file_data( File $external_file_obj ): array {
		// get the ID.
		$attachment_id = $external_file_obj->get_id();

		// get the URL which is used on the website.
		$public_url = wp_get_attachment_url( $attachment_id );

		// if this is not an external file (e.g., after a real import), return the data of the local file.
		if ( ! $external_file_obj->is_valid() ) {
			// get the path of the file.
			$path = get_attached_file( $attachment_id );

			// return the data.
			return array(
				'attachment_id' => $attachment_id,
				'url'           => '',
				'public_url'    => is_string( $public_url ) ? $this->mask_credentials( $public_url ) : '',
				'title'         => get_the_title( $attachment_id ),
				'mime_type'     => (string) get_post_mime_type( $attachment_id ),
				'file_type'     => '',
				'filesize'      => is_string( $path ) && is_file( $path ) ? absint( filesize( $path ) ) : 0,
				'hosting'       => 'local',
				'available'     => true,
				'proxied'       => false,
				'protocol'      => '',
				'service'       => '',
				'source_id'     => 0,
				'source_name'   => '',
				'date'          => $this->get_iso_date( absint( get_post_time( 'U', true, $attachment_id ) ) ),
			);
		}

		// get the protocol handler.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// get the file type.
		$file_type_obj = $external_file_obj->get_file_type_obj();

		// get the external source of this file.
		$source_id   = 0;
		$source_name = '';
		$terms       = wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name() );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$source_id   = $terms[0]->term_id;
			$source_name = $this->mask_credentials( $terms[0]->name );
		}

		// return the data.
		return array(
			'attachment_id' => $attachment_id,
			'url'           => $this->mask_credentials( $external_file_obj->get_url( true ) ),
			'public_url'    => is_string( $public_url ) ? $this->mask_credentials( $public_url ) : '',
			'title'         => $external_file_obj->get_title(),
			'mime_type'     => $external_file_obj->get_mime_type(),
			'file_type'     => $file_type_obj->get_name(),
			'filesize'      => absint( $external_file_obj->get_filesize() ),
			'hosting'       => $external_file_obj->is_locally_saved() ? 'local' : 'external',
			'available'     => (bool) $external_file_obj->is_available(),
			'proxied'       => $file_type_obj->is_proxy_enabled(),
			'protocol'      => $protocol_handler_obj instanceof Protocol_Base ? $protocol_handler_obj->get_name() : '',
			'service'       => $external_file_obj->get_service_name(),
			'source_id'     => $source_id,
			'source_name'   => $source_name,
			'date'          => $this->get_iso_date( absint( get_post_meta( $attachment_id, 'eml_external_file_date', true ) ) ),
		);
	}

	/**
	 * Return a meta query for a flag which is saved as 1 if it is set.
	 *
	 * @param string $key The meta key.
	 * @param bool   $is_set Whether the flag must be set.
	 *
	 * @return array<int|string,mixed>
	 */
	private function get_flag_meta_query( string $key, bool $is_set ): array {
		// return the query for the flag being set.
		if ( $is_set ) {
			return array(
				'key'   => $key,
				'value' => '1',
			);
		}

		// return the query for the flag not being set.
		return array(
			'relation' => 'OR',
			array(
				'key'     => $key,
				'value'   => '1',
				'compare' => '!=',
			),
			array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Return the given log entries in the format for the response.
	 *
	 * @param array<int,array<string,mixed>> $log_entries The entries from the log.
	 * @param int                            $last_log_id Only return entries which are newer than the entry with this ID.
	 *
	 * @return array<int,array{id:int,date:string,state:string,url:string,message:string}>
	 */
	private function get_log_entries( array $log_entries, int $last_log_id = 0 ): array {
		// collect the entries.
		$entries = array();
		foreach ( $log_entries as $log_entry ) {
			// get the ID.
			$id = absint( $log_entry['id'] ?? 0 );

			// bail if the entry is too old.
			if ( $id <= $last_log_id ) {
				continue;
			}

			// add the entry as plain text, as the log contains HTML.
			$entries[] = array(
				'id'      => $id,
				'date'    => $this->get_scalar_as_string( $log_entry['date'] ?? '' ),
				'state'   => $this->get_scalar_as_string( $log_entry['state'] ?? '' ),
				'url'     => $this->mask_credentials( html_entity_decode( $this->get_scalar_as_string( $log_entry['url'] ?? '' ), ENT_QUOTES ) ),
				'message' => trim( html_entity_decode( wp_strip_all_tags( $this->get_scalar_as_string( $log_entry['log'] ?? '' ) ), ENT_QUOTES ) ),
			);
		}

		// return the entries.
		return $entries;
	}

	/**
	 * Return the ID of the newest entry in the log.
	 *
	 * We use it to get only the entries which are created during the run of an ability.
	 *
	 * @return int
	 */
	private function get_last_log_id(): int {
		// get the newest entry.
		$log_entries = Log::get_instance()->get_entries( array( 'limit' => 1 ) );

		// return its ID, or 0 if the log is empty.
		return ! empty( $log_entries[0]['id'] ) ? absint( $log_entries[0]['id'] ) : 0;
	}

	/**
	 * Return the errors from the log which are newer than a given entry.
	 *
	 * @param int    $last_log_id The ID of the log entry.
	 * @param string $url Only errors for this URL (optional).
	 * @param int    $limit The max amount of errors (optional).
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_errors_since( int $last_log_id, string $url = '', int $limit = 20 ): array {
		// get the newest errors.
		$log_entries = $this->get_log_entries(
			Log::get_instance()->get_entries(
				array(
					'state' => 'error',
					'url'   => $url,
					'limit' => $limit,
				)
			),
			$last_log_id
		);

		// collect them in the order they occurred.
		$errors = array();
		foreach ( array_reverse( $log_entries ) as $entry ) {
			$errors[] = array(
				'url'     => $entry['url'],
				'message' => $entry['message'],
			);
		}

		// return the errors.
		return $errors;
	}

	/**
	 * Return a timestamp as date in ISO 8601 format (UTC).
	 *
	 * @param int $timestamp The timestamp.
	 *
	 * @return string Empty string if no timestamp is given.
	 */
	private function get_iso_date( int $timestamp ): string {
		return $timestamp > 0 ? gmdate( 'c', $timestamp ) : '';
	}

	/**
	 * Remove credentials from a URL (e.g. ftp://user:password@example.com).
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function mask_credentials( string $url ): string {
		return (string) preg_replace( '#^([a-z][a-z0-9+.\-]*://)[^/@\s]+@#i', '$1', $url );
	}

	/**
	 * Return the list of URLs in the queue.
	 *
	 * @return array<int,string>
	 */
	private function get_queued_urls(): array {
		$urls = array();
		foreach ( Queue::get_instance()->get_urls( '', true ) as $entry ) {
			if ( isset( $entry['url'] ) ) {
				$urls[] = $entry['url'];
			}
		}
		return $urls;
	}

	/**
	 * Return the label of a service.
	 *
	 * @param string $service_name The name of the service.
	 *
	 * @return string
	 */
	private function get_service_label( string $service_name ): string {
		// bail if no name is given.
		if ( '' === $service_name ) {
			return __( 'Unknown', 'external-files-in-media-library' );
		}

		// get the service.
		$service_obj = Services::get_instance()->get_service_by_name( $service_name );

		// use the name if the service is unknown.
		if ( ! $service_obj instanceof Directory_Listing_Base ) {
			return $service_name;
		}

		// return its label.
		return wp_strip_all_tags( $service_obj->get_label() );
	}

	/**
	 * Return the fields a service needs to be added as external source. Their values are never returned.
	 *
	 * @param Directory_Listing_Base $service_obj The service object.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_service_fields( Directory_Listing_Base $service_obj ): array {
		// get the fields.
		try {
			$service_fields = $service_obj->get_fields();
		} catch ( Throwable $e ) {
			return array();
		}

		// collect them.
		$fields = array();
		foreach ( $service_fields as $name => $field ) {
			// add the field without its value.
			$fields[] = array(
				'name'     => (string) $name,
				'label'    => wp_strip_all_tags( $this->get_scalar_as_string( $field['label'] ?? '' ) ),
				'type'     => $this->get_scalar_as_string( $field['type'] ?? 'text' ),
				'required' => empty( $field['not_required'] ),
			);
		}

		// return the fields.
		return $fields;
	}

	/**
	 * Return the service object for a name, if the actual user is allowed to use it.
	 *
	 * @param string $service_name The name of the service.
	 *
	 * @return Directory_Listing_Base|WP_Error
	 */
	private function get_service_for_user( string $service_name ): Directory_Listing_Base|WP_Error {
		// get the service.
		$service_obj = '' !== $service_name ? Services::get_instance()->get_service_by_name( $service_name ) : false;

		// bail if the service is unknown.
		if ( ! $service_obj instanceof Directory_Listing_Base ) {
			return $this->get_error( 'efml_unknown_service', __( 'The requested service is unknown. Use the ability "list-services" to get the possible services.', 'external-files-in-media-library' ), 400 );
		}

		// bail if the service is disabled.
		if ( $service_obj->is_disabled() ) {
			return $this->get_error( 'efml_service_disabled', __( 'The requested service is not usable on this website.', 'external-files-in-media-library' ), 400 );
		}

		// bail if the actual user is not allowed to use this service.
		if ( ! current_user_can( $service_obj->get_permission_name() ) ) {
			return $this->get_error( 'efml_not_allowed', __( 'You are not allowed to use this service.', 'external-files-in-media-library' ), 403 );
		}

		// return the object.
		return $service_obj;
	}

	/**
	 * Return the capability for a task on external sources.
	 *
	 * @param string $task The task ("manage_terms" or "delete_terms").
	 *
	 * @return string
	 */
	private function get_source_capability( string $task ): string {
		// get the capabilities.
		$capabilities = Init::get_instance()->get_capabilities();

		// return the requested one, with our main capability as fallback.
		return ! empty( $capabilities[ $task ] ) ? (string) $capabilities[ $task ] : EFML_CAP_NAME;
	}

	/**
	 * Return the external sources the actual user is allowed to use.
	 *
	 * @return array<int,WP_Term>
	 */
	private function get_allowed_sources(): array {
		$sources = array();
		foreach ( Directory_Listing::get_instance()->get_external_sources() as $term ) {
			// bail if the actual user is not allowed to use this external source.
			if ( ! Directory_Listing::get_instance()->is_source_allowed_for_current_user( $term->term_id ) ) {
				continue;
			}

			// add it to the list.
			$sources[] = $term;
		}
		return $sources;
	}

	/**
	 * Return the external source for an ID, if the actual user is allowed to use it.
	 *
	 * @param int $source_id The ID of the external source.
	 *
	 * @return WP_Term|WP_Error
	 */
	private function get_source_by_id( int $source_id ): WP_Term|WP_Error {
		// get the term.
		$term = $source_id > 0 ? get_term( $source_id, Taxonomy::get_instance()->get_name() ) : false;

		// bail if the external source does not exist.
		if ( ! $term instanceof WP_Term ) {
			return $this->get_error( 'efml_source_not_found', __( 'The requested external source does not exist. Use the ability "list-sources" to get the possible external sources.', 'external-files-in-media-library' ), 404 );
		}

		// bail if the actual user is not allowed to use this external source.
		if ( ! Directory_Listing::get_instance()->is_source_allowed_for_current_user( $term->term_id ) ) {
			return $this->get_error( 'efml_not_allowed', __( 'Access not allowed!', 'external-files-in-media-library' ) . ' ' . __( 'This entry has been saved by another user.', 'external-files-in-media-library' ), 403 );
		}

		// return the term.
		return $term;
	}

	/**
	 * Return the data of an external source. Its credentials are never returned.
	 *
	 * @param WP_Term $term The term of the external source.
	 *
	 * @return array<string,mixed>
	 */
	private function get_source_data( WP_Term $term ): array {
		// get the name of the service.
		$service_name = (string) get_term_meta( $term->term_id, 'type', true );

		// get the amount of files of this external source.
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'trash' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filter for term.
					array(
						'taxonomy' => Taxonomy::get_instance()->get_name(),
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		// return the data.
		return array(
			'id'                 => $term->term_id,
			'name'               => $this->mask_credentials( $term->name ),
			'directory'          => $this->mask_credentials( (string) get_term_meta( $term->term_id, 'path', true ) ),
			'service'            => $service_name,
			'service_label'      => $this->get_service_label( $service_name ),
			'files'              => absint( $query->found_posts ),
			'sync_enabled'       => 1 === absint( get_term_meta( $term->term_id, 'sync', true ) ),
			'sync_interval'      => (string) get_term_meta( $term->term_id, 'interval', true ),
			'export_enabled'     => ! empty( get_term_meta( $term->term_id, 'efml_export', true ) ),
			'main_export_source' => absint( get_option( 'eml_export_main_source' ) ) === $term->term_id,
		);
	}

	/**
	 * Return the query for the synchronized files of an external source, the newest first.
	 *
	 * @param int $term_id The ID of the external source.
	 * @param int $limit The max amount of files.
	 *
	 * @return WP_Query
	 */
	private function get_synced_files_query( int $term_id, int $limit ): WP_Query {
		return new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'trash' ),
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'meta_key'       => 'eml_synced_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Filter for additional data.
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
					array(
						'key'     => EFML_POST_META_URL,
						'compare' => 'EXISTS',
					),
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filter for term.
					array(
						'taxonomy' => Taxonomy::get_instance()->get_name(),
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);
	}

	/**
	 * Return the time of the next scheduled synchronization of an external source.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return int The timestamp, 0 if none is scheduled.
	 */
	private function get_next_sync_run( int $term_id ): int {
		// loop through all timestamps of the cron system, they are sorted by time.
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			// bail if no sync event exists on this timestamp.
			if ( ! isset( $hooks['eml_sync'] ) ) {
				continue;
			}

			// check each event of our sync hook on this timestamp.
			foreach ( $hooks['eml_sync'] as $event ) {
				if ( isset( $event['args']['term_id'] ) && absint( $event['args']['term_id'] ) === $term_id ) {
					return absint( $timestamp );
				}
			}
		}

		// return 0 if no event has been found.
		return 0;
	}

	/**
	 * Return whether a directory is part of an external source.
	 *
	 * @param string $directory The requested directory.
	 * @param string $base_directory The main directory of the external source.
	 *
	 * @return bool
	 */
	private function is_directory_in_source( string $directory, string $base_directory ): bool {
		// bail if the requested directory is the main directory.
		if ( $directory === $base_directory ) {
			return true;
		}

		// bail if the path tries to leave the directory.
		if ( preg_match( '#(^|[/\\\\])\.\.([/\\\\]|$)#', $directory ) ) {
			return false;
		}

		// compare both without their protocol, as services add it to their sub-directories.
		$normalize = static function ( string $path ): string {
			return untrailingslashit( (string) preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $path ) );
		};
		$base      = $normalize( $base_directory );

		// the requested directory must be the main directory or a directory in it.
		return $normalize( $directory ) === $base || str_starts_with( $normalize( $directory ), $base . '/' );
	}

	/**
	 * Return the entries for a directory from the listing of a service.
	 *
	 * The services return a single directory, a list of directories or the complete tree.
	 *
	 * @param array<int|string,mixed> $listing The listing from the service.
	 * @param string                  $directory The requested directory.
	 *
	 * @return array{files:array<int|string,mixed>,dirs:array<int|string,mixed>}
	 */
	private function get_directory_entry( array $listing, string $directory ): array {
		// remove the marker for a complete tree.
		unset( $listing['completed'] );

		// use the entry of the requested directory, if the listing contains multiple directories.
		foreach ( array( $directory, trailingslashit( $directory ), untrailingslashit( $directory ) ) as $key ) {
			if ( isset( $listing[ $key ] ) && is_array( $listing[ $key ] ) && ( isset( $listing[ $key ]['files'] ) || isset( $listing[ $key ]['dirs'] ) ) ) {
				$listing = $listing[ $key ];
				break;
			}
		}

		// return the listing if it is a single directory.
		if ( isset( $listing['files'] ) || isset( $listing['dirs'] ) ) {
			return array(
				'files' => isset( $listing['files'] ) && is_array( $listing['files'] ) ? $listing['files'] : array(),
				'dirs'  => isset( $listing['dirs'] ) && is_array( $listing['dirs'] ) ? $listing['dirs'] : array(),
			);
		}

		// otherwise collect the files of all directories in the listing.
		$entry = array(
			'files' => array(),
			'dirs'  => array(),
		);
		foreach ( $listing as $sub ) {
			if ( is_array( $sub ) && isset( $sub['files'] ) && is_array( $sub['files'] ) ) {
				$entry['files'] = array_merge( $entry['files'], array_values( $sub['files'] ) );
			}
		}
		return $entry;
	}
}

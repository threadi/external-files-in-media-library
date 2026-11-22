<?php
/**
 * This file contains the functions for the central media libraries of the network via REST API and Abilities API.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Object for the functions of the central media libraries, which are used via WP-CLI, REST API and Abilities API.
 */
class Api {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Api
	 */
	private static ?Api $instance = null;

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
	 * @return Api
	 */
	public static function get_instance(): Api {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if this is not a multisite.
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_filter( 'efml_abilities', array( $this, 'add_abilities' ) );

		// add the commands for WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'eml network', 'ExternalFilesInMediaLibrary\Plugin\Network\Cli' );
		}
	}

	/**
	 * Return the groups with the state of their websites.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_status(): array {
		$list = array();
		foreach ( Media_Library::get_instance()->get_groups() as $group_id => $group ) {
			$sites = array();
			foreach ( $group['sites'] as $blog_id ) {
				$sites[] = array_merge(
					array(
						'blog_id' => $blog_id,
						'label'   => Media_Library::get_instance()->get_site_label( $blog_id ),
					),
					Overview::get_instance()->get_site_state( $blog_id )
				);
			}
			$list[] = array(
				'id'      => $group_id,
				'name'    => $group['name'],
				'central' => $group['central'],
				'label'   => Media_Library::get_instance()->get_site_label( $group['central'] ),
				'sites'   => $sites,
			);
		}
		return $list;
	}

	/**
	 * Move a website to another group (0 to leave its group).
	 *
	 * @param int $blog_id  The website.
	 * @param int $group_id The group (0 for none).
	 *
	 * @return true|WP_Error
	 */
	public function set_group( int $blog_id, int $group_id ): true|WP_Error {
		// get the groups.
		$groups = Media_Library::get_instance()->get_groups();

		// bail if the website does not exist.
		if ( ! get_site( $blog_id ) ) {
			return new WP_Error( 'efml_network_unknown_site', __( 'The website does not exist.', 'external-files-in-media-library' ) );
		}

		// bail if the group does not exist.
		if ( $group_id > 0 && ! isset( $groups[ $group_id ] ) ) {
			return new WP_Error( 'efml_network_unknown_group', __( 'The group does not exist.', 'external-files-in-media-library' ) );
		}

		// bail if the website is a central media library.
		if ( Media_Library::get_instance()->is_central( $blog_id ) ) {
			return new WP_Error( 'efml_network_central', __( 'The website is a central media library. Remove its group in the settings of the network.', 'external-files-in-media-library' ) );
		}

		// apply the changed groups.
		Membership::get_instance()->apply_groups( $groups, Membership::get_instance()->get_groups_with_site( $groups, $blog_id, $group_id ) );
		return true;
	}

	/**
	 * Change the websites, which could use a file of the given central media library.
	 *
	 * @param int            $central       The website with the central media library.
	 * @param int            $attachment_id The attachment ID in the central media library.
	 * @param string         $mode          The mode: all, only or except.
	 * @param array<int,int> $sites         The websites for "only" or "except".
	 *
	 * @return true|WP_Error
	 */
	public function set_visibility( int $central, int $attachment_id, string $mode, array $sites ): true|WP_Error {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central( $central ) ) {
			return new WP_Error( 'efml_network_no_central', __( 'The website is not a central media library.', 'external-files-in-media-library' ) );
		}

		// bail if the mode is unknown.
		if ( ! in_array( $mode, Media_Library::VISIBILITY_MODES, true ) ) {
			return new WP_Error( 'efml_network_unknown_mode', __( 'Use "all", "only" or "except".', 'external-files-in-media-library' ) );
		}

		// change it in the central media library.
		switch_to_blog( $central );
		$exists = 'attachment' === get_post_type( $attachment_id );
		if ( $exists ) {
			Media_Library::get_instance()->set_visibility( $attachment_id, $mode, array_map( 'absint', $sites ) );
		}
		restore_current_blog();

		// return the result.
		return $exists ? true : new WP_Error( 'efml_network_unknown_file', __( 'The file does not exist in the central media library.', 'external-files-in-media-library' ) );
	}

	/**
	 * Return the usage of a file of the given central media library in the websites.
	 *
	 * @param int $central       The website with the central media library.
	 * @param int $attachment_id The attachment ID in the central media library.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function get_usage( int $central, int $attachment_id ): array|WP_Error {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central( $central ) ) {
			return new WP_Error( 'efml_network_no_central', __( 'The website is not a central media library.', 'external-files-in-media-library' ) );
		}

		// get the usage.
		switch_to_blog( $central );
		$usage = Usage::get_instance()->get_usage( $attachment_id );
		restore_current_blog();

		// return it.
		return $usage;
	}

	/**
	 * Run the synchronization with the central media library in the given website.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return true|WP_Error
	 */
	public function sync( int $blog_id ): true|WP_Error {
		// bail if the website does not use a central media library.
		if ( ! Media_Library::get_instance()->is_using_central( $blog_id ) ) {
			return new WP_Error( 'efml_network_no_member', __( 'The website does not use a central media library.', 'external-files-in-media-library' ) );
		}

		// run it.
		switch_to_blog( $blog_id );
		$term_id = Media_Library::get_instance()->get_central_term_id();
		if ( $term_id > 0 ) {
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );
			Synchronization::get_instance()->sync( (string) ( $term_data['directory'] ?? '' ), $term_data, $term_id );
		}
		restore_current_blog();

		// return the result.
		return $term_id > 0 ? true : new WP_Error( 'efml_network_no_source', __( 'The external source of the central media library does not exist in the website.', 'external-files-in-media-library' ) );
	}

	/**
	 * Return whether the actual user could manage the network.
	 *
	 * @return bool
	 */
	public function can_manage_network(): bool {
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Return whether the actual user could change the given file of the given central media library.
	 *
	 * @param int $central       The website with the central media library.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function can_edit_file( int $central, int $attachment_id ): bool {
		// a network administrator could change every file.
		if ( $this->can_manage_network() ) {
			return true;
		}

		// check the capability in the central media library.
		if ( ! Media_Library::get_instance()->is_central( $central ) ) {
			return false;
		}
		switch_to_blog( $central );
		$allowed = current_user_can( 'edit_post', $attachment_id ) && Upload::get_instance()->may_choose();
		restore_current_blog();
		return $allowed;
	}

	/**
	 * Register the endpoints for the REST API.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			'efml/v1',
			'/network/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return new WP_REST_Response( $this->get_status() );
				},
				'permission_callback' => array( $this, 'can_manage_network' ),
			)
		);
		register_rest_route(
			'efml/v1',
			'/network/site/(?P<blog_id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return $this->get_response( $this->set_group( absint( $request->get_param( 'blog_id' ) ), absint( $request->get_param( 'group' ) ) ) );
				},
				'permission_callback' => array( $this, 'can_manage_network' ),
				'args'                => array(
					'group' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			'efml/v1',
			'/network/site/(?P<blog_id>\d+)/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return $this->get_response( $this->sync( absint( $request->get_param( 'blog_id' ) ) ) );
				},
				'permission_callback' => array( $this, 'can_manage_network' ),
			)
		);
		register_rest_route(
			'efml/v1',
			'/network/(?P<central>\d+)/file/(?P<attachment_id>\d+)/visibility',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return $this->get_response( $this->set_visibility( absint( $request->get_param( 'central' ) ), absint( $request->get_param( 'attachment_id' ) ), (string) $request->get_param( 'mode' ), array_map( 'absint', (array) $request->get_param( 'sites' ) ) ) );
				},
				'permission_callback' => function ( WP_REST_Request $request ) {
					return $this->can_edit_file( absint( $request->get_param( 'central' ) ), absint( $request->get_param( 'attachment_id' ) ) );
				},
				'args'                => array(
					'mode'  => array(
						'type'     => 'string',
						'enum'     => Media_Library::VISIBILITY_MODES,
						'required' => true,
					),
					'sites' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			'efml/v1',
			'/network/(?P<central>\d+)/file/(?P<attachment_id>\d+)/usage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return $this->get_response( $this->get_usage( absint( $request->get_param( 'central' ) ), absint( $request->get_param( 'attachment_id' ) ) ) );
				},
				'permission_callback' => function ( WP_REST_Request $request ) {
					return $this->can_edit_file( absint( $request->get_param( 'central' ) ), absint( $request->get_param( 'attachment_id' ) ) );
				},
			)
		);
	}

	/**
	 * Return the response for the given result.
	 *
	 * @param mixed $result The result.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function get_response( mixed $result ): WP_REST_Response|WP_Error {
		if ( $result instanceof WP_Error ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return new WP_REST_Response( $result );
	}

	/**
	 * Add the abilities for the central media libraries.
	 *
	 * @param array<string,array<string,mixed>> $abilities The abilities.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_abilities( array $abilities ): array {
		// bail if no central media library is used.
		if ( ! Media_Library::get_instance()->is_active() ) {
			return $abilities;
		}

		$file_properties = array(
			'central'       => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The ID of the website with the central media library.', 'external-files-in-media-library' ),
			),
			'attachment_id' => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The attachment ID of the file in the central media library.', 'external-files-in-media-library' ),
			),
		);

		$abilities['network-status']         = array(
			'label'               => __( 'Get the state of the central media libraries', 'external-files-in-media-library' ),
			'description'         => __( 'Returns the groups of the network with their central media library and the state of their websites: number of files, own uploads, errors, overdue tasks and the next synchronization.', 'external-files-in-media-library' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'array' ),
			'execute_callback'    => array( $this, 'get_status' ),
			'permission_callback' => array( $this, 'can_manage_network' ),
			'annotations'         => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
		$abilities['network-file-usage']     = array(
			'label'               => __( 'Get the usage of a file of a central media library', 'external-files-in-media-library' ),
			'description'         => __( 'Returns the websites, which use a file of a central media library, with the number of contents, in which they use it.', 'external-files-in-media-library' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => $file_properties,
				'required'             => array( 'central', 'attachment_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'array' ),
			'execute_callback'    => function ( array $input ) {
				return $this->get_usage( absint( $input['central'] ?? 0 ), absint( $input['attachment_id'] ?? 0 ) );
			},
			'permission_callback' => function ( array $input ) {
				return $this->can_edit_file( absint( $input['central'] ?? 0 ), absint( $input['attachment_id'] ?? 0 ) );
			},
			'annotations'         => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
		$abilities['network-set-visibility'] = array(
			'label'               => __( 'Change the availability of a file of a central media library', 'external-files-in-media-library' ),
			'description'         => __( 'Changes, which websites of the group could use a file of a central media library. Websites, which could not use it anymore, lose the file, also where they use it.', 'external-files-in-media-library' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array_merge(
					$file_properties,
					array(
						'mode'  => array(
							'type'        => 'string',
							'enum'        => Media_Library::VISIBILITY_MODES,
							'description' => __( '"all" for all websites, "only" for the given websites, "except" for all websites except the given ones.', 'external-files-in-media-library' ),
						),
						'sites' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( 'The IDs of the websites for "only" and "except".', 'external-files-in-media-library' ),
						),
					)
				),
				'required'             => array( 'central', 'attachment_id', 'mode' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'boolean' ),
			'execute_callback'    => function ( array $input ) {
				return $this->set_visibility( absint( $input['central'] ?? 0 ), absint( $input['attachment_id'] ?? 0 ), (string) ( $input['mode'] ?? '' ), array_map( 'absint', (array) ( $input['sites'] ?? array() ) ) );
			},
			'permission_callback' => function ( array $input ) {
				return $this->can_edit_file( absint( $input['central'] ?? 0 ), absint( $input['attachment_id'] ?? 0 ) );
			},
			'annotations'         => array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			),
		);

		// return the resulting list.
		return $abilities;
	}
}

<?php
/**
 * File to handle the central media library of a multisite network.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Multisite;
use WP_Site;
use WP_Term;

/**
 * Object, which handles the central media library of a multisite network.
 *
 * If a website of the network is configured as central media library, all websites use its files:
 * - uploads of the other websites are exported to it (see Services\Multisite\Export),
 * - its files are used as external files in the other websites ("copies"),
 * - changes, deletions and new files are passed to the other websites (see Propagation).
 *
 * The rules of the network (which website may delete which files, which website may use which file) are saved here.
 */
class Media_Library {
	/**
	 * Meta key on a copy in a website: the ID of the attachment in the central media library.
	 */
	public const META_CENTRAL_ID = 'efml_network_central_id';

	/**
	 * Meta key on a copy in a website: the values, which have been changed in this website (caption, …).
	 */
	public const META_OVERRIDES = 'efml_network_overrides';

	/**
	 * Meta key on an attachment in the central media library: the ID of the website, from which it has been uploaded.
	 */
	public const META_ORIGIN_BLOG = 'efml_multisite_origin_blog';

	/**
	 * Meta key on an attachment in the central media library: the ID of the attachment in the website it comes from.
	 */
	public const META_ORIGIN_ATTACHMENT = 'efml_multisite_origin_attachment';

	/**
	 * Meta key on an attachment in the central media library: which websites could use it.
	 */
	public const META_VISIBILITY = 'efml_network_visibility';

	/**
	 * Meta key on an attachment in the central media library: the MD5 hash of its original file.
	 */
	public const META_HASH = 'efml_network_hash';

	/**
	 * Meta key on an attachment in the central media library: the bytes of all its files (original, sizes).
	 */
	public const META_BYTES = 'efml_network_bytes';

	/**
	 * Meta key on an upload in a website, which has been linked to an existing file of the central media library with
	 * the same content instead of being exported.
	 */
	public const META_LINKED = 'efml_network_linked';

	/**
	 * Network option with the rules for the websites.
	 */
	public const OPTION_RULES = 'efml_network_rules';

	/**
	 * Network option with the groups: each with a central media library and the websites, which use it.
	 */
	public const OPTION_GROUPS = 'efml_network_groups';

	/**
	 * Network option with further settings of the central media libraries.
	 */
	public const OPTION_SETTINGS = 'efml_network_settings';

	/**
	 * Default values of the further settings.
	 *
	 * - new_sites: the group of new websites (0: none, -1: the first group).
	 * - deleted_site: what happens with the uploads of a deleted website ("keep" or "delete").
	 * - leave: what happens with the files in a website, which leaves its group ("local": used files and own uploads
	 *   become local files, "remove": they are removed).
	 * - quota: 1 to count the uploads of a website in the central media library for its upload quota.
	 * - origin_edits: 1 to pass changes of caption, description and alternative text in the website, from which a file
	 *   comes, to the central media library.
	 * - dedupe: 1 to use an existing file of the central media library for an upload with the same content.
	 */
	public const SETTINGS_DEFAULTS = array(
		'new_sites'    => -1,
		'deleted_site' => 'keep',
		'leave'        => 'local',
		'quota'        => 1,
		'origin_edits' => 0,
		'dedupe'       => 1,
		'digest'       => 0,
	);

	/**
	 * Possible rights of a website to delete files of the central media library.
	 *
	 * - none: no file could be deleted.
	 * - own: only the files uploaded in this website could be deleted (they are deleted in the whole network).
	 * - all: every file could be deleted (they are deleted in the whole network).
	 */
	public const DELETE_RIGHTS = array( 'none', 'own', 'all' );

	/**
	 * Possible modes of the visibility of a file.
	 *
	 * - all: every website could use the file.
	 * - only: only the listed websites could use the file.
	 * - except: every website except the listed could use the file.
	 */
	public const VISIBILITY_MODES = array( 'all', 'only', 'except' );

	/**
	 * The website and attachment of the actual export into the central media library, set by the export.
	 *
	 * @var array{blog_id: int, attachment_id: int}|array{}
	 */
	private array $pending_origin = array();

	/**
	 * Cache of the websites of each central media library for this request.
	 *
	 * @var array<int,array<int,string>>
	 */
	private array $sites = array();

	/**
	 * Cache of the groups for this request.
	 *
	 * @var array<int,array{name: string, central: int, sites: array<int,int>}>|null
	 */
	private ?array $groups = null;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Media_Library
	 */
	private static ?Media_Library $instance = null;

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
	 * @return Media_Library
	 */
	public static function get_instance(): Media_Library {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the central media library.
	 *
	 * The hooks do not query anything on registration: each callback checks first whether it is needed.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if this is not a multisite.
		if ( ! is_multisite() ) {
			return;
		}

		// save the origin of new files in the central media library.
		add_action( 'add_attachment', array( $this, 'save_origin_of_new_file' ) );

		// save hash and size of the files in the central media library.
		add_filter( 'wp_update_attachment_metadata', array( $this, 'save_file_info' ), 1000, 2 );

		// initialize the parts.
		Membership::get_instance()->init();
		Overview::get_instance()->init();
		Upload::get_instance()->init();
		Quota::get_instance()->init();
		Api::get_instance()->init();
		Existing::get_instance()->init();
		Unused::get_instance()->init();
		Duplicates::get_instance()->init();
		Move::get_instance()->init();
		Addresses::get_instance()->init();
		Digest::get_instance()->init();
		Propagation::get_instance()->init();
		Permissions::get_instance()->init();
		Visibility::get_instance()->init();
		Metadata::get_instance()->init();
		Usage::get_instance()->init();
	}

	/**
	 * Return the groups of the network.
	 *
	 * Each group has a website with the central media library and the websites, which use it. A website belongs to
	 * one group at most. Networks of versions before 6.1.0 with one central media library get one group with all
	 * other websites.
	 *
	 * @return array<int,array{name: string, central: int, sites: array<int,int>}>
	 */
	public function get_groups(): array {
		// use the cache of this request.
		if ( is_array( $this->groups ) ) {
			return $this->groups;
		}

		// bail if this is not a multisite.
		if ( ! is_multisite() ) {
			return array();
		}

		// get the saved groups.
		$groups = get_site_option( self::OPTION_GROUPS, false );

		// use the setting of older versions: one central media library for all websites.
		if ( ! is_array( $groups ) ) {
			$groups  = array();
			$central = absint( get_site_option( 'efml_media_library_site_id', 0 ) );
			if ( $central > 0 ) {
				$sites = array();
				foreach ( Helper::get_blogs( $central ) as $website ) {
					$sites[] = absint( $website->blog_id ); // @phpstan-ignore property.notFound
				}
				$groups[1] = array(
					'name'    => __( 'Network', 'external-files-in-media-library' ),
					'central' => $central,
					'sites'   => $sites,
				);
			}
		}

		// collect the valid groups.
		$this->groups = array();
		foreach ( $groups as $group_id => $group ) {
			// bail if the group is not usable.
			if ( ! is_array( $group ) || empty( $group['central'] ) ) {
				continue;
			}

			// add it.
			$central                             = absint( $group['central'] );
			$this->groups[ absint( $group_id ) ] = array(
				'name'    => isset( $group['name'] ) ? (string) $group['name'] : '',
				'central' => $central,
				'sites'   => isset( $group['sites'] ) && is_array( $group['sites'] ) ? array_values( array_diff( array_unique( array_map( 'absint', $group['sites'] ) ), array( 0, $central ) ) ) : array(),
			);
		}

		// return the groups.
		return $this->groups;
	}

	/**
	 * Save the groups of the network.
	 *
	 * Only saves them. Settings::apply_groups() connects the websites with their central media library.
	 *
	 * @param array<int,array{name: string, central: int, sites: array<int,int>}> $groups The groups.
	 *
	 * @return void
	 */
	public function set_groups( array $groups ): void {
		// save them.
		update_site_option( self::OPTION_GROUPS, $groups );

		// keep the setting of older versions for code, which still reads it: the central media library of the first group.
		$first = reset( $groups );
		if ( is_array( $first ) && ! empty( $first['central'] ) ) {
			update_site_option( 'efml_media_library_site_id', absint( $first['central'] ) );
		} else {
			delete_site_option( 'efml_media_library_site_id' );
		}

		// forget the caches.
		$this->reset_sites();
	}

	/**
	 * Return the ID of the group of the given website (as central media library or as user of it), 0 if none.
	 *
	 * @param int $blog_id The website (0 for the actual website).
	 *
	 * @return int
	 */
	public function get_group_id( int $blog_id = 0 ): int {
		// get the website to check.
		if ( 0 === $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// search the group.
		foreach ( $this->get_groups() as $group_id => $group ) {
			if ( $group['central'] === $blog_id || in_array( $blog_id, $group['sites'], true ) ) {
				return $group_id;
			}
		}

		// return 0 as the website is in no group.
		return 0;
	}

	/**
	 * Return the ID of the website with the central media library for the given website.
	 *
	 * For a central media library this is the website itself. Returns 0 if the website is in no group.
	 *
	 * @param int $blog_id The website (0 for the actual website).
	 *
	 * @return int
	 */
	public function get_central_for( int $blog_id = 0 ): int {
		// get the group.
		$group_id = $this->get_group_id( $blog_id );

		// return its central media library.
		return $group_id > 0 ? $this->get_groups()[ $group_id ]['central'] : 0;
	}

	/**
	 * Return the ID of the website with the central media library of the actual website (0 if none is used).
	 *
	 * @return int
	 */
	public function get_central_site_id(): int {
		return $this->get_central_for();
	}

	/**
	 * Return whether a central media library is used in this network.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return is_multisite() && ! empty( $this->get_groups() );
	}

	/**
	 * Return whether the actual website (or the given one) is a central media library.
	 *
	 * @param int $blog_id The website to check (0 for the actual website).
	 *
	 * @return bool
	 */
	public function is_central( int $blog_id = 0 ): bool {
		// get the website to check.
		if ( 0 === $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// return whether it is the central media library of a group.
		return $this->get_central_for( $blog_id ) === $blog_id;
	}

	/**
	 * Return whether the actual website (or the given one) uses the central media library of another website.
	 *
	 * @param int $blog_id The website to check (0 for the actual website).
	 *
	 * @return bool
	 */
	public function is_using_central( int $blog_id = 0 ): bool {
		// get the website to check.
		if ( 0 === $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// get its central media library.
		$central = $this->get_central_for( $blog_id );

		// return whether it uses one.
		return $central > 0 && $central !== $blog_id;
	}

	/**
	 * Return the websites, which use the given central media library, as list of ID and URL.
	 *
	 * @param int $central The website with the central media library (0 for the one of the actual website).
	 *
	 * @return array<int,string>
	 */
	public function get_sites( int $central = 0 ): array {
		// get the central media library.
		if ( 0 === $central ) {
			$central = $this->get_central_site_id();
		}

		// use the cache of this request.
		if ( isset( $this->sites[ $central ] ) ) {
			return $this->sites[ $central ];
		}

		// get the websites of its group.
		$list = array();
		foreach ( $this->get_groups() as $group ) {
			if ( $group['central'] !== $central ) {
				continue;
			}
			foreach ( $group['sites'] as $blog_id ) {
				// bail if the website does not exist (anymore).
				$site = get_site( $blog_id );
				if ( ! $site instanceof WP_Site || '1' === $site->deleted || '1' === $site->archived || '1' === $site->spam ) {
					continue;
				}
				$list[ $blog_id ] = (string) get_blogaddress_by_id( $blog_id );
			}
		}
		ksort( $list );

		// save it in the cache and return it.
		$this->sites[ $central ] = $list;
		return $list;
	}

	/**
	 * Forget the cached groups and websites (e.g. after a new website has been added).
	 *
	 * @return void
	 */
	public function reset_sites(): void {
		$this->sites  = array();
		$this->groups = null;
	}

	/**
	 * Return the value of a further setting of the central media libraries.
	 *
	 * @param string $name The name of the setting (a key of SETTINGS_DEFAULTS).
	 *
	 * @return int|string
	 */
	public function get_setting( string $name ): int|string {
		// get the saved settings.
		$settings = get_site_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// get the value or its default.
		$value = $settings[ $name ] ?? ( self::SETTINGS_DEFAULTS[ $name ] ?? '' );

		// return the value in the type of its default.
		return is_int( self::SETTINGS_DEFAULTS[ $name ] ?? '' ) ? (int) $value : (string) $value;
	}

	/**
	 * Save the further settings of the central media libraries.
	 *
	 * @param array<string,int|string> $settings The settings (unknown keys are ignored).
	 *
	 * @return void
	 */
	public function set_settings( array $settings ): void {
		// collect the known settings in the type of their default.
		$values = array();
		foreach ( self::SETTINGS_DEFAULTS as $name => $default ) {
			$value           = $settings[ $name ] ?? $this->get_setting( $name );
			$values[ $name ] = is_int( $default ) ? (int) $value : (string) $value;
		}

		// check the values with fixed choices.
		if ( ! in_array( $values['deleted_site'], array( 'keep', 'delete' ), true ) ) {
			$values['deleted_site'] = 'keep';
		}
		if ( ! in_array( $values['leave'], array( 'local', 'remove' ), true ) ) {
			$values['leave'] = 'local';
		}

		// save them.
		update_site_option( self::OPTION_SETTINGS, $values );

		// schedule or remove the summary via e-mail.
		Digest::get_instance()->schedule();
	}

	/**
	 * Return the rules of the network.
	 *
	 * @return array{delete: string, visibility: string, sites: array<int,array{delete?: string}>}
	 */
	public function get_rules(): array {
		// get the saved rules.
		$rules = get_site_option( self::OPTION_RULES, array() );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		// get the default right to delete files (like before: each website could delete its own uploads).
		$delete = isset( $rules['delete'] ) && in_array( $rules['delete'], self::DELETE_RIGHTS, true ) ? $rules['delete'] : 'own';

		// get the default visibility of new files (like before: every website could use them).
		$visibility = isset( $rules['visibility'] ) && in_array( $rules['visibility'], array( 'all', 'own' ), true ) ? $rules['visibility'] : 'all';

		// get the rules of single websites.
		$sites = array();
		if ( isset( $rules['sites'] ) && is_array( $rules['sites'] ) ) {
			foreach ( $rules['sites'] as $blog_id => $site_rules ) {
				// bail if the rules are not usable.
				if ( ! is_array( $site_rules ) ) {
					continue;
				}

				// use only known rights.
				if ( isset( $site_rules['delete'] ) && in_array( $site_rules['delete'], self::DELETE_RIGHTS, true ) ) {
					$sites[ absint( $blog_id ) ] = array( 'delete' => (string) $site_rules['delete'] );
				}
			}
		}

		// return the rules.
		return array(
			'delete'     => (string) $delete,
			'visibility' => (string) $visibility,
			'sites'      => $sites,
		);
	}

	/**
	 * Save the rules of the network.
	 *
	 * @param string                          $delete     The default right to delete files.
	 * @param string                          $visibility The default visibility of new files ("all" or "own").
	 * @param array<int,array<string,string>> $sites      The rules of single websites.
	 *
	 * @return void
	 */
	public function set_rules( string $delete, string $visibility, array $sites ): void {
		// collect the rules of the websites, which differ from the default.
		$site_rules = array();
		foreach ( $sites as $blog_id => $rules ) {
			if ( isset( $rules['delete'] ) && in_array( $rules['delete'], self::DELETE_RIGHTS, true ) ) {
				$site_rules[ absint( $blog_id ) ] = array( 'delete' => $rules['delete'] );
			}
		}

		// save them.
		update_site_option(
			self::OPTION_RULES,
			array(
				'delete'     => in_array( $delete, self::DELETE_RIGHTS, true ) ? $delete : 'own',
				'visibility' => in_array( $visibility, array( 'all', 'own' ), true ) ? $visibility : 'all',
				'sites'      => $site_rules,
			)
		);
	}

	/**
	 * Return the right of the given website to delete files of the central media library.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return string One of DELETE_RIGHTS.
	 */
	public function get_delete_right( int $blog_id ): string {
		// get the rules.
		$rules = $this->get_rules();

		// get the right of this website.
		$right = $rules['sites'][ $blog_id ]['delete'] ?? $rules['delete'];

		/**
		 * Filter the right of a website to delete files of the central media library.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param string $right The right: "none", "own" or "all".
		 * @param int $blog_id The ID of the website.
		 */
		$right = (string) apply_filters( 'efml_network_delete_right', $right, $blog_id );

		// return a known right.
		return in_array( $right, self::DELETE_RIGHTS, true ) ? $right : 'none';
	}

	/**
	 * Return the visibility of the given file of the central media library.
	 *
	 * Must be called in the central media library.
	 *
	 * @param int $attachment_id The attachment ID in the central media library.
	 *
	 * @return array{mode: string, sites: array<int,int>}
	 */
	public function get_visibility( int $attachment_id ): array {
		// get the saved visibility.
		$visibility = get_post_meta( $attachment_id, self::META_VISIBILITY, true );

		// files without visibility (e.g. from older versions) could be used by all websites.
		if ( ! is_array( $visibility ) || ! isset( $visibility['mode'] ) || ! in_array( $visibility['mode'], self::VISIBILITY_MODES, true ) ) {
			return array(
				'mode'  => 'all',
				'sites' => array(),
			);
		}

		// return the visibility.
		return array(
			'mode'  => (string) $visibility['mode'],
			'sites' => isset( $visibility['sites'] ) && is_array( $visibility['sites'] ) ? array_values( array_unique( array_map( 'absint', $visibility['sites'] ) ) ) : array(),
		);
	}

	/**
	 * Save the visibility of the given file of the central media library.
	 *
	 * Must be called in the central media library. The websites get the change via Propagation.
	 *
	 * @param int            $attachment_id The attachment ID in the central media library.
	 * @param string         $mode          The mode, one of VISIBILITY_MODES.
	 * @param array<int,int> $sites         The websites for the mode "only" or "except".
	 *
	 * @return void
	 */
	public function set_visibility( int $attachment_id, string $mode, array $sites ): void {
		// bail if mode is unknown.
		if ( ! in_array( $mode, self::VISIBILITY_MODES, true ) ) {
			return;
		}

		// remove the setting, if every website could use the file.
		if ( 'all' === $mode ) {
			delete_post_meta( $attachment_id, self::META_VISIBILITY );
		} else {
			update_post_meta(
				$attachment_id,
				self::META_VISIBILITY,
				array(
					'mode'  => $mode,
					'sites' => array_values( array_unique( array_filter( array_map( 'absint', $sites ) ) ) ),
				)
			);
		}

		// pass the change to the websites.
		Propagation::get_instance()->add_update( $attachment_id );
	}

	/**
	 * Return whether the given website could use the given file of the central media library.
	 *
	 * Must be called in the central media library.
	 *
	 * @param int $attachment_id The attachment ID in the central media library.
	 * @param int $blog_id       The website.
	 *
	 * @return bool
	 */
	public function is_visible_for( int $attachment_id, int $blog_id ): bool {
		// the website, from which the file comes, could always use it.
		if ( $this->get_origin_blog( $attachment_id ) === $blog_id ) {
			return true;
		}

		// get the visibility.
		$visibility = $this->get_visibility( $attachment_id );

		// check it.
		$visible = match ( $visibility['mode'] ) {
			'only'   => in_array( $blog_id, $visibility['sites'], true ),
			'except' => ! in_array( $blog_id, $visibility['sites'], true ),
			default  => true,
		};

		/**
		 * Filter whether a website could use a file of the central media library.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param bool $visible True if the website could use the file.
		 * @param int $attachment_id The attachment ID in the central media library.
		 * @param int $blog_id The ID of the website.
		 */
		return (bool) apply_filters( 'efml_network_file_visible', $visible, $attachment_id, $blog_id );
	}

	/**
	 * Return the website, from which the given file of the central media library has been uploaded (0 if it has been
	 * uploaded in the central media library itself or if it is unknown).
	 *
	 * Must be called in the central media library.
	 *
	 * @param int $attachment_id The attachment ID in the central media library.
	 *
	 * @return int
	 */
	public function get_origin_blog( int $attachment_id ): int {
		return absint( get_post_meta( $attachment_id, self::META_ORIGIN_BLOG, true ) );
	}

	/**
	 * Mark the next new attachment in the central media library as export of the given attachment of the given website.
	 *
	 * Used by the export of the multisite service before it creates the attachment in the central media library.
	 *
	 * @param int $blog_id       The website, from which the file is exported.
	 * @param int $attachment_id The attachment in this website.
	 *
	 * @return void
	 */
	public function set_pending_origin( int $blog_id, int $attachment_id ): void {
		$this->pending_origin = array(
			'blog_id'       => $blog_id,
			'attachment_id' => $attachment_id,
		);
	}

	/**
	 * Forget the pending origin (after the export).
	 *
	 * @return void
	 */
	public function reset_pending_origin(): void {
		$this->pending_origin = array();
	}

	/**
	 * Save the origin and the visibility of a new file in the central media library.
	 *
	 * @param int $attachment_id The ID of the new attachment.
	 *
	 * @return void
	 */
	public function save_origin_of_new_file( int $attachment_id ): void {
		// bail if this is not the central media library.
		if ( ! $this->is_central() ) {
			return;
		}

		// get the origin.
		$origin_blog_id = 0;
		$choice         = '';
		if ( ! empty( $this->pending_origin ) ) {
			$origin_blog_id = $this->pending_origin['blog_id'];

			// get the availability chosen for the upload in its website.
			switch_to_blog( $origin_blog_id );
			$choice = Upload::get_instance()->get_choice_of_upload( $this->pending_origin['attachment_id'] );
			restore_current_blog();

			// save the origin.
			update_post_meta( $attachment_id, self::META_ORIGIN_BLOG, $origin_blog_id );
			update_post_meta( $attachment_id, self::META_ORIGIN_ATTACHMENT, $this->pending_origin['attachment_id'] );

			// use it only once.
			$this->reset_pending_origin();
		}

		// set the visibility for new files: only for the website, from which it comes, if chosen or configured.
		$rules = $this->get_rules();
		if ( 'own' === $choice || ( '' === $choice && 'own' === $rules['visibility'] ) ) {
			update_post_meta(
				$attachment_id,
				self::META_VISIBILITY,
				array(
					'mode'  => 'only',
					'sites' => $origin_blog_id > 0 ? array( $origin_blog_id ) : array(),
				)
			);
		}
	}

	/**
	 * Return the ID of the external source of the central media library in the actual website (0 if it does not exist).
	 *
	 * @return int
	 */
	public function get_central_term_id(): int {
		// bail if this website does not use a central media library.
		if ( ! $this->is_using_central() ) {
			return 0;
		}

		// get the term like Taxonomy::add() creates it.
		$term = get_term_by( 'slug', md5( 'multisite' . get_blogaddress_by_id( $this->get_central_site_id() ) ), Taxonomy::get_instance()->get_name() );

		// bail if term does not exist.
		if ( ! $term instanceof WP_Term ) {
			return 0;
		}

		// return its ID.
		return $term->term_id;
	}

	/**
	 * Return the URL of the given file of the central media library, as it is used by the websites.
	 *
	 * Must be called in the central media library.
	 *
	 * @param int $attachment_id The attachment ID in the central media library.
	 *
	 * @return string
	 */
	public function get_file_url( int $attachment_id ): string {
		return Multisite::get_instance()->get_file_url( get_current_blog_id(), $attachment_id );
	}

	/**
	 * Return the copies of the given file of the central media library in the actual website.
	 *
	 * @param int           $central_id The attachment ID in the central media library.
	 * @param array<string> $urls       The URLs of the file (for copies of older versions without the ID).
	 * @param int           $origin     The website, from which the file comes.
	 *
	 * @return array<int,int>
	 */
	public function find_copies( int $central_id, array $urls = array(), int $origin = 0 ): array {
		global $wpdb;

		// search the attachments with the ID of the central file.
		// the export saves the ID with its own key, only in the website, from which the file comes.
		if ( get_current_blog_id() === $origin ) {
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT m.`post_id` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` IN (%s, %s) AND m.`meta_value` = %s', array( $wpdb->postmeta, $wpdb->posts, 'attachment', self::META_CENTRAL_ID, 'efml_multisite_attachment_id', (string) $central_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT m.`post_id` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` = %s AND m.`meta_value` = %s', array( $wpdb->postmeta, $wpdb->posts, 'attachment', self::META_CENTRAL_ID, (string) $central_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// collect the IDs.
		$copies = array_map( 'absint', $ids );

		// search copies of older versions via their URL.
		foreach ( array_unique( array_filter( $urls ) ) as $url ) {
			$external_file_obj = Files::get_instance()->get_file_by_url( $url );
			if ( $external_file_obj && $external_file_obj->is_valid() ) {
				$copies[] = $external_file_obj->get_id();
			}
		}

		// return the list.
		return array_values( array_unique( $copies ) );
	}

	/**
	 * Return the ID of the file in the central media library for the given attachment of the actual website.
	 *
	 * Returns 0 if the attachment is not a file of the central media library.
	 *
	 * @param int $attachment_id The attachment ID in the actual website.
	 *
	 * @return int
	 */
	public function get_central_id( int $attachment_id ): int {
		// bail if this website does not use a central media library.
		if ( ! $this->is_using_central() ) {
			return 0;
		}

		// use the saved ID.
		$central_id = absint( get_post_meta( $attachment_id, self::META_CENTRAL_ID, true ) );
		if ( $central_id > 0 ) {
			return $central_id;
		}

		// use the ID of the export of an own upload.
		$central_id = absint( get_post_meta( $attachment_id, 'efml_multisite_attachment_id', true ) );
		if ( $central_id > 0 && $this->is_own_upload( $attachment_id ) ) {
			return $central_id;
		}

		// get the external file.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file of the multisite service.
		if ( ! $external_file_obj->is_valid() || 'multisite' !== $external_file_obj->get_service_name() ) {
			return 0;
		}

		// search for the file in the central media library via its URL (copies of older versions without the ID).
		$central_id = $this->get_central_id_by_url( $external_file_obj->get_url( true ) );

		// save it for the next time.
		if ( $central_id > 0 ) {
			update_post_meta( $attachment_id, self::META_CENTRAL_ID, $central_id );
		}

		// return the ID.
		return $central_id;
	}

	/**
	 * Return the ID of the attachment in the central media library for the given URL (0 if not found).
	 *
	 * @param string $url The URL.
	 *
	 * @return int
	 */
	public function get_central_id_by_url( string $url ): int {
		global $wpdb;

		// get the address of the central media library.
		$central_url = (string) get_blogaddress_by_id( $this->get_central_site_id() );

		// bail if the URL is not from the central media library.
		if ( empty( $central_url ) || ! str_starts_with( $url, $central_url ) ) {
			return 0;
		}

		// switch to the central media library.
		switch_to_blog( $this->get_central_site_id() );

		// get the path relative to the uploads directory, as it is saved in "_wp_attached_file".
		$upload_dir = wp_get_upload_dir();
		$path       = str_replace( trailingslashit( (string) $upload_dir['basedir'] ), '', str_replace( $central_url, ABSPATH, $url ) );

		// search for it.
		$central_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` = %s LIMIT 1', array( $wpdb->postmeta, '_wp_attached_file', $path ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// switch back.
		restore_current_blog();

		// return the result.
		return $central_id;
	}

	/**
	 * Return whether the given upload of the actual website has been linked to an existing file of the central media
	 * library with the same content (instead of being exported).
	 *
	 * @param int $attachment_id The attachment ID in the actual website.
	 *
	 * @return bool
	 */
	public function is_linked( int $attachment_id ): bool {
		return 1 === absint( get_post_meta( $attachment_id, self::META_LINKED, true ) );
	}

	/**
	 * Save the hash of the original file and the bytes of all files of an attachment in the central media library.
	 *
	 * The hash is used to find uploads with the same content, the bytes for the upload space of the websites.
	 *
	 * @param array<string,mixed>|mixed $data          The metadata.
	 * @param int                       $attachment_id The attachment ID.
	 *
	 * @return array<string,mixed>|mixed
	 */
	public function save_file_info( mixed $data, int $attachment_id ): mixed {
		// save it, if this is a central media library or a local file of a website, which uses one (to link it with
		// a file of the same content, see Linking).
		if ( is_array( $data ) && ( $this->is_central() || ( $this->is_using_central() && ! Files::get_instance()->get_file( $attachment_id )->is_valid() ) ) ) {
			$this->update_file_info( $attachment_id, $data );
		}

		// return the metadata unchanged.
		return $data;
	}

	/**
	 * Save the hash of the original file and the bytes of all files of an attachment of the actual website.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param array<string,mixed> $metadata      The metadata (loaded if empty).
	 *
	 * @return bool False if the file does not exist.
	 */
	public function update_file_info( int $attachment_id, array $metadata = array() ): bool {
		// get the metadata.
		if ( empty( $metadata ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $metadata ) ) {
				$metadata = array();
			}
		}

		// get the files: the attached file, its original and its sizes.
		$file = (string) get_attached_file( $attachment_id, true );
		if ( ! is_file( $file ) ) {
			return false;
		}
		$directory = trailingslashit( dirname( $file ) );
		$files     = array( $file );
		if ( ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
			$files[] = $directory . $metadata['original_image'];
		}
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) && is_string( $size['file'] ) ) {
					$files[] = $directory . $size['file'];
				}
			}
		}

		// sum their bytes.
		$bytes = 0;
		foreach ( array_unique( $files ) as $path ) {
			if ( is_file( $path ) ) {
				$bytes += absint( filesize( $path ) );
			}
		}

		// get the hash of the original file (as it has been uploaded).
		$original = ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) && is_file( $directory . $metadata['original_image'] ) ? $directory . $metadata['original_image'] : $file;
		$hash     = (string) md5_file( $original );

		// save them.
		update_post_meta( $attachment_id, self::META_HASH, $hash );
		update_post_meta( $attachment_id, self::META_BYTES, $bytes );

		// forget the cached upload space of the website, from which the file comes.
		Quota::get_instance()->reset( get_current_blog_id(), $this->get_origin_blog( $attachment_id ) );

		// return success.
		return true;
	}

	/**
	 * Return an existing file of the actual central media library with the same content as the given file, which the
	 * given website could use (0 if none exists or if this is disabled).
	 *
	 * Must be called in the central media library. The website could use the file afterward.
	 *
	 * @param string $file    The path of the file.
	 * @param int    $blog_id The website, which uploads the file.
	 *
	 * @return int
	 */
	public function find_duplicate( string $file, int $blog_id ): int {
		global $wpdb;

		// bail if this is disabled or if this is not a central media library.
		if ( 1 !== (int) $this->get_setting( 'dedupe' ) || ! $this->is_central() || ! is_file( $file ) ) {
			return 0;
		}

		// search a file with the same hash.
		$attachment_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT m.`post_id` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` = %s AND m.`meta_value` = %s ORDER BY m.`post_id` ASC LIMIT 1', array( $wpdb->postmeta, $wpdb->posts, 'attachment', self::META_HASH, (string) md5_file( $file ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// bail if none has been found.
		if ( 0 === $attachment_id ) {
			return 0;
		}

		// the website could use the file now.
		$this->allow_site( $attachment_id, $blog_id );

		// return the file.
		return $attachment_id;
	}

	/**
	 * Allow the given website to use the given file of the actual central media library.
	 *
	 * @param int $attachment_id The attachment ID in the central media library.
	 * @param int $blog_id       The website.
	 *
	 * @return void
	 */
	public function allow_site( int $attachment_id, int $blog_id ): void {
		// bail if the website could use it already.
		if ( $this->is_visible_for( $attachment_id, $blog_id ) ) {
			return;
		}

		// add it to the websites.
		$visibility = $this->get_visibility( $attachment_id );
		if ( 'only' === $visibility['mode'] ) {
			$visibility['sites'][] = $blog_id;
		} else {
			$visibility['sites'] = array_values( array_diff( $visibility['sites'], array( $blog_id ) ) );
		}
		$this->set_visibility( $attachment_id, $visibility['mode'], $visibility['sites'] );
	}

	/**
	 * Use another file of a central media library for the given copy in the actual website.
	 *
	 * The attachment in the website stays, so its usage in contents is kept. Used if duplicates are merged and if a
	 * central media library moves to another website.
	 *
	 * @param int    $copy_id        The attachment ID in the actual website.
	 * @param int    $old_central_id The former ID in the central media library.
	 * @param int    $new_central_id The new ID in the central media library.
	 * @param string $new_url        The URL of the new file.
	 *
	 * @return void
	 */
	public function repoint_copy( int $copy_id, int $old_central_id, int $new_central_id, string $new_url ): void {
		// get the external file.
		$external_file_obj = Files::get_instance()->get_file( $copy_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// use the new URL and load the file again.
		if ( $external_file_obj->get_url( true ) !== $new_url ) {
			$external_file_obj->delete_cache();
			$external_file_obj->delete_thumbs();
			$external_file_obj->set_url( $new_url );
		}

		// save the new ID.
		update_post_meta( $copy_id, self::META_CENTRAL_ID, $new_central_id );
		if ( absint( get_post_meta( $copy_id, 'efml_multisite_attachment_id', true ) ) === $old_central_id ) {
			update_post_meta( $copy_id, 'efml_multisite_attachment_id', $new_central_id );
		}

		// forget the marker of the synchronization, so it checks the file again.
		delete_post_meta( $copy_id, \ExternalFilesInMediaLibrary\Services\Multisite::META_STAMP );
	}

	/**
	 * Return whether the given attachment of the actual website is an own upload, which has been exported to the
	 * central media library.
	 *
	 * @param int $attachment_id The attachment ID in the actual website.
	 *
	 * @return bool
	 */
	public function is_own_upload( int $attachment_id ): bool {
		// bail if this website does not use a central media library.
		if ( ! $this->is_using_central() ) {
			return false;
		}

		// bail if the file is not exported to another website.
		if ( 0 === absint( get_post_meta( $attachment_id, 'efml_multisite_attachment_id', true ) ) ) {
			return false;
		}

		// bail if the upload has been linked to an existing file of the central media library.
		if ( $this->is_linked( $attachment_id ) ) {
			return false;
		}

		// return whether it has been exported to the central media library.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		return $external_file_obj->is_valid() && str_starts_with( $external_file_obj->get_url( true ), (string) get_blogaddress_by_id( $this->get_central_site_id() ) );
	}

	/**
	 * Save the origin of the files of the central media library, which have been uploaded in other websites before
	 * the origin has been saved (versions before 6.1.0).
	 *
	 * Runs in parts, as the network could contain many websites: the actual website is saved and the next run starts
	 * with the next one. Returns true if all websites have been checked.
	 *
	 * @param int $time_limit The time in seconds this run could use.
	 *
	 * @return bool
	 */
	public function migrate_origins( int $time_limit = 20 ): bool {
		global $wpdb;

		// bail if no central media library is used.
		if ( ! $this->is_active() ) {
			return true;
		}

		// get the start time.
		$start = time();

		// get the last checked website.
		$last_blog_id = absint( get_site_option( 'efml_network_origins_migration', 0 ) );

		// get all websites, which use a central media library, with their central media library.
		$websites = array();
		foreach ( $this->get_groups() as $group ) {
			foreach ( $group['sites'] as $site_id ) {
				$websites[ $site_id ] = $group['central'];
			}
		}
		ksort( $websites );

		// check each website after the last checked one.
		foreach ( $websites as $blog_id => $central ) {
			// bail if this website has already been checked.
			if ( $blog_id <= $last_blog_id ) {
				continue;
			}

			// stop if the time is over.
			if ( time() - $start >= $time_limit ) {
				return false;
			}

			// get the exported files of this website.
			switch_to_blog( $blog_id );
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT `post_id`, `meta_value` FROM %i WHERE `meta_key` = %s', array( $wpdb->postmeta, 'efml_multisite_attachment_id' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$own  = array();
			foreach ( (array) $rows as $row ) {
				// use only exports to the central media library.
				if ( $this->is_own_upload( absint( $row['post_id'] ) ) ) {
					$own[ absint( $row['meta_value'] ) ] = absint( $row['post_id'] );
				}
			}
			restore_current_blog();

			// save the origin on the files in the central media library, if not already set.
			if ( ! empty( $own ) ) {
				switch_to_blog( $central );
				foreach ( $own as $central_id => $attachment_id ) {
					if ( 'attachment' === get_post_type( $central_id ) && 0 === $this->get_origin_blog( $central_id ) ) {
						update_post_meta( $central_id, self::META_ORIGIN_BLOG, $blog_id );
						update_post_meta( $central_id, self::META_ORIGIN_ATTACHMENT, $attachment_id );
					}
				}
				restore_current_blog();
			}

			// save the progress.
			update_site_option( 'efml_network_origins_migration', $blog_id );
		}

		// all websites have been checked.
		update_site_option( 'efml_network_origins_migrated', 1 );
		delete_site_option( 'efml_network_origins_migration' );
		return true;
	}

	/**
	 * Save hash and bytes on files of the central media libraries, which do not have them (e.g. uploaded before
	 * version 6.1.0). Returns false, if the time is over before all files have been checked.
	 *
	 * @param int $time_limit The time in seconds, which could be used.
	 *
	 * @return bool
	 */
	public function migrate_file_info( int $time_limit = 20 ): bool {
		global $wpdb;

		// bail if no central media library is used.
		if ( ! $this->is_active() ) {
			return true;
		}

		// get the start time.
		$start = time();

		// check the files of each central media library.
		foreach ( $this->get_groups() as $group ) {
			switch_to_blog( $group['central'] );
			do {
				// stop if the time is over.
				if ( time() - $start >= $time_limit ) {
					restore_current_blog();
					return false;
				}

				// get the next files without hash.
				$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT p.`ID` FROM %i AS p LEFT JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND m.`meta_id` IS NULL ORDER BY p.`ID` ASC LIMIT 50', array( $wpdb->posts, $wpdb->postmeta, self::META_HASH, 'attachment' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				// save the info: a file, which does not exist, gets an empty hash, so it is not checked again.
				foreach ( $ids as $attachment_id ) {
					$this->update_file_info( absint( $attachment_id ) );
					if ( ! metadata_exists( 'post', absint( $attachment_id ), self::META_HASH ) ) {
						update_post_meta( absint( $attachment_id ), self::META_HASH, '' );
					}
				}
			} while ( ! empty( $ids ) );
			restore_current_blog();
		}

		// all files have been checked.
		update_site_option( 'efml_network_file_info_migrated', 1 );
		return true;
	}

	/**
	 * Return the name of the given website for lists.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return string
	 */
	public function get_site_label( int $blog_id ): string {
		// get the website.
		$site = get_site( $blog_id );

		// bail if it does not exist.
		if ( ! $site instanceof WP_Site ) {
			/* translators: %1$d will be replaced by the ID of a website. */
			return sprintf( __( 'Website %1$d', 'external-files-in-media-library' ), $blog_id );
		}

		// return name and URL.
		$name = (string) $site->blogname;
		$url  = untrailingslashit( $site->domain . $site->path );
		return empty( $name ) ? $url : $name . ' (' . $url . ')';
	}
}

<?php
/**
 * This file contains the handling of uploads in websites, which use a central media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Query;

/**
 * Object to choose the availability of new uploads and to filter the media library for own uploads and files of the
 * central media library.
 */
class Upload {
	/**
	 * Meta key on the user and on a new upload: the chosen availability of new uploads in the network.
	 */
	public const META = 'efml_network_upload_visibility';

	/**
	 * Name of the site option with the roles, which could choose the availability of their uploads (besides super
	 * administrators and administrators).
	 */
	public const OPTION_ROLES = 'efml_network_upload_roles';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Upload
	 */
	private static ?Upload $instance = null;

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
	 * @return Upload
	 */
	public static function get_instance(): Upload {
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
		// choose the availability of new uploads.
		add_action( 'post-upload-ui', array( $this, 'show_choice' ) );
		add_action( 'wp_ajax_efml_network_upload_visibility', array( $this, 'save_choice_via_ajax' ) );
		add_action( 'add_attachment', array( $this, 'save_choice_on_upload' ), 5 );

		// filter the media library.
		add_filter( 'efml_filter_options', array( $this, 'add_filter_options' ) );
		add_action( 'efml_filter_query', array( $this, 'filter_query' ) );
	}

	/**
	 * Return the saved roles: the default for all websites and the own choice of single websites.
	 *
	 * @return array{default: array<int,string>, sites: array<int,array<int,string>>}
	 */
	private function get_saved_roles(): array {
		$saved = get_site_option( self::OPTION_ROLES, array() );
		$roles = array(
			'default' => array(),
			'sites'   => array(),
		);
		if ( ! is_array( $saved ) ) {
			return $roles;
		}
		$roles['default'] = isset( $saved['default'] ) && is_array( $saved['default'] ) ? array_values( array_map( 'strval', $saved['default'] ) ) : array();
		if ( isset( $saved['sites'] ) && is_array( $saved['sites'] ) ) {
			foreach ( $saved['sites'] as $blog_id => $site_roles ) {
				if ( is_array( $site_roles ) ) {
					$roles['sites'][ absint( $blog_id ) ] = array_values( array_map( 'strval', $site_roles ) );
				}
			}
		}
		return $roles;
	}

	/**
	 * Return the roles, which could choose the availability of files by default (besides super administrators and
	 * administrators).
	 *
	 * @return array<int,string>
	 */
	public function get_default_roles(): array {
		return $this->get_saved_roles()['default'];
	}

	/**
	 * Return the own choice of roles of the given website (null if it uses the default).
	 *
	 * @param int $blog_id The website.
	 *
	 * @return array<int,string>|null
	 */
	public function get_site_roles( int $blog_id ): ?array {
		return $this->get_saved_roles()['sites'][ $blog_id ] ?? null;
	}

	/**
	 * Return the roles, which could choose the availability of files in the given website (besides super
	 * administrators and administrators).
	 *
	 * @param int $blog_id The website (0 for the actual one).
	 *
	 * @return array<int,string>
	 */
	public function get_roles( int $blog_id = 0 ): array {
		$site_roles = $this->get_site_roles( 0 === $blog_id ? get_current_blog_id() : $blog_id );
		return is_null( $site_roles ) ? $this->get_default_roles() : $site_roles;
	}

	/**
	 * Save the roles, which could choose the availability of files.
	 *
	 * @param array<int,string>                 $default_roles The roles for all websites.
	 * @param array<int,array<int,string>|null> $sites         The own choice of single websites (null to use the
	 *                                                         default). Websites, which are not given, keep theirs.
	 *
	 * @return void
	 */
	public function set_roles( array $default_roles, array $sites = array() ): void {
		$roles            = $this->get_saved_roles();
		$roles['default'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $default_roles ) ) ) );
		foreach ( $sites as $blog_id => $site_roles ) {
			if ( is_null( $site_roles ) ) {
				unset( $roles['sites'][ absint( $blog_id ) ] );
				continue;
			}
			$roles['sites'][ absint( $blog_id ) ] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $site_roles ) ) ) );
		}
		update_site_option( self::OPTION_ROLES, $roles );
	}

	/**
	 * Return whether the actual user could choose the availability of files in the actual website: of the uploads in
	 * the websites and of the files in the central media library.
	 *
	 * Super administrators, administrators and the roles chosen in the settings of the network could choose it.
	 *
	 * @return bool
	 */
	public function may_choose(): bool {
		// super administrators could choose it.
		if ( is_super_admin() ) {
			return true;
		}

		// check the roles of the user in the actual website.
		$user = wp_get_current_user();
		return ! empty( array_intersect( (array) $user->roles, array_merge( array( 'administrator' ), $this->get_roles( get_current_blog_id() ) ) ) );
	}

	/**
	 * Return the availability of new uploads of the actual user ("all" or "own").
	 *
	 * Users, who could not choose it, get the setting of the network.
	 *
	 * @return string
	 */
	public function get_choice(): string {
		// get the choice of the user, if the user could choose it.
		$choice = $this->may_choose() ? get_user_meta( get_current_user_id(), self::META, true ) : '';

		// use the setting of the network, if the user has not chosen.
		if ( ! in_array( $choice, array( 'all', 'own' ), true ) ) {
			$rules  = Media_Library::get_instance()->get_rules();
			$choice = $rules['visibility'];
		}

		// return the choice.
		return (string) $choice;
	}

	/**
	 * Show the choice of the availability for new uploads below the upload field.
	 *
	 * @return void
	 */
	public function show_choice(): void {
		// bail if this website does not use a central media library or the user could not choose it.
		if ( ! Media_Library::get_instance()->is_using_central() || ! $this->may_choose() ) {
			return;
		}

		// get the choice.
		$choice = $this->get_choice();

		?>
		<p class="efml-network-upload-visibility">
			<label for="efml-network-upload-visibility"><?php echo esc_html__( 'New uploads are available for', 'external-files-in-media-library' ); ?></label>
			<select id="efml-network-upload-visibility" data-nonce="<?php echo esc_attr( wp_create_nonce( 'efml-network-upload' ) ); ?>" data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" onchange="jQuery.post( this.dataset.url, { action: 'efml_network_upload_visibility', nonce: this.dataset.nonce, visibility: this.value } );">
				<option value="all"<?php selected( $choice, 'all' ); ?>><?php echo esc_html__( 'All websites', 'external-files-in-media-library' ); ?></option>
				<option value="own"<?php selected( $choice, 'own' ); ?>><?php echo esc_html__( 'Only this website', 'external-files-in-media-library' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Save the choice of the user via AJAX.
	 *
	 * @return void
	 */
	public function save_choice_via_ajax(): void {
		// check the nonce.
		check_ajax_referer( 'efml-network-upload', 'nonce' );

		// bail if the user is not allowed to upload files or to choose the availability.
		if ( ! current_user_can( 'upload_files' ) || ! $this->may_choose() ) {
			wp_send_json_error();
		}

		// get the choice.
		$choice = filter_input( INPUT_POST, 'visibility', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not valid.
		if ( ! in_array( $choice, array( 'all', 'own' ), true ) ) {
			wp_send_json_error();
		}

		// save it.
		update_user_meta( get_current_user_id(), self::META, $choice );

		// return success.
		wp_send_json_success();
	}

	/**
	 * Save the choice of the actual user on a new upload in a website, which uses a central media library.
	 *
	 * The choice is used, when the file is added to the central media library (also later via cron).
	 *
	 * @param int $attachment_id The ID of the new attachment.
	 *
	 * @return void
	 */
	public function save_choice_on_upload( int $attachment_id ): void {
		// bail if this website does not use a central media library or during a synchronization or propagation.
		if ( ! Media_Library::get_instance()->is_using_central() || Propagation::get_instance()->is_running() || \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->is_running() ) {
			return;
		}

		// bail if no user is logged in.
		if ( 0 === get_current_user_id() ) {
			return;
		}

		// save the choice.
		update_post_meta( $attachment_id, self::META, $this->get_choice() );
	}

	/**
	 * Return the availability chosen for the given upload of the actual website ('' if none has been chosen).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_choice_of_upload( int $attachment_id ): string {
		$choice = get_post_meta( $attachment_id, self::META, true );
		return in_array( $choice, array( 'all', 'own' ), true ) ? (string) $choice : '';
	}

	/**
	 * Add the options to filter for own uploads and for files of the central media library.
	 *
	 * @param array<string,string> $options The options.
	 *
	 * @return array<string,string>
	 */
	public function add_filter_options( array $options ): array {
		// bail if this website does not use a central media library.
		if ( ! Media_Library::get_instance()->is_using_central() ) {
			return $options;
		}

		// add the options.
		$options['efml-network-own']     = __( 'Own uploads', 'external-files-in-media-library' );
		$options['efml-network-central'] = __( 'From the central media library', 'external-files-in-media-library' );

		// return the resulting list.
		return $options;
	}

	/**
	 * Return the meta query for the given value of the filter (empty if it is not ours).
	 *
	 * @param string $filter The value of the filter.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_meta_query( string $filter ): array {
		// own uploads: exported to the central media library and not linked to an existing file.
		if ( 'efml-network-own' === $filter ) {
			return array(
				'relation' => 'AND',
				array(
					'key'     => 'efml_multisite_attachment_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => Media_Library::META_LINKED,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		// files of the central media library: copies and linked uploads.
		if ( 'efml-network-central' === $filter ) {
			return array(
				'relation' => 'OR',
				array(
					'relation' => 'AND',
					array(
						'key'     => Media_Library::META_CENTRAL_ID,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'efml_multisite_attachment_id',
						'compare' => 'NOT EXISTS',
					),
				),
				array(
					'key'     => Media_Library::META_LINKED,
					'compare' => 'EXISTS',
				),
			);
		}

		// return an empty list for any other value.
		return array();
	}

	/**
	 * Filter the query of the media library for our values.
	 *
	 * @param WP_Query $query The query.
	 *
	 * @return void
	 */
	public function filter_query( WP_Query $query ): void {
		// get the value.
		$filter = $query->get( 'efml_media_filter' );
		if ( ! is_string( $filter ) || '' === $filter ) {
			$filter = $query->is_main_query() ? (string) filter_input( INPUT_GET, 'admin_filter_media_external_files', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
		}

		// get our meta query.
		$meta_query = $this->get_meta_query( $filter );

		// bail if it is not ours.
		if ( empty( $meta_query ) ) {
			return;
		}

		// add it to the query, together with an existing meta query.
		$existing = $query->get( 'meta_query' );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$meta_query = array(
				'relation' => 'AND',
				$existing,
				$meta_query,
			);
		}
		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
	}
}

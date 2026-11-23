<?php
/**
 * File to configure which websites could use a file of the central media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Object, which configures which websites could use a file of the central media library.
 *
 * A file could be released for all websites, only for selected websites or for all websites except selected ones.
 * The website, from which a file comes, could always use it. A website, which could not use a file anymore, loses its
 * copy, also if the file is used there.
 */
class Visibility {
	/**
	 * Name of the page for the change of multiple files.
	 */
	private const PAGE = 'efml-network-visibility';

	/**
	 * Name of the bulk action in the media library.
	 */
	public const BULK_ACTION = 'efml_network_visibility';

	/**
	 * Name of the action, which saves the form for multiple files.
	 *
	 * It must differ from the bulk action: WordPress runs "admin_action_<action>" for each request with this action,
	 * also for the bulk action itself.
	 */
	public const SAVE_ACTION = 'efml_network_visibility_save';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Visibility
	 */
	private static ?Visibility $instance = null;

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
	 * @return Visibility
	 */
	public static function get_instance(): Visibility {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// the setting on a single file, saved via its own button (not with the other fields, as each change of a field
		// in the media dialog saves it immediately, which would remove the file from websites before all are selected).
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_field' ), 10, 2 );
		add_action( 'wp_ajax_efml_network_save_visibility', array( $this, 'save_via_ajax' ) );

		// the change of multiple files via bulk action.
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_action_' . self::SAVE_ACTION, array( $this, 'save_bulk' ) );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
	}

	/**
	 * Return the labels of the modes.
	 *
	 * @return array<string,string>
	 */
	private function get_modes(): array {
		return array(
			'all'    => __( 'All websites', 'external-files-in-media-library' ),
			'only'   => __( 'Only the selected websites', 'external-files-in-media-library' ),
			'except' => __( 'All websites except the selected ones', 'external-files-in-media-library' ),
		);
	}

	/**
	 * Return the HTML of the form for the visibility.
	 *
	 * Without a name prefix the fields have no names: they are saved via the button of the form (see save_via_ajax()).
	 *
	 * @param string                                     $name          The name prefix of the fields (empty for the button).
	 * @param array{mode: string, sites: array<int,int>} $visibility    The actual visibility.
	 * @param int                                        $origin        The website, from which the file comes.
	 * @param int                                        $attachment_id The attachment for the button.
	 *
	 * @return string
	 */
	private function get_form( string $name, array $visibility, int $origin = 0, int $attachment_id = 0 ): string {
		// the selection of the mode.
		$html = '<div class="efml-network-visibility" data-id="' . absint( $attachment_id ) . '"><select' . ( empty( $name ) ? '' : ' name="' . esc_attr( $name ) . '[efml_network_visibility_mode]"' ) . ' class="efml-network-visibility-mode">';
		foreach ( $this->get_modes() as $mode => $label ) {
			$html .= '<option value="' . esc_attr( $mode ) . '"' . selected( $visibility['mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select>';

		// the websites.
		$html .= '<div class="efml-network-visibility-sites"' . ( 'all' === $visibility['mode'] ? ' style="display:none"' : '' ) . '>';
		foreach ( Media_Library::get_instance()->get_sites() as $blog_id => $url ) {
			// the website, from which the file comes, could always use it.
			if ( $blog_id === $origin ) {
				$html .= '<label style="display:block"><input type="checkbox" disabled checked> ' . esc_html( $url ) . ' (' . esc_html__( 'the file comes from this website', 'external-files-in-media-library' ) . ')</label>';
				continue;
			}

			// add the checkbox.
			$html .= '<label style="display:block"><input type="checkbox" class="efml-network-visibility-site"' . ( empty( $name ) ? '' : ' name="' . esc_attr( $name ) . '[efml_network_visibility_site_' . absint( $blog_id ) . ']"' ) . ' value="' . absint( $blog_id ) . '"' . checked( in_array( $blog_id, $visibility['sites'], true ), true, false ) . '> ' . esc_html( $url ) . '</label>';
		}
		$html .= '</div>';

		// the button to save it.
		if ( empty( $name ) ) {
			$html .= '<p><button type="button" class="button efml-network-visibility-save">' . esc_html__( 'Save availability', 'external-files-in-media-library' ) . '</button> <span class="efml-network-visibility-result"></span></p>';
		}

		// the hint.
		$html .= '<p class="description">' . esc_html__( 'Websites, which could not use the file anymore, lose it, also if they use it in their contents.', 'external-files-in-media-library' ) . '</p></div>';

		// return the form.
		return $html;
	}

	/**
	 * Read the submitted visibility.
	 *
	 * @param array<string,mixed> $values The submitted values.
	 *
	 * @return array{mode: string, sites: array<int,int>}|false
	 */
	private function get_submitted( array $values ): array|false {
		// bail if our form has not been submitted.
		if ( ! isset( $values['efml_network_visibility_mode'] ) || ! in_array( $values['efml_network_visibility_mode'], Media_Library::VISIBILITY_MODES, true ) ) {
			return false;
		}

		// get the selected websites (missing ones are not selected).
		$sites = array();
		foreach ( array_keys( Media_Library::get_instance()->get_sites() ) as $blog_id ) {
			if ( ! empty( $values[ 'efml_network_visibility_site_' . $blog_id ] ) ) {
				$sites[] = $blog_id;
			}
		}

		// return the visibility.
		return array(
			'mode'  => (string) $values['efml_network_visibility_mode'],
			'sites' => $sites,
		);
	}

	/**
	 * Add the setting to the fields of a file in the central media library.
	 *
	 * @param array<string,array<string,mixed>> $form_fields The fields.
	 * @param WP_Post                           $post        The attachment.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_field( array $form_fields, WP_Post $post ): array {
		// bail if this is not the central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return $form_fields;
		}

		// bail if the user could not change the file or its availability.
		if ( ! current_user_can( 'edit_post', $post->ID ) || ! Upload::get_instance()->may_choose() ) {
			return $form_fields;
		}

		// add the field.
		$form_fields['efml_network_visibility'] = array(
			'label' => __( 'Available for', 'external-files-in-media-library' ),
			'input' => 'html',
			'html'  => $this->get_form( '', Media_Library::get_instance()->get_visibility( $post->ID ), Media_Library::get_instance()->get_origin_blog( $post->ID ), $post->ID ),
		);

		// return the fields.
		return $form_fields;
	}

	/**
	 * Save the setting of a file in the central media library via AJAX.
	 *
	 * @return void
	 */
	public function save_via_ajax(): void {
		// check the nonce.
		check_ajax_referer( 'efml-network', 'nonce' );

		// get the file.
		$attachment_id = absint( filter_input( INPUT_POST, 'attachment_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if this is not the central media library or if the user could not change the file.
		if ( ! Media_Library::get_instance()->is_central() || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) || ! Upload::get_instance()->may_choose() ) {
			wp_send_json_error();
		}

		// get the submitted visibility.
		$values = array( 'efml_network_visibility_mode' => isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '' );
		foreach ( isset( $_POST['sites'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['sites'] ) ) : array() as $blog_id ) {
			$values[ 'efml_network_visibility_site_' . $blog_id ] = 1;
		}
		$visibility = $this->get_submitted( $values );

		// bail if it is not valid.
		if ( false === $visibility ) {
			wp_send_json_error();
		}

		// save it, if it has been changed.
		if ( Media_Library::get_instance()->get_visibility( $attachment_id ) !== $visibility ) {
			Media_Library::get_instance()->set_visibility( $attachment_id, $visibility['mode'], $visibility['sites'] );
		}

		// return success.
		wp_send_json_success();
	}

	/**
	 * Add the bulk action in the central media library.
	 *
	 * @param array<string,string> $actions The bulk actions.
	 *
	 * @return array<string,string>
	 */
	public function add_bulk_action( array $actions ): array {
		// bail if this is not the central media library or the user could not change the availability.
		if ( ! Media_Library::get_instance()->is_central() || ! Upload::get_instance()->may_choose() ) {
			return $actions;
		}

		// add the action.
		$actions[ self::BULK_ACTION ] = __( 'Change availability in the network', 'external-files-in-media-library' );

		// return the actions.
		return $actions;
	}

	/**
	 * Forward the bulk action to the page with the form.
	 *
	 * @param string         $redirect_to The URL to redirect to.
	 * @param string         $action      The bulk action.
	 * @param array<int,int> $post_ids    The selected attachments.
	 *
	 * @return string
	 */
	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		// bail if this is not our action.
		if ( self::BULK_ACTION !== $action ) {
			return $redirect_to;
		}

		// forward to the form.
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'ids'  => implode( ',', array_map( 'absint', $post_ids ) ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Return the hook name of the hidden page with the form for multiple files (as "admin_enqueue_scripts" gets it).
	 *
	 * @return string
	 */
	public function get_page_hook(): string {
		return get_plugin_page_hookname( self::PAGE, '' );
	}

	/**
	 * Add the hidden page with the form for multiple files.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'',
			__( 'Change availability in the network', 'external-files-in-media-library' ),
			'',
			'upload_files',
			self::PAGE,
			array( $this, 'show_page' )
		);
	}

	/**
	 * Return the requested attachments of the form, which the user could change.
	 *
	 * @param string $ids The comma-separated IDs.
	 *
	 * @return array<int,int>
	 */
	private function get_requested_ids( string $ids ): array {
		// bail if the user could not change the availability.
		if ( ! Upload::get_instance()->may_choose() ) {
			return array();
		}

		$list = array();
		foreach ( array_unique( array_filter( array_map( 'absint', explode( ',', $ids ) ) ) ) as $attachment_id ) {
			if ( 'attachment' === get_post_type( $attachment_id ) && current_user_can( 'edit_post', $attachment_id ) ) {
				$list[] = $attachment_id;
			}
		}
		return $list;
	}

	/**
	 * Show the page with the form for multiple files.
	 *
	 * @return void
	 */
	public function show_page(): void {
		// bail if this is not the central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return;
		}

		// get the files.
		$ids = $this->get_requested_ids( (string) filter_input( INPUT_GET, 'ids', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Change availability in the network', 'external-files-in-media-library' ); ?></h1>
			<?php
			if ( empty( $ids ) ) {
				echo '<p>' . esc_html__( 'No files have been selected.', 'external-files-in-media-library' ) . '</p>';
				echo '</div>';
				return;
			}
			?>
			<p>
				<?php
				/* translators: %1$d will be replaced by a number. */
				echo esc_html( sprintf( _n( 'The setting is used for %1$d file:', 'The setting is used for %1$d files:', count( $ids ), 'external-files-in-media-library' ), count( $ids ) ) );
				?>
			</p>
			<ul>
				<?php
				foreach ( $ids as $attachment_id ) {
					echo '<li>' . esc_html( get_the_title( $attachment_id ) ) . '</li>';
				}
				?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<input type="hidden" name="ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
				<?php
				wp_nonce_field( 'efml-network-visibility' );
				$form = $this->get_form(
					'efml',
					array(
						'mode'  => 'all',
						'sites' => array(),
					)
				);
				echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form is escaped in get_form().
				submit_button( __( 'Save', 'external-files-in-media-library' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save the form for multiple files.
	 *
	 * @return void
	 */
	public function save_bulk(): void {
		// check the nonce.
		check_admin_referer( 'efml-network-visibility' );

		// bail if this is not the central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			wp_safe_redirect( admin_url( 'upload.php' ) );
			exit;
		}

		// get the files the user could change.
		$ids = $this->get_requested_ids( isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '' );

		// get the submitted visibility.
		$values     = isset( $_POST['efml'] ) && is_array( $_POST['efml'] ) ? map_deep( wp_unslash( $_POST['efml'] ), 'sanitize_text_field' ) : array();
		$visibility = $this->get_submitted( $values );

		// save it.
		if ( false !== $visibility ) {
			foreach ( $ids as $attachment_id ) {
				Media_Library::get_instance()->set_visibility( $attachment_id, $visibility['mode'], $visibility['sites'] );
			}
		}

		// return to the media library.
		wp_safe_redirect( add_query_arg( array( 'efml_network_visibility' => count( $ids ) ), admin_url( 'upload.php?mode=list' ) ) );
		exit;
	}

	/**
	 * Show the result of the change of multiple files.
	 *
	 * @return void
	 */
	public function show_notice(): void {
		// get the count of changed files.
		$count = filter_input( INPUT_GET, 'efml_network_visibility', FILTER_SANITIZE_NUMBER_INT );

		// bail if not set.
		if ( is_null( $count ) || false === $count ) {
			return;
		}

		// show the hint.
		/* translators: %1$d will be replaced by a number. */
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'The availability of %1$d file has been changed.', 'The availability of %1$d files has been changed.', absint( $count ), 'external-files-in-media-library' ), absint( $count ) ) ) . '</p></div>';
	}
}

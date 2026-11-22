<?php
/**
 * This file defines the settings for the multisite network of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object, which handles the settings for the multisite network of this plugin.
 */
class Settings {
	/**
	 * Name of the page in the network administration.
	 */
	public const PAGE = 'efml-network';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Settings
	 */
	private static ?Settings $instance = null;

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
	 * @return Settings
	 */
	public static function get_instance(): Settings {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize the settings for the network.
	 *
	 * @return void
	 */
	public function init(): void {
		// the page in the network administration.
		add_action( 'network_admin_menu', array( $this, 'add_page' ) );
		add_action( 'network_admin_edit_efml_network_settings', array( $this, 'save_page' ) );

		// the hint on the settings of the network, where the settings have been before.
		add_action( 'wpmu_options', array( $this, 'add_settings' ) );
	}

	/**
	 * Add the page in the network administration under "Settings".
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'settings.php',
			__( 'External files in Media Library', 'external-files-in-media-library' ),
			__( 'External files', 'external-files-in-media-library' ),
			'manage_network_options',
			self::PAGE,
			array( $this, 'show_page' )
		);
	}

	/**
	 * Return the URL of the page in the network administration.
	 *
	 * @param string $tab The tab.
	 *
	 * @return string
	 */
	public function get_url( string $tab = '' ): string {
		return network_admin_url( 'settings.php?page=' . self::PAGE . ( empty( $tab ) ? '' : '&tab=' . $tab ) );
	}

	/**
	 * Show the hint on the settings of the network, which links to our own page.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		?>
		<h2><?php echo esc_html__( 'External files in Media Library', 'external-files-in-media-library' ); ?></h2>
		<p>
			<?php
			/* translators: %1$s will be replaced by a URL. */
			echo wp_kses_post( sprintf( __( 'The settings for the central media libraries of the network are on <a href="%1$s">their own page</a>.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ) );
			?>
		</p>
		<?php
	}

	/**
	 * Return the labels of the rights to delete files.
	 *
	 * @return array<string,string>
	 */
	private function get_delete_rights(): array {
		return array(
			'none' => __( 'No file', 'external-files-in-media-library' ),
			'own'  => __( 'Only own uploads', 'external-files-in-media-library' ),
			'all'  => __( 'All files', 'external-files-in-media-library' ),
		);
	}

	/**
	 * Return all websites of the network as list of ID and label.
	 *
	 * @return array<int,string>
	 */
	private function get_all_sites(): array {
		$sites = array();
		foreach ( Helper::get_blogs() as $website ) {
			$blog_id           = absint( $website->blog_id ); // @phpstan-ignore property.notFound
			$sites[ $blog_id ] = Media_Library::get_instance()->get_site_label( $blog_id );
		}
		ksort( $sites );
		return $sites;
	}

	/**
	 * Return the HTML of a selection of a website.
	 *
	 * @param string            $name     The name of the field.
	 * @param int               $selected The selected website.
	 * @param array<int,string> $sites    The websites.
	 * @param string            $none_label    The label for no selection.
	 *
	 * @return string
	 */
	private function get_site_select( string $name, int $selected, array $sites, string $none_label ): string {
		$html = '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html( $none_label ) . '</option>';
		foreach ( $sites as $blog_id => $label ) {
			$html .= '<option value="' . absint( $blog_id ) . '"' . selected( $selected, $blog_id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * Return the HTML of the field for the roles of the given website, which could choose the availability of files.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return string
	 */
	private function get_roles_field( int $blog_id ): string {
		// get the roles of the website (they could differ between the websites).
		switch_to_blog( $blog_id );
		$names = wp_roles()->get_names();
		restore_current_blog();

		// get the own choice of the website.
		$site_roles = Upload::get_instance()->get_site_roles( $blog_id );
		$name       = 'efml_network_sites[' . absint( $blog_id ) . ']';

		// create the field.
		$html  = '<input type="hidden" name="' . esc_attr( $name ) . '[roles_shown]" value="1">';
		$html .= '<label style="display:block"><input type="checkbox" name="' . esc_attr( $name ) . '[roles_default]" value="1"' . checked( is_null( $site_roles ), true, false ) . '> ' . esc_html__( 'Default', 'external-files-in-media-library' ) . '</label>';
		foreach ( $names as $role => $role_label ) {
			if ( 'administrator' === $role ) {
				continue;
			}
			$html .= '<label style="margin-right:.5em"><input type="checkbox" name="' . esc_attr( $name ) . '[roles][]" value="' . esc_attr( $role ) . '"' . checked( ! is_null( $site_roles ) && in_array( $role, $site_roles, true ), true, false ) . '> ' . esc_html( translate_user_role( $role_label ) ) . '</label>';
		}
		return $html;
	}

	/**
	 * Return the websites, to which the central media library of the given group could be moved.
	 *
	 * @param int               $group_id The group.
	 * @param array<int,string> $sites    All websites.
	 *
	 * @return array<int,string>
	 */
	private function get_move_targets( int $group_id, array $sites ): array {
		$targets = array();
		foreach ( $sites as $blog_id => $label ) {
			if ( ! Media_Library::get_instance()->is_central( $blog_id ) && in_array( Media_Library::get_instance()->get_group_id( $blog_id ), array( 0, $group_id ), true ) ) {
				$targets[ $blog_id ] = $label;
			}
		}
		return $targets;
	}

	/**
	 * Show the page in the network administration.
	 *
	 * @return void
	 */
	public function show_page(): void {
		// bail if the user is not allowed to change the settings of the network.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// get the actual tab.
		$tab = 'overview' === filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ? 'overview' : 'settings';

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'External files in Media Library', 'external-files-in-media-library' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $this->get_url() ); ?>" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__( 'Settings', 'external-files-in-media-library' ); ?></a>
				<a href="<?php echo esc_url( $this->get_url( 'overview' ) ); ?>" class="nav-tab<?php echo 'overview' === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__( 'Overview', 'external-files-in-media-library' ); ?></a>
			</nav>
			<?php
			$error = filter_input( INPUT_GET, 'efml_error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $error ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
			if ( ! is_null( filter_input( INPUT_GET, 'updated', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'external-files-in-media-library' ) . '</p></div>';
			}
			if ( 'overview' === $tab ) {
				Overview::get_instance()->show();
			} else {
				$this->show_settings();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Show the settings.
	 *
	 * @return void
	 */
	private function show_settings(): void {
		// get the actual settings.
		$media_library = Media_Library::get_instance();
		$groups        = $media_library->get_groups();
		$rules         = $media_library->get_rules();
		$sites         = $this->get_all_sites();
		$move_running  = Move::get_instance()->is_running();

		?>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=efml_network_settings' ) ); ?>">
			<?php wp_nonce_field( 'efml-network-settings' ); ?>
			<h2><?php echo esc_html__( 'Groups', 'external-files-in-media-library' ); ?></h2>
			<?php
			if ( $move_running ) {
				echo '<div class="notice notice-info inline"><p>' . esc_html__( 'A central media library is moved in the background at the moment. The groups could be changed afterward.', 'external-files-in-media-library' ) . '</p></div>';
			}
			?>
			<p><?php echo wp_kses_post( __( 'Each group has a central media library: all files of the websites of the group are stored there, the websites use them. Groups are separated from each other. <strong>Create a backup of everything beforehand.</strong>', 'external-files-in-media-library' ) ); ?></p>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Name', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Central media library', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Move the central media library to', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Remove', 'external-files-in-media-library' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $groups as $group_id => $group ) {
						?>
						<tr>
							<td><input type="text" name="efml_groups[<?php echo absint( $group_id ); ?>][name]" value="<?php echo esc_attr( $group['name'] ); ?>"></td>
							<td><?php echo $this->get_site_select( 'efml_groups[' . absint( $group_id ) . '][central]', $group['central'], $sites, __( 'Choose the website', 'external-files-in-media-library' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td>
								<?php
								if ( $move_running ) {
									echo '<em>' . esc_html__( 'A move is running.', 'external-files-in-media-library' ) . '</em>';
								} else {
									echo $this->get_site_select( 'efml_groups[' . absint( $group_id ) . '][move]', 0, $this->get_move_targets( (int) $group_id, $sites ), __( 'Do not move', 'external-files-in-media-library' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</td>
							<td><label><input type="checkbox" name="efml_groups[<?php echo absint( $group_id ); ?>][remove]" value="1"> <?php echo esc_html__( 'Remove this group', 'external-files-in-media-library' ); ?></label></td>
						</tr>
						<?php
					}
					?>
					<tr>
						<td><input type="text" name="efml_groups[new][name]" value="" placeholder="<?php echo esc_attr__( 'Name of a new group', 'external-files-in-media-library' ); ?>"></td>
						<td><?php echo $this->get_site_select( 'efml_groups[new][central]', 0, $sites, __( 'Choose the website', 'external-files-in-media-library' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>

			<?php
			if ( ! empty( $groups ) ) {
				?>
				<h2><?php echo esc_html__( 'Websites', 'external-files-in-media-library' ); ?></h2>
				<p><?php echo esc_html__( 'A website, which leaves its group, keeps its own uploads and the files used in its contents as local files (see below). This runs in the background.', 'external-files-in-media-library' ); ?></p>
				<table class="widefat striped" style="max-width:900px">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Website', 'external-files-in-media-library' ); ?></th>
							<th><?php echo esc_html__( 'Group', 'external-files-in-media-library' ); ?></th>
							<th><?php echo esc_html__( 'Could delete', 'external-files-in-media-library' ); ?></th>
							<th><?php echo esc_html__( 'Could choose the availability', 'external-files-in-media-library' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $sites as $blog_id => $label ) {
							// show the central media libraries without selection.
							if ( $media_library->is_central( $blog_id ) ) {
								?>
								<tr>
									<td><?php echo esc_html( $label ); ?></td>
									<td><em><?php echo esc_html( sprintf( /* translators: %1$s will be replaced by the name of a group. */ __( 'Central media library of %1$s', 'external-files-in-media-library' ), $groups[ $media_library->get_group_id( $blog_id ) ]['name'] ) ); ?></em></td>
									<td><?php echo esc_html__( 'All files', 'external-files-in-media-library' ); ?></td>
									<td><?php echo $this->get_roles_field( $blog_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								</tr>
								<?php
								continue;
							}

							$group_id   = $media_library->get_group_id( $blog_id );
							$site_right = $rules['sites'][ $blog_id ]['delete'] ?? '';
							?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td>
									<select name="efml_network_sites[<?php echo absint( $blog_id ); ?>][group]">
										<option value="0"><?php echo esc_html__( 'No group', 'external-files-in-media-library' ); ?></option>
										<?php
										foreach ( $groups as $id => $group ) {
											echo '<option value="' . absint( $id ) . '"' . selected( $group_id, $id, false ) . '>' . esc_html( $group['name'] ) . '</option>';
										}
										?>
									</select>
								</td>
								<td>
									<select name="efml_network_sites[<?php echo absint( $blog_id ); ?>][delete]">
										<option value=""><?php echo esc_html__( 'Default', 'external-files-in-media-library' ); ?></option>
										<?php
										foreach ( $this->get_delete_rights() as $right => $right_label ) {
											echo '<option value="' . esc_attr( $right ) . '"' . selected( $site_right, $right, false ) . '>' . esc_html( $right_label ) . '</option>';
										}
										?>
									</select>
								</td>
								<td><?php echo $this->get_roles_field( $blog_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
			}
			?>

			<h2><?php echo esc_html__( 'Settings', 'external-files-in-media-library' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="efml_network_delete"><?php echo esc_html__( 'Websites could delete', 'external-files-in-media-library' ); ?></label></th>
					<td>
						<select name="efml_network_delete" id="efml_network_delete">
							<?php
							foreach ( $this->get_delete_rights() as $right => $label ) {
								echo '<option value="' . esc_attr( $right ) . '"' . selected( $rules['delete'], $right, false ) . '>' . esc_html( $label ) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php echo esc_html__( 'A file of the central media library is always deleted in all websites of its group. Users of the central media library could delete every file.', 'external-files-in-media-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="efml_network_visibility"><?php echo esc_html__( 'New uploads are available for', 'external-files-in-media-library' ); ?></label></th>
					<td>
						<select name="efml_network_visibility" id="efml_network_visibility">
							<option value="all"<?php selected( $rules['visibility'], 'all' ); ?>><?php echo esc_html__( 'All websites', 'external-files-in-media-library' ); ?></option>
							<option value="own"<?php selected( $rules['visibility'], 'own' ); ?>><?php echo esc_html__( 'Only the website, in which it has been uploaded', 'external-files-in-media-library' ); ?></option>
						</select>
						<p class="description"><?php echo esc_html__( 'In the central media library it could be changed for each file.', 'external-files-in-media-library' ); ?></p>
						<p><?php echo esc_html__( 'By default could choose the availability for their uploads and change it on the files in the central media library (besides super administrators and administrators, differently for single websites in the table above):', 'external-files-in-media-library' ); ?></p>
						<?php
						$upload_roles = Upload::get_instance()->get_default_roles();
						foreach ( wp_roles()->get_names() as $role => $role_label ) {
							if ( 'administrator' === $role ) {
								continue;
							}
							?>
							<label style="margin-right:1em"><input type="checkbox" name="efml_upload_roles[]" value="<?php echo esc_attr( $role ); ?>"<?php checked( in_array( $role, $upload_roles, true ) ); ?>> <?php echo esc_html( translate_user_role( $role_label ) ); ?></label>
							<?php
						}
						?>
						<p class="description"><?php echo esc_html__( 'The other users do not see these options: their uploads get the setting above.', 'external-files-in-media-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="efml_settings_new_sites"><?php echo esc_html__( 'New websites join', 'external-files-in-media-library' ); ?></label></th>
					<td>
						<select name="efml_settings[new_sites]" id="efml_settings_new_sites">
							<option value="-1"<?php selected( (int) $media_library->get_setting( 'new_sites' ), -1 ); ?>><?php echo esc_html__( 'The first group', 'external-files-in-media-library' ); ?></option>
							<option value="0"<?php selected( (int) $media_library->get_setting( 'new_sites' ), 0 ); ?>><?php echo esc_html__( 'No group', 'external-files-in-media-library' ); ?></option>
							<?php
							foreach ( $groups as $id => $group ) {
								echo '<option value="' . absint( $id ) . '"' . selected( (int) $media_library->get_setting( 'new_sites' ), $id, false ) . '>' . esc_html( $group['name'] ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="efml_settings_leave"><?php echo esc_html__( 'A website leaving its group', 'external-files-in-media-library' ); ?></label></th>
					<td>
						<select name="efml_settings[leave]" id="efml_settings_leave">
							<option value="local"<?php selected( $media_library->get_setting( 'leave' ), 'local' ); ?>><?php echo esc_html__( 'keeps its own uploads and the files used in its contents as local files', 'external-files-in-media-library' ); ?></option>
							<option value="remove"<?php selected( $media_library->get_setting( 'leave' ), 'remove' ); ?>><?php echo esc_html__( 'loses all files of the central media library', 'external-files-in-media-library' ); ?></option>
						</select>
						<p class="description"><?php echo esc_html__( 'The files in the central media library are kept in any case.', 'external-files-in-media-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="efml_settings_deleted_site"><?php echo esc_html__( 'Uploads of a deleted website', 'external-files-in-media-library' ); ?></label></th>
					<td>
						<select name="efml_settings[deleted_site]" id="efml_settings_deleted_site">
							<option value="keep"<?php selected( $media_library->get_setting( 'deleted_site' ), 'keep' ); ?>><?php echo esc_html__( 'are kept and belong to the central media library', 'external-files-in-media-library' ); ?></option>
							<option value="delete"<?php selected( $media_library->get_setting( 'deleted_site' ), 'delete' ); ?>><?php echo esc_html__( 'are deleted in all websites, also where they are used', 'external-files-in-media-library' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Further options', 'external-files-in-media-library' ); ?></th>
					<td>
						<p><label><input type="hidden" name="efml_settings[quota]" value="0"><input type="checkbox" name="efml_settings[quota]" value="1"<?php checked( 1, (int) $media_library->get_setting( 'quota' ) ); ?>> <?php echo esc_html__( 'Count the uploads of a website in the central media library for its upload space', 'external-files-in-media-library' ); ?></label></p>
						<p class="description"><?php echo esc_html__( 'Only if the upload space of the websites is limited in the settings of the network. Otherwise, the uploads of the websites do not count, as they are not stored there.', 'external-files-in-media-library' ); ?></p>
						<p><label><input type="hidden" name="efml_settings[dedupe]" value="0"><input type="checkbox" name="efml_settings[dedupe]" value="1"<?php checked( 1, (int) $media_library->get_setting( 'dedupe' ) ); ?>> <?php echo esc_html__( 'Use an existing file of the central media library for an upload with the same content', 'external-files-in-media-library' ); ?></label></p>
						<p><label><input type="hidden" name="efml_settings[origin_edits]" value="0"><input type="checkbox" name="efml_settings[origin_edits]" value="1"<?php checked( 1, (int) $media_library->get_setting( 'origin_edits' ) ); ?>> <?php echo esc_html__( 'Changes of caption, description and alternative text in the website, from which a file comes, apply to all websites', 'external-files-in-media-library' ); ?></label></p>
						<p><label><input type="hidden" name="efml_settings[digest]" value="0"><input type="checkbox" name="efml_settings[digest]" value="1"<?php checked( 1, (int) $media_library->get_setting( 'digest' ) ); ?>> <?php echo esc_html__( 'Send a weekly summary of problems in the websites to the e-mail address of the network administration (only if there are problems)', 'external-files-in-media-library' ); ?></label></p>
						<p><label><input type="checkbox" name="efml_hide_options" value="1"<?php checked( 1, absint( get_site_option( 'efml_hide_options', 0 ) ) ); ?>> <?php echo esc_html__( 'Hide the options of this plugin in the websites, which use a central media library', 'external-files-in-media-library' ); ?></label></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Save the page in the network administration.
	 *
	 * @return void
	 */
	public function save_page(): void {
		// check the nonce.
		check_admin_referer( 'efml-network-settings' );

		// bail if the user is not allowed to change the settings of the network.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'external-files-in-media-library' ) );
		}

		// get the submitted values.
		$submitted_groups = isset( $_POST['efml_groups'] ) && is_array( $_POST['efml_groups'] ) ? map_deep( wp_unslash( $_POST['efml_groups'] ), 'sanitize_text_field' ) : array();
		$submitted_sites  = isset( $_POST['efml_network_sites'] ) && is_array( $_POST['efml_network_sites'] ) ? map_deep( wp_unslash( $_POST['efml_network_sites'] ), 'sanitize_text_field' ) : array();
		$submitted        = isset( $_POST['efml_settings'] ) && is_array( $_POST['efml_settings'] ) ? map_deep( wp_unslash( $_POST['efml_settings'] ), 'sanitize_text_field' ) : array();
		$hide_options     = absint( filter_input( INPUT_POST, 'efml_hide_options', FILTER_SANITIZE_NUMBER_INT ) );

		// save the further settings and the rules.
		Media_Library::get_instance()->set_settings( $submitted );
		$site_roles = array();
		foreach ( $submitted_sites as $blog_id => $values ) {
			if ( ! is_array( $values ) || ! isset( $values['roles_shown'] ) ) {
				continue;
			}
			$site_roles[ absint( $blog_id ) ] = ! empty( $values['roles_default'] ) ? null : array_map( 'strval', isset( $values['roles'] ) && is_array( $values['roles'] ) ? $values['roles'] : array() );
		}
		Upload::get_instance()->set_roles( isset( $_POST['efml_upload_roles'] ) && is_array( $_POST['efml_upload_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['efml_upload_roles'] ) ) : array(), $site_roles );
		$site_rules = array();
		foreach ( $submitted_sites as $blog_id => $values ) {
			if ( is_array( $values ) && ! empty( $values['delete'] ) ) {
				$site_rules[ absint( $blog_id ) ] = array( 'delete' => (string) $values['delete'] );
			}
		}
		Media_Library::get_instance()->set_rules(
			isset( $_POST['efml_network_delete'] ) ? sanitize_text_field( wp_unslash( $_POST['efml_network_delete'] ) ) : 'own',
			isset( $_POST['efml_network_visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['efml_network_visibility'] ) ) : 'all',
			$site_rules
		);

		// start a requested move of a central media library: the groups are not changed in this request then.
		$move_result = null;
		foreach ( $submitted_groups as $group_id => $values ) {
			if ( is_array( $values ) && ! empty( $values['move'] ) && is_null( $move_result ) ) {
				$move_result = Move::get_instance()->start( absint( $group_id ), absint( $values['move'] ) );
			}
		}

		// get the changed groups.
		$old_groups = Media_Library::get_instance()->get_groups();
		$new_groups = is_null( $move_result ) ? $this->get_groups_from_request( $old_groups, $submitted_groups, $submitted_sites ) : $old_groups;

		// apply the option to hide the options of the plugin: it changes the capabilities in each website.
		$hide_changed = ( 1 === $hide_options ) !== ( 1 === absint( get_site_option( 'efml_hide_options', 0 ) ) );
		update_site_option( 'efml_hide_options', $hide_options );

		// apply the groups.
		Membership::get_instance()->apply_groups( $old_groups, $new_groups );

		// apply the changed option to hide the options in all websites, which stay in their group.
		if ( $hide_changed && ! Move::get_instance()->is_running() ) {
			foreach ( Media_Library::get_instance()->get_groups() as $group ) {
				foreach ( $group['sites'] as $blog_id ) {
					switch_to_blog( $blog_id );
					Membership::get_instance()->connect_site( $group['central'] );
					restore_current_blog();
				}
			}
		}

		// return to the page.
		$args = array( 'updated' => 'true' );
		if ( $move_result instanceof \WP_Error ) {
			$args['efml_error'] = rawurlencode( $move_result->get_error_message() );
		}
		wp_safe_redirect( add_query_arg( $args, $this->get_url() ) );
		exit;
	}

	/**
	 * Return the groups from the submitted values.
	 *
	 * @param array<int,array{name: string, central: int, sites: array<int,int>}> $old_groups       The actual groups.
	 * @param array<int|string,mixed>                                             $submitted_groups The submitted groups.
	 * @param array<int|string,mixed>                                             $submitted_sites  The submitted websites.
	 *
	 * @return array<int,array{name: string, central: int, sites: array<int,int>}>
	 */
	public function get_groups_from_request( array $old_groups, array $submitted_groups, array $submitted_sites ): array {
		// collect the groups with their central media library: each website could be the central of one group only.
		$groups   = array();
		$centrals = array();
		foreach ( $submitted_groups as $group_id => $values ) {
			// bail if the values are not usable or the group should be removed.
			if ( ! is_array( $values ) || ! empty( $values['remove'] ) ) {
				continue;
			}

			// get the central media library.
			$central = absint( $values['central'] ?? 0 );
			if ( 0 === $central || isset( $centrals[ $central ] ) ) {
				continue;
			}

			// get the ID of the group: a new group gets the next free ID.
			if ( 'new' === $group_id ) {
				if ( empty( $values['name'] ) ) {
					continue;
				}
				$group_id = max( array_merge( array( 0 ), array_keys( $old_groups ), array_keys( $groups ) ) ) + 1;
			} elseif ( ! isset( $old_groups[ absint( $group_id ) ] ) ) {
				continue;
			}

			// add the group.
			$groups[ absint( $group_id ) ] = array(
				'name'    => empty( $values['name'] ) ? (string) get_blogaddress_by_id( $central ) : (string) $values['name'],
				'central' => $central,
				'sites'   => array(),
			);
			$centrals[ $central ]          = true;
		}

		// add the websites to their group: without submitted group they keep their actual one.
		foreach ( array_keys( $this->get_all_sites() ) as $blog_id ) {
			// a central media library is in its own group only.
			if ( isset( $centrals[ $blog_id ] ) ) {
				continue;
			}

			// get the group.
			$group_id = isset( $submitted_sites[ $blog_id ] ) && is_array( $submitted_sites[ $blog_id ] ) && isset( $submitted_sites[ $blog_id ]['group'] ) ? absint( $submitted_sites[ $blog_id ]['group'] ) : Media_Library::get_instance()->get_group_id( $blog_id );

			// add it.
			if ( isset( $groups[ $group_id ] ) ) {
				$groups[ $group_id ]['sites'][] = $blog_id;
			}
		}

		// return the groups.
		return $groups;
	}

	/**
	 * Update the settings from the request.
	 *
	 * Used by the settings of the network in versions before 6.1.0. Kept for code, which calls it.
	 *
	 * @return void
	 */
	public function update_settings(): void {
		$this->apply( absint( filter_input( INPUT_POST, 'efml_media_library_site_id', FILTER_SANITIZE_NUMBER_INT ) ), absint( filter_input( INPUT_POST, 'efml_hide_options', FILTER_SANITIZE_NUMBER_INT ) ) );
	}

	/**
	 * Use one central media library for all websites (or none), like in versions before 6.1.0.
	 *
	 * @param int $efml_media_library_site_id The website with the central media library (0 to use none).
	 * @param int $efml_hide_options          1 to hide the options of the plugin in the other websites.
	 *
	 * @return void
	 */
	public function apply( int $efml_media_library_site_id, int $efml_hide_options ): void {
		// save the option to hide the options.
		update_site_option( 'efml_hide_options', $efml_hide_options );

		// get the groups: one group with all websites or none.
		$groups = array();
		if ( $efml_media_library_site_id > 0 ) {
			$sites = array();
			foreach ( Helper::get_blogs( $efml_media_library_site_id ) as $website ) {
				$sites[] = absint( $website->blog_id ); // @phpstan-ignore property.notFound
			}
			$groups[1] = array(
				'name'    => __( 'Network', 'external-files-in-media-library' ),
				'central' => $efml_media_library_site_id,
				'sites'   => $sites,
			);
		}

		// apply them.
		Membership::get_instance()->apply_groups( Media_Library::get_instance()->get_groups(), $groups );
	}

	/**
	 * Connect the actual website with the central media library.
	 *
	 * Kept for code, which calls it. Use Membership::connect_site().
	 *
	 * @param int $efml_media_library_site_id The website with the central media library.
	 * @param int $efml_hide_options          Not used anymore, the network option is used.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function connect_site( int $efml_media_library_site_id, int $efml_hide_options ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		Membership::get_instance()->connect_site( $efml_media_library_site_id );
	}

	/**
	 * Return the site ID of the website with the central media library of the actual website.
	 *
	 * @return int
	 */
	public function get_main_media_library_site_id(): int {
		return Media_Library::get_instance()->get_central_site_id();
	}
}

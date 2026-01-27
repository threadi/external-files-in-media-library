<?php
/**
 * This file defines the settings for the multisite network of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Roles;
use WP_Role;

defined( 'ABSPATH' ) || exit;

/**
 * Object, which handles the settings for the multisite network of this plugin.
 */
class Settings {

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
		add_action( 'wpmu_options', array( $this, 'add_settings' ) );
		add_action( 'update_wpmu_options', array( $this, 'update_settings' ) );
	}

	/**
	 * Add the settings under "settings" in a multisite network.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the actual settings.
		$efml_media_library = $this->get_main_media_library_site_id();
		$efml_hide_options = $this->get_hide_options();
		var_dump($efml_hide_options);

		// show selection.
		?>
			<h2><?php echo esc_html__( 'External files in Media Library', 'external-files-in-media-library' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="efml_media_library_site_id"><?php _e( 'One media library for all sites' ); ?></label></th>
					<td>
						<select name="efml_media_library_site_id" id="efml_media_library_site_id">
							<option value="0"><?php echo __( 'Not used', 'external-files-in-media-library' ); ?></option>
							<?php
								foreach( Helper::get_blogs() as $website ) {
									// get the URL of this website.
									$url = get_blogaddress_by_id( $website->blog_id );

									// show it.
									?><option value="<?php echo absint( $website->blog_id ); ?>"<?php echo ( $efml_media_library === absint( $website->blog_id ) ? ' selected' : '' ); ?>><?php echo esc_html( $url ); ?></option><?php
								}
							?>
						</select>
						<p><?php echo wp_kses_post( __( 'All files from all websites are stored in the media library of the website selected here. The other websites store a reference to these. Depending on the file type, the URLs using a proxy or are delivered directly. <strong>Create a backup of everything beforehand.</strong>', 'external-files-in-media-library' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="efml_hide_options"><?php _e( 'Hide options' ); ?></label></th>
					<td>
						<input type="checkbox" name="efml_hide_options" id="efml_hide_options" value="1"<?php echo ( $efml_hide_options ? ' checked="checked"' : '' ); ?>>
						<p><?php echo esc_html__( 'If enabled no user will be able to use external files options in any multisite except the one configured above.', 'external-files-in-media-library' ) ?></p>
					</td>
				</tr>
			</table><?php
	}

	/**
	 * Update the settings.
	 *
	 * "efml_media_library_site_id":
	 * If enabled every uploaded file of all websites will be saved on the chosen website via Multisite Export.
	 * And if any file is uploaded on the main site, it will be synced as external file in every website in this multisite.
	 *
	 * @return void
	 */
	public function update_settings(): void {
		// get our setting from request.
		$efml_media_library_site_id = absint( filter_input( INPUT_POST, 'efml_media_library_site_id', FILTER_SANITIZE_NUMBER_INT ) );
		$efml_hide_options = absint( filter_input( INPUT_POST, 'efml_hide_options', FILTER_SANITIZE_NUMBER_INT ) );

		// if setting is 0, disable it and remove all export settings from each site.
		if( 0 === $efml_media_library_site_id ) {
			// loop through each site and add the main library as external source for export.
			foreach( Helper::get_blogs() as $website ) {
				// switch to the site.
				switch_to_blog( $website->blog_id );

				// get the export terms.
				$terms = Export::get_instance()->get_export_terms();

				// remove them.
				foreach( $terms as $term_id ) {
					Taxonomy::get_instance()->delete( $term_id );
				}

				// disable the export tool.
				update_option( 'eml_export', 0 );

				// reset the capability for 'eml_manage_files' in this blog.
				Roles::get_instance()->set_capabilities( get_option( 'eml_allowed_roles' ) );
			}

			// return to the current blog.
			restore_current_blog();

			// remove the setting.
			delete_site_option( 'efml_media_library_site_id' );
		}
		else {
			// get the URL of the chosen site.
			$url = get_blogaddress_by_id( $efml_media_library_site_id );

			// prepare the fields for export configuration.
			$fields = array(
				'website' => array(
					'value' => $efml_media_library_site_id,
				)
			);

			// Loop through each site and:
			// - add the main library as external target for export and source for sync.
			// - change the capabilities to hide any external files features.
			foreach( Helper::get_blogs( $efml_media_library_site_id ) as $website ) {
				// get the blog ID.
				$blog_id = absint( $website->blog_id );

				// switch to the site.
				switch_to_blog( $blog_id );

				// enable the export tool.
				update_option( 'eml_export', 1 );

				// add the entry for the export.
				$term_id = Taxonomy::get_instance()->add( 'multisite', $url, $fields );

				// bail if term_id could not be loaded.
				if( 0 === $term_id ) {
					continue;
				}

				// set the URL on the export.
				update_term_meta( $term_id, 'efml_export_url', $url );

				// set is as default for export in this site.
				Export::get_instance()->set_state_for_term( $term_id, 1 );

				// enable sync in this site.
				update_option( 'eml_sync', 1 );

				// enable the sync for this term.
				Synchronization::get_instance()->set_state( $term_id, 1 );

				// remove the capability 'eml_manage_files' from each role if this is requested.
				if( 1 === $efml_hide_options ) {
					foreach ( wp_roles()->roles as $slug => $role ) {
						// get the role-object.
						$role_obj = get_role( $slug );

						// bail if role object could not be loaded.
						if ( ! $role_obj instanceof WP_Role ) {
							continue;
						}

						// remove capability.
						$role_obj->remove_cap( EFML_CAP_NAME );
					}
				}
				else {
					// reset the capability for 'eml_manage_files' in this blog.
					Roles::get_instance()->set_capabilities( get_option( 'eml_allowed_roles' ) );
				}
			}

			// return to the current blog.
			restore_current_blog();

			// update this setting.
			update_site_option( 'efml_media_library_site_id', $efml_media_library_site_id );
		}

		// update the hide options setting.
		update_site_option( 'efml_hide_options', $efml_hide_options );
	}

	/**
	 * Return the site ID of the website, which has the main media library.
	 *
	 * @return int
	 */
	public function get_main_media_library_site_id(): int {
		return absint( get_site_option( 'efml_media_library_site_id', 0 ) );
	}

	/**
	 * Return the hide options setting.
	 *
	 * @return bool
	 */
	private function get_hide_options(): bool {
		return 1 === absint( get_site_option( 'efml_hide_options', 0 ) );
	}
}

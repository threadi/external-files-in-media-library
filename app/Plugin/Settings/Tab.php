<?php
/**
 * This file represents a single tab for settings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to hold single tab in settings.
 */
class Tab {
	/**
	 * The internal name of this tab.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * The title of this tab.
	 *
	 * @var string
	 */
	private string $title = '';

	/**
	 * List of sections on this tab.
	 *
	 * @var array
	 */
	private array $sections = array();

	/**
	 * List of settings on this tab.
	 *
	 * @var array
	 */
	private array $settings = array();

	/**
	 * Callback for the tab.
	 *
	 * @var array
	 */
	private array $callback = array();

	/**
	 * URL for tab navigation.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * Target-attribute-value for URL.
	 *
	 * @var string
	 */
	private string $url_target = '_self';

	/**
	 * Tab class.
	 *
	 * @var string
	 */
	private string $tab_class = '';

	/**
	 * Show this tab in menu instead as tab.
	 *
	 * @var bool
	 */
	private bool $show_in_menu = false;

	/**
	 * Hide save button.
	 *
	 * @var bool
	 */
	private bool $hide_save = false;

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Return the internal name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		$name = $this->name;

		/**
		 * Filter the name of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $name The name.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_name', $name, $this );
	}

	/**
	 * Set internal name.
	 *
	 * @param string $name The name to use.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Return the internal name.
	 *
	 * @return string
	 */
	public function get_title(): string {
		$title = $this->title;

		/**
		 * Filter the title of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $title The title.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_title', $title, $this );
	}

	/**
	 * Set internal name.
	 *
	 * @param string $title The title to use.
	 *
	 * @return void
	 */
	public function set_title( string $title ): void {
		$this->title = $title;
	}

	/**
	 * Return list of setting-objects assigned to this tab.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$settings = $this->settings;

		/**
		 * Filter the settings of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $settings The settings.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_settings', $settings, $this );
	}

	/**
	 * Output single tab in backend settings.
	 *
	 * @return void
	 */
	public function display(): void {
		?>
		<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
			<?php
			settings_fields( $this->get_name() );
			do_settings_sections( $this->get_name() );
			$this->is_save_hidden() ? '' : submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Return whether this is the current tab in backend settings view.
	 *
	 * @return bool
	 */
	public function is_current(): bool {
		// bail if this is not wp-admin.
		if ( ! is_admin() ) {
			return false;
		}

		// get tab from request.
		$tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no tab is set.
		if ( is_null( $tab ) ) {
			return false;
		}

		// return whether the name matches.
		return $this->get_name() === $tab;
	}

	/**
	 * Return list of sections in this tab.
	 *
	 * @return array
	 */
	public function get_sections(): array {
		$sections = $this->sections;

		/**
		 * Filter the sections of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $sections The settings.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_settings', $sections, $this );
	}

	/**
	 * Add section to the list of sections in this tab.
	 *
	 * @param string|Section $section The section as object.
	 *
	 * @return false|Section
	 */
	public function add_section( string|Section $section ): false|Section {
		// initialize the section object value.
		$section_obj = false;

		// if value is a string, create the tab object first.
		if ( is_string( $section ) ) {
			$section_obj = new Section();
			$section_obj->set_name( $section );
		}

		// if value is a Tab object, use it.
		if ( $section instanceof Section ) {
			$section_obj = $section;
		}

		// bail if $tab_obj is not set.
		if ( ! $section_obj instanceof Section ) {
			return false;
		}

		// set the tab where this section is assigned to.
		$section_obj->set_tab( $this );

		// add the section to the list of sections of this tab.
		$this->sections[] = $section_obj;

		// return the tab object.
		return $section_obj;
	}

	/**
	 * Return the callback.
	 *
	 * @return array
	 */
	public function get_callback(): array {
		// if callback is empty, use our default callback.
		if ( empty( $this->callback ) ) {
			return array( $this, 'display' );
		}

		// return the callback.
		return $this->callback;
	}

	/**
	 * Set the callback.
	 *
	 * @param array $callback The callback.
	 *
	 * @return void
	 */
	public function set_callback( array $callback ): void {
		// bail if callback is not callable.
		if ( ! is_callable( $callback ) ) {
			return;
		}

		// set the callback.
		$this->callback = $callback;
	}

	/**
	 * Return the URL for the tab navigation.
	 *
	 * @return string
	 */
	public function get_url(): string {
		$url = $this->url;

		/**
		 * Filter the URL of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $url The settings.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_settings', $url, $this );
	}

	/**
	 * Set the URL for the tab navigation.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Return the URL target.
	 *
	 * @return string
	 */
	public function get_url_target(): string {
		$url_target = $this->url_target;

		/**
		 * Filter the URL target of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $url_target The URL target.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_settings', $url_target, $this );
	}

	/**
	 * Set the URL target.
	 *
	 * @param string $url_target The URL target.
	 *
	 * @return void
	 */
	public function set_url_target( string $url_target ): void {
		// bail if target does not have a valid value.
		if ( ! in_array( $url_target, array( '_self', '_blank', '_top', '_parent' ), true ) ) {
			return;
		}

		// set the target-value.
		$this->url_target = $url_target;
	}

	/**
	 * Return the tab class.
	 *
	 * @return string
	 */
	public function get_tab_class(): string {
		$tab_class = $this->tab_class;

		/**
		 * Filter the class of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $tab_class The tab class.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( Settings::get_instance()->get_slug() . '_settings_tab_settings', $tab_class, $this );
	}

	/**
	 * Set the tab class.
	 *
	 * @param string $tab_class The class for the tab.
	 *
	 * @return void
	 */
	public function set_tab_class( string $tab_class ): void {
		$this->tab_class = $tab_class;
	}

	/**
	 * Return whether to show this tab in menu instead of tab in settings page.
	 *
	 * Works only if settings value for parent slug is not 'options-general.php'.
	 *
	 * @return bool
	 */
	public function is_show_in_menu(): bool {
		return $this->show_in_menu;
	}

	/**
	 * Show in menu.
	 *
	 * @param bool $show_in_menu The value to use.
	 *
	 * @return void
	 */
	public function set_show_in_menu( bool $show_in_menu ): void {
		$this->show_in_menu = $show_in_menu;
	}

	/**
	 * Return a section of this tab by its name.
	 *
	 * @param string $section_name The name of the searched section.
	 *
	 * @return false|Section
	 */
	public function get_section( string $section_name ): false|Section {
		foreach ( $this->get_sections() as $section_obj ) {
			// bail if section is not Section object.
			if ( ! $section_obj instanceof Section ) {
				continue;
			}

			// bail if names do not match.
			if ( $section_obj->get_name() !== $section_name ) {
				continue;
			}

			return $section_obj;
		}

		// return false if object has not been found.
		return false;
	}

	/**
	 * Return whether to hide the save button.
	 * *
	 *
	 * @return bool
	 */
	public function is_save_hidden(): bool {
		return $this->hide_save;
	}

	/**
	 * Set hide the save button.
	 *
	 * @param bool $hide_save_button Hide the button (true) or not (false).
	 *
	 * @return void
	 */
	public function set_hide_save( bool $hide_save_button ): void {
		$this->hide_save = $hide_save_button;
	}
}

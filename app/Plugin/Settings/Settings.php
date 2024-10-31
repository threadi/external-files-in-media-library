<?php
/**
 * This file holds the setting functions for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Initialize the settings object.
 */
class Settings {
	/**
	 * List of tabs.
	 *
	 * @var array
	 */
	private array $tabs = array();

	/**
	 * Set the default tab.
	 *
	 * @var Tab|null
	 */
	private ?Tab $default_tab = null;

	/**
	 * The menu title.
	 *
	 * @var string
	 */
	private string $menu_title = '';

	/**
	 * The title.
	 *
	 * @var string
	 */
	private string $title = '';

	/**
	 * The menu slug.
	 *
	 * @var string
	 */
	private string $menu_slug = '';

	/**
	 * The parent menu slug.
	 *
	 * @var string
	 */
	private string $menu_parent_slug = 'options-general.php';

	/**
	 * The menu icon.
	 *
	 * @var string
	 */
	private string $menu_icon = '';

	/**
	 * The capability to show and edit settings.
	 *
	 * @var string
	 */
	private string $capability = 'manage_options';

	/**
	 * The callback for the menu.
	 *
	 * @var array
	 */
	private array $callback = array();

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
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the settings.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'rest_api_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Return list of tabs.
	 *
	 * @return array
	 */
	public function get_tabs(): array {
		$tabs = $this->tabs;

		/**
		 * Filter the list of setting tabs.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $tabs List of tabs.
		 * @param Settings $this The settings-object.
		 */
		return apply_filters( 'eml_settings_tabs', $tabs, $this );
	}

	/**
	 * Add tab with its settings for this setting object.
	 *
	 * @param string|Tab $tab The tab object or its internal name.
	 *
	 * @return false|Tab
	 */
	public function add_tab( string|Tab $tab ): false|Tab {
		// initialize the tab object value.
		$tab_obj = false;

		// if value is a string, create the tab object first.
		if ( is_string( $tab ) ) {
			$tab_obj = new Tab();
			$tab_obj->set_name( $tab );
		}

		// if value is a Tab object, use it.
		if ( $tab instanceof Tab ) {
			$tab_obj = $tab;
		}

		// bail if $tab_obj is not set.
		if ( ! $tab_obj instanceof Tab ) {
			return false;
		}

		// add the tab to the list of tabs of these settings.
		$this->tabs[] = $tab_obj;

		// return the tab object.
		return $tab_obj;
	}

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		$title = $this->title;

		/**
		 * Filter the title of settings object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $title The title.
		 * @param Settings $this The settings-object.
		 */
		return apply_filters( 'eml_settings_title', $title, $this );
	}

	/**
	 * Set the title.
	 *
	 * @param string $title The title.
	 *
	 * @return void
	 */
	public function set_title( string $title ): void {
		$this->title = $title;
	}

	/**
	 * Return the menu title.
	 *
	 * @return string
	 */
	public function get_menu_title(): string {
		$menu_title = $this->menu_title;

		/**
		 * Filter the menu title of settings object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $menu_title The menu title.
		 * @param Settings $this The settings-object.
		 */
		return apply_filters( 'eml_settings_title', $menu_title, $this );
	}

	/**
	 * Set the menu title.
	 *
	 * @param string $menu_title The menu title.
	 *
	 * @return void
	 */
	public function set_menu_title( string $menu_title ): void {
		$this->menu_title = $menu_title;
	}

	/**
	 * Return the slug.
	 *
	 * @return string
	 */
	public function get_menu_slug(): string {
		$menu_slug = $this->menu_slug;

		/**
		 * Filter the menu slug of settings object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $menu_slug The menu slug.
		 * @param Settings $this The settings-object.
		 */
		return apply_filters( 'eml_settings_menu_slug', $menu_slug, $this );
	}

	/**
	 * Set the slug.
	 *
	 * @param string $menu_slug The slug.
	 *
	 * @return void
	 */
	public function set_menu_slug( string $menu_slug ): void {
		$this->menu_slug = $menu_slug;
	}

	/**
	 * Add the menu in backend to show the settings there.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		global $submenu;

		// decide how to add the menu depending on given parent slug.
		switch ( $this->get_menu_parent_slug() ) {
			case 'options-general.php':
				add_options_page(
					$this->get_title(),
					$this->get_menu_title(),
					$this->get_capability(),
					$this->get_menu_slug(),
					$this->get_callback()
				);
				break;
			case 'admin.php':
				add_menu_page(
					$this->get_title(),
					$this->get_menu_title(),
					$this->get_capability(),
					$this->get_menu_slug(),
					$this->get_callback(),
					$this->get_menu_icon(),
					6
				);

				// check tabs for this setting whether they should be visible in menu.
				foreach ( $this->get_tabs() as $tab ) {
					// bail if tab is not Tab object.
					if ( ! $tab instanceof Tab ) {
						continue;
					}

					// bail if tab should not be visible in menu.
					if ( ! $tab->is_show_in_menu() ) {
						continue;
					}

					// add the menu item.
					add_submenu_page(
						$this->get_menu_slug(),
						$tab->get_title(),
						$tab->get_title(),
						$this->get_capability(),
						$tab->get_name(),
						$tab->get_callback()
					);

					// change link in menu if it is an external URL.
					if ( ! empty( $tab->get_url() ) ) {
						foreach ( $submenu as $main_slug => $main_menu ) {
							// bail if main slug is not our settings slug.
							if ( $main_slug !== $this->get_menu_slug() ) {
								continue;
							}

							foreach ( $main_menu as $index => $menu ) {
								// bail if this is not our menu.
								if ( $tab->get_name() !== $menu[2] ) {
									continue;
								}

								$submenu[ $main_slug ][ $index ][2] = $tab->get_url();
							}
						}
					}
				}
				break;
		}
	}

	/**
	 * Show the menu page.
	 *
	 * @return void
	 */
	public function display(): void {
		// bail on missing capabilities.
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		// set active tab.
		$active_tab = false;

		// get tab from request.
		$current_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_title() ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php
					// loop through the tabs.
				foreach ( $this->get_tabs() as $tab ) {
					// bail if tab is not Tab object.
					if ( ! $tab instanceof Tab ) {
						continue;
					}

					// ignore if tab should be a menu item.
					if ( $tab->is_show_in_menu() ) {
						continue;
					}

					// set additional classes.
					$classes = '';

					// check for current tab.
					if ( $tab->is_current() ) {
						$active_tab = $tab;
						$classes   .= ' nav-tab-active';
					} elseif ( is_null( $current_tab ) && $tab === $this->get_default_tab() ) {
						$active_tab = $tab;
						$classes   .= ' nav-tab-active';
					}

					if ( ! empty( $tab->get_tab_class() ) ) {
						$classes .= ' ' . sanitize_html_class( $tab->get_tab_class() );
					}

					// get URL for this tab.
					$url    = add_query_arg(
						array(
							'page' => $this->get_menu_slug(),
							'tab'  => $tab->get_name(),
						),
						get_admin_url() . $this->get_menu_parent_slug()
					);
					$target = $tab->get_url_target();
					if ( ! empty( $tab->get_url() ) ) {
						$url = $tab->get_url();
					}

					// output.
					?>
						<a href="<?php echo esc_url( $url ); ?>" target="<?php echo esc_attr( $target ); ?>" class="nav-tab<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $tab->get_title() ); ?></a>
						<?php
				}
				?>
			</nav>

			<div class="tab-content">
				<?php
				if ( $active_tab && is_callable( $active_tab->get_callback() ) ) {
					call_user_func( $active_tab->get_callback() );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Return the capability to show and change settings.
	 *
	 * @return string
	 */
	public function get_capability(): string {
		return $this->capability;
	}

	/**
	 * Set the capability to show and change settings.
	 *
	 * @param string $capability The capability.
	 *
	 * @return void
	 */
	public function set_capability( string $capability ): void {
		$this->capability = $capability;
	}

	/**
	 * Return the default tab.
	 *
	 * @return ?Tab
	 */
	public function get_default_tab(): ?Tab {
		return $this->default_tab;
	}

	/**
	 * Set the default tab.
	 *
	 * @param Tab $tab The tab for set as default tab.
	 *
	 * @return void
	 */
	public function set_default_tab( Tab $tab ): void {
		$this->default_tab = $tab;
	}

	/**
	 * Register settings of all tabs configured within this settings object.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// bail if no tabs are set.
		if ( ! $this->has_tabs() ) {
			return;
		}

		// get the tabs.
		$tabs = $this->get_tabs();

		// loop through the tabs and register their settings.
		$tab_count = count( $tabs );
		for ( $t = 0;$t < $tab_count; $t++ ) {
			// get the tab array entry.
			$tab = $tabs[ $t ];

			// bail if tab is not Tab.
			if ( ! $tab instanceof Tab ) {
				continue;
			}

			// get the sections of this tab.
			$sections = $tab->get_sections();

			if ( function_exists( 'add_settings_section' ) ) {
				// loop through the sections of this tab.
				$section_count = count( $sections );
				for ( $sec = 0; $sec < $section_count; $sec++ ) {
					// get the section array entry.
					$section = $sections[ $sec ];

					// bail if section is not Section.
					if ( ! $section instanceof Section ) {
						continue;
					}

					// add section.
					add_settings_section(
						$section->get_name(),
						$section->get_title(),
						$section->get_callback(),
						$tab->get_name()
					);
				}
			}

			// get the settings for this tab.
			$settings = $tab->get_settings();

			// loop through the settings.
			$settings_count = count( $settings );
			for ( $set = 0;$set < $settings_count;$set++ ) {
				// get the settings array entry.
				$setting = $settings[ $set ];

				// bail if setting is not Setting.
				if ( ! $setting instanceof Setting ) {
					continue;
				}

				// get the field object.
				$field = $setting->get_field();

				if ( function_exists( 'add_settings_field' ) && $setting->get_section() instanceof Section ) {
					// add the field for this setting.
					add_settings_field(
						$setting->get_name(),
						$field->get_title(),
						$field->get_callback(),
						$tab->get_name(),
						$setting->get_section()->get_name(),
						array(
							'setting' => $setting,
						)
					);
				}

				// register the setting.
				register_setting(
					$tab->get_name(),
					$setting->get_name(),
					array(
						'sanitize_callback' => $field->get_sanitize_callback(),
						'type'              => $setting->get_type(),
						'default'           => $setting->get_default(),
						'show_in_rest'      => $setting->is_show_in_rest(),
					)
				);

				// sanitize the option before any output.
				add_filter( 'option_' . $setting->get_name(), array( $this, 'sanitize_option' ), 10, 2 );

				// run custom callback before updating an option.
				if ( $setting->has_save_callback() ) {
					add_filter( 'pre_update_option_' . $setting->get_name(), $setting->get_save_callback(), 10, 2 );
				}
			}
		}
	}

	/**
	 * Return whether we have tabs.
	 *
	 * @return bool
	 */
	private function has_tabs(): bool {
		return ! empty( $this->get_tabs() );
	}

	/**
	 * Return menu icon.
	 *
	 * @return string
	 */
	public function get_menu_icon(): string {
		$menu_icon = $this->menu_icon;

		/**
		 * Filter the menu slug of settings object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $menu_icon The menu icon.
		 * @param Settings $this The settings-object.
		 */
		return apply_filters( 'eml_settings_menu_icon', $menu_icon, $this );
	}

	/**
	 * Set menu icon.
	 *
	 * @param string $menu_icon The menu icon. URL-path to file or dashicon-slug.
	 *
	 * @return void
	 */
	public function set_menu_icon( string $menu_icon ): void {
		$this->menu_icon = $menu_icon;
	}

	/**
	 * Return menu parent slug.
	 *
	 * @return string
	 */
	public function get_menu_parent_slug(): string {
		$parent_menu_slug = $this->menu_parent_slug;

		/**
		 * Filter the menu slug of settings object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $parent_menu_slug The parent menu slug.
		 * @param Settings $this The settings-object.
		 */
		return apply_filters( 'eml_settings_parent_menu_slug', $parent_menu_slug, $this );
	}

	/**
	 * Set menu icon.
	 *
	 * @param string $menu_parent_slug The parent menu slug.
	 *
	 * @return void
	 */
	public function set_menu_parent_slug( string $menu_parent_slug ): void {
		$this->menu_parent_slug = $menu_parent_slug;
	}

	/**
	 * Return the callback for the settings menu.
	 *
	 * @return array
	 */
	private function get_callback(): array {
		// if callback is empty use our default callback.
		if ( empty( $this->callback ) ) {
			return array( $this, 'display' );
		}

		// return the configured callback.
		return $this->callback;
	}

	/**
	 * Set the callback for the settings menu.
	 *
	 * @param array $callback The callback.
	 *
	 * @return void
	 */
	public function set_callback( array $callback ): void {
		// bail if given callback is not callable.
		if ( ! is_callable( $callback ) ) {
			return;
		}

		// set the callback.
		$this->callback = $callback;
	}

	/**
	 * Sanitize our own option values before output.
	 *
	 * @param mixed  $value The value.
	 * @param string $option The option-name.
	 *
	 * @return mixed
	 */
	public function sanitize_option( mixed $value, string $option ): mixed {
		// get field settings.
		$field_settings = $this->get_setting( $option );

		// bail if setting could not be found.
		if ( ! $field_settings ) {
			return $value;
		}

		// bail if no type is set.
		if ( empty( $field_settings->get_type() ) ) {
			return $value;
		}

		// bail if given type is not supported.
		if ( ! Helper::is_setting_type_valid( $field_settings->get_type() ) ) {
			return $value;
		}

		// if type is a string, secure for string.
		if ( 'string' === $field_settings->get_type() ) {
			return (string) $value;
		}

		// if type is a boolean, secure for boolean.
		if ( 'boolean' === $field_settings->get_type() ) {
			return (bool) $value;
		}

		// if type is an object, secure for object.
		if ( 'object' === $field_settings->get_type() ) {
			return (object) $value;
		}

		// if type is array, secure for array.
		if ( 'array' === $field_settings->get_type() ) {
			// if it is an array, use it 1:1.
			if ( is_array( $value ) ) {
				return $value;
			}

			// secure the value.
			return (array) $value;
		}

		// if type is int, secure value for int.
		if ( 'integer' === $field_settings->get_type() || 'number' === $field_settings->get_type() ) {
			return absint( $value );
		}

		// return the value.
		return $value;
	}

	/**
	 * Return the setting object for given setting by name.
	 *
	 * @param string $option The settings internal name.
	 *
	 * @return false|Setting
	 */
	private function get_setting( string $option ): false|Setting {
		foreach ( $this->get_tabs() as $tab ) {
			// bail if tab is not a Tab object.
			if ( ! $tab instanceof Tab ) {
				continue;
			}

			// check its settings.
			foreach ( $tab->get_settings() as $setting ) {
				// bail if tab is not a Tab object.
				if ( ! $setting instanceof Setting ) {
					continue;
				}

				// bail if setting has not the searched name.
				if ( $option !== $setting->get_name() ) {
					continue;
				}

				// return the object.
				return $setting;
			}
		}

		// return false if no setting with the given name could be found.
		return false;
	}

	/**
	 * Get just the settings from tabs of this settings object.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		// variable for list of settings.
		$settings = array();

		// loop through the tabs.
		foreach ( $this->get_tabs() as $tab ) {
			// bail if tab is not a Tab object.
			if ( ! $tab instanceof Tab ) {
				continue;
			}

			// check its settings.
			foreach ( $tab->get_settings() as $setting ) {
				// bail if tab is not a Tab object.
				if ( ! $setting instanceof Setting ) {
					continue;
				}

				// add the default values for each setting.
				$settings[] = $setting;
			}
		}

		// return resulting settings.
		return $settings;
	}

	/**
	 * Run this tasks during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {
		foreach ( $this->get_settings() as $setting ) {
			// bail if tab is not a Tab object.
			if ( ! $setting instanceof Setting ) {
				continue;
			}

			// bail if default value is empty.
			if ( ! $setting->is_default_set() ) {
				continue;
			}

			// bail if option is already set.
			if ( false !== get_option( $setting->get_name(), false ) ) {
				continue;
			}

			// add the option.
			add_option( $setting->get_name(), $setting->get_default(), '', true );
		}
	}

	/**
	 * Delete all settings.
	 *
	 * @return void
	 */
	public function delete_settings(): void {
		foreach ( $this->get_settings() as $setting ) {
			delete_option( $setting->get_name() );
		}
	}
}

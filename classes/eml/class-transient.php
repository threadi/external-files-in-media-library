<?php
/**
 * This file contains the handling of a single transient for this plugin in wp-admin.
 *
 * @package thread\eml
 */

namespace threadi\eml;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize a single transient-object.
 */
class Transient {
	/**
	 * The transient message.
	 *
	 * @var string
	 */
	private string $message = '';

	/**
	 * The internal name for this transient.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The transient type.
	 *
	 * @var string
	 */
	private string $type = '';

	/**
	 * Set the dismissible days.
	 *
	 * @var int
	 */
	private int $dismissible_days = 0;

	/**
	 * Action-callback-array.
	 *
	 * @var array
	 */
	private array $action = array();

	/**
	 * Constructor for this object.
	 *
	 * If $transient is given, fill the object with its data.
	 *
	 * @param string $transient The transient-name we use for this object.
	 */
	public function __construct( string $transient = '' ) {
		$this->set_name( $transient );
	}

	/**
	 * Get the message for this transient.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Set the message for this transient.
	 *
	 * @param string $message The text-message for the transient.
	 *
	 * @return void
	 */
	public function set_message( string $message ): void {
		$this->message = $message;
	}

	/**
	 * Save the transient in WP.
	 *
	 * @return void
	 */
	public function save(): void {
		// save the internal name to our own list of transients.
		Transients::get_instance()->add_transient( $this );

		// save the transient itself in WP.
		set_transient( $this->get_name(), $this->get_entry() );
	}

	/**
	 * Get the internal name of this transient.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Set the internal name of this transient.
	 *
	 * @param string $name The internal name for this transient.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Collect the entry for this transient.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		return array(
			'message'          => $this->get_message(),
			'type'             => $this->get_type(),
			'dismissible_days' => $this->get_dismissible_days(),
			'action'           => $this->get_action(),
		);
	}

	/**
	 * Check if this transient is set in WP.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function is_set(): bool {
		return null !== get_transient( $this->get_name() );
	}

	/**
	 * Output the content of this transient.
	 *
	 * @return void
	 */
	public function display(): void {
		// check if this transient is dismissed.
		if ( false === $this->is_transient_not_dismissed() ) {
			return;
		}

		// get the transients contents.
		$entry = get_transient( $this->get_name() );

		// bail if entry is empty.
		if ( empty( $entry ) || ! isset( $entry['message'] ) ) {
			return;
		}

		$this->set_message( $entry['message'] );
		$this->set_type( $entry['type'] );
		$this->set_dismissible_days( $entry['dismissible_days'] );
		$this->set_action( $entry['action'] );

		// output, if message is given.
		if ( $this->has_message() ) {
			?>
			<div class="eml-transient updated <?php echo esc_attr( $this->get_type() ); ?>" data-dismissible="<?php echo esc_attr( $this->get_name() ); ?>-<?php echo absint( $this->get_dismissible_days() ); ?>">
				<?php
				echo wp_kses_post( wpautop( $this->get_message() ) );
				if ( $this->get_dismissible_days() > 0 ) {
					?>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php echo esc_html__( 'Dismiss this notice.', 'external-files-in-media-library' ); ?></span></button>
					<?php
				}
				?>
			</div>
			<?php
		}

		// call action, if set.
		if ( $this->has_action() ) {
			$action = $this->get_action();
			if ( method_exists( $action[0], $action[1] ) ) {
				call_user_func( $action );
			}
		}

		// remove the transient.
		$this->delete();
	}

	/**
	 * Get the message-type.
	 *
	 * @return string
	 */
	private function get_type(): string {
		return $this->type;
	}

	/**
	 * Set the message-type.
	 *
	 * @param string $type The type of this transient (e.g. error).
	 *
	 * @return void
	 */
	public function set_type( string $type ): void {
		$this->type = $type;
	}

	/**
	 * Delete this transient from WP and our own list.
	 *
	 * This does not remove the dismiss-marker as it should be independent of the settings itself.
	 *
	 * @return void
	 */
	public function delete(): void {
		// delete from our own list.
		Transients::get_instance()->delete_transient( $this );

		// delete from WP.
		delete_transient( $this->get_name() );
	}

	/**
	 * Return whether this transient is dismissed (false) or not (true).
	 *
	 * @return bool
	 */
	private function is_transient_not_dismissed(): bool {
		// get value from cache, if set.
		$db_record = $this->get_admin_transient_dismiss_cache();

		// return bool depending on value.
		if ( 'forever' === $db_record || absint( $db_record ) >= time() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get transient-dismiss-cache.
	 *
	 * @return string|int|false
	 */
	private function get_admin_transient_dismiss_cache(): string|int|false {
		$cache_key = 'pi-dismissed-' . md5( $this->get_name() );
		$timeout   = get_option( $cache_key );
		$timeout   = 'forever' === $timeout ? time() + 60 : $timeout;

		if ( empty( $timeout ) || time() > $timeout ) {
			return false;
		}

		return $timeout;
	}

	/**
	 * Get the dismissible days.
	 *
	 * @return int
	 */
	private function get_dismissible_days(): int {
		return $this->dismissible_days;
	}

	/**
	 * Set the dismissible days.
	 *
	 * @param int $days The days for the dismissible-function.
	 *
	 * @return void
	 */
	public function set_dismissible_days( int $days ): void {
		$this->dismissible_days = $days;
	}

	/**
	 * Return the defined action for this transient.
	 *
	 * @return array
	 */
	private function get_action(): array {
		return $this->action;
	}

	/**
	 * Add an action to run. This is meant to be a callback as array like: array( 'class-name', 'function' );
	 *
	 * @param array $action The action as array.
	 * @return void
	 */
	public function set_action( array $action ): void {
		$this->action = $action;
	}

	/**
	 * Return whether this transient has a message set.
	 *
	 * @return bool
	 */
	private function has_message(): bool {
		return ! empty( $this->get_message() );
	}

	/**
	 * Return whether this transient has a action set.
	 *
	 * @return bool
	 */
	private function has_action(): bool {
		return ! empty( $this->get_action() );
	}
}

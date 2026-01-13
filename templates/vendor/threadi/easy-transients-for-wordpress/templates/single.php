<?php
/**
 * Template for single view of transient.
 *
 * @version 1.0.0
 * @package easy-transients-for-wordpress
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

?>
<div class="etfw-transient notice etfw-<?php echo esc_attr( $this->get_type() ); ?>" data-dismissible="<?php echo esc_attr( $this->get_name() ); ?>-<?php echo absint( $this->get_dismissible_days() ); ?>">
	<h3><?php echo wp_kses_post( Helper::get_logo_img() ); ?> <?php echo esc_html( apply_filters( 'personio_integration_light_transient_title', Helper::get_plugin_name() ) ); ?></h3>
	<?php
	echo wp_kses_post( wpautop( $this->get_message() ) );
	if ( $this->get_dismissible_days() > 0 ) {
		/* translators: %1$d will be replaced by the days this message will be hidden. */
		$title = sprintf( __( 'Hide this message for %1$d days.', 'personio-integration-light' ), $this->get_dismissible_days() );
		?>
		<button type="button" class="notice-dismiss" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html__( 'Dismiss', 'personio-integration-light' ); ?><span class="screen-reader-text"><?php echo esc_html( $title ); ?></span></button>
		<?php
	}
	?>
</div>
<?php

<?php
/**
 * Template to handle the output of YouTube videos.
 *
 * @param int $size_w The width to use.
 * @param int $size_h The height to use.
 * @param string $url The video-URL.
 *
 * @version: 3.0.0
 * @package external-files-in-media-library
 */

?><iframe width="<?php echo absint( $size_w ); ?>" height="<?php echo absint( $size_h ); ?>" src="<?php echo esc_url( $url ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

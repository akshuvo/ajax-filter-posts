<?php

/**
 *	Class Including
 */
require_once( dirname( __FILE__ ) . '/class-admin-notice.php' );

/**
 * Review Notice
 */
function am_post_grid_admin_notices($args){
	$args[] = array(
		'id' => "am_post_grid_review_notice",
		'text' => "<h2 style='margin: 0;color: #536dfe;'>Do you have some moment for me?</h2> <strong>We hope you're enjoying this plugin! Could you please give a 5-star rating on WordPress to inspire us? <span style='text-decoration: underline; color: #9E9E9E;'>[It won't take 1 minute]</span></strong>",
		'logo' => "https://ps.w.org/ajax-filter-posts/assets/icon-256x256.png",
		'border_color' => "#4f69f4",
		'is_dismissable' => "true",
		'dismiss_text' => "Dismiss",
		'buttons' => array(
			array(
				'text' => "Ok, you deserve it!",
				'link' => "https://wordpress.org/support/plugin/ajax-filter-posts/reviews/?filter=5#new-post",
				'target' => "_blank",
				'icon' => "dashicons dashicons-external",
				'class' => "button-primary",
			)
		)
	);

	return $args;
}
add_filter( 'addonmaster_admin_notice', 'am_post_grid_admin_notices' );
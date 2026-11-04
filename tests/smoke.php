<?php
/** Disposable WordPress smoke test for the installed release ZIP. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

foreach ( array( 'gridmaster', 'am_post_grid', 'asr_ajax' ) as $alias ) {
	if ( ! shortcode_exists( $alias ) ) {
		WP_CLI::error( 'Missing shortcode: ' . $alias );
	}
	$html = do_shortcode( '[' . $alias . ' posts_per_page="1"]' );
	if ( false === strpos( $html, 'am_ajax_post_grid_wrap' ) || false === strpos( $html, 'Hello world!' ) ) {
		WP_CLI::error( 'Grid did not render the fixture post: ' . $alias );
	}
}

if ( ! class_exists( 'Appsero\Client' ) || ! defined( 'GRIDMASTER_VERSION' ) ) {
	WP_CLI::error( 'Missing runtime dependency or bootstrap constant.' );
}

WP_CLI::success( 'Installed ZIP activated; all three shortcode aliases rendered; Appsero loaded.' );

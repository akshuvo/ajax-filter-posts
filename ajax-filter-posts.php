<?php 
/*
 Plugin Name: Ajax Filter Posts
 Plugin URI: http://asrcoder.com/plugins/ajax-filter-posts
 Author: mdsvuo
 Author URI: http://asrcoder.com
 Version:1.0
 Description: a simple WordPress plugin that helps you filter your post by category terms with Ajax
 */

//enqueue scripts
function asrafp_scripts(){

	wp_enqueue_style('asrafp-styles',plugin_dir_url( __FILE__ ).'assets/css/ajax-filter-post-stylesheet.css');

	wp_enqueue_script('jquery');
	wp_register_script( 'asr_ajax_filter_post', plugin_dir_url( __FILE__ ).'assets/js/ajax-filter-posts.js','jquery','1.0');
	wp_enqueue_script('asr_ajax_filter_post');

	wp_localize_script( 'asr_ajax_filter_post', 'asr_ajax_params', array(
        'asr_ajax_nonce' => wp_create_nonce( 'asr_ajax_nonce' ),
        'asr_ajax_url' => admin_url( 'admin-ajax.php' ),
    )
  );
}

add_action( 'wp_enqueue_scripts', 'asrafp_scripts' );

//shortcode function
function asrafp_shortcode_mapper(){
	$taxonomy = 'category';
	$terms = get_terms($taxonomy); // Get all terms of a taxonomy
	if ( $terms && !is_wp_error( $terms ) ){
		echo '<div class="asr-filter-div"><ul>';
        foreach( $terms as $term ) {
            echo '<li class="asr_texonomy" data_id="'.$term->term_id.'">'.$term->name.'</li>';
        }
        echo '</ul></div>';
    }
    $content = '
    <div class="asr-ajax-container">
	    <div class="asr-loader">
	    	<img src="'.plugin_dir_url( __FILE__ ).'assets/ajax-loader.gif'.'" alt="">
	    </div>
	    <div class="asrafp-filter-result"></div>
    </div>';

    echo $content;
}
add_shortcode( 'asr_ajax', 'asrafp_shortcode_mapper' );

//ajax actions
add_action('wp_ajax_asr_filter_posts', 'asrafp_ajax_functions');
add_action('wp_ajax_nopriv_asr_filter_posts', 'asrafp_ajax_functions');

//ajax main function
function asrafp_ajax_functions(){
	// Verify nonce
  	if( !isset( $_POST['asr_ajax_nonce'] ) || !wp_verify_nonce( $_POST['asr_ajax_nonce'], 'asr_ajax_nonce' ) )
    die('Permission denied');
	
	$term_ID = $_POST['term_ID'];

	//post query
	$query = new WP_Query( array(
		'post_type' => 'post',
		'post_per_pages' => -1,
		'tax_query' => array(
			array(
				'taxonomy' => 'category',
				'field' => 'term_id',
				'terms' => $term_ID,
			)
		)

	) );

	if( $query->have_posts() ): 
		while( $query->have_posts()): $query->the_post();

			$results = '<div class="asr-single-post">';
			$results .= '<a href="'.get_the_permalink().'"><h2>'.get_the_title().'</h2></a>';
			$results .= '<p>'.get_the_excerpt().'</p>';
			$results .= '</div>';

			echo $results;

		endwhile;
	else:
		echo __('<h2>No Posts Found</h2>','asr_td');
	endif;
	wp_reset_query();
	die();
}
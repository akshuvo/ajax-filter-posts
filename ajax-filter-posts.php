<?php
/**
 * Plugin Name:  Post Grid with Ajax Filter
 * Plugin URI:   http://asrcoder.com
 * Author:       Akhtarujjaman Shuvo
 * Author URI:   http://addonmaster.com/plugins/post-grid-with-ajax-filter
 * Version: 	  2.0.3
 * Description:  Post Grid with Ajax Filter plugin is a simple WordPress plugin that helps you filter your post by category terms with Ajax.
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  am_post_grid
 * Domain Path:  /lang
 */

/**
* Including Plugin file for security
* Include_once
* 
* @since 1.0.0
*/
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
* Loading Text Domain
*/
add_action('plugins_loaded', 'am_post_grid_plugin_loaded_action', 10, 2);
function am_post_grid_plugin_loaded_action() {
	load_plugin_textdomain( 'am_post_grid', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
}

//enqueue scripts
function asrafp_scripts(){
	//$ver = current_time( 'timestamp' );
	$ver = '2.0.2';

	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? : '.min';

	wp_enqueue_style( 'asrafp-styles', plugin_dir_url( __FILE__ ) . 'assets/css/post-grid-styles' . $suffix . '.css', null, $ver );

	wp_enqueue_script( 'jquery' );
	wp_register_script( 'asr_ajax_filter_post', plugin_dir_url( __FILE__ ) . 'assets/js/post-grid-scripts' . $suffix . '.js', 'jquery', $ver );
	wp_enqueue_script( 'asr_ajax_filter_post' );

	wp_localize_script( 'asr_ajax_filter_post', 'asr_ajax_params', array(
        'asr_ajax_nonce' => wp_create_nonce( 'asr_ajax_nonce' ),
        'asr_ajax_url' => admin_url( 'admin-ajax.php' ),
    )
  );
}

add_action( 'wp_enqueue_scripts', 'asrafp_scripts' );

//shortcode function
function asrafp_shortcode_mapper( $atts, $content = null ) { 
	$pppInit = ( get_option( 'posts_per_page', true ) ) ? get_option( 'posts_per_page', true ) : 9;

	$shortcode_atts = shortcode_atts(
            array(            	
                'show_filter' => "yes",
                'btn_all' => "yes",
                'initial' => "-1",
                'layout' => '1',
                'post_type' => 'post',
                'posts_per_page' => $pppInit,
                'cat' => 'all',
                'paginate' => 'no',
            ), 
            $atts
        );
	
    // Params extraction
    extract($shortcode_atts);

	ob_start();
	$taxonomy = 'category';
	$terms = get_terms($taxonomy);
	?>
	<div class="am_ajax_post_grid_wrap" data-am_ajax_post_grid='<?php echo json_encode($shortcode_atts);?>'>
	
		<?php if ( $show_filter == "yes" && $terms && !is_wp_error( $terms ) ){ ?>
			<div class="asr-filter-div" data-layout="<?php echo $layout; ?>"><ul>
				<?php if($btn_all != "no"): ?>
					<li class="asr_texonomy" data_id="-1"><?php echo esc_html('All','am_post_grid'); ?></li>
				<?php endif; ?>
		        <?php foreach( $terms as $term ) { ?>
		            <li class="asr_texonomy" data_id="<?php echo $term->term_id; ?>"><?php echo $term->name; ?></li>
		        <?php } ?>
	        </ul></div>
	    <?php } ?>
	    
	    <div class="asr-ajax-container">
		    <div class="asr-loader">
		    	<div class="lds-dual-ring"></div>
		    </div>
		    <div class="asrafp-filter-result"></div>
	    </div>
    </div>
	
	<?php return ob_get_clean();
}
add_shortcode('asr_ajax','asrafp_shortcode_mapper');
add_shortcode('am_post_grid','asrafp_shortcode_mapper');

//ajax actions
add_action('wp_ajax_asr_filter_posts', 'asrafp_ajax_functions');
add_action('wp_ajax_nopriv_asr_filter_posts', 'asrafp_ajax_functions');

//ajax main function
function asrafp_ajax_functions(){
	// Verify nonce
  	if( !isset( $_POST['asr_ajax_nonce'] ) || !wp_verify_nonce( $_POST['asr_ajax_nonce'], 'asr_ajax_nonce' ) )
    die('Permission denied');
	
	$term_ID = sanitize_text_field( intval($_POST['term_ID']) );
	$layout = intval($_POST['layout']);
	
	// Pagination
	if( $_POST['paged'] ) {
		$dataPaged = intval($_POST['paged']);
	} else {
		$dataPaged = get_query_var('paged') ? get_query_var('paged') : 1;
	}	

	$jsonData = json_decode( str_replace('\\', '', $_POST['jsonData']), true );	

	$data = array(
		'post_type' => 'post',
		'paged' => $dataPaged
	);

	if( $jsonData ){
		if( $jsonData['posts_per_page'] ){
			$data['posts_per_page'] = intval( $jsonData['posts_per_page'] );
		}
	}

	if( $term_ID != -1 ){
		$data['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field' => 'term_id',
				'terms' => $term_ID,
			)
		);
	}

	//post query
	$query = new WP_Query($data);
	ob_start();

	if( $query->have_posts() ): 
		echo "<div class='am_post_grid am__col-3 am_layout_{$layout}'>";
		while( $query->have_posts()): $query->the_post(); ?>

			<?php if($layout == 1){ ?>
			<div class="am_grid_col">
				<div class="am_single_grid">
					<div class="am_thumb">
						<?php the_post_thumbnail('full'); ?>
					</div>
					<div class="am_cont">		
						<a href="<?php echo get_the_permalink(); ?>"><h2 class="am__title"><?php echo get_the_title(); ?></h2></a>
						<div class="am__excerpt">
							<?php echo wp_trim_words( get_the_excerpt(), 15, null ); ?>
						</div>
						<a href="<?php echo get_the_permalink(); ?>" class="am__readmore"><?php echo esc_html__('Read More','am_post_grid');?></a>
					</div>
				</div>
			</div>
			<?php } else if( $layout == 2 ){ ?>
			<?php } ?>

		<?php endwhile; ?>
		</div>

		<div class="am_posts_navigation">
		<?php
			$big = 999999999; // need an unlikely integer
			$dataNext = $dataPaged+1;
			
			$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

			$paginate_links = paginate_links( array(
			    'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			    'format' => '?paged=%#%',
			    'current' => max( 1, $dataPaged ),
			    'prev_next' => false,
			    'mid_size' => 2,
			    'total' => $query->max_num_pages
			) );

			echo "<div id='am_posts_navigation_init'>";
				echo $paginate_links;
			echo "</div>";

			if( $paginate_links ){
				//echo "<button type='button' data-paged='{$dataPaged}' data-next='{$dataNext}'>Load More</button>";
			}
		?>
		</div>
		
		<?php
	else:
		esc_html_e('<h2>No Posts Found</h2>','am_post_grid');
	endif;
	wp_reset_query();
	echo ob_get_clean();
	die();
}



/**
 *	Admin Page
 */
require_once( dirname( __FILE__ ) . '/inc/admin-page/admin-page.php' );

/**
 *	Admin Notice
 */
require_once( dirname( __FILE__ ) . '/inc/class-admin-notice/admin-notices.php' );

/**
 * Add plugin action links.
 *
 * @since 1.0.0
 * @version 4.0.0
 */
function am_ajax_post_grid_plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="'.admin_url( 'admin.php?page=_woinstant' ).'">' . esc_html__( 'Shortcodes', 'am_post_grid' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}
//add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'am_ajax_post_grid_plugin_action_links' );


/**
 *	Plugin activation hook
 *
 */
function am_ajax_post_grid_activation_redirect( $plugin ) {
	if( $plugin == plugin_basename( __FILE__ ) ) {
	    // redirect option page after installed
	    wp_redirect( admin_url( 'admin.php?page=_woinstant' ) );
	    exit;
	}
}
//add_action( 'activated_plugin', 'am_ajax_post_grid_activation_redirect' );

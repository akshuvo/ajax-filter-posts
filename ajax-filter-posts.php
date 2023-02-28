<?php
/**
 * Plugin Name:  Post Grid Ajax
 * Plugin URI:   http://addonmaster.com
 * Author:       AddonMaster 
 * Author URI:   http://addonmaster.com/plugins/post-grid-with-ajax-filter
 * Version: 	 3.3.0
 * Description:  Post Grid with Ajax Filter helps you filter your posts by category terms with Ajax. Infinite scroll function included.
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  ajax-filter-posts
 * Domain Path:  /lang
 */

/**
* Including Plugin file for security
* Include_once
*
* @since 1.0.0
*/
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

// Defines
define('AM_POST_GRID_VERSION', '3.3.0');

/**
* Loading Text Domain
*/
add_action('plugins_loaded', 'am_post_grid_plugin_loaded_action', 10, 2);
function am_post_grid_plugin_loaded_action() {
	load_plugin_textdomain( 'ajax-filter-posts', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
}


/**
 *	Admin Page
 */
//require_once( dirname( __FILE__ ) . '/inc/admin/admin-page.php' );


// Enqueue scripts
function am_ajax_filter_posts_scripts(){

	// CSS File
	wp_enqueue_style( 'asrafp-styles', plugin_dir_url( __FILE__ ) . 'assets/css/post-grid-styles.css', null, AM_POST_GRID_VERSION );

	// JS File
	wp_register_script( 'asr_ajax_filter_post', plugin_dir_url( __FILE__ ) . 'assets/js/post-grid-scripts.js', array('jquery'), AM_POST_GRID_VERSION );
	wp_enqueue_script( 'asr_ajax_filter_post' );

	// Localization
	wp_localize_script( 'asr_ajax_filter_post', 'asr_ajax_params', array(
	        'asr_ajax_nonce' => wp_create_nonce( 'asr_ajax_nonce' ),
	        'asr_ajax_url' => admin_url( 'admin-ajax.php' ),
	    )
	);
}

add_action( 'wp_enqueue_scripts', 'am_ajax_filter_posts_scripts' );

//shortcode function
function am_post_grid_shortcode_mapper( $atts, $content = null ) {

	// Posts per pages.
	$posts_per_page = ( get_option( 'posts_per_page', true ) ) ? get_option( 'posts_per_page', true ) : 9;

	// Default attributes
	$shortcode_atts = shortcode_atts(
            array(
                'show_filter' 		=> "yes",
                'btn_all' 			=> "yes",
                'initial' 			=> "-1",
                'layout' 			=> '1',
                'post_type' 		=> 'post',
                'posts_per_page' 	=> $posts_per_page,
                'cat' 				=> '',
                'terms' 			=> '',
                'paginate' 			=> 'no',
                'hide_empty' 		=> 'true',
                'orderby' 			=> 'menu_order date', //Display posts sorted by ‘menu_order’ with a fallback to post ‘date’
    			'order'   			=> 'DESC',
    			'pagination_type'   => '',
    			'infinite_scroll'   => '',
    			'animation'  		=> '',
    			'grid_id'  			=> '', // master ID
            ),
            $atts
        );

    // Params extraction
    extract($shortcode_atts);

	ob_start();

	// Texonomy arguments
	$taxonomy = 'category';
	$args = array(
		'hide_empty' => $hide_empty,
	    'taxonomy' => $taxonomy,
	    'include' => $terms ? $terms : $cat,
	);

	// Get category terms
	$terms = get_terms($args); ?>
	<div class="am_ajax_post_grid_wrap" data-pagination_type="<?php echo esc_attr($pagination_type); ?>" data-am_ajax_post_grid='<?php echo json_encode($shortcode_atts);?>'>

		<?php if ( $show_filter == "yes" && $terms && !is_wp_error( $terms ) ){ ?>
			<div class="asr-filter-div" data-layout="<?php echo $layout; ?>"><ul>
				<?php if($btn_all != "no"): ?>
					<li class="asr_texonomy" data_id="-1"><?php echo esc_html('All','ajax-filter-posts'); ?></li>
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
		    <div class="asrafp-filter-result">
		    	<?php echo am_post_grid_output( am_post_grid_get_args_from_shortcode_atts($shortcode_atts) ); ?>
		    </div>
	    </div>
    </div>

	<?php return ob_get_clean();
}
add_shortcode('asr_ajax','am_post_grid_shortcode_mapper');
add_shortcode('am_post_grid','am_post_grid_shortcode_mapper');

// Get Args from Json Data
function am_post_grid_get_args_from_shortcode_atts( $jsonData ){

	if( isset( $jsonData['posts_per_page'] ) ){
		$data['posts_per_page'] = intval( $jsonData['posts_per_page'] );
	}

	if( isset( $jsonData['orderby'] ) ){
		$data['orderby'] = sanitize_text_field( $jsonData['orderby'] );
	}

	if( isset( $jsonData['order'] ) ){
		$data['order'] = sanitize_text_field( $jsonData['order'] );
	}

	if( isset( $jsonData['pagination_type'] ) ){
		$data['pagination_type'] = sanitize_text_field( $jsonData['pagination_type'] );
		
	}

	if( isset( $jsonData['animation'] ) && $jsonData['animation'] == "true" ){
		$data['animation'] = 'am_has_animation';
	}

	if( isset( $jsonData['infinite_scroll'] ) && $jsonData['infinite_scroll'] == "true" ){
		$data['infinite_scroll'] = 'infinite_scroll';
	}

	// Bind to Category Terms
	$terms =  '';
	if ( isset( $jsonData['cat'] ) && !empty( $jsonData['cat'] ) ) {
		$terms = explode(',', $jsonData['cat']);
	} elseif ( isset( $jsonData['terms'] ) && !empty( $jsonData['terms'] ) ) {
		$terms = explode(',', $jsonData['terms']);
	}

	
	// Tax Query
	if ( !empty( $terms ) ) {
		$data['tax_query'] = [
			'category' => $terms,
		];
	}

	return $data;
}

// Load Posts Ajax actions
add_action('wp_ajax_asr_filter_posts', 'am_post_grid_load_posts_ajax_functions');
add_action('wp_ajax_nopriv_asr_filter_posts', 'am_post_grid_load_posts_ajax_functions');

// Load Posts Ajax function
function am_post_grid_load_posts_ajax_functions(){
	// Verify nonce
  	// if( !isset( $_POST['asr_ajax_nonce'] ) || !wp_verify_nonce( $_POST['asr_ajax_nonce'], 'asr_ajax_nonce' ) )
    // die('Permission denied');

    $data = [];

	$term_ID = isset( $_POST['term_ID'] ) ? sanitize_text_field( intval($_POST['term_ID']) ) : '';

	// Pagination
	if( $_POST['paged'] ) {
		$dataPaged = intval($_POST['paged']);
	} else {
		$dataPaged = get_query_var('paged') ? get_query_var('paged') : 1;
	}

	$jsonData = json_decode( str_replace('\\', '', $_POST['jsonData']), true );
	
	// Merge Json Data
	$data = array_merge( am_post_grid_get_args_from_shortcode_atts( $jsonData ), $data );

	// Current Page
	if ( isset( $_POST['paged'] ) ) {
		$data['paged'] = sanitize_text_field( $_POST['paged'] );
	}

	// Selected Category
	if ( !empty( $term_ID ) && $term_ID != -1 ) {
		$data['tax_query'] = [
			'category' => [$term_ID],
		];
	}

	// Output
	echo am_post_grid_output( $data );

	die();
}

// Post Grid
function am_post_grid_output( $args = [] ){

	// Parse Args
	$args = wp_parse_args( $args, [
		'post_type' => 'post',
		'post_status' => 'publish',
		'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
		'posts_per_page' => '',
		'orderby' => '',
		'order' => '',
		'layout' => '1',
		'pagination_type' => '',
		'animation' => '',
		'infinite_scroll' => '',
		'tax_query' => [
			'category' => []
		],
	]);

	// Post Query Args
	$query_args = array(
		'post_type' => 'post',
		'post_status' => 'publish',
		'paged' => $args['paged'],
	);


	// If json data found
	if ( !empty( $args['posts_per_page'] ) ) {
		$query_args['posts_per_page'] = intval( $args['posts_per_page'] );
	}

	if ( !empty( $args['orderby'] ) ) {
		$query_args['orderby'] = sanitize_text_field( $args['orderby'] );
	}

	if ( !empty( $args['order'] ) ) {
		$query_args['order'] = sanitize_text_field( $args['order'] );
	}
	

	// Pagination Type
	$pagination_type = sanitize_text_field( $args['pagination_type'] );
	$dataPaged = sanitize_text_field( $args['paged'] );


	// Tax Query Var
	$tax_query = [];

	// Check if has terms 
	if( !empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
		foreach ( $args['tax_query'] as $taxonomy => $terms ) {
			if ( !empty( $terms ) ) {
				$tax_query[] =[
					'taxonomy' => $taxonomy,
					'field' => 'term_id',
					'terms' => $terms,
				];
			}
			
		}
	}

	// Tax Query
	if ( !empty( $tax_query ) ) {
		$query_args['tax_query'] = $tax_query;
	}
	

	//post query
	$query = new WP_Query( $query_args );
	ob_start();

	// Wrap with a div when infinity load
	echo ( $pagination_type == 'load_more' ) ? '<div class="am-postgrid-wrapper">' : '';

	// Start posts query
	if( $query->have_posts() ): ?>

		<div class="<?php echo esc_attr( "am_post_grid am__col-3 am_layout_{$args['layout']} {$args['animation']} " ); ?>">
		
		<?php while( $query->have_posts()): $query->the_post(); ?>

			<?php if($args['layout'] == "1"){ ?>
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
						<a href="<?php echo get_the_permalink(); ?>" class="am__readmore"><?php echo esc_html__('Read More','ajax-filter-posts');?></a>
					</div>
				</div>
			</div>
			<?php } else if( $args['layout'] == 2 ){ ?>
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

			// Load more button
			if( $pagination_type == 'load_more' ){

				if( $paginate_links && $dataPaged < $query->max_num_pages ){
					echo "<button type='button' data-paged='{$dataPaged}' data-next='{$dataNext}' class='{$args['infinite_scroll']} am-post-grid-load-more'>" . esc_html__( 'Load More', 'ajax-filter-posts' )."</button>";
				}

			} else {

				// Paginate links
				echo "<div id='am_posts_navigation_init'>{$paginate_links}</div>";
			}

		?>
		</div>

		<?php
	else:
		esc_html_e('No Posts Found','ajax-filter-posts');
	endif;
	wp_reset_query();

	// Wrap close when infinity load
	echo ( $pagination_type == 'load_more' ) ? '</div>' : '';

	// Echo the results
	return ob_get_clean();
}


/**
 * Add plugin action links.
 *
 * @since 1.0.0
 * @version 4.0.0
 */
function am_ajax_post_grid_plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="'.admin_url( 'admin.php?page=ajax-post-grid' ).'">' . esc_html__( 'Options', 'ajax-filter-posts' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}
//add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'am_ajax_post_grid_plugin_action_links' );


/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_ajax_filter_posts() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
      require_once __DIR__ . '/appsero/src/Client.php';
    }

	$client = new Appsero\Client( 'dc1dc5f0-8c32-4208-b217-b8b1a1a0b85f', 'Post Grid Ajax', __FILE__ );

    // Active insights
    $client->insights()->init();

}

appsero_init_tracker_ajax_filter_posts();

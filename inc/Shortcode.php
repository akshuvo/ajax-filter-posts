<?php
namespace GridMaster;

class Shortcode {

    /**
     * Class constructor
     */
    function __construct() {
        // Shortcodes
        add_shortcode( 'gridmaster', [ $this, 'render_shortcode' ] );
        add_shortcode( 'am_post_grid', [ $this, 'render_shortcode' ] );
        add_shortcode( 'asr_ajax', [ $this, 'render_shortcode' ] );

                
        // Load Posts Ajax actions
        add_action('wp_ajax_asr_filter_posts', [ $this, 'am_post_grid_load_posts_ajax_functions' ]);
        add_action('wp_ajax_nopriv_asr_filter_posts', [ $this, 'am_post_grid_load_posts_ajax_functions' ]);

        // Filter Gridmaster Render Grid Args
        add_filter('gridmaster_render_grid_args', [ $this, 'filter_render_grid_args' ], 10 );

        add_action( 'init', [ $this, 'init_hook' ] );
        add_action( 'gridmaster_render_filter', [ $this, 'render_filter' ] );
    }

    /**
     * Initializes a singleton instance
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }
    
    /**
     * Render Dynamic Stylesheets
     */
    public function render_styles( $args = [] ){
        $args = wp_parse_args( $args, [
            'grid_style' => ''
        ]);

        // Grid Style
        $grid_style = $args['grid_style'];

        // Enqueue Styles
        if( defined( 'GRIDMASTER_PRO_PATH' ) && file_exists( GRIDMASTER_PRO_PATH . '/assets/css/' . $grid_style . '.css' ) ) {
            wp_enqueue_style( 'gridmaster-frontends-' . $grid_style, GRIDMASTER_PRO_ASSETS_URL . '/css/' . $grid_style . '.css', array(), GRIDMASTER_VERSION );
        } elseif( file_exists( GRIDMASTER_PATH . '/assets/css/' . $grid_style . '.css' ) ) {
            wp_enqueue_style( 'gridmaster-frontends-' . $grid_style, GRIDMASTER_ASSETS . 'css/' . $grid_style . '.css', array(), GRIDMASTER_VERSION );
        }

    }

    /**
     * Render the shortcode
     *
     * @param array $atts
     * @param string $content
     *
     * @return void
     */
    public function render_shortcode( $atts, $content = null ) {
        
        // Check if GridMaster Pro is not installed and current user is admin
        if( !gridmaster_is_pro() && current_user_can( 'manage_options' ) && isset( $atts['post_type_selection'] ) && $atts['post_type_selection'] == 'auto' ) {
            echo '<div class="gm-admin-notice">' . sprintf( __( '<strong>Admin Notice:</strong> You need to upgrade to <a href="%s" target="_blank">GridMaster Pro</a> in order to use <strong>Advanced Post Type Selection</strong> feature.', 'gridmaster' ), GRIDMASTER_PRO_LINK ) . '</div>';
        }

        $atts = shortcode_atts( [
            'id' => '',
            'post_type' => 'post',
            'posts_per_page' => 9,
            'orderby' => 'menu_order date', //Display posts sorted by ‘menu_order’ with a fallback to post ‘date’
            'order' => 'DESC',
            'tax_query' => [],
            'meta_query' => [],
            'title_length' => 50,
            'content_from' => 'excerpt',
            'excerpt_length' => 15,
            'show_read_more' => 'yes',
            'read_more_text' => '',
            // START OLD ATTRIBUTES
            'show_filter' 		=> "yes",
            'btn_all' 			=> "yes",
            // 'initial' 			=> "-1",
            'cat' 				=> '',
            'paginate' 			=> 'no',
            'hide_empty' 		=> 0,
            'pagination_type'   => '',
            'infinite_scroll'   => '',
            'animation'  		=> '',
            // END OLD ATTRIBUTES
            'grid_style'  		=> 'default', // master ID
            'grid_id'  			=> wp_generate_password( 8, false ), // grid ID
            'taxonomy'  		=> 'category',
            'terms'  			=> '',
            'grid_image_size'   => 'full',
            'filter_style'  	=> 'default',

        ], $atts, 'gridmaster' );

        $id = $atts['id'];

        if ( !empty( $id ) ) {
            // Get args from the database
            // $atts = get_post_meta( $id, 'gridmaster_args', true );
        }

        // Grid Style
        $grid_style = $atts['grid_style'];

        // $atts['tax_query'] = ! empty( $atts['tax_query'] ) ? json_decode( $atts['tax_query'], true ) : [];

        // If id is set then get args from the database and render the grid
        // Otherwise render the grid from the shortcode attributes

        // Render dynamic styles
        $this->render_styles([
            'grid_style' => $grid_style
        ]);

        // echo '<pre>';
        // print_r($atts);
        // echo '</pre>';


        extract($atts);

        // Grid ID 
        $atts['grid_id'] = 'gridmaster-' . $atts['grid_id'];

        // Grid ID
        $grid_id = $atts['grid_id'];

        // Pagination
        $pagination_type = $atts['pagination_type'];

        // Public Attributes
        $public_atts = apply_filters( 'gridmaster_shortcode_public_atts', $atts );

        // Unset for Public Attributes
        unset( $public_atts['grid_col_gap'] );
        unset( $public_atts['grid_row_gap'] );
        unset( $public_atts['grid_item_per_row'] );
        unset( $public_atts['tax_query'] );
        unset( $public_atts['meta_query'] );
        // unset( $public_atts['grid_style'] ); // Didn't know why put this here
        unset( $public_atts['filter_style'] );


        ob_start();   
        ?>
        <div id="<?php echo esc_attr($grid_id); ?>" 
            data-grid-style="<?php echo esc_attr( $grid_style ); ?>"
            class="am_ajax_post_grid_wrap <?php echo esc_attr($grid_id); ?> <?php echo esc_attr( 'gridmaster-'.$grid_style ); ?>" 
            data-pagination_type="<?php echo esc_attr($pagination_type); ?>" 
            data-am_ajax_post_grid='<?php echo wp_json_encode($public_atts);?>'>

            <?php do_action( 'gridmaster_render_filter', $atts ); ?>

            <div class="asr-ajax-container">
                <div class="asr-loader">
                    <div class="lds-dual-ring"></div>
                </div>
                <div class="asrafp-filter-result">
                    <?php echo $this->render_grid( $this->get_args_from_atts($atts) ); ?>
                </div>
            </div>
        </div>
    
        <?php 
        $output = ob_get_clean();

        return apply_filters( 'gridmaster_shortcode_output', $output, $atts );
    }

    /**
     * Render Filter
     *
     * @param array $args
     *
     * @return string
     */
    function render_filter( $atts ){
        // echo '<pre>'; print_r( $atts ); echo '</pre>';

        if ( $atts['show_filter'] == "yes" ) :  ?>
            <div class="asr-filter-div">
                <?php
                // Texonomy arguments
                $tax_args = array(
                    'hide_empty' => $atts['hide_empty'],
                    'taxonomy' => $atts['taxonomy'],
                    'include' => $atts['terms'] ? $atts['terms'] : $atts['cat'],
                );

                // If not pro
                if( !gridmaster_is_pro() && $tax_args['taxonomy'] != 'category' && !empty( $tax_args['include'] ) ){
                    if( current_user_can( 'manage_options' ) ){
                        echo '<div class="gm-admin-notice">' . sprintf( __( '<strong>Admin Notice:</strong> You need to upgrade to <a href="%s" target="_blank">GridMaster Pro</a> in order to use custom taxonomy filters with selected terms.', 'gridmaster' ), GRIDMASTER_PRO_LINK ) . '</div>';
                    }
                    return;
                }

                // Filter arguments
                $filter_args = [
                    // 'atts' => $atts,
                    'tax_args' => $tax_args,
                    'grid_id' => $atts['grid_id'],
                    'btn_all' => $atts['btn_all'],
                    'filter_style' => $atts['filter_style'],
                    'input_type' => apply_filters( 'gridmaster_filter_input_type', 'radio', $atts ),
                ];
                ?>
                <?php $this->get_template_part( $atts['filter_style'], 'filter', $filter_args ); ?>
                
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Get args from shortcode attributes
     * Trigger when no ajax, default load
     *
     * @param array $atts
     *
     * @return array
     */
    function get_args_from_atts( $jsonData ){

        $data = wp_parse_args( $jsonData, [
            'post_type' => 'post',
        ] );

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
            
        // Tax Query: Default Load
        if ( !empty( $terms ) ) {
            $data['has_terms'] = true;
            $data['tax_query'] = [
                'category' => $terms,
            ];
        }
    
        return apply_filters( 'gridmaster_get_args_from_atts', $data );
    }

        
    // Load Posts Ajax function
    function am_post_grid_load_posts_ajax_functions(){
        // Verify nonce
        // if( !isset( $_POST['asr_ajax_nonce'] ) || !wp_verify_nonce( $_POST['asr_ajax_nonce'], 'asr_ajax_nonce' ) )
        // die('Permission denied');

        $data = [];

        $term_ID = isset( $_POST['term_ID'] ) ? sanitize_text_field( intval($_POST['term_ID']) ) : '';


        // Pagination
        if ( isset( $_POST['paged'] ) ) {
            $dataPaged = intval($_POST['paged']);
        } else {
            $dataPaged = get_query_var('paged') ? get_query_var('paged') : 1;
        }

        $argsArray = isset( $_POST['argsArray'] ) ? $_POST['argsArray'] : [];
        // Merge Json Data
        $data = array_merge( $this->get_args_from_atts( $argsArray ), $data );
        
  

        // Current Page
        if ( isset( $_POST['paged'] ) ) {
            $data['paged'] = sanitize_text_field( $_POST['paged'] );
        }

        // Selected Category: Older way
        if ( !empty( $term_ID ) && $term_ID != -1 ) {
            $data['tax_query'] = [
                'category' => [$term_ID],
            ];
        }
        
        // Tax Input: Skip old way, Proceed New way
        $taxInput = [];
        if( isset( $_POST['taxInput'] ) ) {
            parse_str( $_POST['taxInput'], $taxInput );
        }
        if ( !empty( $taxInput ) ) {
            $data = array_merge( $data, $taxInput );
        }

        // Ajax Flag
        $data['ajax'] = true;
        
        // Output
        echo $this->render_grid( $data );

        die();
    }

    /**
     * Render the grid with args
     *
     * @param array $args
     *
     * @return void
     */
    public function filter_render_grid_args( $args ) {
        $all_terms = isset( $args['terms'] ) ? array_filter( explode(',', $args['terms'])) : [];
        
        // Tax Query
        if ( isset( $args['tax_input'] ) && !empty( $args['tax_input'] ) ) {
            $tax_query = [];
            foreach( $args['tax_input'] as $taxonomy => $terms ) {

                // Check if array
                if ( is_array( $terms ) ) {
                    $terms = isset( $terms[0] ) ? [$terms[0]] : [];
                }

                // Continue if terms is empty or -1 in array
                if ( empty( $terms ) || in_array( "-1", $terms ) ) {
                    if ( !empty( $all_terms ) ) {
                        $tax_query[] = [
                            'taxonomy' => 'category',
                            'field' => 'term_id',
                            'terms' => $all_terms,
                        ];
                    }
                    
                    continue;
                }

                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $terms,
                ];
                break;
            }
            $args['tax_query'] = $tax_query;
            $args['has_tax_query'] = true;

            // Unset Tax Input
            if( !function_exists( 'GridMasterProPlugin' ) ) {
                unset( $args['tax_input'] );
            }

        }

        return $args;
    }

    /**
     * Render the grid
     *
     * @param array $args
     *
     * @return void
     */
    public function render_grid( $args ) {

        // Parse Args
        $args = wp_parse_args( $args, [
            'post_type' => 'post',
            'post_status' => 'publish',
            'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
            'posts_per_page' => '4',
            'orderby' => '',
            'order' => '',
            'grid_style' => 'default',
            'pagination_type' => '',
            'animation' => '',
            'infinite_scroll' => '',
            'tax_query' => [
                'category' => []
            ],
            'has_tax_query' => false,
            // Old Args
            'taxonomy' => '',
            'terms' => '',
        ]);
      
        // Excerpt Length Filter
        add_filter( 'gridmaster_excerpt_length', function( $length ) use ( $args ) {
            return intval( $args['excerpt_length'] );
        } );

        // Thumbnail size filter
        add_filter( 'gridmaster_post_thumb_size', function( $thumb_size ) use ( $args ) {
            return sanitize_text_field( $args['grid_image_size'] );
        } );
        
        // Render Args Apply Filter
        $args = apply_filters( 'gridmaster_render_grid_args', $args );

        // Get render grid args filter
        add_filter( 'gridmaster_get_render_grid_args', function( $arguments ) use ( $args ) {
            return $args;
        } );

        // Post Query Args
        $query_args = array(
            'post_type' => $args['post_type'],
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
        $dataPaged = intval( $args['paged'] );

        // Grid Style
        $grid_style = sanitize_text_field( $args['grid_style'] );

        // Tax Query
        if ( $args['has_tax_query'] ) { // Already has tax query so add it to query args
            $query_args['tax_query'] = $args['tax_query'];
        } elseif ( !empty( $args['tax_query'] ) && !$args['has_tax_query'] ) {

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

            $query_args['tax_query'] = $tax_query;
        } 

        // Apply Filter for query args
        $query_args = apply_filters( 'gridmaster_render_grid_query_args', $query_args, $args );

        //post query
        $query = new \WP_Query( $query_args );
        ob_start();

        // Wrap with a div when infinity load
        echo ( $pagination_type == 'load_more' ) ? '<div class="am-postgrid-wrapper">' : '';

        // Start posts query
        if( $query->have_posts() ): ?>

            <div class="<?php echo esc_attr( "am_post_grid am__col-3 am_layout_1 {$args['animation']} " ); ?>">
            
            <?php while( $query->have_posts()): $query->the_post(); ?>

                <?php $this->get_template_part( $grid_style, 'grid' ); ?>

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
                    'prev_next' => true,
                    'mid_size' => 2,
                    'total' => $query->max_num_pages,
                    'type' => 'plain',
                    'prev_text' => '«',
                    'next_text' => '»',
                ) );


                // Load more button
                if( $pagination_type == 'load_more' ){

                    if( $paginate_links && $dataPaged < $query->max_num_pages ){
                        echo "<button type='button' data-paged='{$dataPaged}' data-next='{$dataNext}' class='{$args['infinite_scroll']} am-post-grid-load-more'>" . esc_html__( 'Load More', 'gridmaster' )."</button>";
                    }

                } else {

                    // Paginate links
                    echo "<div class='am_posts_navigation_init'>{$paginate_links}</div>";
                }

            ?>
            </div>

        <?php else:
            esc_html_e('No Posts Found','gridmaster');
        endif;
        wp_reset_query();

        // Wrap close when infinity load
        echo ( $pagination_type == 'load_more' ) ? '</div>' : '';

        // Echo the results
        return ob_get_clean();
    }

    /**
     * Get template part (for templates like the loop).
     *
     * @access public
     * @param mixed $slug
     * @param string $name (default: '')
     * @param array $args (default: array())
     * @return void 
     */
    function get_template_part( $slug, $name = '', $args = array() ) {

        $templates = array();
        if( !empty( $name ) ) {
            $templates[] = "{$name}/{$slug}.php";
        } else {
            $templates[] = "{$slug}.php";
        }

        if ( ! $this->locate_template( $templates, true, false, $args ) ) {
            return false;
        }
    }

    /**
     * Locate a template and return the path for inclusion.
     * @access public
     * @param mixed $template_names
     * @param bool $load (default: false)
     * @param bool $require_once (default: true)
     * @param array $args (default: array())
     * @return string
     */
    function locate_template( $template_names, $load = false, $require_once = true, $args = array() ) {
        $template_names = (array) $template_names;
        $located = '';
        foreach ( $template_names as $template_name ) {
            if ( ! $template_name ) {
                continue;
            }

            // Load from child theme if child theme is active
            if ( defined( 'GRIDMASTER_PRO_PATH' ) && file_exists( get_stylesheet_directory() . '/gridmaster/templates/' . $template_name ) ) {
                $located = get_stylesheet_directory() . '/gridmaster/templates/' . $template_name;
                break;
            } elseif ( defined( 'GRIDMASTER_PRO_PATH' ) && file_exists( GRIDMASTER_PRO_PATH . '/templates/' . $template_name ) ) {
                $located = GRIDMASTER_PRO_PATH . '/templates/' . $template_name;
                break;
            } elseif ( file_exists( GRIDMASTER_PATH . '/templates/' . $template_name ) ) {
                $located = GRIDMASTER_PATH . '/templates/' . $template_name;
                break;
            }
        }

        // Filter for locating template file
        $located = apply_filters( 'gm_locate_template', $located, $template_names, $args );

        if ( $load && '' != $located ) {
            load_template( $located, $require_once, $args );
        }
        return $located;
    }

    // Init Hook
    public function init_hook() {
        // Return if admin
        if ( is_admin() ) {
            return;
        }

        // Shortcode for frontend
        if ( isset( $_GET['gm_shortcode_preview'] ) && $_GET['gm_shortcode_preview'] == 1 ) {
            // Disable admin bar
            add_filter( 'show_admin_bar', '__return_false' );

            $shortcode = isset( $_GET['shortcode'] ) ? $_GET['shortcode'] : '';
            // Remove BackSlash
            $shortcode = wp_unslash( $shortcode );
            ?>
            <!-- Blank HTML Template  -->
            <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta http-equiv="X-UA-Compatible" content="IE=edge">
                    <?php wp_head(); ?>
                    <style>
                        html body {
                            margin: 0;
                            padding: 16px;
                            background: transparent;
                        }
                    </style>
                </head>
                <body>
                    <?php echo do_shortcode( $shortcode ); ?>
                    <?php wp_footer(); ?>
                </body>
            </html>
            <?php die();
        }
    }
}
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
        if( file_exists( GRIDMASTER_PRO_ASSETS_DIR . '/assets/style-1.css' ) ) {
            wp_enqueue_style( 'gridmaster-frontends', GRIDMASTER_PRO_ASSETS_URL . '/' . $grid_style . '.css', array(), GRIDMASTER_VERSION );
        } elseif( file_exists( GRIDMASTER_PATH . '/assets/style-1.css' ) ) {
            wp_enqueue_style( 'gridmaster-frontends', GRIDMASTER_ASSETS . '/' . $grid_style . '.css', array(), GRIDMASTER_VERSION );
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
        $atts = shortcode_atts( [
            'id' => '',
            'post_type' => 'post',
            'posts_per_page' => 9,
            'orderby' => 'menu_order date', //Display posts sorted by ‘menu_order’ with a fallback to post ‘date’
            'order' => 'DESC',
            'tax_query' => [],
            'meta_query' => [],
            'columns' => 3,
            'column_gap' => 20,
            'row_gap' => 20,
            'image_size' => 'large',
            'image_position' => 'top',
            'image_height' => 200,
            'title_tag' => 'h2',
            'title_length' => 50,
            'excerpt_length' => 100,
            // START OLD ATTRIBUTES
            'show_filter' 		=> "yes",
            'btn_all' 			=> "yes",
            'initial' 			=> "-1",
            'layout' 			=> '1',
            'cat' 				=> '',
            'paginate' 			=> 'no',
            'hide_empty' 		=> 'true',
            'pagination_type'   => '',
            'infinite_scroll'   => '',
            'animation'  		=> '',
            // END OLD ATTRIBUTES
            'grid_style'  		=> 'default', // master ID
            'grid_id'  			=> wp_generate_password( 8, false ), // grid ID
            'taxonomy'  		=> 'category',
            'terms'  			=> '',

        ], $atts, 'gridmaster' );

        $id = $atts['id'];

        if ( !empty( $id ) ) {
            // Get args from the database
            // $atts = get_post_meta( $id, 'gridmaster_args', true );
        }

        // $atts['tax_query'] = ! empty( $atts['tax_query'] ) ? json_decode( $atts['tax_query'], true ) : [];

        // If id is set then get args from the database and render the grid
        // Otherwise render the grid from the shortcode attributes


        // Render dynamic styles
        $this->render_styles([
            'grid_style' => $atts['grid_style']
        ]);

        


        extract($atts);

        // Grid ID
        $grid_id = $atts['grid_id'];

        // Pagination
        $pagination_type = $atts['pagination_type'];

        ob_start();

        // Texonomy arguments
        $taxonomy = $atts['taxonomy'];
        $tax_args = array(
            'hide_empty' => $atts['hide_empty'],
            'taxonomy' => $taxonomy,
            'include' => $terms ? $terms : $cat,
        );

        // Get category terms
        $tax_terms = get_terms($tax_args); 

        $input_name = 'tax_input[' . $taxonomy . '][]';
        $input_id = $grid_id . '-' . $taxonomy . '_all';
        ?>
        <div data-grid-style="<?php echo esc_attr( $atts['grid_style'] ); ?>" data-grid-id="<?php echo esc_attr($grid_id); ?>" class="am_ajax_post_grid_wrap" data-pagination_type="<?php echo esc_attr($pagination_type); ?>" data-am_ajax_post_grid='<?php echo wp_json_encode($atts);?>'>
    
            <?php if ( $show_filter == "yes" && $tax_terms && !is_wp_error( $tax_terms ) ){ ?>
                <div class="asr-filter-div">
                    <div class="gm-taxonomy-filter">

                        <?php if($btn_all != "no"): ?>
                            <div class="gm-taxonomy-item gm-taxonomy-all">
                                <input type="radio" name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" value="-1" />
                                <label class="asr_texonomy" for="<?php echo $input_id; ?>"><?php echo esc_html('All','gridmaster'); ?></label>
                            </div>
                        <?php endif; ?>

                        <?php foreach( $tax_terms as $term ) { 
                            $taxonomy = $term->taxonomy;
                            $input_id = $grid_id . '-' . $taxonomy . '_' . $term->term_id;
                            $input_name = 'tax_input[' . $taxonomy . '][]';
                            ?>
                            <div class="gm-taxonomy-item">
                                <input type="radio" name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" value="<?php echo $term->term_id; ?>" />
                                <label class="asr_texonomy" for="<?php echo $input_id; ?>"><?php echo $term->name; ?></label>
                            </div>
                        <?php } ?>

                    </div>
                </div>
            <?php } ?>
    
            <div class="asr-ajax-container">
                <div class="asr-loader">
                    <div class="lds-dual-ring"></div>
                </div>
                <div class="asrafp-filter-result">
                    <?php echo $this->render_grid( $this->get_args_from_atts($atts) ); ?>
                </div>
            </div>
        </div>
    
        <?php return ob_get_clean();
    }

    /**
     * Get args from shortcode attributes
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
    
        
        // Tax Query
        if ( !empty( $terms ) ) {
            $data['tax_query'] = [
                'category' => $terms,
            ];
        }
    
        return $data;
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
        
        // Tax Input: New way
        $taxInput = [];
        if( isset( $_POST['taxInput'] ) ) {
            parse_str( $_POST['taxInput'], $taxInput );
        }
        if ( !empty( $taxInput ) ) {
            $data = array_merge( $data, $taxInput );
        }
        
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
            unset( $args['tax_input'] );
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
        ]);

        
        // Apply Filter
        $args = apply_filters( 'gridmaster_render_grid_args', $args );
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
        $dataPaged = sanitize_text_field( $args['paged'] );

        // echo '<pre>'; print_r( $args ); echo '</pre>';

        // Grid Style
        $grid_style = sanitize_text_field( $args['grid_style'] );

        // Tax Query
        if ( $args['has_tax_query'] ) {
            $query_args['tax_query'] = $args['tax_query'];
        } elseif ( !empty( $tax_query ) && !$args['has_tax_query'] ) {

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
                        echo "<button type='button' data-paged='{$dataPaged}' data-next='{$dataNext}' class='{$args['infinite_scroll']} am-post-grid-load-more'>" . esc_html__( 'Load More', 'ajax-filter-posts' )."</button>";
                    }

                } else {

                    // Paginate links
                    echo "<div class='am_posts_navigation_init'>{$paginate_links}</div>";
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
        $located = '';
        foreach ( (array) $template_names as $template_name ) {
            if ( ! $template_name ) {
                continue;
            }
            if ( file_exists( GRIDMASTER_PATH . '/templates/' . $template_name ) ) {
                $located = GRIDMASTER_PATH . '/templates/' . $template_name;
                break;
            }
        }
        if ( $load && '' != $located ) {
            load_template( $located, $require_once );
        }
        return $located;
    }

}

/*shortcode test*/

// function button_shortcode( $atts, $content = null){     $values = shordcode_atts ( array (
//          'url' => '#',
//     ), $atts);
//     return '<a class="button" href="'.esc_attr(values['url']) . '">' . $content . '</a>';
//  }
//  add_shortcode( 'button' , 'button_shortcode');

//  function my_shortcode(){
//    $massege  = "hello wordpress";
//      return $massege;
//  }
//  //register shortcode [greeting]
//  add_shortcode('greeting','my_shortcode');
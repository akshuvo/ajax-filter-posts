<?php
// Include the admin functions
require_once( GRIDMASTER_PATH . '/admin/admin-functions.php' );
?>
<form class="container-fluid gm-container metabox-holder pt-0" id="gm-shortcode-generator" action="" method="post">
    <div class="row">
        <nav class="gm-left-sidebar pt-3 border-1 border-end  col-md-4 col-xl-3 col-xxl-3  d-md-block sidebar">
            
            <div id="gm-select-filter" class="postbox gm-slide-toggle ">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Filter Options', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside" style="display: block;">

                    <!-- show_filter -->
                    <?php gridmaster_form_field( gm_field_name('show_filter'),array(
                        'type' => 'radio',
                        'label' => 'Show Filter',
                        'options' => [
                            'yes' => __('Yes', 'gridmaster'),
                            'no' => __('No', 'gridmaster'),
                        ],
                        'default' => 'yes',
                    ) ); ?>

                    <!-- filter_style -->
                    <?php gridmaster_form_field( gm_field_name('filter_style'),array(
                        'type' => 'select',
                        'label' => __('Filter Style', 'gridmaster'),
                        'options' => apply_filters( 'gridmaster_filter_styles', [
                            'default' => 'Style 1 (Default)',
                            'style-2' => 'Style 2 (New)',
                            'style-3' => 'Style 3 (New)',
                        ] ),
                        'default' => 'default', // default
                    ) ); ?>
                    <div class="filter-demo-link-button hidden"></div>

                    <!-- btn_all -->
                    <?php gridmaster_form_field( gm_field_name('btn_all'),array(
                        'type' => 'radio',
                        'label' => 'Show All Button',
                        'options' => [
                            'yes' => __('Yes', 'gridmaster'),
                            'no' => __('No', 'gridmaster'),
                        ],
                        'default' => 'yes',
                    ) ); ?>

                    <!-- Select taxonomy -->
                    <?php 
                    $taxonomies = gm_get_taxonomies();
                    $taxonomy_options = $taxonomies['options'];
                    $taxonomy_object_types = $taxonomies['object_types'];
                    $terms = $taxonomies['terms'];

                    gridmaster_form_field( gm_field_name('taxonomy'), array(
                        'type' => 'select',
                        'label' => 'Select Taxonomy',
                        'options' => $taxonomy_options,
                        'default' => 'category', // category
                        'class' => 'gm-select-taxonomy',
                    ) ); ?>
                    <script>
                        window.gm_taxonomy_object_types = <?php echo json_encode($taxonomy_object_types); ?>;
                        window.gm_terms = <?php echo json_encode(  $terms); ?>;
                    </script>

                    <?php
                    gridmaster_form_field( gm_field_name('terms'), array(
                        'id' => 'terms',
                        'type' => 'checkbox-list',
                        'label' => 'Select Terms',
                        'placeholder' => 'Select Terms',
                        'options' => $terms['category'],
                        'class' => 'gm-select-term',
                    ) ); ?>
                    
                    <!-- hide_empty -->
                    <?php gridmaster_form_field( gm_field_name('hide_empty'),array(
                        'type' => 'radio',
                        'label' => 'Hide Empty Terms',
                        'options' => [
                            '1' => __('Yes', 'gridmaster'),
                            '0' => __('No', 'gridmaster'),
                        ],
                        'default' => '0',
                    ) ); ?>

                     <!-- // Initial Term on Page Load -->
                     <?php gridmaster_form_field( gm_field_name('initial_term'),array(
                        'type' => 'select',
                        'label' => 'Initial Term on Page Load',
                        'options' => [
                            '-1' => __('All - Default', 'gridmaster'),
                            'auto' => __('Auto Select', 'gridmaster'),
                        ],
                        'default' => '-1',
                        'is_pro' => true,
                        'description' => __('Select the initial term to be selected on page load.', 'gridmaster'),
                    ) ); ?>


                    <!-- Allow Multiple Selection -->
                    <?php gridmaster_form_field( gm_field_name('multiple_select'),array(
                        'type' => 'radio',
                        'label' => __('Allow Multiple Select', 'gridmaster'),
                        'options' => [
                            'yes' => __('Yes', 'gridmaster'),
                            'no' => __('No', 'gridmaster')
                        ],
                        'default' => 'no',
                        'description' => __('Allow multiple selection of terms in the filter.', 'gridmaster'),
                        'is_pro' => true,
                    ) ); ?>

                </div>
            </div>
            

            <div id="gm-select-grid" class="postbox gm-slide-toggle ">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Grid Options', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside" style="display: block;">
                <?php do_action( 'gridmaster_grid_settings_fields_before' ); ?>

                    <!-- Select Style -->
                    <?php gridmaster_form_field( gm_field_name('grid_style'),array(
                        'type' => 'select',
                        'label' => 'Select Grid Style',
                        'options' => gridmaster_grid_styles(),
                        'default' => 'default',
                    ) ); ?>
                    <div class="grid-demo-link-button hidden"></div>

                    <!-- Select Post Type -->
                    <?php gridmaster_form_field( gm_field_name('post_type'),array(
                        'type' => 'select',
                        'label' => 'Select Post Type',
                        'options' => gm_get_post_types(),
                        'default' => 'post',
                    ) ); ?>

                    <!-- Select Advanced Post Type -->
                    <?php gridmaster_form_field( gm_field_name('post_type_selection'),array(
                        'type' => 'select',
                        'label' => 'Post Type Advanced Selection',
                        'options' => [
                            '' => __('None', 'gridmaster'),
                            'auto' => __('Auto Select', 'gridmaster'),
                        ],
                        'default' => '',
                        'is_pro' => true,
                    ) ); ?>

                    <!-- posts_per_page -->
                    <?php gridmaster_form_field( gm_field_name('posts_per_page'),array(
                        'type' => 'number',
                        'label' => 'Posts Per Page',
                        'default' => 10,
                    ) ); ?>

                    

                    <!-- orderby -->
                    <?php gridmaster_form_field( gm_field_name('orderby'),array(
                        'type' => 'select',
                        'label' => 'Order By',
                        'options' => [
                            'date' => 'Date',
                            'title' => 'Title',
                            'rand' => 'Random',
                            'comment_count' => 'Comment Count',
                            'modified' => 'Modified',
                            'ID' => 'ID',
                            'author' => 'Author',
                            'name' => 'Name',
                            'type' => 'Type',
                            'parent' => 'Parent',
                            'menu_order' => 'Menu Order',
                            'meta_value' => 'Meta Value',
                            'meta_value_num' => 'Meta Value Number',
                        ],
                        'default' => 'date',
                    ) ); ?>

                    <!-- order -->
                    <?php gridmaster_form_field( gm_field_name('order'),array(
                        'type' => 'select',
                        'label' => 'Order',
                        'options' => [
                            'DESC' => 'Descending',
                            'ASC' => 'Ascending',
                        ],
                        'default' => 'DESC',
                    ) ); ?>

                    <!-- Show content from  -->
                    <?php gridmaster_form_field( gm_field_name('content_from'),array(
                        'type' => 'select',
                        'label' => 'Show content from',
                        'options' => [
                            'excerpt' => 'Post Excerpt',
                            'content' => 'Post Content',
                        ],
                        'default' => 'excerpt',
                    ) ); ?>
                    
                    <!-- excerpt_length -->
                    <?php gridmaster_form_field( gm_field_name('excerpt_length'),array(
                        'type' => 'number',
                        'label' => 'Excerpt Length',
                        'default' => 15,
                    ) ); ?>

                    <!-- show_read_more -->
                    <?php gridmaster_form_field( gm_field_name('show_read_more'),array(
                        'type' => 'radio',
                        'label' => 'Show Read More',
                        'options' => [
                            'yes' => __('Yes', 'gridmaster'),
                            'no' => __('No', 'gridmaster'),
                        ],
                        'default' => 'yes',
                    ) ); ?>

                    <!-- read_more_text -->
                    <?php gridmaster_form_field( gm_field_name('read_more_text'),array(
                        'type' => 'text',
                        'label' => __('Read More Text', 'gridmaster'),
                        'default' => '',
                        'placeholder' => __('Read More', 'gridmaster'),
                    ) );

                    // Grid Image Size
                    gridmaster_form_field( gm_field_name('grid_image_size'),array(
                        'type' => 'select',
                        'label' => 'Grid Image Size',
                        'options' => gm_get_image_sizes(),
                        'default' => 'full',
                    ) );
                    ?>
                    <div class="show-if-image-size-custom">
                        <p><i>You can crop the original image size to any custom size. You can also set a single value for height or width in order to keep the original size ratio.</i></p>
                        <!-- image_width -->
                        <?php gridmaster_form_field( gm_field_name('grid_image_width'),array(
                            'type' => 'number',
                            'label' => 'Image Width',
                            'default' => 350,
                            'is_pro' => true,
                        ) ); ?>

                        <!-- image_height -->
                        <?php gridmaster_form_field( gm_field_name('grid_image_height'),array(
                            'type' => 'number',
                            'label' => 'Image Height',
                            'default' => 200,
                            'is_pro' => true,
                        ) ); ?>
                    </div>

                    <!-- Apply Link on Thumbnail -->
                    <?php gridmaster_form_field( gm_field_name('link_thumbnail'),array(
                        'type' => 'radio',
                        'label' => __('Apply Link on Thumbnail', 'gridmaster'),
                        'options' => [
                            'yes' => __('Yes', 'gridmaster'),
                            'no' => __('No', 'gridmaster'),
                        ],
                        'default' => 'no',
                        'is_pro' => true,
                    ) ); ?>

                    <div class="show-if-link_thumbnail-yes hidden">
                        <!-- link_thumbnail_to -->
                        <?php gridmaster_form_field( gm_field_name('link_thumbnail_to'),array(
                            'type' => 'select',
                            'label' => 'Link Thumbnail To',
                            'options' => [
                                'post' => __('Post Link', 'gridmaster'),
                                'image' => __('Image Link', 'gridmaster'),
                            ],
                            'default' => 'post',
                            'is_pro' => true,
                        ) ); ?>
                    </div>

                    <hr>
                    <?php
                    // Heading Tag
                    gridmaster_form_field ( gm_field_name('title_tag'), array(
                        'type' => 'select',
                        'label' => 'Heading Tag',
                        'options' => [
                            '' => 'Select Tag',
                            'h1' => 'H1',
                            'h2' => 'H2',
                            'h3' => 'H3',
                            'h4' => 'H4',
                            'h5' => 'H5',
                            'h6' => 'H6',
                            'div' => 'div',
                            'span' => 'span',
                            'p' => 'p',
                        ],
                        'default' => '',
                        'is_pro' => true,
                        'description' => 'Select the heading tag for the post title.',
                    ) );

                    // Heading Font Size
                    gridmaster_form_field( gm_field_name('heading_font_size'),array(
                        'type' => 'text',
                        'label' => 'Heading Font Size',
                        'default' => [
                            'xs' => '16px',
                            'sm' => '18px',
                            'md' => '20px',
                            'lg' => '22px',
                            'xl' => '24px',
                        ],
                        'is_pro' => true,
                        'responsive_field' => true,
                        'description' => 'Set the font size for the post title in different devices.',
                    ) );
                    ?>

                    <hr>
                    <?php
                    // Column Gap
                    gridmaster_form_field( gm_field_name('grid_col_gap'),array(
                        'type' => 'number',
                        'label' => 'Column Gap',
                        'default' => 30,
                        'is_pro' => true,
                        'responsive_field' => true,
                    ) );

                    // Row Gap
                    gridmaster_form_field( gm_field_name('grid_row_gap'),array(
                        'type' => 'number',
                        'label' => 'Row Gap',
                        'default' => 30,
                        'is_pro' => true,
                        'responsive_field' => true,
                    ) );

                    // Item Per Row
                    gridmaster_form_field( gm_field_name('grid_item_per_row'),array(
                        'type' => 'number',
                        'label' => 'Item Per Row',
                        'default' => [
                            'xs' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 3,
                            'xl' => 3,
                        ],
                        'is_pro' => true,
                        'responsive_field' => true,
                    ) );

                  


                    ?>


                    <?php do_action( 'gridmaster_grid_settings_fields_after' ); ?>
                </div>
            </div>
            
            <div id="gm-select-pagination" class="postbox gm-slide-toggle ">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Pagination Options', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside" style="display: block;">
                    <!-- pagination_type -->
                    <?php gridmaster_form_field( gm_field_name('pagination_type'),array(
                        'type' => 'radio',
                        'label' => 'Pagination Type',
                        'options' => [
                            '' => 'Default',
                            'load_more' => 'Load More',
                        ],
                        'default' => '',
                    ) ); ?>

                    <!-- infinite_scroll -->
                    <?php gridmaster_form_field( gm_field_name('infinite_scroll'),array(
                        'type' => 'radio',
                        'label' => 'Infinite Scroll',
                        'options' => [
                            'true' => 'Yes',
                            'false' => 'No',
                        ],
                        'default' => 'no',
                        'description' => 'If pagination type is default, this option will be ignored.',
                    ) ); ?>

                    <!-- animation -->
                    <?php gridmaster_form_field( gm_field_name('animation'),array(
                        'type' => 'radio',
                        'label' => 'Animation',
                        'options' => [
                            'true' => 'Yes',
                            'false' => 'No',
                        ],
                        'default' => 'no',
                    ) ); ?>
                </div>
            </div>
            
        </nav>

        <main class="pt-3 gm-right-sidebar col-md-9 ms-sm-auto col-lg-9 ">
            <?php //echo do_shortcode("[gridmaster]"); ?>

            <!-- Grid Preview  -->
            <div class="gm-iframe-postbox postbox gm-slide-toggle-- ">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Preview', 'gridmaster' ); ?></h2>
                    <div class="preview-action-buttons">
                        <div class="gridmaster-responsive-fields-devices px-2">
                        <?php
                        foreach( gm_get_breakpoints() as $device => $breakpoint ) {
                            $classes = '';
                            if( $breakpoint['default'] ) {
                                $classes = ' selected ';
                            } 
                            echo '<div class="gridmaster-responsive-fields-device gm-tooltip' . esc_attr( $classes ) . '" data-device="' . esc_attr( $device ) . '" title="' . esc_attr( $breakpoint['label'] . ' (>=' . $breakpoint['value'] . 'px)' ) . '">' 
                                . '<span class="dashicons dashicons-' . esc_attr( $breakpoint['icon'] ) . '"></span>' . 
                            '</div>';
                        }
                        ?>
                        </div>
                        <div class="align-items-center d-flex preview-scale px-2">
                                                   
                            <div id="gm-responsive-bar-scale__minus" class="gm-tooltip" title="<?php esc_attr_e('Scale Down', 'gridmaster'); ?>">
                                <span class="dashicons dashicons-minus"></span>
                            </div>
                            <div id="gm-responsive-bar-scale__value-wrapper">
                                <input class="skip-reload hidden" id="gm-preview-scale-input" type="number" min="50" max="120" step="10" value="100" readonly/>
                                <span id="gm-responsive-bar-scale__value">100</span>%
                            </div>
                            <div id="gm-responsive-bar-scale__plus" class="gm-tooltip" title="<?php esc_attr_e('Scale Up', 'gridmaster'); ?>">
                                <span class="dashicons dashicons-plus-alt2"></span>
                            </div>
                            <div id="gm-responsive-bar-scale__reset" class="gm-tooltip" title="<?php esc_attr_e('Reset Scale', 'gridmaster'); ?>">
                                <span class="dashicons dashicons-undo"></span>
                            </div>
                
                        </div>
                    </div>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside" style="display: block;">
                    <div id="gm-grid-preview">
                        <div class="gm-iframe-wrap loading">
                            <div class="asr-loader">
                                <div class="lds-dual-ring"></div>
                            </div>
                            <iframe id="gm-iframe" src="<?php echo esc_url( home_url('/') . '?gm_shortcode_preview=1&shortcode='.urlencode( '[gridmaster]' ) ); ?>" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Grid Preview -->


            
            
        </main>
    </div>
</form>

<script>

    // Scale preview
    const scaleInput = document.querySelector("#gm-preview-scale-input")
    scaleInput.addEventListener("input", (event) => {
        let thisVal = Math.round(event.target.value)
        const scale =  thisVal/ 100
        const iframe = document.querySelector("#gm-iframe")
        iframe.style.transform = `scale(${scale})`
        document.querySelector("#gm-responsive-bar-scale__value").innerHTML = thisVal
    })

    // Increase scale
    const incrBtn = document.querySelector("#gm-responsive-bar-scale__plus")
    incrBtn.addEventListener("click", (event) => {
        scaleInput.stepUp();
        scaleInput.dispatchEvent(new Event('input', {bubbles:true}));
    })

    // Decrease scale
    const decrBtn = document.querySelector("#gm-responsive-bar-scale__minus")
    decrBtn.addEventListener("click", (event) => {
        scaleInput.stepDown();
        scaleInput.dispatchEvent(new Event('input', {bubbles:true}));
    })

    // Reset scale
    const resetBtn = document.querySelector("#gm-responsive-bar-scale__reset")
    resetBtn.addEventListener("click", (event) => {
        scaleInput.value = 100;
        scaleInput.dispatchEvent(new Event('input', {bubbles:true}));
    })

    // Add classes to taxonomy options
    const taxOptions = document.querySelectorAll(".gm-select-taxonomy option");
    taxOptions.forEach( (option) => {
        const tax = option.value;
        if( gm_taxonomy_object_types[tax] ) {
            // Add classes with 'post_type_' prefix
            const classes = gm_taxonomy_object_types[tax].map( (post_type) => {
                return 'obj_type_' + post_type
            })
            option.classList.add(...classes)

            // If post type is not `post` then add hidden class
            if( !gm_taxonomy_object_types[tax].includes('post') ) {
                option.classList.add('hidden')
            }
        }
    })

    // Post Type change
    const postTypeSelect = document.querySelector("#post_type");
    postTypeSelect.addEventListener("change", (event) => {
        const postType = event.target.value;
      
        taxOptions.forEach( (option) => {
            if( option.classList.contains('obj_type_' + postType) ) {
                option.classList.remove('hidden')
            } else {
                if( option.value !== '-' ) {
                    option.classList.add('hidden')
                }
            }

            // Select first option
            if( option.value == '-' ) {
                option.selected = true;
            }
        })

    })

    // Window load event
    window.addEventListener("load", (event) => {
        // Trigger Post Type change
        // postTypeSelect.dispatchEvent(new Event('change', {bubbles:true}));

        // Trigger Taxonomy change
        jQuery('#taxonomy').trigger('change');

    })

    // Add a loader class when Iframe is loading
    const iframe = document.querySelector("#gm-iframe")
    iframe.addEventListener("load", (event) => {
        iframe.parentNode.classList.remove('loading')
    })

</script>
<style>
    .gm-admin-content {
        max-width: 100% !important;
    }
    .gm-admin-into {
        display: none;
    }
    .toplevel_page_gridmaster #wpcontent #wpbody {
        height: calc(100vh - 72px);
        overflow: hidden;
    }

    .toplevel_page_gridmaster #wpcontent #wpbody-content {
        padding-bottom: 0;
        height: 100%;
    }

    #gm-shortcode-generator p {
        color: #3c434a;
        font-size: inherit;
    }

</style>
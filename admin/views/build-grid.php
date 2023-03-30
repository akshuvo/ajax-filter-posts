<?php
// Include the admin functions
require_once( GRIDMASTER_PATH . '/admin/admin-functions.php' );
?>
<form class="container-fluid gm-container metabox-holder pt-0" id="gm-shortcode-generator" action="" method="post">
    <div class="row">
        <nav class="gm-left-sidebar pt-3 border-1 border-end  col-md-4 col-xl-3 col-xxl-3  d-md-block sidebar">
            
            <div id="gm-select-filter" class="postbox gm-slide-toggle closed--">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Filter Options', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">
                    <!-- show_filter -->
                    <?php gridmaster_form_field( gm_field_name('show_filter'),array(
                        'type' => 'radio',
                        'label' => 'Show Filter',
                        'options' => [
                            'yes' => 'Yes',
                            'no' => 'No',
                        ],
                        'default' => 'yes',
                    ) ); ?>

                    <!-- btn_all -->
                    <?php gridmaster_form_field( gm_field_name('btn_all'),array(
                        'type' => 'radio',
                        'label' => 'Show All Button',
                        'options' => [
                            'yes' => 'Yes',
                            'no' => 'No',
                        ],
                        'default' => 'yes',
                    ) ); ?>


                </div>
            </div>
            

            <div id="gm-select-grid" class="postbox gm-slide-toggle closed--">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Grid Options', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">
                <?php do_action( 'gridmaster_grid_settings_fields_before' ); ?>

                    <!-- Select Style -->
                    <?php gridmaster_form_field( gm_field_name('grid_style'),array(
                        'type' => 'select',
                        'label' => 'Select Grid Style',
                        'options' => apply_filters( 'gridmaster_grid_styles', [
                            'default' => 'Default',
                            'style-1' => 'Style 1',
                            'style-2' => 'Style 2',
                            'style-3' => 'Style 3',
                            'style-4' => 'Style 4',
                        ] ),
                        'default' => 'default',
                    ) ); ?>

                    <!-- Select Post Type -->
                    <?php gridmaster_form_field( gm_field_name('post_type'),array(
                        'type' => 'select',
                        'label' => 'Select Post Type',
                        'options' => gm_get_post_types(),
                        'default' => 'post',
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
                            'yes' => 'Yes',
                            'no' => 'No',
                        ],
                        'default' => 'yes',
                    ) ); ?>

                    <!-- read_more_text -->
                    <?php gridmaster_form_field( gm_field_name('read_more_text'),array(
                        'type' => 'text',
                        'label' => 'Read More Text',
                        'default' => '',
                        'placeholder' => 'Read More',
                    ) ); ?>

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
                        'default' => 3,
                        'is_pro' => true,
                        'responsive_field' => true,
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

                    <?php do_action( 'gridmaster_grid_settings_fields_after' ); ?>
                </div>
            </div>
            
            <div id="gm-select-pagination" class="postbox gm-slide-toggle closed--">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Pagination Options', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">
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
            <div class="postbox gm-slide-toggle-- closed--">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Preview', 'gridmaster' ); ?></h2>
                    <div class="preview-action-buttons">
                        <div class="gridmaster-responsive-fields-devices">
                        <?php
                        foreach( gm_get_breakpoints() as $device => $breakpoint ) {
                            $classes = '';
                            if( $breakpoint['default'] ) {
                                $classes = ' selected ';
                            } 
                            echo '<div class="gridmaster-responsive-fields-device ' . esc_attr( $classes ) . '" data-device="' . esc_attr( $device ) . '" title="' . esc_attr( $breakpoint['label'] ) . '">' 
                                . '<span class="dashicons dashicons-' . esc_attr( $breakpoint['icon'] ) . '"></span>' . 
                            '</div>';
                        }
                        ?>
                        </div>
                        <div class="d-flex preview-scale">
                                                   
                            <div id="gm-responsive-bar-scale__minus">
                                <span class="dashicons dashicons-minus"></span>
                            </div>
                            <div id="gm-responsive-bar-scale__value-wrapper">
                                <input class="skip-reload hidden" id="gm-preview-scale-input" type="number" min="50" max="120" step="10" value="100" readonly/>
                                <span id="gm-responsive-bar-scale__value">100</span>%
                            </div>
                            <div id="gm-responsive-bar-scale__plus">
                                <span class="dashicons dashicons-plus"></span>
                            </div>
                            <div id="gm-responsive-bar-scale__reset">
                                <span class="dashicons dashicons-image-rotate"></span>
                            </div>
                
                        </div>
                    </div>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">
                    <div id="gm-grid-preview">
                        <div class="gm-iframe-wrap">
                            <iframe id="gm-iframe" src="<?php echo esc_url( 'http://ajax-post-grid.local/?gm_shortcode_preview=1&shortcode='.urlencode( '[gridmaster]' ) ); ?>" frameborder="0"></iframe>
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


</script>
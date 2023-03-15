<?php
// Include the admin functions
require_once( GRIDMASTER_PATH . '/admin/admin-functions.php' );
?>
<form class="container-fluid" id="gm-shortcode-generator" action="" method="post">
    <div class="row">
        <nav id="sidebarMenu" class="metabox-holder pt-3 border-1 border-end  col-md-4 col-xl-3 col-xxl-2  d-md-block sidebar">
            
            <div id="gm-select-filter" class="postbox gm-slide-toggle closed--">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Select Filter Style', 'gridmaster' ); ?></h2>
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
                    <h2 class="hndle"><?php esc_html_e( 'Select Grid Style', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">

                    <!-- Select Style -->
                    <?php gridmaster_form_field( gm_field_name('grid_style'),array(
                        'type' => 'select',
                        'label' => 'Select Grid Style',
                        'options' => apply_filters( 'gridmaster_grid_styles', [
                            'default' => 'Default',
                            'style-1' => 'Style 1',
                            'style-2' => 'Style 2',
                            'style-3' => 'Style 3',
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
                    
                </div>
            </div>
            
            <div id="gm-select-pagination" class="postbox gm-slide-toggle closed--">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Select Pagination Style', 'gridmaster' ); ?></h2>
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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php //echo do_shortcode("[gridmaster]"); ?>

            <div id="gm-grid-preview" class="postbox">
                <div class="gm-iframe-wrap">
                    <iframe id="gm-iframe" src="<?php echo esc_url( 'http://ajax-post-grid.local/?gm_shortcode_preview=1&shortcode='.urlencode( '[gridmaster]' ) ); ?>" frameborder="0"></iframe>
                </div>
            </div>
            
        </main>
    </div>
</form>
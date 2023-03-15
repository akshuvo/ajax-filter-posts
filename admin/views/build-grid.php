<?php
// Include the admin functions
require_once( GRIDMASTER_PATH . '/admin/admin-functions.php' );



?>
<form class="container-fluid ">
    <div class="row">
        <nav id="sidebarMenu" class="metabox-holder pt-3 border-1 border-end  col-md-4 col-xl-3 col-xxl-2  d-md-block sidebar">
            
            <div id="gm-select-filter" class="postbox gm-slide-toggle closed">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Select Filter Style', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">
                </div>
            </div>
            
            <div id="gm-select-grid" class="postbox gm-slide-toggle closed">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Select Grid Style', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">

                    <?php gridmaster_form_field( gm_field_name('grid_style'),array(
                        'type' => 'select',
                        'label' => 'Grid Style',
                        // 'id' => 'grid_style',
                        'options' => apply_filters( 'gridmaster_grid_styles', [
                            'default' => 'Default',
                            'style-1' => 'Style 1',
                            'style-2' => 'Style 2',
                            'style-3' => 'Style 3',
                        ] ),
                        'default' => 'default',
                    ) ); ?>
                    
                </div>
            </div>
            
            <div id="gm-select-pagination" class="postbox gm-slide-toggle closed">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Select Pagination Style', 'gridmaster' ); ?></h2>
                    <div class="handle-actions pe-2">
                        <span class="dashicons dashicons-arrow-down">
                    </div>
                </div>
                <div class="inside">
                </div>
            </div>
            
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php //echo do_shortcode("[gridmaster]"); ?>
            
        </main>
    </div>
</form>
<?php

// Create table if not exists
if ( !class_exists( 'GridMaster\DB_Migration' ) ) {
    require_once GRIDMASTER_PATH . '/admin/DB_Migration.php';
}
if ( !class_exists( 'GridMaster\Grids' ) ) {
    require_once GRIDMASTER_PATH . '/admin/Grids.php';
}

$my_grids = GridMaster\Grids::init()->list();


?>


<div class="gridmaster-wrap ">
    <div class="container-fluid pt-0 pt-3 gm-container">
        <div class="row">
            <div class="col-md-12">
                
                <?php if( !empty( $my_grids ) ) : ?>
                    <div class="gm-grid-list">
                        <div class="gm-grid-list-header">
                            <h2><?php echo esc_html('My Grids', 'gridmaster'); ?></h2>
                        </div>
                        <div class="gm-grid-list-body">
                        <table class="wp-list-table widefat fixed striped table-view-list">
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo esc_html('Title', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Post Type', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Taxonomy', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Shortcode', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Actions', 'gridmaster'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="the-list">
                            <tr>
                                    <th scope="col"><?php echo esc_html('Title', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Post Type', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Taxonomy', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Shortcode', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Actions', 'gridmaster'); ?></th>
                                </tr>
                                <tr>
                                    <th scope="col"><?php echo esc_html('Title', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Post Type', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Taxonomy', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Shortcode', 'gridmaster'); ?></th>
                                    <th scope="col"><?php echo esc_html('Actions', 'gridmaster'); ?></th>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="gm-card text-center">
                        <div class="gm-card-details">
                            <div class="gm-icon">
                                <span class="dashicons dashicons-info-outline"></span>
                            </div>
                            <div class="gm-card-containt">
                                <h2><?php echo esc_html('No Grids Found', 'gridmaster'); ?></h2>
                                <a class="gm-btn gm-btn-fill" href="<?php echo esc_url( admin_url('admin.php?page=gridmaster&path=build-grid') ); ?>"><?php echo esc_html('Create Your First Grid', 'gridmaster'); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

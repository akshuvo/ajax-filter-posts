<?php
// Direct Access is not allowed.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$path = isset( $_GET['path'] ) ? $_GET['path'] : '';

// Left Tabs : Welcome, Templates, Build Grid, Settings, Support,
$left_tabs = [
    [
        'title' => __( 'Welcome', 'gridmaster' ),
        'url'   => admin_url( 'admin.php?page=gridmaster' ),
        'icon'  => 'dashicons dashicons-admin-home',
        'path' => '',
        'target' => '',
    ],
    // [
    //     'title' => __( 'My Grids', 'gridmaster' ),
    //     'url'   => admin_url( 'admin.php?page=gridmaster&path=my-grids' ),
    //     'icon'  => 'dashicons dashicons-layout',
    //     'path' => 'my-grids',
    //     'target' => '',
    // ],
    // [
    //     'title' => __( 'Templates', 'gridmaster' ),
    //     'url'   => admin_url( 'admin.php?page=gridmaster&path=templates' ),
    //     'icon'  => 'dashicons dashicons-layout',
    //     'path' => 'templates',
    //     'target' => '',
    // ],
    [
        'title' => __( 'Grid Builder', 'gridmaster' ),
        'url'   => admin_url( 'admin.php?page=gridmaster&path=build-grid' ),
        'icon'  => 'dashicons dashicons-schedule',
        'path' => 'build-grid',
        'target' => '',
    ],
    [
        'title' => __( 'Settings', 'gridmaster' ),
        'url'   => admin_url( 'admin.php?page=gridmaster&path=settings' ),
        'icon'  => 'dashicons dashicons-admin-generic',
        'path' => 'settings',
        'target' => '',
    ],
    [
        'title' => __( 'Free vs Pro', 'gridmaster' ),
        'url'   => gridmaster_website_url( 'gridmaster/free-vs-pro/' ),
        'icon'  => 'dashicons dashicons-star-filled',
        'path' => 'free-vs-pro',
        'target' => '_blank'
    ],
];
?>
<div class="gridmaster-wrap <?php echo esc_attr('gm-page-' . $path); ?>">
    <div class="gm-admin-header">
        <div class="gm-admin-into">
            <h2><span class="dashicons dashicons-forms me-2"></span> <?php esc_html_e( 'GridMaster', 'gridmaster' ); ?></h2>
            <p><?php esc_html_e( 'Your ultimate tool for crafting visually stunning, customizable, and user-friendly post grids with ease.', 'gridmaster' ); ?></p>
        </div>

        <div class="gm-admin-toolbar">
            <?php if( $path == 'build-grid' ) : ?>
                
                <div class=" float-end nav-tab">
                    <!-- <input type="text"> -->
                    <button type="button" class="gm-btn gm-btn-has-icon gm-toggle-modal" data-modal-id="gm-embed-modal"><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Embed', 'gridmaster' ); ?></button>
                    <!-- <button type="button" class="gm-save-grid gm-btn gm-btn-fill"><?php esc_html_e( 'Save Grid', 'gridmaster' ); ?></button> -->
                </div>
            <?php else: ?>
                <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <?php foreach( $left_tabs as $tab ) : ?>
                    <a href="<?php echo esc_url( $tab['url'] ); ?>" class="nav-tab <?php echo $path == $tab['path'] ? 'nav-tab-active' : ''; ?>" target="<?php echo esc_attr( $tab['target'] ); ?>">
                        <span class="<?php echo esc_attr( $tab['icon'] ); ?>"></span>
                        <?php echo esc_html( $tab['title'] ); ?>
                    </a>
                <?php endforeach; ?>
                </nav>
            <?php endif; ?>
       
        </div>
    </div>
    <div class="gm-admin-content">
        <?php
        // if ( $path == 'build-grid' ) {
        //     $file_path = GRIDMASTER_PATH . '/admin/views/build-grid.php';
        //     if( file_exists( $file_path ) ) {
        //         require_once $file_path;
        //     }
        // } elseif ( $path == 'templates' ) {
        //     $file_path = GRIDMASTER_PATH . '/admin/views/templates.php';
        //     if( file_exists( $file_path ) ) {
        //         require_once $file_path;
        //     }
        // } elseif ( $path == 'settings' ) {
        //     $file_path = GRIDMASTER_PATH . '/admin/views/settings.php';
        //     if( file_exists( $file_path ) ) {
        //         require_once $file_path;
        //     }
        // } elseif ( $path == 'support' ) {
        //     $file_path = GRIDMASTER_PATH . '/admin/views/support.php';
        //     if( file_exists( $file_path ) ) {
        //         require_once $file_path;
        //     }
        // } else {
        //     $file_path = GRIDMASTER_PATH . '/admin/views/welcome.php';
        //     if( file_exists( $file_path ) ) {
        //         require_once $file_path;
        //     }
        // }

        switch ( $path ) {
            case 'build-grid':
                $file_path = GRIDMASTER_PATH . '/admin/views/build-grid.php';
                break;
            case 'templates':
                $file_path = GRIDMASTER_PATH . '/admin/views/templates.php';
                break;
            case 'settings':
                $file_path = GRIDMASTER_PATH . '/admin/views/settings.php';
                break;
            case 'support':
                $file_path = GRIDMASTER_PATH . '/admin/views/support.php';
                break;
            case 'my-grids':
                $file_path = GRIDMASTER_PATH . '/admin/views/my-grids.php';
                break;
            default:
                $file_path = GRIDMASTER_PATH . '/admin/views/welcome.php';
                break;
        }

        if( file_exists( $file_path ) ) {
            require_once $file_path;
        }
        ?>
    </div>
</div>

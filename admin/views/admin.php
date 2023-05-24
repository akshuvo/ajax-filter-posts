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
    ],
    [
        'title' => __( 'Templates', 'gridmaster' ),
        'url'   => admin_url( 'admin.php?page=gridmaster&path=templates' ),
        'icon'  => 'dashicons dashicons-layout',
        'path' => 'templates',
    ],
    [
        'title' => __( 'Grid Builder', 'gridmaster' ),
        'url'   => admin_url( 'admin.php?page=gridmaster&path=build-grid' ),
        'icon'  => 'dashicons dashicons-schedule',
        'path' => 'build-grid',
    ],
    [
        'title' => __( 'Settings', 'gridmaster' ),
        'url'   => admin_url( 'admin.php?page=gridmaster&path=settings' ),
        'icon'  => 'dashicons dashicons-admin-generic',
        'path' => 'settings',
    ],
];
?>
<div class="gridmaster-wrap ">
    <h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <div class="gm-admin-toolbar">
        <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
            <?php foreach( $left_tabs as $tab ) : ?>
                <a href="<?php echo esc_url( $tab['url'] ); ?>" class="nav-tab <?php echo $path == $tab['path'] ? 'nav-tab-active' : ''; ?>">
                    <span class="<?php echo esc_attr( $tab['icon'] ); ?>"></span>
                    <?php echo esc_html( $tab['title'] ); ?>
                </a>
            <?php endforeach; ?>
            <?php if( $path == 'build-grid' ) : ?>
            <div class="bg-white float-end gm-copy-nav nav-tab">
                <div class="gm-copy-wrap">
                    <input type="text" id="blogname" value="[gridmaster]" class="regular-text gm-copy-inp" readonly>
                    <button type="button" class="button gm-copy-btn">Copy Shortcode</button>
                </div>
            </div>
            <?php endif; ?>
        </nav>
    </div>
    <div class="gm-admin-content">
        <?php
        if ( $path == 'build-grid' ) {
            $file_path = GRIDMASTER_PATH . '/admin/views/build-grid.php';
            if( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        } elseif ( $path == 'templates' ) {
            $file_path = GRIDMASTER_PATH . '/admin/views/templates.php';
            if( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        } elseif ( $path == 'settings' ) {
            $file_path = GRIDMASTER_PATH . '/admin/views/settings.php';
            if( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        } elseif ( $path == 'support' ) {
            $file_path = GRIDMASTER_PATH . '/admin/views/support.php';
            if( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        } else {
            $file_path = GRIDMASTER_PATH . '/admin/views/welcome.php';
            if( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
        ?>
    </div>
</div>

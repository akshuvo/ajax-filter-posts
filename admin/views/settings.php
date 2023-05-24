<?php do_action( 'lmfwppt_license_activation_form_fields' ); ?>

/**
 * License Management
 */
add_action( 'init', 'gridmaster_pro_license_updates' );
function gridmaster_pro_license_updates( ){

    // Load Class
    include_once( dirname( __FILE__ ) . '/updates/LmfwpptAutoUpdatePlugin.php' );

    // Plugin Args
    $plugin = plugin_basename( __FILE__ );
    $plugin_slug = (dirname(plugin_basename(__FILE__)));
    $current_version = '1.0.0';
    $remote_url = 'https://portal.addonmaster.com/';

    // Required args
    $args = array(
        'plugin' => $plugin,
        'plugin_slug' => $plugin_slug,
        'current_version' => $current_version,
        'remote_url' => $remote_url,
        'menu_type' => 'section',
        'parent_slug' => '',
        'page_title' => 'GridMaster Pro License Activation',
        'menu_title' => 'GridMaster Pro License',
    );

    new LmfwpptAutoUpdatePlugin( $args );
}
            
<?php
namespace GridMaster;

class DB_Migration {

    // DB Version
    private $db_version = '1.0.0';

    /**
     * Initialize the class
     */
    public function __construct() {
        // run if the plugin version up using version_compare
        if ( version_compare( get_option( 'gridmaster_db_version', 0 ), $this->db_version, '<' ) ) {
            $this->run_migration();
            update_option( 'gridmaster_db_version', $this->db_version );
        }
    }

    /**
     * Run the migration
     *
     * @return void
     */
    public function run_migration() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$wpdb->prefix}gridmaster_grids (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            attributes text NOT NULL,
            dated datetime DEFAULT NOW(),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

// Initialize the class
new DB_Migration();
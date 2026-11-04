<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database schema migrations for GridMaster.
 */
class DB_Migration {

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0.0';

	/**
	 * Initialize the class and run migration if needed.
	 */
	public function __construct() {
		if ( self::needs_migration() ) {
			self::migrate();
		}
	}

	/**
	 * Check if database migration is required.
	 *
	 * @return bool
	 */
	public static function needs_migration() {
		$installed_version = get_option( 'gridmaster_db_version', '0' );
		return version_compare( $installed_version, self::DB_VERSION, '<' );
	}

	/**
	 * Run the migration idempotently.
	 *
	 * @return void
	 */
	public static function migrate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Two spaces after PRIMARY KEY is required by WordPress dbDelta.
		$sql = "CREATE TABLE {$wpdb->prefix}gridmaster_grids (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  title text NOT NULL,
  attributes text NOT NULL,
  config_json longtext DEFAULT NULL,
  layout_post_id bigint(20) unsigned DEFAULT 0,
  schema_version int(11) NOT NULL DEFAULT 1,
  migration_status varchar(32) NOT NULL DEFAULT 'legacy',
  dated datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY layout_post_id (layout_post_id),
  KEY migration_status (migration_status)
) $charset_collate;";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );

		update_option( 'gridmaster_db_version', self::DB_VERSION );
	}
}

// Initialize the migration class.
new DB_Migration();

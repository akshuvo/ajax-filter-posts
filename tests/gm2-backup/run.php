<?php
/**
 * GridMaster 2.0 M2-T02-R1 — Deterministic CLI tests for SHOW CREATE TABLE normalization
 * and rehearsal failure-path comparison/invariant reporting.
 *
 * Exercises the exact production helper `scripts/gm2/ddl-normalizer.php` loaded by
 * `scripts/gm2/backup-restore-rehearsal.php` without connecting to WordPress or MySQL.
 *
 * Usage (from `ajax-filter-posts` repository root):
 *   php tests/gm2-backup/run.php
 *   php tests/gm2-backup/run.php --self-test-failure
 *
 * @package GridMaster
 */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "CLI execution only.\n" );
}

require_once dirname( __DIR__, 2 ) . '/scripts/gm2/ddl-normalizer.php';

/**
 * Minimal deterministic test runner for GM2-M2-T02-R1.
 */
final class GM2_Backup_Normalizer_Test_Runner {
	/** @var int */
	private $assertions = 0;

	/** @var int */
	private $suites_run = 0;

	/** @var array<int, string> */
	private $failures = array();

	public function assert_true( $condition, $message ) {
		++$this->assertions;
		if ( true !== $condition ) {
			$this->failures[] = sprintf( '%s (expected true, got %s)', $message, var_export( $condition, true ) );
		}
	}

	public function assert_false( $condition, $message ) {
		++$this->assertions;
		if ( false !== $condition ) {
			$this->failures[] = sprintf( '%s (expected false, got %s)', $message, var_export( $condition, true ) );
		}
	}

	public function assert_same( $expected, $actual, $message ) {
		++$this->assertions;
		if ( $expected !== $actual ) {
			$this->failures[] = sprintf(
				"%s\n  expected: %s\n  actual:   %s",
				$message,
				var_export( $expected, true ),
				var_export( $actual, true )
			);
		}
	}

	public function assert_not_same( $unexpected, $actual, $message ) {
		++$this->assertions;
		if ( $unexpected === $actual ) {
			$this->failures[] = sprintf(
				"%s (expected values to differ, but both were %s)",
				$message,
				var_export( $actual, true )
			);
		}
	}

	/**
	 * Execute all test suites.
	 *
	 * @return int Exit code (0 on pass, 1 on failure).
	 */
	public function run_all() {
		$this->suite_volatile_ddl_differences_compare_equal();
		$this->suite_meaningful_structural_ddl_changes_remain_unequal();
		$this->suite_failure_path_collection_and_not_run_reporting();

		if ( ! empty( $this->failures ) ) {
			foreach ( $this->failures as $failure ) {
				fwrite( STDERR, "[FAIL] {$failure}\n" );
			}
			fwrite(
				STDERR,
				sprintf(
					"FAIL: %d suites, %d assertions, %d failures.\n",
					$this->suites_run,
					$this->assertions,
					count( $this->failures )
				)
			);
			return 1;
		}

		fwrite(
			STDOUT,
			sprintf(
				"PASS: %d suites, %d assertions, 0 failures (production ddl-normalizer.php; no WP/MySQL connection).\n",
				$this->suites_run,
				$this->assertions
			)
		);
		return 0;
	}

	/**
	 * Execute deliberate failure path to verify nonzero exit code on failure.
	 *
	 * @return int Exit code (1 when self-test catches the deliberate failure).
	 */
	public function run_self_test_failure() {
		++$this->suites_run;
		$this->assert_same(
			'expected-normalized-ddl-sentinel',
			'deliberate-mismatch-sentinel',
			'Deliberate self-test failure check'
		);

		if ( empty( $this->failures ) ) {
			fwrite( STDERR, "ERROR: Deliberate self-test failure did not record a failure.\n" );
			return 2;
		}

		fwrite(
			STDERR,
			sprintf(
				"SELF-TEST FAILURE VERIFIED: caught %d deliberate failure across %d assertion(s); exiting nonzero (1).\n",
				count( $this->failures ),
				$this->assertions
			)
		);
		return 1;
	}

	/**
	 * Suite 1: Proven volatile and server-canonicalized dump/restore DDL differences compare equal.
	 */
	private function suite_volatile_ddl_differences_compare_equal() {
		++$this->suites_run;

		// 1. Source wp_gridmaster_grids with pre-8.0.19 mediumint(9) display width and AUTO_INCREMENT counter
		// vs MySQL 8.4 restored scratch table without (9) or AUTO_INCREMENT=12.
		$source_grids_ddl = "CREATE TABLE `wp_gridmaster_grids` (\r\n"
			. "  `id` mediumint(9) NOT NULL AUTO_INCREMENT,\r\n"
			. "  `title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\r\n"
			. "  `attributes` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\r\n"
			. "  `dated` datetime DEFAULT CURRENT_TIMESTAMP,\r\n"
			. "  PRIMARY KEY (`id`)\r\n"
			. ") ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";

		$restored_grids_ddl = "CREATE TABLE `wp_gridmaster_grids` (\n"
			. "  `id` mediumint NOT NULL AUTO_INCREMENT,\n"
			. "  `title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n"
			. "  `attributes` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n"
			. "  `dated` datetime DEFAULT CURRENT_TIMESTAMP,\n"
			. "  PRIMARY KEY (`id`)\n"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";

		$this->assert_same(
			gm2_normalize_create_table_ddl( $source_grids_ddl ),
			gm2_normalize_create_table_ddl( $restored_grids_ddl ),
			'wp_gridmaster_grids source DDL with mediumint(9) and AUTO_INCREMENT=12 normalizes equal to MySQL 8.4 restored DDL'
		);

		$grids_diag = gm2_diagnose_schema_mismatches(
			array( 'wp_gridmaster_grids' => $source_grids_ddl ),
			array( 'wp_gridmaster_grids' => $restored_grids_ddl ),
			'source_db',
			'scratch_db',
			'wp_gridmaster_grids'
		);

		$this->assert_same(
			'normalized_match_volatile_only',
			(
				'normalized_match_volatile_only' === gm2_classify_create_table_mismatch( $source_grids_ddl, $restored_grids_ddl )
				&& 'normalized_match_volatile_only' === $grids_diag['grids_table_diagnostics']['status']
				&& 1 === $grids_diag['clause_breakdown']['normalized_match_volatile_only']
				&& in_array( 'integer_display_width', $grids_diag['grids_table_diagnostics']['raw_categories'], true )
				&& in_array( 'table_options_auto_increment', $grids_diag['grids_table_diagnostics']['raw_categories'], true )
				&& empty( $grids_diag['grids_table_diagnostics']['residual_categories'] )
			) ? 'normalized_match_volatile_only' : 'diagnostic_mismatch',
			'gm2_classify_create_table_mismatch and gm2_diagnose_schema_mismatches report normalized_match_volatile_only and detect raw volatile categories'
		);

		$exact_diag = gm2_diagnose_schema_mismatches(
			array( 'wp_gridmaster_grids' => $restored_grids_ddl ),
			array( 'wp_gridmaster_grids' => $restored_grids_ddl ),
			'source_db',
			'scratch_db',
			'wp_gridmaster_grids'
		);

		$this->assert_same(
			'exact_raw_match',
			(
				'exact_raw_match' === gm2_classify_create_table_mismatch( $restored_grids_ddl, $restored_grids_ddl )
				&& 'exact_raw_match' === $exact_diag['grids_table_diagnostics']['status']
				&& 1 === $exact_diag['clause_breakdown']['exact_raw_match']
				&& empty( $exact_diag['grids_table_diagnostics']['raw_categories'] )
			) ? 'exact_raw_match' : 'diagnostic_mismatch',
			'gm2_classify_create_table_mismatch and gm2_diagnose_schema_mismatches report exact_raw_match for identical raw DDL'
		);

		// 2. AUTO_INCREMENT appearing after other table options (not immediately adjacent to ENGINE=InnoDB).
		$reordered_autoinc_ddl = "CREATE TABLE `wp_gridmaster_grids` (\n"
			. "  `id` mediumint NOT NULL AUTO_INCREMENT,\n"
			. "  `title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n"
			. "  `attributes` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n"
			. "  `dated` datetime DEFAULT CURRENT_TIMESTAMP,\n"
			. "  PRIMARY KEY (`id`)\n"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci AUTO_INCREMENT=99";

		$this->assert_same(
			gm2_normalize_create_table_ddl( $restored_grids_ddl ),
			gm2_normalize_create_table_ddl( $reordered_autoinc_ddl ),
			'Table-level AUTO_INCREMENT=<n> is stripped even when positioned after DEFAULT CHARSET/COLLATE'
		);

		// 3. WordPress core/plugin integer display widths across tinyint/smallint/mediumint/int/bigint (signed & unsigned),
		// while preserving signed tinyint(1) and zerofill display widths.
		$source_wp_ddl = "CREATE TABLE `wp_gm2_sample` (\n"
			. "  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "  `parent_id` bigint(20) NOT NULL DEFAULT '0',\n"
			. "  `menu_order` int(11) NOT NULL DEFAULT '0',\n"
			. "  `term_order` int(10) unsigned NOT NULL DEFAULT '0',\n"
			. "  `grid_id` mediumint(9) NOT NULL DEFAULT '0',\n"
			. "  `schema_ver` smallint(6) unsigned NOT NULL DEFAULT '1',\n"
			. "  `status_Bucket` tinyint(4) NOT NULL DEFAULT '0',\n"
			. "  `flag_unsigned` tinyint(1) unsigned NOT NULL DEFAULT '0',\n"
			. "  `is_active` tinyint(1) NOT NULL DEFAULT '1',\n"
			. "  `padded_code` int(5) unsigned zerofill NOT NULL DEFAULT '00000',\n"
			. "  `label` varchar(64) NOT NULL DEFAULT 'int(11) AUTO_INCREMENT=5',\n"
			. "  PRIMARY KEY (`id`)\n"
			. ") ENGINE=InnoDB AUTO_INCREMENT=501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";

		$restored_wp_ddl = "CREATE TABLE `wp_gm2_sample` (\n"
			. "  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n"
			. "  `parent_id` bigint NOT NULL DEFAULT '0',\n"
			. "  `menu_order` int NOT NULL DEFAULT '0',\n"
			. "  `term_order` int unsigned NOT NULL DEFAULT '0',\n"
			. "  `grid_id` mediumint NOT NULL DEFAULT '0',\n"
			. "  `schema_ver` smallint unsigned NOT NULL DEFAULT '1',\n"
			. "  `status_Bucket` tinyint NOT NULL DEFAULT '0',\n"
			. "  `flag_unsigned` tinyint unsigned NOT NULL DEFAULT '0',\n"
			. "  `is_active` tinyint(1) NOT NULL DEFAULT '1',\n"
			. "  `padded_code` int(5) unsigned zerofill NOT NULL DEFAULT '00000',\n"
			. "  `label` varchar(64) NOT NULL DEFAULT 'int(11) AUTO_INCREMENT=5',\n"
			. "  PRIMARY KEY (`id`)\n"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";

		$norm_source_wp   = gm2_normalize_create_table_ddl( $source_wp_ddl );
		$norm_restored_wp = gm2_normalize_create_table_ddl( $restored_wp_ddl );

		$this->assert_same(
			$norm_restored_wp,
			$norm_source_wp,
			'Pre-8.0.19 integer display widths normalize equal to MySQL 8.4 restored integer types while preserving tinyint(1), zerofill, and string literals'
		);
		$this->assert_true(
			false !== strpos( $norm_source_wp, "`is_active` tinyint(1) NOT NULL DEFAULT '1'" ),
			'Signed tinyint(1) retains (1) after normalization'
		);
		$this->assert_true(
			false !== strpos( $norm_source_wp, "`padded_code` int(5) unsigned zerofill NOT NULL DEFAULT '00000'" ),
			'ZEROFILL integer column retains display width after normalization'
		);
		$this->assert_true(
			false !== strpos( $norm_source_wp, "DEFAULT 'int(11) AUTO_INCREMENT=5'" ),
			'Single-quoted DEFAULT literal containing DDL-like substrings is never modified'
		);

		// 4. Legacy utf8 -> utf8mb3 alias on column and table options, and self-schema FK qualifier.
		$source_fk_utf8_ddl = "CREATE TABLE `wp_wc_download_log` (\n"
			. "  `download_log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "  `legacy_code` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'utf8_literal',\n"
			. "  `permission_id` bigint(20) unsigned NOT NULL,\n"
			. "  PRIMARY KEY (`download_log_id`),\n"
			. "  KEY `permission_id` (`permission_id`),\n"
			. "  CONSTRAINT `fk_wp_wc_download_log_permission` FOREIGN KEY (`permission_id`) REFERENCES `wp_woocommerce_downloadable_product_permissions` (`permission_id`) ON DELETE CASCADE\n"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

		$restored_fk_utf8mb3_ddl = "CREATE TABLE `wp_wc_download_log` (\n"
			. "  `download_log_id` bigint unsigned NOT NULL AUTO_INCREMENT,\n"
			. "  `legacy_code` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'utf8_literal',\n"
			. "  `permission_id` bigint unsigned NOT NULL,\n"
			. "  PRIMARY KEY (`download_log_id`),\n"
			. "  KEY `permission_id` (`permission_id`),\n"
			. "  CONSTRAINT `fk_wp_wc_download_log_permission` FOREIGN KEY (`permission_id`) REFERENCES `gm2_restore_0123456789abcdef`.`wp_woocommerce_downloadable_product_permissions` (`permission_id`) ON DELETE CASCADE\n"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci";

		$this->assert_same(
			gm2_normalize_create_table_ddl( $source_fk_utf8_ddl, 'local' ),
			gm2_normalize_create_table_ddl( $restored_fk_utf8mb3_ddl, 'gm2_restore_0123456789abcdef' ),
			'MySQL utf8->utf8mb3 alias and scratch self-schema FK qualifier normalize equal'
		);
	}

	/**
	 * Suite 2: Meaningful changes to columns, types, signedness, nullability, defaults, indexes, engine, and collation remain unequal.
	 */
	private function suite_meaningful_structural_ddl_changes_remain_unequal() {
		++$this->suites_run;

		$baseline_ddl = "CREATE TABLE `wp_gridmaster_grids` (\n"
			. "  `id` mediumint(9) NOT NULL AUTO_INCREMENT,\n"
			. "  `title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n"
			. "  `attributes` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n"
			. "  `dated` datetime DEFAULT CURRENT_TIMESTAMP,\n"
			. "  `lifecycle_state` varchar(16) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'active',\n"
			. "  `definition_post_id` bigint(20) unsigned DEFAULT NULL,\n"
			. "  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',\n"
			. "  PRIMARY KEY (`id`),\n"
			. "  UNIQUE KEY `definition_post_id` (`definition_post_id`),\n"
			. "  KEY `lifecycle_state_id` (`lifecycle_state`,`id`)\n"
			. ") ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC";

		$baseline_norm = gm2_normalize_create_table_ddl( $baseline_ddl );

		$mutations = array(
			'column_renamed' => str_replace( '`title` text', '`grid_title` text', $baseline_ddl ),
			'column_removed' => str_replace( "  `attributes` text COLLATE utf8mb4_unicode_520_ci NOT NULL,\n", '', $baseline_ddl ),
			'column_type_mediumint_to_int' => str_replace( '`id` mediumint(9)', '`id` int(11)', $baseline_ddl ),
			'column_type_mediumint_to_bigint' => str_replace( '`id` mediumint(9)', '`id` bigint(20)', $baseline_ddl ),
			'column_type_text_to_longtext' => str_replace( '`attributes` text', '`attributes` longtext', $baseline_ddl ),
			'column_type_varchar_length_changed' => str_replace( 'varchar(16)', 'varchar(24)', $baseline_ddl ),
			'column_type_tinyint1_to_tinyint4' => str_replace( '`is_enabled` tinyint(1)', '`is_enabled` tinyint(4)', $baseline_ddl ),
			'column_signedness_unsigned_removed' => str_replace( '`definition_post_id` bigint(20) unsigned', '`definition_post_id` bigint(20)', $baseline_ddl ),
			'column_signedness_unsigned_added' => str_replace( '`id` mediumint(9) NOT NULL', '`id` mediumint(9) unsigned NOT NULL', $baseline_ddl ),
			'column_nullability_title_nullable' => str_replace( '`title` text COLLATE utf8mb4_unicode_520_ci NOT NULL', '`title` text COLLATE utf8mb4_unicode_520_ci', $baseline_ddl ),
			'column_nullability_dated_not_null' => str_replace( '`dated` datetime DEFAULT CURRENT_TIMESTAMP', '`dated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', $baseline_ddl ),
			'column_default_changed' => str_replace( "DEFAULT 'active'", "DEFAULT 'trash'", $baseline_ddl ),
			'column_datetime_default_removed' => str_replace( 'DEFAULT CURRENT_TIMESTAMP', 'DEFAULT NULL', $baseline_ddl ),
			'index_primary_key_changed' => str_replace( 'PRIMARY KEY (`id`)', 'PRIMARY KEY (`id`,`lifecycle_state`)', $baseline_ddl ),
			'index_unique_to_non_unique' => str_replace( 'UNIQUE KEY `definition_post_id`', 'KEY `definition_post_id`', $baseline_ddl ),
			'index_columns_reordered' => str_replace( 'KEY `lifecycle_state_id` (`lifecycle_state`,`id`)', 'KEY `lifecycle_state_id` (`id`,`lifecycle_state`)', $baseline_ddl ),
			'index_dropped' => str_replace( ",\n  KEY `lifecycle_state_id` (`lifecycle_state`,`id`)", '', $baseline_ddl ),
			'table_engine_changed' => str_replace( 'ENGINE=InnoDB', 'ENGINE=MyISAM', $baseline_ddl ),
			'table_charset_utf8mb4_to_utf8mb3' => str_replace( 'DEFAULT CHARSET=utf8mb4', 'DEFAULT CHARSET=utf8mb3', $baseline_ddl ),
			'table_charset_utf8mb4_to_latin1' => str_replace( 'DEFAULT CHARSET=utf8mb4', 'DEFAULT CHARSET=latin1', $baseline_ddl ),
			'table_collation_520_to_unicode' => str_replace( 'COLLATE=utf8mb4_unicode_520_ci', 'COLLATE=utf8mb4_unicode_ci', $baseline_ddl ),
			'table_collation_520_to_0900' => str_replace( 'COLLATE=utf8mb4_unicode_520_ci', 'COLLATE=utf8mb4_0900_ai_ci', $baseline_ddl ),
			'column_collation_changed' => str_replace( '`title` text COLLATE utf8mb4_unicode_520_ci', '`title` text COLLATE utf8mb4_bin', $baseline_ddl ),
			'table_row_format_changed' => str_replace( 'ROW_FORMAT=DYNAMIC', 'ROW_FORMAT=COMPACT', $baseline_ddl ),
		);

		foreach ( $mutations as $case_name => $mutated_ddl ) {
			$mutated_norm = gm2_normalize_create_table_ddl( $mutated_ddl );
			$this->assert_not_same(
				$baseline_norm,
				$mutated_norm,
				"Meaningful DDL change ({$case_name}) must remain unequal after normalization"
			);
		}
	}

	/**
	 * Suite 3: Failure-path comparison collection, `not_run` reporting, and post-run source invariant checks.
	 */
	private function suite_failure_path_collection_and_not_run_reporting() {
		++$this->suites_run;

		// 1. Verify initial not_run maps never use false placeholders.
		$comp_keys = gm2_get_rehearsal_comparison_keys()['all'];
		$inv_keys  = gm2_get_source_invariant_keys();
		$init_comp = gm2_build_not_run_checks_map( $comp_keys, 'not_reached_before_abort' );
		$init_inv  = gm2_build_not_run_checks_map( $inv_keys, 'not_reached_before_abort' );

		$this->assert_same( 12, count( $init_comp['values'] ), 'All 12 comparison keys initialized' );
		$this->assert_same( 8, count( $init_inv['values'] ), 'All 8 source invariant keys initialized' );

		foreach ( $comp_keys as $k ) {
			$this->assert_same( 'not_run', $init_comp['values'][ $k ], "Comparison {$k} initializes to 'not_run', not false" );
			$this->assert_same( 'not_run', $init_comp['checks'][ $k ]['status'], "Comparison check {$k} status is 'not_run'" );
			$this->assert_same( null, $init_comp['checks'][ $k ]['passed'], "Comparison check {$k} passed is null when not_run" );
			$this->assert_same( 'not_reached_before_abort', $init_comp['checks'][ $k ]['reason'], "Comparison check {$k} has sanitized reason" );
		}

		$updated_checks = gm2_update_not_run_check_reasons( $init_comp['checks'], 'source_non_innodb_precheck_failed' );
		$this->assert_same(
			'source_non_innodb_precheck_failed',
			$updated_checks['table_schema_exact_match']['reason'],
			'gm2_update_not_run_check_reasons updates not_run check reasons to specific stage reason'
		);

		// 2. Verify gm2_evaluate_rehearsal_comparisons collects ALL failures without short-circuiting on the first mismatch.
		$base_snap = array(
			'table_list'            => array( 'wp_gridmaster_grids', 'wp_options' ),
			'table_count'           => 2,
			'table_engines'         => array( 'wp_gridmaster_grids' => 'InnoDB', 'wp_options' => 'InnoDB' ),
			'engine_counts'         => array( 'InnoDB' => 2 ),
			'non_innodb_tables'     => array(),
			'all_tables_innodb'     => true,
			'table_schemas_norm'    => array( 'wp_gridmaster_grids' => 'norm_a', 'wp_options' => 'norm_opt' ),
			'table_schemas_raw'     => array( 'wp_gridmaster_grids' => 'raw_a', 'wp_options' => 'raw_opt' ),
			'table_row_counts'      => array( 'wp_gridmaster_grids' => 0, 'wp_options' => 10 ),
			'aggregate_row_count'   => 10,
			'grids_schema_norm'     => 'norm_a',
			'grids_schema_raw'      => 'raw_a',
			'grids_row_count'       => 0,
			'grids_ids'             => array(),
			'grids_attr_hashes'     => array(),
			'grids_full_row_hashes' => array(),
			'grids_aggregate_hash'  => 'empty_grids_hash',
			'active_plugins_hash'   => 'plugins_hash_1',
		);

		$mismatched_scratch = $base_snap;
		$mismatched_scratch['table_schemas_norm']['wp_gridmaster_grids'] = 'norm_b_changed';
		$mismatched_scratch['table_schemas_raw']['wp_gridmaster_grids']  = 'raw_b_changed';
		$mismatched_scratch['grids_schema_norm']                         = 'norm_b_changed';
		$mismatched_scratch['active_plugins_hash']                       = 'plugins_hash_2_changed';

		$comp_eval = gm2_evaluate_rehearsal_comparisons( $base_snap, $mismatched_scratch );
		$this->assert_false( $comp_eval['all_required_passed'], 'Comparison evaluation reports failure when required comparisons fail' );
		$this->assert_same(
			array(
				'table_schema_exact_match',
				'grids_table_schema_exact_match',
				'active_plugins_option_hash_exact_match',
			),
			$comp_eval['failed_required'],
			'All 3 failing required comparisons are collected without short-circuiting on table_schema_exact_match'
		);
		$this->assert_same( 3, count( $comp_eval['errors'] ), 'Each failing required comparison records an explicit error entry' );
		$this->assert_same( 'failed', $comp_eval['comparison_checks']['table_schema_exact_match']['status'], 'Failed comparison has status=failed' );
		$this->assert_same( 'passed', $comp_eval['comparison_checks']['table_row_counts_exact_match']['status'], 'Passing comparison has status=passed' );

		// 3. Verify source post-run invariants still evaluate and pass when source DB is untouched after a failed comparison.
		$inv_pass = gm2_evaluate_source_invariants( $base_snap, $base_snap, 'local', 'local', 'local' );
		$this->assert_true( $inv_pass['all_passed'], 'Source invariants pass when source DB remained unchanged after failed scratch comparison' );
		foreach ( $inv_keys as $inv_k ) {
			$this->assert_true( $inv_pass['invariants'][ $inv_k ], "Source invariant {$inv_k} is true" );
			$this->assert_same( 'passed', $inv_pass['invariant_checks'][ $inv_k ]['status'], "Source invariant check {$inv_k} status is passed" );
		}

		// 4. Verify source post-run invariants collect multiple failures if source state changes.
		$mutated_source_post                        = $base_snap;
		$mutated_source_post['aggregate_row_count'] = 11;
		$mutated_source_post['active_plugins_hash'] = 'plugins_hash_mutated';

		$inv_fail = gm2_evaluate_source_invariants( $base_snap, $mutated_source_post, 'local', 'local', 'wrong_db' );
		$this->assert_false( $inv_fail['all_passed'], 'Source invariant evaluation fails when source state changes' );
		$this->assert_same(
			array(
				'db_identity_unchanged',
				'aggregate_row_count_unchanged',
				'active_plugins_option_hash_unchanged',
			),
			$inv_fail['failed_invariants'],
			'All failed source invariants are collected without short-circuiting'
		);
	}
}

$gm2_test_runner = new GM2_Backup_Normalizer_Test_Runner();
if ( isset( $argv ) && is_array( $argv ) && in_array( '--self-test-failure', $argv, true ) ) {
	exit( $gm2_test_runner->run_self_test_failure() );
}
exit( $gm2_test_runner->run_all() );

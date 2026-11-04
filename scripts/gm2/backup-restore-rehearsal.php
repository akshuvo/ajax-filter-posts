<?php
/**
 * Guarded local backup and disposable scratch-restore rehearsal utility (GM2-M2-T02).
 *
 * Runs only under WP-CLI (`PHP_SAPI === 'cli'` and `WP_CLI === true`), invoked with
 * `--skip-plugins --skip-themes` before `eval-file` so active plugins and themes are
 * not loaded during bootstrap and plugin/theme state is never modified.
 * Performs strictly read-only inspection and `mysqldump` against the active WordPress
 * source database, verifies before creating any dump or scratch database that every
 * source base table uses the transactional `InnoDB` storage engine (failing closed
 * with no source DB lock fallback), writes the full dump and SHA-256 metadata to a
 * verified private directory outside the WordPress root (0700 directory / 0600 files),
 * restores only into a randomly generated `gm2_restore_<hex>` scratch database verified
 * absent and created by this invocation, compares table lists, storage engines,
 * normalized `SHOW CREATE TABLE` schemas, per-table and aggregate row counts,
 * `active_plugins` option hash, and `{$wpdb->prefix}gridmaster_grids` IDs and raw
 * serialized `attributes` hashes, drops only the scratch database created by this
 * invocation, and re-verifies that the source database remained completely unchanged.
 *
 * @package GridMaster
 */

if ( 'cli' !== PHP_SAPI || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "Error: scripts/gm2/backup-restore-rehearsal.php must be executed under WP-CLI in CLI SAPI.\n" );
	exit( 1 );
}

require_once __DIR__ . '/ddl-normalizer.php';

if ( ! function_exists( 'gm2_backup_restore_rehearsal_main' ) ) {
	/**
	 * Parse explicit CLI arguments (`key=value` or `--key=value`) and environment variables.
	 *
	 * @param array $cli_args Positional arguments provided by WP-CLI `eval-file`.
	 * @return array<string, string>
	 */
	function gm2_rehearsal_parse_inputs( array $cli_args ) {
		$inputs = array(
			'output_dir'    => '',
			'mysqldump_bin' => '',
			'mysql_bin'     => '',
		);

		$env_map = array(
			'output_dir'    => array( 'GM2_BACKUP_DIR', 'GM2_PRIVATE_BACKUP_DIR', 'GM2_OUTPUT_DIR' ),
			'mysqldump_bin' => array( 'GM2_MYSQLDUMP_BIN' ),
			'mysql_bin'     => array( 'GM2_MYSQL_BIN' ),
		);

		foreach ( $env_map as $key => $env_names ) {
			foreach ( $env_names as $env_name ) {
				$val = getenv( $env_name );
				if ( is_string( $val ) && '' !== trim( $val ) ) {
					$inputs[ $key ] = trim( $val );
					break;
				}
			}
		}

		$arg_map = array(
			'output_dir'      => 'output_dir',
			'--output-dir'    => 'output_dir',
			'backup_dir'      => 'output_dir',
			'--backup-dir'    => 'output_dir',
			'mysqldump_bin'   => 'mysqldump_bin',
			'--mysqldump-bin' => 'mysqldump_bin',
			'mysql_bin'       => 'mysql_bin',
			'--mysql-bin'     => 'mysql_bin',
		);

		foreach ( $cli_args as $arg ) {
			if ( ! is_string( $arg ) || false === strpos( $arg, '=' ) ) {
				continue;
			}
			list( $raw_key, $raw_val ) = explode( '=', $arg, 2 );
			$raw_key                   = trim( $raw_key );
			$raw_val                   = trim( $raw_val );
			if ( isset( $arg_map[ $raw_key ] ) ) {
				$inputs[ $arg_map[ $raw_key ] ] = $raw_val;
			}
		}

		return $inputs;
	}

	/**
	 * Normalize a filesystem path lexically without requiring all leaf segments to exist.
	 *
	 * @param string $path Raw path.
	 * @return string|null Normalized absolute path, or null if invalid/relative/traversal.
	 */
	function gm2_rehearsal_normalize_abs_path( $path ) {
		if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, "\0" ) ) {
			return null;
		}
		if ( 0 !== strpos( $path, '/' ) ) {
			return null;
		}
		if ( 1 === preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) {
			return null;
		}
		$collapsed = preg_replace( '#/+#', '/', $path );
		if ( ! is_string( $collapsed ) || '' === $collapsed ) {
			return null;
		}
		return '/' === $collapsed ? '/' : rtrim( $collapsed, '/' );
	}

	/**
	 * Resolve a path through realpath on its deepest existing ancestor.
	 *
	 * @param string $normalized_path Lexical absolute path.
	 * @return string|null Canonical path with symlinks resolved on existing ancestor.
	 */
	function gm2_rehearsal_resolve_candidate_path( $normalized_path ) {
		$current = $normalized_path;
		$suffix  = array();

		while ( '/' !== $current && '' !== $current ) {
			if ( file_exists( $current ) ) {
				$real_base = realpath( $current );
				if ( ! is_string( $real_base ) || '' === $real_base ) {
					return null;
				}
				if ( empty( $suffix ) ) {
					return $real_base;
				}
				return rtrim( $real_base, '/' ) . '/' . implode( '/', array_reverse( $suffix ) );
			}
			$suffix[] = basename( $current );
			$parent   = dirname( $current );
			if ( $parent === $current ) {
				break;
			}
			$current = $parent;
		}

		return null;
	}

	/**
	 * Check whether $path_a is equal to or nested inside $path_b.
	 *
	 * @param string $path_a First canonical path.
	 * @param string $path_b Second canonical path.
	 * @return bool
	 */
	function gm2_rehearsal_path_is_within_or_equal( $path_a, $path_b ) {
		$a = '/' === $path_a ? '/' : rtrim( $path_a, '/' );
		$b = '/' === $path_b ? '/' : rtrim( $path_b, '/' );

		if ( $a === $b ) {
			return true;
		}
		if ( '/' === $b ) {
			return true;
		}
		return 0 === strpos( $a, $b . '/' );
	}

	/**
	 * Validate and prepare the private output directory outside all WordPress and plugin roots.
	 *
	 * @param string $raw_output_dir User-supplied output directory.
	 * @return string Canonical realpath of the verified mode-0700 private directory.
	 * @throws RuntimeException On any validation failure.
	 */
	function gm2_rehearsal_prepare_private_dir( $raw_output_dir ) {
		$normalized = gm2_rehearsal_normalize_abs_path( $raw_output_dir );
		if ( null === $normalized || '/' === $normalized ) {
			throw new RuntimeException( 'Private output_dir must be a non-root absolute path without traversal segments.' );
		}

		$resolved_candidate = gm2_rehearsal_resolve_candidate_path( $normalized );
		if ( null === $resolved_candidate ) {
			throw new RuntimeException( 'Unable to resolve ancestor path for private output_dir.' );
		}

		$forbidden_roots = array();
		if ( defined( 'ABSPATH' ) && is_string( ABSPATH ) ) {
			$real_abspath = realpath( ABSPATH );
			if ( false === $real_abspath ) {
				throw new RuntimeException( 'Unable to resolve ABSPATH realpath.' );
			}
			$forbidden_roots['abspath'] = $real_abspath;

			$abspath_parent = dirname( $real_abspath );
			if ( 'public' === basename( $real_abspath ) && 'app' === basename( $abspath_parent ) ) {
				$forbidden_roots['site_app_root'] = $abspath_parent;
			}
		} else {
			throw new RuntimeException( 'ABSPATH is not defined.' );
		}

		if ( defined( 'WP_CONTENT_DIR' ) && is_string( WP_CONTENT_DIR ) ) {
			$real_content = realpath( WP_CONTENT_DIR );
			if ( is_string( $real_content ) && '' !== $real_content ) {
				$forbidden_roots['wp_content'] = $real_content;
			}
		}

		$plugin_root = realpath( dirname( __DIR__, 2 ) );
		if ( is_string( $plugin_root ) && '' !== $plugin_root ) {
			$forbidden_roots['plugin_root']    = $plugin_root;
			$forbidden_roots['plugins_parent'] = dirname( $plugin_root );
		}

		foreach ( $forbidden_roots as $label => $root_path ) {
			if ( gm2_rehearsal_path_is_within_or_equal( $resolved_candidate, $root_path ) ) {
				throw new RuntimeException( sprintf( 'Private output_dir resolves inside forbidden root (%s).', $label ) );
			}
			if ( gm2_rehearsal_path_is_within_or_equal( $root_path, $resolved_candidate ) ) {
				throw new RuntimeException( sprintf( 'Private output_dir cannot be an ancestor of %s.', $label ) );
			}
		}

		if ( ! is_dir( $resolved_candidate ) ) {
			$old_umask = umask( 0077 );
			$created   = @mkdir( $resolved_candidate, 0700, true );
			umask( $old_umask );
			if ( ! $created && ! is_dir( $resolved_candidate ) ) {
				throw new RuntimeException( 'Failed to create private output_dir.' );
			}
		}

		if ( is_link( $resolved_candidate ) ) {
			throw new RuntimeException( 'Private output_dir must not be a symbolic link.' );
		}

		if ( ! @chmod( $resolved_candidate, 0700 ) ) {
			throw new RuntimeException( 'Failed to set mode 0700 on private output_dir.' );
		}

		$real_output_dir = realpath( $resolved_candidate );
		if ( ! is_string( $real_output_dir ) || '' === $real_output_dir || ! is_dir( $real_output_dir ) || ! is_writable( $real_output_dir ) ) {
			throw new RuntimeException( 'Private output_dir is not a writable directory after resolution.' );
		}

		foreach ( $forbidden_roots as $label => $root_path ) {
			if ( gm2_rehearsal_path_is_within_or_equal( $real_output_dir, $root_path ) || gm2_rehearsal_path_is_within_or_equal( $root_path, $real_output_dir ) ) {
				throw new RuntimeException( sprintf( 'Resolved private output_dir overlaps forbidden root (%s).', $label ) );
			}
		}

		clearstatcache( true, $real_output_dir );
		$perms = fileperms( $real_output_dir ) & 0777;
		if ( 0700 !== $perms ) {
			throw new RuntimeException( sprintf( 'Private output_dir permissions must be 0700, got %04o.', $perms ) );
		}

		return $real_output_dir;
	}

	/**
	 * Validate an explicitly supplied SQL client binary path.
	 *
	 * @param string $raw_bin       User-supplied binary path.
	 * @param array  $allowed_names Allowed binary basenames or prefixes.
	 * @param string $label         Diagnostic label.
	 * @return string Canonical executable binary path.
	 * @throws RuntimeException On invalid or non-executable binary.
	 */
	function gm2_rehearsal_validate_binary( $raw_bin, array $allowed_names, $label ) {
		if ( ! is_string( $raw_bin ) || '' === trim( $raw_bin ) || false !== strpos( $raw_bin, "\0" ) ) {
			throw new RuntimeException( sprintf( 'SQL binary %s must be explicitly provided.', $label ) );
		}
		if ( 0 !== strpos( $raw_bin, '/' ) ) {
			throw new RuntimeException( sprintf( 'SQL binary %s must be an absolute path.', $label ) );
		}
		$real_bin = realpath( $raw_bin );
		if ( ! is_string( $real_bin ) || ! is_file( $real_bin ) || ! is_executable( $real_bin ) ) {
			throw new RuntimeException( sprintf( 'SQL binary %s does not exist or is not executable.', $label ) );
		}
		$base = strtolower( basename( $real_bin ) );
		$ok   = false;
		foreach ( $allowed_names as $prefix ) {
			if ( $base === $prefix || 0 === strpos( $base, $prefix . '.' ) || 0 === strpos( $base, $prefix . '-' ) ) {
				$ok = true;
				break;
			}
		}
		if ( ! $ok ) {
			throw new RuntimeException( sprintf( 'SQL binary %s basename (%s) is not in the allowed client list.', $label, $base ) );
		}
		return $real_bin;
	}

	/**
	 * Execute an external binary directly without a shell using proc_open array syntax.
	 *
	 * @param array<int, string> $argv       Command vector (element 0 is executable path).
	 * @param string|null        $stdin_file Optional file path to bind as STDIN.
	 * @return array{exit_code:int, stdout:string, stderr:string}
	 * @throws RuntimeException If process launch fails.
	 */
	function gm2_rehearsal_run_process( array $argv, $stdin_file = null ) {
		if ( empty( $argv ) || ! is_string( $argv[0] ) || '' === $argv[0] ) {
			throw new RuntimeException( 'Process command vector must be non-empty.' );
		}

		$descriptors = array(
			0 => null !== $stdin_file ? array( 'file', $stdin_file, 'r' ) : array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$pipes   = array();
		$process = @proc_open( $argv, $descriptors, $pipes, null, null, array( 'bypass_shell' => true ) );
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Failed to spawn process via proc_open.' );
		}

		if ( null === $stdin_file && isset( $pipes[0] ) && is_resource( $pipes[0] ) ) {
			fclose( $pipes[0] );
		}

		$stdout = isset( $pipes[1] ) && is_resource( $pipes[1] ) ? (string) stream_get_contents( $pipes[1] ) : '';
		if ( isset( $pipes[1] ) && is_resource( $pipes[1] ) ) {
			fclose( $pipes[1] );
		}

		$stderr = isset( $pipes[2] ) && is_resource( $pipes[2] ) ? (string) stream_get_contents( $pipes[2] ) : '';
		if ( isset( $pipes[2] ) && is_resource( $pipes[2] ) ) {
			fclose( $pipes[2] );
		}

		$exit_code = proc_close( $process );

		return array(
			'exit_code' => (int) $exit_code,
			'stdout'    => $stdout,
			'stderr'    => $stderr,
		);
	}

	/**
	 * Parse DB_HOST into host, port, and socket parameters compatible with mysqli and MySQL client tools.
	 *
	 * @param object $wpdb Active wpdb instance.
	 * @return array{host:string, port:int|null, socket:string|null}
	 */
	function gm2_rehearsal_parse_db_endpoint( $wpdb ) {
		$host   = 'localhost';
		$port   = null;
		$socket = null;

		if ( method_exists( $wpdb, 'parse_db_host' ) ) {
			$parsed = $wpdb->parse_db_host( DB_HOST );
			if ( is_array( $parsed ) ) {
				$host   = isset( $parsed[0] ) && is_string( $parsed[0] ) && '' !== $parsed[0] ? $parsed[0] : 'localhost';
				$port   = isset( $parsed[1] ) && null !== $parsed[1] && '' !== (string) $parsed[1] ? (int) $parsed[1] : null;
				$socket = isset( $parsed[2] ) && is_string( $parsed[2] ) && '' !== $parsed[2] ? $parsed[2] : null;
			}
		} else {
			$raw = (string) DB_HOST;
			if ( false !== strpos( $raw, ':/' ) ) {
				list( $h, $s ) = explode( ':', $raw, 2 );
				$host          = '' !== $h ? $h : 'localhost';
				$socket        = $s;
			} elseif ( false !== strpos( $raw, ':' ) ) {
				list( $h, $p ) = explode( ':', $raw, 2 );
				$host          = '' !== $h ? $h : '127.0.0.1';
				$port          = (int) $p;
			} elseif ( '' !== $raw ) {
				$host = $raw;
			}
		}

		if ( null === $socket && ( 'localhost' === $host || '' === $host ) ) {
			$ini_socket = ini_get( 'mysqli.default_socket' );
			if ( is_string( $ini_socket ) && '' !== trim( $ini_socket ) && file_exists( trim( $ini_socket ) ) ) {
				$socket = trim( $ini_socket );
			} else {
				$env_socket = getenv( 'MYSQL_UNIX_PORT' );
				if ( is_string( $env_socket ) && '' !== trim( $env_socket ) && file_exists( trim( $env_socket ) ) ) {
					$socket = trim( $env_socket );
				}
			}
		}

		return array(
			'host'   => $host,
			'port'   => $port,
			'socket' => $socket,
		);
	}

	/**
	 * Escape a scalar string for a MySQL option file double-quoted value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function gm2_rehearsal_escape_cnf_value( $value ) {
		return '"' . str_replace(
			array( '\\', '"', "\n", "\r" ),
			array( '\\\\', '\"', '\n', '\r' ),
			(string) $value
		) . '"';
	}

	/**
	 * Exclusively create and write a mode-0600 file inside the private directory without overwriting existing files.
	 *
	 * @param string $file_path Target file path (must not exist).
	 * @param string $content   Byte payload to write.
	 * @param string $label     Diagnostic label for error messages.
	 * @return void
	 * @throws RuntimeException If the target path already exists or cannot be exclusively created with mode 0600.
	 */
	function gm2_rehearsal_write_exclusive_private_file( $file_path, $content, $label ) {
		if ( ! is_string( $file_path ) || '' === $file_path || false !== strpos( $file_path, "\0" ) ) {
			throw new RuntimeException( sprintf( 'Invalid file path for %s.', $label ) );
		}

		clearstatcache( true, $file_path );
		if ( file_exists( $file_path ) || is_link( $file_path ) ) {
			throw new RuntimeException( sprintf( 'Target %s already exists; refusing to overwrite existing artifact.', $label ) );
		}

		$old_umask = umask( 0077 );
		$fh        = @fopen( $file_path, 'xb' );
		umask( $old_umask );

		if ( ! is_resource( $fh ) ) {
			throw new RuntimeException( sprintf( 'Failed to exclusively create %s (fopen mode xb).', $label ) );
		}

		$payload  = (string) $content;
		$expected = strlen( $payload );
		$written  = 0;

		try {
			while ( $written < $expected ) {
				$chunk = fwrite( $fh, substr( $payload, $written ) );
				if ( false === $chunk || 0 === $chunk ) {
					throw new RuntimeException( sprintf( 'Failed to write complete payload to %s.', $label ) );
				}
				$written += $chunk;
			}
			fflush( $fh );
		} finally {
			fclose( $fh );
		}

		if ( ! is_file( $file_path ) || is_link( $file_path ) || ! @chmod( $file_path, 0600 ) ) {
			throw new RuntimeException( sprintf( 'Failed to enforce mode 0600 on %s.', $label ) );
		}

		clearstatcache( true, $file_path );
		$perms = fileperms( $file_path ) & 0777;
		if ( 0600 !== $perms ) {
			throw new RuntimeException( sprintf( 'File %s permissions must be 0600, got %04o.', $label, $perms ) );
		}
	}

	/**
	 * Verify that none of the target private backup artifact paths (.sql, .sql.sha256, .meta.json) exist.
	 *
	 * @param array<string, string> $artifact_paths Map of artifact label to absolute path.
	 * @return void
	 * @throws RuntimeException If any artifact path already exists or is a symlink.
	 */
	function gm2_rehearsal_assert_artifacts_absent( array $artifact_paths ) {
		foreach ( $artifact_paths as $artifact_label => $artifact_path ) {
			if ( ! is_string( $artifact_path ) || '' === $artifact_path || false !== strpos( $artifact_path, "\0" ) ) {
				throw new RuntimeException( sprintf( 'Invalid private backup artifact path for %s.', $artifact_label ) );
			}
			clearstatcache( true, $artifact_path );
			if ( file_exists( $artifact_path ) || is_link( $artifact_path ) ) {
				throw new RuntimeException(
					sprintf( 'Target private backup artifact (%s) already exists; refusing to overwrite.', $artifact_label )
				);
			}
		}
	}

	/**
	 * Write a mode-0600 temporary MySQL client defaults file inside the private directory.
	 *
	 * @param string                                             $private_dir Canonical private directory.
	 * @param array{host:string, port:int|null, socket:string|null} $endpoint    Parsed endpoint.
	 * @return string Path to the temporary `.cnf` file.
	 * @throws RuntimeException On write or permission failure.
	 */
	function gm2_rehearsal_write_temp_cnf( $private_dir, array $endpoint ) {
		$cnf_path = $private_dir . '/.gm2-client-' . bin2hex( random_bytes( 8 ) ) . '.cnf';
		$lines    = array(
			'[client]',
			'user=' . gm2_rehearsal_escape_cnf_value( DB_USER ),
			'password=' . gm2_rehearsal_escape_cnf_value( DB_PASSWORD ),
		);

		if ( null !== $endpoint['socket'] && '' !== $endpoint['socket'] ) {
			$lines[] = 'host=' . gm2_rehearsal_escape_cnf_value( 'localhost' );
			$lines[] = 'protocol=socket';
			$lines[] = 'socket=' . gm2_rehearsal_escape_cnf_value( $endpoint['socket'] );
		} else {
			$lines[] = 'host=' . gm2_rehearsal_escape_cnf_value( $endpoint['host'] );
			$lines[] = 'protocol=tcp';
			if ( null !== $endpoint['port'] && $endpoint['port'] > 0 ) {
				$lines[] = 'port=' . (int) $endpoint['port'];
			}
		}

		$content = implode( "\n", $lines ) . "\n";
		gm2_rehearsal_write_exclusive_private_file(
			$cnf_path,
			$content,
			'temporary MySQL client configuration file'
		);

		return $cnf_path;
	}

	/**
	 * Normalize `SHOW CREATE TABLE` DDL for structural comparison across dump/restore.
	 * Delegates to `gm2_normalize_create_table_ddl()` in `scripts/gm2/ddl-normalizer.php`.
	 *
	 * @param string      $create_sql  Raw `SHOW CREATE TABLE` statement.
	 * @param string|null $schema_name Optional owning schema name for self-referential FK normalization.
	 * @return string
	 */
	function gm2_rehearsal_normalize_create_table( $create_sql, $schema_name = null ) {
		return gm2_normalize_create_table_ddl( $create_sql, $schema_name );
	}

	/**
	 * Quote a validated SQL identifier with backticks.
	 *
	 * @param string $identifier Table or schema identifier.
	 * @return string
	 * @throws RuntimeException If identifier contains null bytes or control characters.
	 */
	function gm2_rehearsal_quote_ident( $identifier ) {
		if ( ! is_string( $identifier ) || '' === $identifier || false !== strpos( $identifier, "\0" ) ) {
			throw new RuntimeException( 'Invalid SQL identifier.' );
		}
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Check whether a schema exists in INFORMATION_SCHEMA.SCHEMATA.
	 *
	 * @param mysqli $mysqli      Active mysqli connection.
	 * @param string $schema_name Schema name to check.
	 * @return bool
	 * @throws RuntimeException On query error.
	 */
	function gm2_rehearsal_schema_exists( mysqli $mysqli, $schema_name ) {
		$stmt = $mysqli->prepare( 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1' );
		if ( ! $stmt ) {
			throw new RuntimeException( 'Failed to prepare INFORMATION_SCHEMA.SCHEMATA existence check.' );
		}
		$stmt->bind_param( 's', $schema_name );
		if ( ! $stmt->execute() ) {
			$stmt->close();
			throw new RuntimeException( 'Failed to execute INFORMATION_SCHEMA.SCHEMATA existence check.' );
		}
		$res = $stmt->get_result();
		$row = $res ? $res->fetch_assoc() : null;
		$stmt->close();
		return is_array( $row ) && isset( $row['SCHEMA_NAME'] );
	}

	/**
	 * Capture a read-only structural and integrity snapshot of a database schema.
	 *
	 * @param mysqli $mysqli       Active mysqli connection.
	 * @param string $schema_name  Target database schema name.
	 * @param string $table_prefix WordPress table prefix (`$wpdb->prefix`).
	 * @return array<string, mixed>
	 * @throws RuntimeException On any query or missing-table error.
	 */
	function gm2_rehearsal_capture_db_snapshot( mysqli $mysqli, $schema_name, $table_prefix ) {
		$quoted_schema = gm2_rehearsal_quote_ident( $schema_name );

		$prev_db_res = $mysqli->query( 'SELECT DATABASE()' );
		$prev_db_row = $prev_db_res instanceof mysqli_result ? $prev_db_res->fetch_row() : null;
		if ( $prev_db_res instanceof mysqli_result ) {
			$prev_db_res->free();
		}
		$previous_db = is_array( $prev_db_row ) && isset( $prev_db_row[0] ) && is_string( $prev_db_row[0] )
			? $prev_db_row[0]
			: '';

		if ( '' !== $schema_name && $previous_db !== $schema_name ) {
			@$mysqli->select_db( $schema_name );
		}

		try {
			$tables_res = $mysqli->query( "SHOW FULL TABLES FROM {$quoted_schema} WHERE Table_type = 'BASE TABLE'" );
			if ( ! ( $tables_res instanceof mysqli_result ) ) {
				throw new RuntimeException( 'Failed to list base tables during snapshot capture.' );
			}

			$tables = array();
			while ( $row = $tables_res->fetch_row() ) {
				if ( isset( $row[0] ) && is_string( $row[0] ) ) {
					$tables[] = $row[0];
				}
			}
			$tables_res->free();
			sort( $tables, SORT_STRING );

			if ( empty( $tables ) ) {
				throw new RuntimeException( 'No base tables found in schema during snapshot capture.' );
			}

			$engine_stmt = $mysqli->prepare(
				"SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
			);
			if ( ! $engine_stmt ) {
				throw new RuntimeException( 'Failed to prepare INFORMATION_SCHEMA.TABLES engine inspection query.' );
			}
			$engine_stmt->bind_param( 's', $schema_name );
			if ( ! $engine_stmt->execute() ) {
				$engine_stmt->close();
				throw new RuntimeException( 'Failed to execute INFORMATION_SCHEMA.TABLES engine inspection query.' );
			}
			$engine_res  = $engine_stmt->get_result();
			$raw_engines = array();
			if ( $engine_res instanceof mysqli_result ) {
				while ( $erow = $engine_res->fetch_row() ) {
					if ( isset( $erow[0] ) && is_string( $erow[0] ) ) {
						$raw_engines[ $erow[0] ] = isset( $erow[1] ) && is_string( $erow[1] ) ? trim( $erow[1] ) : '';
					}
				}
				$engine_res->free();
			}
			$engine_stmt->close();

			$table_engines         = array();
			$engine_counts         = array();
			$non_innodb_tables     = array();
			$table_schemas_norm    = array();
			$table_schemas_raw     = array();
			$table_schemas_raw_ddl = array();
			$table_row_counts      = array();
			$aggregate_rows        = 0;

			foreach ( $tables as $table_name ) {
				$engine_name = isset( $raw_engines[ $table_name ] ) ? $raw_engines[ $table_name ] : '';
				if ( '' === $engine_name ) {
					throw new RuntimeException( sprintf( 'Missing storage engine metadata for base table %s.', $table_name ) );
				}
				$table_engines[ $table_name ] = $engine_name;
				if ( ! isset( $engine_counts[ $engine_name ] ) ) {
					$engine_counts[ $engine_name ] = 0;
				}
				++$engine_counts[ $engine_name ];
				if ( 0 !== strcasecmp( 'InnoDB', $engine_name ) ) {
					$non_innodb_tables[ $table_name ] = $engine_name;
				}

				$quoted_table = $quoted_schema . '.' . gm2_rehearsal_quote_ident( $table_name );

				$create_res = $mysqli->query( "SHOW CREATE TABLE {$quoted_table}" );
				if ( ! ( $create_res instanceof mysqli_result ) ) {
					throw new RuntimeException( sprintf( 'Failed SHOW CREATE TABLE on %s.', $table_name ) );
				}
				$create_row = $create_res->fetch_row();
				$create_res->free();
				if ( ! is_array( $create_row ) || ! isset( $create_row[1] ) ) {
					throw new RuntimeException( sprintf( 'Unexpected SHOW CREATE TABLE result on %s.', $table_name ) );
				}
				$raw_ddl                              = (string) $create_row[1];
				$table_schemas_raw[ $table_name ]     = hash( 'sha256', $raw_ddl );
				$table_schemas_norm[ $table_name ]    = hash( 'sha256', gm2_rehearsal_normalize_create_table( $raw_ddl, $schema_name ) );
				$table_schemas_raw_ddl[ $table_name ] = $raw_ddl;

				$count_res = $mysqli->query( "SELECT COUNT(*) FROM {$quoted_table}" );
				if ( ! ( $count_res instanceof mysqli_result ) ) {
					throw new RuntimeException( sprintf( 'Failed SELECT COUNT(*) on %s.', $table_name ) );
				}
				$count_row = $count_res->fetch_row();
				$count_res->free();
				$row_count                       = isset( $count_row[0] ) ? (int) $count_row[0] : 0;
				$table_row_counts[ $table_name ] = $row_count;
				$aggregate_rows                 += $row_count;
			}
			ksort( $engine_counts, SORT_STRING );
			$all_tables_innodb = empty( $non_innodb_tables ) && count( $table_engines ) === count( $tables );

			$grids_table = $table_prefix . 'gridmaster_grids';
			if ( ! in_array( $grids_table, $tables, true ) ) {
				throw new RuntimeException( sprintf( 'Required table %s is missing from schema.', $grids_table ) );
			}

			$quoted_grids = $quoted_schema . '.' . gm2_rehearsal_quote_ident( $grids_table );
			$grids_res    = $mysqli->query( "SELECT id, title, attributes, dated FROM {$quoted_grids} ORDER BY id ASC" );
			if ( ! ( $grids_res instanceof mysqli_result ) ) {
				throw new RuntimeException( 'Failed to read gridmaster_grids rows during snapshot capture.' );
			}

			$grid_ids              = array();
			$grid_attr_hashes      = array();
			$grid_full_row_hashes  = array();
			$grids_aggregate_parts = array();

			while ( $grid_row = $grids_res->fetch_assoc() ) {
				$id_str     = (string) $grid_row['id'];
				$title_str  = (string) ( $grid_row['title'] ?? '' );
				$attr_bytes = (string) ( $grid_row['attributes'] ?? '' );
				$dated_str  = null === $grid_row['dated'] ? 'NULL' : (string) $grid_row['dated'];

				$grid_ids[]                      = (int) $grid_row['id'];
				$attr_hash                       = hash( 'sha256', $attr_bytes );
				$grid_attr_hashes[ $id_str ]     = $attr_hash;
				$envelope                        = strlen( $id_str ) . ':' . $id_str . '|'
					. strlen( $title_str ) . ':' . $title_str . '|'
					. strlen( $attr_bytes ) . ':' . $attr_bytes . '|'
					. strlen( $dated_str ) . ':' . $dated_str;
				$row_hash                        = hash( 'sha256', $envelope );
				$grid_full_row_hashes[ $id_str ] = $row_hash;
				$grids_aggregate_parts[]         = $id_str . ':' . $row_hash;
			}
			$grids_res->free();

			$options_table       = $table_prefix . 'options';
			$active_plugins_hash = null;
			if ( in_array( $options_table, $tables, true ) ) {
				$quoted_options = $quoted_schema . '.' . gm2_rehearsal_quote_ident( $options_table );
				$opt_res        = $mysqli->query( "SELECT option_value FROM {$quoted_options} WHERE option_name = 'active_plugins' LIMIT 1" );
				if ( $opt_res instanceof mysqli_result ) {
					$opt_row = $opt_res->fetch_assoc();
					$opt_res->free();
					$opt_val             = is_array( $opt_row ) && isset( $opt_row['option_value'] ) ? (string) $opt_row['option_value'] : '';
					$active_plugins_hash = hash( 'sha256', $opt_val );
				}
			}
			if ( null === $active_plugins_hash ) {
				throw new RuntimeException( 'Failed to hash active_plugins option during snapshot capture.' );
			}

			return array(
				'table_list'            => $tables,
				'table_count'           => count( $tables ),
				'table_engines'         => $table_engines,
				'engine_counts'         => $engine_counts,
				'non_innodb_tables'     => $non_innodb_tables,
				'all_tables_innodb'     => $all_tables_innodb,
				'table_schemas_norm'    => $table_schemas_norm,
				'table_schemas_raw'     => $table_schemas_raw,
				'table_schemas_raw_ddl' => $table_schemas_raw_ddl,
				'schema_norm_aggregate' => hash( 'sha256', wp_json_encode( $table_schemas_norm ) ),
				'table_row_counts'      => $table_row_counts,
				'aggregate_row_count'   => $aggregate_rows,
				'grids_table_name'      => $grids_table,
				'grids_schema_norm'     => $table_schemas_norm[ $grids_table ],
				'grids_schema_raw'      => $table_schemas_raw[ $grids_table ],
				'grids_row_count'       => count( $grid_ids ),
				'grids_ids'             => $grid_ids,
				'grids_attr_hashes'     => $grid_attr_hashes,
				'grids_full_row_hashes' => $grid_full_row_hashes,
				'grids_aggregate_hash'  => hash( 'sha256', implode( ';', $grids_aggregate_parts ) ),
				'active_plugins_hash'   => $active_plugins_hash,
			);
		} finally {
			if ( '' !== $previous_db && $previous_db !== $schema_name ) {
				@$mysqli->select_db( $previous_db );
			}
		}
	}

	/**
	 * Inspect dump file headers/statements before restore to prove it contains no
	 * `USE`, `CREATE DATABASE`, or `DROP DATABASE` statements targeting the source DB.
	 *
	 * @param string $dump_path Path to the generated SQL dump file.
	 * @return void
	 * @throws RuntimeException If the dump file is empty or contains database-switching DDL.
	 */
	function gm2_rehearsal_verify_dump_safety( $dump_path ) {
		if ( ! is_file( $dump_path ) || filesize( $dump_path ) <= 0 ) {
			throw new RuntimeException( 'Generated SQL dump file is missing or empty.' );
		}

		$fh = @fopen( $dump_path, 'rb' );
		if ( ! is_resource( $fh ) ) {
			throw new RuntimeException( 'Unable to open SQL dump file for safety verification.' );
		}

		$has_create_table = false;
		try {
			while ( ! feof( $fh ) ) {
				$line = fgets( $fh );
				if ( false === $line ) {
					break;
				}
				$trimmed = ltrim( $line );
				if ( 0 === stripos( $trimmed, 'CREATE TABLE' ) ) {
					$has_create_table = true;
				}
				if ( 1 === preg_match( '/^(USE|CREATE\s+DATABASE|CREATE\s+SCHEMA|DROP\s+DATABASE|DROP\s+SCHEMA)\b/i', $trimmed ) ) {
					throw new RuntimeException( 'SQL dump contains forbidden database-level statement (USE/CREATE DATABASE/DROP DATABASE).' );
				}
			}
		} finally {
			fclose( $fh );
		}

		if ( ! $has_create_table ) {
			throw new RuntimeException( 'SQL dump verification failed: no CREATE TABLE statements found.' );
		}
	}

	/**
	 * Main entry point for the backup and scratch-restore rehearsal utility.
	 *
	 * @param array $cli_args Positional arguments from WP-CLI `eval-file`.
	 * @return int Exit code (`0` on full verified pass, nonzero on any failure).
	 */
	function gm2_backup_restore_rehearsal_main( array $cli_args ) {
		$comp_key_meta = gm2_get_rehearsal_comparison_keys();
		$init_comp     = gm2_build_not_run_checks_map( $comp_key_meta['all'], 'not_reached_before_abort' );
		$init_inv      = gm2_build_not_run_checks_map( gm2_get_source_invariant_keys(), 'not_reached_before_abort' );

		$report = array(
			'task_id'                                  => 'GM2-M2-T02',
			'status'                                   => 'failed',
			'sapi'                                     => PHP_SAPI,
			'wp_cli'                                   => defined( 'WP_CLI' ) && WP_CLI,
			'scratch_db_name'                          => null,
			'scratch_db_confirmed_absent_before_create' => false,
			'scratch_db_created'                       => false,
			'scratch_db_dropped_verified'              => false,
			'cleanup_check'                            => array(
				'status' => 'not_run',
				'passed' => null,
				'reason' => 'scratch_db_not_created',
			),
			'backup_retained_outside_web_root'         => false,
			'backup_directory_mode'                    => null,
			'backup_file_mode'                         => null,
			'backup_size_bytes'                        => 0,
			'backup_sha256_recorded_privately'         => false,
			'source_table_count'                       => 0,
			'source_table_engines_all_innodb'          => false,
			'source_table_engine_counts'               => array(),
			'source_non_innodb_table_count'            => 0,
			'scratch_table_engines_all_innodb'         => false,
			'scratch_table_engine_counts'              => array(),
			'scratch_non_innodb_table_count'           => 0,
			'source_aggregate_row_count'               => 0,
			'source_grids_row_count'                   => 0,
			'source_saved_grid_render_comparison'      => null,
			'schema_comparison_summary'                => null,
			'comparisons'                              => $init_comp['values'],
			'comparison_checks'                        => $init_comp['checks'],
			'source_invariants_before_after'           => $init_inv['values'],
			'source_invariant_checks'                  => $init_inv['checks'],
			'errors'                                   => array(),
		);

		$mysqli                   = null;
		$temp_cnf_path            = null;
		$created_scratch_db       = null;
		$source_db                = null;
		$table_prefix             = null;
		$pre_source_identity      = null;
		$source_pre               = null;
		$scratch_snap             = null;
		$comparisons_evaluated    = false;
		$all_required_comp_passed = false;
		$invariants_evaluated     = false;
		$all_invariants_passed    = false;
		$comp_not_run_reason      = 'precheck_failed_before_source_snapshot';
		$inv_not_run_reason       = 'precheck_failed_before_source_snapshot';
		$timestamp                = null;
		$dump_path                = null;
		$dump_size                = 0;
		$dump_sha256              = null;
		$meta_path                = null;
		$scratch_db               = null;

		try {
			// 1. Validate required WordPress DB constants and mysqli availability.
			if ( ! extension_loaded( 'mysqli' ) || ! class_exists( 'mysqli', false ) ) {
				throw new RuntimeException( 'Required PHP mysqli extension is not available.' );
			}
			if ( ! defined( 'DB_NAME' ) || ! is_string( DB_NAME ) || '' === trim( DB_NAME ) ) {
				throw new RuntimeException( 'DB_NAME constant is missing or empty.' );
			}
			if ( ! defined( 'DB_USER' ) || ! is_string( DB_USER ) || '' === trim( DB_USER ) ) {
				throw new RuntimeException( 'DB_USER constant is missing or empty.' );
			}
			if ( ! defined( 'DB_PASSWORD' ) || ! is_string( DB_PASSWORD ) ) {
				throw new RuntimeException( 'DB_PASSWORD constant is not defined.' );
			}
			if ( ! defined( 'DB_HOST' ) || ! is_string( DB_HOST ) || '' === trim( DB_HOST ) ) {
				throw new RuntimeException( 'DB_HOST constant is missing or empty.' );
			}
			if ( ! isset( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) || empty( $GLOBALS['wpdb']->prefix ) ) {
				throw new RuntimeException( 'Global $wpdb is not initialized.' );
			}

			$wpdb         = $GLOBALS['wpdb'];
			$source_db    = DB_NAME;
			$table_prefix = (string) $wpdb->prefix;

			// 2. Validate explicit inputs, private directory outside web root, and SQL client binaries.
			$inputs        = gm2_rehearsal_parse_inputs( $cli_args );
			$private_dir   = gm2_rehearsal_prepare_private_dir( $inputs['output_dir'] );
			$mysqldump_bin = gm2_rehearsal_validate_binary(
				$inputs['mysqldump_bin'],
				array( 'mysqldump', 'mariadb-dump' ),
				'mysqldump_bin'
			);
			$mysql_bin     = gm2_rehearsal_validate_binary(
				$inputs['mysql_bin'],
				array( 'mysql', 'mariadb' ),
				'mysql_bin'
			);

			$report['backup_directory_mode'] = sprintf( '%04o', fileperms( $private_dir ) & 0777 );

			// 3. Generate and validate scratch DB name against strict allowlist.
			$scratch_db = 'gm2_restore_' . bin2hex( random_bytes( 8 ) );
			if ( 1 !== preg_match( '/^gm2_restore_[0-9a-f]{16,32}$/', $scratch_db ) ) {
				throw new RuntimeException( 'Generated scratch DB name failed allowlist pattern check.' );
			}
			if ( strtolower( $scratch_db ) === strtolower( $source_db ) ) {
				throw new RuntimeException( 'Generated scratch DB name collided with source DB name.' );
			}
			$report['scratch_db_name'] = $scratch_db;

			// 4. Verify binaries execute cleanly and inspect mysqldump supported flags without shell.
			$ver_dump = gm2_rehearsal_run_process( array( $mysqldump_bin, '--version' ) );
			if ( 0 !== $ver_dump['exit_code'] ) {
				throw new RuntimeException( 'mysqldump --version check failed.' );
			}
			$ver_mysql = gm2_rehearsal_run_process( array( $mysql_bin, '--version' ) );
			if ( 0 !== $ver_mysql['exit_code'] ) {
				throw new RuntimeException( 'mysql --version check failed.' );
			}
			$help_dump = gm2_rehearsal_run_process( array( $mysqldump_bin, '--help' ) );
			$help_text = $help_dump['stdout'] . "\n" . $help_dump['stderr'];

			// 5. Connect via mysqli to verify source DB and confirm scratch DB absence.
			mysqli_report( MYSQLI_REPORT_OFF );
			$endpoint = gm2_rehearsal_parse_db_endpoint( $wpdb );
			$mysqli   = mysqli_init();
			if ( ! ( $mysqli instanceof mysqli ) ) {
				throw new RuntimeException( 'mysqli_init() failed.' );
			}

			$connect_host   = null !== $endpoint['socket'] ? 'localhost' : $endpoint['host'];
			$connect_port   = null !== $endpoint['port'] ? (int) $endpoint['port'] : 0;
			$connect_socket = null !== $endpoint['socket'] ? $endpoint['socket'] : '';

			$connected = @$mysqli->real_connect(
				$connect_host,
				DB_USER,
				DB_PASSWORD,
				$source_db,
				$connect_port,
				$connect_socket
			);
			if ( ! $connected || $mysqli->connect_errno ) {
				throw new RuntimeException( 'Failed to connect to MySQL server via mysqli.' );
			}
			$mysqli->set_charset( 'utf8mb4' );

			$db_ident_res = $mysqli->query( 'SELECT DATABASE()' );
			$db_ident_row = $db_ident_res instanceof mysqli_result ? $db_ident_res->fetch_row() : null;
			if ( $db_ident_res instanceof mysqli_result ) {
				$db_ident_res->free();
			}
			$pre_source_identity = is_array( $db_ident_row ) && isset( $db_ident_row[0] ) ? (string) $db_ident_row[0] : '';
			if ( $pre_source_identity !== $source_db ) {
				throw new RuntimeException( 'Active MySQL database identity does not match DB_NAME.' );
			}

			// Confirm scratch DB does NOT exist before any backup/create operation.
			if ( gm2_rehearsal_schema_exists( $mysqli, $scratch_db ) ) {
				throw new RuntimeException( 'Scratch database already exists prior to CREATE; aborting fail-closed.' );
			}
			$report['scratch_db_confirmed_absent_before_create'] = true;

			// Query source DB default charset and collation so the scratch DB matches.
			$charset_stmt = $mysqli->prepare(
				'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1'
			);
			$source_charset   = 'utf8mb4';
			$source_collation = 'utf8mb4_unicode_ci';
			$charset_resolved = false;
			if ( $charset_stmt ) {
				$charset_stmt->bind_param( 's', $source_db );
				if ( $charset_stmt->execute() ) {
					$charset_res = $charset_stmt->get_result();
					$charset_row = $charset_res ? $charset_res->fetch_assoc() : null;
					if ( is_array( $charset_row ) ) {
						$charset_row = array_change_key_case( $charset_row, CASE_UPPER );
						if (
							isset( $charset_row['DEFAULT_CHARACTER_SET_NAME'], $charset_row['DEFAULT_COLLATION_NAME'] )
							&& 1 === preg_match( '/^[a-zA-Z0-9_]+$/', (string) $charset_row['DEFAULT_CHARACTER_SET_NAME'] )
							&& 1 === preg_match( '/^[a-zA-Z0-9_]+$/', (string) $charset_row['DEFAULT_COLLATION_NAME'] )
						) {
							$source_charset   = (string) $charset_row['DEFAULT_CHARACTER_SET_NAME'];
							$source_collation = (string) $charset_row['DEFAULT_COLLATION_NAME'];
							$charset_resolved = true;
						}
					}
				}
				$charset_stmt->close();
			}
			if ( ! $charset_resolved ) {
				$db_vars_res = $mysqli->query( 'SELECT @@character_set_database, @@collation_database' );
				$db_vars_row = $db_vars_res instanceof mysqli_result ? $db_vars_res->fetch_row() : null;
				if ( $db_vars_res instanceof mysqli_result ) {
					$db_vars_res->free();
				}
				if (
					is_array( $db_vars_row )
					&& isset( $db_vars_row[0], $db_vars_row[1] )
					&& 1 === preg_match( '/^[a-zA-Z0-9_]+$/', (string) $db_vars_row[0] )
					&& 1 === preg_match( '/^[a-zA-Z0-9_]+$/', (string) $db_vars_row[1] )
				) {
					$source_charset   = (string) $db_vars_row[0];
					$source_collation = (string) $db_vars_row[1];
				}
			}

			// 6. Capture read-only pre-rehearsal snapshot of the source database and fail closed unless all base tables are InnoDB.
			$source_pre          = gm2_rehearsal_capture_db_snapshot( $mysqli, $source_db, $table_prefix );
			$comp_not_run_reason = 'aborted_before_scratch_restore_comparison';
			$inv_not_run_reason  = 'source_post_snapshot_not_completed';

			$report['source_table_count']              = $source_pre['table_count'];
			$report['source_table_engines_all_innodb'] = $source_pre['all_tables_innodb'];
			$report['source_table_engine_counts']      = $source_pre['engine_counts'];
			$report['source_non_innodb_table_count']   = count( $source_pre['non_innodb_tables'] );
			$report['source_aggregate_row_count']      = $source_pre['aggregate_row_count'];
			$report['source_grids_row_count']          = $source_pre['grids_row_count'];
			$report['source_saved_grid_render_comparison'] = 0 === $source_pre['grids_row_count']
				? 'skipped_zero_saved_grids_use_gm2_m2_t01_synthetic_harness'
				: 'source_rows_present';

			if ( true !== $source_pre['all_tables_innodb'] || ! empty( $source_pre['non_innodb_tables'] ) ) {
				$comp_not_run_reason = 'source_non_innodb_precheck_failed';
				throw new RuntimeException(
					sprintf(
						'Source database contains non-InnoDB base tables (%d found); aborting fail-closed before dump or scratch DB creation (no source DB lock fallback).',
						count( $source_pre['non_innodb_tables'] )
					)
				);
			}

			// 7. Generate random backup stem, check all three target artifact paths (.sql, .sql.sha256, .meta.json)
			// before any file write or mysqldump invocation, then run mysqldump to the private directory.
			$timestamp      = gmdate( 'Ymd\THis\Z' );
			$dump_stem      = 'gm2-pre-schema-backup-' . $timestamp . '-' . substr( $scratch_db, strlen( 'gm2_restore_' ), 8 );
			$dump_path      = $private_dir . '/' . $dump_stem . '.sql';
			$sha_path       = $dump_path . '.sha256';
			$meta_path      = $private_dir . '/' . $dump_stem . '.meta.json';
			$artifact_paths = array(
				'SQL dump (.sql)'               => $dump_path,
				'SHA-256 sidecar (.sql.sha256)' => $sha_path,
				'metadata sidecar (.meta.json)' => $meta_path,
			);

			gm2_rehearsal_assert_artifacts_absent( $artifact_paths );

			$temp_cnf_path = gm2_rehearsal_write_temp_cnf( $private_dir, $endpoint );

			// Re-check exact artifact paths immediately before mysqldump writes.
			gm2_rehearsal_assert_artifacts_absent( $artifact_paths );

			$dump_argv = array(
				$mysqldump_bin,
				'--defaults-extra-file=' . $temp_cnf_path,
				'--single-transaction',
				'--quick',
				'--routines',
				'--triggers',
				'--events',
				'--hex-blob',
				'--default-character-set=utf8mb4',
			);
			if ( false !== strpos( $help_text, 'no-tablespaces' ) ) {
				$dump_argv[] = '--no-tablespaces';
			}
			if ( false !== strpos( $help_text, 'set-gtid-purged' ) ) {
				$dump_argv[] = '--set-gtid-purged=OFF';
			}
			if ( false !== strpos( $help_text, 'column-statistics' ) ) {
				$dump_argv[] = '--column-statistics=0';
			}
			$dump_argv[] = '--result-file=' . $dump_path;
			$dump_argv[] = $source_db;

			$dump_proc = gm2_rehearsal_run_process( $dump_argv );
			if ( 0 !== $dump_proc['exit_code'] ) {
				$comp_not_run_reason = 'mysqldump_execution_failed';
				throw new RuntimeException( sprintf( 'mysqldump failed with exit code %d.', $dump_proc['exit_code'] ) );
			}

			if ( ! is_file( $dump_path ) || ! @chmod( $dump_path, 0600 ) ) {
				throw new RuntimeException( 'Failed to set mode 0600 on private SQL dump file.' );
			}
			clearstatcache( true, $dump_path );
			$dump_size = (int) filesize( $dump_path );
			$dump_mode = sprintf( '%04o', fileperms( $dump_path ) & 0777 );
			if ( '0600' !== $dump_mode || $dump_size <= 0 ) {
				throw new RuntimeException( 'Private SQL dump file failed mode 0600 or non-empty size verification.' );
			}

			gm2_rehearsal_verify_dump_safety( $dump_path );

			$dump_sha256 = hash_file( 'sha256', $dump_path );
			if ( ! is_string( $dump_sha256 ) || 64 !== strlen( $dump_sha256 ) ) {
				throw new RuntimeException( 'Failed to compute SHA-256 of private SQL dump.' );
			}

			gm2_rehearsal_assert_artifacts_absent(
				array(
					'SHA-256 sidecar (.sql.sha256)' => $sha_path,
					'metadata sidecar (.meta.json)' => $meta_path,
				)
			);
			gm2_rehearsal_write_exclusive_private_file(
				$sha_path,
				$dump_sha256 . '  ' . basename( $dump_path ) . "\n",
				'SHA-256 companion file (.sql.sha256)'
			);

			$report['backup_retained_outside_web_root'] = true;
			$report['backup_file_mode']                 = $dump_mode;
			$report['backup_size_bytes']                = $dump_size;

			// 8. Re-verify scratch DB absence, create scratch DB, and restore dump into scratch DB only.
			if ( gm2_rehearsal_schema_exists( $mysqli, $scratch_db ) ) {
				throw new RuntimeException( 'Scratch database appeared unexpectedly before CREATE.' );
			}

			$quoted_scratch = gm2_rehearsal_quote_ident( $scratch_db );
			$create_sql     = "CREATE DATABASE {$quoted_scratch} CHARACTER SET {$source_charset} COLLATE {$source_collation}";
			if ( ! $mysqli->query( $create_sql ) ) {
				$comp_not_run_reason = 'scratch_db_create_failed';
				throw new RuntimeException( 'Failed to CREATE scratch database for restore rehearsal.' );
			}
			$created_scratch_db           = $scratch_db;
			$report['scratch_db_created'] = true;

			if ( 1 !== preg_match( '/^gm2_restore_[0-9a-f]{16,32}$/', $created_scratch_db ) || strtolower( $created_scratch_db ) === strtolower( $source_db ) ) {
				throw new RuntimeException( 'Scratch database target validation failed prior to restore.' );
			}

			$restore_argv = array(
				$mysql_bin,
				'--defaults-extra-file=' . $temp_cnf_path,
				'--default-character-set=utf8mb4',
				'--database=' . $created_scratch_db,
			);
			$restore_proc = gm2_rehearsal_run_process( $restore_argv, $dump_path );
			if ( 0 !== $restore_proc['exit_code'] ) {
				$comp_not_run_reason = 'scratch_db_restore_failed';
				throw new RuntimeException( sprintf( 'mysql restore into scratch DB failed with exit code %d.', $restore_proc['exit_code'] ) );
			}

			// 9. Capture snapshot of restored scratch DB and evaluate all comparisons without short-circuiting.
			$scratch_snap = gm2_rehearsal_capture_db_snapshot( $mysqli, $created_scratch_db, $table_prefix );

			$report['scratch_table_engines_all_innodb'] = $scratch_snap['all_tables_innodb'];
			$report['scratch_table_engine_counts']      = $scratch_snap['engine_counts'];
			$report['scratch_non_innodb_table_count']   = count( $scratch_snap['non_innodb_tables'] );

			$src_raw_map = isset( $source_pre['table_schemas_raw_ddl'] ) && is_array( $source_pre['table_schemas_raw_ddl'] )
				? $source_pre['table_schemas_raw_ddl']
				: array();
			$scr_raw_map = isset( $scratch_snap['table_schemas_raw_ddl'] ) && is_array( $scratch_snap['table_schemas_raw_ddl'] )
				? $scratch_snap['table_schemas_raw_ddl']
				: array();

			$schema_diag = gm2_diagnose_schema_mismatches(
				$src_raw_map,
				$scr_raw_map,
				$source_db,
				$created_scratch_db,
				$source_pre['grids_table_name']
			);

			unset(
				$source_pre['table_schemas_raw_ddl'],
				$scratch_snap['table_schemas_raw_ddl'],
				$src_raw_map,
				$scr_raw_map
			);

			$report['schema_comparison_summary'] = array(
				'total_tables'                          => $source_pre['table_count'],
				'raw_mismatch_table_count'              => $schema_diag['raw_mismatch_table_count'],
				'normalized_mismatch_table_count'       => $schema_diag['normalized_mismatch_table_count'],
				'volatile_normalized_equal_table_count' => $schema_diag['volatile_normalized_equal_table_count'],
				'grids_table_schema_status'             => $schema_diag['grids_table_diagnostics']['status'],
				'clause_breakdown'                      => $schema_diag['clause_breakdown'],
				'raw_category_counts'                   => $schema_diag['raw_category_counts'],
				'residual_category_counts'              => $schema_diag['residual_category_counts'],
				'grids_table_diagnostics'               => $schema_diag['grids_table_diagnostics'],
			);

			$comp_eval                       = gm2_evaluate_rehearsal_comparisons( $source_pre, $scratch_snap );
			$report['comparisons']           = $comp_eval['comparisons'];
			$report['comparison_checks']     = $comp_eval['comparison_checks'];
			$comparisons_evaluated           = true;
			$all_required_comp_passed        = $comp_eval['all_required_passed'];
			foreach ( $comp_eval['errors'] as $comp_err ) {
				$report['errors'][] = $comp_err;
			}

			// 10. Drop only the scratch database created by this invocation before post-checking source DB.
			$drop_target    = $created_scratch_db;
			$quoted_drop_db = gm2_rehearsal_quote_ident( $drop_target );
			if ( ! $mysqli->query( "DROP DATABASE IF EXISTS {$quoted_drop_db}" ) ) {
				$report['cleanup_check'] = array(
					'status' => 'failed',
					'passed' => false,
					'reason' => 'scratch_db_drop_query_failed',
				);
				throw new RuntimeException( 'Failed to DROP scratch database after verification.' );
			}
			if ( gm2_rehearsal_schema_exists( $mysqli, $drop_target ) ) {
				$report['cleanup_check'] = array(
					'status' => 'failed',
					'passed' => false,
					'reason' => 'scratch_db_still_present_after_drop',
				);
				throw new RuntimeException( 'Scratch database still exists after DROP DATABASE.' );
			}
			$created_scratch_db                    = null;
			$report['scratch_db_dropped_verified'] = true;
			$report['cleanup_check']               = array(
				'status' => 'passed',
				'passed' => true,
				'reason' => null,
			);
		} catch ( Throwable $e ) {
			$report['status']   = 'failed';
			$report['errors'][] = $e->getMessage();
		} finally {
			if ( null !== $temp_cnf_path && is_string( $temp_cnf_path ) && file_exists( $temp_cnf_path ) ) {
				@unlink( $temp_cnf_path );
			}

			// Ensure scratch DB cleanup runs if an exception occurred before step 10 completed.
			if (
				null !== $created_scratch_db
				&& is_string( $created_scratch_db )
				&& 1 === preg_match( '/^gm2_restore_[0-9a-f]{16,32}$/', $created_scratch_db )
				&& ( null === $source_db || strtolower( $created_scratch_db ) !== strtolower( $source_db ) )
				&& ( $mysqli instanceof mysqli )
			) {
				$quoted_cleanup = '`' . str_replace( '`', '``', $created_scratch_db ) . '`';
				@$mysqli->query( "DROP DATABASE IF EXISTS {$quoted_cleanup}" );
				try {
					if ( ! gm2_rehearsal_schema_exists( $mysqli, $created_scratch_db ) ) {
						$report['scratch_db_dropped_verified'] = true;
						$report['cleanup_check']               = array(
							'status' => 'passed',
							'passed' => true,
							'reason' => null,
						);
						$created_scratch_db = null;
					} else {
						$report['status']        = 'failed';
						$report['cleanup_check'] = array(
							'status' => 'failed',
							'passed' => false,
							'reason' => 'scratch_db_still_present_after_drop',
						);
						$report['errors'][] = 'Cleanup failed to confirm removal of scratch database.';
					}
				} catch ( Throwable $cleanup_err ) {
					$report['status']        = 'failed';
					$report['cleanup_check'] = array(
						'status' => 'failed',
						'passed' => false,
						'reason' => 'scratch_db_cleanup_verification_query_failed',
					);
					$report['errors'][] = 'Cleanup verification query failed.';
				}
			}

			// 11. Always attempt post-run source DB invariant verification after scratch cleanup
			// whenever an active source connection and pre-rehearsal snapshot exist, even if a restore comparison failed.
			if ( ( $mysqli instanceof mysqli ) && is_array( $source_pre ) && is_string( $source_db ) && '' !== $source_db ) {
				try {
					@$mysqli->select_db( $source_db );
					$post_ident_res = $mysqli->query( 'SELECT DATABASE()' );
					$post_ident_row = $post_ident_res instanceof mysqli_result ? $post_ident_res->fetch_row() : null;
					if ( $post_ident_res instanceof mysqli_result ) {
						$post_ident_res->free();
					}
					$post_source_identity = is_array( $post_ident_row ) && isset( $post_ident_row[0] ) ? (string) $post_ident_row[0] : '';
					$source_post          = gm2_rehearsal_capture_db_snapshot( $mysqli, $source_db, (string) $table_prefix );
					unset( $source_post['table_schemas_raw_ddl'] );

					$inv_eval                                  = gm2_evaluate_source_invariants(
						$source_pre,
						$source_post,
						$source_db,
						(string) $pre_source_identity,
						$post_source_identity
					);
					$report['source_invariants_before_after'] = $inv_eval['invariants'];
					$report['source_invariant_checks']        = $inv_eval['invariant_checks'];
					$invariants_evaluated                     = true;
					$all_invariants_passed                    = $inv_eval['all_passed'];
					foreach ( $inv_eval['errors'] as $inv_err ) {
						$report['errors'][] = $inv_err;
					}
				} catch ( Throwable $post_err ) {
					$inv_not_run_reason   = 'source_post_snapshot_query_failed';
					$report['status']     = 'failed';
					$report['errors'][]   = 'Failed to capture post-rehearsal source DB snapshot for invariant verification.';
				}
			}

			if ( ! $comparisons_evaluated ) {
				$report['comparison_checks'] = gm2_update_not_run_check_reasons(
					$report['comparison_checks'],
					$comp_not_run_reason
				);
			}
			if ( ! $invariants_evaluated ) {
				$report['source_invariant_checks'] = gm2_update_not_run_check_reasons(
					$report['source_invariant_checks'],
					$inv_not_run_reason
				);
			}

			// 12. Record private metadata file (mode 0600) alongside the retained private SQL dump if the dump was produced.
			if (
				true === $report['backup_retained_outside_web_root']
				&& is_string( $dump_path )
				&& is_string( $dump_sha256 )
				&& is_string( $meta_path )
				&& is_array( $source_pre )
			) {
				try {
					$private_meta = array(
						'task_id'                          => 'GM2-M2-T02',
						'created_at_utc'                   => $timestamp,
						'backup_file'                      => basename( $dump_path ),
						'backup_size_bytes'                => $dump_size,
						'backup_sha256'                    => $dump_sha256,
						'scratch_db_rehearsed'             => $scratch_db,
						'scratch_db_dropped'               => $report['scratch_db_dropped_verified'],
						'cleanup_check'                    => $report['cleanup_check'],
						'source_table_count'               => $source_pre['table_count'],
						'source_table_engines_all_innodb'  => $source_pre['all_tables_innodb'],
						'source_table_engine_counts'       => $source_pre['engine_counts'],
						'scratch_table_engines_all_innodb' => is_array( $scratch_snap ) ? $scratch_snap['all_tables_innodb'] : null,
						'scratch_table_engine_counts'      => is_array( $scratch_snap ) ? $scratch_snap['engine_counts'] : array(),
						'source_aggregate_rows'            => $source_pre['aggregate_row_count'],
						'source_grids_row_count'           => $source_pre['grids_row_count'],
						'schema_norm_sha256'               => $source_pre['schema_norm_aggregate'],
						'grids_rows_sha256'                => $source_pre['grids_aggregate_hash'],
						'active_plugins_sha256'            => $source_pre['active_plugins_hash'],
						'schema_comparison_summary'        => $report['schema_comparison_summary'],
						'comparisons'                      => $report['comparisons'],
						'comparison_checks'                => $report['comparison_checks'],
						'source_invariants'                => $report['source_invariants_before_after'],
						'source_invariant_checks'          => $report['source_invariant_checks'],
					);

					gm2_rehearsal_assert_artifacts_absent(
						array(
							'metadata sidecar (.meta.json)' => $meta_path,
						)
					);
					gm2_rehearsal_write_exclusive_private_file(
						$meta_path,
						wp_json_encode( $private_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n",
						'private metadata file (.meta.json)'
					);

					$report['backup_sha256_recorded_privately'] = true;
				} catch ( Throwable $meta_err ) {
					$report['status']   = 'failed';
					$report['errors'][] = $meta_err->getMessage();
				}
			}

			if ( $mysqli instanceof mysqli ) {
				@$mysqli->close();
			}
		}

		if (
			empty( $report['errors'] )
			&& $comparisons_evaluated
			&& $all_required_comp_passed
			&& $invariants_evaluated
			&& $all_invariants_passed
			&& true === $report['scratch_db_created']
			&& true === $report['scratch_db_dropped_verified']
			&& isset( $report['cleanup_check']['status'] )
			&& 'passed' === $report['cleanup_check']['status']
			&& true === $report['backup_retained_outside_web_root']
			&& true === $report['backup_sha256_recorded_privately']
		) {
			$report['status'] = 'passed';
		} else {
			$report['status'] = 'failed';
		}

		$json_out = function_exists( 'wp_json_encode' )
			? wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
			: json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		fwrite( STDOUT, ( is_string( $json_out ) ? $json_out : '{"status":"failed"}' ) . "\n" );

		if ( 'passed' !== $report['status'] ) {
			exit( 1 );
		}

		return 0;
	}
}

$gm2_cli_args = array();
if ( isset( $args ) && is_array( $args ) ) {
	$gm2_cli_args = $args;
} elseif ( isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) ) {
	$gm2_cli_args = array_slice( $_SERVER['argv'], 1 );
}

gm2_backup_restore_rehearsal_main( $gm2_cli_args );

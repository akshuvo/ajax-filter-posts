<?php
/**
 * Pure SHOW CREATE TABLE normalization and comparison/invariant evaluation helpers (GM2-M2-T02-R1).
 *
 * Contains no WordPress or database side effects so both `scripts/gm2/backup-restore-rehearsal.php`
 * and the offline CLI test runner `tests/gm2-backup/run.php` execute the exact same production logic.
 *
 * @package GridMaster
 */

if ( ! function_exists( 'gm2_normalize_create_table_ddl' ) ) {
	/**
	 * Normalize a column definition segment outside single-quoted SQL literals.
	 *
	 * Canonicalizes only MySQL's `utf8` -> `utf8mb3` charset/collation alias and
	 * `CURRENT_TIMESTAMP()` -> `CURRENT_TIMESTAMP` in unquoted DDL tokens while leaving
	 * single-quoted `DEFAULT '...'` and `COMMENT '...'` literals untouched.
	 *
	 * @param string $segment Raw column definition suffix after the data type.
	 * @return string
	 */
	function gm2_normalize_unquoted_column_clauses( $segment ) {
		$parts = preg_split( "/('(?:\\\\.|''|[^'\\\\])*')/s", (string) $segment, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return (string) $segment;
		}

		foreach ( $parts as $idx => $part ) {
			// Odd indices are single-quoted literals captured by the delimiter group.
			if ( 1 === ( $idx % 2 ) ) {
				continue;
			}
			$part = preg_replace( '/\bCHARACTER\s+SET\s+utf8\b/i', 'CHARACTER SET utf8mb3', $part );
			$part = preg_replace( '/\bCOLLATE\s+utf8_([a-z0-9_]+)\b/i', 'COLLATE utf8mb3_$1', (string) $part );
			$part = preg_replace( '/\b(DEFAULT|ON\s+UPDATE)\s+CURRENT_TIMESTAMP\(\)/i', '$1 CURRENT_TIMESTAMP', (string) $part );
			$parts[ $idx ] = (string) $part;
		}

		return implode( '', $parts );
	}

	/**
	 * Normalize a single line inside the `CREATE TABLE (...)` body.
	 *
	 * Narrowly normalizes proven server-generated MySQL dump/restore differences:
	 * 1. Deprecated MySQL < 8.0.19 integer display widths on column types
	 *    (`smallint(N)`, `mediumint(N)`, `int(N)`, `integer(N)`, `bigint(N)`,
	 *    and `tinyint(N)` when `N != 1` or `unsigned`, unless `zerofill` is present),
	 *    which MySQL 8.0.19+/8.4 strips when executing `CREATE TABLE` during restore.
	 *    Signed `tinyint(1)`, base integer types, `unsigned`, `zerofill`, nullability,
	 *    defaults, and comments are preserved.
	 * 2. MySQL 8.0.30+/8.4 `utf8` -> `utf8mb3` character set / collation alias tokens
	 *    on unquoted column clauses (`utf8mb3` remains distinct from `utf8mb4`).
	 * 3. Self-schema database prefix on `FOREIGN KEY ... REFERENCES `<schema>`.`<table>``
	 *    when `$schema_name` is supplied.
	 *
	 * @param string      $line        Single line from the `CREATE TABLE` body.
	 * @param string|null $schema_name Optional owning schema name for self-reference normalization.
	 * @return string
	 */
	function gm2_normalize_create_table_body_line( $line, $schema_name = null ) {
		$line_str = (string) $line;

		if (
			1 === preg_match(
				'/^(\s*`(?:[^`]|``)+`\s+)(tinyint|smallint|mediumint|int|integer|bigint)\((\d+)\)(\s+unsigned\b)?(\s+zerofill\b)?(.*)$/i',
				$line_str,
				$matches
			)
		) {
			$prefix    = $matches[1];
			$int_type  = strtolower( $matches[2] );
			$width     = $matches[3];
			$unsigned  = isset( $matches[4] ) && '' !== $matches[4] ? ' unsigned' : '';
			$zerofill  = isset( $matches[5] ) && '' !== $matches[5] ? $matches[5] : '';
			$remainder = isset( $matches[6] ) ? $matches[6] : '';

			if ( '' !== $zerofill ) {
				$type_token = $int_type . '(' . $width . ')' . $unsigned . $zerofill;
			} elseif ( 'tinyint' === $int_type && '1' === $width && '' === $unsigned ) {
				$type_token = 'tinyint(1)';
			} else {
				$type_token = $int_type . $unsigned;
			}

			return $prefix . $type_token . gm2_normalize_unquoted_column_clauses( $remainder );
		}

		if ( 1 === preg_match( '/^(\s*`(?:[^`]|``)+`\s+)(.+)$/', $line_str, $col_matches ) ) {
			return $col_matches[1] . gm2_normalize_unquoted_column_clauses( $col_matches[2] );
		}

		if ( is_string( $schema_name ) && '' !== $schema_name && false !== stripos( $line_str, 'FOREIGN KEY' ) ) {
			$quoted_schema = '`' . str_replace( '`', '``', $schema_name ) . '`';
			$pattern       = '/(\bREFERENCES\s+)' . preg_quote( $quoted_schema, '/' ) . '\.(`(?:[^`]|``)+`)/i';
			$line_str      = (string) preg_replace( $pattern, '$1$2', $line_str );
		}

		return $line_str;
	}

	/**
	 * Normalize `SHOW CREATE TABLE` DDL for structural comparison across MySQL dump/restore.
	 *
	 * Preserves all meaningful schema constraints: column names, types, character lengths,
	 * integer signedness, `tinyint(1)` vs broader integer types, nullability, defaults,
	 * primary/unique/secondary keys and index columns/types, storage engine, table and
	 * column character sets and collations, `ROW_FORMAT`, and table options.
	 *
	 * Normalizes only proven volatile/server-canonicalized dump-restore differences:
	 * - CRLF/CR line endings and outer whitespace.
	 * - Table-level `AUTO_INCREMENT=<n>` counter in the trailing table-options clause.
	 * - Deprecated MySQL < 8.0.19 non-boolean integer display widths (`int(11)`, `mediumint(9)`,
	 *   `bigint(20)`, `smallint(6)`, `tinyint(4)`, `tinyint(1) unsigned`) that MySQL 8.0.19+/8.4
	 *   canonicalizes away on `CREATE TABLE`.
	 * - MySQL 8.0.30+/8.4 `utf8` -> `utf8mb3` alias tokens in unquoted charset/collation clauses.
	 * - Optional self-schema qualifier in `FOREIGN KEY ... REFERENCES `<schema>`.`<table>``.
	 *
	 * @param string      $create_sql  Raw `SHOW CREATE TABLE` statement.
	 * @param string|null $schema_name Optional schema name for self-referential FK normalization.
	 * @return string
	 */
	function gm2_normalize_create_table_ddl( $create_sql, $schema_name = null ) {
		$normalized = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $create_sql ) );
		if ( '' === $normalized ) {
			return '';
		}

		$close_pos = strrpos( $normalized, "\n)" );
		if ( false === $close_pos ) {
			$normalized = preg_replace( '/(\)\s+ENGINE=\S+)\s+AUTO_INCREMENT=\d+\b/i', '$1', $normalized );
			return is_string( $normalized ) ? $normalized : '';
		}

		$body          = substr( $normalized, 0, $close_pos );
		$table_options = substr( $normalized, $close_pos + 1 );

		$lines = explode( "\n", $body );
		foreach ( $lines as $idx => $line ) {
			if ( 0 === $idx ) {
				continue;
			}
			$lines[ $idx ] = gm2_normalize_create_table_body_line( $line, $schema_name );
		}
		$normalized_body = implode( "\n", $lines );

		// Strip only table-level AUTO_INCREMENT=<n> in the trailing table options clause outside any comment literal.
		$option_parts = preg_split( "/('(?:\\\\.|''|[^'\\\\])*')/s", $table_options, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( is_array( $option_parts ) ) {
			foreach ( $option_parts as $idx => $opt_part ) {
				if ( 1 === ( $idx % 2 ) ) {
					continue;
				}
				$opt_part             = preg_replace( '/\s+\bAUTO_INCREMENT\s*=\s*\d+\b/i', '', $opt_part );
				$opt_part             = preg_replace( '/\b(DEFAULT\s+CHARSET|CHARSET)\s*=\s*utf8\b/i', '$1=utf8mb3', (string) $opt_part );
				$opt_part             = preg_replace( '/\bCOLLATE(\s*=\s*|\s+)utf8_([a-z0-9_]+)\b/i', 'COLLATE$1utf8mb3_$2', (string) $opt_part );
				$opt_part             = preg_replace( '/[ \t]+/', ' ', (string) $opt_part );
				$option_parts[ $idx ] = (string) $opt_part;
			}
			$table_options = implode( '', $option_parts );
		}
		$table_options = rtrim( $table_options, " \t;" );

		return $normalized_body . "\n" . $table_options;
	}

	/**
	 * Canonical list of DDL mismatch categories reported in privacy-safe aggregate diagnostics.
	 *
	 * @return array<int, string>
	 */
	function gm2_get_ddl_diagnostic_category_keys() {
		return array(
			'table_options_auto_increment',
			'table_options_engine',
			'table_options_row_format',
			'table_options_charset_or_collation',
			'table_options_other',
			'integer_display_width',
			'current_timestamp_syntax',
			'column_charset_or_collation',
			'column_nullability_or_default',
			'column_type_or_length',
			'column_name_or_order',
			'column_or_key_count_mismatch',
			'foreign_key_qualifier',
			'index_or_key_definition',
			'body_other',
		);
	}

	/**
	 * Detect specific mismatch category tokens between two table bodies and table option clauses.
	 *
	 * @param string $body_a Table body lines (excluding outer closing parenthesis).
	 * @param string $opts_a Trailing table options clause.
	 * @param string $body_b Table body lines (excluding outer closing parenthesis).
	 * @param string $opts_b Trailing table options clause.
	 * @return array<int, string> List of unique category tokens.
	 */
	function gm2_detect_ddl_categories( $body_a, $opts_a, $body_b, $opts_b ) {
		$categories = array();

		// 1. Analyze trailing table options differences.
		if ( $opts_a !== $opts_b ) {
			$has_ai_a = (bool) preg_match( '/\bAUTO_INCREMENT=\d+\b/i', $opts_a );
			$has_ai_b = (bool) preg_match( '/\bAUTO_INCREMENT=\d+\b/i', $opts_b );
			$no_ai_a  = preg_replace( '/\s*\bAUTO_INCREMENT=\d+\b/i', '', $opts_a );
			$no_ai_b  = preg_replace( '/\s*\bAUTO_INCREMENT=\d+\b/i', '', $opts_b );
			if ( $has_ai_a !== $has_ai_b || ( $has_ai_a && $has_ai_b && $no_ai_a !== $opts_a ) ) {
				$categories[] = 'table_options_auto_increment';
			}

			preg_match( '/\bENGINE=([a-zA-Z0-9_]+)/i', $opts_a, $m_ea );
			preg_match( '/\bENGINE=([a-zA-Z0-9_]+)/i', $opts_b, $m_eb );
			$ea = isset( $m_ea[1] ) ? strtolower( $m_ea[1] ) : '';
			$eb = isset( $m_eb[1] ) ? strtolower( $m_eb[1] ) : '';
			if ( $ea !== $eb ) {
				$categories[] = 'table_options_engine';
			}

			preg_match( '/\bROW_FORMAT=([a-zA-Z0-9_]+)/i', $opts_a, $m_ra );
			preg_match( '/\bROW_FORMAT=([a-zA-Z0-9_]+)/i', $opts_b, $m_rb );
			$ra = isset( $m_ra[1] ) ? strtolower( $m_ra[1] ) : '';
			$rb = isset( $m_rb[1] ) ? strtolower( $m_rb[1] ) : '';
			if ( $ra !== $rb ) {
				$categories[] = 'table_options_row_format';
			}

			preg_match( '/\b(?:DEFAULT\s+)?CHARSET=([a-zA-Z0-9_]+)/i', $opts_a, $m_ca );
			preg_match( '/\b(?:DEFAULT\s+)?CHARSET=([a-zA-Z0-9_]+)/i', $opts_b, $m_cb );
			preg_match( '/\bCOLLATE=([a-zA-Z0-9_]+)/i', $opts_a, $m_oa );
			preg_match( '/\bCOLLATE=([a-zA-Z0-9_]+)/i', $opts_b, $m_ob );
			$ca = isset( $m_ca[1] ) ? strtolower( $m_ca[1] ) : '';
			$cb = isset( $m_cb[1] ) ? strtolower( $m_cb[1] ) : '';
			$oa = isset( $m_oa[1] ) ? strtolower( $m_oa[1] ) : '';
			$ob = isset( $m_ob[1] ) ? strtolower( $m_ob[1] ) : '';
			if ( $ca !== $cb || $oa !== $ob ) {
				$categories[] = 'table_options_charset_or_collation';
			}

			$scrubbed_a = preg_replace(
				array(
					'/\s*\bAUTO_INCREMENT=\d+\b/i',
					'/\bENGINE=[a-zA-Z0-9_]+/i',
					'/\bROW_FORMAT=[a-zA-Z0-9_]+/i',
					'/\b(?:DEFAULT\s+)?CHARSET=[a-zA-Z0-9_]+/i',
					'/\bCOLLATE=[a-zA-Z0-9_]+/i',
					'/\s+/',
				),
				array( '', '', '', '', '', ' ' ),
				$opts_a
			);
			$scrubbed_b = preg_replace(
				array(
					'/\s*\bAUTO_INCREMENT=\d+\b/i',
					'/\bENGINE=[a-zA-Z0-9_]+/i',
					'/\bROW_FORMAT=[a-zA-Z0-9_]+/i',
					'/\b(?:DEFAULT\s+)?CHARSET=[a-zA-Z0-9_]+/i',
					'/\bCOLLATE=[a-zA-Z0-9_]+/i',
					'/\s+/',
				),
				array( '', '', '', '', '', ' ' ),
				$opts_b
			);
			if ( trim( (string) $scrubbed_a ) !== trim( (string) $scrubbed_b ) ) {
				$categories[] = 'table_options_other';
			}
		}

		// 2. Analyze table body differences.
		if ( $body_a !== $body_b ) {
			$lines_a = explode( "\n", $body_a );
			$lines_b = explode( "\n", $body_b );
			if ( count( $lines_a ) > 1 ) {
				$lines_a = array_slice( $lines_a, 1 );
			}
			if ( count( $lines_b ) > 1 ) {
				$lines_b = array_slice( $lines_b, 1 );
			}

			if ( count( $lines_a ) !== count( $lines_b ) ) {
				$categories[] = 'column_or_key_count_mismatch';
			}

			$max_lines = max( count( $lines_a ), count( $lines_b ) );
			for ( $i = 0; $i < $max_lines; $i++ ) {
				if ( ! isset( $lines_a[ $i ] ) || ! isset( $lines_b[ $i ] ) ) {
					continue;
				}
				$la = rtrim( trim( $lines_a[ $i ] ), ',' );
				$lb = rtrim( trim( $lines_b[ $i ] ), ',' );
				if ( $la === $lb ) {
					continue;
				}

				$is_key_a = (bool) preg_match( '/^\s*(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|FULLTEXT|SPATIAL|CONSTRAINT)\b/i', $la );
				$is_key_b = (bool) preg_match( '/^\s*(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|FULLTEXT|SPATIAL|CONSTRAINT)\b/i', $lb );
				if ( $is_key_a || $is_key_b ) {
					if ( false !== stripos( $la, 'REFERENCES' ) || false !== stripos( $lb, 'REFERENCES' ) ) {
						$la_nofk = preg_replace( '/(\bREFERENCES\s+)`[^`]+`\.(`[^`]+`)/i', '$1$2', $la );
						$lb_nofk = preg_replace( '/(\bREFERENCES\s+)`[^`]+`\.(`[^`]+`)/i', '$1$2', $lb );
						if ( $la_nofk === $lb_nofk ) {
							$categories[] = 'foreign_key_qualifier';
						} else {
							$categories[] = 'index_or_key_definition';
						}
					} else {
						$categories[] = 'index_or_key_definition';
					}
					continue;
				}

				preg_match( '/^\s*(`(?:[^`]|``)+`)/', $la, $m_cola );
				preg_match( '/^\s*(`(?:[^`]|``)+`)/', $lb, $m_colb );
				$cola = isset( $m_cola[1] ) ? $m_cola[1] : '';
				$colb = isset( $m_colb[1] ) ? $m_colb[1] : '';
				if ( '' !== $cola && '' !== $colb && $cola !== $colb ) {
					$categories[] = 'column_name_or_order';
					continue;
				}

				$handled_body_diff = false;

				$la_noint = preg_replace( '/\b(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/i', '$1', $la );
				$lb_noint = preg_replace( '/\b(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/i', '$1', $lb );
				if ( $la_noint !== $la || $lb_noint !== $lb ) {
					$categories[]      = 'integer_display_width';
					$handled_body_diff = true;
				}

				$la_nots = preg_replace( '/\bCURRENT_TIMESTAMP\(\)/i', 'CURRENT_TIMESTAMP', $la );
				$lb_nots = preg_replace( '/\bCURRENT_TIMESTAMP\(\)/i', 'CURRENT_TIMESTAMP', $lb );
				if ( $la_nots !== $la || $lb_nots !== $lb ) {
					$categories[]      = 'current_timestamp_syntax';
					$handled_body_diff = true;
				}

				if (
					false !== stripos( $la, 'CHARACTER SET' ) || false !== stripos( $lb, 'CHARACTER SET' )
					|| false !== stripos( $la, 'COLLATE' ) || false !== stripos( $lb, 'COLLATE' )
				) {
					$categories[]      = 'column_charset_or_collation';
					$handled_body_diff = true;
				}

				$has_nn_a = (bool) preg_match( '/\bNOT\s+NULL\b/i', $la );
				$has_nn_b = (bool) preg_match( '/\bNOT\s+NULL\b/i', $lb );
				$has_df_a = (bool) preg_match( '/\bDEFAULT\b/i', $la );
				$has_df_b = (bool) preg_match( '/\bDEFAULT\b/i', $lb );
				if ( $has_nn_a !== $has_nn_b || $has_df_a !== $has_df_b ) {
					$categories[]      = 'column_nullability_or_default';
					$handled_body_diff = true;
				}

				preg_match( '/^\s*`(?:[^`]|``)+`\s+([a-zA-Z0-9_]+(?:\([^)]+\))?)/', $la, $m_ta );
				preg_match( '/^\s*`(?:[^`]|``)+`\s+([a-zA-Z0-9_]+(?:\([^)]+\))?)/', $lb, $m_tb );
				$ta = isset( $m_ta[1] ) ? strtolower( $m_ta[1] ) : '';
				$tb = isset( $m_tb[1] ) ? strtolower( $m_tb[1] ) : '';
				if ( '' !== $ta && '' !== $tb && $ta !== $tb ) {
					$categories[]      = 'column_type_or_length';
					$handled_body_diff = true;
				}

				if ( ! $handled_body_diff ) {
					$categories[] = 'body_other';
				}
			}
		}

		return array_values( array_unique( $categories ) );
	}

	/**
	 * Classify the structural relationship between source and restored `SHOW CREATE TABLE` strings
	 * and detect detailed mismatch categories without exposing DDL, table names, or column names.
	 *
	 * @param string      $source_raw_ddl  Raw `SHOW CREATE TABLE` from source schema.
	 * @param string      $scratch_raw_ddl Raw `SHOW CREATE TABLE` from scratch schema.
	 * @param string|null $source_schema   Optional source schema name.
	 * @param string|null $scratch_schema  Optional scratch schema name.
	 * @return array{
	 *   is_raw_match: bool,
	 *   is_normalized_match: bool,
	 *   status: string,
	 *   clause_classification: string,
	 *   raw_categories: array<int, string>,
	 *   residual_categories: array<int, string>
	 * }
	 */
	function gm2_classify_single_table_mismatch( $source_raw_ddl, $scratch_raw_ddl, $source_schema = null, $scratch_schema = null ) {
		$src_raw = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $source_raw_ddl ) );
		$scr_raw = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $scratch_raw_ddl ) );

		if ( $src_raw === $scr_raw ) {
			return array(
				'is_raw_match'          => true,
				'is_normalized_match'   => true,
				'status'                => 'exact_raw_match',
				'clause_classification' => 'exact_raw_match',
				'raw_categories'        => array(),
				'residual_categories'   => array(),
			);
		}

		$src_norm = gm2_normalize_create_table_ddl( $src_raw, $source_schema );
		$scr_norm = gm2_normalize_create_table_ddl( $scr_raw, $scratch_schema );
		$is_norm  = ( $src_norm === $scr_norm );

		$src_close = strrpos( $src_raw, "\n)" );
		$scr_close = strrpos( $scr_raw, "\n)" );
		$src_body  = false !== $src_close ? substr( $src_raw, 0, $src_close ) : $src_raw;
		$src_opts  = false !== $src_close ? substr( $src_raw, $src_close + 1 ) : '';
		$scr_body  = false !== $scr_close ? substr( $scr_raw, 0, $scr_close ) : $scr_raw;
		$scr_opts  = false !== $scr_close ? substr( $scr_raw, $scr_close + 1 ) : '';

		$raw_categories = gm2_detect_ddl_categories( $src_body, $src_opts, $scr_body, $scr_opts );

		if ( $is_norm ) {
			return array(
				'is_raw_match'          => false,
				'is_normalized_match'   => true,
				'status'                => 'normalized_match_volatile_only',
				'clause_classification' => 'normalized_match_volatile_only',
				'raw_categories'        => $raw_categories,
				'residual_categories'   => array(),
			);
		}

		$src_n_close = strrpos( $src_norm, "\n)" );
		$scr_n_close = strrpos( $scr_norm, "\n)" );
		$src_n_body  = false !== $src_n_close ? substr( $src_norm, 0, $src_n_close ) : $src_norm;
		$src_n_opts  = false !== $src_n_close ? substr( $src_norm, $src_n_close + 1 ) : '';
		$scr_n_body  = false !== $scr_n_close ? substr( $scr_norm, 0, $scr_n_close ) : $scr_norm;
		$scr_n_opts  = false !== $scr_n_close ? substr( $scr_norm, $scr_n_close + 1 ) : '';

		$residual_categories = gm2_detect_ddl_categories( $src_n_body, $src_n_opts, $scr_n_body, $scr_n_opts );

		$classification = 'body_and_table_options_mismatch';
		if ( $src_n_body === $scr_n_body && $src_n_opts !== $scr_n_opts ) {
			$classification = 'table_options_only_mismatch';
		} elseif ( $src_n_body !== $scr_n_body && $src_n_opts === $scr_n_opts ) {
			$classification = 'body_only_mismatch';
		}

		return array(
			'is_raw_match'          => false,
			'is_normalized_match'   => false,
			'status'                => 'normalized_mismatch',
			'clause_classification' => $classification,
			'raw_categories'        => $raw_categories,
			'residual_categories'   => $residual_categories,
		);
	}

	/**
	 * Classify the structural relationship between source and restored `SHOW CREATE TABLE` strings
	 * into a sanitized category label without exposing DDL or table/column identifiers.
	 *
	 * Preserves backward compatibility with prior caller tokens.
	 *
	 * @param string      $source_raw_ddl  Raw `SHOW CREATE TABLE` from source schema.
	 * @param string      $scratch_raw_ddl Raw `SHOW CREATE TABLE` from scratch schema.
	 * @param string|null $source_schema   Optional source schema name.
	 * @param string|null $scratch_schema  Optional scratch schema name.
	 * @return string Sanitized category token.
	 */
	function gm2_classify_create_table_mismatch( $source_raw_ddl, $scratch_raw_ddl, $source_schema = null, $scratch_schema = null ) {
		$classified = gm2_classify_single_table_mismatch( $source_raw_ddl, $scratch_raw_ddl, $source_schema, $scratch_schema );
		if ( 'table_options_only_mismatch' === $classified['clause_classification'] ) {
			return 'table_options_mismatch';
		}
		if ( 'body_only_mismatch' === $classified['clause_classification'] ) {
			return 'column_or_index_definition_mismatch';
		}
		return $classified['clause_classification'];
	}

	/**
	 * Compute privacy-safe, aggregate schema mismatch diagnostics across all base tables.
	 *
	 * Emits only aggregate category counts and high-level clause classifications without
	 * emitting DDL, table names, column names, rows, content hashes, or credentials.
	 *
	 * @param array<string, string> $source_tables_ddl Source table name => raw DDL map.
	 * @param array<string, string> $scratch_tables_ddl Scratch table name => raw DDL map.
	 * @param string|null           $source_schema      Optional source schema name.
	 * @param string|null           $scratch_schema     Optional scratch schema name.
	 * @param string|null           $grids_table_name   Optional gridmaster_grids table identifier.
	 * @return array<string, mixed>
	 */
	function gm2_diagnose_schema_mismatches(
		array $source_tables_ddl,
		array $scratch_tables_ddl,
		$source_schema = null,
		$scratch_schema = null,
		$grids_table_name = null
	) {
		$keys = gm2_get_ddl_diagnostic_category_keys();

		$clause_breakdown = array(
			'exact_raw_match'                 => 0,
			'normalized_match_volatile_only'  => 0,
			'table_options_only_mismatch'     => 0,
			'body_only_mismatch'              => 0,
			'body_and_table_options_mismatch' => 0,
		);

		$raw_category_counts      = array_fill_keys( $keys, 0 );
		$residual_category_counts = array_fill_keys( $keys, 0 );

		$raw_mismatch_count   = 0;
		$norm_mismatch_count  = 0;
		$volatile_equal_count = 0;

		$grids_diag = array(
			'schema_match'          => false,
			'status'                => 'not_evaluated',
			'clause_classification' => 'not_evaluated',
			'raw_categories'        => array(),
			'residual_categories'   => array(),
		);

		foreach ( $source_tables_ddl as $table_name => $src_ddl ) {
			$scr_ddl = isset( $scratch_tables_ddl[ $table_name ] ) ? $scratch_tables_ddl[ $table_name ] : '';
			$res     = gm2_classify_single_table_mismatch( $src_ddl, $scr_ddl, $source_schema, $scratch_schema );

			if ( ! $res['is_raw_match'] ) {
				++$raw_mismatch_count;
				if ( $res['is_normalized_match'] ) {
					++$volatile_equal_count;
				}
			}
			if ( ! $res['is_normalized_match'] ) {
				++$norm_mismatch_count;
			}

			if ( isset( $clause_breakdown[ $res['clause_classification'] ] ) ) {
				++$clause_breakdown[ $res['clause_classification'] ];
			}

			foreach ( $res['raw_categories'] as $rcat ) {
				if ( isset( $raw_category_counts[ $rcat ] ) ) {
					++$raw_category_counts[ $rcat ];
				}
			}

			foreach ( $res['residual_categories'] as $rescat ) {
				if ( isset( $residual_category_counts[ $rescat ] ) ) {
					++$residual_category_counts[ $rescat ];
				}
			}

			if ( null !== $grids_table_name && (string) $table_name === (string) $grids_table_name ) {
				$grids_diag = array(
					'schema_match'          => $res['is_normalized_match'],
					'status'                => $res['status'],
					'clause_classification' => $res['clause_classification'],
					'raw_categories'        => $res['raw_categories'],
					'residual_categories'   => $res['residual_categories'],
				);
			}
		}

		return array(
			'total_tables'                          => count( $source_tables_ddl ),
			'raw_mismatch_table_count'              => $raw_mismatch_count,
			'normalized_mismatch_table_count'       => $norm_mismatch_count,
			'volatile_normalized_equal_table_count' => $volatile_equal_count,
			'clause_breakdown'                      => $clause_breakdown,
			'raw_category_counts'                   => $raw_category_counts,
			'residual_category_counts'              => $residual_category_counts,
			'grids_table_diagnostics'               => $grids_diag,
		);
	}

	/**
	 * Build an initial `not_run` map and structured check-status map for a list of check keys.
	 *
	 * @param array<int, string> $keys             Check identifiers.
	 * @param string             $sanitized_reason Sanitized reason why checks have not run.
	 * @return array{values: array<string, string>, checks: array<string, array{status: string, passed: null, reason: string}>}
	 */
	function gm2_build_not_run_checks_map( array $keys, $sanitized_reason ) {
		$values = array();
		$checks = array();
		$reason = (string) $sanitized_reason;

		foreach ( $keys as $key ) {
			$values[ $key ] = 'not_run';
			$checks[ $key ] = array(
				'status' => 'not_run',
				'passed' => null,
				'reason' => $reason,
			);
		}

		return array(
			'values' => $values,
			'checks' => $checks,
		);
	}

	/**
	 * Update any remaining `not_run` entries in a structured check map with a more specific sanitized reason.
	 *
	 * @param array<string, array{status: string, passed: bool|null, reason: string|null}> $checks           Existing check map.
	 * @param string                                                                        $sanitized_reason Updated sanitized reason.
	 * @return array<string, array{status: string, passed: bool|null, reason: string|null}>
	 */
	function gm2_update_not_run_check_reasons( array $checks, $sanitized_reason ) {
		$reason = (string) $sanitized_reason;
		foreach ( $checks as $key => $entry ) {
			if ( isset( $entry['status'] ) && 'not_run' === $entry['status'] ) {
				$checks[ $key ]['reason'] = $reason;
			}
		}
		return $checks;
	}

	/**
	 * Return the canonical list of comparison check keys and required comparison check keys.
	 *
	 * @return array{all: array<int, string>, required: array<int, string>}
	 */
	function gm2_get_rehearsal_comparison_keys() {
		$all = array(
			'table_list_exact_match',
			'source_all_tables_innodb',
			'scratch_all_tables_innodb',
			'table_engines_exact_match',
			'table_schema_exact_match',
			'table_create_raw_exact_match',
			'table_row_counts_exact_match',
			'grids_table_schema_exact_match',
			'grids_id_list_exact_match',
			'grids_raw_attributes_hash_exact_match',
			'grids_full_row_hash_exact_match',
			'active_plugins_option_hash_exact_match',
		);

		$required = array(
			'table_list_exact_match',
			'source_all_tables_innodb',
			'scratch_all_tables_innodb',
			'table_engines_exact_match',
			'table_schema_exact_match',
			'table_row_counts_exact_match',
			'grids_table_schema_exact_match',
			'grids_id_list_exact_match',
			'grids_raw_attributes_hash_exact_match',
			'grids_full_row_hash_exact_match',
			'active_plugins_option_hash_exact_match',
		);

		return array(
			'all'      => $all,
			'required' => $required,
		);
	}

	/**
	 * Return the canonical list of source before/after invariant check keys.
	 *
	 * @return array<int, string>
	 */
	function gm2_get_source_invariant_keys() {
		return array(
			'db_identity_unchanged',
			'table_count_unchanged',
			'table_engines_unchanged',
			'table_schemas_unchanged',
			'aggregate_row_count_unchanged',
			'per_table_row_counts_unchanged',
			'active_plugins_option_hash_unchanged',
			'grids_row_hash_unchanged',
		);
	}

	/**
	 * Evaluate all source-vs-scratch comparisons without short-circuiting on the first failure.
	 *
	 * @param array<string, mixed> $source_pre   Pre-rehearsal source DB snapshot.
	 * @param array<string, mixed> $scratch_snap Restored scratch DB snapshot.
	 * @return array{
	 *   comparisons: array<string, bool>,
	 *   comparison_checks: array<string, array{status: string, passed: bool, reason: string|null}>,
	 *   failed_required: array<int, string>,
	 *   errors: array<int, string>,
	 *   all_required_passed: bool
	 * }
	 */
	function gm2_evaluate_rehearsal_comparisons( array $source_pre, array $scratch_snap ) {
		$comparisons = array(
			'table_list_exact_match'                 => ( $source_pre['table_list'] === $scratch_snap['table_list'] ),
			'source_all_tables_innodb'               => ( true === $source_pre['all_tables_innodb'] && empty( $source_pre['non_innodb_tables'] ) ),
			'scratch_all_tables_innodb'              => ( true === $scratch_snap['all_tables_innodb'] && empty( $scratch_snap['non_innodb_tables'] ) ),
			'table_engines_exact_match'              => (
				$source_pre['table_engines'] === $scratch_snap['table_engines']
				&& $source_pre['engine_counts'] === $scratch_snap['engine_counts']
				&& true === $source_pre['all_tables_innodb']
				&& true === $scratch_snap['all_tables_innodb']
			),
			'table_schema_exact_match'               => ( $source_pre['table_schemas_norm'] === $scratch_snap['table_schemas_norm'] ),
			'table_create_raw_exact_match'           => ( $source_pre['table_schemas_raw'] === $scratch_snap['table_schemas_raw'] ),
			'table_row_counts_exact_match'           => (
				$source_pre['table_row_counts'] === $scratch_snap['table_row_counts']
				&& $source_pre['aggregate_row_count'] === $scratch_snap['aggregate_row_count']
			),
			'grids_table_schema_exact_match'         => ( $source_pre['grids_schema_norm'] === $scratch_snap['grids_schema_norm'] ),
			'grids_id_list_exact_match'              => ( $source_pre['grids_ids'] === $scratch_snap['grids_ids'] ),
			'grids_raw_attributes_hash_exact_match'  => ( $source_pre['grids_attr_hashes'] === $scratch_snap['grids_attr_hashes'] ),
			'grids_full_row_hash_exact_match'        => (
				$source_pre['grids_full_row_hashes'] === $scratch_snap['grids_full_row_hashes']
				&& $source_pre['grids_aggregate_hash'] === $scratch_snap['grids_aggregate_hash']
			),
			'active_plugins_option_hash_exact_match' => ( $source_pre['active_plugins_hash'] === $scratch_snap['active_plugins_hash'] ),
		);

		$key_meta          = gm2_get_rehearsal_comparison_keys();
		$required_lookup   = array_fill_keys( $key_meta['required'], true );
		$comparison_checks = array();
		$failed_required   = array();
		$errors            = array();

		foreach ( $comparisons as $comp_key => $comp_ok ) {
			$is_required = isset( $required_lookup[ $comp_key ] );
			if ( true === $comp_ok ) {
				$comparison_checks[ $comp_key ] = array(
					'status' => 'passed',
					'passed' => true,
					'reason' => null,
				);
			} else {
				$reason = $is_required
					? 'required_restore_comparison_mismatch'
					: 'informational_raw_ddl_differs_volatile_attributes_present';
				$comparison_checks[ $comp_key ] = array(
					'status' => 'failed',
					'passed' => false,
					'reason' => $reason,
				);
				if ( $is_required ) {
					$failed_required[] = $comp_key;
					$errors[]          = sprintf( 'Scratch DB comparison failed check: %s.', $comp_key );
				}
			}
		}

		return array(
			'comparisons'         => $comparisons,
			'comparison_checks'   => $comparison_checks,
			'failed_required'     => $failed_required,
			'errors'              => $errors,
			'all_required_passed' => empty( $failed_required ),
		);
	}

	/**
	 * Evaluate all source database before/after invariants without short-circuiting on the first failure.
	 *
	 * @param array<string, mixed> $source_pre           Pre-rehearsal source DB snapshot.
	 * @param array<string, mixed> $source_post          Post-cleanup source DB snapshot.
	 * @param string               $source_db            Expected source DB name (`DB_NAME`).
	 * @param string               $pre_source_identity  `SELECT DATABASE()` before rehearsal.
	 * @param string               $post_source_identity `SELECT DATABASE()` after scratch cleanup.
	 * @return array{
	 *   invariants: array<string, bool>,
	 *   invariant_checks: array<string, array{status: string, passed: bool, reason: string|null}>,
	 *   failed_invariants: array<int, string>,
	 *   errors: array<int, string>,
	 *   all_passed: bool
	 * }
	 */
	function gm2_evaluate_source_invariants(
		array $source_pre,
		array $source_post,
		$source_db,
		$pre_source_identity,
		$post_source_identity
	) {
		$invariants = array(
			'db_identity_unchanged'                => (
				(string) $post_source_identity === (string) $source_db
				&& (string) $post_source_identity === (string) $pre_source_identity
			),
			'table_count_unchanged'                => ( $source_pre['table_list'] === $source_post['table_list'] ),
			'table_engines_unchanged'              => (
				$source_pre['table_engines'] === $source_post['table_engines']
				&& true === $source_post['all_tables_innodb']
			),
			'table_schemas_unchanged'              => ( $source_pre['table_schemas_raw'] === $source_post['table_schemas_raw'] ),
			'aggregate_row_count_unchanged'        => ( $source_pre['aggregate_row_count'] === $source_post['aggregate_row_count'] ),
			'per_table_row_counts_unchanged'       => ( $source_pre['table_row_counts'] === $source_post['table_row_counts'] ),
			'active_plugins_option_hash_unchanged' => ( $source_pre['active_plugins_hash'] === $source_post['active_plugins_hash'] ),
			'grids_row_hash_unchanged'             => (
				$source_pre['grids_aggregate_hash'] === $source_post['grids_aggregate_hash']
				&& $source_pre['grids_full_row_hashes'] === $source_post['grids_full_row_hashes']
			),
		);

		$invariant_checks  = array();
		$failed_invariants = array();
		$errors            = array();

		foreach ( $invariants as $inv_key => $inv_ok ) {
			if ( true === $inv_ok ) {
				$invariant_checks[ $inv_key ] = array(
					'status' => 'passed',
					'passed' => true,
					'reason' => null,
				);
			} else {
				$invariant_checks[ $inv_key ] = array(
					'status' => 'failed',
					'passed' => false,
					'reason' => 'source_state_changed_after_rehearsal',
				);
				$failed_invariants[] = $inv_key;
				$errors[]            = sprintf( 'Source DB invariant check failed after rehearsal: %s.', $inv_key );
			}
		}

		return array(
			'invariants'        => $invariants,
			'invariant_checks'  => $invariant_checks,
			'failed_invariants' => $failed_invariants,
			'errors'            => $errors,
			'all_passed'        => empty( $failed_invariants ),
		);
	}
}

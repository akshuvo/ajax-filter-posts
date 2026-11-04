<?php
/**
 * GridMaster 2.0 M2-T01 — Dependency-free CLI storage characterization harness.
 *
 * Exercises the real `GridMaster\Grids`, `gm_get_grid()`, and `GridMaster\Shortcode`
 * ID-lookup code paths against an isolated in-memory `$wpdb` fixture without
 * connecting to WordPress or MySQL.
 *
 * Usage (from `ajax-filter-posts` repository root):
 *   php tests/gm2-storage/run.php
 *   php tests/gm2-storage/run.php --self-test-failure
 *
 * @package GridMaster
 */

namespace {
	if ( PHP_SAPI !== 'cli' ) {
		http_response_code( 403 );
		exit( "CLI execution only.\n" );
	}

	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'GRIDMASTER_VERSION', '3.4.18' );
	define( 'GRIDMASTER_FILE', dirname( __DIR__, 2 ) . '/ajax-filter-posts.php' );
	define( 'GRIDMASTER_PATH', dirname( __DIR__, 2 ) );
	define( 'GRIDMASTER_URL', 'http://example.invalid/wp-content/plugins/ajax-filter-posts' );
	define( 'GRIDMASTER_ASSETS', GRIDMASTER_URL . '/assets/' );
	define( 'GRIDMASTER_PRO_LINK', 'https://addonmaster.com/post-grid-master-pro/' );

	/**
	 * TEST-ONLY DOUBLE: Tracks WordPress hooks, shortcodes, enqueued styles, and password counter
	 * in memory so each test case can reset state deterministically.
	 */
	final class GM2_Test_WP_Env {
		/** @var array<string, array<int, array<int, callable>>> */
		public static $filters = array();

		/** @var array<string, callable> */
		public static $shortcodes = array();

		/** @var array<int, array<string, mixed>> */
		public static $enqueued_styles = array();

		/** @var int */
		public static $password_counter = 0;

		/** @var array<string, mixed> */
		public static $options = array();

		public static function reset() {
			self::$filters          = array();
			self::$shortcodes       = array();
			self::$enqueued_styles  = array();
			self::$password_counter = 0;
			self::$options          = array();
			\WP_Query::reset();
		}
	}

	/**
	 * TEST-ONLY DOUBLE: Minimal in-memory `$wpdb` replacement modeling only
	 * `{$wpdb->prefix}gridmaster_grids` queries executed by `GridMaster\Grids`.
	 * Never opens a MySQL socket or touches the Local database.
	 */
	final class GM2_Test_Disposable_Wpdb {
		/** @var string */
		public $prefix = 'wp_gm2_test_';

		/** @var int */
		public $insert_id = 0;

		/** @var array<int, string> */
		public $queries = array();

		/** @var array<int, array<string, string>> */
		private $rows = array();

		/** @var int */
		private $auto_increment = 1;

		/**
		 * Reset in-memory table from the deterministic fixture definition.
		 *
		 * @param array<string, mixed> $fixture Fixture data.
		 * @return void
		 */
		public function reset_from_fixture( array $fixture ) {
			$this->prefix         = isset( $fixture['table_prefix'] ) ? (string) $fixture['table_prefix'] : 'wp_gm2_test_';
			$this->insert_id      = 0;
			$this->queries        = array();
			$this->rows           = array();
			$this->auto_increment = 1;

			if ( isset( $fixture['rows'] ) && is_array( $fixture['rows'] ) ) {
				foreach ( $fixture['rows'] as $row ) {
					$id                 = (int) $row['id'];
					$this->rows[ $id ]  = array(
						'id'         => (string) $id,
						'title'      => (string) $row['title'],
						'attributes' => (string) $row['raw_attributes_serialized'],
						'dated'      => (string) $row['dated'],
					);
					if ( $id >= $this->auto_increment ) {
						$this->auto_increment = $id + 1;
					}
				}
			}
		}

		/**
		 * Minimal `$wpdb->prepare()` implementation for `%d` and `%s` placeholders.
		 *
		 * @param string $query_template SQL query with placeholders.
		 * @param mixed  ...$args        Values to substitute.
		 * @return string
		 */
		public function prepare( $query_template, ...$args ) {
			if ( isset( $args[0] ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			$index = 0;
			return (string) preg_replace_callback(
				'/%(?:d|s)/',
				function ( $matches ) use ( $args, &$index ) {
					$val = isset( $args[ $index ] ) ? $args[ $index ] : null;
					$index++;
					if ( '%d' === $matches[0] ) {
						return (string) intval( $val );
					}
					return "'" . addslashes( (string) $val ) . "'";
				},
				(string) $query_template
			);
		}

		/**
		 * Return a fresh stdClass copy of a single row, matching `$wpdb->get_row()` OBJECT mode.
		 *
		 * @param string $query Prepared SQL query.
		 * @return object|null
		 */
		public function get_row( $query ) {
			$this->queries[] = (string) $query;
			$table           = preg_quote( $this->prefix . 'gridmaster_grids', '/' );
			if ( preg_match( '/^SELECT \* FROM ' . $table . ' WHERE id = (-?\d+)$/', trim( (string) $query ), $matches ) ) {
				$id = (int) $matches[1];
				if ( isset( $this->rows[ $id ] ) ) {
					return (object) $this->rows[ $id ];
				}
			}
			return null;
		}

		/**
		 * Return fresh stdClass copies for `GridMaster\Grids::list()`.
		 *
		 * @param string $query SQL query.
		 * @return array<int, object>
		 */
		public function get_results( $query ) {
			$this->queries[] = (string) $query;
			$table           = preg_quote( $this->prefix . 'gridmaster_grids', '/' );
			if ( preg_match( '/^SELECT \* FROM ' . $table . ' ORDER BY id DESC LIMIT (\d+)$/', trim( (string) $query ), $matches ) ) {
				$limit = (int) $matches[1];
				$ids   = array_keys( $this->rows );
				rsort( $ids, SORT_NUMERIC );
				$ids     = array_slice( $ids, 0, $limit );
				$results = array();
				foreach ( $ids as $id ) {
					$results[] = (object) $this->rows[ $id ];
				}
				return $results;
			}
			return array();
		}

		/**
		 * Insert a row into the in-memory table.
		 *
		 * @param string               $table Table name.
		 * @param array<string, mixed> $data  Column map.
		 * @return int|false
		 */
		public function insert( $table, array $data ) {
			if ( $this->prefix . 'gridmaster_grids' !== $table ) {
				return false;
			}
			$id                = $this->auto_increment++;
			$this->insert_id   = $id;
			$this->rows[ $id ] = array(
				'id'         => (string) $id,
				'title'      => isset( $data['title'] ) ? (string) $data['title'] : '',
				'attributes' => isset( $data['attributes'] ) ? (string) $data['attributes'] : '',
				'dated'      => isset( $data['dated'] ) ? (string) $data['dated'] : '2026-11-04 00:00:00',
			);
			return 1;
		}

		/**
		 * Update an existing row in the in-memory table.
		 *
		 * @param string               $table Table name.
		 * @param array<string, mixed> $data  Updated columns.
		 * @param array<string, mixed> $where Where clause map.
		 * @return int|false
		 */
		public function update( $table, array $data, array $where ) {
			if ( $this->prefix . 'gridmaster_grids' !== $table ) {
				return false;
			}
			$id = isset( $where['id'] ) ? (int) $where['id'] : 0;
			if ( ! isset( $this->rows[ $id ] ) ) {
				return 0;
			}
			$changed = 0;
			foreach ( array( 'title', 'attributes' ) as $column ) {
				if ( array_key_exists( $column, $data ) ) {
					$new_value = (string) $data[ $column ];
					if ( $this->rows[ $id ][ $column ] !== $new_value ) {
						$this->rows[ $id ][ $column ] = $new_value;
						$changed                      = 1;
					}
				}
			}
			return $changed;
		}

		/**
		 * Delete a row from the in-memory table.
		 *
		 * @param string               $table Table name.
		 * @param array<string, mixed> $where Where clause map.
		 * @return int|false
		 */
		public function delete( $table, array $where ) {
			if ( $this->prefix . 'gridmaster_grids' !== $table ) {
				return false;
			}
			$id = isset( $where['id'] ) ? (int) $where['id'] : 0;
			if ( ! isset( $this->rows[ $id ] ) ) {
				return 0;
			}
			unset( $this->rows[ $id ] );
			return 1;
		}

		/**
		 * Inspect raw stored `attributes` bytes for a row without unserializing.
		 *
		 * @param int $id Grid ID.
		 * @return string|null
		 */
		public function get_raw_row_bytes( $id ) {
			$id = (int) $id;
			return isset( $this->rows[ $id ] ) ? $this->rows[ $id ]['attributes'] : null;
		}

		/**
		 * Return total row count currently in the disposable table.
		 *
		 * @return int
		 */
		public function count_rows() {
			return count( $this->rows );
		}
	}

	/**
	 * TEST-ONLY DOUBLE: Minimal `WP_Query` stub that records compiled query args
	 * and returns zero posts so `GridMaster\Shortcode::render_shortcode()` can be
	 * exercised without a WordPress database.
	 */
	class WP_Query {
		/** @var array<int, array<string, mixed>> */
		public static $recorded_queries = array();

		/** @var array<string, mixed> */
		public $query_args = array();

		/** @var int */
		public $max_num_pages = 0;

		/**
		 * Record query args.
		 *
		 * @param array<string, mixed> $query_args Query arguments.
		 */
		public function __construct( $query_args = array() ) {
			$this->query_args         = is_array( $query_args ) ? $query_args : array();
			self::$recorded_queries[] = $this->query_args;
		}

		public static function reset() {
			self::$recorded_queries = array();
		}

		/**
		 * Always returns false in the storage characterization stub.
		 *
		 * @return bool
		 */
		public function have_posts() {
			return false;
		}

		public function the_post() {
		}
	}

	// Minimal WordPress function shims required by `inc/functions.php`, `admin/Grids.php`, and `inc/Shortcode.php`.
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}

	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return esc_html( $text );
	}

	function esc_html_x( $text, $context, $domain = 'default' ) {
		unset( $context, $domain );
		return esc_html( $text );
	}

	function _nx( $single, $plural, $number, $context, $domain = 'default' ) {
		unset( $context, $domain );
		return 1 === (int) $number ? (string) $single : (string) $plural;
	}

	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}

	function sanitize_text_field( $str ) {
		if ( is_object( $str ) || is_array( $str ) ) {
			return '';
		}
		$filtered = strip_tags( (string) $str );
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
		return trim( (string) $filtered );
	}

	function sanitize_textarea_field( $str ) {
		if ( is_object( $str ) || is_array( $str ) ) {
			return '';
		}
		return trim( strip_tags( (string) $str ) );
	}

	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $filename );
	}

	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}

	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}

	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed_args = $args;
		} else {
			parse_str( (string) $args, $parsed_args );
		}

		if ( is_array( $defaults ) && ! empty( $defaults ) ) {
			return array_merge( $defaults, $parsed_args );
		}
		return $parsed_args;
	}

	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			if ( array_key_exists( $name, $atts ) ) {
				$out[ $name ] = $atts[ $name ];
			} else {
				$out[ $name ] = $default;
			}
		}
		if ( $shortcode ) {
			$out = apply_filters( "shortcode_atts_{$shortcode}", $out, $pairs, $atts, $shortcode );
		}
		return $out;
	}

	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) {
						return false;
					}
				} elseif ( false === strpos( $data, '"' ) ) {
					return false;
				}
				// Intentional fall-through.
			case 'a':
			case 'O':
			case 'E':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
		}
		return false;
	}

	function maybe_unserialize( $data ) {
		if ( is_serialized( $data ) ) {
			return @unserialize( trim( $data ) );
		}
		return $data;
	}

	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		unset( $special_chars, $extra_special_chars );
		GM2_Test_WP_Env::$password_counter++;
		$seed = sprintf( 'gm2pass%04dabcdef0123456789', GM2_Test_WP_Env::$password_counter );
		return substr( $seed, 0, max( 1, (int) $length ) );
	}

	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}

	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $accepted_args );
		$priority                                          = (int) $priority;
		GM2_Test_WP_Env::$filters[ $hook_name ][ $priority ][] = $callback;
		return true;
	}

	function apply_filters( $hook_name, $value, ...$args ) {
		if ( empty( GM2_Test_WP_Env::$filters[ $hook_name ] ) ) {
			return $value;
		}
		$priorities = GM2_Test_WP_Env::$filters[ $hook_name ];
		ksort( $priorities, SORT_NUMERIC );
		$all_args = array_merge( array( $value ), $args );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value         = call_user_func_array( $callback, $all_args );
				$all_args[0] = $value;
			}
		}
		return $value;
	}

	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	function do_action( $hook_name, ...$args ) {
		if ( empty( GM2_Test_WP_Env::$filters[ $hook_name ] ) ) {
			return;
		}
		$priorities = GM2_Test_WP_Env::$filters[ $hook_name ];
		ksort( $priorities, SORT_NUMERIC );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				call_user_func_array( $callback, $args );
			}
		}
	}

	function add_shortcode( $tag, $callback ) {
		GM2_Test_WP_Env::$shortcodes[ (string) $tag ] = $callback;
	}

	function is_admin() {
		return false;
	}

	function current_user_can( $capability ) {
		unset( $capability );
		return false;
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		GM2_Test_WP_Env::$enqueued_styles[] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'media'  => (string) $media,
		);
	}

	function get_option( $option, $default = false ) {
		return array_key_exists( $option, GM2_Test_WP_Env::$options )
			? GM2_Test_WP_Env::$options[ $option ]
			: $default;
	}

	function get_query_var( $var, $default = '' ) {
		unset( $var );
		return $default;
	}

	function wp_reset_postdata() {
	}

	// Load the real production files under test.
	require_once GRIDMASTER_PATH . '/inc/functions.php';
	require_once GRIDMASTER_PATH . '/admin/Grids.php';
	require_once GRIDMASTER_PATH . '/inc/Shortcode.php';

	/**
	 * Minimal assertion runner with explicit failure tracking.
	 */
	final class GM2_Storage_Characterization_Runner {
		/** @var int */
		private $assertions = 0;

		/** @var int */
		private $suites_run = 0;

		/** @var array<int, string> */
		private $failures = array();

		/** @var string */
		private $fixture_path;

		/**
		 * @param string $fixture_path Path to deterministic fixture file.
		 */
		public function __construct( $fixture_path ) {
			$this->fixture_path = (string) $fixture_path;
		}

		/**
		 * Load a fresh copy of the deterministic legacy fixture array.
		 *
		 * @return array<string, mixed>
		 */
		private function load_fresh_fixture() {
			$fixture = require $this->fixture_path;
			$this->assert_true( is_array( $fixture ), 'Fixture file must return an array' );
			return $fixture;
		}

		/**
		 * Reset both the disposable `$wpdb` instance and WordPress hook state.
		 *
		 * @return array{0: GM2_Test_Disposable_Wpdb, 1: array<string, mixed>}
		 */
		private function create_isolated_environment() {
			GM2_Test_WP_Env::reset();
			$fixture = $this->load_fresh_fixture();
			$wpdb    = new GM2_Test_Disposable_Wpdb();
			$wpdb->reset_from_fixture( $fixture );
			$GLOBALS['wpdb'] = $wpdb;
			return array( $wpdb, $fixture );
		}

		public function assert_true( $condition, $message ) {
			$this->assertions++;
			if ( true !== $condition ) {
				$this->failures[] = sprintf( '%s (expected true, got %s)', $message, var_export( $condition, true ) );
			}
		}

		public function assert_same( $expected, $actual, $message ) {
			$this->assertions++;
			if ( $expected !== $actual ) {
				$this->failures[] = sprintf(
					"%s\n  expected: %s\n  actual:   %s",
					$message,
					var_export( $expected, true ),
					var_export( $actual, true )
				);
			}
		}

		/**
		 * Execute all normal characterization suites.
		 *
		 * @return int Exit status code (0 on pass, 1 on failure).
		 */
		public function run_all() {
			$this->suite_exact_legacy_bytes_and_numeric_lookup();
			$this->suite_missing_ids_and_defaults();
			$this->suite_shortcode_id_lookup_and_overrides();
			$this->suite_crud_isolation_and_baseline_gaps();

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
					"PASS: %d suites, %d assertions, 0 failures (test-only disposable wpdb/WP shims; no MySQL/Local DB connection).\n",
					$this->suites_run,
					$this->assertions
				)
			);
			return 0;
		}

		/**
		 * Execute deliberate failure path to prove nonzero exit on assertion failure.
		 *
		 * @return int Exit status code (1 when self-test catches the deliberate failure).
		 */
		public function run_self_test_failure() {
			$this->suites_run++;
			$this->assert_same(
				'expected-legacy-byte-sentinel',
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
		 * Suite 1: Numeric ID retrieval and exact serialized attribute byte round-trip.
		 */
		private function suite_exact_legacy_bytes_and_numeric_lookup() {
			$this->suites_run++;
			list( $wpdb, $fixture ) = $this->create_isolated_environment();

			$expected_sha256 = array(
				42  => hash( 'sha256', $fixture['rows'][42]['raw_attributes_serialized'] ),
				108 => hash( 'sha256', $fixture['rows'][108]['raw_attributes_serialized'] ),
			);

			foreach ( array( 42, 108 ) as $grid_id ) {
				$row_fixture = $fixture['rows'][ $grid_id ];
				$raw_bytes   = $row_fixture['raw_attributes_serialized'];

				$this->assert_same(
					$row_fixture['raw_attributes_length'],
					strlen( $raw_bytes ),
					"Row {$grid_id} serialized fixture byte length matches pinned length"
				);
				$this->assert_same(
					$raw_bytes,
					$wpdb->get_raw_row_bytes( $grid_id ),
					"Row {$grid_id} initial disposable table bytes match fixture exactly"
				);

				// Load via GridMaster\Grids::get() with integer ID.
				$grid = \GridMaster\Grids::get( $grid_id );
				$this->assert_true( is_object( $grid ), "Grids::get({$grid_id}) returns an object" );
				$this->assert_same( (string) $grid_id, $grid->id, "Grids::get({$grid_id}) preserves string numeric id property" );
				$this->assert_same( $row_fixture['title'], $grid->title, "Grids::get({$grid_id}) preserves title" );
				$this->assert_same( $row_fixture['dated'], $grid->dated, "Grids::get({$grid_id}) preserves original dated timestamp" );
				$this->assert_same(
					$row_fixture['expected_attributes'],
					$grid->attributes,
					"Grids::get({$grid_id}) unserializes exact expected attribute structure"
				);

				// Verify round-trip serialization produces the exact legacy byte stream and SHA-256.
				$reserialized = serialize( $grid->attributes );
				$this->assert_same(
					$raw_bytes,
					$reserialized,
					"Row {$grid_id} re-serialized attributes match legacy fixture bytes exactly"
				);
				$this->assert_same(
					$expected_sha256[ $grid_id ],
					hash( 'sha256', $reserialized ),
					"Row {$grid_id} re-serialized SHA-256 matches legacy fixture digest"
				);

				// Verify in-place unserialization on returned row object did not mutate raw table storage.
				$this->assert_same(
					$raw_bytes,
					$wpdb->get_raw_row_bytes( $grid_id ),
					"Row {$grid_id} raw bytes in disposable table remain unmutated after Grids::get()"
				);

				// Load via global gm_get_grid() with numeric string ID.
				$via_helper = \gm_get_grid( (string) $grid_id );
				$this->assert_true( is_object( $via_helper ), "gm_get_grid('{$grid_id}') returns an object" );
				$this->assert_same(
					$row_fixture['expected_attributes'],
					$via_helper->attributes,
					"gm_get_grid('{$grid_id}') returns identical decoded attributes"
				);
			}

			// Verify stored premium/Pro keys on row 108 survive Free-only read without stripping.
			$pro_grid = \gm_get_grid( 108 );
			$this->assert_same( 'pro-style-4', $pro_grid->attributes['grid_style'], 'Row 108 retains pro-style-4 in Free-only read' );
			$this->assert_same( 'pro-filter-1', $pro_grid->attributes['filter_style'], 'Row 108 retains pro-filter-1 in Free-only read' );
			$this->assert_same( 'auto', $pro_grid->attributes['post_type_selection'], 'Row 108 retains post_type_selection=auto' );
			$this->assert_same( 'yes', $pro_grid->attributes['multiple_select'], 'Row 108 retains multiple_select=yes' );
			$this->assert_same( 'yes', $pro_grid->attributes['enable_slider'], 'Row 108 retains enable_slider=yes' );
		}

		/**
		 * Suite 2: Missing-ID behavior and `$defaults = true` fallback characterization.
		 */
		private function suite_missing_ids_and_defaults() {
			$this->suites_run++;
			list( $wpdb, $fixture ) = $this->create_isolated_environment();

			foreach ( $fixture['missing_ids'] as $missing_id ) {
				$this->assert_same(
					null,
					\GridMaster\Grids::get( $missing_id ),
					"Grids::get({$missing_id}) returns null for absent/non-positive ID"
				);
				$this->assert_same(
					null,
					\gm_get_grid( $missing_id ),
					"gm_get_grid({$missing_id}) returns null for absent/non-positive ID"
				);
			}

			// Positive missing ID still queries prepared SELECT once per lookup; id=0 with $defaults=true returns in-memory defaults without SQL.
			$query_count_before = count( $wpdb->queries );
			$default_grid       = \gm_get_grid( 0, true );
			$this->assert_same(
				$query_count_before,
				count( $wpdb->queries ),
				'gm_get_grid(0, true) does not issue a database query'
			);
			$this->assert_true( is_object( $default_grid ), 'gm_get_grid(0, true) returns stdClass object' );
			$this->assert_same(
				'GridMaster #gm2pass0',
				$default_grid->title,
				'gm_get_grid(0, true) formats default title with 8-char generated suffix'
			);
			$this->assert_same(
				\GridMaster\Grids::$defaults,
				$default_grid->attributes,
				'gm_get_grid(0, true) returns GridMaster\\Grids::$defaults'
			);
		}

		/**
		 * Suite 3: Shortcode ID lookup (`GridMaster\Shortcode::render_shortcode`) for saved, overridden, and missing IDs.
		 */
		private function suite_shortcode_id_lookup_and_overrides() {
			$this->suites_run++;
			list( $wpdb, $fixture ) = $this->create_isolated_environment();
			unset( $wpdb, $fixture );

			$shortcode = new \GridMaster\Shortcode();

			// 1. Saved ID lookup (`[gridmaster id="42" show_filter="no"]`).
			$html_saved = $shortcode->render_shortcode(
				array(
					'id'          => '42',
					'show_filter' => 'no',
				)
			);
			$this->assert_true(
				false !== strpos( $html_saved, 'id="gm-42"' ),
				'Saved shortcode ID 42 emits deterministic DOM id="gm-42"'
			);
			$this->assert_true(
				false !== strpos( $html_saved, 'data-grid-style="style-2"' ),
				'Saved shortcode ID 42 applies stored grid_style="style-2"'
			);
			$this->assert_true(
				false !== strpos( $html_saved, 'data-pagination_type="load_more"' ),
				'Saved shortcode ID 42 applies stored pagination_type="load_more"'
			);

			$public_atts_42 = $this->extract_public_atts_json( $html_saved );
			$this->assert_same( 'gm-42', $public_atts_42['grid_id'], 'Saved shortcode ID 42 public_atts has grid_id="gm-42"' );
			$this->assert_same( '6', $public_atts_42['posts_per_page'], 'Saved shortcode ID 42 public_atts retains stored posts_per_page="6"' );
			$this->assert_same( '20', $public_atts_42['excerpt_length'], 'Saved shortcode ID 42 public_atts retains stored excerpt_length="20"' );
			$this->assert_same( 'Continue Reading', $public_atts_42['read_more_text'], 'Saved shortcode ID 42 public_atts retains stored read_more_text' );
			$this->assert_same( '3,7', $public_atts_42['terms'], 'Saved shortcode ID 42 public_atts retains stored terms="3,7"' );

			// Verify baseline `shortcode_atts` behavior: keys in `Grids::$defaults` not in `Shortcode::$default_atts` are filtered out.
			$this->assert_same(
				false,
				array_key_exists( 'heading_font_size', $public_atts_42 ),
				'Characterization: shortcode_atts strips stored keys absent from Shortcode default_atts (e.g. heading_font_size)'
			);

			// 2. Inline shortcode attribute override precedence over stored grid attributes.
			$html_override = $shortcode->render_shortcode(
				array(
					'id'             => '42',
					'posts_per_page' => '4',
					'grid_style'     => 'style-3',
					'show_filter'    => 'no',
				)
			);
			$public_atts_override = $this->extract_public_atts_json( $html_override );
			$this->assert_same( 'gm-42', $public_atts_override['grid_id'], 'Overridden saved shortcode keeps deterministic grid_id="gm-42"' );
			$this->assert_same( '4', $public_atts_override['posts_per_page'], 'Explicit inline posts_per_page="4" overrides stored "6"' );
			$this->assert_same( 'style-3', $public_atts_override['grid_style'], 'Explicit inline grid_style="style-3" overrides stored "style-2"' );
			$this->assert_same( '20', $public_atts_override['excerpt_length'], 'Non-overridden stored excerpt_length="20" remains intact' );

			// 3. Missing ID lookup (`[gridmaster id="9999" show_filter="no"]`) falls back to generated `gm-<random>` ID and Shortcode defaults.
			$html_missing        = $shortcode->render_shortcode(
				array(
					'id'          => '9999',
					'show_filter' => 'no',
				)
			);
			$public_atts_missing = $this->extract_public_atts_json( $html_missing );
			$this->assert_true(
				'gm-9999' !== $public_atts_missing['grid_id'] && 0 === strpos( (string) $public_atts_missing['grid_id'], 'gm-' ),
				'Missing saved ID 9999 does not emit gm-9999; falls back to generated gm-* ID'
			);
			$this->assert_same(
				'default',
				$public_atts_missing['grid_style'],
				'Missing saved ID 9999 falls back to Shortcode default grid_style="default"'
			);
			$this->assert_same(
				9,
				$public_atts_missing['posts_per_page'],
				'Missing saved ID 9999 falls back to Shortcode default posts_per_page=9 (distinct from Grids::$defaults 10)'
			);
		}

		/**
		 * Suite 4: CRUD round-trip, baseline gaps (`save` on missing ID, `list` 100-row hard cap), and disposable fixture isolation.
		 */
		private function suite_crud_isolation_and_baseline_gaps() {
			$this->suites_run++;

			// Run A: Mutate the disposable store heavily.
			list( $wpdb_a, $fixture_a ) = $this->create_isolated_environment();
			$grids_repo                 = \GridMaster\Grids::init();

			// Insert new grid (no ID -> allocates 109).
			$new_id = $grids_repo->save(
				array(
					'title'      => 'Disposable Newly Inserted Grid',
					'attributes' => \GridMaster\Grids::$defaults,
				)
			);
			$this->assert_same( 109, $new_id, 'Grids::save() without id inserts and returns next integer insert_id (109)' );
			$this->assert_same(
				serialize( \GridMaster\Grids::$defaults ),
				$wpdb_a->get_raw_row_bytes( 109 ),
				'Inserted row 109 stores exact serialize(Grids::$defaults) bytes'
			);

			// Update existing row 42.
			$updated_attrs                   = $fixture_a['rows'][42]['expected_attributes'];
			$updated_attrs['posts_per_page'] = '15';
			$returned_id                     = $grids_repo->save(
				array(
					'id'         => 42,
					'title'      => 'Mutated Blog Grid Title',
					'attributes' => $updated_attrs,
				)
			);
			$this->assert_same( 42, $returned_id, 'Grids::save() with existing id=42 returns 42' );
			$reloaded_42 = \GridMaster\Grids::get( 42 );
			$this->assert_same( 'Mutated Blog Grid Title', $reloaded_42->title, 'Updated row 42 reflects new title in Run A' );
			$this->assert_same( '15', $reloaded_42->attributes['posts_per_page'], 'Updated row 42 reflects updated attribute in Run A' );
			$this->assert_same( '2025-11-15 09:30:00', $reloaded_42->dated, 'Updated row 42 preserves original dated value' );

			// Characterize baseline gap 1: `Grids::save()` with non-existent positive ID returns that ID even though 0 rows were updated.
			$ghost_id = $grids_repo->save(
				array(
					'id'         => 7777,
					'title'      => 'Non-existent Update Target',
					'attributes' => \GridMaster\Grids::$defaults,
				)
			);
			$this->assert_same(
				7777,
				$ghost_id,
				'Characterization (baseline gap): Grids::save() with missing id=7777 still returns 7777 without checking wpdb->update outcome'
			);
			$this->assert_same(
				null,
				\GridMaster\Grids::get( 7777 ),
				'Characterization (baseline gap): Missing id=7777 was not inserted by update path'
			);

			// Delete row 108 in Run A.
			$grids_repo->delete( 108 );
			$this->assert_same( null, \GridMaster\Grids::get( 108 ), 'Grids::delete(108) removes row 108 in Run A' );

			// Characterize baseline gap 2: `Grids::list()` ignores `$args` and hard-caps at `ORDER BY id DESC LIMIT 100`.
			for ( $i = 1; $i <= 105; $i++ ) {
				$grids_repo->save(
					array(
						'title'      => 'Bulk Grid #' . $i,
						'attributes' => array( 'id' => (string) $i ),
					)
				);
			}
			$this->assert_true( $wpdb_a->count_rows() > 100, 'Disposable table holds >100 rows before Grids::list()' );
			$listed = $grids_repo->list(
				array(
					'per_page' => 20,
					'page'     => 2,
				)
			);
			$this->assert_same(
				100,
				count( $listed ),
				'Characterization (baseline gap): Grids::list($args) ignores $args and returns hard LIMIT 100 rows'
			);
			$this->assert_true(
				(int) $listed[0]->id > (int) $listed[99]->id,
				'Grids::list() orders rows by id DESC'
			);

			// Run B: Fresh isolated environment must show zero leakage from Run A mutations.
			list( $wpdb_b, $fixture_b ) = $this->create_isolated_environment();
			$this->assert_same( 2, $wpdb_b->count_rows(), 'Fresh isolated environment resets row count to 2 (IDs 42 and 108)' );
			$this->assert_same( null, \GridMaster\Grids::get( 109 ), 'Row 109 from Run A does not leak into Run B' );
			$this->assert_same(
				$fixture_b['rows'][42]['raw_attributes_serialized'],
				$wpdb_b->get_raw_row_bytes( 42 ),
				'Row 42 raw serialized bytes in Run B match original fixture exactly'
			);
			$fresh_42 = \GridMaster\Grids::get( 42 );
			$this->assert_same( 'Featured Blog Grid', $fresh_42->title, 'Row 42 title in Run B is unmodified' );
			$fresh_108 = \GridMaster\Grids::get( 108 );
			$this->assert_true( is_object( $fresh_108 ), 'Row 108 deleted in Run A is present again in Run B' );
		}

		/**
		 * Extract and decode the `data-am_ajax_post_grid` JSON attribute from shortcode wrapper markup.
		 *
		 * @param string $html Rendered shortcode HTML.
		 * @return array<string, mixed>
		 */
		private function extract_public_atts_json( $html ) {
			$matched = preg_match( "/data-am_ajax_post_grid='([^']*)'/", (string) $html, $matches );
			$this->assert_same( 1, $matched, 'Shortcode output contains data-am_ajax_post_grid attribute' );
			if ( 1 !== $matched ) {
				return array();
			}
			$json    = htmlspecialchars_decode( $matches[1], ENT_QUOTES );
			$decoded = json_decode( $json, true );
			$this->assert_true( is_array( $decoded ), 'data-am_ajax_post_grid decodes to an array' );
			return is_array( $decoded ) ? $decoded : array();
		}
	}

	$gm2_runner = new GM2_Storage_Characterization_Runner( __DIR__ . '/fixtures/legacy-grids.php' );
	$gm2_args   = isset( $argv ) && is_array( $argv ) ? array_slice( $argv, 1 ) : array();

	if ( in_array( '--self-test-failure', $gm2_args, true ) ) {
		exit( $gm2_runner->run_self_test_failure() );
	}

	exit( $gm2_runner->run_all() );
}

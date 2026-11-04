<?php
namespace GridMaster\Facets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry and rendering engine for GridMaster filters and facets.
 */
class Filter_Registry {

	/**
	 * Registered facet handlers.
	 *
	 * @var array<string, string>
	 */
	private static $handlers = array();

	/**
	 * Registers a facet type handler class.
	 *
	 * @param string $type Unique facet type slug.
	 * @param string $class Fully-qualified class name implementing Facet_Handler_Interface.
	 */
	public static function register( $type, $class ) {
		self::$handlers[ sanitize_key( $type ) ] = $class;
	}

	/**
	 * Returns registered facet handlers.
	 *
	 * @return array
	 */
	public static function get_handlers() {
		self::ensure_defaults();
		return self::$handlers;
	}

	/**
	 * Renders all configured facets for a grid.
	 *
	 * @param array $facets Array of facet configurations.
	 * @param int   $grid_id Grid ID.
	 * @param array $runtime_params Runtime parameters.
	 * @param bool  $is_preview True if preview rendering.
	 * @return string HTML.
	 */
	public static function render_facets( $facets, $grid_id, $runtime_params = array(), $is_preview = false ) {
		self::ensure_defaults();

		if ( empty( $facets ) || ! is_array( $facets ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="gm-filters-wrap" data-grid-id="<?php echo esc_attr( $grid_id ); ?>" role="region" aria-label="<?php esc_attr_e( 'Grid Filters', 'ajax-filter-posts' ); ?>">
			<?php
			foreach ( $facets as $facet ) {
				$type = isset( $facet['type'] ) ? sanitize_key( $facet['type'] ) : 'taxonomy-tabs';
				if ( isset( self::$handlers[ $type ] ) && class_exists( self::$handlers[ $type ] ) ) {
					$handler = self::$handlers[ $type ];
					echo $handler::render( $facet, $grid_id, $runtime_params, $is_preview ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Ensures default Free filter types are registered.
	 */
	private static function ensure_defaults() {
		if ( empty( self::$handlers ) ) {
			self::register( 'taxonomy-tabs', __NAMESPACE__ . '\Handlers\Taxonomy_Tabs' );
			self::register( 'taxonomy-dropdown', __NAMESPACE__ . '\Handlers\Taxonomy_Dropdown' );
			self::register( 'taxonomy-checkbox', __NAMESPACE__ . '\Handlers\Taxonomy_Checkbox' );
			self::register( 'taxonomy-radio', __NAMESPACE__ . '\Handlers\Taxonomy_Radio' );
			self::register( 'search', __NAMESPACE__ . '\Handlers\Search_Input' );
			self::register( 'reset', __NAMESPACE__ . '\Handlers\Reset_Button' );

			/**
			 * Action to allow Pro and extensions to register custom facets.
			 */
			do_action( 'gridmaster_register_facets' );
		}
	}
}

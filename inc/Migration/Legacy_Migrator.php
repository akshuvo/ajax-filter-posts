<?php
namespace GridMaster\Migration;

use GridMaster\Storage\Grid_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration engine for upgrading legacy serialized grids to GridMaster 2.0.
 */
class Legacy_Migrator {

	/**
	 * Migrates a legacy grid to v2 Gutenberg structure.
	 *
	 * @param int $grid_id Grid ID.
	 * @return bool|\WP_Error
	 */
	public static function migrate( $grid_id ) {
		$grid = Grid_Repository::get( $grid_id );
		if ( ! $grid ) {
			return new \WP_Error( 'invalid_grid', __( 'Grid not found.', 'ajax-filter-posts' ) );
		}

		$attrs = ! empty( $grid->attributes ) ? (array) $grid->attributes : array();

		// 1. Build Query Config
		$post_type      = ! empty( $attrs['post_type'] ) ? sanitize_key( $attrs['post_type'] ) : 'post';
		$posts_per_page = ! empty( $attrs['posts_per_page'] ) ? absint( $attrs['posts_per_page'] ) : 9;
		$orderby        = ! empty( $attrs['orderby'] ) ? sanitize_key( $attrs['orderby'] ) : 'date';
		$order          = ! empty( $attrs['order'] ) ? strtoupper( sanitize_key( $attrs['order'] ) ) : 'DESC';
		$taxonomy       = ! empty( $attrs['taxonomy'] ) ? sanitize_key( $attrs['taxonomy'] ) : 'category';

		$tax_query = array();
		if ( ! empty( $attrs['terms'] ) ) {
			$terms = is_array( $attrs['terms'] ) ? $attrs['terms'] : explode( ',', $attrs['terms'] );
			$terms = array_filter( array_map( 'absint', $terms ) );
			if ( ! empty( $terms ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'terms'    => $terms,
					'operator' => 'IN',
				);
			}
		}

		$query_config = array(
			'post_type'      => $post_type,
			'posts_per_page' => $posts_per_page,
			'orderby'        => $orderby,
			'order'          => $order,
			'tax_query'      => $tax_query,
			'author'         => '',
			'search'         => '',
			'hide_empty'     => true,
		);

		// 2. Build Facets Config
		$facets = array();
		if ( ! empty( $attrs['show_filter'] ) && 'yes' === $attrs['show_filter'] ) {
			$filter_style = ! empty( $attrs['filter_style'] ) ? $attrs['filter_style'] : 'default';
			$facet_type   = 'taxonomy-tabs';
			if ( 'dropdown' === $filter_style ) {
				$facet_type = 'taxonomy-dropdown';
			}

			$facets[] = array(
				'type'      => $facet_type,
				'taxonomy'  => $taxonomy,
				'heading'   => ! empty( $attrs['filter_heading'] ) ? sanitize_text_field( $attrs['filter_heading'] ) : '',
				'show_all'  => ! empty( $attrs['btn_all'] ) && 'no' === $attrs['btn_all'] ? false : true,
				'all_label' => __( 'All', 'ajax-filter-posts' ),
			);
		}

		// 3. Build Pagination Config
		$pagination_type = 'none';
		if ( ! empty( $attrs['paginate'] ) && 'yes' === $attrs['paginate'] ) {
			$raw_type = ! empty( $attrs['pagination_type'] ) ? $attrs['pagination_type'] : '';
			if ( 'load_more' === $raw_type ) {
				$pagination_type = 'load_more';
			} elseif ( 'infinite_scroll' === $raw_type || ( ! empty( $attrs['infinite_scroll'] ) && 'yes' === $attrs['infinite_scroll'] ) ) {
				$pagination_type = 'infinite';
			} else {
				$pagination_type = 'numbered';
			}
		}

		$pagination_config = array(
			'type'           => $pagination_type,
			'load_more_text' => __( 'Load More', 'ajax-filter-posts' ),
			'loading_text'   => __( 'Loading...', 'ajax-filter-posts' ),
			'end_text'       => __( 'No more posts', 'ajax-filter-posts' ),
		);

		// 4. Build Responsive Columns and Gaps
		$columns = array(
			'xs' => 1,
			'sm' => 2,
			'md' => 3,
			'lg' => 3,
			'xl' => 3,
		);

		if ( ! empty( $attrs['grid_item_per_row'] ) && is_array( $attrs['grid_item_per_row'] ) ) {
			foreach ( array( 'xs', 'sm', 'md', 'lg', 'xl' ) as $bp ) {
				if ( isset( $attrs['grid_item_per_row'][ $bp ] ) ) {
					$columns[ $bp ] = max( 1, absint( $attrs['grid_item_per_row'][ $bp ] ) );
				}
			}
		}

		$col_gap = 30;
		if ( ! empty( $attrs['grid_col_gap'] ) ) {
			$col_gap = is_array( $attrs['grid_col_gap'] ) && isset( $attrs['grid_col_gap']['lg'] ) ? absint( $attrs['grid_col_gap']['lg'] ) : absint( $attrs['grid_col_gap'] );
		}

		$row_gap = 30;
		if ( ! empty( $attrs['grid_row_gap'] ) ) {
			$row_gap = is_array( $attrs['grid_row_gap'] ) && isset( $attrs['grid_row_gap']['lg'] ) ? absint( $attrs['grid_row_gap']['lg'] ) : absint( $attrs['grid_row_gap'] );
		}

		$layout_config = array(
			'columns'    => $columns,
			'col_gap'    => $col_gap ? $col_gap : 30,
			'row_gap'    => $row_gap ? $row_gap : 30,
			'card_style' => array(
				'background'    => '#ffffff',
				'border_radius' => '8px',
				'padding'       => '20px',
				'box_shadow'    => '0 1px 3px rgba(0,0,0,0.1)',
			),
		);

		// 5. Build Gutenberg Card Block Tree
		$grid_style    = ! empty( $attrs['grid_style'] ) ? $attrs['grid_style'] : 'default';
		$block_content = self::get_template_for_legacy_style( $grid_style, $attrs );

		$new_config = array(
			'query'      => $query_config,
			'facets'     => $facets,
			'pagination' => $pagination_config,
			'layout'     => $layout_config,
		);

		// Save into v2 repository
		$data = array(
			'id'               => $grid_id,
			'title'            => $grid->title,
			'config'           => $new_config,
			'block_content'    => $block_content,
			'migration_status' => 'converted',
			'schema_version'   => 2,
			'attributes'       => $attrs, // Preserve legacy serialized attributes untouched!
		);

		$saved = Grid_Repository::save( $data );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return true;
	}

	/**
	 * Rolls back a converted grid back to the legacy renderer.
	 *
	 * @param int $grid_id Grid ID.
	 * @return bool
	 */
	public static function rollback( $grid_id ) {
		return Grid_Repository::update_status( $grid_id, 'legacy' );
	}

	/**
	 * Constructs the Gutenberg card block tree for a given legacy style.
	 *
	 * @param string $style Legacy style name.
	 * @param array  $attrs Legacy attributes.
	 * @return string Gutenberg block HTML.
	 */
	public static function get_template_for_legacy_style( $style, $attrs = array() ) {
		$excerpt_len = ! empty( $attrs['excerpt_length'] ) ? absint( $attrs['excerpt_length'] ) : 20;
		$btn_text    = ! empty( $attrs['read_more_text'] ) ? sanitize_text_field( $attrs['read_more_text'] ) : __( 'Read More', 'ajax-filter-posts' );

		if ( 'style-1' === $style ) {
			return '<!-- wp:gridmaster/card {"borderRadius":"12px","padding":"0px","backgroundColor":"#ffffff","boxShadow":"0 4px 12px rgba(0,0,0,0.08)"} -->' .
				'<!-- wp:gridmaster/featured-image {"size":"medium_large","aspectRatio":"4/3","borderRadius":"12px 12px 0 0"} /-->' .
				'<!-- wp:core/group {"style":{"spacing":{"padding":{"top":"16px","bottom":"20px","left":"20px","right":"20px"}}}} -->' .
				'<!-- wp:gridmaster/post-terms {"taxonomy":"category"} /-->' .
				'<!-- wp:gridmaster/title {"tagName":"h3","fontSize":"18px","isLink":true} /-->' .
				'<!-- wp:gridmaster/post-date {"format":"M d, Y"} /-->' .
				'<!-- wp:gridmaster/excerpt {"length":' . $excerpt_len . '} /-->' .
				'<!-- wp:gridmaster/read-more {"text":"' . esc_js( $btn_text ) . '"} /-->' .
				'<!-- /wp:core/group -->' .
				'<!-- /wp:gridmaster/card -->';
		}

		if ( 'style-2' === $style || 'style-3' === $style ) {
			return '<!-- wp:gridmaster/card {"borderRadius":"8px","padding":"24px","backgroundColor":"#ffffff","boxShadow":"0 2px 8px rgba(0,0,0,0.06)"} -->' .
				'<!-- wp:gridmaster/post-terms {"taxonomy":"category"} /-->' .
				'<!-- wp:gridmaster/title {"tagName":"h3","fontSize":"22px","isLink":true} /-->' .
				'<!-- wp:gridmaster/featured-image {"size":"medium_large","aspectRatio":"16/9","borderRadius":"6px"} /-->' .
				'<!-- wp:gridmaster/post-date {"format":"F j, Y"} /-->' .
				'<!-- wp:gridmaster/excerpt {"length":' . $excerpt_len . '} /-->' .
				'<!-- wp:gridmaster/read-more {"text":"' . esc_js( $btn_text ) . '"} /-->' .
				'<!-- /wp:gridmaster/card -->';
		}

		// Default style template
		return '<!-- wp:gridmaster/card {"borderRadius":"8px","padding":"20px","backgroundColor":"#ffffff","boxShadow":"0 1px 3px rgba(0,0,0,0.1)"} -->' .
			'<!-- wp:gridmaster/featured-image {"size":"medium_large","aspectRatio":"16/9","borderRadius":"6px"} /-->' .
			'<!-- wp:gridmaster/title {"tagName":"h3","fontSize":"20px","isLink":true} /-->' .
			'<!-- wp:gridmaster/post-date {"format":"F j, Y"} /-->' .
			'<!-- wp:gridmaster/excerpt {"length":' . $excerpt_len . '} /-->' .
			'<!-- wp:gridmaster/read-more {"text":"' . esc_js( $btn_text ) . '"} /-->' .
			'<!-- /wp:gridmaster/card -->';
	}
}

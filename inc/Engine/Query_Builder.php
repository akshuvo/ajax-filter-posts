<?php
namespace GridMaster\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reusable Query Builder for GridMaster 2.0.
 */
class Query_Builder {

	/**
	 * Whitelist of allowed orderby parameters.
	 */
	const ALLOWED_ORDERBY = array(
		'date',
		'title',
		'menu_order',
		'ID',
		'modified',
		'rand',
		'comment_count',
		'meta_value',
		'meta_value_num',
	);

	/**
	 * Builds and executes a sanitized WP_Query instance.
	 *
	 * @param array $config Query configuration array.
	 * @param array $runtime_params Optional dynamic runtime parameters (page, filters, search).
	 * @return \WP_Query
	 */
	public static function execute( $config = array(), $runtime_params = array() ) {
		$args = self::build_args( $config, $runtime_params );

		$query = new \WP_Query( $args );

		// Prime caches for posts, terms, and postmeta to prevent N+1 queries.
		if ( ! empty( $query->posts ) ) {
			update_post_caches( $query->posts, $args['post_type'], true, true );
		}

		return $query;
	}

	/**
	 * Builds sanitized WP_Query arguments.
	 *
	 * @param array $config Query configuration array.
	 * @param array $runtime_params Runtime parameters.
	 * @return array
	 */
	public static function build_args( $config = array(), $runtime_params = array() ) {
		$defaults = array(
			'post_type'      => 'post',
			'posts_per_page' => 9,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array(),
			'author'         => '',
			'search'         => '',
			'hide_empty'     => true,
		);
		$config   = wp_parse_args( $config, $defaults );

		$paged = 1;
		if ( ! empty( $runtime_params['paged'] ) ) {
			$paged = max( 1, absint( $runtime_params['paged'] ) );
		}

		$posts_per_page = absint( $config['posts_per_page'] );
		if ( $posts_per_page < 1 || $posts_per_page > 100 ) {
			$posts_per_page = 9;
		}

		// Sanitize post type.
		$post_type = $config['post_type'];
		if ( is_array( $post_type ) ) {
			$post_type = array_map( 'sanitize_key', $post_type );
		} else {
			$post_type = sanitize_key( $post_type ? $post_type : 'post' );
		}

		// Sanitize orderby and order.
		$orderby = in_array( $config['orderby'], self::ALLOWED_ORDERBY, true ) ? $config['orderby'] : 'date';
		$order   = 'ASC' === strtoupper( $config['order'] ) ? 'ASC' : 'DESC';

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $posts_per_page,
			'paged'               => $paged,
			'orderby'             => $orderby,
			'order'               => $order,
			'ignore_sticky_posts' => true,
		);

		// Search keyword.
		$search_query = ! empty( $runtime_params['search'] ) ? $runtime_params['search'] : $config['search'];
		if ( ! empty( $search_query ) ) {
			$query_args['s'] = sanitize_text_field( $search_query );
		}

		// Author filter.
		if ( ! empty( $config['author'] ) ) {
			$query_args['author'] = absint( $config['author'] );
		}

		// Build Tax Query.
		$tax_queries = array();

		// Static tax query from config.
		if ( ! empty( $config['tax_query'] ) && is_array( $config['tax_query'] ) ) {
			foreach ( $config['tax_query'] as $tq ) {
				if ( ! empty( $tq['taxonomy'] ) && ! empty( $tq['terms'] ) ) {
					$terms = is_array( $tq['terms'] ) ? array_map( 'absint', $tq['terms'] ) : array( absint( $tq['terms'] ) );
					$tax_queries[] = array(
						'taxonomy' => sanitize_key( $tq['taxonomy'] ),
						'field'    => 'term_id',
						'terms'    => $terms,
						'operator' => ! empty( $tq['operator'] ) && in_array( $tq['operator'], array( 'IN', 'NOT IN', 'AND' ), true ) ? $tq['operator'] : 'IN',
					);
				}
			}
		}

		// Dynamic filter terms from runtime params.
		if ( ! empty( $runtime_params['facets'] ) && is_array( $runtime_params['facets'] ) ) {
			foreach ( $runtime_params['facets'] as $taxonomy => $terms ) {
				$taxonomy = sanitize_key( $taxonomy );
				if ( ! empty( $terms ) ) {
					$term_ids = is_array( $terms ) ? array_map( 'absint', $terms ) : array( absint( $terms ) );
					// Filter out zeros.
					$term_ids = array_filter( $term_ids );
					if ( ! empty( $term_ids ) ) {
						$tax_queries[] = array(
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => $term_ids,
							'operator' => 'IN',
						);
					}
				}
			}
		}

		if ( ! empty( $tax_queries ) ) {
			if ( count( $tax_queries ) > 1 ) {
				$tax_queries['relation'] = 'AND';
			}
			$query_args['tax_query'] = $tax_queries;
		}

		/**
		 * Filter query arguments before execution.
		 * Allows Pro and third-party add-ons to safely extend query parameters (meta_query, custom order, etc.).
		 *
		 * @param array $query_args WP_Query arguments.
		 * @param array $config Grid query configuration.
		 * @param array $runtime_params Runtime parameters.
		 */
		return apply_filters( 'gridmaster_query_args', $query_args, $config, $runtime_params );
	}
}

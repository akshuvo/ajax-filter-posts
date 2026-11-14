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
		'post__in',
		'modified',
		'rand',
		'comment_count',
		'meta_value',
		'meta_value_num',
		'price',
		'price-desc',
		'popularity',
		'rating',
		'sku',
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

		$offset = ! empty( $config['offset'] ) ? absint( $config['offset'] ) : 0;
		if ( $offset > 0 && $query->found_posts > 0 ) {
			$adjusted_found       = max( 0, (int) $query->found_posts - $offset );
			$per_page             = max( 1, (int) $args['posts_per_page'] );
			$query->found_posts   = $adjusted_found;
			$query->max_num_pages = (int) ceil( $adjusted_found / $per_page );
		}

		// Prime caches for posts, terms, and postmeta to prevent N+1 queries.
		if ( ! empty( $query->posts ) ) {
			update_post_caches( $query->posts, $args['post_type'], true, true );
		}

		return $query;
	}

	/**
	 * Parses a comma-separated string or array into an array of unique positive integer post IDs.
	 *
	 * @param array|string $raw Input IDs.
	 * @return int[]
	 */
	private static function parse_id_list( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
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
			'post_type'           => 'post',
			'posts_per_page'      => 9,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'tax_query'           => array(),
			'author'              => '',
			'search'              => '',
			'hide_empty'          => true,
			'offset'              => 0,
			'ignore_sticky_posts' => true,
			'exclude_current'     => false,
			'post__in'            => '',
			'post__not_in'        => '',
			'context_mode'        => 'default',
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

		// Sanitize post type (supports single string, comma-separated string, or array).
		$raw_post_types = $config['post_type'];
		if ( is_string( $raw_post_types ) && false !== strpos( $raw_post_types, ',' ) ) {
			$raw_post_types = explode( ',', $raw_post_types );
		}
		if ( is_array( $raw_post_types ) ) {
			$sanitized_pts = array_values( array_unique( array_filter( array_map( 'sanitize_key', $raw_post_types ) ) ) );
			if ( empty( $sanitized_pts ) ) {
				$post_type = 'post';
			} elseif ( 1 === count( $sanitized_pts ) ) {
				$post_type = $sanitized_pts[0];
			} else {
				$post_type = $sanitized_pts;
			}
		} else {
			$post_type = sanitize_key( $raw_post_types ? $raw_post_types : 'post' );
		}

		// Sanitize orderby and order (runtime_params['orderby'] overrides config['orderby']).
		$raw_orderby = ! empty( $runtime_params['orderby'] ) ? $runtime_params['orderby'] : $config['orderby'];
		$orderby     = in_array( $raw_orderby, self::ALLOWED_ORDERBY, true ) ? $raw_orderby : 'date';
		$order       = 'ASC' === strtoupper( $config['order'] ) ? 'ASC' : 'DESC';

		if ( ! empty( $runtime_params['orderby'] ) ) {
			if ( 'title' === $orderby ) {
				$order = 'ASC';
			} elseif ( 'date' === $orderby ) {
				$order = 'DESC';
			}
		}

		$ignore_sticky = isset( $config['ignore_sticky_posts'] ) ? (bool) $config['ignore_sticky_posts'] : true;
		$query_args    = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $posts_per_page,
			'paged'               => $paged,
			'orderby'             => $orderby,
			'order'               => $order,
			'ignore_sticky_posts' => $ignore_sticky,
		);

		$offset = ! empty( $config['offset'] ) ? absint( $config['offset'] ) : 0;
		if ( $offset > 0 ) {
			$query_args['offset'] = $offset + ( ( $paged - 1 ) * $posts_per_page );
		}

		// Map WooCommerce sorting options to WP_Query meta_key / orderby / order.
		switch ( $orderby ) {
			case 'price':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'ASC';
				break;
			case 'price-desc':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_price';
				$query_args['order']    = 'DESC';
				break;
			case 'popularity':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = 'total_sales';
				$query_args['order']    = 'DESC';
				break;
			case 'rating':
				$query_args['orderby']  = 'meta_value_num';
				$query_args['meta_key'] = '_wc_average_rating';
				$query_args['order']    = 'DESC';
				break;
			case 'sku':
				$query_args['orderby']  = 'meta_value';
				$query_args['meta_key'] = '_sku';
				$query_args['order']    = 'ASC';
				break;
		}

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

		// Context resolution (for archive & related posts & exclude_current).
		$context = ! empty( $runtime_params['context'] ) && is_array( $runtime_params['context'] ) ? $runtime_params['context'] : array();
		if ( empty( $context['post_id'] ) && function_exists( 'is_singular' ) && is_singular() ) {
			$context['post_id']   = absint( get_queried_object_id() );
			$context['post_type'] = $context['post_id'] ? get_post_type( $context['post_id'] ) : '';
		}
		if ( empty( $context['term_id'] ) && function_exists( 'is_category' ) && ( is_category() || is_tag() || is_tax() ) ) {
			$q_obj = get_queried_object();
			if ( $q_obj instanceof \WP_Term ) {
				$context['term_id']  = (int) $q_obj->term_id;
				$context['taxonomy'] = $q_obj->taxonomy;
			}
		}
		if ( empty( $context['author_id'] ) && function_exists( 'is_author' ) && is_author() ) {
			$context['author_id'] = absint( get_queried_object_id() );
		}
		if ( empty( $context['search_query'] ) && function_exists( 'is_search' ) && is_search() ) {
			$context['search_query'] = get_search_query( false );
		}

		$context_mode = ! empty( $config['context_mode'] ) ? sanitize_key( $config['context_mode'] ) : 'default';
		$exclude_ids  = self::parse_id_list( $config['post__not_in'] );
		$include_ids  = self::parse_id_list( $config['post__in'] );

		if ( ! empty( $config['exclude_current'] ) && ! empty( $context['post_id'] ) ) {
			$exclude_ids[] = absint( $context['post_id'] );
		}

		if ( 'current_archive' === $context_mode ) {
			if ( ! empty( $context['term_id'] ) && ! empty( $context['taxonomy'] ) ) {
				$tax_queries[] = array(
					'taxonomy' => sanitize_key( $context['taxonomy'] ),
					'field'    => 'term_id',
					'terms'    => array( absint( $context['term_id'] ) ),
					'operator' => 'IN',
				);
			} elseif ( ! empty( $context['author_id'] ) && empty( $config['author'] ) ) {
				$query_args['author'] = absint( $context['author_id'] );
			} elseif ( ! empty( $context['search_query'] ) && empty( $query_args['s'] ) ) {
				$query_args['s'] = sanitize_text_field( $context['search_query'] );
			}
		} elseif ( 'related_posts' === $context_mode && ! empty( $context['post_id'] ) ) {
			$ref_post_id   = absint( $context['post_id'] );
			$exclude_ids[] = $ref_post_id;
			$ref_type      = ! empty( $context['post_type'] ) ? sanitize_key( $context['post_type'] ) : get_post_type( $ref_post_id );
			$taxonomies    = $ref_type ? get_object_taxonomies( $ref_type, 'names' ) : array( 'category', 'post_tag' );
			$related_tax   = array();

			foreach ( (array) $taxonomies as $rel_tax ) {
				if ( in_array( $rel_tax, array( 'post_format', 'product_shipping_class', 'product_visibility' ), true ) ) {
					continue;
				}
				$rel_terms = wp_get_post_terms( $ref_post_id, $rel_tax, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $rel_terms ) && ! empty( $rel_terms ) ) {
					$related_tax[] = array(
						'taxonomy' => $rel_tax,
						'field'    => 'term_id',
						'terms'    => array_map( 'absint', $rel_terms ),
						'operator' => 'IN',
					);
				}
			}

			if ( count( $related_tax ) > 1 ) {
				$related_tax['relation'] = 'OR';
				$tax_queries[]           = $related_tax;
			} elseif ( 1 === count( $related_tax ) ) {
				$tax_queries[] = $related_tax[0];
			}
		}

		$exclude_ids = array_values( array_unique( array_filter( $exclude_ids ) ) );
		if ( ! empty( $include_ids ) ) {
			if ( ! empty( $exclude_ids ) ) {
				$include_ids = array_values( array_diff( $include_ids, $exclude_ids ) );
			}
			$query_args['post__in'] = ! empty( $include_ids ) ? $include_ids : array( 0 );
		} else {
			if ( 'post__in' === $query_args['orderby'] ) {
				$query_args['orderby'] = 'date';
			}
			if ( ! empty( $exclude_ids ) ) {
				$query_args['post__not_in'] = $exclude_ids;
			}
		}

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

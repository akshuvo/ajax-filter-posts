<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query_Builder {

	public static function build( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'paged'          => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
				'posts_per_page' => 9,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array(),
				'tax_input'      => array(),
				'terms'          => '',
				'taxonomy'       => 'category',
				'has_tax_query'  => false,
			)
		);

		$query_args = array(
			'post_type'   => sanitize_key( $args['post_type'] ),
			'post_status' => 'publish',
			'paged'       => max( 1, absint( $args['paged'] ) ),
		);

		if ( ! empty( $args['posts_per_page'] ) ) {
			$query_args['posts_per_page'] = intval( $args['posts_per_page'] );
		}

		if ( ! empty( $args['orderby'] ) ) {
			$query_args['orderby'] = sanitize_text_field( $args['orderby'] );
		}

		if ( ! empty( $args['order'] ) ) {
			$query_args['order'] = sanitize_text_field( $args['order'] );
		}

		$tax_query = self::build_tax_query( $args );
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		return apply_filters( 'gridmaster_render_grid_query_args', $query_args, $args );
	}

	private static function build_tax_query( $args ) {
		if ( ! empty( $args['tax_input'] ) && is_array( $args['tax_input'] ) ) {
			$tax_query = array();

			foreach ( $args['tax_input'] as $taxonomy => $terms ) {
				$terms = is_array( $terms ) ? array_filter( array_map( 'absint', $terms ) ) : array( absint( $terms ) );

				if ( empty( $terms ) || in_array( -1, $terms, true ) ) {
					continue;
				}

				$tax_query[] = array(
					'taxonomy' => sanitize_key( $taxonomy ),
					'field'    => 'term_id',
					'terms'    => $terms,
				);
			}

			return $tax_query;
		}

		if ( ! empty( $args['has_tax_query'] ) && ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
			return $args['tax_query'];
		}

		$terms = array();
		if ( ! empty( $args['terms'] ) ) {
			$terms = is_array( $args['terms'] ) ? $args['terms'] : explode( ',', $args['terms'] );
		} elseif ( ! empty( $args['cat'] ) ) {
			$terms = is_array( $args['cat'] ) ? $args['cat'] : explode( ',', $args['cat'] );
		}

		$terms = array_filter( array_map( 'absint', $terms ) );
		if ( empty( $terms ) ) {
			return array();
		}

		return array(
			array(
				'taxonomy' => sanitize_key( $args['taxonomy'] ),
				'field'    => 'term_id',
				'terms'    => $terms,
			),
		);
	}
}

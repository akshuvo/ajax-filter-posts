<?php
/**
 * Get Grid by ID
 *
 * @param  integer $grid_id Grid id.
 * @param boolean $defaults Show defaults data.
 * @return object
 */
function gm_get_grid( $grid_id, $defaults = false ) {
	// Grid Handler Class.
	if ( ! class_exists( 'GridMaster\Grids' ) ) {
		require_once GRIDMASTER_PATH . '/admin/Grids.php';
	}

	// Grid args
	return GridMaster\Grids::get( $grid_id, $defaults );
}

/**
 * Responsive Breakpoints
 *
 * @return array
 */
function gm_get_breakpoints() {
	// All values are in pixels and are used for media queries up to the next breakpoint.
	return array(
		'xs' => array(
			'label'   => __( 'Extra Small', 'ajax-filter-posts'  ),
			'value'   => '320',
			'default' => false,
			'icon'    => 'dashicons dashicons-smartphone',
		),
		'sm' => array(
			'label'   => __( 'Small', 'ajax-filter-posts'  ),
			'value'   => '768',
			'default' => false,
			'icon'    => 'dashicons dashicons-smartphone',
		),
		'md' => array(
			'label'   => __( 'Medium', 'ajax-filter-posts'  ),
			'value'   => '992',
			'default' => false,
			'icon'    => 'dashicons dashicons-tablet',
		),
		'lg' => array(
			'label'   => __( 'Large', 'ajax-filter-posts'  ),
			'value'   => '1200',
			'default' => true,
			'icon'    => 'dashicons dashicons-laptop',
		),
		'xl' => array(
			'label'   => __( 'Extra Large', 'ajax-filter-posts'  ),
			'value'   => '1600',
			'default' => false,
			'icon'    => 'dashicons dashicons-desktop',
		),
	);
}

/**
 * Post Title
 *
 * @param  string  $tag Title html tag.
 * @param  boolean $link Link the title.
 */
function gridmaster_get_post_title( $tag = 'h2', $link = true ) {
	$tag   = apply_filters( 'gridmaster_post_title_tag', $tag );
	$title = esc_html( get_the_title() );
	$title = $link ? '<a href="' . esc_url( get_the_permalink() ) . '">' . $title . '</a>' : $title;
	return '<' . $tag . ' class="am__title">' . $title . '</' . $tag . '>';
}

// Post content
function gridmaster_the_content() {

	// Shortcode atts
	$args           = apply_filters( 'gridmaster_get_render_grid_args', array() );
	$content_from   = isset( $args['content_from'] ) ? $args['content_from'] : 'excerpt';
	$excerpt_type   = isset( $args['excerpt_type'] ) ? $args['excerpt_type'] : 'words'; // words or characters
	$excerpt_length = apply_filters( 'gridmaster_excerpt_length', 15 );

	// Output
	$output = '<div class="am__excerpt">';

	if ( $content_from == 'content' ) {
		$content = get_the_content();
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );
	} else {
		$content = get_the_excerpt();
	}

	// Apply excerpt length
	if ( $excerpt_length != '-1' ) {
		if ( $excerpt_type == 'characters' ) {
			$content = substr( $content, 0, $excerpt_length );
		} else {
			$content = wp_trim_words( $content, $excerpt_length, '' );
		}
	}

	// Remove All HTML tags
	$content = wp_strip_all_tags( $content );

	// Set content
	$output .= $content;

	$output .= '</div>';

	return $output;
}

// Get gridmaster_settings
function gridmaster_get_settings( $key = '' ) {
	$settings = get_option( 'gridmaster_settings', array() );
	$settings = ! empty( $settings ) && is_array( $settings ) ? $settings : array();
	if ( ! empty( $key ) ) {
		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}
	return $settings;
}

// Read More Link
function gridmaster_read_more_link( $link_text = '' ) {

	// Shortcode atts
	$args           = apply_filters( 'gridmaster_get_render_grid_args', array() );
	$show_read_more = isset( $args['show_read_more'] ) ? $args['show_read_more'] : 'yes';
	$read_more_text = isset( $args['read_more_text'] ) ? $args['read_more_text'] : '';

	if ( $show_read_more == 'no' ) {
		return;
	}

	if ( ! empty( $read_more_text ) ) {
		$link_text = $read_more_text;
	} elseif ( empty( $link_text ) ) {
		$link_text = __( 'Read More', 'ajax-filter-posts'  );
	}

	$output  = '<div class="gm_read_more">';
	$output .= '<a href="' . get_the_permalink() . '" class="am__readmore am__read_more_link">' . $link_text . '</a>';
	$output .= '</div>';

	return $output;
}

// Grid Styles
function gridmaster_grid_styles() {
	return apply_filters(
		'gridmaster_grid_styles',
		array(
			'default' => __( 'Style 1 (Default)', 'ajax-filter-posts' ),
			'style-2' => __( 'Style 2 (New)', 'ajax-filter-posts' ),
			'style-3' => __( 'Style 3 (New)', 'ajax-filter-posts' ),
		)
	);
}

// Grid Pro Styles
add_filter( 'gridmaster_grid_styles', 'gridmaster_grid_pro_styles', 9 );
function gridmaster_grid_pro_styles( $styles ) {

	$pro_text   = gridmaster_is_pro() ? '' : __( ' (Available in Pro)', 'ajax-filter-posts'  );
	$pro_styles = apply_filters(
		'gridmaster_grid_pro_styles',
		array(
			'pro-style-1'  => __( 'Pro Style 1', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-2'  => __( 'Pro Style 2', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-3'  => __( 'Pro Style 3', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-4'  => __( 'Pro Style 4', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-5'  => __( 'Pro Style 5', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-6'  => __( 'Pro Style 6', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-7'  => __( 'Pro Style 7', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-8'  => __( 'Pro Style 8', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-9'  => __( 'Pro Style 9', 'ajax-filter-posts'  ) . $pro_text,
			'pro-style-10' => __( 'Pro Style 10', 'ajax-filter-posts'  ) . $pro_text,
		)
	);
	return array_merge( $styles, $pro_styles );
}
// Filter Pro  Styles
add_filter( 'gridmaster_filter_styles', 'gridmaster_filter_pro_styles', 9 );
function gridmaster_filter_pro_styles( $styles ) {
	$pro_text   = gridmaster_is_pro() ? '' : __( ' (Available in Pro)', 'ajax-filter-posts'  );
	$pro_styles = apply_filters(
		'gridmaster_grid_pro_styles',
		array(
			'pro-filter-1' => __( 'Pro Filter 1', 'ajax-filter-posts'  ) . $pro_text,
			'pro-filter-2' => __( 'Pro Filter 2', 'ajax-filter-posts'  ) . $pro_text,
		)
	);
	return array_merge( $styles, $pro_styles );
}

// Is Pro
function gridmaster_is_pro() {
	return defined( 'GRIDMASTER_PRO_VERSION' );
}

// Get Pro URL
function gridmaster_website_url( $path = '' ) {
	return esc_url( 'https://addonmaster.com/' . $path );
}

// Get the date
function gridmaster_get_the_date( $format = '' ) {
	$format = ! empty( $format ) ? $format : get_option( 'date_format' );
	$date   = get_the_date( $format );
	$title  = sprintf(
		/* translators: %s: post date */
		esc_html_x( 'Posted on %s', 'post date', 'ajax-filter-posts'  ),
		esc_html( $date )
	);
	return '<span title="' . $title . '" class="gm-post-date">' . $date . '</span>';
}

// Get the terms
function gridmaster_get_the_terms( $post_id = 0, $taxonomy = '', $separator = ', ', $link = true ) {
	// Shortcode atts
	$args = apply_filters( 'gridmaster_get_render_grid_args', array() );

	// Set taxonomy from shortcode atts
	if ( empty( $taxonomy ) ) {
		$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : 'category';
	}

	// Post ID
	$post_id = ! empty( $post_id ) ? $post_id : get_the_ID();

	// Get terms
	$terms = get_the_terms( $post_id, $taxonomy );

	$output = '';
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$term_link = get_term_link( $term );
			if ( $link ) {
				$output .= '<a class="gm-term" href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>' . $separator;
			} else {
				$output .= '<span class="gm-term">' . esc_html( $term->name ) . '</span>' . $separator;
			}
		}
	}
	$output = trim( $output, $separator );

	return $output ? '<span class="gm-taxonomy">' . $output . '</span>' : '';
}

// Posted by
function gridmaster_posted_by() {

	// Author Name
	$author = get_the_author();

	$title = sprintf(
		/* translators: %s: post author */
		esc_html_x( 'Posted by %s', 'post author', 'ajax-filter-posts'  ),
		esc_html( $author )
	);

	return '<span title="' . $title . '" class="gm-byline"><span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( $author ) . '</a></span></span>';
}

// Comments number
function gridmaster_comments_number( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'after'         => '',
			'before'        => '',
			'comments_link' => false,
		)
	);
	if ( ! is_single() && ! post_password_required() ) {

		// Comment Number
		$comment_number = get_comments_number();

		// Number text
		$comment_text = sprintf(
			/* translators: 1: comment number, 2: title. */
			esc_html( _nx( '%1$s Comment', '%1$s Comments', $comment_number, 'comments title', 'ajax-filter-posts'  ) ),
			number_format_i18n( $comment_number )
		);

		$comment_html = '<span class="gm-comments-link">';

		// Comments link
		if ( $args['comments_link'] ) {
			$comment_html .= '<a href="' . esc_url( get_comments_link() ) . '" title="' . esc_attr( $comment_text ) . '">';
		}

		// Before
		$comment_html .= $args['before'];

		$comment_html .= $comment_number;

		// After
		$comment_html .= $args['after'] . '</span>';

		if ( $args['comments_link'] ) {
			$comment_html .= '</a>';
		}

		return $comment_html;
	}
}

// Post Thumbnail
function gridmaster_post_thumbnail( $size = 'post-thumbnail', $attr = '' ) {
	$output = apply_filters( 'gridmaster_post_thumbnail_html', get_the_post_thumbnail( null, $size, $attr ) );
	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Get Filter All Text
function gm_taxonomy_item_all( $args = array() ) {
	// Taxonomy Args
	$tax_args = $args['tax_args'];

	$taxonomy     = $tax_args['taxonomy'];
	$grid_id      = $args['grid_id'];
	$input_type   = $args['input_type'];
	$filter_style = $args['filter_style'];

	$input_name = 'tax_input[' . $taxonomy . '][]';
	$input_id   = $grid_id . '-' . $taxonomy . '_all';

	if ( $args['btn_all'] != 'no' ) : ?>
		<div class="gm-taxonomy-item gm-taxonomy-all">
			<input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $input_name ); ?>" id="<?php echo esc_attr( $input_id ); ?>" value="-1" />
			<label class="asr_texonomy" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html__( 'All', 'ajax-filter-posts'  ); ?></label>
		</div>
		<?php
	endif;
}
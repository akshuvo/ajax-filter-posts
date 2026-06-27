<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Template_Renderer {

	public static function is_template( $template_id ) {
		$template = get_post( absint( $template_id ) );
		if ( ! $template || ! in_array( $template->post_type, array( 'gm_grid_template', 'gm_grid_style' ), true ) ) {
			return false;
		}

		return 'publish' === $template->post_status || current_user_can( 'edit_post', $template->ID );
	}

	public static function render_post( $template_id, $args = array() ) {
		$template = get_post( absint( $template_id ) );

		if ( ! self::is_template( $template_id ) ) {
			return self::fallback_card();
		}

		$content = trim( $template->post_content );
		if ( '' === $content ) {
			return self::fallback_card();
		}

		$output = '';
		foreach ( parse_blocks( $content ) as $block ) {
			$output .= render_block( $block );
		}

		return apply_filters( 'gridmaster_template_post_output', $output, $template, $args );
	}

	public static function fallback_card() {
		ob_start();
		?>
		<div class="gm-block-card">
			<div class="gm-block-card__image"><?php gridmaster_post_thumbnail( apply_filters( 'gridmaster_post_thumb_size', 'full' ) ); ?></div>
			<div class="gm-block-card__content">
				<?php echo gridmaster_get_post_title( 'h3' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo gridmaster_the_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo gridmaster_read_more_link( __( 'Read More', 'ajax-filter-posts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function field( $field, $attributes = array() ) {
		switch ( $field ) {
			case 'featured-image':
				ob_start();
				gridmaster_post_thumbnail( isset( $attributes['size'] ) ? sanitize_key( $attributes['size'] ) : apply_filters( 'gridmaster_post_thumb_size', 'full' ) );
				return '<div class="gm-field gm-field-featured-image">' . ob_get_clean() . '</div>';

			case 'title':
				$tag  = ! empty( $attributes['tagName'] ) ? sanitize_key( $attributes['tagName'] ) : 'h3';
				$link = ! isset( $attributes['isLink'] ) || (bool) $attributes['isLink'];
				return '<div class="gm-field gm-field-title">' . gridmaster_get_post_title( $tag, $link ) . '</div>';

			case 'excerpt':
				return '<div class="gm-field gm-field-excerpt">' . gridmaster_the_content() . '</div>';

			case 'terms':
				$taxonomy = ! empty( $attributes['taxonomy'] ) ? sanitize_key( $attributes['taxonomy'] ) : '';
				return '<div class="gm-field gm-field-terms">' . gridmaster_get_the_terms( 0, $taxonomy ) . '</div>';

			case 'date':
				$format = ! empty( $attributes['format'] ) ? sanitize_text_field( $attributes['format'] ) : '';
				return '<div class="gm-field gm-field-date">' . gridmaster_get_the_date( $format ) . '</div>';

			case 'author':
				return '<div class="gm-field gm-field-author">' . gridmaster_posted_by() . '</div>';

			case 'comments':
				return '<div class="gm-field gm-field-comments">' . gridmaster_comments_number() . '</div>';

			case 'read-more':
				$text = ! empty( $attributes['text'] ) ? sanitize_text_field( $attributes['text'] ) : '';
				return '<div class="gm-field gm-field-read-more">' . gridmaster_read_more_link( $text ) . '</div>';
		}

		return '';
	}
}

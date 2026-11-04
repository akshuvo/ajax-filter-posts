<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers GridMaster Gutenberg blocks and server-side render callbacks.
 */
class Blocks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * Registers all GridMaster blocks.
	 */
	public function register_blocks() {
		$this->register_editor_script();

		// Register Grid embed block.
		register_block_type(
			'gridmaster/grid',
			array(
				'api_version'     => 3,
				'editor_script'   => 'gridmaster-blocks-editor',
				'editor_style'    => 'gridmaster-blocks-editor',
				'render_callback' => array( $this, 'render_grid_block' ),
				'attributes'      => array(
					'gridId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);

		// Register Card container block.
		register_block_type(
			'gridmaster/card',
			array(
				'api_version'     => 3,
				'editor_script'   => 'gridmaster-blocks-editor',
				'editor_style'    => 'gridmaster-blocks-editor',
				'render_callback' => array( $this, 'render_card_block' ),
				'attributes'      => array(
					'backgroundColor' => array( 'type' => 'string', 'default' => '#ffffff' ),
					'padding'         => array( 'type' => 'string', 'default' => '20px' ),
					'borderRadius'    => array( 'type' => 'string', 'default' => '8px' ),
					'boxShadow'       => array( 'type' => 'string', 'default' => 'none' ),
					'borderWidth'     => array( 'type' => 'string', 'default' => '0px' ),
					'borderColor'     => array( 'type' => 'string', 'default' => 'transparent' ),
				),
			)
		);

		// Dynamic field blocks.
		$field_blocks = array(
			'featured-image' => array(
				'callback'   => 'render_featured_image',
				'attributes' => array(
					'size'         => array( 'type' => 'string', 'default' => 'medium_large' ),
					'borderRadius' => array( 'type' => 'string', 'default' => '0px' ),
					'isLink'       => array( 'type' => 'boolean', 'default' => true ),
				),
			),
			'title'          => array(
				'callback'   => 'render_title',
				'attributes' => array(
					'tagName'  => array( 'type' => 'string', 'default' => 'h3' ),
					'isLink'   => array( 'type' => 'boolean', 'default' => true ),
					'fontSize' => array( 'type' => 'string', 'default' => '' ),
					'color'    => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'excerpt'        => array(
				'callback'   => 'render_excerpt',
				'attributes' => array(
					'length' => array( 'type' => 'number', 'default' => 20 ),
				),
			),
			'read-more'      => array(
				'callback'   => 'render_read_more',
				'attributes' => array(
					'text' => array( 'type' => 'string', 'default' => 'Read More' ),
				),
			),
			'post-date'      => array(
				'callback'   => 'render_post_date',
				'attributes' => array(
					'format' => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'post-author'    => array(
				'callback'   => 'render_post_author',
				'attributes' => array(),
			),
			'post-terms'     => array(
				'callback'   => 'render_post_terms',
				'attributes' => array(
					'taxonomy' => array( 'type' => 'string', 'default' => 'category' ),
				),
			),
			'post-meta'      => array(
				'callback'   => 'render_post_meta',
				'attributes' => array(
					'metaKey' => array( 'type' => 'string', 'default' => '' ),
					'prefix'  => array( 'type' => 'string', 'default' => '' ),
					'suffix'  => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'comments-count' => array(
				'callback'   => 'render_comments_count',
				'attributes' => array(),
			),
		);

		foreach ( $field_blocks as $name => $block_def ) {
			register_block_type(
				'gridmaster/' . $name,
				array(
					'api_version'     => 3,
					'editor_script'   => 'gridmaster-blocks-editor',
					'editor_style'    => 'gridmaster-blocks-editor',
					'uses_context'    => array( 'postId', 'postType', 'gridmaster/gridId' ),
					'attributes'      => $block_def['attributes'],
					'render_callback' => array( $this, $block_def['callback'] ),
				)
			);
		}

		/**
		 * Allow Pro and add-ons to register additional Gutenberg blocks.
		 */
		do_action( 'gridmaster_register_blocks' );
	}

	/**
	 * Registers the editor assets.
	 */
	public function register_editor_script() {
		wp_register_script(
			'gridmaster-blocks-editor',
			GRIDMASTER_ASSETS . 'blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n' ),
			GRIDMASTER_VERSION,
			true
		);

		wp_register_style(
			'gridmaster-blocks-editor',
			GRIDMASTER_ASSETS . 'blocks/editor.css',
			array( 'wp-edit-blocks' ),
			GRIDMASTER_VERSION
		);
	}

	/**
	 * Enqueues block editor assets.
	 */
	public function editor_assets() {
		// Fetch saved grids for the grid selector block.
		$grids_result = \GridMaster\Storage\Grid_Repository::list_grids( array( 'per_page' => 100 ) );
		$grid_options = array(
			array( 'label' => __( 'Select a Grid', 'ajax-filter-posts' ), 'value' => 0 ),
		);
		if ( ! empty( $grids_result['items'] ) ) {
			foreach ( $grids_result['items'] as $grid ) {
				$grid_options[] = array(
					'label' => sprintf( '#%d — %s', $grid->id, $grid->title ),
					'value' => $grid->id,
				);
			}
		}

		wp_localize_script(
			'gridmaster-blocks-editor',
			'gridmasterBlockData',
			array(
				'grids'   => $grid_options,
				'siteUrl' => get_site_url(),
			)
		);
	}

	/**
	 * Helper: Extracts contextual post ID.
	 *
	 * @param array       $attributes Block attributes.
	 * @param \WP_Block   $block Block instance.
	 * @return int
	 */
	private function get_context_post_id( $attributes, $block ) {
		if ( ! empty( $block->context['postId'] ) ) {
			return absint( $block->context['postId'] );
		}
		return get_the_ID();
	}

	/**
	 * Render Grid block on frontend.
	 */
	public function render_grid_block( $attributes ) {
		$grid_id = ! empty( $attributes['gridId'] ) ? absint( $attributes['gridId'] ) : 0;
		if ( ! $grid_id ) {
			return '<div class="gm-empty-grid-notice">' . esc_html__( 'Please select a GridMaster grid.', 'ajax-filter-posts' ) . '</div>';
		}

		if ( ! class_exists( 'GridMaster\Engine\Grid_Renderer' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';
		}

		return \GridMaster\Engine\Grid_Renderer::render_grid( $grid_id );
	}

	/**
	 * Render Card container block.
	 */
	public function render_card_block( $attributes, $content ) {
		$bg     = ! empty( $attributes['backgroundColor'] ) ? sanitize_hex_color( $attributes['backgroundColor'] ) : '#ffffff';
		$pad    = ! empty( $attributes['padding'] ) ? sanitize_text_field( $attributes['padding'] ) : '20px';
		$radius = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '8px';
		$shadow = ! empty( $attributes['boxShadow'] ) ? sanitize_text_field( $attributes['boxShadow'] ) : 'none';
		$b_width = ! empty( $attributes['borderWidth'] ) ? sanitize_text_field( $attributes['borderWidth'] ) : '';
		$b_color = ! empty( $attributes['borderColor'] ) ? sanitize_text_field( $attributes['borderColor'] ) : '#dcdcde';

		$style = sprintf(
			'background-color:%s;padding:%s;border-radius:%s;box-shadow:%s;',
			esc_attr( $bg ),
			esc_attr( $pad ),
			esc_attr( $radius ),
			esc_attr( $shadow )
		);

		if ( ! empty( $b_width ) && '0px' !== $b_width && '0' !== $b_width && 'none' !== $b_width ) {
			$style .= sprintf( 'border:%s solid %s;', esc_attr( $b_width ), esc_attr( $b_color ) );
		}

		return sprintf(
			'<div class="gm-card" style="%s">%s</div>',
			$style,
			$content
		);
	}

	/**
	 * Render Featured Image block.
	 */
	public function render_featured_image( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
			return '';
		}

		$size   = ! empty( $attributes['size'] ) ? sanitize_key( $attributes['size'] ) : 'medium_large';
		$is_link = ! isset( $attributes['isLink'] ) || (bool) $attributes['isLink'];
		$radius = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '0px';

		$image_html = get_the_post_thumbnail( $post_id, $size, array(
			'style' => 'width:100%;height:auto;object-fit:cover;border-radius:' . esc_attr( $radius ) . ';',
			'class' => 'gm-card-img',
		) );

		if ( $is_link ) {
			$image_html = sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( get_permalink( $post_id ) ), esc_attr( get_the_title( $post_id ) ), $image_html );
		}

		return '<div class="gm-field gm-field-featured-image">' . $image_html . '</div>';
	}

	/**
	 * Render Title block.
	 */
	public function render_title( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$tag     = ! empty( $attributes['tagName'] ) && in_array( $attributes['tagName'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ? $attributes['tagName'] : 'h3';
		$is_link = ! isset( $attributes['isLink'] ) || (bool) $attributes['isLink'];
		$title   = get_the_title( $post_id );

		$style_parts = array();
		if ( ! empty( $attributes['fontSize'] ) ) {
			$style_parts[] = 'font-size:' . esc_attr( $attributes['fontSize'] );
		}
		if ( ! empty( $attributes['color'] ) ) {
			$style_parts[] = 'color:' . esc_attr( $attributes['color'] );
		}
		$style_attr = ! empty( $style_parts ) ? ' style="' . implode( ';', $style_parts ) . '"' : '';

		if ( $is_link ) {
			$inner = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $post_id ) ), esc_html( $title ) );
		} else {
			$inner = esc_html( $title );
		}

		return sprintf( '<%1$s class="gm-field gm-field-title"%2$s>%3$s</%1$s>', $tag, $style_attr, $inner );
	}

	/**
	 * Render Excerpt block.
	 */
	public function render_excerpt( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		if ( post_password_required( $post_id ) ) {
			return '<div class="gm-field gm-field-excerpt"><p>' . esc_html__( 'There is no excerpt because this is a protected post.', 'ajax-filter-posts' ) . '</p></div>';
		}

		$length = ! empty( $attributes['length'] ) ? absint( $attributes['length'] ) : 20;
		$post   = get_post( $post_id );

		if ( ! empty( $post->post_excerpt ) ) {
			$text = $post->post_excerpt;
		} else {
			$text = wp_strip_all_tags( $post->post_content );
		}

		$trimmed = wp_trim_words( $text, $length, '&hellip;' );
		return '<div class="gm-field gm-field-excerpt"><p>' . wp_kses_post( $trimmed ) . '</p></div>';
	}

	/**
	 * Render Read More block.
	 */
	public function render_read_more( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$text = ! empty( $attributes['text'] ) ? sanitize_text_field( $attributes['text'] ) : __( 'Read More', 'ajax-filter-posts' );
		$url  = get_permalink( $post_id );

		return sprintf(
			'<div class="gm-field gm-field-read-more"><a href="%s" class="gm-btn gm-btn-read-more">%s</a></div>',
			esc_url( $url ),
			esc_html( $text )
		);
	}

	/**
	 * Render Post Date block.
	 */
	public function render_post_date( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$format = ! empty( $attributes['format'] ) ? sanitize_text_field( $attributes['format'] ) : get_option( 'date_format' );
		$date   = get_the_date( $format, $post_id );

		return sprintf(
			'<div class="gm-field gm-field-date"><time datetime="%s">%s</time></div>',
			esc_attr( get_the_date( 'c', $post_id ) ),
			esc_html( $date )
		);
	}

	/**
	 * Render Post Author block.
	 */
	public function render_post_author( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$author_id   = get_post_field( 'post_author', $post_id );
		$author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
		if ( empty( $author_name ) ) {
			$author_name = __( 'Admin', 'ajax-filter-posts' );
		}
		$author_url  = $author_id ? get_author_posts_url( $author_id ) : home_url();

		return sprintf(
			'<div class="gm-field gm-field-author"><span class="gm-author-name"><a href="%s">%s</a></span></div>',
			esc_url( $author_url ),
			esc_html( $author_name )
		);
	}

	/**
	 * Render Post Terms block.
	 */
	public function render_post_terms( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$taxonomy = ! empty( $attributes['taxonomy'] ) ? sanitize_key( $attributes['taxonomy'] ) : 'category';
		$terms    = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$term_links = array();
		foreach ( $terms as $term ) {
			$term_links[] = sprintf(
				'<a href="%s" class="gm-term-badge gm-term-%s">%s</a>',
				esc_url( get_term_link( $term ) ),
				esc_attr( $term->slug ),
				esc_html( $term->name )
			);
		}

		return '<div class="gm-field gm-field-terms">' . implode( ' ', $term_links ) . '</div>';
	}

	/**
	 * Render Post Meta block.
	 */
	public function render_post_meta( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$meta_key = ! empty( $attributes['metaKey'] ) ? sanitize_key( $attributes['metaKey'] ) : '';
		if ( empty( $meta_key ) ) {
			return '';
		}

		if ( post_password_required( $post_id ) ) {
			return '';
		}

		// Prevent unauthorized exposure of protected/internal post meta.
		if ( is_protected_meta( $meta_key, 'post' ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		$meta_value = get_post_meta( $post_id, $meta_key, true );
		if ( '' === $meta_value || false === $meta_value ) {
			return '';
		}

		$prefix = ! empty( $attributes['prefix'] ) ? esc_html( $attributes['prefix'] ) : '';
		$suffix = ! empty( $attributes['suffix'] ) ? esc_html( $attributes['suffix'] ) : '';

		return sprintf(
			'<div class="gm-field gm-field-meta"><span class="gm-meta-key">%s</span> <span class="gm-meta-val">%s%s%s</span></div>',
			esc_html( $meta_key ),
			$prefix,
			esc_html( is_array( $meta_value ) ? implode( ', ', $meta_value ) : $meta_value ),
			$suffix
		);
	}

	/**
	 * Render Comments Count block.
	 */
	public function render_comments_count( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$count = get_comments_number( $post_id );
		return sprintf(
			'<div class="gm-field gm-field-comments"><span class="gm-comments-number">%s</span></div>',
			esc_html( sprintf( _n( '%s Comment', '%s Comments', $count, 'ajax-filter-posts' ), number_format_i18n( $count ) ) )
		);
	}
}

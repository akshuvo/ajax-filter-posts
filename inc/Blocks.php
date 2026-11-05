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
					'layoutMode'      => array( 'type' => 'string', 'default' => 'vertical' ),
					'mediaWidth'      => array( 'type' => 'string', 'default' => '35%' ),
					'minHeight'       => array( 'type' => 'string', 'default' => '' ),
					'hoverEffect'     => array( 'type' => 'string', 'default' => 'lift' ),
				),
			)
		);

		// Dynamic field blocks.
		$field_blocks = array(
			'featured-image' => array(
				'callback'   => 'render_featured_image',
				'attributes' => array(
					'size'         => array( 'type' => 'string', 'default' => 'medium_large' ),
					'aspectRatio'  => array( 'type' => 'string', 'default' => '16/9' ),
					'objectFit'    => array( 'type' => 'string', 'default' => 'cover' ),
					'borderRadius' => array( 'type' => 'string', 'default' => '0px' ),
					'isLink'       => array( 'type' => 'boolean', 'default' => true ),
					'zoomOnHover'  => array( 'type' => 'boolean', 'default' => false ),
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
					'length'   => array( 'type' => 'number', 'default' => 20 ),
					'fontSize' => array( 'type' => 'string', 'default' => '' ),
					'color'    => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'read-more'      => array(
				'callback'   => 'render_read_more',
				'attributes' => array(
					'text'            => array( 'type' => 'string', 'default' => 'Read More' ),
					'backgroundColor' => array( 'type' => 'string', 'default' => '' ),
					'textColor'       => array( 'type' => 'string', 'default' => '' ),
					'borderRadius'    => array( 'type' => 'string', 'default' => '' ),
					'padding'         => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'post-date'      => array(
				'callback'   => 'render_post_date',
				'attributes' => array(
					'format'   => array( 'type' => 'string', 'default' => '' ),
					'fontSize' => array( 'type' => 'string', 'default' => '' ),
					'color'    => array( 'type' => 'string', 'default' => '' ),
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
			'reading-time'     => array(
				'callback'   => 'render_reading_time',
				'attributes' => array(
					'wordsPerMinute' => array( 'type' => 'number', 'default' => 200 ),
					'prefix'         => array( 'type' => 'string', 'default' => '' ),
					'suffix'         => array( 'type' => 'string', 'default' => 'min read' ),
					'showIcon'       => array( 'type' => 'boolean', 'default' => true ),
					'fontSize'       => array( 'type' => 'string', 'default' => '13px' ),
					'color'          => array( 'type' => 'string', 'default' => '#6b7280' ),
				),
			),
			'author-avatar'    => array(
				'callback'   => 'render_author_avatar',
				'attributes' => array(
					'avatarSize'   => array( 'type' => 'number', 'default' => 40 ),
					'borderRadius' => array( 'type' => 'string', 'default' => '50%' ),
					'isLink'       => array( 'type' => 'boolean', 'default' => true ),
					'borderWidth'  => array( 'type' => 'string', 'default' => '0px' ),
					'borderColor'  => array( 'type' => 'string', 'default' => 'transparent' ),
				),
			),
			'modified-date'    => array(
				'callback'   => 'render_modified_date',
				'attributes' => array(
					'format'         => array( 'type' => 'string', 'default' => '' ),
					'prefix'         => array( 'type' => 'string', 'default' => 'Updated on' ),
					'onlyIfModified' => array( 'type' => 'boolean', 'default' => true ),
					'minHoursDiff'   => array( 'type' => 'number', 'default' => 24 ),
					'fontSize'       => array( 'type' => 'string', 'default' => '13px' ),
					'color'          => array( 'type' => 'string', 'default' => '#6b7280' ),
				),
			),
			'post-format-icon' => array(
				'callback'   => 'render_post_format_icon',
				'attributes' => array(
					'iconSize'        => array( 'type' => 'number', 'default' => 16 ),
					'color'           => array( 'type' => 'string', 'default' => '#ffffff' ),
					'backgroundColor' => array( 'type' => 'string', 'default' => 'rgba(0,0,0,0.6)' ),
					'borderRadius'    => array( 'type' => 'string', 'default' => '50%' ),
					'padding'         => array( 'type' => 'string', 'default' => '8px' ),
				),
			),
			'social-share'     => array(
				'callback'   => 'render_social_share',
				'attributes' => array(
					'networks'    => array( 'type' => 'array', 'default' => array( 'twitter', 'facebook', 'linkedin', 'copylink' ) ),
					'iconStyle'   => array( 'type' => 'string', 'default' => 'minimal' ),
					'iconSize'    => array( 'type' => 'number', 'default' => 14 ),
					'buttonShape' => array( 'type' => 'string', 'default' => 'circle' ),
				),
			),
			'badge'            => array(
				'callback'   => 'render_badge',
				'attributes' => array(
					'text'            => array( 'type' => 'string', 'default' => 'Featured' ),
					'badgeType'       => array( 'type' => 'string', 'default' => 'static' ),
					'metaKey'         => array( 'type' => 'string', 'default' => '' ),
					'backgroundColor' => array( 'type' => 'string', 'default' => '#ef4444' ),
					'textColor'       => array( 'type' => 'string', 'default' => '#ffffff' ),
					'fontSize'        => array( 'type' => 'string', 'default' => '11px' ),
					'borderRadius'    => array( 'type' => 'string', 'default' => '9999px' ),
					'padding'         => array( 'type' => 'string', 'default' => '3px 8px' ),
				),
			),
			'shortcode'        => array(
				'callback'   => 'render_shortcode',
				'attributes' => array(
					'shortcode' => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'html'             => array(
				'callback'   => 'render_html',
				'attributes' => array(
					'content' => array( 'type' => 'string', 'default' => '' ),
				),
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

		/**
		 * Allow Pro and add-ons to enqueue editor assets for custom blocks.
		 */
		do_action( 'gridmaster_enqueue_block_editor_assets' );
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
		$layout_mode = ! empty( $attributes['layoutMode'] ) ? sanitize_key( $attributes['layoutMode'] ) : 'vertical';
		$bg          = ! empty( $attributes['backgroundColor'] ) ? sanitize_hex_color( $attributes['backgroundColor'] ) : '#ffffff';
		$pad         = ! empty( $attributes['padding'] ) ? sanitize_text_field( $attributes['padding'] ) : '20px';
		$radius      = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '8px';
		$shadow      = ! empty( $attributes['boxShadow'] ) ? sanitize_text_field( $attributes['boxShadow'] ) : 'none';
		$b_width     = ! empty( $attributes['borderWidth'] ) ? sanitize_text_field( $attributes['borderWidth'] ) : '';
		$b_color     = ! empty( $attributes['borderColor'] ) ? sanitize_text_field( $attributes['borderColor'] ) : '#dcdcde';
		$media_width = ! empty( $attributes['mediaWidth'] ) ? sanitize_text_field( $attributes['mediaWidth'] ) : '35%';
		$min_height  = ! empty( $attributes['minHeight'] ) ? sanitize_text_field( $attributes['minHeight'] ) : '';
		$hover_fx    = ! empty( $attributes['hoverEffect'] ) ? sanitize_key( $attributes['hoverEffect'] ) : 'lift';

		$classes = array( 'gm-card', 'gm-card--' . $layout_mode, 'gm-card-hover--' . $hover_fx );

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

		if ( in_array( $layout_mode, array( 'horizontal', 'horizontal-reverse' ), true ) ) {
			$style .= sprintf( '--gm-card-media-width:%s;', esc_attr( $media_width ) );
		}

		if ( ! empty( $min_height ) && '0px' !== $min_height && '0' !== $min_height ) {
			$style .= sprintf( 'min-height:%s;', esc_attr( $min_height ) );
		}

		return sprintf(
			'<div class="%s" style="%s">%s</div>',
			esc_attr( implode( ' ', $classes ) ),
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

		$size        = ! empty( $attributes['size'] ) ? sanitize_key( $attributes['size'] ) : 'medium_large';
		$aspect      = ! empty( $attributes['aspectRatio'] ) ? sanitize_text_field( $attributes['aspectRatio'] ) : '16/9';
		$fit         = ! empty( $attributes['objectFit'] ) ? sanitize_key( $attributes['objectFit'] ) : 'cover';
		$is_link     = ! isset( $attributes['isLink'] ) || (bool) $attributes['isLink'];
		$radius      = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '0px';
		$zoom_class  = ! empty( $attributes['zoomOnHover'] ) ? ' gm-img-zoom' : '';

		$img_style = sprintf(
			'width:100%%;height:%s;object-fit:%s;border-radius:%s;%s',
			'auto' === $aspect ? 'auto' : '100%',
			esc_attr( $fit ),
			esc_attr( $radius ),
			'auto' !== $aspect ? 'aspect-ratio:' . esc_attr( $aspect ) . ';' : ''
		);

		$image_html = get_the_post_thumbnail( $post_id, $size, array(
			'style' => $img_style,
			'class' => 'gm-card-img' . $zoom_class,
		) );

		if ( $is_link ) {
			$image_html = sprintf( '<a href="%s" aria-label="%s" style="display:block;height:100%%;">%s</a>', esc_url( get_permalink( $post_id ) ), esc_attr( get_the_title( $post_id ) ), $image_html );
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

		$trimmed   = wp_trim_words( $text, $length, '&hellip;' );
		$font_size = ! empty( $attributes['fontSize'] ) ? sanitize_text_field( $attributes['fontSize'] ) : '';
		$color     = ! empty( $attributes['color'] ) ? sanitize_hex_color( $attributes['color'] ) : '';

		$style = '';
		if ( ! empty( $font_size ) ) {
			$style .= 'font-size:' . esc_attr( $font_size ) . ';';
		}
		if ( ! empty( $color ) ) {
			$style .= 'color:' . esc_attr( $color ) . ';';
		}
		$style_attr = ! empty( $style ) ? ' style="' . $style . '"' : '';

		return '<div class="gm-field gm-field-excerpt"' . $style_attr . '><p>' . wp_kses_post( $trimmed ) . '</p></div>';
	}

	/**
	 * Render Read More block.
	 */
	public function render_read_more( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$text       = ! empty( $attributes['text'] ) ? sanitize_text_field( $attributes['text'] ) : __( 'Read More', 'ajax-filter-posts' );
		$url        = get_permalink( $post_id );
		$bg_color   = ! empty( $attributes['backgroundColor'] ) ? sanitize_text_field( $attributes['backgroundColor'] ) : '';
		$text_color = ! empty( $attributes['textColor'] ) ? sanitize_text_field( $attributes['textColor'] ) : '';
		$radius     = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '';
		$padding    = ! empty( $attributes['padding'] ) ? sanitize_text_field( $attributes['padding'] ) : '';

		$btn_style = '';
		if ( ! empty( $bg_color ) ) {
			$btn_style .= 'background-color:' . esc_attr( $bg_color ) . ';';
		}
		if ( ! empty( $text_color ) ) {
			$btn_style .= 'color:' . esc_attr( $text_color ) . ';';
		}
		if ( ! empty( $radius ) ) {
			$btn_style .= 'border-radius:' . esc_attr( $radius ) . ';';
		}
		if ( ! empty( $padding ) ) {
			$btn_style .= 'padding:' . esc_attr( $padding ) . ';';
		}

		$style_attr = ! empty( $btn_style ) ? ' style="' . $btn_style . '"' : '';

		return sprintf(
			'<div class="gm-field gm-field-read-more"><a href="%s" class="gm-btn gm-btn-read-more"%s>%s</a></div>',
			esc_url( $url ),
			$style_attr,
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

		$format    = ! empty( $attributes['format'] ) ? sanitize_text_field( $attributes['format'] ) : get_option( 'date_format' );
		$date      = get_the_date( $format, $post_id );
		$font_size = ! empty( $attributes['fontSize'] ) ? sanitize_text_field( $attributes['fontSize'] ) : '';
		$color     = ! empty( $attributes['color'] ) ? sanitize_hex_color( $attributes['color'] ) : '';

		$style = '';
		if ( ! empty( $font_size ) ) {
			$style .= 'font-size:' . esc_attr( $font_size ) . ';';
		}
		if ( ! empty( $color ) ) {
			$style .= 'color:' . esc_attr( $color ) . ';';
		}
		$style_attr = ! empty( $style ) ? ' style="' . $style . '"' : '';

		return sprintf(
			'<div class="gm-field gm-field-date"%s><time datetime="%s">%s</time></div>',
			$style_attr,
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

	/**
	 * Render Reading Time block.
	 */
	public function render_reading_time( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$raw_content = get_post_field( 'post_content', $post_id );
		$clean_text  = strip_shortcodes( wp_strip_all_tags( $raw_content ) );
		$word_count  = str_word_count( $clean_text );
		$wpm         = ! empty( $attributes['wordsPerMinute'] ) ? absint( $attributes['wordsPerMinute'] ) : 200;
		$minutes     = max( 1, (int) ceil( $word_count / max( 1, $wpm ) ) );

		$prefix    = ! empty( $attributes['prefix'] ) ? esc_html( $attributes['prefix'] ) . ' ' : '';
		$suffix    = ! empty( $attributes['suffix'] ) ? ' ' . esc_html( $attributes['suffix'] ) : ' min read';
		$show_icon = ! isset( $attributes['showIcon'] ) || (bool) $attributes['showIcon'];

		$style_parts = array();
		if ( ! empty( $attributes['fontSize'] ) ) {
			$style_parts[] = 'font-size:' . esc_attr( $attributes['fontSize'] );
		}
		if ( ! empty( $attributes['color'] ) ) {
			$style_parts[] = 'color:' . esc_attr( $attributes['color'] );
		}
		$style_attr = ! empty( $style_parts ) ? ' style="' . implode( ';', $style_parts ) . '"' : '';

		$icon_html = $show_icon ? '<span class="gm-icon gm-icon-clock" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></span> ' : '';

		return sprintf(
			'<div class="gm-field gm-field-reading-time"%s>%s<span class="gm-reading-time-text">%s%d%s</span></div>',
			$style_attr,
			$icon_html,
			$prefix,
			$minutes,
			$suffix
		);
	}

	/**
	 * Render Author Avatar block.
	 */
	public function render_author_avatar( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$author_id = get_post_field( 'post_author', $post_id );
		if ( ! $author_id ) {
			return '';
		}

		$size        = ! empty( $attributes['avatarSize'] ) ? absint( $attributes['avatarSize'] ) : 40;
		$radius      = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '50%';
		$is_link     = ! isset( $attributes['isLink'] ) || (bool) $attributes['isLink'];
		$author_name = get_the_author_meta( 'display_name', $author_id );

		$avatar_html = get_avatar( $author_id, $size, '', $author_name, array(
			'class'      => 'gm-author-avatar-img',
			'extra_attr' => sprintf( 'style="border-radius:%s;width:%dpx;height:%dpx;object-fit:cover;"', esc_attr( $radius ), $size, $size ),
		) );

		if ( $is_link ) {
			$avatar_html = sprintf(
				'<a href="%s" aria-label="%s" class="gm-author-avatar-link">%s</a>',
				esc_url( get_author_posts_url( $author_id ) ),
				esc_attr( sprintf( __( 'Posts by %s', 'ajax-filter-posts' ), $author_name ) ),
				$avatar_html
			);
		}

		return sprintf( '<div class="gm-field gm-field-author-avatar">%s</div>', $avatar_html );
	}

	/**
	 * Render Modified Date block.
	 */
	public function render_modified_date( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$published_time = get_post_timestamp( $post_id, 'date' );
		$modified_time  = get_post_timestamp( $post_id, 'modified' );

		$only_if_mod = ! isset( $attributes['onlyIfModified'] ) || (bool) $attributes['onlyIfModified'];
		$min_diff    = isset( $attributes['minHoursDiff'] ) ? absint( $attributes['minHoursDiff'] ) * HOUR_IN_SECONDS : DAY_IN_SECONDS;

		if ( $only_if_mod && ( ( $modified_time - $published_time ) < $min_diff ) ) {
			return '';
		}

		$format = ! empty( $attributes['format'] ) ? sanitize_text_field( $attributes['format'] ) : get_option( 'date_format' );
		$prefix = ! empty( $attributes['prefix'] ) ? esc_html( $attributes['prefix'] ) . ' ' : '';
		$date_formatted = get_the_modified_date( $format, $post_id );

		$style_parts = array();
		if ( ! empty( $attributes['fontSize'] ) ) {
			$style_parts[] = 'font-size:' . esc_attr( $attributes['fontSize'] );
		}
		if ( ! empty( $attributes['color'] ) ) {
			$style_parts[] = 'color:' . esc_attr( $attributes['color'] );
		}
		$style_attr = ! empty( $style_parts ) ? ' style="' . implode( ';', $style_parts ) . '"' : '';

		return sprintf(
			'<div class="gm-field gm-field-modified-date"%s><span class="gm-date-prefix">%s</span><time datetime="%s">%s</time></div>',
			$style_attr,
			$prefix,
			esc_attr( get_the_modified_date( 'c', $post_id ) ),
			esc_html( $date_formatted )
		);
	}

	/**
	 * Render Post Format Icon block.
	 */
	public function render_post_format_icon( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$format = get_post_format( $post_id );
		if ( false === $format ) {
			$format = 'standard';
		}

		$icons = array(
			'video'    => '<polygon points="5 3 19 12 5 21 5 3"/>',
			'audio'    => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
			'gallery'  => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
			'quote'    => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/>',
			'standard' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
		);

		$svg_inner = isset( $icons[ $format ] ) ? $icons[ $format ] : $icons['standard'];
		$size      = ! empty( $attributes['iconSize'] ) ? absint( $attributes['iconSize'] ) : 16;
		$bg        = ! empty( $attributes['backgroundColor'] ) ? sanitize_text_field( $attributes['backgroundColor'] ) : 'rgba(0,0,0,0.6)';
		$fg        = ! empty( $attributes['color'] ) ? sanitize_hex_color( $attributes['color'] ) : '#ffffff';
		$radius    = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '50%';
		$padding   = ! empty( $attributes['padding'] ) ? sanitize_text_field( $attributes['padding'] ) : '8px';

		$style = sprintf(
			'background-color:%s;color:%s;border-radius:%s;padding:%s;',
			esc_attr( $bg ),
			esc_attr( $fg ),
			esc_attr( $radius ),
			esc_attr( $padding )
		);

		return sprintf(
			'<div class="gm-field gm-field-format-icon gm-format-%s" style="%s" title="%s"><svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg><span class="screen-reader-text">%s</span></div>',
			esc_attr( $format ),
			$style,
			esc_attr( ucfirst( $format ) ),
			$size,
			$size,
			$svg_inner,
			esc_html( sprintf( __( 'Format: %s', 'ajax-filter-posts' ), ucfirst( $format ) ) )
		);
	}

	/**
	 * Render Social Share block.
	 */
	public function render_social_share( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$url   = rawurlencode( get_permalink( $post_id ) );
		$title = rawurlencode( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );

		$networks = ! empty( $attributes['networks'] ) && is_array( $attributes['networks'] )
			? $attributes['networks']
			: array( 'twitter', 'facebook', 'linkedin', 'copylink' );

		$buttons = array();
		foreach ( $networks as $net ) {
			switch ( $net ) {
				case 'twitter':
					$share_href = "https://twitter.com/intent/tweet?url={$url}&text={$title}";
					$label      = __( 'Share on X', 'ajax-filter-posts' );
					$svg        = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
					break;
				case 'facebook':
					$share_href = "https://www.facebook.com/sharer/sharer.php?u={$url}";
					$label      = __( 'Share on Facebook', 'ajax-filter-posts' );
					$svg        = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
					break;
				case 'linkedin':
					$share_href = "https://www.linkedin.com/sharing/share-offsite/?url={$url}";
					$label      = __( 'Share on LinkedIn', 'ajax-filter-posts' );
					$svg        = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>';
					break;
				case 'copylink':
				default:
					$share_href = esc_url( get_permalink( $post_id ) );
					$label      = __( 'Copy Link', 'ajax-filter-posts' );
					$svg        = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
					break;
			}

			$is_copy = ( 'copylink' === $net );
			$buttons[] = sprintf(
				'<a href="%s" class="gm-share-btn gm-share-%s" %s aria-label="%s" %s>%s</a>',
				esc_url( $share_href ),
				esc_attr( $net ),
				$is_copy ? 'role="button"' : 'target="_blank" rel="noopener noreferrer"',
				esc_attr( $label ),
				$is_copy ? 'data-gm-copy="true" data-url="' . esc_url( get_permalink( $post_id ) ) . '"' : '',
				$svg
			);
		}

		return '<div class="gm-field gm-field-social-share">' . implode( '', $buttons ) . '</div>';
	}

	/**
	 * Render Badge block (Free Core).
	 */
	public function render_badge( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$badge_type = ! empty( $attributes['badgeType'] ) ? $attributes['badgeType'] : 'static';
		$label = '';

		if ( 'meta' === $badge_type && ! empty( $attributes['metaKey'] ) ) {
			$meta_val = get_post_meta( $post_id, sanitize_key( $attributes['metaKey'] ), true );
			if ( ! empty( $meta_val ) ) {
				$label = is_array( $meta_val ) ? implode( ', ', $meta_val ) : (string) $meta_val;
			}
		} else {
			$label = ! empty( $attributes['text'] ) ? sanitize_text_field( $attributes['text'] ) : __( 'Featured', 'ajax-filter-posts' );
		}

		if ( empty( $label ) ) {
			return '';
		}

		$bg      = ! empty( $attributes['backgroundColor'] ) ? sanitize_text_field( $attributes['backgroundColor'] ) : '#ef4444';
		$color   = ! empty( $attributes['textColor'] ) ? sanitize_hex_color( $attributes['textColor'] ) : '#ffffff';
		$font    = ! empty( $attributes['fontSize'] ) ? sanitize_text_field( $attributes['fontSize'] ) : '11px';
		$radius  = ! empty( $attributes['borderRadius'] ) ? sanitize_text_field( $attributes['borderRadius'] ) : '9999px';
		$padding = ! empty( $attributes['padding'] ) ? sanitize_text_field( $attributes['padding'] ) : '3px 8px';

		$style = sprintf(
			'background-color:%s;color:%s;font-size:%s;border-radius:%s;padding:%s;',
			esc_attr( $bg ),
			esc_attr( $color ),
			esc_attr( $font ),
			esc_attr( $radius ),
			esc_attr( $padding )
		);

		return sprintf(
			'<div class="gm-field gm-field-badge"><span class="gm-badge-tag" style="%s">%s</span></div>',
			$style,
			esc_html( $label )
		);
	}

	/**
	 * Render Shortcode block.
	 */
	public function render_shortcode( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$shortcode_text = ! empty( $attributes['shortcode'] ) ? $attributes['shortcode'] : '';
		if ( empty( $shortcode_text ) ) {
			return '';
		}

		// Replace dynamic post tags
		$shortcode_text = str_replace(
			array( '{post_id}', '{id}', '{title}', '{permalink}' ),
			array( $post_id, $post_id, esc_attr( get_the_title( $post_id ) ), esc_url( get_permalink( $post_id ) ) ),
			$shortcode_text
		);

		// Setup global post context so shortcodes relying on get_the_ID() work
		global $post;
		$orig_post = $post;
		$post      = get_post( $post_id );
		if ( $post ) {
			setup_postdata( $post );
		}

		$output = do_shortcode( $shortcode_text );

		if ( $orig_post ) {
			$post = $orig_post;
			setup_postdata( $post );
		} else {
			wp_reset_postdata();
		}

		return '<div class="gm-field gm-field-shortcode">' . $output . '</div>';
	}

	/**
	 * Render Custom HTML block.
	 */
	public function render_html( $attributes, $content, $block ) {
		$post_id = $this->get_context_post_id( $attributes, $block );
		if ( ! $post_id ) {
			return '';
		}

		$raw_html = ! empty( $attributes['content'] ) ? $attributes['content'] : '';
		if ( empty( $raw_html ) ) {
			return '';
		}

		// Replace dynamic post tags
		$rendered = str_replace(
			array( '{post_id}', '{id}', '{title}', '{permalink}', '{date}' ),
			array( $post_id, $post_id, esc_html( get_the_title( $post_id ) ), esc_url( get_permalink( $post_id ) ), get_the_date( '', $post_id ) ),
			$raw_html
		);

		return '<div class="gm-field gm-field-html">' . $rendered . '</div>';
	}
}

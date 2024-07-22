<?php
// Include the admin functions
require_once GRIDMASTER_PATH . '/admin/admin-functions.php';

// Grid id.
$grid_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : null;

// Get grid by id.
$grid = gm_get_grid( $grid_id, true );

// Attributes.
$attr = isset( $grid->attributes ) ? $grid->attributes : array();

?>
<form class="container-fluid gm-container metabox-holder pt-0 gm-ajax-form" id="gm-shortcode-generator" action="" method="post">
	<div class="row">
		<nav class="gm-left-sidebar pt-3 border-1 border-end  col-md-4 col-xl-3 col-xxl-3  d-md-block sidebar">
			<?php ppr( $attr ); ?>
			<div id="gm-select-grid" class="postbox gm-slide-toggle ">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Grid Options', 'gridmaster' ); ?></h2>
					<div class="handle-actions pe-2">
						<span class="dashicons dashicons-arrow-down">
					</div>
				</div>
				<div class="inside" style="display: block;">
				<?php do_action( 'gridmaster_grid_settings_fields_before' ); ?>

					<!-- Select Style -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'grid_style' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Select Grid Style', 'gridmaster' ),
							'options' => gridmaster_grid_styles(),
						),
						gm_field_value( 'grid_style', $attr )
					);
					?>
					<div class="grid-demo-link-button hidden"></div>

					<!-- Select Post Type -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'post_type' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Select Post Type', 'gridmaster'),
							'options' => gm_get_post_types(),
							'default' => 'post',
						),
						gm_field_value( 'post_type', $attr )
					);
					?>

					<!-- Select Advanced Post Type -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'post_type_selection' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Post Type Advanced Selection', 'gridmaster'),
							'options' => array(
								''     => __( 'None', 'gridmaster' ),
								'auto' => __( 'Auto Select', 'gridmaster' ),
							),
							'default' => '',
							'is_pro'  => true,
						),
						gm_field_value( 'post_type_selection', $attr )
					);
					?>

					<!-- posts_per_page -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'posts_per_page' ),
						array(
							'type'    => 'number',
							'label'   => __( 'Posts Per Page', 'gridmaster'),
							'default' => 10,
						),
						gm_field_value( 'posts_per_page', $attr )
					);
					?>

					

					<!-- orderby -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'orderby' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Order By', 'gridmaster'),
							'options' => array(
								'date'           => __( 'Date', 'gridmaster'),
								'title'          => __( 'Title', 'gridmaster'),
								'rand'           => __( 'Random', 'gridmaster'),
								'comment_count'  => __( 'Comment Count', 'gridmaster'),
								'modified'       => __( 'Modified', 'gridmaster'),
								'ID'             => __( 'ID', 'gridmaster'),
								'author'         => __( 'Author', 'gridmaster'),
								'name'           => __( 'Name', 'gridmaster'),
								'type'           => __( 'Type', 'gridmaster'),
								'parent'         => __( 'Parent', 'gridmaster'),
								'menu_order'     => __( 'Menu Order', 'gridmaster'),
								'meta_value'     => __( 'Meta Value', 'gridmaster'),
								'meta_value_num' => __( 'Meta Value Number', 'gridmaster'),
							),
							'default' => 'date',
						),
						gm_field_value( 'orderby', $attr )
					);
					?>

					<!-- order -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'order' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Order', 'gridmaster'),
							'options' => array(
								'DESC' => __( 'Descending', 'gridmaster'),
								'ASC'  => __( 'Ascending', 'gridmaster'),
							),
							'default' => 'DESC',
						),
						gm_field_value( 'order', $attr )
					);
					?>

					<!-- Show content from  -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'content_from' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Show content from', 'gridmaster'),
							'options' => array(
								'excerpt' => __( 'Post Excerpt', 'gridmaster'),
								'content' => __( 'Post Content', 'gridmaster'),
							),
							'default' => 'excerpt',
						),
						gm_field_value( 'content_from', $attr )
					);
					?>

					<!-- excerpt_type -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'excerpt_type' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Excerpt Type', 'gridmaster'),
							'options' => array(
								'words'      => __( 'Words', 'gridmaster'),
								'characters' => __( 'Characters', 'gridmaster'),
							),
							'default' => 'words',
						),
						gm_field_value( 'excerpt_type', $attr )
					);
					?>
					
					<!-- excerpt_length -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'excerpt_length' ),
						array(
							'type'    => 'number',
							'label'   => __( 'Excerpt Length', 'gridmaster'),
							'default' => 15,
						),
						gm_field_value( 'excerpt_length', $attr )
					);
					?>

					<!-- show_read_more -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'show_read_more' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Show Read More', 'gridmaster'),
							'options' => array(
								'yes' => __( 'Yes', 'gridmaster' ),
								'no'  => __( 'No', 'gridmaster' ),
							),
							'default' => 'yes',
						),
						gm_field_value( 'show_read_more', $attr )
					);
					?>

					<!-- read_more_text -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'read_more_text' ),
						array(
							'type'        => 'text',
							'label'       => __( 'Read More Text', 'gridmaster' ),
							'default'     => '',
							'placeholder' => __( 'Read More', 'gridmaster' ),
						),
						gm_field_value( 'read_more_text', $attr )
					);

					// Grid Image Size
					gridmaster_form_field(
						gm_field_name( 'grid_image_size' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Grid Image Size', 'gridmaster'),
							'options' => gm_get_image_sizes(),
							'default' => 'full',
						),
						gm_field_value( 'grid_image_size', $attr )
					);
					?>
					<div class="show-if-grid_image_size-custom">
						<p><i>You can crop the original image size to any custom size. You can also set a single value for height or width in order to keep the original size ratio.</i></p>
						<!-- image_width -->
						<?php
						gridmaster_form_field(
							gm_field_name( 'grid_image_width' ),
							array(
								'type'    => 'number',
								'label'   => __( 'Image Width', 'gridmaster'),
								'default' => 350,
								'is_pro'  => true,
							),
							gm_field_value( 'grid_image_width', $attr )
						);
						?>

						<!-- image_height -->
						<?php
						gridmaster_form_field(
							gm_field_name( 'grid_image_height' ),
							array(
								'type'    => 'number',
								'label'   => __( 'Image Height', 'gridmaster'),
								'default' => 200,
								'is_pro'  => true,
							),
							gm_field_value( 'grid_image_height', $attr )
						);
						?>
					</div>

					<!-- Apply Link on Thumbnail -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'link_thumbnail' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Apply Link on Thumbnail', 'gridmaster' ),
							'options' => array(
								'yes' => __( 'Yes', 'gridmaster' ),
								'no'  => __( 'No', 'gridmaster' ),
							),
							'default' => 'no',
							'is_pro'  => true,
						),
						gm_field_value( 'link_thumbnail', $attr )
					);
					?>

					<div class="show-if-link_thumbnail-yes hidden">
						<!-- link_thumbnail_to -->
						<?php
						gridmaster_form_field(
							gm_field_name( 'link_thumbnail_to' ),
							array(
								'type'    => 'select',
								'label'   => __( 'Link Thumbnail To', 'gridmaster'),
								'options' => array(
									'post'  => __( 'Post Link', 'gridmaster' ),
									'image' => __( 'Image Link', 'gridmaster' ),
								),
								'default' => 'post',
								'is_pro'  => true,
							),
							gm_field_value( 'link_thumbnail_to', $attr )
						);
						?>
					</div>

					<hr>
					<?php
					// Heading Tag
					gridmaster_form_field(
						gm_field_name( 'title_tag' ),
						array(
							'type'        => 'select',
							'label'       => __( 'Heading Tag', 'gridmaster' ),
							'options'     => array(
								''     => __( 'Select Tag', 'gridmaster' ),
								'h1'   => 'H1',
								'h2'   => 'H2',
								'h3'   => 'H3',
								'h4'   => 'H4',
								'h5'   => 'H5',
								'h6'   => 'H6',
								'div'  => 'div',
								'span' => 'span',
								'p'    => 'p',
							),
							'default'     => '',
							'is_pro'      => true,
							'description' => __( 'Select the heading tag for the post title.', 'gridmaster' ),
						),
						gm_field_value( 'title_tag', $attr )
					);

					// Heading Font Size
					gridmaster_form_field(
						gm_field_name( 'heading_font_size' ),
						array(
							'type'             => 'text',
							'label'            => __( 'Heading Font Size', 'gridmaster' ),
							'default'          => array(
								'xs' => '16px',
								'sm' => '18px',
								'md' => '20px',
								'lg' => '22px',
								'xl' => '24px',
							),
							'is_pro'           => true,
							'responsive_field' => true,
							'description'      => __( 'Set the font size for the post title in different devices.', 'gridmaster' ),
						),
						gm_field_value( 'heading_font_size', $attr )
					);
					?>

					<hr>
					<?php
					// Column Gap
					gridmaster_form_field(
						gm_field_name( 'grid_col_gap' ),
						array(
							'type'             => 'number',
							'label'            => __( 'Column Gap', 'gridmaster' ),
							'default'          => 30,
							'is_pro'           => true,
							'responsive_field' => true,
						),
						gm_field_value( 'grid_col_gap', $attr )
					);

					// Row Gap
					gridmaster_form_field(
						gm_field_name( 'grid_row_gap' ),
						array(
							'type'             => 'number',
							'label'            => __( 'Row Gap', 'gridmaster' ),
							'default'          => 30,
							'is_pro'           => true,
							'responsive_field' => true,
						),
						gm_field_value( 'grid_row_gap', $attr )
					);

					// Item Per Row
					gridmaster_form_field(
						gm_field_name( 'grid_item_per_row' ),
						array(
							'type'             => 'number',
							'label'            => __('Item Per Row', 'gridmaster'),
							'default'          => array(
								'xs' => 1,
								'sm' => 2,
								'md' => 3,
								'lg' => 3,
								'xl' => 3,
							),
							'is_pro'           => true,
							'responsive_field' => true,
						),
						gm_field_value( 'grid_item_per_row', $attr )
					);
					?>
					<?php do_action( 'gridmaster_grid_settings_fields_after' ); ?>
				</div>
			</div>

			<!-- Filter Options -->
			<div id="gm-select-filter" class="postbox gm-slide-toggle ">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Filter Options', 'gridmaster' ); ?></h2>
					<div class="handle-actions pe-2">
						<span class="dashicons dashicons-arrow-down">
					</div>
				</div>
				<div class="inside" style="display: block;">

					<!-- show_filter -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'show_filter' ),
						array(
							'type'    => 'radio',
							'label'   => 'Show Filter',
							'options' => array(
								'yes' => __( 'Yes', 'gridmaster' ),
								'no'  => __( 'No', 'gridmaster' ),
							),
							'default' => 'yes',
						),
						gm_field_value( 'show_filter', $attr )
					);
					?>

					<!-- filter_style -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'filter_style' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Filter Style', 'gridmaster' ),
							'options' => apply_filters(
								'gridmaster_filter_styles',
								array(
									'default' => __( 'Style 1 (Default)', 'gridmaster' ),
									'style-2' => __( 'Style 2 (New)', 'gridmaster' ),
									'style-3' => __( 'Style 3 (New)', 'gridmaster' ),
									'style-4' => __( 'Style 4 (New)', 'gridmaster' ),
								)
							),
							'default' => 'default', // default
						),
						gm_field_value( 'filter_style', $attr )
					);
					?>
					<div class="filter-demo-link-button hidden"></div>

					<!-- btn_all -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'btn_all' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Show All Button', 'gridmaster'),
							'options' => array(
								'yes' => __( 'Yes', 'gridmaster' ),
								'no'  => __( 'No', 'gridmaster' ),
							),
							'default' => 'yes',
						),
						gm_field_value( 'btn_all', $attr )
					);
					?>

					<!-- Select taxonomy -->
					<?php
					$taxonomies            = gm_get_taxonomies();
					$taxonomy_options      = $taxonomies['options'];
					$taxonomy_object_types = $taxonomies['object_types'];
					$terms                 = $taxonomies['terms'];

					gridmaster_form_field(
						gm_field_name( 'taxonomy' ),
						array(
							'type'    => 'select',
							'label'   => __( 'Select Taxonomy', 'gridmaster'),
							'options' => $taxonomy_options,
							'default' => 'category', // category
							'class'   => 'gm-select-taxonomy',
						),
						gm_field_value( 'taxonomy', $attr )
					);
					?>
					<script>
						window.gm_taxonomy_object_types = <?php echo wp_json_encode( $taxonomy_object_types ); ?>;
						window.gm_terms = <?php echo wp_json_encode( $terms ); ?>;
					</script>

					<?php
					gridmaster_form_field(
						gm_field_name( 'terms' ),
						array(
							'id'          => 'terms',
							'type'        => 'checkbox-list',
							'label'       => __( 'Select Terms', 'gridmaster' ),
							'placeholder' => __( 'Select Terms', 'gridmaster' ),
							'options'     => $terms['category'],
							'class'       => 'gm-select-term',
						),
						gm_field_value( 'terms', $attr )
					);
					?>
					
					<!-- hide_empty -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'hide_empty' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Hide Empty Terms', 'gridmaster'),
							'options' => array(
								'1' => __( 'Yes', 'gridmaster' ),
								'0' => __( 'No', 'gridmaster' ),
							),
							'default' => '0',
						),
						gm_field_value( 'hide_empty', $attr )
					);
					?>

					<!-- // Initial Term on Page Load -->
					<?php
						gridmaster_form_field(
							gm_field_name( 'initial_term' ),
							array(
								'type'        => 'select',
								'label'       => __( 'Initial Term on Page Load', 'gridmaster'),
								'options'     => array(
									'-1'   => __( 'All - Default', 'gridmaster' ),
									'auto' => __( 'Auto Select', 'gridmaster' ),
								),
								'default'     => '-1',
								'is_pro'      => true,
								'description' => __( 'Select the initial term to be selected on page load.', 'gridmaster' ),
							),
							gm_field_value( 'initial_term', $attr )
						);
						?>

					<!-- Allow Multiple Selection -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'multiple_select' ),
						array(
							'type'        => 'radio',
							'label'       => __( 'Allow Multiple Select', 'gridmaster' ),
							'options'     => array(
								'yes' => __( 'Yes', 'gridmaster' ),
								'no'  => __( 'No', 'gridmaster' ),
							),
							'default'     => 'no',
							'description' => __( 'Allow multiple selection of terms in the filter.', 'gridmaster' ),
							'is_pro'      => true,
						),
						gm_field_value( 'multiple_select', $attr )
					);
					?>
					<hr>
					<!-- Filter Heading -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'filter_heading' ),
						array(
							'type'        => 'text',
							'label'       => __( 'Add a Filter Heading?', 'gridmaster' ),
							'default'     => '',
							'placeholder' => __( 'Category', 'gridmaster' ),
						),
						gm_field_value( 'filter_heading', $attr )
					);
					?>

					<!-- Toggle Filter Items -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'toggle_filter_items' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Toggle Filter Items', 'gridmaster' ),
							'options' => array(
								'yes' => __( 'Yes', 'gridmaster' ),
								'no'  => __( 'No', 'gridmaster' ),
							),
							'default' => 'no',
						),
						gm_field_value( 'toggle_filter_items', $attr )
					);
					?>

				</div>
			</div>
			<!-- Filter Options -->

			<div id="gm-select-slider" class="postbox gm-slide-toggle ">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Slider Options', 'gridmaster' ); ?></h2>
					<div class="handle-actions pe-2">
						<span class="dashicons dashicons-arrow-down">
					</div>
				</div>
				<div class="inside" style="display: block;">

					<?php
					gridmaster_form_field(
						gm_field_name( 'enable_slider' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Enable Slider', 'gridmaster' ),
							'options' => array(
								''    => __( 'No', 'gridmaster' ),
								'yes' => __( 'Yes', 'gridmaster' ),
							),
							'default' => '',
							'is_pro'  => true,
						),
						gm_field_value( 'enable_slider', $attr )
					);
					?>

					<div class="show-if-enable_slider-yes">
						<?php
						// slidesToShow
						gridmaster_form_field(
							gm_field_name( 'slider_slidesToShow' ),
							array(
								'type'             => 'number',
								'label'            => __( 'Slides to Show', 'gridmaster' ),
								'default'          => array(
									'xs' => 1,
									'sm' => 2,
									'md' => 3,
									'lg' => 3,
									'xl' => 3,
								),
								'is_pro'           => true,
								'responsive_field' => true,
							),
							gm_field_value( 'slider_slidesToShow', $attr )
						);

						// slidesToScroll
						gridmaster_form_field(
							gm_field_name( 'slider_slidesToScroll' ),
							array(
								'type'             => 'number',
								'label'            => __( 'Slides to Scroll', 'gridmaster' ),
								'default'          => array(
									'xs' => 1,
									'sm' => 1,
									'md' => 1,
									'lg' => 1,
									'xl' => 1,
								),
								'is_pro'           => true,
								'responsive_field' => true,
							),
							gm_field_value( 'slider_slidesToScroll', $attr )
						);

						// arrows
						gridmaster_form_field(
							gm_field_name( 'slider_arrows' ),
							array(
								'type'    => 'radio',
								'label'   => __( 'Show Prev/Next Arrows', 'gridmaster' ),
								'options' => array(
									'0' => __( 'No', 'gridmaster' ),
									'1' => __( 'Yes', 'gridmaster' ),
								),
								'default' => '1',
								'is_pro'  => true,
							),
							gm_field_value( 'slider_arrows', $attr )
						);

						// dots
						gridmaster_form_field(
							gm_field_name( 'slider_dots' ),
							array(
								'type'    => 'radio',
								'label'   => __( 'Show Dots', 'gridmaster' ),
								'options' => array(
									''  => __( 'No', 'gridmaster' ),
									'1' => __( 'Yes', 'gridmaster' ),
								),
								'default' => '',
								'is_pro'  => true,
							),
							gm_field_value( 'slider_dots', $attr )
						);

						// autoplay
						gridmaster_form_field(
							gm_field_name( 'slider_autoplay' ),
							array(
								'type'    => 'radio',
								'label'   => __( 'Enable Autoplay', 'gridmaster' ),
								'options' => array(
									''  => __( 'No', 'gridmaster' ),
									'1' => __( 'Yes', 'gridmaster' ),
								),
								'default' => '',
								'is_pro'  => true,
							),
							gm_field_value( 'slider_autoplay', $attr )
						);

						// autoplaySpeed
						gridmaster_form_field(
							gm_field_name( 'slider_autoplaySpeed' ),
							array(
								'type'    => 'number',
								'label'   => __( 'Autoplay Speed', 'gridmaster' ),
								'default' => 3000,
								'is_pro'  => true,
							),
							gm_field_value( 'slider_autoplaySpeed', $attr )
						);

						// pauseOnHover
						gridmaster_form_field(
							gm_field_name( 'slider_pauseOnHover' ),
							array(
								'type'    => 'radio',
								'label'   => __( 'Pause Autoplay on Hover', 'gridmaster' ),
								'options' => array(
									''  => __( 'No', 'gridmaster' ),
									'1' => __( 'Yes', 'gridmaster' ),
								),
								'default' => '',
								'is_pro'  => true,
							),
							gm_field_value( 'slider_pauseOnHover', $attr )
						);

						// infinite
						gridmaster_form_field(
							gm_field_name( 'slider_infinite' ),
							array(
								'type'    => 'radio',
								'label'   => __( 'Infinite loop sliding', 'gridmaster' ),
								'options' => array(
									'0' => __( 'No', 'gridmaster' ),
									'1' => __( 'Yes', 'gridmaster' ),
								),
								'default' => '1',
								'is_pro'  => true,
							),
							gm_field_value( 'slider_infinite', $attr )
						);

						// centerMode
						gridmaster_form_field(
							gm_field_name( 'slider_centerMode' ),
							array(
								'type'    => 'radio',
								'label'   => __( 'Enable Center View', 'gridmaster' ),
								'options' => array(
									''  => __( 'No', 'gridmaster' ),
									'1' => __( 'Yes', 'gridmaster' ),
								),
								'default' => '',
								'is_pro'  => true,
							),
							gm_field_value( 'slider_centerMode', $attr )
						);

						?>
					</div>
					
				</div>
			</div>
			
			<div id="gm-select-pagination" class="postbox gm-slide-toggle ">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Pagination Options', 'gridmaster' ); ?></h2>
					<div class="handle-actions pe-2">
						<span class="dashicons dashicons-arrow-down">
					</div>
				</div>
				<div class="inside" style="display: block;">
					<!-- pagination_type -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'pagination_type' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Pagination Type', 'gridmaster'),
							'options' => array(
								''          => __( 'Default', 'gridmaster'),
								'load_more' => __( 'Load More', 'gridmaster'),
							),
							'default' => '',
						),
						gm_field_value( 'pagination_type', $attr )
					);
					?>

					<!-- infinite_scroll -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'infinite_scroll' ),
						array(
							'type'        => 'radio',
							'label'       => __( 'Infinite Scroll', 'gridmaster'),
							'options'     => array(
								'true'  => 'Yes',
								'false' => 'No',
							),
							'default'     => 'no',
							'description' => __( 'If pagination type is default, this option will be ignored.', 'gridmaster'),
						),
						gm_field_value( 'infinite_scroll', $attr )
					);
					?>

					<!-- animation -->
					<?php
					gridmaster_form_field(
						gm_field_name( 'animation' ),
						array(
							'type'    => 'radio',
							'label'   => __( 'Animation', 'gridmaster'),
							'options' => array(
								'true'  => __( 'Yes', 'gridmaster'),
								'false' => __( 'No', 'gridmaster'),
							),
							'default' => 'no',
						),
						gm_field_value( 'animation', $attr )
					);
					?>
				</div>
			</div>
			<!-- Spacer -->
			<div class="gm-spacer clear" style="height: 100px;"></div>
		</nav>

		<main class="pt-3 gm-right-sidebar col-md-9 ms-sm-auto col-lg-9 ">
			<?php // echo do_shortcode("[gridmaster]"); ?>

			<!-- Grid Preview  -->
			<div class="gm-iframe-postbox postbox gm-slide-toggle-- ">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Preview', 'gridmaster' ); ?></h2>
					<div class="preview-action-buttons">
						<div class="gridmaster-responsive-fields-devices px-2">
						<?php
						foreach ( gm_get_breakpoints() as $device => $breakpoint ) {
							$classes = '';
							if ( $breakpoint['default'] ) {
								$classes = ' selected ';
							}
							echo '<div class="gridmaster-responsive-fields-device gm-tooltip' . esc_attr( $classes ) . '" data-device="' . esc_attr( $device ) . '" title="' . esc_attr( $breakpoint['label'] . ' (>=' . $breakpoint['value'] . 'px)' ) . '">'
								. '<span class="dashicons dashicons-' . esc_attr( $breakpoint['icon'] ) . '"></span>' .
							'</div>';
						}
						?>
						</div>
						<div class="align-items-center d-flex preview-scale px-2">
												   
							<div id="gm-responsive-bar-scale__minus" class="gm-tooltip" title="<?php esc_attr_e( 'Scale Down', 'gridmaster' ); ?>">
								<span class="dashicons dashicons-minus"></span>
							</div>
							<div id="gm-responsive-bar-scale__value-wrapper">
								<input class="skip-reload hidden" id="gm-preview-scale-input" type="number" min="50" max="120" step="10" value="100" readonly/>
								<span id="gm-responsive-bar-scale__value">100</span>%
							</div>
							<div id="gm-responsive-bar-scale__plus" class="gm-tooltip" title="<?php esc_attr_e( 'Scale Up', 'gridmaster' ); ?>">
								<span class="dashicons dashicons-plus-alt2"></span>
							</div>
							<div id="gm-responsive-bar-scale__reset" class="gm-tooltip" title="<?php esc_attr_e( 'Reset Scale', 'gridmaster' ); ?>">
								<span class="dashicons dashicons-undo"></span>
							</div>
				
						</div>
					</div>
					<div class="handle-actions pe-2">
						<span class="dashicons dashicons-arrow-down">
					</div>
				</div>
				<div class="inside" style="display: block;">
					<div id="gm-grid-preview">
						<div class="gm-iframe-wrap loading">
							<div class="asr-loader">
								<div class="lds-dual-ring"></div>
							</div>
							<iframe id="gm-iframe" src="<?php echo esc_url( home_url( '/' ) . '?gm_shortcode_preview=1&shortcode=' . urlencode( '[gridmaster]' ) ); ?>" frameborder="0"></iframe>
						</div>
					</div>
				</div>
			</div>
			<!-- End Grid Preview -->
		</main>
	</div>

	<div class="gm-ajax-response"></div>
	<input type="hidden" name="id" value="<?php echo esc_attr( $grid_id ); ?>">
	<input class="gm-ignore-field" type="hidden" name="action" value="gridmaster_ajax">
	<input class="gm-ignore-field" type="hidden" name="gm-action" value="save_grid">
	<?php wp_nonce_field( 'gm-ajax-nonce', 'gm_nonce' ); ?>
</form>

<!-- Modal -->
<div id="gm-embed-modal" class="gm-modal-wrap">
	<div class="gm-modal-inner">
		<div class="gm-modal-body">
			<div class="gm-modal-content">
				<button type="button" class="button gm-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
				<h2 class="m-0"><?php esc_html_e( 'Embed Shortcode', 'gridmaster' ); ?></h2>
				<p class="desc"><?php esc_html_e( 'Copy the shortcode below and paste it into your post, page, or text widget content.', 'gridmaster' ); ?></p>
				<div class="d-flex gm-copy-wrap gm-saved-code">
					<input type="text" value="<?php esc_attr_e( '[gridmaster id="1"]', 'gridmaster' ); ?>" class="regular-text gm-copy-val" readonly>
					<button type="button" class="gm-copy-btn gm-btn gm-tooltip" title="<?php esc_html_e( 'Copy Shortcode', 'gridmaster' ); ?>"><span class="m-0 dashicons dashicons-admin-page"></span></button>
				</div>

				<h2 class="mb-0"><?php esc_html_e( 'Or below one', 'gridmaster' ); ?></h2>
				<div class="d-flex gm-copy-wrap">
					<input type="text" value="[gridmaster]" class="regular-text gm-copy-inp gm-copy-val" readonly>
					<button type="button" class="gm-copy-btn gm-btn gm-tooltip" title="<?php esc_html_e( 'Copy Shortcode', 'gridmaster' ); ?>"><span class="m-0 dashicons dashicons-admin-page"></span></button>
				</div>

			</div>
		</div>
	</div>
</div>

<script>
	// Scale preview
	const scaleInput = document.querySelector("#gm-preview-scale-input")
	scaleInput.addEventListener("input", (event) => {
		let thisVal = Math.round(event.target.value)
		const scale =  thisVal/ 100
		const iframe = document.querySelector("#gm-iframe")
		iframe.style.transform = `scale(${scale})`
		document.querySelector("#gm-responsive-bar-scale__value").innerHTML = thisVal
	})

	// Increase scale
	const incrBtn = document.querySelector("#gm-responsive-bar-scale__plus")
	incrBtn.addEventListener("click", (event) => {
		scaleInput.stepUp();
		scaleInput.dispatchEvent(new Event('input', {bubbles:true}));
	})

	// Decrease scale
	const decrBtn = document.querySelector("#gm-responsive-bar-scale__minus")
	decrBtn.addEventListener("click", (event) => {
		scaleInput.stepDown();
		scaleInput.dispatchEvent(new Event('input', {bubbles:true}));
	})

	// Reset scale
	const resetBtn = document.querySelector("#gm-responsive-bar-scale__reset")
	resetBtn.addEventListener("click", (event) => {
		scaleInput.value = 100;
		scaleInput.dispatchEvent(new Event('input', {bubbles:true}));
	})

	// Add classes to taxonomy options
	const taxOptions = document.querySelectorAll(".gm-select-taxonomy option");
	taxOptions.forEach( (option) => {
		const tax = option.value;
		if( gm_taxonomy_object_types[tax] ) {
			// Add classes with 'post_type_' prefix
			const classes = gm_taxonomy_object_types[tax].map( (post_type) => {
				return 'obj_type_' + post_type
			})
			option.classList.add(...classes)

			// If post type is not `post` then add hidden class
			if( !gm_taxonomy_object_types[tax].includes('post') ) {
				option.classList.add('hidden')
			}
		}
	})

	// Post Type change
	const postTypeSelect = document.querySelector("#post_type");
	postTypeSelect.addEventListener("change", (event) => {
		const postType = event.target.value;
	  
		taxOptions.forEach( (option) => {
			if( option.classList.contains('obj_type_' + postType) ) {
				option.classList.remove('hidden')
			} else {
				if( option.value !== '-' ) {
					option.classList.add('hidden')
				}
			}

			// Select first option
			if( option.value == '-' ) {
				option.selected = true;
			}
		})

	})

	// Window load event
	window.addEventListener("load", (event) => {
		// Trigger Post Type change
		// postTypeSelect.dispatchEvent(new Event('change', {bubbles:true}));

		// Trigger Taxonomy change
		jQuery('#taxonomy').trigger('change');


	})

	// Add a loader class when Iframe is loading
	const iframe = document.querySelector("#gm-iframe")
	iframe.addEventListener("load", (event) => {
		iframe.parentNode.classList.remove('loading')
	})

	// // Save the shortcode
	// jQuery(document).ready(function($) {
	// 	$('#gm-shortcode-generator').on('submit', function(e) {
	// 		e.preventDefault();
	// 		var data = $(this).serialize();
	// 		$.ajax({
	// 			url: ajaxurl,
	// 			type: 'post',
	// 			data: data,
	// 			success: function(response) {
	// 				if( response.success ) {
	// 					alert('Shortcode saved successfully');
	// 				} else {
	// 					alert('Failed to save shortcode');
	// 				}
	// 			}
	// 		});
	// 	});
	// });

</script>
<style>
	.gm-admin-content {
		max-width: 100% !important;
	}
	.gm-admin-into {
		display: none;
	}
	.toplevel_page_gridmaster #wpcontent #wpbody {
		height: calc(100vh - 72px);
		overflow: hidden;
	}

	.toplevel_page_gridmaster #wpcontent #wpbody-content {
		padding-bottom: 0;
		height: 100%;
	}

	#gm-shortcode-generator p {
		color: #3c434a;
		font-size: inherit;
	}

</style>
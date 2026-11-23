<?php
/**
 * Deterministic legacy storage fixtures for GridMaster 2.0 M2-T01 characterization tests.
 *
 * TEST-ONLY FIXTURE: Models rows in `{$wpdb->prefix}gridmaster_grids` (`id`, `title`,
 * `attributes`, `dated`) without connecting to WordPress or MySQL.
 *
 * @package GridMaster
 */

$gm2_row_42_serialized = 'a:43:{'
	. 's:10:"grid_style";s:7:"style-2";'
	. 's:9:"post_type";s:4:"post";'
	. 's:19:"post_type_selection";s:0:"";'
	. 's:14:"posts_per_page";s:1:"6";'
	. 's:7:"orderby";s:4:"date";'
	. 's:5:"order";s:4:"DESC";'
	. 's:12:"content_from";s:7:"excerpt";'
	. 's:12:"excerpt_type";s:5:"words";'
	. 's:14:"excerpt_length";s:2:"20";'
	. 's:14:"show_read_more";s:3:"yes";'
	. 's:14:"read_more_text";s:16:"Continue Reading";'
	. 's:15:"grid_image_size";s:12:"medium_large";'
	. 's:16:"grid_image_width";s:3:"350";'
	. 's:17:"grid_image_height";s:3:"200";'
	. 's:14:"link_thumbnail";s:3:"yes";'
	. 's:17:"link_thumbnail_to";s:4:"post";'
	. 's:9:"title_tag";s:2:"h3";'
	. 's:17:"heading_font_size";a:5:{s:2:"xs";s:4:"16px";s:2:"sm";s:4:"18px";s:2:"md";s:4:"20px";s:2:"lg";s:4:"22px";s:2:"xl";s:4:"24px";}'
	. 's:12:"grid_col_gap";a:5:{s:2:"xs";s:2:"16";s:2:"sm";s:2:"20";s:2:"md";s:2:"24";s:2:"lg";s:2:"30";s:2:"xl";s:2:"30";}'
	. 's:12:"grid_row_gap";a:5:{s:2:"xs";s:2:"20";s:2:"sm";s:2:"24";s:2:"md";s:2:"24";s:2:"lg";s:2:"30";s:2:"xl";s:2:"30";}'
	. 's:17:"grid_item_per_row";a:5:{s:2:"xs";s:1:"1";s:2:"sm";s:1:"2";s:2:"md";s:1:"3";s:2:"lg";s:1:"3";s:2:"xl";s:1:"3";}'
	. 's:11:"show_filter";s:3:"yes";'
	. 's:12:"filter_style";s:7:"style-2";'
	. 's:7:"btn_all";s:3:"yes";'
	. 's:8:"taxonomy";s:8:"category";'
	. 's:5:"terms";s:3:"3,7";'
	. 's:10:"hide_empty";s:1:"1";'
	. 's:12:"initial_term";s:2:"-1";'
	. 's:15:"multiple_select";s:2:"no";'
	. 's:14:"filter_heading";s:6:"Topics";'
	. 's:19:"toggle_filter_items";s:2:"no";'
	. 's:13:"enable_slider";s:0:"";'
	. 's:19:"slider_slidesToShow";a:5:{s:2:"xs";s:1:"1";s:2:"sm";s:1:"2";s:2:"md";s:1:"3";s:2:"lg";s:1:"3";s:2:"xl";s:1:"3";}'
	. 's:21:"slider_slidesToScroll";a:5:{s:2:"xs";s:1:"1";s:2:"sm";s:1:"1";s:2:"md";s:1:"1";s:2:"lg";s:1:"1";s:2:"xl";s:1:"1";}'
	. 's:13:"slider_arrows";s:1:"1";'
	. 's:11:"slider_dots";s:0:"";'
	. 's:15:"slider_autoplay";s:0:"";'
	. 's:20:"slider_autoplaySpeed";s:4:"3000";'
	. 's:19:"slider_pauseOnHover";s:0:"";'
	. 's:15:"slider_infinite";s:1:"1";'
	. 's:17:"slider_centerMode";s:0:"";'
	. 's:15:"pagination_type";s:9:"load_more";'
	. 's:2:"id";s:2:"42";'
	. '}';

$gm2_row_108_serialized = 'a:43:{'
	. 's:10:"grid_style";s:11:"pro-style-4";'
	. 's:9:"post_type";s:9:"portfolio";'
	. 's:19:"post_type_selection";s:4:"auto";'
	. 's:14:"posts_per_page";s:2:"12";'
	. 's:7:"orderby";s:10:"menu_order";'
	. 's:5:"order";s:3:"ASC";'
	. 's:12:"content_from";s:7:"content";'
	. 's:12:"excerpt_type";s:10:"characters";'
	. 's:14:"excerpt_length";s:3:"120";'
	. 's:14:"show_read_more";s:3:"yes";'
	. 's:14:"read_more_text";s:12:"View Project";'
	. 's:15:"grid_image_size";s:4:"full";'
	. 's:16:"grid_image_width";s:3:"480";'
	. 's:17:"grid_image_height";s:3:"320";'
	. 's:14:"link_thumbnail";s:3:"yes";'
	. 's:17:"link_thumbnail_to";s:4:"post";'
	. 's:9:"title_tag";s:2:"h2";'
	. 's:17:"heading_font_size";a:5:{s:2:"xs";s:4:"18px";s:2:"sm";s:4:"20px";s:2:"md";s:4:"22px";s:2:"lg";s:4:"24px";s:2:"xl";s:4:"28px";}'
	. 's:12:"grid_col_gap";a:5:{s:2:"xs";s:2:"20";s:2:"sm";s:2:"24";s:2:"md";s:2:"30";s:2:"lg";s:2:"36";s:2:"xl";s:2:"40";}'
	. 's:12:"grid_row_gap";a:5:{s:2:"xs";s:2:"20";s:2:"sm";s:2:"24";s:2:"md";s:2:"30";s:2:"lg";s:2:"36";s:2:"xl";s:2:"40";}'
	. 's:17:"grid_item_per_row";a:5:{s:2:"xs";s:1:"1";s:2:"sm";s:1:"2";s:2:"md";s:1:"3";s:2:"lg";s:1:"4";s:2:"xl";s:1:"4";}'
	. 's:11:"show_filter";s:3:"yes";'
	. 's:12:"filter_style";s:12:"pro-filter-1";'
	. 's:7:"btn_all";s:3:"yes";'
	. 's:8:"taxonomy";s:13:"portfolio_cat";'
	. 's:5:"terms";s:8:"11,14,19";'
	. 's:10:"hide_empty";s:1:"0";'
	. 's:12:"initial_term";s:2:"11";'
	. 's:15:"multiple_select";s:3:"yes";'
	. 's:14:"filter_heading";s:10:"Discipline";'
	. 's:19:"toggle_filter_items";s:3:"yes";'
	. 's:13:"enable_slider";s:3:"yes";'
	. 's:19:"slider_slidesToShow";a:5:{s:2:"xs";s:1:"1";s:2:"sm";s:1:"2";s:2:"md";s:1:"3";s:2:"lg";s:1:"4";s:2:"xl";s:1:"4";}'
	. 's:21:"slider_slidesToScroll";a:5:{s:2:"xs";s:1:"1";s:2:"sm";s:1:"1";s:2:"md";s:1:"2";s:2:"lg";s:1:"2";s:2:"xl";s:1:"2";}'
	. 's:13:"slider_arrows";s:1:"1";'
	. 's:11:"slider_dots";s:1:"1";'
	. 's:15:"slider_autoplay";s:1:"1";'
	. 's:20:"slider_autoplaySpeed";s:4:"2500";'
	. 's:19:"slider_pauseOnHover";s:1:"1";'
	. 's:15:"slider_infinite";s:1:"1";'
	. 's:17:"slider_centerMode";s:1:"1";'
	. 's:15:"pagination_type";s:0:"";'
	. 's:2:"id";s:3:"108";'
	. '}';

return array(
	'table_prefix' => 'wp_gm2_test_',
	'rows'         => array(
		42  => array(
			'id'                        => 42,
			'title'                     => 'Featured Blog Grid',
			'dated'                     => '2025-11-15 09:30:00',
			'raw_attributes_serialized' => $gm2_row_42_serialized,
			'raw_attributes_length'     => 1878,
			'expected_attributes'       => array(
				'grid_style'            => 'style-2',
				'post_type'             => 'post',
				'post_type_selection'   => '',
				'posts_per_page'        => '6',
				'orderby'               => 'date',
				'order'                 => 'DESC',
				'content_from'          => 'excerpt',
				'excerpt_type'          => 'words',
				'excerpt_length'        => '20',
				'show_read_more'        => 'yes',
				'read_more_text'        => 'Continue Reading',
				'grid_image_size'       => 'medium_large',
				'grid_image_width'      => '350',
				'grid_image_height'     => '200',
				'link_thumbnail'        => 'yes',
				'link_thumbnail_to'     => 'post',
				'title_tag'             => 'h3',
				'heading_font_size'     => array(
					'xs' => '16px',
					'sm' => '18px',
					'md' => '20px',
					'lg' => '22px',
					'xl' => '24px',
				),
				'grid_col_gap'          => array(
					'xs' => '16',
					'sm' => '20',
					'md' => '24',
					'lg' => '30',
					'xl' => '30',
				),
				'grid_row_gap'          => array(
					'xs' => '20',
					'sm' => '24',
					'md' => '24',
					'lg' => '30',
					'xl' => '30',
				),
				'grid_item_per_row'     => array(
					'xs' => '1',
					'sm' => '2',
					'md' => '3',
					'lg' => '3',
					'xl' => '3',
				),
				'show_filter'           => 'yes',
				'filter_style'          => 'style-2',
				'btn_all'               => 'yes',
				'taxonomy'              => 'category',
				'terms'                 => '3,7',
				'hide_empty'            => '1',
				'initial_term'          => '-1',
				'multiple_select'       => 'no',
				'filter_heading'        => 'Topics',
				'toggle_filter_items'   => 'no',
				'enable_slider'         => '',
				'slider_slidesToShow'   => array(
					'xs' => '1',
					'sm' => '2',
					'md' => '3',
					'lg' => '3',
					'xl' => '3',
				),
				'slider_slidesToScroll' => array(
					'xs' => '1',
					'sm' => '1',
					'md' => '1',
					'lg' => '1',
					'xl' => '1',
				),
				'slider_arrows'         => '1',
				'slider_dots'           => '',
				'slider_autoplay'       => '',
				'slider_autoplaySpeed'  => '3000',
				'slider_pauseOnHover'   => '',
				'slider_infinite'       => '1',
				'slider_centerMode'     => '',
				'pagination_type'       => 'load_more',
				'id'                    => '42',
			),
		),
		108 => array(
			'id'                        => 108,
			'title'                     => 'Pro Portfolio Carousel',
			'dated'                     => '2026-01-20 14:15:22',
			'raw_attributes_serialized' => $gm2_row_108_serialized,
			'raw_attributes_length'     => 1916,
			'expected_attributes'       => array(
				'grid_style'            => 'pro-style-4',
				'post_type'             => 'portfolio',
				'post_type_selection'   => 'auto',
				'posts_per_page'        => '12',
				'orderby'               => 'menu_order',
				'order'                 => 'ASC',
				'content_from'          => 'content',
				'excerpt_type'          => 'characters',
				'excerpt_length'        => '120',
				'show_read_more'        => 'yes',
				'read_more_text'        => 'View Project',
				'grid_image_size'       => 'full',
				'grid_image_width'      => '480',
				'grid_image_height'     => '320',
				'link_thumbnail'        => 'yes',
				'link_thumbnail_to'     => 'post',
				'title_tag'             => 'h2',
				'heading_font_size'     => array(
					'xs' => '18px',
					'sm' => '20px',
					'md' => '22px',
					'lg' => '24px',
					'xl' => '28px',
				),
				'grid_col_gap'          => array(
					'xs' => '20',
					'sm' => '24',
					'md' => '30',
					'lg' => '36',
					'xl' => '40',
				),
				'grid_row_gap'          => array(
					'xs' => '20',
					'sm' => '24',
					'md' => '30',
					'lg' => '36',
					'xl' => '40',
				),
				'grid_item_per_row'     => array(
					'xs' => '1',
					'sm' => '2',
					'md' => '3',
					'lg' => '4',
					'xl' => '4',
				),
				'show_filter'           => 'yes',
				'filter_style'          => 'pro-filter-1',
				'btn_all'               => 'yes',
				'taxonomy'              => 'portfolio_cat',
				'terms'                 => '11,14,19',
				'hide_empty'            => '0',
				'initial_term'          => '11',
				'multiple_select'       => 'yes',
				'filter_heading'        => 'Discipline',
				'toggle_filter_items'   => 'yes',
				'enable_slider'         => 'yes',
				'slider_slidesToShow'   => array(
					'xs' => '1',
					'sm' => '2',
					'md' => '3',
					'lg' => '4',
					'xl' => '4',
				),
				'slider_slidesToScroll' => array(
					'xs' => '1',
					'sm' => '1',
					'md' => '2',
					'lg' => '2',
					'xl' => '2',
				),
				'slider_arrows'         => '1',
				'slider_dots'           => '1',
				'slider_autoplay'       => '1',
				'slider_autoplaySpeed'  => '2500',
				'slider_pauseOnHover'   => '1',
				'slider_infinite'       => '1',
				'slider_centerMode'     => '1',
				'pagination_type'       => '',
				'id'                    => '108',
			),
		),
	),
	'missing_ids'  => array(
		0,
		-5,
		9999,
	),
);

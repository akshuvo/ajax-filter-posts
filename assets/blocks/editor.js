/**
 * GridMaster 2.0 — Gutenberg Block Editor Definitions
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps, InnerBlocks } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, ToggleControl, RangeControl } = wp.components;
	const __ = wp.i18n.__;

	const blockData = window.gridmasterBlockData || {};
	const grids = blockData.grids || [ { label: __( 'Select a Grid', 'ajax-filter-posts' ), value: 0 } ];

	// Allowed blocks inside the post card
	const ALLOWED_CARD_BLOCKS = [
		'gridmaster/featured-image',
		'gridmaster/title',
		'gridmaster/excerpt',
		'gridmaster/read-more',
		'gridmaster/post-date',
		'gridmaster/post-author',
		'gridmaster/post-terms',
		'gridmaster/post-meta',
		'gridmaster/comments-count',
		'gridmaster-pro/product-price',
		'gridmaster-pro/add-to-cart',
		'gridmaster-pro/badge',
		'core/group',
		'core/columns',
		'core/column',
		'core/spacer',
		'core/separator',
		'core/paragraph',
		'core/heading',
	];

	// ==========================================
	// 1. Grid Embed Block (gridmaster/grid)
	// ==========================================
	registerBlockType( 'gridmaster/grid', {
		apiVersion: 3,
		title: __( 'GridMaster Grid', 'ajax-filter-posts' ),
		description: __( 'Embed a saved GridMaster post grid layout.', 'ajax-filter-posts' ),
		icon: 'grid-view',
		category: 'widgets',
		attributes: {
			gridId: { type: 'number', default: 0 },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-embed-block' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Grid Settings', 'ajax-filter-posts' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Select Grid', 'ajax-filter-posts' ),
							value: attributes.gridId,
							options: grids,
							onChange: function ( val ) {
								setAttributes( { gridId: parseInt( val, 10 ) || 0 } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'gm-embed-placeholder' },
						el( 'span', { className: 'dashicons dashicons-grid-view' } ),
						el( 'strong', null, __( 'GridMaster Grid Embed', 'ajax-filter-posts' ) ),
						el(
							'p',
							null,
							attributes.gridId
								? sprintf( __( 'Rendering Grid #%d on frontend.', 'ajax-filter-posts' ), attributes.gridId )
								: __( 'Please choose a grid in the block settings panel.', 'ajax-filter-posts' )
						)
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	// ==========================================
	// 2. Card Container Block (gridmaster/card)
	// ==========================================
	registerBlockType( 'gridmaster/card', {
		apiVersion: 3,
		title: __( 'Post Card', 'ajax-filter-posts' ),
		description: __( 'The main container for one post card layout.', 'ajax-filter-posts' ),
		icon: 'format-aside',
		category: 'layout',
		attributes: {
			backgroundColor: { type: 'string', default: '#ffffff' },
			padding: { type: 'string', default: '20px' },
			borderRadius: { type: 'string', default: '8px' },
			boxShadow: { type: 'string', default: 'none' },
			borderWidth: { type: 'string', default: '0px' },
			borderColor: { type: 'string', default: '#dcdcde' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const cardStyle = {
				backgroundColor: attributes.backgroundColor,
				padding: attributes.padding,
				borderRadius: attributes.borderRadius,
				boxShadow: attributes.boxShadow,
				border: attributes.borderWidth && attributes.borderWidth !== '0px'
					? `${attributes.borderWidth} solid ${attributes.borderColor || '#dcdcde'}`
					: '1px dashed #dcdcde',
			};
			const blockProps = useBlockProps( {
				className: 'gm-editor-card',
				style: cardStyle,
			} );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Card Style', 'ajax-filter-posts' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Background Color', 'ajax-filter-posts' ),
							value: attributes.backgroundColor,
							onChange: function ( val ) {
								setAttributes( { backgroundColor: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Padding', 'ajax-filter-posts' ),
							value: attributes.padding,
							onChange: function ( val ) {
								setAttributes( { padding: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Border Radius', 'ajax-filter-posts' ),
							value: attributes.borderRadius,
							onChange: function ( val ) {
								setAttributes( { borderRadius: val } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( InnerBlocks, {
						allowedBlocks: ALLOWED_CARD_BLOCKS,
						template: [
							[ 'gridmaster/featured-image', { size: 'medium_large' } ],
							[ 'gridmaster/title', { tagName: 'h3' } ],
							[ 'gridmaster/post-date', {} ],
							[ 'gridmaster/excerpt', { length: 20 } ],
							[ 'gridmaster/read-more', { text: __( 'Read More', 'ajax-filter-posts' ) } ],
						],
					} )
				)
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );

	// ==========================================
	// 3. Featured Image (gridmaster/featured-image)
	// ==========================================
	registerBlockType( 'gridmaster/featured-image', {
		apiVersion: 3,
		title: __( 'Featured Image', 'ajax-filter-posts' ),
		icon: 'format-image',
		category: 'theme',
		attributes: {
			size: { type: 'string', default: 'medium_large' },
			borderRadius: { type: 'string', default: '6px' },
			isLink: { type: 'boolean', default: true },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-image' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Image Settings', 'ajax-filter-posts' ) },
						el( SelectControl, {
							label: __( 'Size', 'ajax-filter-posts' ),
							value: attributes.size,
							options: [
								{ label: 'Thumbnail (150x150)', value: 'thumbnail' },
								{ label: 'Medium (300x300)', value: 'medium' },
								{ label: 'Medium Large (768px)', value: 'medium_large' },
								{ label: 'Large (1024px)', value: 'large' },
								{ label: 'Full', value: 'full' },
							],
							onChange: function ( val ) { setAttributes( { size: val } ); },
						} ),
						el( TextControl, {
							label: __( 'Border Radius', 'ajax-filter-posts' ),
							value: attributes.borderRadius,
							onChange: function ( val ) { setAttributes( { borderRadius: val } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Link to Post', 'ajax-filter-posts' ),
							checked: attributes.isLink,
							onChange: function ( val ) { setAttributes( { isLink: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'div', { className: 'gm-placeholder-img' }, el( 'span', { className: 'dashicons dashicons-format-image' } ), ' ' + __( 'Featured Image', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 4. Post Title (gridmaster/title)
	// ==========================================
	registerBlockType( 'gridmaster/title', {
		apiVersion: 3,
		title: __( 'Post Title', 'ajax-filter-posts' ),
		icon: 'heading',
		category: 'theme',
		attributes: {
			tagName: { type: 'string', default: 'h3' },
			fontSize: { type: 'string', default: '20px' },
			color: { type: 'string', default: '' },
			isLink: { type: 'boolean', default: true },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-title' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Title Settings', 'ajax-filter-posts' ) },
						el( SelectControl, {
							label: __( 'HTML Tag', 'ajax-filter-posts' ),
							value: attributes.tagName,
							options: [
								{ label: 'H1', value: 'h1' },
								{ label: 'H2', value: 'h2' },
								{ label: 'H3', value: 'h3' },
								{ label: 'H4', value: 'h4' },
								{ label: 'H5', value: 'h5' },
								{ label: 'H6', value: 'h6' },
								{ label: 'DIV', value: 'div' },
							],
							onChange: function ( val ) { setAttributes( { tagName: val } ); },
						} ),
						el( TextControl, {
							label: __( 'Font Size (e.g. 20px, 1.25rem)', 'ajax-filter-posts' ),
							value: attributes.fontSize,
							onChange: function ( val ) { setAttributes( { fontSize: val } ); },
						} ),
						el( TextControl, {
							label: __( 'Text Color', 'ajax-filter-posts' ),
							value: attributes.color,
							onChange: function ( val ) { setAttributes( { color: val } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Link to Post', 'ajax-filter-posts' ),
							checked: attributes.isLink,
							onChange: function ( val ) { setAttributes( { isLink: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( attributes.tagName, { style: { fontSize: attributes.fontSize, color: attributes.color || 'inherit', margin: 0 } }, __( 'Sample Post Title', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 5. Post Excerpt (gridmaster/excerpt)
	// ==========================================
	registerBlockType( 'gridmaster/excerpt', {
		apiVersion: 3,
		title: __( 'Post Excerpt', 'ajax-filter-posts' ),
		icon: 'excerpt-view',
		category: 'theme',
		attributes: {
			length: { type: 'number', default: 20 },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-excerpt' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Excerpt Settings', 'ajax-filter-posts' ) },
						el( RangeControl, {
							label: __( 'Length (Words)', 'ajax-filter-posts' ),
							value: attributes.length,
							min: 5,
							max: 100,
							onChange: function ( val ) { setAttributes( { length: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'p', { style: { color: '#6b7280', fontSize: '14px', margin: 0 } }, __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 6. Read More Button (gridmaster/read-more)
	// ==========================================
	registerBlockType( 'gridmaster/read-more', {
		apiVersion: 3,
		title: __( 'Read More Button', 'ajax-filter-posts' ),
		icon: 'button',
		category: 'theme',
		attributes: {
			text: { type: 'string', default: __( 'Read More', 'ajax-filter-posts' ) },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-read-more' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Button Settings', 'ajax-filter-posts' ) },
						el( TextControl, {
							label: __( 'Button Text', 'ajax-filter-posts' ),
							value: attributes.text,
							onChange: function ( val ) { setAttributes( { text: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { className: 'gm-editor-btn' }, attributes.text || __( 'Read More', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 7. Post Date (gridmaster/post-date)
	// ==========================================
	registerBlockType( 'gridmaster/post-date', {
		apiVersion: 3,
		title: __( 'Post Date', 'ajax-filter-posts' ),
		icon: 'calendar-alt',
		category: 'theme',
		attributes: {
			format: { type: 'string', default: 'F j, Y' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-date' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Date Settings', 'ajax-filter-posts' ) },
						el( TextControl, {
							label: __( 'Format (PHP date format)', 'ajax-filter-posts' ),
							value: attributes.format,
							onChange: function ( val ) { setAttributes( { format: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { style: { fontSize: '13px', color: '#9ca3af' } }, el( 'span', { className: 'dashicons dashicons-calendar-alt' } ), ' November 4, 2026' )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 8. Post Author (gridmaster/post-author)
	// ==========================================
	registerBlockType( 'gridmaster/post-author', {
		apiVersion: 3,
		title: __( 'Post Author', 'ajax-filter-posts' ),
		icon: 'admin-users',
		category: 'theme',
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-author' } );
			return el(
				'div',
				blockProps,
				el( 'span', { style: { fontSize: '13px', color: '#4b5563' } }, el( 'span', { className: 'dashicons dashicons-admin-users' } ), ' John Doe' )
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 9. Post Terms (gridmaster/post-terms)
	// ==========================================
	registerBlockType( 'gridmaster/post-terms', {
		apiVersion: 3,
		title: __( 'Post Terms / Categories', 'ajax-filter-posts' ),
		icon: 'tag',
		category: 'theme',
		attributes: {
			taxonomy: { type: 'string', default: 'category' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-terms' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Taxonomy Settings', 'ajax-filter-posts' ) },
						el( TextControl, {
							label: __( 'Taxonomy Slug', 'ajax-filter-posts' ),
							value: attributes.taxonomy,
							onChange: function ( val ) { setAttributes( { taxonomy: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { className: 'gm-editor-term-badge' }, 'Design' ),
					' ',
					el( 'span', { className: 'gm-editor-term-badge' }, 'WordPress' )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 10. Post Meta / Custom Field (gridmaster/post-meta)
	// ==========================================
	registerBlockType( 'gridmaster/post-meta', {
		apiVersion: 3,
		title: __( 'Custom Field / Meta', 'ajax-filter-posts' ),
		icon: 'admin-generic',
		category: 'theme',
		attributes: {
			metaKey: { type: 'string', default: '' },
			prefix: { type: 'string', default: '' },
			suffix: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-meta' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Custom Field Settings', 'ajax-filter-posts' ) },
						el( TextControl, {
							label: __( 'Meta Key', 'ajax-filter-posts' ),
							value: attributes.metaKey,
							onChange: function ( val ) { setAttributes( { metaKey: val } ); },
						} ),
						el( TextControl, {
							label: __( 'Prefix', 'ajax-filter-posts' ),
							value: attributes.prefix,
							onChange: function ( val ) { setAttributes( { prefix: val } ); },
						} ),
						el( TextControl, {
							label: __( 'Suffix', 'ajax-filter-posts' ),
							value: attributes.suffix,
							onChange: function ( val ) { setAttributes( { suffix: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { style: { fontSize: '13px', color: '#6b7280' } }, attributes.metaKey ? attributes.metaKey + ': sample value' : __( 'Custom Field (set key in panel)', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 11. Comments Count (gridmaster/comments-count)
	// ==========================================
	registerBlockType( 'gridmaster/comments-count', {
		apiVersion: 3,
		title: __( 'Comments Count', 'ajax-filter-posts' ),
		icon: 'admin-comments',
		category: 'theme',
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-comments' } );
			return el(
				'div',
				blockProps,
				el( 'span', { style: { fontSize: '13px', color: '#6b7280' } }, el( 'span', { className: 'dashicons dashicons-admin-comments' } ), ' 3 Comments' )
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// Pro Blocks (Editor Previews)
	// ==========================================
	registerBlockType( 'gridmaster-pro/product-price', {
		apiVersion: 3,
		title: __( 'Product Price (Pro)', 'ajax-filter-posts' ),
		icon: 'cart',
		category: 'widgets',
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-price' } );
			return el(
				'div',
				blockProps,
				el( 'span', { style: { fontSize: '16px', fontWeight: 'bold', color: '#059669' } }, '$49.00' )
			);
		},
		save: function () { return null; },
	} );

	registerBlockType( 'gridmaster-pro/add-to-cart', {
		apiVersion: 3,
		title: __( 'Add to Cart (Pro)', 'ajax-filter-posts' ),
		icon: 'cart',
		category: 'widgets',
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-cart' } );
			return el(
				'div',
				blockProps,
				el( 'button', { type: 'button', className: 'gm-btn', style: { padding: '8px 16px', background: '#2563eb', color: '#fff', borderRadius: '4px', border: 'none', cursor: 'default' } }, __( 'Add to Cart', 'ajax-filter-posts' ) )
			);
		},
		save: function () { return null; },
	} );

	registerBlockType( 'gridmaster-pro/badge', {
		apiVersion: 3,
		title: __( 'Badge (Pro)', 'ajax-filter-posts' ),
		icon: 'tag',
		category: 'widgets',
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-badge' } );
			return el(
				'div',
				blockProps,
				el( 'span', { style: { display: 'inline-block', padding: '3px 8px', background: '#ef4444', color: '#fff', borderRadius: '9999px', fontSize: '11px', fontWeight: 'bold' } }, 'HOT' )
			);
		},
		save: function () { return null; },
	} );

} )( window.wp );

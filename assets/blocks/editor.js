( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const __ = wp.i18n.__;

	const data = window.gridmasterBlockData || {};
	const grids = data.grids || [ { label: __( 'Select a grid', 'ajax-filter-posts' ), value: '' } ];
	const filters = data.filters || [ { label: __( 'Style 1 (Default)', 'ajax-filter-posts' ), value: 'default' } ];

	function fieldEdit( label, className, controls ) {
		return function ( props ) {
			const blockProps = useBlockProps( { className: className } );
			return el(
				Fragment,
				null,
				controls ? controls( props ) : null,
				el( 'div', blockProps, label )
			);
		};
	}

	registerBlockType( 'gridmaster/grid', {
		apiVersion: 3,
		title: __( 'GridMaster Grid', 'ajax-filter-posts' ),
		description: __( 'Insert a reusable GridMaster Gutenberg grid template.', 'ajax-filter-posts' ),
		icon: 'grid-view',
		category: 'widgets',
		attributes: {
			gridRef: { type: 'string', default: '' },
			templateId: { type: 'number', default: 0 },
			filterStyle: { type: 'string', default: 'default' },
			postType: { type: 'string', default: 'post' },
			postsPerPage: { type: 'number', default: 9 },
			orderby: { type: 'string', default: 'date' },
			order: { type: 'string', default: 'DESC' },
			taxonomy: { type: 'string', default: 'category' },
			terms: { type: 'string', default: '' },
			showFilter: { type: 'boolean', default: true },
			paginationType: { type: 'string', default: '' },
			infiniteScroll: { type: 'boolean', default: false },
			responsiveColumns: { type: 'object', default: { lg: 3 } },
			rowGap: { type: 'number', default: 30 },
			columnGap: { type: 'number', default: 30 },
		},
		edit: function ( props ) {
			const attrs = props.attributes;
			const setAttributes = props.setAttributes;
			const blockProps = useBlockProps( { className: 'gm-grid-editor-preview' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'GridMaster', 'ajax-filter-posts' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Grid', 'ajax-filter-posts' ),
							value: attrs.gridRef || ( attrs.templateId ? 'template:' + attrs.templateId : '' ),
							options: grids,
							onChange: function ( value ) {
								const nextAttrs = { gridRef: value };
								if ( value && value.indexOf( 'template:' ) === 0 ) {
									nextAttrs.templateId = parseInt( value.replace( 'template:', '' ), 10 ) || 0;
								} else {
									nextAttrs.templateId = 0;
								}
								setAttributes( nextAttrs );
							},
						} ),
						el( SelectControl, {
							label: __( 'Filter', 'ajax-filter-posts' ),
							value: attrs.filterStyle,
							options: filters,
							onChange: function ( value ) {
								setAttributes( { filterStyle: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					attrs.gridRef || attrs.templateId
						? el( ServerSideRender, { block: 'gridmaster/grid', attributes: attrs } )
						: el( 'div', { className: 'gm-grid-placeholder' }, __( 'Select a GridMaster grid from the block settings.', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/post-template', {
		apiVersion: 3,
		title: __( 'GM Post Template', 'ajax-filter-posts' ),
		description: __( 'Group the blocks used for each post card.', 'ajax-filter-posts' ),
		icon: 'screenoptions',
		category: 'widgets',
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-post-template-editor' } );
			return el(
				'div',
				blockProps,
				el( wp.blockEditor.InnerBlocks, {
					template: [
						[ 'gridmaster/featured-image' ],
						[ 'gridmaster/title' ],
						[ 'gridmaster/excerpt' ],
						[ 'gridmaster/read-more' ],
					],
				} )
			);
		},
		save: function () {
			return el( wp.blockEditor.InnerBlocks.Content );
		},
	} );

	registerBlockType( 'gridmaster/filter', {
		apiVersion: 3,
		title: __( 'GM Filter', 'ajax-filter-posts' ),
		description: __( 'Filter settings are controlled by the inserted GridMaster Grid block.', 'ajax-filter-posts' ),
		icon: 'filter',
		category: 'widgets',
		edit: fieldEdit( __( 'Grid Filter', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/featured-image', {
		apiVersion: 3,
		title: __( 'GM Featured Image', 'ajax-filter-posts' ),
		icon: 'format-image',
		category: 'widgets',
		edit: fieldEdit( __( 'Featured Image', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/title', {
		apiVersion: 3,
		title: __( 'GM Post Title', 'ajax-filter-posts' ),
		icon: 'heading',
		category: 'widgets',
		attributes: { tagName: { type: 'string', default: 'h3' }, isLink: { type: 'boolean', default: true } },
		edit: fieldEdit( __( 'Post Title', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/excerpt', {
		apiVersion: 3,
		title: __( 'GM Excerpt', 'ajax-filter-posts' ),
		icon: 'excerpt-view',
		category: 'widgets',
		edit: fieldEdit( __( 'Post Excerpt', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/terms', {
		apiVersion: 3,
		title: __( 'GM Terms', 'ajax-filter-posts' ),
		icon: 'tag',
		category: 'widgets',
		edit: fieldEdit( __( 'Post Terms', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/date', {
		apiVersion: 3,
		title: __( 'GM Date', 'ajax-filter-posts' ),
		icon: 'calendar-alt',
		category: 'widgets',
		edit: fieldEdit( __( 'Post Date', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/author', {
		apiVersion: 3,
		title: __( 'GM Author', 'ajax-filter-posts' ),
		icon: 'admin-users',
		category: 'widgets',
		edit: fieldEdit( __( 'Post Author', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/comments', {
		apiVersion: 3,
		title: __( 'GM Comments', 'ajax-filter-posts' ),
		icon: 'admin-comments',
		category: 'widgets',
		edit: fieldEdit( __( 'Comment Count', 'ajax-filter-posts' ), 'gm-field-placeholder' ),
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'gridmaster/read-more', {
		apiVersion: 3,
		title: __( 'GM Read More', 'ajax-filter-posts' ),
		icon: 'button',
		category: 'widgets',
		attributes: { text: { type: 'string', default: '' } },
		edit: function ( props ) {
			const blockProps = useBlockProps( { className: 'gm-field-placeholder' } );
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Button', 'ajax-filter-posts' ) },
						el( TextControl, {
							label: __( 'Text', 'ajax-filter-posts' ),
							value: props.attributes.text,
							onChange: function ( value ) {
								props.setAttributes( { text: value } );
							},
						} )
					)
				),
				el( 'div', blockProps, props.attributes.text || __( 'Read More', 'ajax-filter-posts' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );

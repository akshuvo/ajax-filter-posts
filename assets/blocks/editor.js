/**
 * GridMaster 2.0 — Gutenberg Block Editor Definitions
 */
( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState } = wp.element;
	const { InspectorControls, useBlockProps, InnerBlocks } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl, SelectControl, ToggleControl, RangeControl, ColorPalette } = wp.components;
	const { __, sprintf } = wp.i18n;

	const blockData = window.gridmasterBlockData || {};
	const grids = blockData.grids || [ { label: __( 'Select a Grid', 'ajax-filter-posts' ), value: 0 } ];

	// Helper: parse string with 'px' into integer safely
	const parsePx = ( val, defaultVal ) => {
		const num = parseInt( val, 10 );
		return Number.isFinite( num ) ? num : defaultVal;
	};

	// Helper: clean UI Color control with label and Gutenberg ColorPalette
	const renderColorControl = ( label, value, onChange ) => {
		return el(
			'div',
			{ className: 'components-base-control gm-editor-color-field', style: { marginBottom: '16px' } },
			el( 'label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '8px', fontWeight: '500', fontSize: '11px', textTransform: 'uppercase', color: '#1e1e1e' } }, label ),
			el( ColorPalette, {
				value: value,
				onChange: ( val ) => onChange( val || '' ),
			} )
		);
	};

	// Helper: Format a JavaScript Date according to PHP date() format string tokens
	const formatPhpDate = ( formatStr, date = new Date( 2026, 10, 5, 14, 30 ) ) => {
		if ( ! formatStr || typeof formatStr !== 'string' ) {
			return '';
		}
		const monthsFull = [
			__( 'January', 'ajax-filter-posts' ),
			__( 'February', 'ajax-filter-posts' ),
			__( 'March', 'ajax-filter-posts' ),
			__( 'April', 'ajax-filter-posts' ),
			__( 'May', 'ajax-filter-posts' ),
			__( 'June', 'ajax-filter-posts' ),
			__( 'July', 'ajax-filter-posts' ),
			__( 'August', 'ajax-filter-posts' ),
			__( 'September', 'ajax-filter-posts' ),
			__( 'October', 'ajax-filter-posts' ),
			__( 'November', 'ajax-filter-posts' ),
			__( 'December', 'ajax-filter-posts' ),
		];
		const monthsShort = [
			__( 'Jan', 'ajax-filter-posts' ),
			__( 'Feb', 'ajax-filter-posts' ),
			__( 'Mar', 'ajax-filter-posts' ),
			__( 'Apr', 'ajax-filter-posts' ),
			__( 'May', 'ajax-filter-posts' ),
			__( 'Jun', 'ajax-filter-posts' ),
			__( 'Jul', 'ajax-filter-posts' ),
			__( 'Aug', 'ajax-filter-posts' ),
			__( 'Sep', 'ajax-filter-posts' ),
			__( 'Oct', 'ajax-filter-posts' ),
			__( 'Nov', 'ajax-filter-posts' ),
			__( 'Dec', 'ajax-filter-posts' ),
		];
		const daysFull = [
			__( 'Sunday', 'ajax-filter-posts' ),
			__( 'Monday', 'ajax-filter-posts' ),
			__( 'Tuesday', 'ajax-filter-posts' ),
			__( 'Wednesday', 'ajax-filter-posts' ),
			__( 'Thursday', 'ajax-filter-posts' ),
			__( 'Friday', 'ajax-filter-posts' ),
			__( 'Saturday', 'ajax-filter-posts' ),
		];
		const daysShort = [
			__( 'Sun', 'ajax-filter-posts' ),
			__( 'Mon', 'ajax-filter-posts' ),
			__( 'Tue', 'ajax-filter-posts' ),
			__( 'Wed', 'ajax-filter-posts' ),
			__( 'Thu', 'ajax-filter-posts' ),
			__( 'Fri', 'ajax-filter-posts' ),
			__( 'Sat', 'ajax-filter-posts' ),
		];

		const day = date.getDate();
		const month = date.getMonth();
		const year = date.getFullYear();
		const dayOfWeek = date.getDay();
		const hours24 = date.getHours();
		const hours12 = hours24 % 12 || 12;
		const minutes = date.getMinutes();
		const seconds = date.getSeconds();

		const getOrdinal = ( n ) => {
			const s = [ 'th', 'st', 'nd', 'rd' ];
			const v = n % 100;
			return s[ ( v - 20 ) % 10 ] || s[ v ] || s[ 0 ];
		};

		let result = '';
		let escaped = false;

		for ( let i = 0; i < formatStr.length; i++ ) {
			const char = formatStr[ i ];
			if ( escaped ) {
				result += char;
				escaped = false;
				continue;
			}
			if ( char === '\\' ) {
				escaped = true;
				continue;
			}
			switch ( char ) {
				case 'd': result += String( day ).padStart( 2, '0' ); break;
				case 'j': result += day; break;
				case 'S': result += getOrdinal( day ); break;
				case 'D': result += daysShort[ dayOfWeek ]; break;
				case 'l': result += daysFull[ dayOfWeek ]; break;
				case 'F': result += monthsFull[ month ]; break;
				case 'M': result += monthsShort[ month ]; break;
				case 'm': result += String( month + 1 ).padStart( 2, '0' ); break;
				case 'n': result += month + 1; break;
				case 'Y': result += year; break;
				case 'y': result += String( year ).slice( -2 ); break;
				case 'a': result += hours24 < 12 ? 'am' : 'pm'; break;
				case 'A': result += hours24 < 12 ? 'AM' : 'PM'; break;
				case 'g': result += hours12; break;
				case 'G': result += hours24; break;
				case 'h': result += String( hours12 ).padStart( 2, '0' ); break;
				case 'H': result += String( hours24 ).padStart( 2, '0' ); break;
				case 'i': result += String( minutes ).padStart( 2, '0' ); break;
				case 's': result += String( seconds ).padStart( 2, '0' ); break;
				default:  result += char; break;
			}
		}
		return result;
	};

	// Common standard PHP Date Format presets
	const DATE_FORMAT_PRESETS = [
		{ value: 'F j, Y', label: 'F j, Y — November 5, 2026' },
		{ value: 'M j, Y', label: 'M j, Y — Nov 5, 2026' },
		{ value: 'M d, Y', label: 'M d, Y — Nov 05, 2026' },
		{ value: 'm/d/Y',  label: 'm/d/Y — 11/05/2026' },
		{ value: 'd/m/Y',  label: 'd/m/Y — 05/11/2026' },
		{ value: 'Y-m-d',  label: 'Y-m-d — 2026-11-05' },
		{ value: 'jS F Y', label: 'jS F Y — 5th November 2026' },
		{ value: 'l, F j, Y', label: 'l, F j, Y — Thursday, November 5, 2026' },
		{ value: '__site_default__', label: __( 'Site Default (WordPress Setting)', 'ajax-filter-posts' ) },
		{ value: 'custom', label: __( 'Custom Format…', 'ajax-filter-posts' ) },
	];

	// Component: Clean, intuitive Date Format Selector with dropdown presets, custom format input, live preview badge, and collapsible token guide
	const DateFormatControl = ( { label, value, onChange } ) => {
		const isKnownPreset = value === '' || DATE_FORMAT_PRESETS.some( ( p ) => p.value !== 'custom' && p.value === value );
		const [ isCustomMode, setIsCustomMode ] = useState( ! isKnownPreset );
		const [ showGuide, setShowGuide ] = useState( false );

		const selectedPreset = ( isCustomMode || ! isKnownPreset ) ? 'custom' : ( value === '' ? '__site_default__' : value );
		const displayLabel = label || __( 'Format (PHP date format)', 'ajax-filter-posts' );
		const sampleFormatted = formatPhpDate( value || 'F j, Y' );

		return el(
			'div',
			{ className: 'gm-date-format-control' },
			el( SelectControl, {
				label: displayLabel,
				value: selectedPreset,
				options: DATE_FORMAT_PRESETS,
				onChange: ( val ) => {
					if ( val === 'custom' ) {
						setIsCustomMode( true );
					} else if ( val === '__site_default__' ) {
						setIsCustomMode( false );
						onChange( '' );
					} else {
						setIsCustomMode( false );
						onChange( val );
					}
				},
			} ),
			( isCustomMode || ! isKnownPreset ) &&
				el( TextControl, {
					label: __( 'Custom Format String', 'ajax-filter-posts' ),
					value: value || '',
					placeholder: 'e.g. F j, Y \a\t g:i a',
					help: __( 'Enter custom PHP date characters (e.g. Y/m/d, F jS Y).', 'ajax-filter-posts' ),
					onChange: ( val ) => onChange( val ),
				} ),
			el(
				'div',
				{ className: 'gm-date-preview-badge' },
				el( 'span', { className: 'gm-date-preview-label' }, __( 'Preview', 'ajax-filter-posts' ) ),
				el( 'strong', { className: 'gm-date-preview-value' }, sampleFormatted || ( value === '' ? 'November 5, 2026 (' + __( 'Site default', 'ajax-filter-posts' ) + ')' : '—' ) )
			),
			el(
				'button',
				{
					type: 'button',
					className: 'gm-date-guide-toggle-btn',
					onClick: () => setShowGuide( ! showGuide ),
					'aria-expanded': showGuide,
				},
				el( 'span', { className: `dashicons ${ showGuide ? 'dashicons-arrow-up-alt2' : 'dashicons-info-outline' }` } ),
				showGuide
					? __( 'Hide PHP Date Format Guide', 'ajax-filter-posts' )
					: __( 'PHP Date Format Guide & Cheat Sheet', 'ajax-filter-posts' )
			),
			showGuide &&
				el(
					'div',
					{ className: 'gm-date-guide-box' },
					el( 'div', { className: 'gm-date-guide-title' }, __( 'PHP Date Formatting Cheat Sheet', 'ajax-filter-posts' ) ),
					el(
						'table',
						{ className: 'gm-date-guide-table' },
						el(
							'thead',
							null,
							el(
								'tr',
								null,
								el( 'th', null, __( 'Token', 'ajax-filter-posts' ) ),
								el( 'th', null, __( 'Meaning', 'ajax-filter-posts' ) ),
								el( 'th', null, __( 'Example', 'ajax-filter-posts' ) )
							)
						),
						el(
							'tbody',
							null,
							[
								{ token: 'j', desc: __( 'Day of month without zeros', 'ajax-filter-posts' ), ex: '5, 28' },
								{ token: 'd', desc: __( 'Day of month with 2 digits', 'ajax-filter-posts' ), ex: '05, 28' },
								{ token: 'S', desc: __( 'English ordinal suffix', 'ajax-filter-posts' ), ex: 'th, nd, rd' },
								{ token: 'F', desc: __( 'Full month name', 'ajax-filter-posts' ), ex: 'November' },
								{ token: 'M', desc: __( '3-letter month abbreviation', 'ajax-filter-posts' ), ex: 'Nov' },
								{ token: 'm', desc: __( 'Numeric month with 2 digits', 'ajax-filter-posts' ), ex: '11, 01' },
								{ token: 'n', desc: __( 'Numeric month without zeros', 'ajax-filter-posts' ), ex: '11, 1' },
								{ token: 'Y', desc: __( '4-digit year', 'ajax-filter-posts' ), ex: '2026' },
								{ token: 'y', desc: __( '2-digit year', 'ajax-filter-posts' ), ex: '26' },
								{ token: 'l', desc: __( 'Full weekday name (lowercase L)', 'ajax-filter-posts' ), ex: 'Thursday' },
								{ token: 'D', desc: __( '3-letter weekday abbreviation', 'ajax-filter-posts' ), ex: 'Thu' },
								{ token: 'g:i a', desc: __( '12-hr time with am/pm', 'ajax-filter-posts' ), ex: '2:30 pm' },
								{ token: 'H:i', desc: __( '24-hr time', 'ajax-filter-posts' ), ex: '14:30' },
							].map( ( row ) =>
								el(
									'tr',
									{ key: row.token },
									el( 'td', null, el( 'span', { className: 'gm-date-guide-token' }, row.token ) ),
									el( 'td', null, row.desc ),
									el( 'td', { className: 'gm-date-guide-ex' }, row.ex )
								)
							)
						)
					),
					el(
						'div',
						{ className: 'gm-date-guide-tip' },
						__( 'Tip: Prepend backslash to escape letters (e.g. \\a\\t for literal "at").', 'ajax-filter-posts' )
					),
					el(
						'div',
						{ className: 'gm-date-guide-links' },
						el(
							'a',
							{
								href: 'https://wordpress.org/documentation/article/customize-date-and-time-format/',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__( 'WordPress Date & Time Codex Guide ↗', 'ajax-filter-posts' )
						),
						el(
							'a',
							{
								href: 'https://www.php.net/manual/en/datetime.format.php',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__( 'Official PHP date() Manual ↗', 'ajax-filter-posts' )
						)
					)
				)
		);
	};

	// Allowed blocks inside the post card
	const defaultAllowedBlocks = [
		'gridmaster/featured-image',
		'gridmaster/title',
		'gridmaster/excerpt',
		'gridmaster/read-more',
		'gridmaster/post-date',
		'gridmaster/modified-date',
		'gridmaster/reading-time',
		'gridmaster/post-author',
		'gridmaster/author-avatar',
		'gridmaster/post-terms',
		'gridmaster/post-format-icon',
		'gridmaster/social-share',
		'gridmaster/badge',
		'gridmaster/shortcode',
		'gridmaster/html',
		'gridmaster/post-meta',
		'gridmaster/comments-count',
		'gridmaster-pro/product-price',
		'gridmaster-pro/add-to-cart',
		'gridmaster-pro/badge',
		'gridmaster-pro/product-rating',
		'gridmaster-pro/stock-status',
		'gridmaster-pro/sale-countdown',
		'gridmaster-pro/quick-view',
		'gridmaster-pro/video-lightbox',
		'gridmaster-pro/bookmark-button',
		'core/group',
		'core/columns',
		'core/column',
		'core/spacer',
		'core/separator',
		'core/paragraph',
		'core/heading',
		'core/shortcode',
		'core/html',
	];

	const getAllowedCardBlocks = () => {
		return wp.hooks && wp.hooks.applyFilters
			? wp.hooks.applyFilters( 'gridmaster.allowedCardBlocks', defaultAllowedBlocks )
			: defaultAllowedBlocks;
	};

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
			layoutMode: { type: 'string', default: 'vertical' },
			mediaWidth: { type: 'string', default: '35%' },
			minHeight: { type: 'string', default: '' },
			hoverEffect: { type: 'string', default: 'lift' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const isHorizontal = attributes.layoutMode === 'horizontal' || attributes.layoutMode === 'horizontal-reverse';
			const isOverlay = attributes.layoutMode === 'overlay';

			const cardStyle = {
				backgroundColor: attributes.backgroundColor,
				padding: attributes.padding,
				borderRadius: attributes.borderRadius,
				boxShadow: attributes.boxShadow,
				border: attributes.borderWidth && attributes.borderWidth !== '0px'
					? `${attributes.borderWidth} solid ${attributes.borderColor || '#dcdcde'}`
					: '1px dashed #dcdcde',
				display: 'flex',
				flexDirection: attributes.layoutMode === 'horizontal' ? 'row' : ( attributes.layoutMode === 'horizontal-reverse' ? 'row-reverse' : 'column' ),
				minHeight: attributes.minHeight || ( isOverlay ? '320px' : 'auto' ),
				position: 'relative',
				'--gm-card-media-width': attributes.mediaWidth || '35%',
			};
			const blockProps = useBlockProps( {
				className: 'gm-editor-card gm-editor-card--' + ( attributes.layoutMode || 'vertical' ),
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
						{ title: __( 'Card Layout & Orientation', 'ajax-filter-posts' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Layout Mode', 'ajax-filter-posts' ),
							value: attributes.layoutMode || 'vertical',
							options: [
								{ label: __( 'Vertical (Standard Card)', 'ajax-filter-posts' ), value: 'vertical' },
								{ label: __( 'Horizontal (Media Left — List)', 'ajax-filter-posts' ), value: 'horizontal' },
								{ label: __( 'Horizontal Reverse (Media Right)', 'ajax-filter-posts' ), value: 'horizontal-reverse' },
								{ label: __( 'Overlay (Hero Cover Card)', 'ajax-filter-posts' ), value: 'overlay' },
							],
							onChange: ( val ) => setAttributes( { layoutMode: val } ),
						} ),
						isHorizontal &&
							el( RangeControl, {
								label: __( 'Media Column Width (%)', 'ajax-filter-posts' ),
								value: parseInt( attributes.mediaWidth, 10 ) || 35,
								min: 20,
								max: 60,
								step: 1,
								onChange: ( val ) => setAttributes( { mediaWidth: val + '%' } ),
							} ),
						isOverlay &&
							el( RangeControl, {
								label: __( 'Minimum Height (px)', 'ajax-filter-posts' ),
								value: parsePx( attributes.minHeight, 320 ),
								min: 200,
								max: 600,
								step: 10,
								onChange: ( val ) => setAttributes( { minHeight: val + 'px' } ),
							} ),
						el( SelectControl, {
							label: __( 'Hover Effect', 'ajax-filter-posts' ),
							value: attributes.hoverEffect || 'lift',
							options: [
								{ label: __( 'Lift on Hover', 'ajax-filter-posts' ), value: 'lift' },
								{ label: __( 'Zoom Image on Hover', 'ajax-filter-posts' ), value: 'zoom' },
								{ label: __( 'Glow / Ambient Shadow', 'ajax-filter-posts' ), value: 'glow' },
								{ label: __( 'None', 'ajax-filter-posts' ), value: 'none' },
							],
							onChange: ( val ) => setAttributes( { hoverEffect: val } ),
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Card Style', 'ajax-filter-posts' ), initialOpen: false },
						renderColorControl(
							__( 'Background Color', 'ajax-filter-posts' ),
							attributes.backgroundColor,
							( val ) => setAttributes( { backgroundColor: val || '#ffffff' } )
						),
						el( RangeControl, {
							label: __( 'Padding (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.padding, 20 ),
							min: 0,
							max: 60,
							step: 1,
							onChange: ( val ) => setAttributes( { padding: val + 'px' } ),
						} ),
						el( RangeControl, {
							label: __( 'Corner Radius (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.borderRadius, 8 ),
							min: 0,
							max: 40,
							step: 1,
							onChange: ( val ) => setAttributes( { borderRadius: val + 'px' } ),
						} ),
						el( SelectControl, {
							label: __( 'Box Shadow', 'ajax-filter-posts' ),
							value: attributes.boxShadow || 'none',
							options: [
								{ label: __( 'None', 'ajax-filter-posts' ), value: 'none' },
								{ label: __( 'Subtle (Low)', 'ajax-filter-posts' ), value: '0 2px 4px rgba(0,0,0,0.05)' },
								{ label: __( 'Medium', 'ajax-filter-posts' ), value: '0 4px 12px rgba(0,0,0,0.08)' },
								{ label: __( 'Elevated (High)', 'ajax-filter-posts' ), value: '0 10px 25px rgba(0,0,0,0.1)' },
							],
							onChange: ( val ) => setAttributes( { boxShadow: val } ),
						} ),
						el( RangeControl, {
							label: __( 'Border Width (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.borderWidth, 0 ),
							min: 0,
							max: 10,
							step: 1,
							onChange: ( val ) => setAttributes( { borderWidth: val + 'px' } ),
						} ),
						parsePx( attributes.borderWidth, 0 ) > 0 &&
							renderColorControl(
								__( 'Border Color', 'ajax-filter-posts' ),
								attributes.borderColor || '#dcdcde',
								( val ) => setAttributes( { borderColor: val || '#dcdcde' } )
							)
					)
				),
				el(
					'div',
					blockProps,
					el( InnerBlocks, {
						allowedBlocks: getAllowedCardBlocks(),
						templateLock: window.gridmasterBuilderData ? false : undefined,
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
			aspectRatio: { type: 'string', default: '16/9' },
			objectFit: { type: 'string', default: 'cover' },
			borderRadius: { type: 'string', default: '6px' },
			isLink: { type: 'boolean', default: true },
			zoomOnHover: { type: 'boolean', default: false },
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
							label: __( 'Aspect Ratio', 'ajax-filter-posts' ),
							value: attributes.aspectRatio || '16/9',
							options: [
								{ label: __( '16:9 Landscape', 'ajax-filter-posts' ), value: '16/9' },
								{ label: __( '4:3 Standard', 'ajax-filter-posts' ), value: '4/3' },
								{ label: __( '1:1 Square', 'ajax-filter-posts' ), value: '1/1' },
								{ label: __( '3:2 Classic Photo', 'ajax-filter-posts' ), value: '3/2' },
								{ label: __( '21:9 Cinematic', 'ajax-filter-posts' ), value: '21/9' },
								{ label: __( 'Original / Auto', 'ajax-filter-posts' ), value: 'auto' },
							],
							onChange: function ( val ) { setAttributes( { aspectRatio: val } ); },
						} ),
						el( SelectControl, {
							label: __( 'Object Fit', 'ajax-filter-posts' ),
							value: attributes.objectFit || 'cover',
							options: [
								{ label: __( 'Cover (Crop to fill)', 'ajax-filter-posts' ), value: 'cover' },
								{ label: __( 'Contain (Show entire image)', 'ajax-filter-posts' ), value: 'contain' },
							],
							onChange: function ( val ) { setAttributes( { objectFit: val } ); },
						} ),
						el( SelectControl, {
							label: __( 'Resolution / Size', 'ajax-filter-posts' ),
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
						el( RangeControl, {
							label: __( 'Border Radius (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.borderRadius, 6 ),
							min: 0,
							max: 40,
							step: 1,
							onChange: function ( val ) { setAttributes( { borderRadius: val + 'px' } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Link to Post', 'ajax-filter-posts' ),
							checked: attributes.isLink,
							onChange: function ( val ) { setAttributes( { isLink: val } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Zoom on Card Hover', 'ajax-filter-posts' ),
							checked: !! attributes.zoomOnHover,
							onChange: function ( val ) { setAttributes( { zoomOnHover: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{
							className: 'gm-placeholder-img',
							style: {
								borderRadius: attributes.borderRadius,
								aspectRatio: attributes.aspectRatio && attributes.aspectRatio !== 'auto' ? attributes.aspectRatio : 'auto',
								height: attributes.aspectRatio === 'auto' ? '160px' : 'auto',
							},
						},
						el( 'span', { className: 'dashicons dashicons-format-image' } ),
						' ' + __( 'Featured Image', 'ajax-filter-posts' ) + ( attributes.aspectRatio ? ` (${attributes.aspectRatio})` : '' )
					)
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
						el( RangeControl, {
							label: __( 'Font Size (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.fontSize, 20 ),
							min: 12,
							max: 64,
							step: 1,
							onChange: function ( val ) { setAttributes( { fontSize: val ? val + 'px' : '' } ); },
						} ),
						renderColorControl(
							__( 'Text Color', 'ajax-filter-posts' ),
							attributes.color,
							( val ) => setAttributes( { color: val } )
						),
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
			fontSize: { type: 'string', default: '14px' },
			color: { type: 'string', default: '' },
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
						} ),
						el( RangeControl, {
							label: __( 'Font Size (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.fontSize, 14 ),
							min: 10,
							max: 32,
							onChange: ( val ) => setAttributes( { fontSize: val + 'px' } ),
						} ),
						renderColorControl(
							__( 'Text Color', 'ajax-filter-posts' ),
							attributes.color || '#6b7280',
							( val ) => setAttributes( { color: val } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el( 'p', { style: { color: attributes.color || '#6b7280', fontSize: attributes.fontSize || '14px', margin: 0 } }, __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...', 'ajax-filter-posts' ) )
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
			backgroundColor: { type: 'string', default: '' },
			textColor: { type: 'string', default: '' },
			borderRadius: { type: 'string', default: '' },
			padding: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-read-more' } );

			const btnStyle = {
				display: 'inline-flex',
				alignItems: 'center',
				justifyContent: 'center',
				padding: attributes.padding || '8px 18px',
				backgroundColor: attributes.backgroundColor || '#2563eb',
				color: attributes.textColor || '#ffffff',
				borderRadius: attributes.borderRadius || '6px',
				fontWeight: '500',
				fontSize: '14px',
				textDecoration: 'none',
				lineHeight: '1.4',
			};

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
						} ),
						el( RangeControl, {
							label: __( 'Border Radius (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.borderRadius, 6 ),
							min: 0,
							max: 30,
							onChange: ( val ) => setAttributes( { borderRadius: val + 'px' } ),
						} ),
						renderColorControl(
							__( 'Background Color', 'ajax-filter-posts' ),
							attributes.backgroundColor || '#2563eb',
							( val ) => setAttributes( { backgroundColor: val } )
						),
						renderColorControl(
							__( 'Text Color', 'ajax-filter-posts' ),
							attributes.textColor || '#ffffff',
							( val ) => setAttributes( { textColor: val } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { style: btnStyle }, attributes.text || __( 'Read More', 'ajax-filter-posts' ) )
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
			fontSize: { type: 'string', default: '13px' },
			color: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-date' } );
			const formattedDate = formatPhpDate( attributes.format || 'F j, Y' );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Date Settings', 'ajax-filter-posts' ) },
						el( DateFormatControl, {
							label: __( 'Format (PHP date format)', 'ajax-filter-posts' ),
							value: attributes.format,
							onChange: function ( val ) { setAttributes( { format: val } ); },
						} ),
						el( RangeControl, {
							label: __( 'Font Size (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.fontSize, 13 ),
							min: 10,
							max: 24,
							onChange: ( val ) => setAttributes( { fontSize: val + 'px' } ),
						} ),
						renderColorControl(
							__( 'Date Color', 'ajax-filter-posts' ),
							attributes.color || '#9ca3af',
							( val ) => setAttributes( { color: val } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { style: { fontSize: attributes.fontSize || '13px', color: attributes.color || '#9ca3af' } }, el( 'span', { className: 'dashicons dashicons-calendar-alt' } ), ' ' + ( formattedDate || 'November 5, 2026' ) )
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
	// 12. Reading Time (gridmaster/reading-time)
	// ==========================================
	registerBlockType( 'gridmaster/reading-time', {
		apiVersion: 3,
		title: __( 'Reading Time', 'ajax-filter-posts' ),
		icon: 'clock',
		category: 'theme',
		attributes: {
			wordsPerMinute: { type: 'number', default: 200 },
			prefix: { type: 'string', default: '' },
			suffix: { type: 'string', default: 'min read' },
			showIcon: { type: 'boolean', default: true },
			fontSize: { type: 'string', default: '13px' },
			color: { type: 'string', default: '#6b7280' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-reading-time' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Reading Time Settings', 'ajax-filter-posts' ) },
						el( RangeControl, {
							label: __( 'Words Per Minute', 'ajax-filter-posts' ),
							value: attributes.wordsPerMinute,
							min: 100,
							max: 350,
							step: 10,
							onChange: function ( val ) { setAttributes( { wordsPerMinute: val } ); },
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
						} ),
						el( ToggleControl, {
							label: __( 'Show Clock Icon', 'ajax-filter-posts' ),
							checked: attributes.showIcon,
							onChange: function ( val ) { setAttributes( { showIcon: val } ); },
						} ),
						el( RangeControl, {
							label: __( 'Font Size (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.fontSize, 13 ),
							min: 10,
							max: 32,
							step: 1,
							onChange: function ( val ) { setAttributes( { fontSize: val + 'px' } ); },
						} ),
						renderColorControl(
							__( 'Text Color', 'ajax-filter-posts' ),
							attributes.color,
							( val ) => setAttributes( { color: val } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el(
						'span',
						{ style: { fontSize: attributes.fontSize, color: attributes.color, display: 'inline-flex', alignItems: 'center', gap: '4px' } },
						attributes.showIcon && el( 'span', { className: 'dashicons dashicons-clock' } ),
						( attributes.prefix ? attributes.prefix + ' ' : '' ) + '5 ' + ( attributes.suffix || 'min read' )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 13. Author Avatar (gridmaster/author-avatar)
	// ==========================================
	registerBlockType( 'gridmaster/author-avatar', {
		apiVersion: 3,
		title: __( 'Author Avatar', 'ajax-filter-posts' ),
		icon: 'admin-users',
		category: 'theme',
		attributes: {
			avatarSize: { type: 'number', default: 40 },
			borderRadius: { type: 'string', default: '50%' },
			isLink: { type: 'boolean', default: true },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-author-avatar' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Avatar Settings', 'ajax-filter-posts' ) },
						el( RangeControl, {
							label: __( 'Avatar Size (px)', 'ajax-filter-posts' ),
							value: attributes.avatarSize,
							min: 24,
							max: 120,
							step: 2,
							onChange: function ( val ) { setAttributes( { avatarSize: val } ); },
						} ),
						el( SelectControl, {
							label: __( 'Border Radius', 'ajax-filter-posts' ),
							value: attributes.borderRadius,
							options: [
								{ label: __( 'Circle (50%)', 'ajax-filter-posts' ), value: '50%' },
								{ label: __( 'Rounded (8px)', 'ajax-filter-posts' ), value: '8px' },
								{ label: __( 'Slightly Rounded (4px)', 'ajax-filter-posts' ), value: '4px' },
								{ label: __( 'Square (0px)', 'ajax-filter-posts' ), value: '0px' },
							],
							onChange: function ( val ) { setAttributes( { borderRadius: val } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Link to Author Profile', 'ajax-filter-posts' ),
							checked: attributes.isLink,
							onChange: function ( val ) { setAttributes( { isLink: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{
							style: {
								width: attributes.avatarSize + 'px',
								height: attributes.avatarSize + 'px',
								borderRadius: attributes.borderRadius,
								background: '#e5e7eb',
								display: 'inline-flex',
								alignItems: 'center',
								justifyContent: 'center',
								color: '#6b7280',
							},
						},
						el( 'span', { className: 'dashicons dashicons-admin-users', style: { fontSize: Math.floor( attributes.avatarSize * 0.55 ) + 'px', width: 'auto', height: 'auto' } } )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 14. Modified Date (gridmaster/modified-date)
	// ==========================================
	registerBlockType( 'gridmaster/modified-date', {
		apiVersion: 3,
		title: __( 'Modified Date', 'ajax-filter-posts' ),
		icon: 'update',
		category: 'theme',
		attributes: {
			format: { type: 'string', default: 'F j, Y' },
			prefix: { type: 'string', default: 'Updated on' },
			onlyIfModified: { type: 'boolean', default: true },
			minHoursDiff: { type: 'number', default: 24 },
			fontSize: { type: 'string', default: '13px' },
			color: { type: 'string', default: '#6b7280' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-modified-date' } );
			const formattedDate = formatPhpDate( attributes.format || 'F j, Y' );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Modified Date Settings', 'ajax-filter-posts' ) },
						el( TextControl, {
							label: __( 'Prefix', 'ajax-filter-posts' ),
							value: attributes.prefix,
							onChange: function ( val ) { setAttributes( { prefix: val } ); },
						} ),
						el( DateFormatControl, {
							label: __( 'Format (PHP date format)', 'ajax-filter-posts' ),
							value: attributes.format,
							onChange: function ( val ) { setAttributes( { format: val } ); },
						} ),
						el( RangeControl, {
							label: __( 'Font Size (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.fontSize, 13 ),
							min: 10,
							max: 32,
							step: 1,
							onChange: function ( val ) { setAttributes( { fontSize: val + 'px' } ); },
						} ),
						renderColorControl(
							__( 'Text Color', 'ajax-filter-posts' ),
							attributes.color,
							( val ) => setAttributes( { color: val } )
						),
						el( ToggleControl, {
							label: __( 'Show only if post was updated', 'ajax-filter-posts' ),
							checked: attributes.onlyIfModified,
							onChange: function ( val ) { setAttributes( { onlyIfModified: val } ); },
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'span',
						{ style: { fontSize: attributes.fontSize, color: attributes.color, display: 'inline-flex', alignItems: 'center', gap: '4px' } },
						el( 'span', { className: 'dashicons dashicons-update' } ),
						( attributes.prefix ? attributes.prefix + ' ' : '' ) + ( formattedDate || 'November 5, 2026' )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 15. Post Format Icon (gridmaster/post-format-icon)
	// ==========================================
	registerBlockType( 'gridmaster/post-format-icon', {
		apiVersion: 3,
		title: __( 'Post Format Icon', 'ajax-filter-posts' ),
		icon: 'video-alt3',
		category: 'theme',
		attributes: {
			iconSize: { type: 'number', default: 16 },
			color: { type: 'string', default: '#ffffff' },
			backgroundColor: { type: 'string', default: 'rgba(0,0,0,0.6)' },
			borderRadius: { type: 'string', default: '50%' },
			padding: { type: 'string', default: '8px' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-format-icon' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Format Icon Settings', 'ajax-filter-posts' ) },
						el( RangeControl, {
							label: __( 'Icon Size (px)', 'ajax-filter-posts' ),
							value: attributes.iconSize,
							min: 12,
							max: 36,
							step: 1,
							onChange: function ( val ) { setAttributes( { iconSize: val } ); },
						} ),
						el( RangeControl, {
							label: __( 'Padding (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.padding, 8 ),
							min: 2,
							max: 24,
							step: 1,
							onChange: function ( val ) { setAttributes( { padding: val + 'px' } ), setAttributes( { padding: val + 'px' } ); },
						} ),
						el( RangeControl, {
							label: __( 'Border Radius (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.borderRadius, 50 ),
							min: 0,
							max: 50,
							step: 1,
							onChange: function ( val ) { setAttributes( { borderRadius: val === 50 ? '50%' : val + 'px' } ); },
						} ),
						renderColorControl(
							__( 'Icon Color', 'ajax-filter-posts' ),
							attributes.color,
							( val ) => setAttributes( { color: val } )
						),
						renderColorControl(
							__( 'Background Color', 'ajax-filter-posts' ),
							attributes.backgroundColor,
							( val ) => setAttributes( { backgroundColor: val } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{
							style: {
								display: 'inline-flex',
								alignItems: 'center',
								justifyContent: 'center',
								background: attributes.backgroundColor,
								color: attributes.color,
								borderRadius: attributes.borderRadius,
								padding: attributes.padding,
							},
						},
						el( 'span', { className: 'dashicons dashicons-video-alt3', style: { fontSize: attributes.iconSize + 'px', width: 'auto', height: 'auto' } } )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 16. Social Share (gridmaster/social-share)
	// ==========================================
	registerBlockType( 'gridmaster/social-share', {
		apiVersion: 3,
		title: __( 'Social Share', 'ajax-filter-posts' ),
		icon: 'share',
		category: 'theme',
		attributes: {
			networks: { type: 'array', default: [ 'twitter', 'facebook', 'linkedin', 'copylink' ] },
			iconSize: { type: 'number', default: 14 },
		},
		edit: function () {
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-social-share' } );
			return el(
				'div',
				blockProps,
				el(
					'div',
					{ style: { display: 'inline-flex', gap: '6px' } },
					el( 'span', { style: { width: '28px', height: '28px', borderRadius: '50%', background: '#f3f4f6', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' } }, el( 'span', { className: 'dashicons dashicons-twitter' } ) ),
					el( 'span', { style: { width: '28px', height: '28px', borderRadius: '50%', background: '#f3f4f6', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' } }, el( 'span', { className: 'dashicons dashicons-facebook' } ) ),
					el( 'span', { style: { width: '28px', height: '28px', borderRadius: '50%', background: '#f3f4f6', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' } }, el( 'span', { className: 'dashicons dashicons-admin-links' } ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 17. Custom Badge (gridmaster/badge) Free Core
	// ==========================================
	registerBlockType( 'gridmaster/badge', {
		apiVersion: 3,
		title: __( 'Custom Badge', 'ajax-filter-posts' ),
		icon: 'tag',
		category: 'theme',
		attributes: {
			text: { type: 'string', default: 'Featured' },
			badgeType: { type: 'string', default: 'static' },
			metaKey: { type: 'string', default: '' },
			backgroundColor: { type: 'string', default: '#ef4444' },
			textColor: { type: 'string', default: '#ffffff' },
			fontSize: { type: 'string', default: '11px' },
			borderRadius: { type: 'string', default: '9999px' },
			padding: { type: 'string', default: '3px 8px' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-badge' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Badge Settings', 'ajax-filter-posts' ) },
						el( SelectControl, {
							label: __( 'Badge Type', 'ajax-filter-posts' ),
							value: attributes.badgeType,
							options: [
								{ label: __( 'Static Text', 'ajax-filter-posts' ), value: 'static' },
								{ label: __( 'Dynamic from Custom Meta', 'ajax-filter-posts' ), value: 'meta' },
							],
							onChange: function ( val ) { setAttributes( { badgeType: val } ); },
						} ),
						attributes.badgeType === 'static' && el( TextControl, {
							label: __( 'Badge Text', 'ajax-filter-posts' ),
							value: attributes.text,
							onChange: function ( val ) { setAttributes( { text: val } ); },
						} ),
						attributes.badgeType === 'meta' && el( TextControl, {
							label: __( 'Meta Key', 'ajax-filter-posts' ),
							value: attributes.metaKey,
							onChange: function ( val ) { setAttributes( { metaKey: val } ); },
						} ),
						el( RangeControl, {
							label: __( 'Font Size (px)', 'ajax-filter-posts' ),
							value: parsePx( attributes.fontSize, 11 ),
							min: 9,
							max: 24,
							step: 1,
							onChange: function ( val ) { setAttributes( { fontSize: val + 'px' } ); },
						} ),
						el( SelectControl, {
							label: __( 'Corner Style', 'ajax-filter-posts' ),
							value: attributes.borderRadius,
							options: [
								{ label: __( 'Pill (Fully Rounded)', 'ajax-filter-posts' ), value: '9999px' },
								{ label: __( 'Rounded (6px)', 'ajax-filter-posts' ), value: '6px' },
								{ label: __( 'Slightly Rounded (3px)', 'ajax-filter-posts' ), value: '3px' },
								{ label: __( 'Square (0px)', 'ajax-filter-posts' ), value: '0px' },
							],
							onChange: function ( val ) { setAttributes( { borderRadius: val } ); },
						} ),
						renderColorControl(
							__( 'Background Color', 'ajax-filter-posts' ),
							attributes.backgroundColor,
							( val ) => setAttributes( { backgroundColor: val } )
						),
						renderColorControl(
							__( 'Text Color', 'ajax-filter-posts' ),
							attributes.textColor,
							( val ) => setAttributes( { textColor: val } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el(
						'span',
						{
							style: {
								display: 'inline-block',
								padding: attributes.padding,
								background: attributes.backgroundColor,
								color: attributes.textColor,
								borderRadius: attributes.borderRadius,
								fontSize: attributes.fontSize,
								fontWeight: 'bold',
								lineHeight: '1',
								textTransform: 'uppercase',
							},
						},
						attributes.badgeType === 'meta' ? ( attributes.metaKey ? attributes.metaKey : 'META BADGE' ) : ( attributes.text || 'FEATURED' )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 18. Shortcode Block (gridmaster/shortcode)
	// ==========================================
	registerBlockType( 'gridmaster/shortcode', {
		apiVersion: 3,
		title: __( 'Shortcode', 'ajax-filter-posts' ),
		description: __( 'Insert third-party or custom shortcodes with dynamic post tags.', 'ajax-filter-posts' ),
		icon: 'shortcode',
		category: 'widgets',
		attributes: {
			shortcode: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-shortcode' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Shortcode Settings', 'ajax-filter-posts' ), initialOpen: true },
						el( TextareaControl, {
							label: __( 'Shortcode', 'ajax-filter-posts' ),
							help: __( 'Supported dynamic tags: {post_id}, {id}, {title}, {permalink}', 'ajax-filter-posts' ),
							placeholder: '[my_shortcode id="{post_id}"]',
							value: attributes.shortcode,
							onChange: function ( val ) { setAttributes( { shortcode: val } ); },
							rows: 4,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'gm-editor-shortcode-preview', style: { padding: '10px 14px', background: '#f3f4f6', border: '1px dashed #d1d5db', borderRadius: '4px', fontFamily: 'monospace', fontSize: '13px', color: '#374151' } },
						el( 'span', { className: 'dashicons dashicons-shortcode', style: { marginRight: '6px', color: '#2563eb' } } ),
						attributes.shortcode || __( '[custom_shortcode id="{post_id}"]', 'ajax-filter-posts' )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// 19. Custom HTML Block (gridmaster/html)
	// ==========================================
	registerBlockType( 'gridmaster/html', {
		apiVersion: 3,
		title: __( 'Custom HTML', 'ajax-filter-posts' ),
		description: __( 'Write custom HTML markup with dynamic post tags.', 'ajax-filter-posts' ),
		icon: 'html',
		category: 'widgets',
		attributes: {
			content: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-html' } );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'HTML Settings', 'ajax-filter-posts' ), initialOpen: true },
						el( TextareaControl, {
							label: __( 'Custom HTML Markup', 'ajax-filter-posts' ),
							help: __( 'Supported dynamic tags: {post_id}, {id}, {title}, {permalink}, {date}', 'ajax-filter-posts' ),
							placeholder: '<div class="my-custom-tag">{title}</div>',
							value: attributes.content,
							onChange: function ( val ) { setAttributes( { content: val } ); },
							rows: 6,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'gm-editor-html-preview', style: { padding: '10px 14px', background: '#f8fafc', border: '1px dashed #cbd5e1', borderRadius: '4px', fontFamily: 'monospace', fontSize: '12px', color: '#475569' } },
						el( 'span', { className: 'dashicons dashicons-html', style: { marginRight: '6px', color: '#e11d48' } } ),
						attributes.content ? attributes.content.slice( 0, 80 ) + ( attributes.content.length > 80 ? '...' : '' ) : __( '<div>Custom HTML block...</div>', 'ajax-filter-posts' )
					)
				)
			);
		},
		save: function () { return null; },
	} );

	// ==========================================
	// Pro Blocks (Editor Fallback Previews)
	// Only registered if Pro plugin hasn't registered them
	// ==========================================
	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/product-price' ) ) {
		registerBlockType( 'gridmaster-pro/product-price', {
			apiVersion: 3,
			title: __( 'Product Price (Pro)', 'ajax-filter-posts' ),
			icon: 'cart',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-price' } );
				return el( 'div', blockProps, el( 'span', { style: { fontSize: '16px', fontWeight: 'bold', color: '#059669' } }, '$49.00' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/add-to-cart' ) ) {
		registerBlockType( 'gridmaster-pro/add-to-cart', {
			apiVersion: 3,
			title: __( 'Add to Cart (Pro)', 'ajax-filter-posts' ),
			icon: 'cart',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-cart' } );
				return el( 'div', blockProps, el( 'button', { type: 'button', className: 'gm-btn', style: { padding: '8px 16px', background: '#2563eb', color: '#fff', borderRadius: '4px', border: 'none', cursor: 'default' } }, __( 'Add to Cart', 'ajax-filter-posts' ) ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/badge' ) ) {
		registerBlockType( 'gridmaster-pro/badge', {
			apiVersion: 3,
			title: __( 'Badge (Pro)', 'ajax-filter-posts' ),
			icon: 'tag',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-badge' } );
				return el( 'div', blockProps, el( 'span', { style: { display: 'inline-block', padding: '3px 8px', background: '#ef4444', color: '#fff', borderRadius: '9999px', fontSize: '11px', fontWeight: 'bold' } }, 'HOT' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/product-rating' ) ) {
		registerBlockType( 'gridmaster-pro/product-rating', {
			apiVersion: 3,
			title: __( 'Product Rating (Pro)', 'ajax-filter-posts' ),
			icon: 'star-filled',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-rating' } );
				return el( 'div', blockProps, el( 'span', { style: { color: '#f59e0b', fontSize: '14px' } }, '★★★★★ (5)' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/stock-status' ) ) {
		registerBlockType( 'gridmaster-pro/stock-status', {
			apiVersion: 3,
			title: __( 'Stock Status (Pro)', 'ajax-filter-posts' ),
			icon: 'archive',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-stock' } );
				return el( 'div', blockProps, el( 'span', { style: { color: '#10b981', fontSize: '12px', fontWeight: 'bold' } }, '● In Stock' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/sale-countdown' ) ) {
		registerBlockType( 'gridmaster-pro/sale-countdown', {
			apiVersion: 3,
			title: __( 'Sale Countdown (Pro)', 'ajax-filter-posts' ),
			icon: 'clock',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-countdown' } );
				return el( 'div', blockProps, el( 'span', { style: { background: '#1f2937', color: '#fff', padding: '4px 8px', borderRadius: '4px', fontSize: '12px' } }, '⏳ 03d : 14h : 42m' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/quick-view' ) ) {
		registerBlockType( 'gridmaster-pro/quick-view', {
			apiVersion: 3,
			title: __( 'Quick View (Pro)', 'ajax-filter-posts' ),
			icon: 'visibility',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-quick-view' } );
				return el( 'div', blockProps, el( 'button', { type: 'button', className: 'gm-btn', style: { padding: '6px 12px', background: '#2563eb', color: '#fff', borderRadius: '4px' } }, '👁 Quick View' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/video-lightbox' ) ) {
		registerBlockType( 'gridmaster-pro/video-lightbox', {
			apiVersion: 3,
			title: __( 'Video Lightbox (Pro)', 'ajax-filter-posts' ),
			icon: 'video-alt3',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-video' } );
				return el( 'div', blockProps, el( 'button', { type: 'button', style: { width: '40px', height: '40px', borderRadius: '50%', background: '#e11d48', color: '#fff', border: 'none' } }, '▶' ) );
			},
			save: function () { return null; },
		} );
	}

	if ( ! wp.blocks.getBlockType( 'gridmaster-pro/bookmark-button' ) ) {
		registerBlockType( 'gridmaster-pro/bookmark-button', {
			apiVersion: 3,
			title: __( 'Bookmark / Wishlist (Pro)', 'ajax-filter-posts' ),
			icon: 'heart',
			category: 'widgets',
			edit: function () {
				const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-bookmark' } );
				return el( 'div', blockProps, el( 'span', { style: { color: '#ef4444', fontSize: '18px' } }, '♥' ) );
			},
			save: function () { return null; },
		} );
	}

} )( window.wp );

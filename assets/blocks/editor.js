/**
 * GridMaster 2.0 — Gutenberg Block Editor Definitions
 */
( function ( wp ) {
	'use strict';

	const { registerBlockType, createBlock } = wp.blocks;
	const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;
	const { InspectorControls, BlockControls, useBlockProps, InnerBlocks } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl, SelectControl, ToggleControl, RangeControl, ColorPalette, Button, Popover, Spinner, Placeholder, ToolbarGroup, ToolbarButton, Notice } = wp.components;
	const DisabledComponent = ( wp.blockEditor && wp.blockEditor.Disabled ) || ( wp.components && wp.components.Disabled ) || ( ( { children } ) => el( 'div', { style: { pointerEvents: 'none' } }, children ) );
	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const blockData = window.gridmasterBlockData || {};
	const grids = blockData.grids || [ { label: __( 'Select a Grid', 'ajax-filter-posts' ), value: 0 } ];

	// Helper: parse string with 'px' into integer safely
	const parsePx = ( val, defaultVal ) => {
		const num = parseInt( val, 10 );
		return Number.isFinite( num ) ? num : defaultVal;
	};

	// Helper: detect if hex color is dark
	const isColorDark = ( color ) => {
		if ( ! color || typeof color !== 'string' ) return false;
		const hex = color.trim().replace( '#', '' );
		if ( hex.length === 3 ) {
			const r = parseInt( hex[ 0 ] + hex[ 0 ], 16 );
			const g = parseInt( hex[ 1 ] + hex[ 1 ], 16 );
			const b = parseInt( hex[ 2 ] + hex[ 2 ], 16 );
			return ( r * 299 + g * 587 + b * 114 ) / 1000 < 128;
		}
		if ( hex.length === 6 ) {
			const r = parseInt( hex.substring( 0, 2 ), 16 );
			const g = parseInt( hex.substring( 2, 4 ), 16 );
			const b = parseInt( hex.substring( 4, 6 ), 16 );
			return ( r * 299 + g * 587 + b * 114 ) / 1000 < 128;
		}
		return false;
	};

	// Modern, compact Color Control with circular swatch, clear trigger, and popover
	const ColorControl = ( { label, value, onChange } ) => {
		const [ isOpen, setIsOpen ] = useState( false );
		const anchorRef = useRef( null );

		return el(
			'div',
			{ className: 'components-base-control gm-editor-color-field' },
			el(
				'div',
				{ className: 'gm-color-row' },
				el( 'label', { className: 'gm-color-label' }, label ),
				el(
					'div',
					{ className: 'gm-color-actions' },
					el(
						'button',
						{
							ref: anchorRef,
							type: 'button',
							className: 'gm-color-swatch-btn' + ( ! value ? ' is-empty' : '' ),
							onClick: () => setIsOpen( ! isOpen ),
							'aria-expanded': isOpen,
							'aria-label': sprintf( __( 'Select %s', 'ajax-filter-posts' ), label ),
							title: value || __( 'Select color', 'ajax-filter-posts' ),
						},
						el( 'span', {
							className: 'gm-color-swatch-circle',
							style: { backgroundColor: value || 'transparent' },
						} ),
						el( 'span', { className: 'gm-color-code' }, value ? value : __( 'Default', 'ajax-filter-posts' ) )
					),
					value &&
						el(
							'button',
							{
								type: 'button',
								className: 'gm-color-clear-btn',
								onClick: () => onChange( '' ),
								title: __( 'Clear color (reset to default)', 'ajax-filter-posts' ),
								'aria-label': __( 'Clear color', 'ajax-filter-posts' ),
							},
							el( 'span', { className: 'dashicons dashicons-no-alt' } )
						)
				)
			),
			isOpen &&
				el(
					Popover,
					{
						anchor: anchorRef.current,
						onClose: () => setIsOpen( false ),
						position: 'bottom right',
						className: 'gm-color-popover',
					},
					el(
						'div',
						{ className: 'gm-color-popover-content' },
						el(
							'div',
							{ className: 'gm-color-popover-title' },
							el( 'span', null, label ),
							el(
								'button',
								{
									type: 'button',
									className: 'gm-color-popover-close-btn',
									onClick: () => setIsOpen( false ),
									'aria-label': __( 'Close', 'ajax-filter-posts' ),
								},
								el( 'span', { className: 'dashicons dashicons-no-alt' } )
							)
						),
						el( ColorPalette, {
							value: value,
							onChange: ( val ) => onChange( val || '' ),
						} )
					)
				)
		);
	};

	// Helper: clean UI Color control with label and Gutenberg ColorPalette
	const renderColorControl = ( label, value, onChange ) => {
		return el( ColorControl, { label, value, onChange } );
	};

	// ------------------------------------------
	// Responsive font size (device + unit aware)
	// ------------------------------------------

	// Supported units: slider ranges plus an approximate px factor used when converting between units.
	const FONT_SIZE_UNITS = {
		px: { min: 8, max: 120, step: 1, factor: 1 },
		em: { min: 0.5, max: 8, step: 0.05, factor: 16 },
		rem: { min: 0.5, max: 8, step: 0.05, factor: 16 },
		vw: { min: 0.5, max: 15, step: 0.1, factor: 14.4 },
	};

	// Breakpoints must stay in sync with the responsive font-size rules in assets/css/frontend-v2.css.
	const RESPONSIVE_DEVICES = [
		{ key: 'desktop', attr: 'fontSize', icon: 'desktop', label: __( 'Desktop', 'ajax-filter-posts' ) },
		{ key: 'tablet', attr: 'fontSizeTablet', icon: 'tablet', label: __( 'Tablet (991px and below)', 'ajax-filter-posts' ) },
		{ key: 'mobile', attr: 'fontSizeMobile', icon: 'smartphone', label: __( 'Mobile (767px and below)', 'ajax-filter-posts' ) },
	];

	// Shared attribute definitions for blocks that use ResponsiveFontSizeControl.
	const responsiveFontSizeAttributes = ( desktopDefault ) => ( {
		fontSize: { type: 'string', default: desktopDefault },
		fontSizeTablet: { type: 'string', default: '' },
		fontSizeMobile: { type: 'string', default: '' },
	} );

	// Helper: split a CSS size such as "1.25rem" into { num, unit }. Bare numbers are treated as px.
	const parseCssSize = ( val ) => {
		const match = /^\s*(\d*\.?\d+)\s*(px|em|rem|vw)?\s*$/i.exec( val === undefined || val === null ? '' : String( val ) );
		if ( ! match ) {
			return null;
		}
		return { num: parseFloat( match[ 1 ] ), unit: ( match[ 2 ] || 'px' ).toLowerCase() };
	};

	const roundToStep = ( num, step ) => {
		const decimals = ( String( step ).split( '.' )[ 1 ] || '' ).length;
		return parseFloat( ( Math.round( num / step ) * step ).toFixed( decimals ) );
	};

	// Helper: convert a parsed size into another unit (approximate for em/rem/vw).
	const convertCssSize = ( parsed, toUnit ) => {
		if ( ! parsed ) {
			return undefined;
		}
		if ( parsed.unit === toUnit ) {
			return parsed.num;
		}
		const from = FONT_SIZE_UNITS[ parsed.unit ] || FONT_SIZE_UNITS.px;
		const to = FONT_SIZE_UNITS[ toUnit ] || FONT_SIZE_UNITS.px;
		return roundToStep( ( parsed.num * from.factor ) / to.factor, to.step );
	};

	// Shared editor device, so every responsive control and the canvas preview switch together.
	const editorDevice = { current: 'desktop', listeners: new Set() };
	const setEditorDevice = ( device ) => {
		editorDevice.current = device;
		editorDevice.listeners.forEach( ( listener ) => listener( device ) );
	};
	const useEditorDevice = () => {
		const [ device, setDevice ] = useState( editorDevice.current );
		useEffect( () => {
			editorDevice.listeners.add( setDevice );
			setDevice( editorDevice.current );
			return () => {
				editorDevice.listeners.delete( setDevice );
			};
		}, [] );
		return device;
	};

	// Helper: resolve the font size shown on a device, cascading mobile → tablet → desktop like the frontend.
	const getResponsiveFontSize = ( attributes, device, fallback ) => {
		let chain = [ 'fontSize' ];
		if ( device === 'tablet' ) {
			chain = [ 'fontSizeTablet', 'fontSize' ];
		} else if ( device === 'mobile' ) {
			chain = [ 'fontSizeMobile', 'fontSizeTablet', 'fontSize' ];
		}
		for ( let i = 0; i < chain.length; i++ ) {
			if ( attributes[ chain[ i ] ] ) {
				return attributes[ chain[ i ] ];
			}
		}
		return fallback || undefined;
	};

	/**
	 * Component: font size slider with a Desktop/Tablet/Mobile switcher and px/em/rem/vw unit selector.
	 *
	 * Stores values (number + unit) in `fontSize`, `fontSizeTablet` and `fontSizeMobile`.
	 * Empty tablet/mobile values inherit from the next larger device.
	 */
	const ResponsiveFontSizeControl = ( { label, attributes, setAttributes, defaultValue = '', pxMin, pxMax } ) => {
		const device = useEditorDevice();
		const [ pendingUnits, setPendingUnits ] = useState( {} );

		const activeDevice = RESPONSIVE_DEVICES.find( ( d ) => d.key === device ) || RESPONSIVE_DEVICES[ 0 ];
		const parsedOwn = parseCssSize( attributes[ activeDevice.attr ] );
		const isOverridden = Boolean( parsedOwn );

		let inheritedValue = defaultValue;
		if ( device === 'tablet' ) {
			inheritedValue = getResponsiveFontSize( attributes, 'desktop', defaultValue );
		} else if ( device === 'mobile' ) {
			inheritedValue = getResponsiveFontSize( attributes, 'tablet', defaultValue );
		}
		const parsedInherited = parseCssSize( inheritedValue ) || { num: 16, unit: 'px' };

		const unit = parsedOwn ? parsedOwn.unit : ( pendingUnits[ device ] || ( parsedInherited ? parsedInherited.unit : 'px' ) );
		const unitConfig = FONT_SIZE_UNITS[ unit ] || FONT_SIZE_UNITS.px;
		const min = 'px' === unit && pxMin ? pxMin : unitConfig.min;
		const max = 'px' === unit && pxMax ? pxMax : unitConfig.max;
		const clamp = ( num ) => Math.min( max, Math.max( min, num ) );

		const setDeviceValue = ( next ) => setAttributes( { [ activeDevice.attr ]: next } );

		const onReset = () => {
			if ( 'desktop' === device ) {
				setDeviceValue( defaultValue );
			} else {
				setDeviceValue( '' );
			}
			setPendingUnits( ( prev ) => {
				const next = { ...prev };
				delete next[ device ];
				return next;
			} );
		};

		const onSliderChange = ( e ) => {
			const val = parseFloat( e.target.value );
			if ( ! Number.isNaN( val ) ) {
				setDeviceValue( val + unit );
			}
		};

		const onInputChange = ( e ) => {
			const val = e.target.value;
			if ( val === '' ) {
				onReset();
				return;
			}
			const num = parseFloat( val );
			if ( ! Number.isNaN( num ) ) {
				setDeviceValue( num + unit );
			}
		};

		const onUnitChange = ( nextUnit ) => {
			if ( nextUnit === unit ) {
				return;
			}
			if ( parsedOwn ) {
				const nextConfig = FONT_SIZE_UNITS[ nextUnit ];
				const nextMin = 'px' === nextUnit && pxMin ? pxMin : nextConfig.min;
				const nextMax = 'px' === nextUnit && pxMax ? pxMax : nextConfig.max;
				const converted = Math.min( nextMax, Math.max( nextMin, convertCssSize( parsedOwn, nextUnit ) ) );
				setDeviceValue( converted + nextUnit );
			} else {
				setPendingUnits( Object.assign( {}, pendingUnits, { [ device ]: nextUnit } ) );
			}
		};

		const currentNum = parsedOwn ? parsedOwn.num : convertCssSize( parsedInherited, unit );
		const sliderValue = currentNum !== undefined ? clamp( currentNum ) : min;
		const progressPercent = Math.max( 0, Math.min( 100, ( ( sliderValue - min ) / ( ( max - min ) || 1 ) ) * 100 ) );

		const hasCustomValue = 'desktop' === device
			? ( parsedOwn && parsedOwn.num !== parseCssSize( defaultValue )?.num )
			: isOverridden;

		return el(
			'div',
			{ className: 'gm-responsive-font-size-control' },

			// 1. Header: Label (never wraps) + Device Switcher
			el(
				'div',
				{ className: 'gm-rfs-header' },
				el( 'label', { className: 'gm-rfs-label' }, label ),
				el(
					'div',
					{ className: 'gm-rfs-devices', role: 'group', 'aria-label': __( 'Preview device', 'ajax-filter-posts' ) },
					RESPONSIVE_DEVICES.map( ( d ) => {
						const hasDeviceOverride = 'desktop' !== d.key && Boolean( attributes[ d.attr ] );
						const isActive = device === d.key;
						return el(
							'button',
							{
								key: d.key,
								type: 'button',
								className: 'gm-rfs-device-btn' + ( isActive ? ' is-active' : '' ) + ( hasDeviceOverride ? ' has-override' : '' ),
								onClick: () => setEditorDevice( d.key ),
								title: d.label,
								'aria-pressed': isActive,
								'aria-label': d.label,
							},
							el( 'span', { className: 'dashicons dashicons-' + ( d.key === 'desktop' ? 'desktop' : ( d.key === 'tablet' ? 'tablet' : 'smartphone' ) ) } ),
							hasDeviceOverride && el( 'span', { className: 'gm-rfs-override-dot', title: __( 'Custom override active', 'ajax-filter-posts' ) } )
						);
					} )
				)
			),

			// 2. Toolbar: Units (left) + Number input & Reset (right)
			el(
				'div',
				{ className: 'gm-rfs-toolbar' },
				el(
					'div',
					{ className: 'gm-rfs-units', role: 'group', 'aria-label': __( 'Font size unit', 'ajax-filter-posts' ) },
					[ 'px', 'rem', 'em', 'vw' ].map( ( u ) =>
						el(
							'button',
							{
								key: u,
								type: 'button',
								className: 'gm-rfs-unit-btn' + ( u === unit ? ' is-active' : '' ),
								'aria-pressed': u === unit,
								onClick: () => onUnitChange( u ),
							},
							u.toUpperCase()
						)
					)
				),
				el(
					'div',
					{ className: 'gm-rfs-input-group' },
					el(
						'div',
						{ className: 'gm-rfs-input-wrapper' },
						el( 'input', {
							type: 'number',
							className: 'gm-rfs-number-input',
							value: parsedOwn ? parsedOwn.num : '',
							placeholder: currentNum !== undefined ? String( currentNum ) : '',
							min: min,
							max: max,
							step: unitConfig.step,
							onChange: onInputChange,
							'aria-label': sprintf( __( '%s value in %s', 'ajax-filter-posts' ), label, unit ),
						} ),
						el( 'span', { className: 'gm-rfs-input-unit-label' }, unit )
					),
					hasCustomValue &&
						el(
							'button',
							{
								type: 'button',
								className: 'gm-rfs-reset-btn',
								onClick: onReset,
								title: 'desktop' === device ? __( 'Reset to default', 'ajax-filter-posts' ) : __( 'Reset to inherit', 'ajax-filter-posts' ),
								'aria-label': __( 'Reset value', 'ajax-filter-posts' ),
							},
							el( 'span', { className: 'dashicons dashicons-image-rotate' } )
						)
				)
			),

			// 3. Slider: full-width, smooth, modern
			el(
				'div',
				{ className: 'gm-rfs-slider-wrap' },
				el( 'input', {
					type: 'range',
					className: 'gm-rfs-range-slider',
					min: min,
					max: max,
					step: unitConfig.step,
					value: sliderValue,
					style: {
						'--gm-rfs-progress': progressPercent + '%',
					},
					onChange: onSliderChange,
					'aria-label': sprintf( __( '%s slider', 'ajax-filter-posts' ), label ),
				} )
			),

			// 4. Inheritance / Override status badge
			'desktop' !== device && (
				! isOverridden
					? el(
							'div',
							{ className: 'gm-rfs-status gm-rfs-status--inherited' },
							el( 'span', { className: 'dashicons dashicons-arrow-down-alt2', 'aria-hidden': true } ),
							el( 'span', null,
								__( 'Inherits ', 'ajax-filter-posts' ),
								el( 'strong', null, inheritedValue ),
								__( ' from ', 'ajax-filter-posts' ),
								el( 'strong', null, device === 'mobile' && attributes.fontSizeTablet ? __( 'Tablet', 'ajax-filter-posts' ) : __( 'Desktop', 'ajax-filter-posts' ) )
							)
					  )
					: el(
							'div',
							{ className: 'gm-rfs-status gm-rfs-status--custom' },
							el( 'span', { className: 'gm-rfs-status-dot' } ),
							el( 'span', null,
								sprintf( __( 'Custom %s size for %s', 'ajax-filter-posts' ), unit.toUpperCase(), activeDevice.label )
							),
							el(
								'button',
								{
									type: 'button',
									className: 'gm-rfs-status-clear-btn',
									onClick: onReset,
								},
								__( 'Clear override', 'ajax-filter-posts' )
							)
					  )
			)
		);
	};

	// Allow Pro and add-ons to reuse the same responsive font size control.
	window.gridmasterBlockControls = Object.assign( window.gridmasterBlockControls || {}, {
		ResponsiveFontSizeControl,
		responsiveFontSizeAttributes,
		getResponsiveFontSize,
		useEditorDevice,
		setEditorDevice,
		renderColorControl,
		ColorControl,
	} );

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
		'gridmaster/post-excerpt',
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
		'gridmaster-pro/card-video',
		'gridmaster-pro/bookmark-button',
		'gridmaster-pro/product-compare',
		'gridmaster-pro/request-quote',
		'gridmaster-pro/variation-swatches',
		'gridmaster-pro/product-meta',
		'gridmaster-pro/action-bar',
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
		title: __( 'Post Grid Master', 'ajax-filter-posts' ),
		description: __( 'Embed a saved Post Grid Master layout with live canvas preview.', 'ajax-filter-posts' ),
		icon: 'grid-view',
		category: 'widgets',
		supports: {
			align: [ 'wide', 'full' ],
			html: false,
		},
		attributes: {
			gridId: { type: 'number', default: 0 },
			align: { type: 'string', default: '' },
		},
		transforms: {
			from: [
				{
					type: 'shortcode',
					tag: [ 'gridmaster', 'am_post_grid', 'asr_ajax' ],
					isMatch: function ( { named } ) {
						return Boolean( named && named.id && Object.keys( named ).length === 1 );
					},
					attributes: {
						gridId: {
							type: 'number',
							shortcode: function ( { named } ) {
								return parseInt( named.id, 10 ) || 0;
							},
						},
					},
				},
				{
					type: 'block',
					blocks: [ 'core/shortcode' ],
					isMatch: function ( { text } ) {
						return /^\s*\[(gridmaster|am_post_grid|asr_ajax)\s+id=["']?\d+["']?\s*\]\s*$/i.test( text || '' );
					},
					transform: function ( { text } ) {
						const match = ( text || '' ).match( /id=["']?(\d+)/i );
						return createBlock( 'gridmaster/grid', {
							gridId: match ? parseInt( match[ 1 ], 10 ) : 0,
						} );
					},
				},
			],
			to: [
				{
					type: 'block',
					blocks: [ 'core/shortcode' ],
					transform: function ( { gridId } ) {
						return createBlock( 'core/shortcode', {
							text: '[gridmaster id="' + ( gridId || 0 ) + '"]',
						} );
					},
				},
			],
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { gridId } = attributes;
			const blockProps = useBlockProps( { className: 'gm-editor-embed-block' } );

			const [ previewData, setPreviewData ] = useState( null );
			const [ isLoading, setIsLoading ] = useState( false );
			const [ error, setError ] = useState( null );
			const [ refreshKey, setRefreshKey ] = useState( 0 );
			const containerRef = useRef( null );

			// Fetch live embed preview via REST
			useEffect( () => {
				if ( ! gridId ) {
					setPreviewData( null );
					setIsLoading( false );
					setError( null );
					return;
				}

				let isMounted = true;
				setIsLoading( true );
				setError( null );

				const endpoint = ( blockData.restPath || '/gridmaster/v2/grids/' ) + gridId + '/embed-preview';

				if ( typeof apiFetch === 'function' ) {
					apiFetch( { path: endpoint } )
						.then( ( data ) => {
							if ( isMounted ) {
								setPreviewData( data );
								setIsLoading( false );
							}
						} )
						.catch( ( err ) => {
							if ( isMounted ) {
								setError( ( err && err.message ) || __( 'Failed to load grid preview.', 'ajax-filter-posts' ) );
								setIsLoading( false );
							}
						} );
				} else {
					setIsLoading( false );
				}

				return () => {
					isMounted = false;
				};
			}, [ gridId, refreshKey ] );

			// Dynamic asset injection & reinitialization in iframe / canvas
			useEffect( () => {
				if ( ! previewData || ! previewData.html || ! containerRef.current ) {
					return;
				}

				const targetDoc = containerRef.current.ownerDocument || document;
				const targetWin = targetDoc.defaultView || window;

				// Inject stylesheets into document head if not already loaded
				if ( Array.isArray( previewData.styles ) ) {
					previewData.styles.forEach( ( href ) => {
						if ( ! href ) return;
						const exists = targetDoc.querySelector( `link[href="${ href }"]` );
						if ( ! exists ) {
							const link = targetDoc.createElement( 'link' );
							link.rel = 'stylesheet';
							link.href = href;
							targetDoc.head.appendChild( link );
						}
					} );
				}

				// Re-init slider/reels inside canvas after markup is inserted
				setTimeout( () => {
					if ( typeof targetWin.gridmasterInit === 'function' ) {
						targetWin.gridmasterInit();
					} else if ( typeof window.gridmasterInit === 'function' ) {
						window.gridmasterInit();
					}
					if ( typeof targetWin.jQuery === 'function' ) {
						targetWin.jQuery( targetDoc ).trigger( 'gridmaster_init_all_sliders' );
					}
				}, 60 );
			}, [ previewData ] );

			// Open GridMaster builder in new tab and auto-refresh on return
			const onEditClick = ( url ) => {
				if ( ! url ) return;
				window.open( url, '_blank' );

				const onWindowFocus = () => {
					setRefreshKey( ( k ) => k + 1 );
				};
				window.addEventListener( 'focus', onWindowFocus, { once: true } );
			};

			const editUrl = previewData && previewData.edit_url;
			const canManage = Boolean( blockData.canManage );

			// Toolbar controls
			const toolbarControls = el(
				BlockControls,
				null,
				el(
					ToolbarGroup,
					null,
					editUrl &&
						el( ToolbarButton, {
							icon: 'edit',
							label: __( 'Edit Grid in GridMaster ↗', 'ajax-filter-posts' ),
							onClick: () => onEditClick( editUrl ),
						} ),
					gridId > 0 &&
						el( ToolbarButton, {
							icon: 'update',
							label: __( 'Refresh Preview', 'ajax-filter-posts' ),
							onClick: () => setRefreshKey( ( k ) => k + 1 ),
						} )
				)
			);

			// Inspector controls
			const inspectorControls = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Grid Selection', 'ajax-filter-posts' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Select Grid', 'ajax-filter-posts' ),
						value: gridId,
						options: grids,
						onChange: function ( val ) {
							setAttributes( { gridId: parseInt( val, 10 ) || 0 } );
						},
					} ),
					gridId > 0 &&
						previewData &&
						el(
							'div',
							{ className: 'gm-editor-engine-badge' },
							el( 'span', { className: 'gm-badge-label' }, __( 'Engine: ', 'ajax-filter-posts' ) ),
							el(
								'span',
								{ className: 'gm-badge-tag ' + ( previewData.engine === 'legacy' ? 'gm-tag-legacy' : 'gm-tag-v2' ) },
								previewData.engine === 'legacy' ? __( 'Legacy (v1)', 'ajax-filter-posts' ) : __( 'GridMaster 2.0', 'ajax-filter-posts' )
							)
						),
					editUrl &&
						el(
							Button,
							{
								isPrimary: true,
								className: 'gm-block-edit-btn',
								onClick: () => onEditClick( editUrl ),
							},
							el( 'span', { className: 'dashicons dashicons-edit' } ),
							' ' + __( 'Edit this grid in GridMaster ↗', 'ajax-filter-posts' )
						),
					canManage &&
						el(
							'div',
							{ className: 'gm-block-helper-links' },
							blockData.createUrl &&
								el(
									'a',
									{
										href: blockData.createUrl,
										target: '_blank',
										rel: 'noopener noreferrer',
										className: 'gm-create-link',
									},
									'+ ' + __( 'Create New Grid', 'ajax-filter-posts' )
								),
							blockData.manageUrl &&
								el(
									'a',
									{
										href: blockData.manageUrl,
										target: '_blank',
										rel: 'noopener noreferrer',
										className: 'gm-manage-link',
									},
									__( 'Manage All Grids', 'ajax-filter-posts' )
								)
						)
				)
			);

			// Render Placeholder if no grid is selected
			if ( ! gridId ) {
				return el(
					Fragment,
					null,
					inspectorControls,
					el(
						'div',
						blockProps,
						el(
							Placeholder,
							{
								icon: 'grid-view',
								label: __( 'Post Grid Master', 'ajax-filter-posts' ),
								instructions: __( 'Choose a saved post grid layout to display on this page.', 'ajax-filter-posts' ),
								className: 'gm-gutenberg-placeholder',
							},
							el( SelectControl, {
								label: __( 'Select Grid', 'ajax-filter-posts' ),
								hideLabelFromVision: true,
								value: gridId,
								options: grids,
								onChange: function ( val ) {
									setAttributes( { gridId: parseInt( val, 10 ) || 0 } );
								},
							} ),
							canManage &&
								blockData.createUrl &&
								el(
									Button,
									{
										isSecondary: true,
										href: blockData.createUrl,
										target: '_blank',
										rel: 'noopener noreferrer',
									},
									'+ ' + __( 'Create New Grid', 'ajax-filter-posts' )
								)
						)
					)
				);
			}

			// Render Loading state
			if ( isLoading && ! previewData ) {
				return el(
					Fragment,
					null,
					toolbarControls,
					inspectorControls,
					el(
						'div',
						blockProps,
						el(
							'div',
							{ className: 'gm-block-preview-loading' },
							el( Spinner, null ),
							el( 'p', null, sprintf( __( 'Loading preview for Grid #%d…', 'ajax-filter-posts' ), gridId ) )
						)
					)
				);
			}

			// Render Error state
			if ( error || ( previewData && ! previewData.exists ) ) {
				return el(
					Fragment,
					null,
					toolbarControls,
					inspectorControls,
					el(
						'div',
						blockProps,
						el(
							Notice,
							{ status: 'error', isDismissible: false },
							error || sprintf( __( 'Grid #%d was not found. It may have been deleted.', 'ajax-filter-posts' ), gridId )
						)
					)
				);
			}

			// Render Live Preview
			return el(
				Fragment,
				null,
				toolbarControls,
				inspectorControls,
				el(
					'div',
					blockProps,
					el(
						'div',
						{
							ref: containerRef,
							className: 'gm-live-embed-preview',
						},
						el(
							DisabledComponent,
							null,
							el( 'div', {
								dangerouslySetInnerHTML: { __html: previewData && previewData.html ? previewData.html : '' },
							} )
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
			const isDark = isOverlay || isColorDark( attributes.backgroundColor );
			const blockProps = useBlockProps( {
				className: 'gm-editor-card gm-editor-card--' + ( attributes.layoutMode || 'vertical' ) + ( isDark ? ' gm-editor-card--dark' : '' ),
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
			wooHoverGalleryImage: { type: 'boolean', default: false },
			sourceType: { type: 'string', default: 'featured_image' },
			metaKey: { type: 'string', default: '' },
			staticUrl: { type: 'string', default: '' },
			fallbackUrl: { type: 'string', default: '' },
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
							label: __( 'Image Source', 'ajax-filter-posts' ),
							value: attributes.sourceType || 'featured_image',
							options: [
								{ label: __( 'Featured Image (Default)', 'ajax-filter-posts' ), value: 'featured_image' },
								{ label: __( 'Custom Field / Meta Key (ACF/MetaBox)', 'ajax-filter-posts' ), value: 'custom_meta' },
								{ label: __( 'First Image in Post Content', 'ajax-filter-posts' ), value: 'content_first' },
								{ label: __( 'Post Author Avatar', 'ajax-filter-posts' ), value: 'author_avatar' },
								{ label: __( 'Static Image URL', 'ajax-filter-posts' ), value: 'static_url' },
							],
							onChange: function ( val ) { setAttributes( { sourceType: val } ); },
						} ),
						attributes.sourceType === 'custom_meta' && el( TextControl, {
							label: __( 'Meta Key / Custom Field Name', 'ajax-filter-posts' ),
							help: __( 'Enter the custom meta key storing the image ID or URL (e.g. hero_image, banner_url).', 'ajax-filter-posts' ),
							value: attributes.metaKey || '',
							onChange: function ( val ) { setAttributes( { metaKey: val } ); },
						} ),
						attributes.sourceType === 'static_url' && el( TextControl, {
							label: __( 'Static Image URL', 'ajax-filter-posts' ),
							value: attributes.staticUrl || '',
							onChange: function ( val ) { setAttributes( { staticUrl: val } ); },
						} ),
						el( TextControl, {
							label: __( 'Fallback Image URL (Optional)', 'ajax-filter-posts' ),
							help: __( 'Used if the primary source has no image.', 'ajax-filter-posts' ),
							value: attributes.fallbackUrl || '',
							onChange: function ( val ) { setAttributes( { fallbackUrl: val } ); },
						} ),
						el( SelectControl, {
							label: __( 'Aspect Ratio', 'ajax-filter-posts' ),
							value: attributes.aspectRatio || '16/9',
							options: [
								{ label: __( '16:9 Landscape', 'ajax-filter-posts' ), value: '16/9' },
								{ label: __( '9:16 Vertical (Shorts/Reels/TikTok)', 'ajax-filter-posts' ), value: '9/16' },
								{ label: __( '4:5 Portrait (Instagram)', 'ajax-filter-posts' ), value: '4/5' },
								{ label: __( '1:1 Square (Instagram)', 'ajax-filter-posts' ), value: '1/1' },
								{ label: __( '4:3 Standard', 'ajax-filter-posts' ), value: '4/3' },
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
						} ),
						el( ToggleControl, {
							label: __( 'Swap to 2nd Product Gallery Image on Hover (WooCommerce)', 'ajax-filter-posts' ),
							help: __( 'Crossfades to the second product gallery image when hovering over a WooCommerce product card.', 'ajax-filter-posts' ),
							checked: !! attributes.wooHoverGalleryImage,
							onChange: function ( val ) { setAttributes( { wooHoverGalleryImage: val } ); },
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
		attributes: Object.assign(
			{ tagName: { type: 'string', default: 'h3' } },
			responsiveFontSizeAttributes( '20px' ),
			{
				color: { type: 'string', default: '' },
				isLink: { type: 'boolean', default: true },
			}
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-title' } );
			const device = useEditorDevice();

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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '20px',
							pxMin: 10,
							pxMax: 96,
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
					el( attributes.tagName, { style: { fontSize: getResponsiveFontSize( attributes, device ), color: attributes.color || 'inherit', margin: 0 } }, __( 'Sample Post Title', 'ajax-filter-posts' ) )
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
		attributes: Object.assign(
			{ length: { type: 'number', default: 20 } },
			responsiveFontSizeAttributes( '14px' ),
			{ color: { type: 'string', default: '' } }
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-excerpt' } );
			const device = useEditorDevice();

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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '14px',
							pxMin: 10,
							pxMax: 40,
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
					el( 'p', { style: { color: attributes.color || '#6b7280', fontSize: getResponsiveFontSize( attributes, device, '14px' ), margin: 0 } }, __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...', 'ajax-filter-posts' ) )
				)
			);
		},
		save: function () { return null; },
	} );

	// Alias for backwards-compatibility
	registerBlockType( 'gridmaster/post-excerpt', {
		apiVersion: 3,
		title: __( 'Post Excerpt', 'ajax-filter-posts' ),
		icon: 'excerpt-view',
		category: 'theme',
		attributes: Object.assign(
			{ length: { type: 'number', default: 20 } },
			responsiveFontSizeAttributes( '14px' ),
			{ color: { type: 'string', default: '' } }
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-excerpt' } );
			const device = useEditorDevice();

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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '14px',
							pxMin: 10,
							pxMax: 40,
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
					el( 'p', { style: { color: attributes.color || '#6b7280', fontSize: getResponsiveFontSize( attributes, device, '14px' ), margin: 0 } }, __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...', 'ajax-filter-posts' ) )
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
		attributes: Object.assign(
			{
				text: { type: 'string', default: __( 'Read More', 'ajax-filter-posts' ) },
				backgroundColor: { type: 'string', default: '' },
				textColor: { type: 'string', default: '' },
				borderRadius: { type: 'string', default: '' },
				padding: { type: 'string', default: '' },
			},
			responsiveFontSizeAttributes( '14px' )
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-read-more' } );
			const device = useEditorDevice();

			const btnStyle = {
				display: 'inline-flex',
				alignItems: 'center',
				justifyContent: 'center',
				padding: attributes.padding || '8px 18px',
				backgroundColor: attributes.backgroundColor || '#2563eb',
				color: attributes.textColor || '#ffffff',
				borderRadius: attributes.borderRadius || '6px',
				fontWeight: '500',
				fontSize: getResponsiveFontSize( attributes, device, '14px' ),
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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '14px',
							pxMin: 10,
							pxMax: 36,
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
		attributes: Object.assign(
			{ format: { type: 'string', default: 'F j, Y' } },
			responsiveFontSizeAttributes( '13px' ),
			{ color: { type: 'string', default: '' } }
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-date' } );
			const formattedDate = formatPhpDate( attributes.format || 'F j, Y' );
			const device = useEditorDevice();

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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '13px',
							pxMin: 9,
							pxMax: 32,
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
					el( 'span', { style: { fontSize: getResponsiveFontSize( attributes, device, '13px' ), color: attributes.color || '#9ca3af' } }, el( 'span', { className: 'dashicons dashicons-calendar-alt' } ), ' ' + ( formattedDate || 'November 5, 2026' ) )
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
		attributes: Object.assign(
			{
				wordsPerMinute: { type: 'number', default: 200 },
				prefix: { type: 'string', default: '' },
				suffix: { type: 'string', default: 'min read' },
				showIcon: { type: 'boolean', default: true },
			},
			responsiveFontSizeAttributes( '13px' ),
			{ color: { type: 'string', default: '#6b7280' } }
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-reading-time' } );
			const device = useEditorDevice();

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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '13px',
							pxMin: 9,
							pxMax: 32,
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
						{ style: { fontSize: getResponsiveFontSize( attributes, device ), color: attributes.color, display: 'inline-flex', alignItems: 'center', gap: '4px' } },
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
		attributes: Object.assign(
			{
				format: { type: 'string', default: 'F j, Y' },
				prefix: { type: 'string', default: 'Updated on' },
				onlyIfModified: { type: 'boolean', default: true },
				minHoursDiff: { type: 'number', default: 24 },
			},
			responsiveFontSizeAttributes( '13px' ),
			{ color: { type: 'string', default: '#6b7280' } }
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-modified-date' } );
			const formattedDate = formatPhpDate( attributes.format || 'F j, Y' );
			const device = useEditorDevice();

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
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '13px',
							pxMin: 9,
							pxMax: 32,
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
						{ style: { fontSize: getResponsiveFontSize( attributes, device ), color: attributes.color, display: 'inline-flex', alignItems: 'center', gap: '4px' } },
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
		attributes: Object.assign(
			{
				text: { type: 'string', default: 'Featured' },
				badgeType: { type: 'string', default: 'static' },
				metaKey: { type: 'string', default: '' },
				backgroundColor: { type: 'string', default: '#ef4444' },
				textColor: { type: 'string', default: '#ffffff' },
			},
			responsiveFontSizeAttributes( '11px' ),
			{
				borderRadius: { type: 'string', default: '9999px' },
				padding: { type: 'string', default: '3px 8px' },
			}
		),
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-badge' } );
			const device = useEditorDevice();

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
								{ label: __( 'WooCommerce Sale Badge', 'ajax-filter-posts' ), value: 'sale' },
								{ label: __( 'WooCommerce Discount %', 'ajax-filter-posts' ), value: 'discount' },
							],
							onChange: function ( val ) { setAttributes( { badgeType: val } ); },
						} ),
						( attributes.badgeType === 'static' || attributes.badgeType === 'sale' ) && el( TextControl, {
							label: __( 'Badge Text', 'ajax-filter-posts' ),
							value: attributes.text,
							onChange: function ( val ) { setAttributes( { text: val } ); },
						} ),
						attributes.badgeType === 'meta' && el( TextControl, {
							label: __( 'Meta Key', 'ajax-filter-posts' ),
							value: attributes.metaKey,
							onChange: function ( val ) { setAttributes( { metaKey: val } ); },
						} ),
						el( ResponsiveFontSizeControl, {
							label: __( 'Font Size', 'ajax-filter-posts' ),
							attributes: attributes,
							setAttributes: setAttributes,
							defaultValue: '11px',
							pxMin: 8,
							pxMax: 32,
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
								fontSize: getResponsiveFontSize( attributes, device, '11px' ),
								fontWeight: 'bold',
								lineHeight: '1',
								textTransform: 'uppercase',
							},
						},
						attributes.badgeType === 'meta'
							? ( attributes.metaKey ? attributes.metaKey : 'META BADGE' )
							: ( attributes.badgeType === 'discount' || attributes.badgeType === 'discount_percent' ? '-20%' : ( attributes.text || ( attributes.badgeType === 'sale' ? 'SALE!' : 'FEATURED' ) ) )
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
	// WooCommerce Free Fallback & Pro Block Previews
	// Registered via domReady so Pro's pro-blocks-editor.js takes precedence when active
	// ==========================================
	const onDomReady = wp.domReady || function ( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	};

	onDomReady( function () {
		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/product-price' ) ) {
			registerBlockType( 'gridmaster-pro/product-price', {
				apiVersion: 3,
				title: __( 'Product Price', 'ajax-filter-posts' ),
				icon: 'money-alt',
				category: 'widgets',
				attributes: Object.assign(
					responsiveFontSizeAttributes( '' ),
					{
						color: { type: 'string', default: '' },
						saleColor: { type: 'string', default: '#ef4444' },
						showRegularPrice: { type: 'boolean', default: true },
						showDiscountBadge: { type: 'boolean', default: false },
					}
				),
				edit: function ( props ) {
					const { attributes, setAttributes } = props;
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-price' } );
					const device = useEditorDevice();
					const currentFontSize = getResponsiveFontSize( attributes, device, '16px' );

					return el(
						Fragment,
						null,
						el(
							InspectorControls,
							null,
							el(
								PanelBody,
								{ title: __( 'Price Settings', 'ajax-filter-posts' ) },
								el( ResponsiveFontSizeControl, {
									label: __( 'Font Size', 'ajax-filter-posts' ),
									attributes: attributes,
									setAttributes: setAttributes,
									defaultValue: '16px',
									pxMin: 10,
									pxMax: 48,
								} ),
								renderColorControl(
									__( 'Regular Price Color', 'ajax-filter-posts' ),
									attributes.color,
									( val ) => setAttributes( { color: val } )
								),
								renderColorControl(
									__( 'Sale Price Color', 'ajax-filter-posts' ),
									attributes.saleColor,
									( val ) => setAttributes( { saleColor: val } )
								),
								el( ToggleControl, {
									label: __( 'Show Strikethrough Regular Price', 'ajax-filter-posts' ),
									checked: attributes.showRegularPrice !== false,
									onChange: function ( val ) { setAttributes( { showRegularPrice: val } ); },
								} )
							)
						),
						el(
							'div',
							blockProps,
							el(
								'div',
								{ className: 'gm-product-price-preview', style: { fontSize: currentFontSize } },
								attributes.showRegularPrice !== false && el( 'span', { style: { textDecoration: 'line-through', color: attributes.color || '#9ca3af', marginRight: '6px' } }, '$69.00' ),
								el( 'span', { style: { fontWeight: 'bold', color: attributes.saleColor || '#ef4444' } }, '$49.00' )
							)
						)
					);
				},
				save: function () { return null; },
			} );
		}

		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/add-to-cart' ) ) {
			registerBlockType( 'gridmaster-pro/add-to-cart', {
				apiVersion: 3,
				title: __( 'Add to Cart', 'ajax-filter-posts' ),
				icon: 'cart',
				category: 'widgets',
				attributes: {
					buttonText: { type: 'string', default: '' },
					showIcon: { type: 'boolean', default: true },
					showQuantityStepper: { type: 'boolean', default: false },
					fullWidth: { type: 'boolean', default: false },
					backgroundColor: { type: 'string', default: '#2563eb' },
					textColor: { type: 'string', default: '#ffffff' },
					borderRadius: { type: 'string', default: '4px' },
					padding: { type: 'string', default: '8px 16px' },
				},
				edit: function ( props ) {
					const { attributes, setAttributes } = props;
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-cart' } );

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
									label: __( 'Button Text Override', 'ajax-filter-posts' ),
									placeholder: __( 'Auto (WooCommerce)', 'ajax-filter-posts' ),
									value: attributes.buttonText,
									onChange: function ( val ) { setAttributes( { buttonText: val } ); },
								} ),
								el( ToggleControl, {
									label: __( 'Show Cart Icon', 'ajax-filter-posts' ),
									checked: attributes.showIcon !== false,
									onChange: function ( val ) { setAttributes( { showIcon: val } ); },
								} ),
								el( ToggleControl, {
									label: __( 'Full Width Button', 'ajax-filter-posts' ),
									checked: !! attributes.fullWidth,
									onChange: function ( val ) { setAttributes( { fullWidth: val } ); },
								} ),
								el( RangeControl, {
									label: __( 'Border Radius (px)', 'ajax-filter-posts' ),
									value: parsePx( attributes.borderRadius, 4 ),
									min: 0,
									max: 30,
									step: 1,
									onChange: function ( val ) { setAttributes( { borderRadius: val + 'px' } ); },
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
								'button',
								{
									type: 'button',
									className: 'gm-btn',
									style: {
										padding: attributes.padding || '8px 16px',
										background: attributes.backgroundColor || '#2563eb',
										color: attributes.textColor || '#ffffff',
										borderRadius: attributes.borderRadius || '4px',
										border: 'none',
										display: 'inline-flex',
										alignItems: 'center',
										justifyContent: 'center',
										width: attributes.fullWidth ? '100%' : 'auto',
										gap: '6px',
										cursor: 'default',
									},
								},
								attributes.showIcon !== false && el( 'span', { className: 'dashicons dashicons-cart' } ),
								attributes.buttonText || __( 'Add to Cart', 'ajax-filter-posts' )
							)
						)
					);
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

		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/action-bar' ) ) {
			registerBlockType( 'gridmaster-pro/action-bar', {
				apiVersion: 3,
				title: __( 'Product Action Bar (Pro)', 'ajax-filter-posts' ),
				icon: 'screenoptions',
				category: 'widgets',
				edit: function () {
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-action-bar' } );
					return el( 'div', blockProps, el( 'span', { style: { fontSize: '13px', color: '#475569' } }, '♥  👁  ⇄  🛒' ) );
				},
				save: function () { return null; },
			} );
		}

		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/variation-swatches' ) ) {
			registerBlockType( 'gridmaster-pro/variation-swatches', {
				apiVersion: 3,
				title: __( 'Variation Swatches (Pro)', 'ajax-filter-posts' ),
				icon: 'art',
				category: 'widgets',
				edit: function () {
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-swatches' } );
					return el( 'div', blockProps, el( 'span', { style: { fontSize: '13px', color: '#475569' } }, '● ● ●  [S] [M] [L]' ) );
				},
				save: function () { return null; },
			} );
		}

		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/product-compare' ) ) {
			registerBlockType( 'gridmaster-pro/product-compare', {
				apiVersion: 3,
				title: __( 'Compare Button (Pro)', 'ajax-filter-posts' ),
				icon: 'randomize',
				category: 'widgets',
				edit: function () {
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-compare' } );
					return el( 'div', blockProps, el( 'span', { style: { fontSize: '13px', color: '#475569' } }, '⇄ Compare' ) );
				},
				save: function () { return null; },
			} );
		}

		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/request-quote' ) ) {
			registerBlockType( 'gridmaster-pro/request-quote', {
				apiVersion: 3,
				title: __( 'Request a Quote (Pro)', 'ajax-filter-posts' ),
				icon: 'email-alt',
				category: 'widgets',
				edit: function () {
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-quote' } );
					return el( 'div', blockProps, el( 'button', { type: 'button', className: 'gm-btn', style: { padding: '6px 12px', background: '#0f172a', color: '#fff', borderRadius: '4px' } }, '✉ Request a Quote' ) );
				},
				save: function () { return null; },
			} );
		}

		if ( ! wp.blocks.getBlockType( 'gridmaster-pro/product-meta' ) ) {
			registerBlockType( 'gridmaster-pro/product-meta', {
				apiVersion: 3,
				title: __( 'Product Meta & Specs (Pro)', 'ajax-filter-posts' ),
				icon: 'info-outline',
				category: 'widgets',
				edit: function () {
					const blockProps = useBlockProps( { className: 'gm-editor-field gm-field-pro-product-meta' } );
					return el( 'div', blockProps, el( 'span', { style: { fontSize: '12px', color: '#64748b' } }, 'SKU: GM-1042 • Brand: Acme' ) );
				},
				save: function () { return null; },
			} );
		}
	} );

} )( window.wp );

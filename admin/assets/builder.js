/**
 * GridMaster 2.0 — Redesigned Gutenberg Post Grid Builder Workspace
 */
( function ( wp ) {
	'use strict';

	const { createElement: el, useState, useEffect, useLayoutEffect, useRef, useCallback, Fragment } = wp.element;
	const { render } = wp.element;
	const { parse, serialize, createBlock } = wp.blocks;
	const { useDispatch, useSelect } = wp.data;
	const {
		BlockEditorProvider,
		BlockList,
		BlockTools,
		WritingFlow,
		ObserveTyping,
		BlockInspector,
	} = wp.blockEditor;
	const {
		Button,
		TextControl,
		SelectControl,
		RangeControl,
		ToggleControl,
		Spinner,
		Notice,
		Modal,
		Popover,
		ColorPalette,
		Tooltip,
	} = wp.components;
	const __ = wp.i18n.__;
	const sprintf = wp.i18n.sprintf || ( ( str, ...args ) => args.reduce( ( s, a ) => s.replace( /%s/, a ), str ) );

	const initData = window.gridmasterBuilderData || {};
	// The standalone shell must register core blocks as well as load their script.
	if ( wp.blockLibrary && ! wp.blocks.getBlockType( 'core/group' ) ) {
		wp.blockLibrary.registerCoreBlocks();
	}

	const escapeHtml = ( value ) => String( value ).replace( /[&<>"']/g, ( char ) => ( {
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
	} )[ char ] );
	const scriptJson = ( value ) => JSON.stringify( value ).replace( /</g, '\\u003c' );
	const previewRuntime = {
		restUrl: initData.restUrl, nonce: initData.nonce,
		errorText: __( 'Could not load posts. Please retry.', 'ajax-filter-posts' ),
		retryText: __( 'Retry', 'ajax-filter-posts' ),
	};
	const readApiResponse = async ( response ) => {
		const data = await response.json();
		if ( ! response.ok ) {
			throw new Error( data.code === 'rest_cookie_invalid_nonce' || response.status === 401
				? __( 'Your session expired. Your edits are still here. Sign in in another tab, then retry.', 'ajax-filter-posts' )
				: ( data.message || __( 'The request failed. Please retry.', 'ajax-filter-posts' ) ) );
		}
		return data;
	};

	/**
	 * Safe copy to clipboard with fallback for non-HTTPS / restricted permissions.
	 */
	function safeCopy( text ) {
		if ( window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text ).catch( () => fallbackCopy( text ) );
		}
		return fallbackCopy( text );
	}

	function fallbackCopy( text ) {
		return new Promise( ( resolve, reject ) => {
			const previousFocus = document.activeElement;
			const textArea = document.createElement( 'textarea' );
			try {
				textArea.value = text;
				textArea.style.position = 'fixed';
				textArea.style.top = '-9999px';
				textArea.style.left = '-9999px';
				textArea.style.opacity = '0';
				document.body.appendChild( textArea );
				textArea.focus();
				textArea.select();
				const successful = document.execCommand( 'copy' );
				if ( successful ) {
					resolve();
				} else {
					reject( new Error( 'execCommand failed' ) );
				}
			} catch ( err ) {
				reject( err );
			} finally {
				textArea.remove();
				previousFocus?.focus();
			}
		} );
	}

	/**
	 * Block Inserter Popover component.
	 */
	function MoreBlocksPopover( props ) {
		const { isOpen, onClose, onSelectBlock, isPro } = props;
		const [ searchTerm, setSearchTerm ] = useState( '' );
		const [ activeCat, setActiveCat ] = useState( 'All' );

		if ( ! isOpen ) return null;

		const defaultCategories = [
			{
				title: __( 'Content & Editorial', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster/post-date', title: __( 'Post Date', 'ajax-filter-posts' ), icon: 'calendar-alt', desc: __( 'Display published post date', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/modified-date', title: __( 'Modified Date', 'ajax-filter-posts' ), icon: 'update', desc: __( 'Display updated date for modified posts', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/reading-time', title: __( 'Reading Time', 'ajax-filter-posts' ), icon: 'clock', desc: __( 'Estimated read time calculated from post content', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/post-author', title: __( 'Post Author', 'ajax-filter-posts' ), icon: 'admin-users', desc: __( 'Author name and byline', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/author-avatar', title: __( 'Author Avatar', 'ajax-filter-posts' ), icon: 'admin-users', desc: __( 'Author Gravatar photo with link', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/post-terms', title: __( 'Categories & Terms', 'ajax-filter-posts' ), icon: 'tag', desc: __( 'Display assigned taxonomy terms', 'ajax-filter-posts' ), attrs: { taxonomy: 'category' } },
					{ name: 'gridmaster/post-meta', title: __( 'Custom Field / Meta', 'ajax-filter-posts' ), icon: 'admin-generic', desc: __( 'Custom post meta field value', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/comments-count', title: __( 'Comments Count', 'ajax-filter-posts' ), icon: 'admin-comments', desc: __( 'Number of comments on the post', 'ajax-filter-posts' ), attrs: {} },
				],
			},
			{
				title: __( 'Media & Badges', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster-pro/card-video', title: __( 'Card Video Player (Pro)', 'ajax-filter-posts' ), icon: 'video-alt2', desc: __( 'Inline video player or preview on click/hover/loop', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/video-lightbox', title: __( 'Video Lightbox (Pro)', 'ajax-filter-posts' ), icon: 'video-alt3', desc: __( 'Video play button with modal popup', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster/post-format-icon', title: __( 'Post Format Icon', 'ajax-filter-posts' ), icon: 'video-alt3', desc: __( 'Dynamic icon for video, audio, or gallery formats', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/badge', title: __( 'Custom Badge', 'ajax-filter-posts' ), icon: 'tag', desc: __( 'Static or meta-driven custom badge', 'ajax-filter-posts' ), attrs: {} },
				],
			},
			{
				title: __( 'Engagement & Social', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster/social-share', title: __( 'Social Share', 'ajax-filter-posts' ), icon: 'share', desc: __( 'One-click social sharing buttons', 'ajax-filter-posts' ), attrs: {} },
				],
			},
			{
				title: __( 'Advanced & Embeds', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster/shortcode', title: __( 'Shortcode', 'ajax-filter-posts' ), icon: 'shortcode', desc: __( 'Execute WordPress shortcodes with dynamic tags like {post_id}', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/html', title: __( 'Custom HTML', 'ajax-filter-posts' ), icon: 'html', desc: __( 'Embed custom HTML markup with dynamic post tags', 'ajax-filter-posts' ), attrs: {} },
				],
			},
			{
				title: __( 'Layout & Structure', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'core/group', title: __( 'Group Container', 'ajax-filter-posts' ), icon: 'layout', desc: __( 'Group inner blocks with flex or grid layout', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'core/columns', title: __( 'Columns', 'ajax-filter-posts' ), icon: 'columns', desc: __( 'Multi-column container for badges or metadata', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'core/spacer', title: __( 'Spacer', 'ajax-filter-posts' ), icon: 'image-flip-vertical', desc: __( 'Add vertical whitespace', 'ajax-filter-posts' ), attrs: { height: '16px' } },
					{ name: 'core/separator', title: __( 'Separator', 'ajax-filter-posts' ), icon: 'minus', desc: __( 'Horizontal divider line', 'ajax-filter-posts' ), attrs: {} },
				],
			},
			{
				title: __( 'WooCommerce & Pro Elements', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster-pro/product-price', title: __( 'Product Price', 'ajax-filter-posts' ), icon: 'money-alt', desc: __( 'WooCommerce regular and sale price', 'ajax-filter-posts' ), isPro: false, attrs: {} },
					{ name: 'gridmaster-pro/add-to-cart', title: __( 'Add to Cart', 'ajax-filter-posts' ), icon: 'cart', desc: __( 'AJAX add-to-cart button', 'ajax-filter-posts' ), isPro: false, attrs: {} },
					{ name: 'gridmaster/badge', title: __( 'Sale Badge', 'ajax-filter-posts' ), icon: 'tag', desc: __( 'Dynamic Sale or -% badge for products.', 'ajax-filter-posts' ), isPro: false, attrs: { badgeType: 'sale', text: 'Sale!' } },
					{ name: 'gridmaster-pro/action-bar', title: __( 'Product Action Bar', 'ajax-filter-posts' ), icon: 'screenoptions', desc: __( 'Floating Wishlist, Quick View, Compare & Cart icons.', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/variation-swatches', title: __( 'Variation Swatches', 'ajax-filter-posts' ), icon: 'art', desc: __( 'Color, image & label swatches with live image swap.', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/product-compare', title: __( 'Compare Button', 'ajax-filter-posts' ), icon: 'randomize', desc: __( 'Side-by-side product comparison drawer & table (Native / WPC / YITH).', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/request-quote', title: __( 'Request a Quote', 'ajax-filter-posts' ), icon: 'email-alt', desc: __( 'Product enquiry / B2B quote modal button (Native / YITH / WPC).', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/product-meta', title: __( 'Product Meta & Specs', 'ajax-filter-posts' ), icon: 'info-outline', desc: __( 'SKU, Brand, Weight, Dimensions & key spec attributes.', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/badge', title: __( 'Product Badge (Pro)', 'ajax-filter-posts' ), icon: 'star-filled', desc: __( 'Dynamic badges like New, Featured, or Sale', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/product-rating', title: __( 'Star Rating (Pro)', 'ajax-filter-posts' ), icon: 'star-filled', desc: __( 'Customer review star ratings', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/stock-status', title: __( 'Stock Status (Pro)', 'ajax-filter-posts' ), icon: 'archive', desc: __( 'Product inventory status and low stock alert', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/sale-countdown', title: __( 'Sale Countdown (Pro)', 'ajax-filter-posts' ), icon: 'clock', desc: __( 'Live countdown timer for scheduled sales', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/quick-view', title: __( 'Quick View (Pro)', 'ajax-filter-posts' ), icon: 'visibility', desc: __( 'Modal quick preview trigger', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/video-lightbox', title: __( 'Video Lightbox (Pro)', 'ajax-filter-posts' ), icon: 'video-alt3', desc: __( 'Video play button with modal popup', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/card-video', title: __( 'Card Video Player (Pro)', 'ajax-filter-posts' ), icon: 'video-alt2', desc: __( 'Inline video player, preview on click/hover, or vertical shorts loop', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/bookmark-button', title: __( 'Bookmark / Wishlist (Pro)', 'ajax-filter-posts' ), icon: 'heart', desc: __( 'Save post to visitor favorites', 'ajax-filter-posts' ), isPro: true, attrs: {} },
				],
			},
		];

		const blockCategories = wp.hooks && wp.hooks.applyFilters
			? wp.hooks.applyFilters( 'gridmaster.cardBlockCategories', defaultCategories )
			: defaultCategories;

		// Compute total blocks and category pills
		let totalBlocksCount = 0;
		const categoryTabs = [ { id: 'All', title: __( 'All', 'ajax-filter-posts' ), fullTitle: 'All', count: 0 } ];

		blockCategories.forEach( ( cat ) => {
			const count = cat.blocks ? cat.blocks.length : 0;
			totalBlocksCount += count;
			let shortTitle = cat.title
				.replace( ' & Pro Elements', '' )
				.replace( ' & Badges', '' )
				.replace( ' & Social', '' )
				.replace( ' & Embeds', '' )
				.replace( ' & Structure', '' );
			categoryTabs.push( {
				id: cat.title,
				title: shortTitle,
				fullTitle: cat.title,
				count: count,
			} );
		} );
		categoryTabs[ 0 ].count = totalBlocksCount;

		const getThemeForCat = ( catTitle ) => {
			if ( ! catTitle ) return 'theme-editorial';
			const t = catTitle.toLowerCase();
			if ( t.includes( 'woo' ) ) return 'theme-woo';
			if ( t.includes( 'media' ) ) return 'theme-media';
			if ( t.includes( 'social' ) || t.includes( 'engagement' ) ) return 'theme-social';
			if ( t.includes( 'layout' ) || t.includes( 'structure' ) ) return 'theme-layout';
			if ( t.includes( 'advanced' ) || t.includes( 'embed' ) ) return 'theme-advanced';
			return 'theme-editorial';
		};

		const lowerSearch = searchTerm.trim().toLowerCase();
		const filteredCategories = blockCategories
			.filter( ( cat ) => activeCat === 'All' || cat.title === activeCat )
			.map( ( cat ) => {
				const matching = ( cat.blocks || [] ).filter( ( b ) => {
					if ( ! lowerSearch ) return true;
					return (
						( b.title && b.title.toLowerCase().includes( lowerSearch ) ) ||
						( b.desc && b.desc.toLowerCase().includes( lowerSearch ) ) ||
						( cat.title && cat.title.toLowerCase().includes( lowerSearch ) )
					);
				} );
				return { ...cat, blocks: matching };
			} )
			.filter( ( cat ) => cat.blocks.length > 0 );

		const totalVisibleBlocks = filteredCategories.reduce( ( sum, cat ) => sum + cat.blocks.length, 0 );

		return el(
			Popover,
			{
				className: 'gm-popover-mega gm-more-blocks-popover',
				onClose: onClose,
				position: 'bottom right',
			},
			el(
				'div',
				{ className: 'gm-popover-mega-panel' },

				// Header: Title Bar + Count + Close Button
				el(
					'div',
					{ className: 'gm-popover-header' },
					el(
						'div',
						{ className: 'gm-popover-header-title-row' },
						el( 'span', { className: 'dashicons dashicons-plus-alt' } ),
						el( 'h3', { className: 'gm-popover-title' }, __( 'Card Blocks Library', 'ajax-filter-posts' ) ),
						el(
							'span',
							{ className: 'gm-popover-count-pill' },
							sprintf( __( '%d blocks', 'ajax-filter-posts' ), totalBlocksCount )
						)
					),
					el( Button, {
						icon: 'no-alt',
						label: __( 'Close', 'ajax-filter-posts' ),
						onClick: onClose,
						className: 'gm-popover-close-btn',
						isSmall: true,
					} )
				),

				// Search Bar
				el(
					'div',
					{ className: 'gm-popover-search-wrap' },
					el( 'span', { className: 'dashicons dashicons-search gm-search-icon' } ),
					el( 'input', {
						type: 'text',
						className: 'gm-search-input',
						placeholder: __( 'Search blocks (e.g. date, video, price, rating)...', 'ajax-filter-posts' ),
						value: searchTerm,
						onChange: ( e ) => setSearchTerm( e.target.value ),
						autoFocus: true,
					} ),
					searchTerm &&
						el(
							'button',
							{
								type: 'button',
								className: 'gm-search-clear-btn',
								onClick: () => setSearchTerm( '' ),
								'aria-label': __( 'Clear search', 'ajax-filter-posts' ),
							},
							'✕'
						)
				),

				// Category Filter Tabs
				el(
					'div',
					{ className: 'gm-popover-tabs-bar' },
					categoryTabs.map( ( tab ) =>
						el(
							'button',
							{
								key: tab.id,
								type: 'button',
								className: 'gm-tab-pill' + ( activeCat === tab.fullTitle ? ' is-active' : '' ),
								onClick: () => setActiveCat( tab.fullTitle ),
							},
							tab.title,
							el( 'span', { className: 'gm-tab-count' }, tab.count )
						)
					)
				),

				// Scrollable Grid of Blocks
				el(
					'div',
					{ className: 'gm-popover-scrollable-body' },
					totalVisibleBlocks === 0
						? el(
								'div',
								{ className: 'gm-popover-empty-state' },
								el( 'span', { className: 'dashicons dashicons-search' } ),
								el( 'p', { className: 'gm-empty-title' }, __( 'No matching blocks found', 'ajax-filter-posts' ) ),
								el( 'p', { className: 'gm-empty-hint' }, __( 'Try another keyword or select the "All" category tab.', 'ajax-filter-posts' ) ),
								searchTerm &&
									el(
										Button,
										{
											isSecondary: true,
											isSmall: true,
											onClick: () => setSearchTerm( '' ),
										},
										__( 'Clear Search', 'ajax-filter-posts' )
									)
						  )
						: filteredCategories.map( ( cat, idx ) =>
								el(
									'div',
									{ key: cat.title || idx, className: 'gm-category-section' },
									activeCat === 'All' &&
										el(
											'div',
											{ className: 'gm-category-section-header' },
											el( 'span', { className: 'gm-category-section-title' }, cat.title ),
											el( 'span', { className: 'gm-category-section-count' }, cat.blocks.length )
										),
									el(
										'div',
										{ className: 'gm-blocks-grid-2col' },
										cat.blocks.map( ( b, bIdx ) => {
											const themeClass = getThemeForCat( cat.title );
											const isBlockDisabled = b.isPro && ! isPro;
											return el(
												'button',
												{
													key: b.name + '-' + bIdx,
													type: 'button',
													className:
														'gm-block-grid-item' +
														( b.isPro ? ' is-pro' : '' ) +
														( isBlockDisabled ? ' is-pro-disabled' : '' ),
													disabled: isBlockDisabled,
													onClick: () => {
														onSelectBlock( b.name, b.attrs );
														onClose();
													},
													title: isBlockDisabled
														? __( 'Pro element — upgrade GridMaster to unlock', 'ajax-filter-posts' )
														: b.desc || b.title,
												},
												el(
													'div',
													{ className: 'gm-block-icon-badge ' + themeClass },
													el( 'span', { className: 'dashicons dashicons-' + b.icon } )
												),
												el(
													'div',
													{ className: 'gm-block-content' },
													el(
														'div',
														{ className: 'gm-block-title-row' },
														el( 'span', { className: 'gm-block-title' }, b.title ),
														b.isPro &&
															el(
																'span',
																{ className: 'gm-pro-badge-pill' },
																'PRO'
															)
													),
													el( 'span', { className: 'gm-block-desc' }, b.desc )
												),
												el(
													'div',
													{ className: 'gm-block-add-affordance' },
													el( 'span', { className: 'dashicons dashicons-plus-alt2' } )
												)
											);
										} )
									)
								)
						  )
				)
			)
		);
	}

	/**
	 * Custom Hierarchical List View component.
	 */
	function CardListView( props ) {
		const { onClose } = props;
		const { selectBlock } = useDispatch( 'core/block-editor' );
		const { rootBlocks, selectedClientId } = useSelect( ( select ) => {
			const be = select( 'core/block-editor' );
			return {
				rootBlocks: be.getBlocks(),
				selectedClientId: be.getSelectedBlockClientId(),
			};
		} );

		const blockMetaMap = {
			'gridmaster/card': { title: __( 'Card Container', 'ajax-filter-posts' ), icon: 'format-aside' },
			'gridmaster/title': { title: __( 'Post Title', 'ajax-filter-posts' ), icon: 'heading' },
			'gridmaster/featured-image': { title: __( 'Featured Image', 'ajax-filter-posts' ), icon: 'format-image' },
			'gridmaster/excerpt': { title: __( 'Excerpt', 'ajax-filter-posts' ), icon: 'editor-alignleft' },
			'gridmaster/post-excerpt': { title: __( 'Excerpt', 'ajax-filter-posts' ), icon: 'editor-alignleft' },
			'gridmaster/read-more': { title: __( 'Button (Read More)', 'ajax-filter-posts' ), icon: 'button' },
			'gridmaster/post-date': { title: __( 'Post Date', 'ajax-filter-posts' ), icon: 'calendar-alt' },
			'gridmaster/modified-date': { title: __( 'Modified Date', 'ajax-filter-posts' ), icon: 'update' },
			'gridmaster/reading-time': { title: __( 'Reading Time', 'ajax-filter-posts' ), icon: 'clock' },
			'gridmaster/post-author': { title: __( 'Post Author', 'ajax-filter-posts' ), icon: 'admin-users' },
			'gridmaster/author-avatar': { title: __( 'Author Avatar', 'ajax-filter-posts' ), icon: 'admin-users' },
			'gridmaster/post-terms': { title: __( 'Categories / Terms', 'ajax-filter-posts' ), icon: 'tag' },
			'gridmaster/post-format-icon': { title: __( 'Format Icon', 'ajax-filter-posts' ), icon: 'video-alt3' },
			'gridmaster/social-share': { title: __( 'Social Share', 'ajax-filter-posts' ), icon: 'share' },
			'gridmaster/badge': { title: __( 'Badge', 'ajax-filter-posts' ), icon: 'tag' },
			'gridmaster/post-meta': { title: __( 'Custom Meta', 'ajax-filter-posts' ), icon: 'admin-generic' },
			'gridmaster/comments-count': { title: __( 'Comments Count', 'ajax-filter-posts' ), icon: 'admin-comments' },
			'gridmaster/shortcode': { title: __( 'Shortcode', 'ajax-filter-posts' ), icon: 'shortcode' },
			'gridmaster/html': { title: __( 'Custom HTML', 'ajax-filter-posts' ), icon: 'html' },
			'core/shortcode': { title: __( 'Shortcode', 'ajax-filter-posts' ), icon: 'shortcode' },
			'core/html': { title: __( 'HTML', 'ajax-filter-posts' ), icon: 'html' },
			'gridmaster-pro/product-price': { title: __( 'Product Price', 'ajax-filter-posts' ), icon: 'money-alt' },
			'gridmaster-pro/add-to-cart': { title: __( 'Add to Cart', 'ajax-filter-posts' ), icon: 'cart' },
			'gridmaster-pro/action-bar': { title: __( 'Action Bar', 'ajax-filter-posts' ), icon: 'screenoptions' },
			'gridmaster-pro/variation-swatches': { title: __( 'Variation Swatches', 'ajax-filter-posts' ), icon: 'art' },
			'gridmaster-pro/product-compare': { title: __( 'Compare Button', 'ajax-filter-posts' ), icon: 'randomize' },
			'gridmaster-pro/request-quote': { title: __( 'Request a Quote', 'ajax-filter-posts' ), icon: 'email-alt' },
			'gridmaster-pro/product-meta': { title: __( 'Product Meta & Specs', 'ajax-filter-posts' ), icon: 'info-outline' },
			'gridmaster-pro/badge': { title: __( 'Badge (Pro)', 'ajax-filter-posts' ), icon: 'star-filled' },
			'gridmaster-pro/product-rating': { title: __( 'Rating (Pro)', 'ajax-filter-posts' ), icon: 'star-filled' },
			'gridmaster-pro/stock-status': { title: __( 'Stock (Pro)', 'ajax-filter-posts' ), icon: 'archive' },
			'gridmaster-pro/sale-countdown': { title: __( 'Countdown (Pro)', 'ajax-filter-posts' ), icon: 'clock' },
			'gridmaster-pro/quick-view': { title: __( 'Quick View (Pro)', 'ajax-filter-posts' ), icon: 'visibility' },
			'gridmaster-pro/video-lightbox': { title: __( 'Video Lightbox (Pro)', 'ajax-filter-posts' ), icon: 'video-alt3' },
			'gridmaster-pro/card-video': { title: __( 'Card Video (Pro)', 'ajax-filter-posts' ), icon: 'video-alt2' },
			'gridmaster-pro/bookmark-button': { title: __( 'Bookmark (Pro)', 'ajax-filter-posts' ), icon: 'heart' },
			'core/group': { title: __( 'Group', 'ajax-filter-posts' ), icon: 'layout' },
			'core/columns': { title: __( 'Columns', 'ajax-filter-posts' ), icon: 'columns' },
			'core/column': { title: __( 'Column', 'ajax-filter-posts' ), icon: 'column' },
			'core/spacer': { title: __( 'Spacer', 'ajax-filter-posts' ), icon: 'image-flip-vertical' },
			'core/separator': { title: __( 'Separator', 'ajax-filter-posts' ), icon: 'minus' },
		};

		const renderBlockRow = ( block, depth = 0 ) => {
			const isSelected = block.clientId === selectedClientId;
			const meta = blockMetaMap[ block.name ] || {
				title: block.name.replace( 'gridmaster/', '' ).replace( 'gridmaster-pro/', '' ).replace( 'core/', '' ),
				icon: 'admin-generic',
			};

			return el(
				Fragment,
				{ key: block.clientId },
				el(
					'button',
					{
						type: 'button',
						className: 'gm-list-view-item' + ( isSelected ? ' is-selected' : '' ),
						'aria-pressed': isSelected,
						style: { paddingLeft: 12 + depth * 16 + 'px' },
						onClick: () => selectBlock( block.clientId ),
					},
					el( 'span', { className: 'dashicons dashicons-' + meta.icon + ' gm-list-icon' } ),
					el( 'span', { className: 'gm-list-title' }, block.attributes?.title || meta.title ),
					isSelected && el( 'span', { className: 'gm-list-active-dot' } )
				),
				block.innerBlocks && block.innerBlocks.map( ( child ) => renderBlockRow( child, depth + 1 ) )
			);
		};

		return el(
			'div',
			{ className: 'gm-list-view-panel' },
			el(
				'div',
				{ className: 'gm-panel-header' },
				el( 'h3', { className: 'gm-panel-title' }, el( 'span', { className: 'dashicons dashicons-list-view' } ), ' ' + __( 'List View', 'ajax-filter-posts' ) ),
				el(
					Button,
					{
						icon: 'no-alt',
						isSmall: true,
						onClick: onClose,
						className: 'gm-panel-close-btn',
						label: __( 'Close List View', 'ajax-filter-posts' ),
					}
				)
			),
			el(
				'div',
				{ className: 'gm-list-view-tree' },
				rootBlocks.length === 0
					? el( 'p', { className: 'gm-empty-notice' }, __( 'No blocks in card.', 'ajax-filter-posts' ) )
					: rootBlocks.map( ( b ) => renderBlockRow( b, 0 ) )
			)
		);
	}

	/** Keep frequent insertion-point updates separate from the canvas tree. */
	function DropGuidance( { paperRef } ) {
		const { isDraggingBlocks, dropRootId, dropTargetName, hasDropPosition, isDropContainerEmpty } = useSelect( ( select ) => {
			const be = select( 'core/block-editor' );
			const point = be.getBlockInsertionPoint();
			const targetName = point?.rootClientId ? be.getBlockName( point.rootClientId ) : null;
			return {
				isDraggingBlocks: be.isDraggingBlocks(),
				dropRootId: point?.rootClientId,
				dropTargetName: targetName ? wp.blocks.getBlockType( targetName )?.title : __( 'Canvas', 'ajax-filter-posts' ),
				hasDropPosition: be.isBlockInsertionPointVisible(),
				isDropContainerEmpty: be.getBlockCount( point?.rootClientId ) === 0,
			};
		} );

		useEffect( () => {
			if ( ! isDraggingBlocks || ! hasDropPosition || ! dropRootId ) return;
			const target = paperRef.current?.querySelector( '[data-block="' + CSS.escape( dropRootId ) + '"]' );
			target?.setAttribute( 'data-gm-drop-target', 'true' );
			return () => target?.removeAttribute( 'data-gm-drop-target' );
		}, [ isDraggingBlocks, hasDropPosition, dropRootId ] );

		useLayoutEffect( () => {
			if ( ! isDraggingBlocks ) return;
			// A short canvas has no scroll container, so Gutenberg otherwise scrolls
			// the admin page. Freeze that page without changing the canvas geometry.
			const { scrollX, scrollY } = window;
			const body = document.body;
			const bounds = body.getBoundingClientRect();
			body.style.setProperty( '--gm-drag-body-top', bounds.top + 'px' );
			body.style.setProperty( '--gm-drag-body-left', bounds.left + 'px' );
			body.style.setProperty( '--gm-drag-body-width', bounds.width + 'px' );
			body.style.setProperty( '--gm-drag-body-height', bounds.height + 'px' );
			body.classList.add( 'gm-builder-dragging' );
			return () => {
				body.classList.remove( 'gm-builder-dragging' );
				[ 'top', 'left', 'width', 'height' ].forEach( ( dimension ) => body.style.removeProperty( '--gm-drag-body-' + dimension ) );
				window.scrollTo( scrollX, scrollY );
			};
		}, [ isDraggingBlocks ] );

		return el( 'div', { className: 'gm-drop-guidance', role: 'status', 'aria-live': 'polite' },
			isDraggingBlocks
				? ( hasDropPosition
					? wp.i18n.sprintf( isDropContainerEmpty
						? __( 'Drop inside the highlighted %s.', 'ajax-filter-posts' )
						: __( 'Drop at the blue line in %s.', 'ajax-filter-posts' ), dropTargetName )
					: __( 'Move over the card to find a drop position.', 'ajax-filter-posts' ) )
				: __( 'Drag by the block handle; follow the blue drop line.', 'ajax-filter-posts' )
		);
	}

	/**
	 * Visual Wireframe schematic for pre-built card layout archetypes.
	 */
	function TemplateWireframe( props ) {
		const { template } = props;
		const id = template?.id || '';

		// 1. TikTok / Reels / Shorts 9:16
		if ( id === 'shorts-vertical-feed' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-reel' },
				el(
					'div',
					{ className: 'gm-wf-reel-phone' },
					el( 'span', { className: 'gm-wf-badge-red' }, 'REEL' ),
					el( 'div', { className: 'gm-wf-play-circle' }, '▶' ),
					el(
						'div',
						{ className: 'gm-wf-reel-bottom' },
						el( 'div', { className: 'gm-wf-avatar' } ),
						el(
							'div',
							{ className: 'gm-wf-lines-compact' },
							el( 'div', { className: 'gm-wf-line-title gm-wf-white' } ),
							el( 'div', { className: 'gm-wf-line-sub gm-wf-dim' } )
						)
					)
				)
			);
		}

		// 2. Video Showcase Course
		if ( id === 'video-showcase-course' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-dark-video' },
				el(
					'div',
					{ className: 'gm-wf-video-hero' },
					el( 'div', { className: 'gm-wf-play-circle-red' }, '▶' )
				),
				el(
					'div',
					{ className: 'gm-wf-video-info' },
					el( 'span', { className: 'gm-wf-badge-yellow' }, 'MEDIUM' ),
					el( 'div', { className: 'gm-wf-line-title gm-wf-white' } )
				)
			);
		}

		// 3. Media Video Lightbox, YouTube Playlist, Instagram Video
		if ( id === 'media-video-lightbox' || id === 'youtube-playlist-card' || id === 'instagram-square-video' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el(
					'div',
					{ className: 'gm-wf-video-thumb' },
					el( 'span', { className: 'dashicons dashicons-video-alt3 gm-wf-video-icon' } ),
					el( 'div', { className: 'gm-wf-play-pill' }, '▶ Play' )
				),
				el(
					'div',
					{ className: 'gm-wf-content-row' },
					el( 'div', { className: 'gm-wf-avatar-mini' } ),
					el(
						'div',
						{ className: 'gm-wf-lines-compact' },
						el( 'div', { className: 'gm-wf-line-title' } ),
						el( 'div', { className: 'gm-wf-line-sub' } )
					)
				)
			);
		}

		// 4. Podcast & Audio Episode
		if ( id === 'podcast-episode' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'span', { className: 'gm-wf-badge-purple' }, 'PODCAST' ),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'div', { className: 'gm-wf-btn-purple' }, '▶ Listen' )
				)
			);
		}

		// 5. Hero Overlay
		if ( id === 'hero-overlay' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-hero-overlay' },
				el( 'span', { className: 'gm-wf-badge-blue' }, 'FEATURED' ),
				el(
					'div',
					null,
					el( 'div', { className: 'gm-wf-line-title gm-wf-white' } ),
					el( 'div', { className: 'gm-wf-btn-pill-white' } )
				)
			);
		}

		// 6. Dark Slate Aesthetic
		if ( id === 'card-dark-mode' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-dark-slate' },
				el( 'div', { className: 'gm-wf-image-dark' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-badge-cyan' }, 'TECH' ),
					el( 'div', { className: 'gm-wf-line-short gm-wf-dim' } )
				),
				el( 'div', { className: 'gm-wf-line-title gm-wf-white' } )
			);
		}

		// 7. Horizontal List (Media Left)
		if ( id === 'horizontal-list' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el( 'div', { className: 'gm-wf-side-media' } ),
				el(
					'div',
					{ className: 'gm-wf-side-col' },
					el( 'span', { className: 'gm-wf-badge-blue' }, 'CATEGORY' ),
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'div', { className: 'gm-wf-line-sub' } )
				)
			);
		}

		// 8. Horizontal List (Media Right)
		if ( id === 'horizontal-reverse' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el(
					'div',
					{ className: 'gm-wf-side-col' },
					el( 'span', { className: 'gm-wf-badge-blue' }, 'CATEGORY' ),
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'div', { className: 'gm-wf-line-sub' } )
				),
				el( 'div', { className: 'gm-wf-side-media' } )
			);
		}

		// 9. Split 50/50 Feature Card
		if ( id === 'split-alternating' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el( 'div', { className: 'gm-wf-half-media' } ),
				el(
					'div',
					{ className: 'gm-wf-half-content' },
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'div', { className: 'gm-wf-line-sub' } ),
					el( 'div', { className: 'gm-wf-btn-blue' } )
				)
			);
		}

		// 10. Compact List Row & Ultra Compact Feed Row
		if ( id === 'compact-list-row' || id === 'ultra-compact-ticker' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el( 'div', { className: 'gm-wf-thumb-square' } ),
				el(
					'div',
					{ className: 'gm-wf-compact-lines' },
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'div', { className: 'gm-wf-line-short' } )
				)
			);
		}

		// 11. Minimalist Text-Only
		if ( id === 'minimalist-text' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-minimalist' },
				el( 'span', { className: 'gm-wf-badge-gray' }, 'ESSAY' ),
				el( 'div', { className: 'gm-wf-line-title-lg' } ),
				el( 'div', { className: 'gm-wf-line-sub' } ),
				el( 'div', { className: 'gm-wf-line-link' }, 'Read Article →' )
			);
		}

		// 12. Floating Overlapping Box
		if ( id === 'floating-content-box' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-floating-wrap' },
				el( 'div', { className: 'gm-wf-float-image' } ),
				el(
					'div',
					{ className: 'gm-wf-float-card' },
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'div', { className: 'gm-wf-line-short' } )
				)
			);
		}

		// 13. Centered Aesthetic
		if ( id === 'centered-aesthetic' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-centered' },
				el( 'div', { className: 'gm-wf-image-centered' } ),
				el( 'div', { className: 'gm-wf-line-title gm-wf-center' } ),
				el( 'div', { className: 'gm-wf-line-sub gm-wf-center' } )
			);
		}

		// 14. Date-First Magazine
		if ( id === 'date-badge-top' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'div', { className: 'gm-wf-date-row' }, 'Thursday, Nov 12, 2026' ),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } )
			);
		}

		// 15. Legacy Style 1 & 4 (Bottom meta bar)
		if ( id === 'legacy-style-1' || id === 'legacy-style-4' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el(
					'div',
					{ className: 'gm-wf-meta-bar' },
					el( 'span', { className: 'gm-wf-line-short' } ),
					el( 'span', { className: 'gm-wf-icon-comment' }, '💬' )
				)
			);
		}

		// 16. Masonry Story & Portfolio Showcase
		if ( id === 'masonry-story' || id === 'portfolio-showcase' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'div', { className: 'gm-wf-image-tall' } ),
				el( 'div', { className: 'gm-wf-line-title' } )
			);
		}

		// 17. Flash Sale & Countdown
		if ( id === 'ecommerce-flash-sale' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-flash-sale' },
				el( 'span', { className: 'gm-wf-badge-red' }, '⚡ SALE DEAL' ),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el(
					'div',
					{ className: 'gm-wf-countdown-row' },
					el( 'div', { className: 'gm-wf-countdown-box' }, '12' ),
					el( 'div', { className: 'gm-wf-countdown-box' }, '34' ),
					el( 'div', { className: 'gm-wf-countdown-box' }, '56' )
				),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-price-red' }, '$39.00' ),
					el( 'span', { className: 'gm-wf-btn-red' }, 'Claim' )
				)
			);
		}

		// 18. Fashion & Apparel Hover Card
		if ( id === 'ecommerce-fashion-hover' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el(
					'div',
					{ className: 'gm-wf-media-portrait' },
					el(
						'div',
						{ className: 'gm-wf-floating-bar' },
						el( 'span', null, '♥' ),
						el( 'span', null, '👁' )
					)
				),
				el(
					'div',
					{ className: 'gm-wf-side-col' },
					el( 'div', { className: 'gm-wf-line-title' } ),
					el( 'span', { className: 'gm-wf-price' }, '$89.00' ),
					el(
						'div',
						{ className: 'gm-wf-swatches' },
						el( 'span', { className: 'gm-wf-swatch-1' } ),
						el( 'span', { className: 'gm-wf-swatch-2' } ),
						el( 'span', { className: 'gm-wf-swatch-3' } )
					)
				)
			);
		}

		// 19. Wholesale / B2B Quick-Order Row (Clean horizontal alignment)
		if ( id === 'ecommerce-b2b-wholesale-row' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el( 'div', { className: 'gm-wf-side-media-sm' } ),
				el(
					'div',
					{ className: 'gm-wf-side-col' },
					el( 'span', { className: 'gm-wf-badge-gray' }, 'SKU-892' ),
					el( 'div', { className: 'gm-wf-line-title' } ),
					el(
						'div',
						{ className: 'gm-wf-btn-group' },
						el( 'span', { className: 'gm-wf-stepper' }, '− 1 +' ),
						el( 'span', { className: 'gm-wf-btn-quote' }, 'Quote' )
					)
				)
			);
		}

		// 20. B2B Catalog & Quote Card (Industrial quote card, no prices)
		if ( id === 'ecommerce-b2b-quote-catalog' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'span', { className: 'gm-wf-badge-gray' }, 'SKU • SPECS' ),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el( 'div', { className: 'gm-wf-btn-full-blue' }, 'Request a Quote' )
			);
		}

		// 21. Product Horizontal Row (Pro)
		if ( id === 'ecommerce-horizontal-row' ) {
			return el(
				'div',
				{ className: 'gm-wf-box gm-wf-row' },
				el( 'div', { className: 'gm-wf-side-media' } ),
				el(
					'div',
					{ className: 'gm-wf-side-col' },
					el( 'div', { className: 'gm-wf-stars' }, '★★★★★' ),
					el( 'div', { className: 'gm-wf-line-title' } ),
					el(
						'div',
						{ className: 'gm-wf-row-space' },
						el( 'span', { className: 'gm-wf-price' }, '$39.00' ),
						el( 'span', { className: 'gm-wf-btn-cart' }, '+ Cart' )
					)
				)
			);
		}

		// 22. Grocery / Instant-Stepper Card
		if ( id === 'ecommerce-grocery-stepper' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-badge-green' }, '−20%' ),
					el( 'span', { className: 'gm-wf-badge-gray' }, '500g' )
				),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-price' }, '$4.99' ),
					el( 'span', { className: 'gm-wf-stepper-green' }, '− 2 +' )
				)
			);
		}

		// 23. Electronics & Tech Spec Card
		if ( id === 'ecommerce-electronics-spec' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-badge-gray' }, 'SKU-PRO' ),
					el( 'span', { className: 'gm-wf-badge-cyan' }, 'BRAND' )
				),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-stars' }, '★★★★★' ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-price' }, '$599' ),
					el( 'span', { className: 'gm-wf-btn-cart' }, '+ Cart' )
				)
			);
		}

		// 24. Wishlist & Favorites Card
		if ( id === 'ecommerce-wishlist-showcase' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'div', { className: 'gm-wf-stars' }, '★★★★★' ),
					el( 'span', { className: 'gm-wf-heart' }, '♥' )
				),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-price' }, '$49.00' ),
					el( 'span', { className: 'gm-wf-btn-cart' }, '+ Cart' )
				)
			);
		}

		// 25. Quick View Product Card
		if ( id === 'ecommerce-quick-view' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'span', { className: 'gm-wf-badge-red' }, 'SALE' ),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-price' }, '$49.00' ),
					el( 'span', { className: 'gm-wf-btn-quick' }, 'Quick View' )
				)
			);
		}

		// 26. Standard WooCommerce Product
		if ( id === 'ecommerce-product' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'span', { className: 'gm-wf-badge-red' }, 'SALE' ),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-stars' }, '★★★★★' ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-price' }, '$49.00' ),
					el( 'span', { className: 'gm-wf-btn-cart' }, '+ Cart' )
				)
			);
		}

		// 27. Editorial & Magazine / Modern Tech
		if ( id === 'editorial-magazine' || id === 'modern-tech' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el( 'div', { className: 'gm-wf-image-box' } ),
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'div', { className: 'gm-wf-avatar-mini' } ),
					el( 'div', { className: 'gm-wf-line-short' } )
				),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el( 'div', { className: 'gm-wf-line-sub' } )
			);
		}

		// 28. Social Bookmark Card
		if ( id === 'social-bookmark-card' ) {
			return el(
				'div',
				{ className: 'gm-wf-box' },
				el(
					'div',
					{ className: 'gm-wf-row-space' },
					el( 'span', { className: 'gm-wf-badge-blue' }, 'SOCIAL' ),
					el( 'span', { className: 'gm-wf-heart' }, '♥' )
				),
				el( 'div', { className: 'gm-wf-image-box' } ),
				el( 'div', { className: 'gm-wf-line-title' } ),
				el( 'div', { className: 'gm-wf-share-dots' }, '● ● ●' )
			);
		}

		// Default / Standard Vertical Card
		return el(
			'div',
			{ className: 'gm-wf-box' },
			el( 'div', { className: 'gm-wf-image-box' } ),
			el( 'div', { className: 'gm-wf-line-title' } ),
			el( 'div', { className: 'gm-wf-line-sub' } ),
			el( 'div', { className: 'gm-wf-btn-read-more' } )
		);
	}

	/**
	 * Card Templates Popover component: instant pre-built layouts (horizontal, vertical, overlay, etc.)
	 */
	function CardTemplatesPopover( props ) {
		const { isOpen, onClose, onApplyTemplate, isPro } = props;
		const [ searchTerm, setSearchTerm ] = useState( '' );
		const [ activeCat, setActiveCat ] = useState( 'All' );

		if ( ! isOpen ) return null;

		const categories = [ 'All', 'Video & Reels', 'Editorial', 'List Views', 'Creative', 'WooCommerce', 'Social' ];

		const templates = [
			// 1. Standard Vertical Card
			{
				id: 'vertical-standard',
				title: __( 'Standard Vertical Card', 'ajax-filter-posts' ),
				desc: __( 'Classic blog card with 16:9 featured image on top, title, date, excerpt and read more.', 'ajax-filter-posts' ),
				icon: 'format-aside',
				category: 'Editorial',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '8px', borderWidth: '0px', boxShadow: '0 2px 4px rgba(0,0,0,0.05)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '6px' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', isLink: true } ),
					createBlock( 'gridmaster/post-date', { format: 'F j, Y' } ),
					createBlock( 'gridmaster/excerpt', { length: 20 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Read More', 'ajax-filter-posts' ) } ),
				],
			},
			// 2. Horizontal List (Media Left)
			{
				id: 'horizontal-list',
				title: __( 'Horizontal List (Media Left)', 'ajax-filter-posts' ),
				desc: __( 'Side-by-side list view with image on the left (35%) and content column on the right.', 'ajax-filter-posts' ),
				icon: 'align-left',
				category: 'List Views',
				cardAttrs: { layoutMode: 'horizontal', mediaWidth: '35%', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '4/3', borderRadius: '8px', zoomOnHover: true } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '16px', right: '0px' } } } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '18px', isLink: true } ),
						createBlock( 'gridmaster/excerpt', { length: 16 } ),
						createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
							createBlock( 'gridmaster/post-date', { format: 'M d, Y' } ),
							createBlock( 'gridmaster/reading-time', {} ),
						] ),
					] ),
				],
			},
			// 3. Horizontal List (Media Right)
			{
				id: 'horizontal-reverse',
				title: __( 'Horizontal List (Media Right)', 'ajax-filter-posts' ),
				desc: __( 'Reversed list view with content on the left and image on the right.', 'ajax-filter-posts' ),
				icon: 'align-right',
				category: 'List Views',
				cardAttrs: { layoutMode: 'horizontal-reverse', mediaWidth: '35%', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '4/3', borderRadius: '8px' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '0px', right: '16px' } } } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '18px', isLink: true } ),
						createBlock( 'gridmaster/excerpt', { length: 16 } ),
						createBlock( 'gridmaster/post-date', { format: 'M d, Y' } ),
					] ),
				],
			},
			// 4. Hero Overlay (Cover Image)
			{
				id: 'hero-overlay',
				title: __( 'Hero Overlay (Cover Image)', 'ajax-filter-posts' ),
				desc: __( 'Full bleed background image with dark scrim gradient and text overlay.', 'ajax-filter-posts' ),
				icon: 'cover-image',
				category: 'Creative',
				cardAttrs: { layoutMode: 'overlay', minHeight: '360px', padding: '24px', borderRadius: '12px', borderWidth: '0px', boxShadow: '0 8px 24px rgba(0,0,0,0.15)', backgroundColor: '#1e293b', hoverEffect: 'zoom' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'large', aspectRatio: 'auto', objectFit: 'cover' } ),
					createBlock( 'gridmaster/badge', { text: __( 'Featured', 'ajax-filter-posts' ), backgroundColor: '#2563eb', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '22px', color: '#ffffff', isLink: true } ),
					createBlock( 'gridmaster/post-date', { format: 'F j, Y', color: '#cbd5e1' } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Explore Post', 'ajax-filter-posts' ), backgroundColor: '#ffffff', textColor: '#0f172a', borderRadius: '20px' } ),
				],
			},
			// 5. Editorial & Magazine Card
			{
				id: 'editorial-magazine',
				title: __( 'Editorial & Magazine Card', 'ajax-filter-posts' ),
				desc: __( 'Category badge, bold title, author avatar byline row, excerpt, and social share bar.', 'ajax-filter-posts' ),
				icon: 'media-document',
				category: 'Editorial',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.04)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/badge', { text: __( 'Editorial', 'ajax-filter-posts' ), backgroundColor: '#f1f5f9', textColor: '#475569' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '22px', isLink: true } ),
					createBlock( 'core/group', { layout: { type: 'flex', alignItems: 'center' } }, [
						createBlock( 'gridmaster/author-avatar', { avatarSize: 32, borderRadius: '50%' } ),
						createBlock( 'gridmaster/post-author', {} ),
						createBlock( 'gridmaster/reading-time', {} ),
					] ),
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px', zoomOnHover: true } ),
					createBlock( 'gridmaster/excerpt', { length: 22 } ),
					createBlock( 'gridmaster/social-share', {} ),
				],
			},
			// 6. Modern Tech & SaaS
			{
				id: 'modern-tech',
				title: __( 'Modern Tech / SaaS Card', 'ajax-filter-posts' ),
				desc: __( 'Category pill badge, reading time, bold title, author byline row, and clean hover lift.', 'ajax-filter-posts' ),
				icon: 'laptop',
				category: 'Editorial',
				cardAttrs: { layoutMode: 'vertical', padding: '22px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 10px 25px rgba(0,0,0,0.05)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px', zoomOnHover: true } ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster/reading-time', {} ),
					] ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 18 } ),
					createBlock( 'core/group', { layout: { type: 'flex', alignItems: 'center' } }, [
						createBlock( 'gridmaster/author-avatar', { avatarSize: 28, borderRadius: '50%' } ),
						createBlock( 'gridmaster/post-author', {} ),
						createBlock( 'gridmaster/post-date', { format: 'M j, Y' } ),
					] ),
				],
			},
			// 7. Compact List Row (Sidebar / Dense)
			{
				id: 'compact-list-row',
				title: __( 'Compact List Row', 'ajax-filter-posts' ),
				desc: __( 'Minimal horizontal row with square thumbnail, title, and date for dense news feeds or sidebars.', 'ajax-filter-posts' ),
				icon: 'menu-alt',
				category: 'List Views',
				cardAttrs: { layoutMode: 'horizontal', mediaWidth: '85px', padding: '10px', borderRadius: '6px', borderWidth: '0px', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'thumbnail', aspectRatio: '1/1', borderRadius: '6px' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '12px', right: '0px' } } } }, [
						createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '14px', isLink: true } ),
						createBlock( 'gridmaster/post-date', { fontSize: '12px', format: 'M j, Y' } ),
					] ),
				],
			},
			// 8. Ultra Compact Feed Row
			{
				id: 'ultra-compact-ticker',
				title: __( 'Ultra Compact Feed Row', 'ajax-filter-posts' ),
				desc: __( '60px rounded thumbnail with category pill and inline title for ticker or sidebar lists.', 'ajax-filter-posts' ),
				icon: 'list-view',
				category: 'List Views',
				cardAttrs: { layoutMode: 'horizontal', mediaWidth: '60px', padding: '8px 12px', borderRadius: '6px', borderWidth: '1px', borderColor: '#f1f5f9', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'thumbnail', aspectRatio: '1/1', borderRadius: '4px' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '10px', right: '0px' } } } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '13px', isLink: true } ),
					] ),
				],
			},
			// 9. Split 50/50 Feature Card
			{
				id: 'split-alternating',
				title: __( 'Split 50/50 Feature Card', 'ajax-filter-posts' ),
				desc: __( 'Side-by-side 50/50 split with generous padding, large heading, excerpt, and action button.', 'ajax-filter-posts' ),
				icon: 'columns',
				category: 'List Views',
				cardAttrs: { layoutMode: 'horizontal', mediaWidth: '50%', padding: '24px', borderRadius: '14px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 16px rgba(0,0,0,0.06)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'large', aspectRatio: '4/3', borderRadius: '10px' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '24px', right: '0px' } } } }, [
						createBlock( 'gridmaster/badge', { text: __( 'Featured Story', 'ajax-filter-posts' ), backgroundColor: '#eff6ff', textColor: '#1d4ed8' } ),
						createBlock( 'gridmaster/title', { tagName: 'h2', fontSize: '24px', isLink: true } ),
						createBlock( 'gridmaster/excerpt', { length: 25 } ),
						createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
							createBlock( 'gridmaster/post-date', { format: 'F j, Y' } ),
							createBlock( 'gridmaster/reading-time', {} ),
						] ),
						createBlock( 'gridmaster/read-more', { text: __( 'Continue Story', 'ajax-filter-posts' ), borderRadius: '6px' } ),
					] ),
				],
			},
			// 10. Minimalist Text-Only (Opinion / Essay)
			{
				id: 'minimalist-text',
				title: __( 'Minimalist Text-Only', 'ajax-filter-posts' ),
				desc: __( 'Text-driven opinion/essay card without featured image.', 'ajax-filter-posts' ),
				icon: 'text',
				category: 'Editorial',
				cardAttrs: { layoutMode: 'vertical', padding: '24px', borderRadius: '8px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '22px', isLink: true } ),
					createBlock( 'gridmaster/post-date', { format: 'F j, Y' } ),
					createBlock( 'gridmaster/excerpt', { length: 28 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Read Full Article', 'ajax-filter-posts' ) } ),
				],
			},
			// 11. Floating Overlapping Box (Legacy Style 2)
			{
				id: 'floating-content-box',
				title: __( 'Floating Overlapping Box (Legacy Style 2)', 'ajax-filter-posts' ),
				desc: __( 'Featured image with overlapping white content box and centered typography.', 'ajax-filter-posts' ),
				icon: 'format-aside',
				category: 'Creative',
				cardAttrs: { layoutMode: 'vertical', padding: '0px', borderRadius: '12px', borderWidth: '0px', boxShadow: '0 4px 20px rgba(0,0,0,0.08)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '12px 12px 0 0' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '24px', bottom: '24px', left: '24px', right: '24px' } } } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', isLink: true } ),
						createBlock( 'gridmaster/excerpt', { length: 18 } ),
						createBlock( 'gridmaster/read-more', { text: __( 'Continue Reading', 'ajax-filter-posts' ), borderRadius: '4px' } ),
					] ),
				],
			},
			// 12. Centered Aesthetic (Legacy Style 3)
			{
				id: 'centered-aesthetic',
				title: __( 'Centered Aesthetic (Legacy Style 3)', 'ajax-filter-posts' ),
				desc: __( 'Tall 4:3 rounded image, centered title, excerpt, and styled read more link.', 'ajax-filter-posts' ),
				icon: 'align-center',
				category: 'Creative',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '16px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '4/3', borderRadius: '12px', zoomOnHover: true } ),
					createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 16 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Read Story →', 'ajax-filter-posts' ), backgroundColor: 'transparent', textColor: '#2563eb' } ),
				],
			},
			// 13. Bottom Meta Bar (Legacy Style 1)
			{
				id: 'legacy-style-1',
				title: __( 'Bottom Meta Bar (Legacy Style 1)', 'ajax-filter-posts' ),
				desc: __( 'Featured image, title, excerpt, read more, and separate bottom metadata divider bar.', 'ajax-filter-posts' ),
				icon: 'feedback',
				category: 'Creative',
				cardAttrs: { layoutMode: 'vertical', padding: '18px 18px 0 18px', borderRadius: '8px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 15px rgba(0,0,0,0.05)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '6px' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '18px', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 16 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Continue Reading', 'ajax-filter-posts' ) } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '12px', bottom: '12px' } } }, layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-date', { format: 'M d, Y' } ),
						createBlock( 'gridmaster/comments-count', {} ),
					] ),
				],
			},
			// 14. Inset Border Card (Legacy Style 4)
			{
				id: 'legacy-style-4',
				title: __( 'Inset Border Card (Legacy Style 4)', 'ajax-filter-posts' ),
				desc: __( 'Bordered card with inset depth, title first, author & comments meta, excerpt, and button.', 'ajax-filter-posts' ),
				icon: 'admin-appearance',
				category: 'Creative',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '14px', borderWidth: '1px', borderColor: '#cbd5e1', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '21px', isLink: true } ),
					createBlock( 'core/group', { layout: { type: 'flex', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-date', { format: 'M d, Y' } ),
						createBlock( 'gridmaster/post-author', {} ),
						createBlock( 'gridmaster/comments-count', {} ),
					] ),
					createBlock( 'gridmaster/excerpt', { length: 18 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'View Full Post', 'ajax-filter-posts' ) } ),
				],
			},
			// 15. Date-First Magazine (Legacy Style 9)
			{
				id: 'date-badge-top',
				title: __( 'Date-First Magazine (Legacy Style 9)', 'ajax-filter-posts' ),
				desc: __( 'Prominent formatted date header at top, category badge, image, and read more.', 'ajax-filter-posts' ),
				icon: 'calendar-alt',
				category: 'Editorial',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '8px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/post-date', { format: 'l, F jS Y', fontSize: '13px', color: '#64748b' } ),
					createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', isLink: true } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '6px' } ),
					createBlock( 'gridmaster/excerpt', { length: 20 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Read More', 'ajax-filter-posts' ) } ),
				],
			},
			// 16. Dark Slate Aesthetic
			{
				id: 'card-dark-mode',
				title: __( 'Dark Slate Aesthetic', 'ajax-filter-posts' ),
				desc: __( 'Deep midnight slate card with blue glow hover, white typography, and category pill.', 'ajax-filter-posts' ),
				icon: 'star-filled',
				category: 'Creative',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '12px', borderWidth: '1px', borderColor: '#334155', boxShadow: '0 8px 24px rgba(0,0,0,0.4)', backgroundColor: '#0f172a', hoverEffect: 'glow' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px' } ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/badge', { text: __( 'Tech', 'ajax-filter-posts' ), backgroundColor: '#1e293b', textColor: '#38bdf8' } ),
						createBlock( 'gridmaster/reading-time', {} ),
					] ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', color: '#f8fafc', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 16, color: '#cbd5e1' } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Explore Article', 'ajax-filter-posts' ), backgroundColor: '#2563eb', textColor: '#ffffff', borderRadius: '6px' } ),
				],
			},
			// 17. Masonry Story Card
			{
				id: 'masonry-story',
				title: __( 'Masonry Story Card', 'ajax-filter-posts' ),
				desc: __( 'Variable-height card with natural ratio photo, full story excerpt, date, and comments.', 'ajax-filter-posts' ),
				icon: 'images-alt2',
				category: 'Editorial',
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'large', aspectRatio: 'auto', borderRadius: '8px' } ),
					createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '19px', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 30 } ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-date', { format: 'M d, Y' } ),
						createBlock( 'gridmaster/comments-count', {} ),
					] ),
				],
			},
			// 18. Creative Portfolio & Project
			{
				id: 'portfolio-showcase',
				title: __( 'Creative Portfolio & Project', 'ajax-filter-posts' ),
				desc: __( '3:2 media aspect ratio with clean zoom effect, project title, categories, and direct action link.', 'ajax-filter-posts' ),
				icon: 'portfolio',
				category: 'Creative',
				cardAttrs: { layoutMode: 'vertical', padding: '16px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 14px rgba(0,0,0,0.05)', backgroundColor: '#ffffff', hoverEffect: 'zoom' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'large', aspectRatio: '3/2', borderRadius: '8px', zoomOnHover: true } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '18px', isLink: true } ),
					createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					createBlock( 'gridmaster/read-more', { text: __( 'View Project →', 'ajax-filter-posts' ), backgroundColor: 'transparent', textColor: '#0f172a' } ),
				],
			},
			// 19. E-Commerce Product Starter (Pro)
			{
				id: 'ecommerce-product',
				title: __( 'E-Commerce Product Starter (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Sale badge, 1:1 product thumbnail, product category, title, price, and AJAX Add to Cart button.', 'ajax-filter-posts' ),
				icon: 'cart',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/badge', { badgeType: 'sale', text: __( 'Sale!', 'ajax-filter-posts' ), backgroundColor: '#ef4444', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '6px' } ),
					createBlock( 'gridmaster/post-terms', { taxonomy: 'product_cat' } ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '16px', isLink: true } ),
					createBlock( 'gridmaster-pro/product-price', {} ),
					createBlock( 'gridmaster-pro/add-to-cart', { buttonText: __( 'Add to Cart', 'ajax-filter-posts' ), fullWidth: true } ),
				],
			},
			// 20. Product Quick View & Badge (Pro)
			{
				id: 'ecommerce-quick-view',
				title: __( 'Product Quick View & Badge (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Product badge, 1:1 image, quick view modal trigger button, title, and price.', 'ajax-filter-posts' ),
				icon: 'visibility',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.05)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/badge', { badgeType: 'sale', text: __( 'Sale', 'ajax-filter-posts' ), backgroundColor: '#ef4444' } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '6px' } ),
					createBlock( 'gridmaster-pro/quick-view', { buttonText: __( 'Quick View', 'ajax-filter-posts' ) } ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '16px', isLink: true } ),
					createBlock( 'gridmaster-pro/product-price', {} ),
				],
			},
			// 21. Product Horizontal Row (Pro)
			{
				id: 'ecommerce-horizontal-row',
				title: __( 'Product Horizontal Row (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Side-by-side product row with 30% square image, star rating, price, and Add to Cart.', 'ajax-filter-posts' ),
				icon: 'align-left',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'horizontal', mediaWidth: '30%', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '8px' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '16px', right: '0px' } } } }, [
						createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '18px', isLink: true } ),
						createBlock( 'gridmaster-pro/product-rating', {} ),
						createBlock( 'gridmaster-pro/product-price', {} ),
						createBlock( 'gridmaster-pro/stock-status', {} ),
						createBlock( 'gridmaster-pro/add-to-cart', { buttonText: __( 'Add to Cart', 'ajax-filter-posts' ) } ),
					] ),
				],
			},
			// 22. Flash Sale & Countdown (Pro)
			{
				id: 'ecommerce-flash-sale',
				title: __( 'Flash Sale & Countdown (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Urgency-driven deal card with discount % badge, countdown timer boxes, stock progress bar, and Add to Cart.', 'ajax-filter-posts' ),
				icon: 'clock',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '18px', borderRadius: '12px', borderWidth: '2px', borderColor: '#ef4444', boxShadow: '0 4px 20px rgba(239,68,68,0.1)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/badge', { badgeType: 'discount_percent', text: __( '⚡ LIMITED DEAL', 'ajax-filter-posts' ), backgroundColor: '#ef4444', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '6px' } ),
					createBlock( 'gridmaster-pro/sale-countdown', { layout: 'boxes', backgroundColor: '#1e293b', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '17px', isLink: true } ),
					createBlock( 'gridmaster-pro/product-price', {} ),
					createBlock( 'gridmaster-pro/stock-status', { showProgressBar: true } ),
					createBlock( 'gridmaster-pro/add-to-cart', { buttonText: __( 'Claim Deal', 'ajax-filter-posts' ), fullWidth: true } ),
				],
			},
			// 23. Fashion & Apparel Hover Card (Pro)
			{
				id: 'ecommerce-fashion-hover',
				title: __( 'Fashion & Apparel Hover Card (Pro)', 'ajax-filter-posts' ),
				desc: __( '3:4 portrait fashion image with 2nd gallery image hover swap, floating action bar (Wishlist, Quick View, Compare), title, price, and variation swatches.', 'ajax-filter-posts' ),
				icon: 'visibility',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '14px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 14px rgba(0,0,0,0.04)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '3/4', borderRadius: '8px', wooHoverGalleryImage: true } ),
					createBlock( 'gridmaster-pro/action-bar', { position: 'top-right-vertical', showWishlist: true, showQuickView: true, showCompare: true } ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '16px', isLink: true } ),
					createBlock( 'gridmaster-pro/product-price', {} ),
					createBlock( 'gridmaster-pro/variation-swatches', {} ),
				],
			},
			// 24. Electronics & Tech Spec Card (Pro)
			{
				id: 'ecommerce-electronics-spec',
				title: __( 'Electronics & Tech Spec Card (Pro)', 'ajax-filter-posts' ),
				desc: __( '1:1 product media, SKU & Brand specs, star rating, title, stock status, price with discount badge, and Add to Cart + Compare row.', 'ajax-filter-posts' ),
				icon: 'laptop',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '18px', borderRadius: '12px', borderWidth: '1px', borderColor: '#cbd5e1', boxShadow: '0 2px 10px rgba(0,0,0,0.04)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '6px' } ),
					createBlock( 'gridmaster-pro/product-meta', { showSku: true, showBrand: true, showAttributes: true } ),
					createBlock( 'gridmaster-pro/product-rating', {} ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '16px', isLink: true } ),
					createBlock( 'gridmaster-pro/stock-status', {} ),
					createBlock( 'gridmaster-pro/product-price', { showDiscountBadge: true } ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster-pro/add-to-cart', { buttonText: __( 'Add to Cart', 'ajax-filter-posts' ) } ),
						createBlock( 'gridmaster-pro/product-compare', {} ),
					] ),
				],
			},
			// 25. Wholesale / B2B Quick-Order Row (Pro)
			{
				id: 'ecommerce-b2b-wholesale-row',
				title: __( 'Wholesale / B2B Quick-Order Row (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Horizontal B2B quick-order row (22% media) with SKU & Brand, variation swatches, stock status, quantity stepper Add to Cart, and Request a Quote.', 'ajax-filter-posts' ),
				icon: 'list-view',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'horizontal', mediaWidth: '22%', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '6px' } ),
					createBlock( 'core/group', { style: { spacing: { padding: { top: '0px', bottom: '0px', left: '16px', right: '0px' } } } }, [
						createBlock( 'gridmaster-pro/product-meta', { showSku: true, showBrand: true } ),
						createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '17px', isLink: true } ),
						createBlock( 'gridmaster-pro/variation-swatches', {} ),
						createBlock( 'gridmaster-pro/stock-status', {} ),
						createBlock( 'gridmaster-pro/product-price', {} ),
						createBlock( 'core/group', { layout: { type: 'flex', gap: '10px', alignItems: 'center' } }, [
							createBlock( 'gridmaster-pro/add-to-cart', { showQuantityStepper: true } ),
							createBlock( 'gridmaster-pro/request-quote', {} ),
						] ),
					] ),
				],
			},
			// 26. Grocery / Instant-Stepper Card (Pro)
			{
				id: 'ecommerce-grocery-stepper',
				title: __( 'Grocery / Instant-Stepper Card (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Discount % badge, 1:1 image, weight/dimensions meta, product title, price, and full-width quantity stepper Add to Cart.', 'ajax-filter-posts' ),
				icon: 'cart',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '14px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 2px 6px rgba(0,0,0,0.03)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/badge', { badgeType: 'discount_percent', backgroundColor: '#16a34a', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '8px' } ),
					createBlock( 'gridmaster-pro/product-meta', { showWeightDimensions: true, showSku: false, showBrand: false } ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '15px', isLink: true } ),
					createBlock( 'gridmaster-pro/product-price', {} ),
					createBlock( 'gridmaster-pro/add-to-cart', { showQuantityStepper: true, fullWidth: true } ),
				],
			},
			// 27. B2B Catalog & Quote Card (Pro)
			{
				id: 'ecommerce-b2b-quote-catalog',
				title: __( 'B2B Catalog & Quote Card (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Price-free B2B industrial catalog card with 4:3 image, SKU & spec attributes, short excerpt, and full-width Request a Quote button.', 'ajax-filter-posts' ),
				icon: 'email-alt',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '18px', borderRadius: '10px', borderWidth: '1px', borderColor: '#cbd5e1', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '4/3', borderRadius: '6px' } ),
					createBlock( 'gridmaster-pro/product-meta', { showSku: true, showAttributes: true } ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '17px', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 14 } ),
					createBlock( 'gridmaster-pro/request-quote', { buttonText: __( 'Request a Quote', 'ajax-filter-posts' ), fullWidth: true } ),
				],
			},
			// 28. Wishlist & Favorites Card (Pro)
			{
				id: 'ecommerce-wishlist-showcase',
				title: __( 'Wishlist & Favorites Card (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Heart wishlist toggle button, 1:1 product photo, star rating, title, price, stock status, and full-width Add to Cart button.', 'ajax-filter-posts' ),
				icon: 'heart',
				category: 'WooCommerce',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '16px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.05)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/bookmark-button', { styleType: 'heart' } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium', aspectRatio: '1/1', borderRadius: '8px' } ),
					createBlock( 'gridmaster-pro/product-rating', {} ),
					createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '16px', isLink: true } ),
					createBlock( 'gridmaster-pro/product-price', {} ),
					createBlock( 'gridmaster-pro/stock-status', {} ),
					createBlock( 'gridmaster-pro/add-to-cart', { fullWidth: true } ),
				],
			},
			// 23. Video & Media Spotlight (Pro)
			{
				id: 'media-video-lightbox',
				title: __( 'Video & Media Spotlight (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Format icon, featured media, video lightbox trigger popup, and reading time.', 'ajax-filter-posts' ),
				icon: 'video-alt3',
				category: 'Video & Reels',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '18px', borderRadius: '12px', borderWidth: '0px', boxShadow: '0 8px 24px rgba(0,0,0,0.08)', backgroundColor: '#ffffff', hoverEffect: 'zoom' },
				getBlocks: () => [
					createBlock( 'gridmaster/post-format-icon', {} ),
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px' } ),
					createBlock( 'gridmaster-pro/video-lightbox', { buttonText: __( 'Watch Video', 'ajax-filter-posts' ) } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '18px', isLink: true } ),
					createBlock( 'gridmaster/reading-time', {} ),
				],
			},
			// 24. Podcast & Audio Episode (Pro)
			{
				id: 'podcast-episode',
				title: __( 'Podcast & Audio Episode (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Audio episode badge, media thumbnail, episode title, host author, and listen button.', 'ajax-filter-posts' ),
				icon: 'controls-play',
				category: 'Video & Reels',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster/badge', { text: __( '🎧 PODCAST EPISODE', 'ajax-filter-posts' ), backgroundColor: '#8b5cf6', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '19px', isLink: true } ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-author', {} ),
						createBlock( 'gridmaster/post-date', { format: 'M j, Y' } ),
					] ),
					createBlock( 'gridmaster/read-more', { text: __( '▶ Listen Episode', 'ajax-filter-posts' ), backgroundColor: '#8b5cf6', textColor: '#ffffff', borderRadius: '6px' } ),
				],
			},
			// 25. Social & Wishlist Card (Pro)
			{
				id: 'social-bookmark-card',
				title: __( 'Social & Wishlist Card (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Category badge and bookmark toggle in top header row, image, title, and social share bar.', 'ajax-filter-posts' ),
				icon: 'heart',
				category: 'Social',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '18px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: 'none', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster-pro/bookmark-button', {} ),
					] ),
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '6px' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '18px', isLink: true } ),
					createBlock( 'gridmaster/post-date', { format: 'M d, Y' } ),
					createBlock( 'gridmaster/social-share', {} ),
				],
			},
			// 26. Video Showcase Lesson Card (Pro)
			{
				id: 'video-showcase-course',
				title: __( 'Video Showcase & Course (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Dark neon 16:9 card with inline video player preview, glowing play button, difficulty level badge, title, and lesson link.', 'ajax-filter-posts' ),
				icon: 'format-video',
				category: 'Video & Reels',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '16px', borderRadius: '12px', borderWidth: '1px', borderColor: 'rgba(150,95,255,0.3)', boxShadow: '0 4px 20px rgba(0,0,0,0.4)', backgroundColor: '#0b0e16', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/card-video', {
						aspectRatio: '16/9',
						playbackMode: 'inline_click',
						showPlayButton: true,
						showProgressBar: true,
						playBgColor: 'rgba(213, 0, 0, 0.92)',
						borderRadius: '8px',
					} ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/badge', { text: __( 'MEDIUM', 'ajax-filter-posts' ), backgroundColor: '#050608', textColor: '#ffcf5a' } ),
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					] ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', color: '#ffffff', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 15, color: '#94a3b8' } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Start this lesson →', 'ajax-filter-posts' ), textColor: '#8fb8ff', backgroundColor: 'transparent' } ),
				],
			},
			// 27. TikTok / Reels / Shorts 9:16 Vertical Video (Pro)
			{
				id: 'shorts-vertical-feed',
				title: __( 'TikTok / Reels / Shorts 9:16 (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Full portrait 9:16 vertical card with auto-looping muted teaser, sound toggle, creator avatar, and engagement actions.', 'ajax-filter-posts' ),
				icon: 'smartphone',
				category: 'Video & Reels',
				isPro: true,
				cardAttrs: { layoutMode: 'overlay', minHeight: '440px', padding: '18px', borderRadius: '16px', borderWidth: '0px', boxShadow: '0 8px 30px rgba(0,0,0,0.5)', backgroundColor: '#000000', hoverEffect: 'zoom' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/card-video', {
						aspectRatio: '9/16',
						playbackMode: 'autoplay_loop',
						showPlayButton: false,
						showSoundToggle: true,
						borderRadius: '16px',
					} ),
					createBlock( 'gridmaster/badge', { text: __( '🔥 REEL', 'ajax-filter-posts' ), backgroundColor: 'rgba(239,68,68,0.85)', textColor: '#ffffff' } ),
					createBlock( 'core/group', { style: { spacing: { margin: { top: 'auto' } } } }, [
						createBlock( 'core/group', { layout: { type: 'flex', alignItems: 'center' } }, [
							createBlock( 'gridmaster/author-avatar', { avatarSize: 36, borderRadius: '50%' } ),
							createBlock( 'gridmaster/post-author', {} ),
						] ),
						createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '16px', color: '#ffffff', isLink: true } ),
						createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
							createBlock( 'gridmaster/post-date', { format: 'M j', color: '#e2e8f0' } ),
							createBlock( 'gridmaster-pro/bookmark-button', { iconSize: 20, activeColor: '#ef4444' } ),
						] ),
					] ),
				],
			},
			// 28. Instagram 1:1 Square Video Grid (Pro)
			{
				id: 'instagram-square-video',
				title: __( 'Instagram 1:1 Square Video (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Clean 1:1 square media card with video indicator badge and hover preview or lightbox trigger.', 'ajax-filter-posts' ),
				icon: 'camera',
				category: 'Video & Reels',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '12px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/card-video', {
						aspectRatio: '1/1',
						playbackMode: 'inline_hover',
						showPlayButton: true,
						borderRadius: '8px',
					} ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/title', { tagName: 'h4', fontSize: '15px', isLink: true } ),
						createBlock( 'gridmaster-pro/bookmark-button', { iconSize: 18 } ),
					] ),
					createBlock( 'gridmaster/excerpt', { length: 12, fontSize: '13px' } ),
				],
			},
			// 29. YouTube Channel Video Card (Pro)
			{
				id: 'youtube-playlist-card',
				title: __( 'YouTube Channel Video Card (Pro)', 'ajax-filter-posts' ),
				desc: __( '16:9 video thumbnail with duration badge, channel author avatar, video title, and lightbox watch button.', 'ajax-filter-posts' ),
				icon: 'format-video',
				category: 'Video & Reels',
				isPro: true,
				cardAttrs: { layoutMode: 'vertical', padding: '14px', borderRadius: '12px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				getBlocks: () => [
					createBlock( 'gridmaster-pro/card-video', {
						aspectRatio: '16/9',
						playbackMode: 'inline_click',
						showPlayButton: true,
						borderRadius: '8px',
					} ),
					createBlock( 'core/group', { layout: { type: 'flex', alignItems: 'flex-start', gap: '12px' } }, [
						createBlock( 'gridmaster/author-avatar', { avatarSize: 38, borderRadius: '50%' } ),
						createBlock( 'core/group', { style: { spacing: { margin: { top: '0', bottom: '0' } } } }, [
							createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '16px', isLink: true } ),
							createBlock( 'core/group', { layout: { type: 'flex', gap: '8px', alignItems: 'center' } }, [
								createBlock( 'gridmaster/post-author', {} ),
								createBlock( 'gridmaster/post-date', { format: 'M j, Y', fontSize: '12px' } ),
							] ),
						] ),
					] ),
				],
			},
		];

		// Compute category counts
		const categoryCounts = {};
		categories.forEach( ( cat ) => {
			if ( cat === 'All' ) {
				categoryCounts[ cat ] = templates.length;
			} else {
				categoryCounts[ cat ] = templates.filter( ( t ) => t.category === cat ).length;
			}
		} );

		const lowerSearch = searchTerm.trim().toLowerCase();
		const filteredTemplates = templates.filter( ( t ) => {
			const matchesCat = activeCat === 'All' || t.category === activeCat;
			const matchesSearch =
				! lowerSearch ||
				( t.title && t.title.toLowerCase().includes( lowerSearch ) ) ||
				( t.desc && t.desc.toLowerCase().includes( lowerSearch ) ) ||
				( t.category && t.category.toLowerCase().includes( lowerSearch ) );
			return matchesCat && matchesSearch;
		} );

		return el(
			Popover,
			{
				className: 'gm-popover-mega gm-card-templates-popover',
				onClose: onClose,
				position: 'bottom right',
			},
			el(
				'div',
				{ className: 'gm-popover-mega-panel' },

				// Header: Title Bar + Close Button
				el(
					'div',
					{ className: 'gm-popover-header' },
					el(
						'div',
						{ className: 'gm-popover-header-title-row' },
						el( 'span', { className: 'dashicons dashicons-layout' } ),
						el( 'h3', { className: 'gm-popover-title' }, __( 'Card Templates Library', 'ajax-filter-posts' ) ),
						el(
							'span',
							{ className: 'gm-popover-count-pill' },
							sprintf( __( '%d pre-built templates', 'ajax-filter-posts' ), templates.length )
						),
						el(
							'span',
							{ className: 'gm-popover-scope-pill' },
							__( 'Single Card Blueprint', 'ajax-filter-posts' )
						)
					),
					el( Button, {
						icon: 'no-alt',
						label: __( 'Close', 'ajax-filter-posts' ),
						onClick: onClose,
						className: 'gm-popover-close-btn',
						isSmall: true,
					} )
				),

				// Search Bar
				el(
					'div',
					{ className: 'gm-popover-search-wrap' },
					el( 'span', { className: 'dashicons dashicons-search gm-search-icon' } ),
					el( 'input', {
						type: 'text',
						className: 'gm-search-input',
						placeholder: sprintf(
							__( 'Search %d card templates (e.g. video, reel, list, deal)...', 'ajax-filter-posts' ),
							templates.length
						),
						value: searchTerm,
						onChange: ( e ) => setSearchTerm( e.target.value ),
						autoFocus: true,
					} ),
					searchTerm &&
						el(
							'button',
							{
								type: 'button',
								className: 'gm-search-clear-btn',
								onClick: () => setSearchTerm( '' ),
								'aria-label': __( 'Clear search', 'ajax-filter-posts' ),
							},
							'✕'
						)
				),

				// Category Tabs
				el(
					'div',
					{ className: 'gm-popover-tabs-bar' },
					categories.map( ( cat ) =>
						el(
							'button',
							{
								key: cat,
								type: 'button',
								className: 'gm-tab-pill' + ( activeCat === cat ? ' is-active' : '' ),
								onClick: () => setActiveCat( cat ),
							},
							cat,
							el( 'span', { className: 'gm-tab-count' }, categoryCounts[ cat ] || 0 )
						)
					)
				),

				// Scrollable Grid of Layout Cards
				el(
					'div',
					{ className: 'gm-popover-scrollable-body' },
					filteredTemplates.length === 0
						? el(
								'div',
								{ className: 'gm-popover-empty-state' },
								el( 'span', { className: 'dashicons dashicons-search' } ),
								el( 'p', { className: 'gm-empty-title' }, __( 'No matching card templates found', 'ajax-filter-posts' ) ),
								el( 'p', { className: 'gm-empty-hint' }, __( 'Try searching for video, reel, list, or deal, or click the "All" tab.', 'ajax-filter-posts' ) ),
								searchTerm &&
									el(
										Button,
										{
											isSecondary: true,
											isSmall: true,
											onClick: () => setSearchTerm( '' ),
										},
										__( 'Clear Search', 'ajax-filter-posts' )
									)
						  )
						: el(
								'div',
								{ className: 'gm-layouts-grid' },
								filteredTemplates.map( ( t ) => {
									const isCardDisabled = t.isPro && ! isPro;
									return el(
										'div',
										{
											key: t.id,
											className:
												'gm-layout-card' +
												( t.isPro ? ' is-pro' : '' ) +
												( isCardDisabled ? ' is-pro-disabled' : '' ),
											onClick: () => {
												if ( isCardDisabled ) return;
												onApplyTemplate( t );
												onClose();
											},
											role: 'button',
											tabIndex: isCardDisabled ? -1 : 0,
											onKeyDown: ( e ) => {
												if ( ( e.key === 'Enter' || e.key === ' ' ) && ! isCardDisabled ) {
													e.preventDefault();
													onApplyTemplate( t );
													onClose();
												}
											},
											title: isCardDisabled
												? __( 'Pro card template — upgrade GridMaster to unlock', 'ajax-filter-posts' )
												: ( t.desc || t.title ),
										},
										// 1. Visual Mini Wireframe Graphic
										el( TemplateWireframe, { template: t } ),
										// 2. Info / Content
										el(
											'div',
											{ className: 'gm-layout-info' },
											el(
												'div',
												{ className: 'gm-layout-title-row' },
												el( 'span', { className: 'gm-layout-title' }, t.title ),
												t.isPro &&
													el(
														'span',
														{ className: 'gm-pro-badge-pill' },
														'PRO'
													)
											),
											t.category &&
												el( 'span', { className: 'gm-layout-cat-pill' }, t.category ),
											el( 'p', { className: 'gm-layout-desc' }, t.desc )
										),
										// 3. Action Button Bar
										el(
											'div',
											{ className: 'gm-layout-action-wrap' },
											el(
												'button',
												{
													type: 'button',
													className:
														'gm-layout-apply-btn' +
														( isCardDisabled ? ' is-disabled' : '' ),
													disabled: isCardDisabled,
													tabIndex: -1,
												},
												isCardDisabled
													? __( 'Pro Only 🔒', 'ajax-filter-posts' )
													: __( 'Apply Template', 'ajax-filter-posts' ) + ' →'
											)
										)
									);
								} )
						  )
				)
			)
		);
	}

	/**
	 * Card Canvas Stage: contains quick-add bar, helper text, and Gutenberg canvas.
	 */
	function CardCanvasStage( props ) {
		const { isPro, customCss, showToast, config } = props;
		const { insertBlock, replaceInnerBlocks, updateBlockAttributes } = useDispatch( 'core/block-editor' );
		const registry = wp.data.useRegistry();
		const paperRef = useRef( null );
		const { rootBlocks, selectedClientId } = useSelect( ( select ) => {
			const be = select( 'core/block-editor' );
			return { rootBlocks: be.getBlocks(), selectedClientId: be.getSelectedBlockClientId() };
		} );

		const canvasCss = customCss
			? customCss.replace( /\{\{GRID\}\}|\{\{GRID_ID\}\}|\{\{grid\}\}/g, '.gm-card-canvas-paper' )
			: '';

		const rootCard = rootBlocks.length > 0 && rootBlocks[ 0 ].name === 'gridmaster/card' ? rootBlocks[ 0 ] : null;
		const isHorizontalCard = rootCard?.attributes?.layoutMode === 'horizontal' || rootCard?.attributes?.layoutMode === 'horizontal-reverse';

		const [ isMoreOpen, setIsMoreOpen ] = useState( false );
		const [ isTemplatesOpen, setIsTemplatesOpen ] = useState( false );
		const moreBtnRef = useRef( null );
		const templatesBtnRef = useRef( null );

		const addBlock = ( blockName, defaultAttributes = {} ) => {
			const newBlock = createBlock( blockName, defaultAttributes );
			const editor = registry.select( 'core/block-editor' );
			if ( selectedClientId ) {
				// Leaf fields cannot contain children. Insert their next sibling instead.
				const selected = editor.getBlock( selectedClientId );
				const isContainer = [ 'gridmaster/card', 'core/group', 'core/columns', 'core/column' ].includes( selected?.name );
				if ( isContainer && editor.canInsertBlockType( blockName, selectedClientId ) ) {
					insertBlock( newBlock, undefined, selectedClientId );
				} else {
					const parent = editor.getBlockRootClientId( selectedClientId );
					insertBlock( newBlock, editor.getBlockIndex( selectedClientId ) + 1, parent );
				}
			} else if ( rootBlocks.length > 0 && rootBlocks[ 0 ].name === 'gridmaster/card' ) {
				insertBlock( newBlock, undefined, rootBlocks[ 0 ].clientId );
			} else {
				insertBlock( newBlock );
			}
		};

		const applyCardTemplate = ( tpl ) => {
			const rootCard = rootBlocks.length > 0 && rootBlocks[ 0 ].name === 'gridmaster/card' ? rootBlocks[ 0 ] : null;
			if ( tpl.isPro && ! isPro ) return;

			if ( rootCard && rootCard.innerBlocks && rootCard.innerBlocks.length > 0 ) {
				if ( ! window.confirm( __( 'Replace current card blocks with this prebuilt template?', 'ajax-filter-posts' ) ) ) {
					return;
				}
			}

			// Generate blocks programmatically with 0 parsing errors
			const newBlocks = typeof tpl.getBlocks === 'function' ? tpl.getBlocks() : parse( tpl.markup );

			const defaultAttrs = {
				layoutMode: 'vertical',
				mediaWidth: '35%',
				minHeight: '',
				backgroundColor: '#ffffff',
				padding: '20px',
				borderRadius: '8px',
				borderWidth: '0px',
				borderColor: '#dcdcde',
				boxShadow: 'none',
				hoverEffect: 'lift',
			};

			const mergedAttrs = {
				...defaultAttrs,
				...( tpl.cardAttrs || {} ),
			};

			if ( rootCard ) {
				updateBlockAttributes( rootCard.clientId, mergedAttrs );
				replaceInnerBlocks( rootCard.clientId, newBlocks );
			} else {
				const newCard = createBlock( 'gridmaster/card', mergedAttrs, newBlocks );
				insertBlock( newCard );
			}
			if ( showToast ) {
				showToast( sprintf( __( 'Applied "%s" card template.', 'ajax-filter-posts' ), tpl.title || tpl.name ), 'success', 'layout' );
			}
		};

		return el(
			'div',
			{ className: 'gm-card-canvas-stage' },

			// Streamlined Inserter Toolbar
			el(
				'div',
				{ className: 'gm-card-toolbar' },
				el(
					'div',
					{ className: 'gm-quick-add-group' },
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster/title', { tagName: 'h3', isLink: true } ),
							className: 'gm-quick-add-btn',
						},
						'+ ' + __( 'Title', 'ajax-filter-posts' )
					),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster/featured-image', { size: 'medium_large' } ),
							className: 'gm-quick-add-btn',
						},
						'+ ' + __( 'Image', 'ajax-filter-posts' )
					),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster-pro/card-video', { aspectRatio: '16/9' } ),
							className: 'gm-quick-add-btn gm-quick-video-btn',
						},
						'+ ' + __( 'Video (Pro)', 'ajax-filter-posts' )
					),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster/excerpt', { length: 20 } ),
							className: 'gm-quick-add-btn',
						},
						'+ ' + __( 'Excerpt', 'ajax-filter-posts' )
					),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster/read-more', { text: __( 'Read More', 'ajax-filter-posts' ) } ),
							className: 'gm-quick-add-btn',
						},
						'+ ' + __( 'Button', 'ajax-filter-posts' )
					),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster-pro/product-price', {} ),
							className: 'gm-quick-add-btn gm-quick-woo-btn' + ( config?.query?.post_type === 'product' ? ' is-woo-active' : '' ),
						},
						'+ ' + __( 'Price', 'ajax-filter-posts' )
					),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => addBlock( 'gridmaster-pro/add-to-cart', { buttonText: __( 'Add to Cart', 'ajax-filter-posts' ) } ),
							className: 'gm-quick-add-btn gm-quick-woo-btn' + ( config?.query?.post_type === 'product' ? ' is-woo-active' : '' ),
						},
						'+ ' + __( 'Add to Cart', 'ajax-filter-posts' )
					)
				),
				el(
					'div',
					{ className: 'gm-more-blocks-wrap', ref: moreBtnRef },
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => {
								const next = ! isMoreOpen;
								setIsMoreOpen( next );
								if ( next ) setIsTemplatesOpen( false );
							},
							className: 'gm-more-blocks-btn' + ( isMoreOpen ? ' is-active' : '' ),
						},
						__( 'More blocks', 'ajax-filter-posts' ) + ' ▼'
					),
					isMoreOpen &&
						el( MoreBlocksPopover, {
							isOpen: isMoreOpen,
							onClose: () => setIsMoreOpen( false ),
							onSelectBlock: addBlock,
							isPro: isPro,
						} )
				),
				el(
					'div',
					{ className: 'gm-card-templates-wrap', ref: templatesBtnRef, style: { marginLeft: '8px' } },
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							onClick: () => {
								const next = ! isTemplatesOpen;
								setIsTemplatesOpen( next );
								if ( next ) setIsMoreOpen( false );
							},
							className: 'gm-card-templates-btn' + ( isTemplatesOpen ? ' is-active' : '' ),
						},
						el( 'span', { className: 'dashicons dashicons-layout' } ),
						' ' + __( 'Card Templates', 'ajax-filter-posts' ) + ' ▼'
					),
					isTemplatesOpen &&
						el( CardTemplatesPopover, {
							isOpen: isTemplatesOpen,
							onClose: () => setIsTemplatesOpen( false ),
							onApplyTemplate: applyCardTemplate,
							isPro: isPro,
						} )
				)
			),

			// Canvas Surface
			el(
				'div',
				{ className: 'gm-card-canvas-viewport' },
				el(
					'div',
					{ className: 'gm-card-canvas-hint' },
					el( 'div', null,
						el( 'span', { className: 'dashicons dashicons-info-outline', 'aria-hidden': true } ),
						' ', __( 'Design one card. It repeats for every matching post.', 'ajax-filter-posts' )
					),
					el( DropGuidance, { paperRef: paperRef } )
				),
				el(
					'div',
					{ className: 'gm-card-canvas-paper' + ( isHorizontalCard ? ' gm-card-canvas-paper--horizontal' : '' ), ref: paperRef },
					canvasCss && el( 'style', { id: 'gm-builder-canvas-custom-css' }, canvasCss ),
					el(
						BlockTools,
						{ className: 'gm-card-block-tools' },
						el( WritingFlow, null, el( ObserveTyping, null, el( BlockList, null ) ) )
					)
				)
			)
		);
	}

	/**
	 * Contextual Inspector: shows block settings when child selected,
	 * or Card Container styles when unselected/card selected.
	 */
	function ContextualInspector( props ) {
		const { onClose } = props;
		const { selectBlock, updateBlockAttributes } = useDispatch( 'core/block-editor' );
		const { selectedBlock, rootCardBlock } = useSelect( ( select ) => {
			const be = select( 'core/block-editor' );
			const selectedId = be.getSelectedBlockClientId();
			const selected = selectedId ? be.getBlock( selectedId ) : null;
			const rootBlocks = be.getBlocks();
			const rootCard = rootBlocks.length > 0 && rootBlocks[ 0 ].name === 'gridmaster/card' ? rootBlocks[ 0 ] : null;
			return {
				selectedBlock: selected,
				rootCardBlock: rootCard,
			};
		} );

		const isChildSelected = selectedBlock && selectedBlock.name !== 'gridmaster/card';

		// Card attributes
		const cardAttrs = rootCardBlock?.attributes || {
			backgroundColor: '#ffffff',
			padding: '20px',
			borderRadius: '8px',
			boxShadow: 'none',
			borderWidth: '0px',
			borderColor: '#dcdcde',
		};

		const updateCardAttr = ( key, val ) => {
			if ( rootCardBlock ) {
				updateBlockAttributes( rootCardBlock.clientId, { [ key ]: val } );
			}
		};

		return el(
			'div',
			{ className: 'gm-inspector-panel' },
			el(
				'div',
				{ className: 'gm-panel-header' },
				isChildSelected
					? el(
							'div',
							{ className: 'gm-breadcrumb-bar' },
							el(
								'button',
								{
									type: 'button',
									className: 'gm-breadcrumb-parent-btn',
									onClick: () => rootCardBlock && selectBlock( rootCardBlock.clientId ),
									title: __( 'Select parent Card Container', 'ajax-filter-posts' ),
								},
								__( 'Card', 'ajax-filter-posts' ),
								' ›'
							),
							el( 'span', { className: 'gm-breadcrumb-current' }, selectedBlock.attributes?.title || selectedBlock.name.replace( 'gridmaster/', '' ) )
					  )
					: el( 'h3', { className: 'gm-panel-title' }, el( 'span', { className: 'dashicons dashicons-admin-generic' } ), ' ' + __( 'Card Styles', 'ajax-filter-posts' ) ),
				el(
					Button,
					{
						icon: 'no-alt',
						isSmall: true,
						onClick: onClose,
						className: 'gm-panel-close-btn',
						label: __( 'Close Inspector', 'ajax-filter-posts' ),
					}
				)
			),

			el(
				'div',
				{ className: 'gm-inspector-body' },
				isChildSelected
					? el( BlockInspector, null )
					: el(
							'div',
							{ className: 'gm-card-settings-group' },
							el( 'h4', { className: 'gm-section-heading' }, __( 'Card Surface', 'ajax-filter-posts' ) ),
							el(
								'div',
								{ className: 'gm-control-field' },
								el( 'label', { className: 'gm-field-label' }, __( 'Background Color', 'ajax-filter-posts' ) ),
								el( ColorPalette, {
									value: cardAttrs.backgroundColor,
									onChange: ( val ) => updateCardAttr( 'backgroundColor', val || '#ffffff' ),
								} )
							),
							el(
								'div',
								{ className: 'gm-control-field' },
								el( RangeControl, {
									label: __( 'Padding (px)', 'ajax-filter-posts' ),
									value: Number.isFinite( parseInt( cardAttrs.padding, 10 ) ) ? parseInt( cardAttrs.padding, 10 ) : 20,
									min: 0,
									max: 60,
									onChange: ( val ) => updateCardAttr( 'padding', val + 'px' ),
								} )
							),
							el(
								'div',
								{ className: 'gm-control-field' },
								el( RangeControl, {
									label: __( 'Corner Radius (px)', 'ajax-filter-posts' ),
									value: Number.isFinite( parseInt( cardAttrs.borderRadius, 10 ) ) ? parseInt( cardAttrs.borderRadius, 10 ) : 8,
									min: 0,
									max: 32,
									onChange: ( val ) => updateCardAttr( 'borderRadius', val + 'px' ),
								} )
							),
							el(
								'div',
								{ className: 'gm-control-field' },
								el( SelectControl, {
									label: __( 'Box Shadow', 'ajax-filter-posts' ),
									value: cardAttrs.boxShadow || 'none',
									options: [
										{ label: __( 'None', 'ajax-filter-posts' ), value: 'none' },
										{ label: __( 'Subtle (Low)', 'ajax-filter-posts' ), value: '0 2px 4px rgba(0,0,0,0.05)' },
										{ label: __( 'Medium', 'ajax-filter-posts' ), value: '0 4px 12px rgba(0,0,0,0.08)' },
										{ label: __( 'Elevated (High)', 'ajax-filter-posts' ), value: '0 10px 25px rgba(0,0,0,0.1)' },
									],
									onChange: ( val ) => updateCardAttr( 'boxShadow', val ),
								} )
							),
							el(
								'div',
								{ className: 'gm-control-field' },
								el( RangeControl, {
									label: __( 'Border Width (px)', 'ajax-filter-posts' ),
									value: parseInt( cardAttrs.borderWidth, 10 ) || 0,
									min: 0,
									max: 10,
									onChange: ( val ) => updateCardAttr( 'borderWidth', val + 'px' ),
								} )
							),
							( parseInt( cardAttrs.borderWidth, 10 ) > 0 ) &&
								el(
									'div',
									{ className: 'gm-control-field' },
									el( 'label', { className: 'gm-field-label' }, __( 'Border Color', 'ajax-filter-posts' ) ),
									el( ColorPalette, {
										value: cardAttrs.borderColor || '#dcdcde',
										onChange: ( val ) => updateCardAttr( 'borderColor', val || '#dcdcde' ),
									} )
								)
					  )
			)
		);
	}

	/**
	 * Isolated Responsive Iframe Preview Modal.
	 */
	function PreviewModal( props ) {
		const { isOpen, onClose, title, config, blocks, gridId } = props;
		const [ previewDevice, setPreviewDevice ] = useState( 'desktop' ); // 'desktop' (100%), 'tablet' (768px), 'mobile' (375px)
		const [ isFetching, setIsFetching ] = useState( false );
		const [ previewHtml, setPreviewHtml ] = useState( '' );
		const [ previewError, setPreviewError ] = useState( null );
		const [ previewDraft, setPreviewDraft ] = useState( null );
		const [ viewportWidth, setViewportWidth ] = useState( 0 );
		const [ isOutOfDate, setIsOutOfDate ] = useState( false );
		const iframeRef = useRef( null );
		const lastFetchedFingerprintRef = useRef( '' );
		const requestRef = useRef( null );

		const getFingerprint = useCallback( () => {
			return JSON.stringify( { title, config, blocks: serialize( blocks ) } );
		}, [ title, config, blocks ] );

		const loadPreview = useCallback( () => {
			requestRef.current?.abort();
			const controller = new AbortController();
			requestRef.current = controller;
			setIsFetching( true );
			setPreviewError( null );
			const currentFingerprint = getFingerprint();
			const blockMarkup = serialize( blocks );
			const draft = { config, block_content: blockMarkup, id: gridId };

			fetch( initData.restUrl + 'preview', {
				method: 'POST',
				signal: controller.signal,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': initData.nonce,
				},
				body: JSON.stringify( draft ),
			} )
				.then( readApiResponse )
				.then( ( data ) => {
					if ( controller.signal.aborted ) return;
					setIsFetching( false );
					if ( data && data.html ) {
						setPreviewHtml( data.html );
						setPreviewDraft( draft );
						lastFetchedFingerprintRef.current = currentFingerprint;
						setIsOutOfDate( false );
					} else {
						setPreviewError( __( 'The preview response was empty. Please retry.', 'ajax-filter-posts' ) );
					}
				} )
				.catch( ( err ) => {
					if ( err.name === 'AbortError' ) return;
					setIsFetching( false );
					setPreviewError( err.message );
				} );
		}, [ config, blocks, gridId, getFingerprint ] );

		useEffect( () => {
			if ( isOpen ) {
				loadPreview();
			}
			return () => requestRef.current?.abort();
		}, [ isOpen ] );

		useEffect( () => {
			const frame = iframeRef.current;
			if ( ! frame ) return;
			const observer = new ResizeObserver( () => setViewportWidth( frame.clientWidth ) );
			observer.observe( frame );
			return () => observer.disconnect();
		}, [ isOpen ] );

		// Check if out of date
		useEffect( () => {
			if ( isOpen && lastFetchedFingerprintRef.current && getFingerprint() !== lastFetchedFingerprintRef.current ) {
				setIsOutOfDate( true );
			}
		}, [ config, blocks, title, isOpen, getFingerprint ] );

		// Write preview markup to isolated iframe
		const previewMeta = props.metadata || initData.metadata || {};
		useEffect( () => {
			if ( iframeRef.current && previewHtml ) {
				const iframeDoc = iframeRef.current.contentDocument || iframeRef.current.contentWindow.document;
				if ( iframeDoc ) {
					const previewData = previewDraft;
					const proCssLink = previewMeta.pro_css_url ? `<link rel="stylesheet" href="${previewMeta.pro_css_url}">` : '';
					const jqueryScript = previewMeta.jquery_url ? `<script src="${previewMeta.jquery_url}"></script>` : '';
					const proJsScript = previewMeta.pro_js_url ? `<script src="${previewMeta.pro_js_url}"></script>` : '';

					const docContent = `<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>${escapeHtml( title || 'Preview' )}</title>
	<link rel="stylesheet" href="${initData.dashiconsCssUrl || ''}">
	<link rel="stylesheet" href="${initData.frontendCssUrl || ''}">
	<link rel="stylesheet" href="${initData.coreCssUrl || ''}">
	${proCssLink}
	<style>
		body {
			margin: 0;
			padding: 24px 16px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #ffffff;
			color: #1e1e1e;
		}
		.gm-preview-embedded-container > .gm-v2-grid { margin: 0; }
		@media (max-width: 480px) { body { padding: 20px 16px; } }
	</style>
</head>
<body>
	<div class="gm-preview-embedded-container">
		${previewHtml}
	</div>
	${jqueryScript}
	<script>
		if ( ! window.jQuery && window.parent && window.parent.jQuery ) {
			window.jQuery = window.$ = window.parent.jQuery;
		}
		window.__gm_preview_data = ${scriptJson( previewData )};
		window.gridmasterV2 = ${scriptJson( previewRuntime )};
	</script>
	<script src="${initData.frontendJsUrl || ''}"></script>
	${proJsScript}
</body>
</html>`;
					iframeDoc.open();
					iframeDoc.write( docContent );
					iframeDoc.close();
				}
			}
		}, [ previewHtml, previewDraft ] );

		if ( ! isOpen ) return null;

		const deviceDimensions = {
			desktop: { width: '100%', label: __( 'Desktop', 'ajax-filter-posts' ), icon: 'desktop' },
			tablet: { width: '768px', label: __( 'Tablet', 'ajax-filter-posts' ), icon: 'tablet' },
			mobile: { width: '375px', label: __( 'Mobile', 'ajax-filter-posts' ), icon: 'smartphone' },
		};

		const handleOpenNewTab = () => {
			const previewWin = window.open( '', '_blank' );
			if ( ! previewWin ) {
				alert( __( 'Popup blocked by browser. Please allow popups for this site.', 'ajax-filter-posts' ) );
				return;
			}
			const previewData = previewDraft;
			const proCssLink = previewMeta.pro_css_url ? `<link rel="stylesheet" href="${previewMeta.pro_css_url}">` : '';
			const jqueryScript = previewMeta.jquery_url ? `<script src="${previewMeta.jquery_url}"></script>` : '';
			const proJsScript = previewMeta.pro_js_url ? `<script src="${previewMeta.pro_js_url}"></script>` : '';
			const docContent = `<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>${escapeHtml( title || 'GridMaster Preview' )}</title>
	<link rel="stylesheet" href="${initData.dashiconsCssUrl || ''}">
	<link rel="stylesheet" href="${initData.frontendCssUrl || ''}">
	<link rel="stylesheet" href="${initData.coreCssUrl || ''}">
	${proCssLink}
	<style>
		body {
			margin: 0;
			padding: 40px 20px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			background: #f6f7f9;
		}
		.gm-tab-wrap {
			max-width: 1200px;
			margin: 0 auto;
			background: #ffffff;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			padding: 32px;
		}
	</style>
</head>
<body>
	<div class="gm-tab-wrap">
		${previewHtml}
	</div>
	${jqueryScript}
	<script>
		if ( ! window.jQuery && window.opener && window.opener.jQuery ) {
			window.jQuery = window.$ = window.opener.jQuery;
		}
		window.__gm_preview_data = ${scriptJson( previewData )};
		window.gridmasterV2 = ${scriptJson( previewRuntime )};
	</script>
	<script src="${initData.frontendJsUrl || ''}"></script>
	${proJsScript}
</body>
</html>`;
			previewWin.document.open();
			previewWin.document.write( docContent );
			previewWin.document.close();
		};

		const previewControls = el(
			'div',
			{ className: 'gm-preview-toolbar' },
			el(
				'div',
				{ className: 'gm-device-switcher', role: 'group', 'aria-label': __( 'Preview device', 'ajax-filter-posts' ) },
				[ 'desktop', 'tablet', 'mobile' ].map( ( dev ) =>
					el(
						Button,
						{
							key: dev,
							onClick: () => setPreviewDevice( dev ),
							className: 'gm-device-btn',
							'aria-pressed': previewDevice === dev,
						},
						el( 'span', { className: 'dashicons dashicons-' + deviceDimensions[ dev ].icon } ),
						' ' + deviceDimensions[ dev ].label
					)
				)
			),
			el(
				'div',
				{ className: 'gm-preview-toolbar-actions' },
				el(
					Button,
					{
						onClick: loadPreview,
						disabled: isFetching,
						className: 'gm-refresh-preview-btn',
						label: __( 'Refresh preview', 'ajax-filter-posts' ),
					},
					el( 'span', { className: 'dashicons dashicons-update' } ),
					el( 'span', { className: 'gm-preview-action-label' }, __( 'Refresh', 'ajax-filter-posts' ) )
				),
				el(
					Button,
					{
						onClick: handleOpenNewTab,
						className: 'gm-open-tab-btn',
						label: __( 'Open preview in new tab', 'ajax-filter-posts' ),
						disabled: ! previewHtml || isFetching,
					},
					el( 'span', { className: 'dashicons dashicons-external' } ),
					el( 'span', { className: 'gm-preview-action-label' }, __( 'Open in new tab', 'ajax-filter-posts' ) )
				)
			)
		);

		return el(
			Modal,
			{
				title: el( 'span', { className: 'gm-preview-heading' },
					el( 'span', { className: 'gm-preview-heading-title' }, __( 'Live preview', 'ajax-filter-posts' ) ),
					el( 'span', { className: 'gm-preview-heading-grid' }, title || __( 'Untitled grid', 'ajax-filter-posts' ) )
				),
				headerActions: previewControls,
				onRequestClose: onClose,
				className: 'gm-preview-modal-dialog',
			},
			el(
				'div',
				{ className: 'gm-preview-dialog-inner' },

				// Out of date warning banner
				previewError && el( Notice, { status: 'error', isDismissible: false }, previewError ),
				isOutOfDate &&
					el(
						'div',
						{ className: 'gm-preview-stale-banner' },
						el( 'span', { className: 'dashicons dashicons-warning' } ),
						' ' + __( 'Preview is out of date. Click Refresh to update.', 'ajax-filter-posts' )
					),

				// Iframe Container Stage
				el(
					'div',
					{ className: 'gm-preview-viewport-stage' },
					el(
						'div',
						{
							className: 'gm-iframe-wrapper gm-device-' + previewDevice,
							style: { width: deviceDimensions[ previewDevice ].width },
						},
						isFetching &&
							el(
								'div',
								{ className: 'gm-iframe-loading' },
								el( Spinner ),
								el( 'p', null, __( 'Rendering grid preview...', 'ajax-filter-posts' ) )
							),
						el( 'iframe', {
							ref: iframeRef,
							className: 'gm-preview-iframe',
							title: __( 'Grid preview', 'ajax-filter-posts' ),
						} )
					)
				),
				el( 'div', { className: 'gm-preview-footer' },
					el( 'span', { className: 'gm-preview-footer-note' }, __( 'Showing current editor settings.', 'ajax-filter-posts' ) ),
					el( 'span', { className: 'gm-preview-viewport-size', title: __( 'Preview viewport width', 'ajax-filter-posts' ) },
						viewportWidth ? viewportWidth + ' px' : ''
					)
				)
			)
		);
	}

	/**
	 * Content Tab: Query settings divided into clean logical cards.
	 */
	function ContentTab( props ) {
		const { config, updateConfigSection, metadata, isPro } = props;
		const clauses = Array.isArray( config.query?.tax_query ) ? config.query.tax_query : [];
		const metaClauses = Array.isArray( config.query?.meta_query ) ? config.query.meta_query : [];
		const clause = clauses[ 0 ] || {};
		const [ taxonomy, setTaxonomy ] = useState( clause.taxonomy || '' );
		const [ operator, setOperator ] = useState( clause.operator || 'IN' );
		const [ termSearch, setTermSearch ] = useState( '' );
		const [ terms, setTerms ] = useState( [] );
		const [ termsError, setTermsError ] = useState( '' );
		const [ termsLoading, setTermsLoading ] = useState( false );
		const termLabels = useRef( {} );
		useEffect( () => {
			if ( ! taxonomy ) { setTerms( [] ); return; }
			const controller = new AbortController();
			setTermsLoading( true );
			setTermsError( '' );
			const timer = setTimeout( () => {
				fetch( initData.restUrl + 'meta-data?taxonomy=' + encodeURIComponent( taxonomy ) + '&term_search=' + encodeURIComponent( termSearch ), {
					headers: { 'X-WP-Nonce': initData.nonce }, signal: controller.signal,
				} ).then( readApiResponse ).then( ( data ) => {
					if ( controller.signal.aborted ) return;
					const loaded = data.taxonomies?.find( ( item ) => item.name === taxonomy )?.terms || [];
					loaded.forEach( ( term ) => { termLabels.current[ term.id ] = term.name; } );
					setTerms( loaded );
					setTermsLoading( false );
				} ).catch( ( error ) => {
					if ( error.name === 'AbortError' ) return;
					setTermsError( error.message );
					setTermsLoading( false );
				} );
			}, 200 );
			return () => { clearTimeout( timer ); controller.abort(); };
		}, [ taxonomy, termSearch ] );
		const selectedTerms = ( Array.isArray( clause.terms ) ? clause.terms : ( clause.terms ? [ clause.terms ] : [] ) ).map( Number );
		const setTermsRestriction = ( nextTerms, match = operator ) => {
			const next = clauses.slice();
			if ( nextTerms.length || next.length > 1 ) next[ 0 ] = { ...clause, taxonomy, terms: nextTerms, operator: match };
			else next.length = 0;
			updateConfigSection( 'query', 'tax_query', next );
		};

		return el(
			'div',
			{ className: 'gm-tab-panel-container' },
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-database' } ), ' ' + __( 'Source', 'ajax-filter-posts' ) ),
				el( SelectControl, {
					label: __( 'Primary Post Type', 'ajax-filter-posts' ),
					value: Array.isArray( config.query?.post_type ) ? ( config.query.post_type[ 0 ] || 'post' ) : ( config.query?.post_type || 'post' ),
					options: ( metadata.post_types || [] ).map( ( pt ) => ( { label: pt.label, value: pt.name } ) ),
					onChange: ( val ) => {
						const currentArr = Array.isArray( config.query?.post_type ) ? config.query.post_type : [];
						if ( currentArr.length > 1 ) {
							const merged = Array.from( new Set( [ val, ...currentArr.slice( 1 ) ] ) );
							updateConfigSection( 'query', 'post_type', merged.length === 1 ? merged[ 0 ] : merged );
						} else {
							updateConfigSection( 'query', 'post_type', val );
						}
					},
					help: __( 'Select the primary WordPress post type to display.', 'ajax-filter-posts' ),
				} ),
				( metadata.post_types || [] ).length > 1 &&
					el(
						'div',
						{ style: { marginBottom: '14px' } },
						el( 'p', { style: { fontSize: '12px', fontWeight: 600, margin: '0 0 6px' } }, __( 'Include Additional Post Types (Multi-Type Query)', 'ajax-filter-posts' ) ),
						el(
							'div',
							{ style: { display: 'flex', flexWrap: 'wrap', gap: '12px' } },
							( metadata.post_types || [] ).map( ( pt ) => {
								const activeList = Array.isArray( config.query?.post_type )
									? config.query.post_type
									: [ config.query?.post_type || 'post' ];
								const primaryType = activeList[ 0 ] || 'post';
								const isChecked = activeList.includes( pt.name );
								return el( wp.components.CheckboxControl, {
									key: pt.name,
									label: pt.label,
									checked: isChecked,
									disabled: pt.name === primaryType,
									onChange: ( checked ) => {
										let next = checked
											? Array.from( new Set( [ ...activeList, pt.name ] ) )
											: activeList.filter( ( n ) => n !== pt.name || n === primaryType );
										if ( next.length === 0 ) next = [ primaryType ];
										updateConfigSection( 'query', 'post_type', next.length === 1 ? next[ 0 ] : next );
									},
								} );
							} )
						)
					),
				el( RangeControl, {
					label: __( 'Posts Per Page', 'ajax-filter-posts' ),
					value: config.query?.posts_per_page || 9,
					min: 1,
					max: 50,
					onChange: ( val ) => updateConfigSection( 'query', 'posts_per_page', val ),
					help: __( 'Number of posts to query and display per page.', 'ajax-filter-posts' ),
				} )
			),
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-admin-site' } ), ' ' + __( 'Context-Aware & Advanced Query', 'ajax-filter-posts' ) ),
				el( SelectControl, {
					label: __( 'Dynamic Query Context', 'ajax-filter-posts' ),
					value: config.query?.context_mode || 'default',
					options: [
						{ label: __( 'Standalone / Manual Query (Default)', 'ajax-filter-posts' ), value: 'default' },
						{ label: __( 'Current Archive Context (Category / Tag / Search / Author)', 'ajax-filter-posts' ), value: 'current_archive' },
						{ label: __( 'Related Posts (Share Categories/Tags with Current Post)', 'ajax-filter-posts' ), value: 'related_posts' },
					],
					onChange: ( val ) => updateConfigSection( 'query', 'context_mode', val ),
					help: __( 'Inherit terms from archive templates or automatically display related posts matching the viewed entry.', 'ajax-filter-posts' ),
				} ),
				el( ToggleControl, {
					label: __( 'Exclude Current Post', 'ajax-filter-posts' ),
					checked: !! config.query?.exclude_current,
					onChange: ( val ) => updateConfigSection( 'query', 'exclude_current', val ),
					help: __( 'Prevent the currently open post or product from showing up inside this grid.', 'ajax-filter-posts' ),
				} ),
				el( ToggleControl, {
					label: __( 'Ignore Sticky Posts', 'ajax-filter-posts' ),
					checked: config.query?.ignore_sticky_posts !== false,
					onChange: ( val ) => updateConfigSection( 'query', 'ignore_sticky_posts', val ),
				} ),
				el( TextControl, {
					label: __( 'Offset (Skip First N Posts)', 'ajax-filter-posts' ),
					type: 'number',
					min: 0,
					value: config.query?.offset !== undefined ? config.query.offset : 0,
					onChange: ( val ) => updateConfigSection( 'query', 'offset', Math.max( 0, parseInt( val, 10 ) || 0 ) ),
				} ),
				el( TextControl, {
					label: __( 'Include Only Post IDs (Comma-Separated)', 'ajax-filter-posts' ),
					value: Array.isArray( config.query?.post__in ) ? config.query.post__in.join( ', ' ) : ( config.query?.post__in || '' ),
					placeholder: 'e.g. 12, 45, 78',
					onChange: ( val ) => updateConfigSection( 'query', 'post__in', val ),
				} ),
				el( TextControl, {
					label: __( 'Exclude Post IDs (Comma-Separated)', 'ajax-filter-posts' ),
					value: Array.isArray( config.query?.post__not_in ) ? config.query.post__not_in.join( ', ' ) : ( config.query?.post__not_in || '' ),
					placeholder: 'e.g. 9, 15',
					onChange: ( val ) => updateConfigSection( 'query', 'post__not_in', val ),
				} )
			),
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-filter' } ), ' ' + __( 'Include / Exclude', 'ajax-filter-posts' ) ),
				el(
					'div',
					{ className: 'gm-guidance-callout' },
					el( 'span', { className: 'dashicons dashicons-info' } ),
					el( 'p', null, __( 'Visitor filters narrow this selection. You can set initial inclusions or exclusions below.', 'ajax-filter-posts' ) )
				),
				el( SelectControl, {
					label: __( 'Filter by Taxonomy Source', 'ajax-filter-posts' ),
					value: taxonomy,
					disabled: selectedTerms.length > 0,
					options: [
						{ label: __( 'All (No initial filter)', 'ajax-filter-posts' ), value: '' },
						...( metadata.taxonomies || [] ).map( ( t ) => ( { label: t.label, value: t.name } ) ),
					],
					onChange: ( val ) => { setTaxonomy( val ); setTermSearch( '' ); },
					help: selectedTerms.length ? __( 'Remove selected terms before changing the source.', 'ajax-filter-posts' ) : undefined,
				} ),
				taxonomy && el( Fragment, null,
					el( SelectControl, {
						label: __( 'Match selected terms', 'ajax-filter-posts' ),
						value: operator,
						options: [
							{ label: __( 'Include any selected term', 'ajax-filter-posts' ), value: 'IN' },
							{ label: __( 'Exclude selected terms', 'ajax-filter-posts' ), value: 'NOT IN' },
							{ label: __( 'Include all selected terms', 'ajax-filter-posts' ), value: 'AND' },
						],
						onChange: ( value ) => { setOperator( value ); setTermsRestriction( selectedTerms, value ); },
					} ),
					el( wp.components.ComboboxControl, {
						label: __( 'Add a term', 'ajax-filter-posts' ), value: null,
						options: terms.filter( ( term ) => ! selectedTerms.includes( term.id ) ).map( ( term ) => ( { label: term.name + ' (#' + term.id + ')', value: String( term.id ) } ) ),
						onFilterValueChange: setTermSearch,
						onChange: ( value ) => { if ( value ) setTermsRestriction( [ ...selectedTerms, Number( value ) ] ); },
						help: termsLoading ? __( 'Loading terms…', 'ajax-filter-posts' ) : __( 'Type to search. Visitor filters keep these base restrictions.', 'ajax-filter-posts' ),
					} ),
					termsError && el( Notice, { status: 'error', isDismissible: false }, termsError ),
					el( 'div', { className: 'gm-selected-terms' }, selectedTerms.map( ( id ) => el( Button, {
						key: id, isSecondary: true, isSmall: true,
						onClick: () => setTermsRestriction( selectedTerms.filter( ( value ) => value !== id ) ),
						'aria-label': __( 'Remove term', 'ajax-filter-posts' ) + ' ' + ( termLabels.current[ id ] || id ),
					}, ( termLabels.current[ id ] || '#' + id ) + ' ×' ) ) )
				),
				clauses.length > 1 && el( 'p', null, __( 'Additional saved taxonomy restrictions are also applied.', 'ajax-filter-posts' ) ),
				el( SelectControl, {
					label: __( 'Filter by Author', 'ajax-filter-posts' ),
					value: config.query?.author ? String( config.query.author ) : '',
					options: [
						{ label: __( 'All Authors', 'ajax-filter-posts' ), value: '' },
						...( metadata.authors || [] ).map( ( a ) => ( { label: a.name + ' (#' + a.id + ')', value: String( a.id ) } ) ),
					],
					onChange: ( val ) => updateConfigSection( 'query', 'author', val ? parseInt( val, 10 ) : '' ),
					help: __( 'Restrict initial query results to posts created by a specific author.', 'ajax-filter-posts' ),
				} )
			),
			el(
				'div',
				{ className: 'gm-settings-card' },
				el(
					'div',
					{ style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' } },
					el( 'h3', { className: 'gm-card-heading', style: { margin: 0 } }, el( 'span', { className: 'dashicons dashicons-admin-generic' } ), ' ' + __( 'Custom Field Query (Meta Query)', 'ajax-filter-posts' ) ),
					el(
						Button,
						{
							isSecondary: true,
							isSmall: true,
							disabled: ! isPro,
							onClick: () => {
								const next = [ ...metaClauses, { key: '', compare: '=', value: '' } ];
								updateConfigSection( 'query', 'meta_query', next );
							},
						},
						'+ ' + __( 'Add Meta Condition', 'ajax-filter-posts' )
					)
				),
				! isPro &&
					el(
						Notice,
						{ status: 'info', isDismissible: false },
						__( 'Static custom field (meta_query) rules are powered by GridMaster Pro.', 'ajax-filter-posts' )
					),
				metaClauses.length === 0
					? el( 'p', { style: { color: '#64748b', fontSize: '13px', margin: '4px 0 0' } }, __( 'No custom field conditions applied. Add a condition to filter posts by post meta.', 'ajax-filter-posts' ) )
					: el(
							'div',
							{ className: 'gm-meta-query-list' },
							metaClauses.map( ( mq, idx ) =>
								el(
									'div',
									{ key: idx, className: 'gm-meta-query-row' },
									el( TextControl, {
										label: __( 'Meta Key', 'ajax-filter-posts' ),
										value: mq.key || '',
										placeholder: 'e.g. featured_flag',
										onChange: ( val ) => {
											const next = metaClauses.map( ( item, i ) => ( i === idx ? { ...item, key: val } : item ) );
											updateConfigSection( 'query', 'meta_query', next );
										},
									} ),
									el( SelectControl, {
										label: __( 'Compare', 'ajax-filter-posts' ),
										value: mq.compare || '=',
										options: [
											{ label: '= (Equals)', value: '=' },
											{ label: '!= (Not Equals)', value: '!=' },
											{ label: '> (Greater Than)', value: '>' },
											{ label: '>= (Greater / Equal)', value: '>=' },
											{ label: '< (Less Than)', value: '<' },
											{ label: '<= (Less / Equal)', value: '<=' },
											{ label: 'LIKE (Contains)', value: 'LIKE' },
											{ label: 'NOT LIKE', value: 'NOT LIKE' },
											{ label: 'IN (Comma List)', value: 'IN' },
											{ label: 'NOT IN', value: 'NOT IN' },
											{ label: 'EXISTS', value: 'EXISTS' },
											{ label: 'NOT EXISTS', value: 'NOT EXISTS' },
										],
										onChange: ( val ) => {
											const next = metaClauses.map( ( item, i ) => ( i === idx ? { ...item, compare: val } : item ) );
											updateConfigSection( 'query', 'meta_query', next );
										},
									} ),
									! [ 'EXISTS', 'NOT EXISTS' ].includes( ( mq.compare || '=' ).toUpperCase() ) &&
										el( TextControl, {
											label: __( 'Value', 'ajax-filter-posts' ),
											value: mq.value || '',
											placeholder: __( 'Expected value', 'ajax-filter-posts' ),
											onChange: ( val ) => {
												const next = metaClauses.map( ( item, i ) => ( i === idx ? { ...item, value: val } : item ) );
												updateConfigSection( 'query', 'meta_query', next );
											},
										} ),
									el( Button, {
										icon: 'trash',
										isDestructive: true,
										isSmall: true,
										label: __( 'Remove meta condition', 'ajax-filter-posts' ),
										onClick: () => {
											const next = metaClauses.filter( ( _, i ) => i !== idx );
											updateConfigSection( 'query', 'meta_query', next );
										},
									} )
								)
							)
					  )
			),
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-sort' } ), ' ' + __( 'Ordering', 'ajax-filter-posts' ) ),
				el( SelectControl, {
					label: __( 'Order By', 'ajax-filter-posts' ),
					value: config.query?.orderby || 'date',
					options: [
						{ label: __( 'Publish Date', 'ajax-filter-posts' ), value: 'date' },
						{ label: __( 'Post Title', 'ajax-filter-posts' ), value: 'title' },
						{ label: __( 'Menu Order', 'ajax-filter-posts' ), value: 'menu_order' },
						{ label: __( 'Random', 'ajax-filter-posts' ), value: 'rand' },
						{ label: __( 'Comment Count', 'ajax-filter-posts' ), value: 'comment_count' },
						{ label: __( 'WooCommerce: Price (Low to High)', 'ajax-filter-posts' ), value: 'price' },
						{ label: __( 'WooCommerce: Price (High to Low)', 'ajax-filter-posts' ), value: 'price-desc' },
						{ label: __( 'WooCommerce: Best Selling (Popularity)', 'ajax-filter-posts' ), value: 'popularity' },
						{ label: __( 'WooCommerce: Average Rating', 'ajax-filter-posts' ), value: 'rating' },
						{ label: __( 'WooCommerce: SKU', 'ajax-filter-posts' ), value: 'sku' },
					],
					onChange: ( val ) => updateConfigSection( 'query', 'orderby', val ),
				} ),
				el( SelectControl, {
					label: __( 'Direction', 'ajax-filter-posts' ),
					value: config.query?.order || 'DESC',
					options: [
						{ label: __( 'Descending (Newest first)', 'ajax-filter-posts' ), value: 'DESC' },
						{ label: __( 'Ascending (Oldest first)', 'ajax-filter-posts' ), value: 'ASC' },
					],
					onChange: ( val ) => updateConfigSection( 'query', 'order', val ),
				} )
			),
			config.query?.post_type === 'product' &&
				el(
					'div',
					{ className: 'gm-settings-card' },
					el(
						'h3',
						{ className: 'gm-card-heading' },
						el( 'span', { className: 'dashicons dashicons-cart' } ),
						' ' + __( 'WooCommerce Product Query Settings', 'ajax-filter-posts' )
					),
					el( SelectControl, {
						label: __( 'Product Filter Preset', 'ajax-filter-posts' ),
						value: config.query?.product_source || 'all',
						options: [
							{ label: __( 'All Products', 'ajax-filter-posts' ), value: 'all' },
							{ label: __( 'On Sale Products Only', 'ajax-filter-posts' ), value: 'on_sale' },
							{ label: __( 'Featured Products Only', 'ajax-filter-posts' ), value: 'featured' },
							{ label: __( 'Best Selling Products', 'ajax-filter-posts' ), value: 'best_selling' },
							{ label: __( 'Top Rated Products', 'ajax-filter-posts' ), value: 'top_rated' },
							{ label: __( 'In-Stock Products Only', 'ajax-filter-posts' ), value: 'in_stock' },
							{ label: __( 'User Wishlist / Saved Products', 'ajax-filter-posts' ), value: 'wishlist' },
						],
						onChange: ( val ) => updateConfigSection( 'query', 'product_source', val ),
					} ),
					el( ToggleControl, {
						label: __( 'Hide Out-of-Stock Products', 'ajax-filter-posts' ),
						checked: !! config.query?.hide_out_of_stock,
						onChange: ( val ) => updateConfigSection( 'query', 'hide_out_of_stock', val ),
					} ),
					el( ToggleControl, {
						label: __( 'Search by Product SKU', 'ajax-filter-posts' ),
						checked: !! config.query?.search_by_sku,
						onChange: ( val ) => updateConfigSection( 'query', 'search_by_sku', val ),
						help: __( 'Allow visitor search bars to match product SKUs in addition to titles and descriptions.', 'ajax-filter-posts' ),
					} )
				)
		);
	}

	/**
	 * Filters Tab: Manageable accordion list of facets.
	 */
	function FiltersTab( props ) {
		const { config, setConfig, metadata, isPro } = props;
		const [ expandedIdx, setExpandedIdx ] = useState( 0 );
		const [ showAddPopover, setShowAddPopover ] = useState( false );
		const addBtnRef = useRef( null );

		const facets = config.facets || [];

		const addFacet = ( type ) => {
			setConfig( ( prev ) => {
				const current = prev.facets ? [ ...prev.facets ] : [];
				const defaultTax = metadata.taxonomies?.[ 0 ]?.name || 'category';
				const defaultHeadingMap = {
					search: '',
					'date-range': __( 'Filter by Date', 'ajax-filter-posts' ),
					'meta-filter': __( 'Filter by Custom Field', 'ajax-filter-posts' ),
					'woo-price-range': __( 'Price Range', 'ajax-filter-posts' ),
					'woo-attribute-swatches': __( 'Filter by Attribute', 'ajax-filter-posts' ),
					'woo-rating-filter': __( 'Customer Rating', 'ajax-filter-posts' ),
					'woo-status-toggles': __( 'Availability & Deals', 'ajax-filter-posts' ),
					'woo-sort-dropdown': __( 'Sort By', 'ajax-filter-posts' ),
					'active-filters': __( 'Active Filters', 'ajax-filter-posts' ),
				};
				const newFacet = {
					type: type,
					taxonomy: type === 'woo-attribute-swatches' ? 'product_cat' : defaultTax,
					heading: defaultHeadingMap[ type ] !== undefined ? defaultHeadingMap[ type ] : __( 'Categories', 'ajax-filter-posts' ),
					show_all: true,
					all_label: __( 'All', 'ajax-filter-posts' ),
				};
				if ( type === 'taxonomy-tabs' ) {
					newFacet.style = 'tabs';
					newFacet.multi_select = false;
				} else if ( type === 'search' ) {
					newFacet.placeholder = __( 'Search posts...', 'ajax-filter-posts' );
				} else if ( type === 'meta-filter' ) {
					newFacet.meta_key = '';
				} else if ( type === 'woo-price-range' ) {
					newFacet.min_price = 0;
					newFacet.max_price = 500;
					newFacet.step = 5;
				} else if ( type === 'woo-attribute-swatches' ) {
					newFacet.swatch_style = 'auto';
					newFacet.multi_select = true;
				} else if ( type === 'woo-status-toggles' ) {
					newFacet.show_on_sale = true;
					newFacet.show_in_stock = true;
					newFacet.show_featured = true;
				}
				current.push( newFacet );
				return { ...prev, facets: current };
			} );
			setExpandedIdx( facets.length );
			setShowAddPopover( false );
		};

		const removeFacet = ( index ) => {
			setConfig( ( prev ) => {
				const current = [ ...prev.facets ];
				current.splice( index, 1 );
				return { ...prev, facets: current };
			} );
		};

		const moveFacet = ( index, delta ) => {
			setConfig( ( prev ) => {
				const current = [ ...prev.facets ];
				const target = index + delta;
				if ( target < 0 || target >= current.length ) return prev;
				const tmp = current[ index ];
				current[ index ] = current[ target ];
				current[ target ] = tmp;
				return { ...prev, facets: current };
			} );
			setExpandedIdx( index + delta );
		};

		const updateFacet = ( index, key, val ) => {
			setConfig( ( prev ) => {
				const current = [ ...prev.facets ];
				current[ index ] = { ...current[ index ], [ key ]: val };
				return { ...prev, facets: current };
			} );
		};

		const facetTypes = [
			{ id: 'taxonomy-tabs', label: __( 'Category Tabs', 'ajax-filter-posts' ), icon: 'tag' },
			{ id: 'taxonomy-dropdown', label: __( 'Dropdown Filter', 'ajax-filter-posts' ), icon: 'arrow-down-alt2' },
			{ id: 'taxonomy-checkbox', label: __( 'Checkboxes', 'ajax-filter-posts' ), icon: 'forms' },
			{ id: 'taxonomy-radio', label: __( 'Radio Buttons', 'ajax-filter-posts' ), icon: 'marker' },
			{ id: 'search', label: __( 'Search Bar', 'ajax-filter-posts' ), icon: 'search' },
			{ id: 'reset', label: __( 'Reset Button', 'ajax-filter-posts' ), icon: 'undo' },
			{ id: 'multi-select', label: __( 'Multi-Select (Pro)', 'ajax-filter-posts' ), icon: 'list-view', isPro: true },
			{ id: 'date-range', label: __( 'Date Range (Pro)', 'ajax-filter-posts' ), icon: 'calendar', isPro: true },
			{ id: 'meta-filter', label: __( 'Custom Field / Meta Filter (Pro)', 'ajax-filter-posts' ), icon: 'admin-generic', isPro: true },
			{ id: 'woo-price-range', label: __( 'WooCommerce Price Range Slider (Pro)', 'ajax-filter-posts' ), icon: 'money-alt', isPro: true },
			{ id: 'woo-attribute-swatches', label: __( 'WooCommerce Attribute Swatches (Pro)', 'ajax-filter-posts' ), icon: 'art', isPro: true },
			{ id: 'woo-rating-filter', label: __( 'WooCommerce Star Rating Filter (Pro)', 'ajax-filter-posts' ), icon: 'star-filled', isPro: true },
			{ id: 'woo-status-toggles', label: __( 'WooCommerce Sale / Stock / Featured Toggles (Pro)', 'ajax-filter-posts' ), icon: 'yes-alt', isPro: true },
			{ id: 'woo-sort-dropdown', label: __( 'WooCommerce Sort Dropdown (Pro)', 'ajax-filter-posts' ), icon: 'sort', isPro: true },
			{ id: 'active-filters', label: __( 'Active Filter Chips Bar (Pro)', 'ajax-filter-posts' ), icon: 'tag', isPro: true },
		];

		return el(
			'div',
			{ className: 'gm-tab-panel-container' },
			el(
				'div',
				{ className: 'gm-tab-action-bar' },
				el(
					'div',
					{ ref: addBtnRef, className: 'gm-add-filter-wrap' },
					el(
						Button,
						{
							isPrimary: true,
							onClick: () => setShowAddPopover( ! showAddPopover ),
							className: 'gm-add-filter-btn',
						},
						'+ ' + __( 'Add Filter', 'ajax-filter-posts' )
					),
					showAddPopover &&
						el(
							Popover,
							{
								className: 'gm-add-facet-popover',
								onClose: () => setShowAddPopover( false ),
								position: 'bottom left',
							},
							el(
								'div',
								{ className: 'gm-facet-picker-menu' },
								facetTypes.map( ( f ) =>
									el(
										'button',
										{
											key: f.id,
											type: 'button',
											className: 'gm-facet-picker-item' + ( f.isPro ? ' is-pro' : '' ),
											disabled: f.isPro && ! isPro,
											onClick: () => addFacet( f.id ),
										},
										el( 'span', { className: 'dashicons dashicons-' + f.icon } ),
										el( 'span', null, f.label )
									)
								)
							)
						)
				)
			),

			facets.length === 0
				? el(
						'div',
						{ className: 'gm-empty-facets-card' },
						el( 'span', { className: 'dashicons dashicons-filter' } ),
						el( 'h4', null, __( 'No filters configured yet', 'ajax-filter-posts' ) ),
						el( 'p', null, __( 'Add category tabs, dropdowns, checkboxes, or a search bar above.', 'ajax-filter-posts' ) )
				  )
				: facets.map( ( facet, idx ) => {
						const isExpanded = expandedIdx === idx;
						return el(
							'div',
							{ key: idx, className: 'gm-facet-row-card' + ( isExpanded ? ' is-expanded' : '' ) },
							el(
								'div',
								{ className: 'gm-facet-summary-row', onClick: () => setExpandedIdx( isExpanded ? -1 : idx ) },
								el(
									'div',
									{ className: 'gm-facet-summary-left' },
									el( 'span', { className: 'dashicons dashicons-menu gm-drag-handle' } ),
									el( 'span', { className: 'gm-facet-chip' }, facet.type ),
									el( 'strong', { className: 'gm-facet-name' }, facet.heading || facet.type )
								),
								el(
									'div',
									{ className: 'gm-facet-summary-right', onClick: ( e ) => e.stopPropagation() },
									el( Button, {
										icon: 'arrow-up-alt2',
										isSmall: true,
										disabled: idx === 0,
										onClick: () => moveFacet( idx, -1 ),
										label: __( 'Move filter up', 'ajax-filter-posts' ),
									} ),
									el( Button, {
										icon: 'arrow-down-alt2',
										isSmall: true,
										disabled: idx === facets.length - 1,
										onClick: () => moveFacet( idx, 1 ),
										label: __( 'Move filter down', 'ajax-filter-posts' ),
									} ),
									el( Button, {
										icon: 'trash',
										isDestructive: true,
										isSmall: true,
										onClick: () => removeFacet( idx ),
										label: __( 'Remove filter', 'ajax-filter-posts' ),
									} ),
									el( Button, {
										icon: isExpanded ? 'arrow-up' : 'arrow-down',
										isSmall: true,
										onClick: () => setExpandedIdx( isExpanded ? -1 : idx ),
										label: isExpanded ? __( 'Collapse', 'ajax-filter-posts' ) : __( 'Expand', 'ajax-filter-posts' ),
									} )
								)
							),

							isExpanded &&
								el(
									'div',
									{ className: 'gm-facet-detail-body' },
									( facet.type.startsWith( 'taxonomy' ) || facet.type === 'multi-select' || facet.type === 'woo-attribute-swatches' ) &&
										el(
											Fragment,
											null,
											el( SelectControl, {
												label: __( 'Taxonomy / Attribute Source', 'ajax-filter-posts' ),
												value: facet.taxonomy || 'category',
												options: ( metadata.taxonomies || [] ).map( ( t ) => ( { label: t.label + ' (' + t.name + ')', value: t.name } ) ),
												onChange: ( val ) => updateFacet( idx, 'taxonomy', val ),
											} ),
											isPro &&
												el( ToggleControl, {
													label: __( 'Hide Empty Terms on Dynamic Recount (Pro)', 'ajax-filter-posts' ),
													checked: !! facet.hide_empty_dynamic,
													onChange: ( val ) => updateFacet( idx, 'hide_empty_dynamic', val ),
													help: __( 'Automatically hide filter options that drop to 0 matching results after filtering.', 'ajax-filter-posts' ),
												} )
										),
									el( TextControl, {
										label: __( 'Filter Label / Heading', 'ajax-filter-posts' ),
										value: facet.heading || '',
										onChange: ( val ) => updateFacet( idx, 'heading', val ),
									} ),
									facet.type === 'meta-filter' &&
										el( TextControl, {
											label: __( 'Post Meta Key', 'ajax-filter-posts' ),
											value: facet.meta_key || '',
											placeholder: 'e.g. event_city',
											onChange: ( val ) => updateFacet( idx, 'meta_key', val ),
											help: __( ' visitors can type a value to filter posts matching this custom field key.', 'ajax-filter-posts' ).trim(),
										} ),
									facet.type === 'search' &&
										el( TextControl, {
											label: __( 'Search Input Placeholder', 'ajax-filter-posts' ),
											value: facet.placeholder || __( 'Search posts...', 'ajax-filter-posts' ),
											onChange: ( val ) => updateFacet( idx, 'placeholder', val ),
										} ),
									facet.type === 'taxonomy-radio' &&
										el( TextControl, {
											label: __( '"All" Option Label', 'ajax-filter-posts' ),
											value: facet.all_label || __( 'All', 'ajax-filter-posts' ),
											onChange: ( val ) => updateFacet( idx, 'all_label', val ),
										} ),
									facet.type === 'taxonomy-tabs' &&
										el(
											Fragment,
											null,
											el( SelectControl, {
												label: __( 'Tabs Display Style', 'ajax-filter-posts' ),
												value: facet.style || 'tabs',
												options: [
													{ label: __( 'Standard Tabs', 'ajax-filter-posts' ), value: 'tabs' },
													{ label: __( 'Pill Chips (Wrap)', 'ajax-filter-posts' ), value: 'chips' },
													{ label: __( 'Pill Chips (Horizontal Scroll)', 'ajax-filter-posts' ), value: 'chips_scroll' },
												],
												onChange: ( val ) => updateFacet( idx, 'style', val ),
											} ),
											el( ToggleControl, {
												label: __( 'Allow Multiple Active Tabs', 'ajax-filter-posts' ),
												checked: !! facet.multi_select,
												onChange: ( val ) => updateFacet( idx, 'multi_select', val ),
												help: __( 'Allow visitors to select multiple category tabs simultaneously.', 'ajax-filter-posts' ),
											} ),
											el( ToggleControl, {
												label: __( 'Show "All" Tab', 'ajax-filter-posts' ),
												checked: facet.show_all !== false,
												onChange: ( val ) => updateFacet( idx, 'show_all', val ),
											} ),
											facet.show_all !== false &&
												el( TextControl, {
													label: __( '"All" Tab Label', 'ajax-filter-posts' ),
													value: facet.all_label || __( 'All', 'ajax-filter-posts' ),
													onChange: ( val ) => updateFacet( idx, 'all_label', val ),
												} )
										),
									facet.type === 'woo-price-range' &&
										el(
											Fragment,
											null,
											el( TextControl, {
												label: __( 'Minimum Price', 'ajax-filter-posts' ),
												type: 'number',
												value: facet.min_price !== undefined ? facet.min_price : 0,
												onChange: ( val ) => updateFacet( idx, 'min_price', Number( val ) || 0 ),
											} ),
											el( TextControl, {
												label: __( 'Maximum Price', 'ajax-filter-posts' ),
												type: 'number',
												value: facet.max_price !== undefined ? facet.max_price : 500,
												onChange: ( val ) => updateFacet( idx, 'max_price', Number( val ) || 500 ),
											} ),
											el( TextControl, {
												label: __( 'Price Step', 'ajax-filter-posts' ),
												type: 'number',
												value: facet.step !== undefined ? facet.step : 5,
												onChange: ( val ) => updateFacet( idx, 'step', Math.max( 1, Number( val ) || 1 ) ),
											} )
										),
									facet.type === 'woo-attribute-swatches' &&
										el(
											Fragment,
											null,
											el( SelectControl, {
												label: __( 'Swatch Display Style', 'ajax-filter-posts' ),
												value: facet.swatch_style || 'auto',
												options: [
													{ label: __( 'Auto-Detect (Color / Image / Label)', 'ajax-filter-posts' ), value: 'auto' },
													{ label: __( 'Color Circles', 'ajax-filter-posts' ), value: 'color' },
													{ label: __( 'Image Swatches', 'ajax-filter-posts' ), value: 'image' },
													{ label: __( 'Label Pills', 'ajax-filter-posts' ), value: 'pills' },
												],
												onChange: ( val ) => updateFacet( idx, 'swatch_style', val ),
											} ),
											el( ToggleControl, {
												label: __( 'Allow Multiple Swatch Selection', 'ajax-filter-posts' ),
												checked: facet.multi_select !== false,
												onChange: ( val ) => updateFacet( idx, 'multi_select', val ),
											} ),
											el( ToggleControl, {
												label: __( 'Show Product / Post Count', 'ajax-filter-posts' ),
												checked: !! facet.show_count,
												onChange: ( val ) => updateFacet( idx, 'show_count', val ),
											} )
										),
									facet.type === 'woo-status-toggles' &&
										el(
											Fragment,
											null,
											el( ToggleControl, {
												label: __( 'Show "On Sale" Toggle', 'ajax-filter-posts' ),
												checked: facet.show_on_sale !== false,
												onChange: ( val ) => updateFacet( idx, 'show_on_sale', val ),
											} ),
											el( ToggleControl, {
												label: __( 'Show "In Stock Only" Toggle', 'ajax-filter-posts' ),
												checked: facet.show_in_stock !== false,
												onChange: ( val ) => updateFacet( idx, 'show_in_stock', val ),
											} ),
											el( ToggleControl, {
												label: __( 'Show "Featured" Toggle', 'ajax-filter-posts' ),
												checked: facet.show_featured !== false,
												onChange: ( val ) => updateFacet( idx, 'show_featured', val ),
											} )
										)
								)
						);
				  } )
		);
	}

	/**
	 * Pagination Tab: Visual radio cards with contextual settings.
	 */
	function PaginationTab( props ) {
		const { config, updateConfigSection } = props;
		const currentType = config.pagination?.type || 'numbered';

		const paginationOptions = [
			{ id: 'numbered', label: __( 'Page numbers', 'ajax-filter-posts' ), desc: __( 'Traditional numbered pagination (1, 2, 3...)', 'ajax-filter-posts' ), icon: 'editor-ol' },
			{ id: 'prev_next', label: __( 'Previous / Next', 'ajax-filter-posts' ), desc: __( 'Simple forward and back navigation links', 'ajax-filter-posts' ), icon: 'leftright' },
			{ id: 'load_more', label: __( 'Load more', 'ajax-filter-posts' ), desc: __( 'Interactive button to load the next set of posts', 'ajax-filter-posts' ), icon: 'plus-circle' },
			{ id: 'infinite', label: __( 'Infinite scroll', 'ajax-filter-posts' ), desc: __( 'Automatically load posts when scrolling to bottom', 'ajax-filter-posts' ), icon: 'update' },
			{ id: 'none', label: __( 'None', 'ajax-filter-posts' ), desc: __( 'Display fixed count without pagination controls', 'ajax-filter-posts' ), icon: 'no' },
		];

		return el(
			'div',
			{ className: 'gm-tab-panel-container' },
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-ellipsis' } ), ' ' + __( 'Pagination Mode', 'ajax-filter-posts' ) ),
				el(
					'div',
					{ className: 'gm-radio-cards-grid' },
					paginationOptions.map( ( opt ) => {
						const isSelected = currentType === opt.id;
						return el(
							'button',
							{
								key: opt.id,
								type: 'button',
								className: 'gm-radio-card' + ( isSelected ? ' is-selected' : '' ),
								onClick: () => updateConfigSection( 'pagination', 'type', opt.id ),
							},
							el( 'span', { className: 'dashicons dashicons-' + opt.icon + ' gm-radio-card-icon' } ),
							el( 'strong', { className: 'gm-radio-card-title' }, opt.label ),
							el( 'span', { className: 'gm-radio-card-desc' }, opt.desc )
						);
					} )
				),

				currentType === 'load_more' &&
					el(
						'div',
						{ className: 'gm-contextual-subfield' },
						el( TextControl, {
							label: __( 'Load More Button Text', 'ajax-filter-posts' ),
							value: config.pagination?.load_more_text || __( 'Load More', 'ajax-filter-posts' ),
							onChange: ( val ) => updateConfigSection( 'pagination', 'load_more_text', val ),
						} )
					),

				currentType === 'infinite' &&
					el(
						'div',
						{ className: 'gm-contextual-subfield' },
						el( TextControl, {
							label: __( 'Loading Indicator Text', 'ajax-filter-posts' ),
							value: config.pagination?.loading_text || __( 'Loading more...', 'ajax-filter-posts' ),
							onChange: ( val ) => updateConfigSection( 'pagination', 'loading_text', val ),
						} ),
						el(
							'p',
							{ className: 'gm-control-help', style: { marginTop: '8px', color: '#64748b', fontSize: '13px', lineHeight: 1.5 } },
							__( '⚡ Auto-paging is active: new posts and reels automatically stream in as visitors scroll down the feed (TikTok, Shorts & Instagram style).', 'ajax-filter-posts' )
						)
					)
			)
		);
	}

	/**
	 * Layout Tab: Design & Layout presets, responsive columns, and grid gaps.
	 */
	function LayoutTab( props ) {
		const { config, setConfig, updateConfigSection, isPro, showToast } = props;
		const { updateBlockAttributes, replaceInnerBlocks } = useDispatch( 'core/block-editor' );
		const rootCardBlock = useSelect( ( select ) => {
			const blocks = select( 'core/block-editor' ).getBlocks();
			return blocks.length > 0 && blocks[ 0 ].name === 'gridmaster/card' ? blocks[ 0 ] : null;
		} );
		const [ activePresetId, setActivePresetId ] = useState( null );

		const presets = [
			{
				id: 'modern-clean',
				title: __( 'Modern Clean', 'ajax-filter-posts' ),
				desc: __( 'Balanced 3-column card grid with crisp borders and soft elevation.', 'ajax-filter-posts' ),
				icon: 'grid-view',
				tag: __( '3 Cols • 24px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 2, md: 2, lg: 3, xl: 3 },
				col_gap: 24,
				row_gap: 28,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '20px',
					borderRadius: '8px',
					borderWidth: '1px',
					borderColor: '#dcdcde',
					boxShadow: '0 2px 4px rgba(0,0,0,0.04)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'horizontal-list',
				title: __( 'Horizontal List View', 'ajax-filter-posts' ),
				desc: __( 'Side-by-side list view with 35% media on the left and full-width rows.', 'ajax-filter-posts' ),
				icon: 'align-left',
				tag: __( '2 Cols • 24px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 1, md: 2, lg: 2, xl: 2 },
				col_gap: 24,
				row_gap: 24,
				card: {
					layoutMode: 'horizontal',
					mediaWidth: '35%',
					backgroundColor: '#ffffff',
					padding: '16px',
					borderRadius: '10px',
					borderWidth: '1px',
					borderColor: '#e2e8f0',
					boxShadow: 'none',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'hero-overlay',
				title: __( 'Hero Overlay (Cover)', 'ajax-filter-posts' ),
				desc: __( 'Full bleed background image cards with gradient scrim and white text.', 'ajax-filter-posts' ),
				icon: 'cover-image',
				tag: __( '3 Cols • 24px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 2, md: 2, lg: 3, xl: 3 },
				col_gap: 24,
				row_gap: 28,
				card: {
					layoutMode: 'overlay',
					minHeight: '340px',
					backgroundColor: '#1e293b',
					padding: '24px',
					borderRadius: '12px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 8px 25px rgba(0,0,0,0.15)',
					hoverEffect: 'zoom',
				},
			},
			{
				id: 'elevated-cards',
				title: __( 'Elevated Cards', 'ajax-filter-posts' ),
				desc: __( 'Rich floating cards with generous rounding and smooth ambient depth.', 'ajax-filter-posts' ),
				icon: 'format-aside',
				tag: __( '3 Cols • 32px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 2, md: 2, lg: 3, xl: 3 },
				col_gap: 32,
				row_gap: 32,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '24px',
					borderRadius: '14px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 10px 25px rgba(0,0,0,0.08)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'news-magazine',
				title: __( 'News & Magazine', 'ajax-filter-posts' ),
				desc: __( 'Dense 4-column compact grid engineered for high-volume content feeds.', 'ajax-filter-posts' ),
				icon: 'media-document',
				tag: __( '4 Cols • 16px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 2, md: 2, lg: 4, xl: 4 },
				col_gap: 16,
				row_gap: 20,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '14px',
					borderRadius: '6px',
					borderWidth: '1px',
					borderColor: '#e2e8f0',
					boxShadow: 'none',
					hoverEffect: 'zoom',
				},
			},
			{
				id: 'compact-sidebar',
				title: __( 'Compact List (Sidebar)', 'ajax-filter-posts' ),
				desc: __( 'Single-column dense horizontal row layout for sidebars or widgets.', 'ajax-filter-posts' ),
				icon: 'menu-alt',
				tag: __( '1 Col • 12px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 1, md: 1, lg: 1, xl: 1 },
				col_gap: 12,
				row_gap: 12,
				card: {
					layoutMode: 'horizontal',
					mediaWidth: '85px',
					backgroundColor: '#ffffff',
					padding: '10px',
					borderRadius: '6px',
					borderWidth: '1px',
					borderColor: '#f1f5f9',
					boxShadow: 'none',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'minimalist',
				title: __( 'Minimalist', 'ajax-filter-posts' ),
				desc: __( 'Lightweight flush design without borders, ideal for portfolios and galleries.', 'ajax-filter-posts' ),
				icon: 'portfolio',
				tag: __( '3 Cols • 20px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 2, md: 2, lg: 3, xl: 3 },
				col_gap: 20,
				row_gap: 36,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '0px',
					borderRadius: '0px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: 'none',
					hoverEffect: 'none',
				},
			},
			{
				id: 'dark-aesthetic',
				title: __( 'Dark Aesthetic', 'ajax-filter-posts' ),
				desc: __( 'High-contrast slate dark cards with glowing borders and deep contrast.', 'ajax-filter-posts' ),
				icon: 'admin-appearance',
				tag: __( '3 Cols • 24px Gap', 'ajax-filter-posts' ),
				columns: { xs: 1, sm: 2, md: 2, lg: 3, xl: 3 },
				col_gap: 24,
				row_gap: 28,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#1e293b',
					padding: '20px',
					borderRadius: '12px',
					borderWidth: '1px',
					borderColor: '#334155',
					boxShadow: '0 4px 12px rgba(0,0,0,0.25)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'ecommerce-showcase',
				title: __( 'E-Commerce (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Optimized product showcase grid with tight padding and 4 columns.', 'ajax-filter-posts' ),
				icon: 'cart',
				tag: __( '4 Cols • 20px Gap', 'ajax-filter-posts' ),
				isPro: true,
				columns: { xs: 1, sm: 2, md: 2, lg: 4, xl: 4 },
				col_gap: 20,
				row_gap: 28,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '16px',
					borderRadius: '10px',
					borderWidth: '1px',
					borderColor: '#e5e7eb',
					boxShadow: '0 4px 12px rgba(0,0,0,0.05)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'masonry-pinterest',
				title: __( 'Masonry Mosaic', 'ajax-filter-posts' ),
				desc: __( 'Dynamic staggered variable-height bricks arranged cleanly into crisp vertical columns.', 'ajax-filter-posts' ),
				icon: 'schedule',
				tag: __( 'Masonry • 3 Cols', 'ajax-filter-posts' ),
				layoutType: 'masonry',
				columns: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 },
				col_gap: 24,
				row_gap: 24,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '18px',
					borderRadius: '10px',
					borderWidth: '1px',
					borderColor: '#e2e8f0',
					boxShadow: '0 4px 12px rgba(0,0,0,0.05)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'metro-editorial',
				title: __( 'Metro Magazine Mosaic', 'ajax-filter-posts' ),
				desc: __( 'Editorial mosaic grid featuring highlighted 2×2 lead stories packed densely.', 'ajax-filter-posts' ),
				icon: 'layout',
				tag: __( 'Metro • 4 Cols', 'ajax-filter-posts' ),
				layoutType: 'metro',
				columns: { xs: 1, sm: 2, md: 3, lg: 4, xl: 4 },
				col_gap: 18,
				row_gap: 18,
				card: {
					layoutMode: 'overlay',
					minHeight: '220px',
					backgroundColor: '#1e293b',
					padding: '20px',
					borderRadius: '10px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 6px 18px rgba(0,0,0,0.14)',
					hoverEffect: 'zoom',
				},
			},
			{
				id: 'accessible-slider',
				title: __( 'Accessible Carousel', 'ajax-filter-posts' ),
				desc: __( 'Touch & keyboard accessible carousel with smooth scroll snap, dots & arrows.', 'ajax-filter-posts' ),
				icon: 'slides',
				tag: __( 'Slider • 3 Slides', 'ajax-filter-posts' ),
				layoutType: 'slider',
				columns: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 },
				slider: {
					slides_to_show: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 },
					arrows: true,
					dots: true,
					autoplay: false,
					autoplay_speed: 3000,
					pause_on_hover: true,
					infinite: true,
				},
				col_gap: 24,
				row_gap: 0,
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#ffffff',
					padding: '20px',
					borderRadius: '12px',
					borderWidth: '1px',
					borderColor: '#e2e8f0',
					boxShadow: '0 4px 14px rgba(0,0,0,0.06)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'autoplay-carousel',
				title: __( 'Autoplay Hero Carousel', 'ajax-filter-posts' ),
				desc: __( 'Continuous animated carousel with accessible pause control & cover overlay cards.', 'ajax-filter-posts' ),
				icon: 'controls-play',
				tag: __( 'Autoplay • 2 Slides', 'ajax-filter-posts' ),
				layoutType: 'slider',
				columns: { xs: 1, sm: 1, md: 2, lg: 2, xl: 2 },
				slider: {
					slides_to_show: { xs: 1, sm: 1, md: 2, lg: 2, xl: 2 },
					arrows: true,
					dots: true,
					autoplay: true,
					autoplay_speed: 3500,
					pause_on_hover: true,
					infinite: true,
				},
				col_gap: 20,
				row_gap: 0,
				card: {
					layoutMode: 'overlay',
					minHeight: '320px',
					backgroundColor: '#1e293b',
					padding: '24px',
					borderRadius: '12px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 8px 24px rgba(0,0,0,0.18)',
					hoverEffect: 'zoom',
				},
			},
			{
				id: 'tiktok-reels-grid',
				title: __( 'TikTok / Reels 9:16 Feed (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Vertical 4-column 9:16 portrait video feed with auto-paging on scroll and dark overlay cards.', 'ajax-filter-posts' ),
				icon: 'smartphone',
				tag: __( '4 Cols • 9:16 Infinite', 'ajax-filter-posts' ),
				isPro: true,
				columns: { xs: 1, sm: 2, md: 3, lg: 4, xl: 4 },
				col_gap: 16,
				row_gap: 24,
				pagination: {
					type: 'infinite',
					loading_text: __( 'Streaming next reels...', 'ajax-filter-posts' ),
				},
				card: {
					layoutMode: 'overlay',
					minHeight: '440px',
					backgroundColor: '#000000',
					padding: '16px',
					borderRadius: '16px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 8px 30px rgba(0,0,0,0.5)',
					hoverEffect: 'zoom',
				},
			},
			{
				id: 'reels-shorts-slider',
				title: __( 'Reels & Shorts Carousel (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Horizontal swipeable vertical reel carousel (5 slides desktop, 2 mobile) with scroll snap.', 'ajax-filter-posts' ),
				icon: 'slides',
				tag: __( 'Slider • 5 Vertical Reels', 'ajax-filter-posts' ),
				isPro: true,
				layoutType: 'slider',
				columns: { xs: 2, sm: 2, md: 3, lg: 5, xl: 5 },
				slider: {
					slides_to_show: { xs: 2, sm: 2, md: 3, lg: 5, xl: 5 },
					arrows: true,
					dots: false,
					autoplay: false,
					autoplay_speed: 3000,
					pause_on_hover: true,
					infinite: false,
				},
				col_gap: 16,
				row_gap: 0,
				card: {
					layoutMode: 'overlay',
					minHeight: '380px',
					backgroundColor: '#000000',
					padding: '16px',
					borderRadius: '16px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 8px 24px rgba(0,0,0,0.4)',
					hoverEffect: 'zoom',
				},
			},
			{
				id: 'cinematic-video-grid',
				title: __( 'Cinematic Video Showcase Grid (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Dark neon aesthetic 3-column video showcase grid with glowing accents and auto-paging infinite stream.', 'ajax-filter-posts' ),
				icon: 'format-video',
				tag: __( '3 Cols • Video Grid', 'ajax-filter-posts' ),
				isPro: true,
				columns: { xs: 1, sm: 2, md: 2, lg: 3, xl: 3 },
				col_gap: 24,
				row_gap: 28,
				pagination: {
					type: 'infinite',
					loading_text: __( 'Loading more videos...', 'ajax-filter-posts' ),
				},
				card: {
					layoutMode: 'vertical',
					backgroundColor: '#0b0e16',
					padding: '16px',
					borderRadius: '14px',
					borderWidth: '1px',
					borderColor: 'rgba(150,95,255,0.3)',
					boxShadow: '0 6px 24px rgba(0,0,0,0.45)',
					hoverEffect: 'lift',
				},
			},
			{
				id: 'youtube-shorts-vertical',
				title: __( 'YouTube / FB Reels Snap Feed (Pro)', 'ajax-filter-posts' ),
				desc: __( 'Full-portrait 9:16 vertical snap feed. Swipe top-to-bottom one-by-one with up/down navigation and auto-paging.', 'ajax-filter-posts' ),
				icon: 'video-alt3',
				tag: __( 'Vertical Snap • 1 by 1', 'ajax-filter-posts' ),
				isPro: true,
				layoutType: 'reels',
				columns: { xs: 1, sm: 1, md: 1, lg: 1, xl: 1 },
				col_gap: 0,
				row_gap: 0,
				pagination: {
					type: 'infinite',
					loading_text: __( 'Loading next reel...', 'ajax-filter-posts' ),
				},
				card: {
					layoutMode: 'overlay',
					minHeight: '100%',
					backgroundColor: '#000000',
					padding: '20px',
					borderRadius: '20px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 12px 40px rgba(0,0,0,0.5)',
					hoverEffect: 'none',
				},
			},
		];

		const applyPreset = ( preset ) => {
			setActivePresetId( preset.id );
			if ( showToast ) {
				showToast( sprintf( __( 'Applied "%s" grid preset.', 'ajax-filter-posts' ), preset.title ), 'success', 'layout' );
			}
			setConfig( ( prev ) => {
				const nextLayout = {
					...prev.layout,
					type: preset.layoutType || prev.layout?.type || 'grid',
					columns: { ...preset.columns },
					col_gap: preset.col_gap,
					row_gap: preset.row_gap,
				};
				if ( preset.slider ) {
					nextLayout.slider = { ...preset.slider };
				}
				const nextConfig = {
					...prev,
					layout: nextLayout,
				};
				if ( preset.pagination ) {
					nextConfig.pagination = {
						...prev.pagination,
						...preset.pagination,
					};
				}
				return nextConfig;
			} );
			if ( rootCardBlock && preset.card ) {
				updateBlockAttributes( rootCardBlock.clientId, { ...preset.card } );

				// If applying a horizontal preset to a card with multiple un-grouped non-media blocks,
				// wrap them into a core/group so they neatly stack in a column next to the media.
				const isHorizontalPreset = preset.card.layoutMode === 'horizontal' || preset.card.layoutMode === 'horizontal-reverse';
				const innerBlocks = rootCardBlock.innerBlocks || [];
				if ( isHorizontalPreset && innerBlocks.length > 1 && replaceInnerBlocks ) {
					const hasGroupWrapper = innerBlocks.length === 2 &&
						( innerBlocks[ 0 ].name === 'core/group' || innerBlocks[ 1 ].name === 'core/group' );
					if ( ! hasGroupWrapper ) {
						const mediaBlocks = [];
						const contentBlocks = [];
						innerBlocks.forEach( ( b ) => {
							if ( b.name === 'gridmaster/featured-image' || b.name === 'gridmaster-pro/card-video' ) {
								mediaBlocks.push( b );
							} else {
								contentBlocks.push( b );
							}
						} );
						if ( contentBlocks.length > 0 ) {
							const padLeft = preset.card.layoutMode === 'horizontal' ? '16px' : '0px';
							const padRight = preset.card.layoutMode === 'horizontal-reverse' ? '16px' : '0px';
							const groupBlock = createBlock( 'core/group', {
								style: { spacing: { padding: { top: '0px', bottom: '0px', left: padLeft, right: padRight } } },
							}, contentBlocks );
							const newBlocks = preset.card.layoutMode === 'horizontal-reverse'
								? [ groupBlock, ...mediaBlocks ]
								: [ ...mediaBlocks, groupBlock ];
							replaceInnerBlocks( rootCardBlock.clientId, newBlocks );
						}
					}
				}
			}
		};

		const updateLayoutType = ( type ) => {
			setConfig( ( prev ) => ( {
				...prev,
				layout: {
					...prev.layout,
					type: type,
				},
			} ) );
		};

		const updateColumn = ( breakpoint, val ) => {
			setConfig( ( prev ) => {
				const next = { ...prev };
				const nextCols = { ...next.layout?.columns, [ breakpoint ]: parseInt( val, 10 ) || 1 };
				if ( breakpoint === 'lg' ) {
					nextCols.xl = parseInt( val, 10 ) || 1;
				}
				next.layout = { ...next.layout, columns: nextCols };
				return next;
			} );
		};

		const updateSliderConfig = ( key, val ) => {
			setConfig( ( prev ) => {
				const currentSlider = prev.layout?.slider || {
					slides_to_show: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 },
					arrows: true,
					dots: true,
					autoplay: false,
					autoplay_speed: 3000,
					pause_on_hover: true,
					infinite: true,
				};
				return {
					...prev,
					layout: {
						...prev.layout,
						slider: {
							...currentSlider,
							[ key ]: val,
						},
					},
				};
			} );
		};

		const updateSliderSlideToShow = ( breakpoint, val ) => {
			setConfig( ( prev ) => {
				const currentSlider = prev.layout?.slider || {
					slides_to_show: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 },
					arrows: true,
					dots: true,
					autoplay: false,
					autoplay_speed: 3000,
					pause_on_hover: true,
					infinite: true,
				};
				const currentSlides = { ...( currentSlider.slides_to_show || { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 } ) };
				currentSlides[ breakpoint ] = parseInt( val, 10 ) || 1;
				if ( breakpoint === 'lg' ) {
					currentSlides.xl = parseInt( val, 10 ) || 1;
				}
				return {
					...prev,
					layout: {
						...prev.layout,
						slider: {
							...currentSlider,
							slides_to_show: currentSlides,
						},
					},
				};
			} );
		};

		const updateReelsConfig = ( key, val ) => {
			setConfig( ( prev ) => {
				const currentReels = prev.layout?.reels || {
					show_nav: true,
					nav_bg: '#1e293b',
					nav_color: '#ffffff',
					nav_hover_bg: '#334155',
					nav_hover_color: '#ffffff',
					nav_size: 46,
					nav_position: 'outside',
					show_end_slide: true,
					end_text: __( "You're All Caught Up!", 'ajax-filter-posts' ),
				};
				return {
					...prev,
					layout: {
						...prev.layout,
						reels: {
							...currentReels,
							[ key ]: val,
						},
					},
				};
			} );
		};

		const reelsConfig = config.layout?.reels || {
			show_nav: true,
			nav_bg: '#1e293b',
			nav_color: '#ffffff',
			nav_hover_bg: '#334155',
			nav_hover_color: '#ffffff',
			nav_size: 46,
			nav_position: 'outside',
			show_end_slide: true,
			end_text: __( "You're All Caught Up!", 'ajax-filter-posts' ),
		};

		const layoutType = config.layout?.type || 'grid';
		const isSlider = 'slider' === layoutType || 'carousel' === layoutType;
		const isReels = 'reels' === layoutType || 'shorts' === layoutType;
		const isMasonry = 'masonry' === layoutType;
		const isMetro = 'metro' === layoutType;
		const isGrid = ! isSlider && ! isReels;
		const filterPosition = config.layout?.filter_position || 'top';
		const sidebarWidth = config.layout?.sidebar_width || 280;
		const stickySidebar = !! config.layout?.sticky_sidebar;
		const drawerLabel = config.layout?.drawer_button_label || __( 'Filters', 'ajax-filter-posts' );
		const sliderConfig = config.layout?.slider || {
			slides_to_show: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 },
			arrows: true,
			dots: true,
			autoplay: false,
			autoplay_speed: 3000,
			pause_on_hover: true,
			infinite: true,
		};

		const colLg = config.layout?.columns?.lg ?? 3;
		const colMd = config.layout?.columns?.md ?? 2;
		const colXs = config.layout?.columns?.xs ?? 1;

		const slideLg = sliderConfig.slides_to_show?.lg ?? colLg;
		const slideMd = sliderConfig.slides_to_show?.md ?? colMd;
		const slideXs = sliderConfig.slides_to_show?.xs ?? colXs;

		// Exact 0px support
		const colGap = typeof config.layout?.col_gap !== 'undefined' ? config.layout.col_gap : 30;
		const rowGap = typeof config.layout?.row_gap !== 'undefined' ? config.layout.row_gap : 30;

		return el(
			'div',
			{ className: 'gm-tab-panel-container' },

			// Layout Type Card
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-forms' } ), ' ' + __( 'Layout Type', 'ajax-filter-posts' ) ),
				el(
					'div',
					{ className: 'gm-layout-type-selector' },
					el(
						'button',
						{
							type: 'button',
							className: 'gm-layout-type-option' + ( isGrid && ! isMasonry && ! isMetro ? ' is-active' : '' ),
							onClick: () => updateLayoutType( 'grid' ),
						},
						el( 'span', { className: 'dashicons dashicons-grid-view gm-layout-type-icon' } ),
						el(
							'div',
							{ className: 'gm-layout-type-info' },
							el( 'strong', null, __( 'Standard Post Grid', 'ajax-filter-posts' ) ),
							el( 'span', null, __( 'Classic responsive grid with columns and pagination.', 'ajax-filter-posts' ) )
						)
					),
					el(
						'button',
						{
							type: 'button',
							className: 'gm-layout-type-option' + ( isMasonry ? ' is-active' : '' ),
							onClick: () => updateLayoutType( 'masonry' ),
						},
						el( 'span', { className: 'dashicons dashicons-schedule gm-layout-type-icon' } ),
						el(
							'div',
							{ className: 'gm-layout-type-info' },
							el( 'strong', null, __( 'Masonry Bricks', 'ajax-filter-posts' ) ),
							el( 'span', null, __( 'Variable-height staggered cards packed vertically without blank gaps.', 'ajax-filter-posts' ) )
						)
					),
					el(
						'button',
						{
							type: 'button',
							className: 'gm-layout-type-option' + ( isMetro ? ' is-active' : '' ),
							onClick: () => updateLayoutType( 'metro' ),
						},
						el( 'span', { className: 'dashicons dashicons-layout gm-layout-type-icon' } ),
						el(
							'div',
							{ className: 'gm-layout-type-info' },
							el( 'strong', null, __( 'Metro Mosaic Grid', 'ajax-filter-posts' ) ),
							el( 'span', null, __( 'Dense magazine mosaic featuring featured multi-column lead cards.', 'ajax-filter-posts' ) )
						)
					),
					el(
						'button',
						{
							type: 'button',
							className: 'gm-layout-type-option' + ( isSlider ? ' is-active' : '' ),
							onClick: () => updateLayoutType( 'slider' ),
						},
						el( 'span', { className: 'dashicons dashicons-slides gm-layout-type-icon' } ),
						el(
							'div',
							{ className: 'gm-layout-type-info' },
							el( 'strong', null, __( 'Accessible Carousel / Slider', 'ajax-filter-posts' ) ),
							el( 'span', null, __( 'WCAG compliant touch & arrow slider with scroll snap.', 'ajax-filter-posts' ) )
						)
					),
					el(
						'button',
						{
							type: 'button',
							className: 'gm-layout-type-option' + ( isReels ? ' is-active' : '' ),
							onClick: () => updateLayoutType( 'reels' ),
						},
						el( 'span', { className: 'dashicons dashicons-video-alt3 gm-layout-type-icon' } ),
						el(
							'div',
							{ className: 'gm-layout-type-info' },
							el( 'strong', null, __( 'Vertical Reel Snap Feed (Pro)', 'ajax-filter-posts' ) ),
							el( 'span', null, __( 'One-by-one vertical swipe feed for YouTube Shorts, Reels, and TikTok.', 'ajax-filter-posts' ) )
						)
					)
				)
			),

			// Presets Card
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-grid-view' } ), ' ' + __( 'Grid & Feed Presets', 'ajax-filter-posts' ) ),
				el( 'p', { className: 'gm-preset-intro' }, __( 'Choose a pre-styled foundation for your grid architecture (responsive columns, gaps, and feed type). Does not overwrite your card blocks.', 'ajax-filter-posts' ) ),
				el(
					'div',
					{ className: 'gm-presets-grid' },
					presets.map( ( p ) => {
						const isSelected = activePresetId === p.id;
						return el(
							'button',
							{
								key: p.id,
								type: 'button',
								className: 'gm-preset-card' + ( isSelected ? ' is-active' : '' ) + ( p.isPro ? ' is-pro' : '' ),
								onClick: () => applyPreset( p ),
							},
							el( 'span', { className: 'dashicons dashicons-' + p.icon + ' gm-preset-card-icon' } ),
							el(
								'strong',
								{ className: 'gm-preset-card-title' },
								p.title,
								p.isPro && el( 'span', { className: 'gm-preset-pro-chip' }, 'PRO' )
							),
							el( 'span', { className: 'gm-preset-card-desc' }, p.desc ),
							el( 'span', { className: 'gm-preset-card-pill' }, p.tag )
						);
					} )
				)
			),

			// Reels-specific settings
			isReels &&
				el(
					Fragment,
					null,
					// Overview & Guide Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-video-alt3' } ), ' ' + __( 'Vertical Reel Snap Feed Settings', 'ajax-filter-posts' ) ),
						el( 'p', { className: 'gm-preset-intro', style: { marginBottom: '12px' } }, __( 'Displays posts in a single-column 9:16 portrait vertical snap feed (like YouTube Shorts, Facebook Reels, and TikTok). Users can swipe top-to-bottom, click the up/down arrow buttons, or use the keyboard arrow keys.', 'ajax-filter-posts' ) ),
						el( 'div', { className: 'gm-info-callout', style: { padding: '12px 16px', background: 'rgba(99, 102, 241, 0.08)', borderRadius: '8px', borderLeft: '3px solid #6366f1', fontSize: '13px', color: '#475569' } },
							el( 'strong', null, __( 'Pro Tip: ', 'ajax-filter-posts' ) ),
							__( 'Pair this layout with Card Video set to "Autoplay Loop" and Infinite Scroll pagination for continuous reel streaming.', 'ajax-filter-posts' )
						)
					),

					// Floating Next/Prev Arrow Buttons Customization Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-controls-play' } ), ' ' + __( 'Next / Previous Navigation Buttons', 'ajax-filter-posts' ) ),
						el( ToggleControl, {
							label: __( 'Show Floating Up/Down Arrow Buttons', 'ajax-filter-posts' ),
							help: __( 'Displays floating circular buttons on the reel for jumping to previous/next reels with one click.', 'ajax-filter-posts' ),
							checked: reelsConfig.show_nav !== false,
							onChange: ( val ) => updateReelsConfig( 'show_nav', val ),
						} ),
						reelsConfig.show_nav !== false &&
							el(
								Fragment,
								null,
								el( SelectControl, {
									label: __( 'Button Placement', 'ajax-filter-posts' ),
									value: reelsConfig.nav_position || 'outside',
									options: [
										{ label: __( 'Outside Right (Desktop Floating)', 'ajax-filter-posts' ), value: 'outside' },
										{ label: __( 'Inside Right (Card Overlay)', 'ajax-filter-posts' ), value: 'inside' },
									],
									onChange: ( val ) => updateReelsConfig( 'nav_position', val ),
								} ),
								el( RangeControl, {
									label: __( 'Button Size (px)', 'ajax-filter-posts' ),
									value: reelsConfig.nav_size || 46,
									min: 36,
									max: 64,
									step: 2,
									onChange: ( val ) => updateReelsConfig( 'nav_size', val ),
								} ),
								el(
									'div',
									{ style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginTop: '14px' } },
									el(
										'div',
										null,
										el( 'label', { className: 'gm-field-label', style: { display: 'block', marginBottom: '8px', fontWeight: 600 } }, __( 'Button Background', 'ajax-filter-posts' ) ),
										el( ColorPalette, {
											value: reelsConfig.nav_bg || '#1e293b',
											onChange: ( val ) => updateReelsConfig( 'nav_bg', val || '#1e293b' ),
										} )
									),
									el(
										'div',
										null,
										el( 'label', { className: 'gm-field-label', style: { display: 'block', marginBottom: '8px', fontWeight: 600 } }, __( 'Arrow / Icon Color', 'ajax-filter-posts' ) ),
										el( ColorPalette, {
											value: reelsConfig.nav_color || '#ffffff',
											onChange: ( val ) => updateReelsConfig( 'nav_color', val || '#ffffff' ),
										} )
									)
								),
								el(
									'div',
									{ style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginTop: '14px' } },
									el(
										'div',
										null,
										el( 'label', { className: 'gm-field-label', style: { display: 'block', marginBottom: '8px', fontWeight: 600 } }, __( 'Hover Background', 'ajax-filter-posts' ) ),
										el( ColorPalette, {
											value: reelsConfig.nav_hover_bg || '#334155',
											onChange: ( val ) => updateReelsConfig( 'nav_hover_bg', val || '#334155' ),
										} )
									),
									el(
										'div',
										null,
										el( 'label', { className: 'gm-field-label', style: { display: 'block', marginBottom: '8px', fontWeight: 600 } }, __( 'Hover Icon Color', 'ajax-filter-posts' ) ),
										el( ColorPalette, {
											value: reelsConfig.nav_hover_color || '#ffffff',
											onChange: ( val ) => updateReelsConfig( 'nav_hover_color', val || '#ffffff' ),
										} )
									)
								)
							)
					),

					// End of Feed Card ("All Caught Up")
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-yes-alt' } ), ' ' + __( 'End of Feed ("All Caught Up") Card', 'ajax-filter-posts' ) ),
						el( 'p', { className: 'gm-preset-intro', style: { marginBottom: '12px' } }, __( 'Displays an attractive celebration screen when the user has viewed every reel in the feed.', 'ajax-filter-posts' ) ),
						el( ToggleControl, {
							label: __( 'Show "All Caught Up" Card at End of Reels', 'ajax-filter-posts' ),
							checked: reelsConfig.show_end_slide !== false,
							onChange: ( val ) => updateReelsConfig( 'show_end_slide', val ),
						} ),
						reelsConfig.show_end_slide !== false &&
							el( TextControl, {
								label: __( 'End Screen Heading', 'ajax-filter-posts' ),
								value: reelsConfig.end_text || __( "You're All Caught Up!", 'ajax-filter-posts' ),
								onChange: ( val ) => updateReelsConfig( 'end_text', val ),
							} )
					)
				),

			// Grid-specific settings
			isGrid &&
				el(
					Fragment,
					null,
					// Responsive Columns Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-layout' } ), ' ' + __( 'Responsive Grid Columns', 'ajax-filter-posts' ) ),
						el(
							'div',
							{ className: 'gm-responsive-columns-group' },
							el( RangeControl, {
								label: __( 'Desktop Columns', 'ajax-filter-posts' ),
								value: colLg,
								min: 1,
								max: 6,
								onChange: ( val ) => updateColumn( 'lg', val ),
							} ),
							el( RangeControl, {
								label: __( 'Tablet Columns', 'ajax-filter-posts' ),
								value: colMd,
								min: 1,
								max: 4,
								onChange: ( val ) => updateColumn( 'md', val ),
							} ),
							el( RangeControl, {
								label: __( 'Mobile Columns', 'ajax-filter-posts' ),
								value: colXs,
								min: 1,
								max: 3,
								onChange: ( val ) => updateColumn( 'xs', val ),
							} )
						)
					),

					// Spacing Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-grid-view' } ), ' ' + __( 'Grid Gaps & Spacing', 'ajax-filter-posts' ) ),
						el( RangeControl, {
							label: __( 'Column Gap (px)', 'ajax-filter-posts' ),
							value: colGap,
							min: 0,
							max: 80,
							onChange: ( val ) => updateConfigSection( 'layout', 'col_gap', val ),
							help: __( 'Horizontal gap between grid post cards. Supports 0px for compact flush grids.', 'ajax-filter-posts' ),
						} ),
						el( RangeControl, {
							label: __( 'Row Gap (px)', 'ajax-filter-posts' ),
							value: rowGap,
							min: 0,
							max: 80,
							onChange: ( val ) => updateConfigSection( 'layout', 'row_gap', val ),
							help: __( 'Vertical gap between grid rows. Supports 0px.', 'ajax-filter-posts' ),
						} )
					)
				),

			// Filter Placement & Sidebar / Drawer Settings
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-filter' } ), ' ' + __( 'Filter Placement & Mobile Drawer', 'ajax-filter-posts' ) ),
				el( SelectControl, {
					label: __( 'Filter Bar Position', 'ajax-filter-posts' ),
					value: filterPosition,
					options: [
						{ label: __( 'Top Bar (Above Grid)', 'ajax-filter-posts' ), value: 'top' },
						{ label: __( 'Left Sidebar (Split Layout + Mobile Drawer)', 'ajax-filter-posts' ), value: 'left_sidebar' },
						{ label: __( 'Right Sidebar (Split Layout + Mobile Drawer)', 'ajax-filter-posts' ), value: 'right_sidebar' },
						{ label: __( 'Off-Canvas Slide Drawer (All Devices)', 'ajax-filter-posts' ), value: 'drawer' },
					],
					onChange: ( val ) => updateConfigSection( 'layout', 'filter_position', val ),
					help: __( 'Display filters inline above results, as a desktop sidebar that collapses to a mobile drawer, or inside an off-canvas slide-over drawer.', 'ajax-filter-posts' ),
				} ),
				( filterPosition === 'left_sidebar' || filterPosition === 'right_sidebar' ) &&
					el(
						Fragment,
						null,
						el( RangeControl, {
							label: __( 'Sidebar Width (px)', 'ajax-filter-posts' ),
							value: sidebarWidth,
							min: 180,
							max: 480,
							step: 10,
							onChange: ( val ) => updateConfigSection( 'layout', 'sidebar_width', val ),
						} ),
						el( ToggleControl, {
							label: __( 'Sticky Filter Sidebar', 'ajax-filter-posts' ),
							checked: stickySidebar,
							onChange: ( val ) => updateConfigSection( 'layout', 'sticky_sidebar', val ),
							help: __( 'Keep the sidebar filters fixed in view while scrolling through long grids.', 'ajax-filter-posts' ),
						} )
					),
				filterPosition !== 'top' &&
					el( TextControl, {
						label: __( 'Drawer Toggle Button Label', 'ajax-filter-posts' ),
						value: drawerLabel,
						onChange: ( val ) => updateConfigSection( 'layout', 'drawer_button_label', val ),
					} )
			),

			// Slider-specific settings
			isSlider &&
				el(
					Fragment,
					null,
					// Responsive Slides Per View
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-slides' } ), ' ' + __( 'Responsive Slides Per View', 'ajax-filter-posts' ) ),
						el(
							'div',
							{ className: 'gm-responsive-columns-group' },
							el( RangeControl, {
								label: __( 'Desktop Slides', 'ajax-filter-posts' ),
								value: slideLg,
								min: 1,
								max: 6,
								onChange: ( val ) => updateSliderSlideToShow( 'lg', val ),
							} ),
							el( RangeControl, {
								label: __( 'Tablet Slides', 'ajax-filter-posts' ),
								value: slideMd,
								min: 1,
								max: 4,
								onChange: ( val ) => updateSliderSlideToShow( 'md', val ),
							} ),
							el( RangeControl, {
								label: __( 'Mobile Slides', 'ajax-filter-posts' ),
								value: slideXs,
								min: 1,
								max: 3,
								onChange: ( val ) => updateSliderSlideToShow( 'xs', val ),
							} )
						)
					),

					// Controls & Navigation Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-arrow-right-alt2' } ), ' ' + __( 'Carousel Navigation & Controls', 'ajax-filter-posts' ) ),
						el( ToggleControl, {
							label: __( 'Navigation Arrows', 'ajax-filter-posts' ),
							help: __( 'Show Previous / Next arrow buttons with accessible screen reader announcements.', 'ajax-filter-posts' ),
							checked: sliderConfig.arrows !== false,
							onChange: ( val ) => updateSliderConfig( 'arrows', val ),
						} ),
						el( ToggleControl, {
							label: __( 'Dots Pagination (Tablist)', 'ajax-filter-posts' ),
							help: __( 'Show accessible slide indicator dots at the bottom of the slider.', 'ajax-filter-posts' ),
							checked: sliderConfig.dots !== false,
							onChange: ( val ) => updateSliderConfig( 'dots', val ),
						} ),
						el( ToggleControl, {
							label: __( 'Infinite Wrap (Continuous)', 'ajax-filter-posts' ),
							help: __( 'When reaching the end, wrap smoothly to the first slide.', 'ajax-filter-posts' ),
							checked: sliderConfig.infinite !== false,
							onChange: ( val ) => updateSliderConfig( 'infinite', val ),
						} )
					),

					// Autoplay & Motion Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-controls-play' } ), ' ' + __( 'Autoplay & Motion', 'ajax-filter-posts' ) ),
						el( ToggleControl, {
							label: __( 'Enable Autoplay', 'ajax-filter-posts' ),
							help: __( 'Automatically advance slides at a set interval. Respects system prefers-reduced-motion.', 'ajax-filter-posts' ),
							checked: !! sliderConfig.autoplay,
							onChange: ( val ) => updateSliderConfig( 'autoplay', val ),
						} ),
						sliderConfig.autoplay &&
							el( RangeControl, {
								label: __( 'Autoplay Interval (ms)', 'ajax-filter-posts' ),
								value: sliderConfig.autoplay_speed || 3000,
								min: 1500,
								max: 10000,
								step: 500,
								onChange: ( val ) => updateSliderConfig( 'autoplay_speed', val ),
							} ),
						sliderConfig.autoplay &&
							el( ToggleControl, {
								label: __( 'Pause on Hover & Keyboard Focus', 'ajax-filter-posts' ),
								help: __( 'Recommended for WCAG accessibility compliance.', 'ajax-filter-posts' ),
								checked: sliderConfig.pause_on_hover !== false,
								onChange: ( val ) => updateSliderConfig( 'pause_on_hover', val ),
							} )
					),

					// Slide Spacing Card
					el(
						'div',
						{ className: 'gm-settings-card' },
						el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-grid-view' } ), ' ' + __( 'Slide Spacing', 'ajax-filter-posts' ) ),
						el( RangeControl, {
							label: __( 'Slide Gap (px)', 'ajax-filter-posts' ),
							value: colGap,
							min: 0,
							max: 80,
							onChange: ( val ) => updateConfigSection( 'layout', 'col_gap', val ),
							help: __( 'Space between slider cards. Supports 0px for contiguous slides.', 'ajax-filter-posts' ),
						} )
					)
				)
		);
	}

	/**
	 * Custom Code Tab: Custom CSS stylesheet & custom JavaScript lifecycle scripts.
	 */
	function CustomCodeTab( props ) {
		const { config, setConfig, updateConfigSection, canUnfilteredHtml, gridId, showToast } = props;
		const customCode = config.custom_code || {};
		const cssVal = customCode.css || config.custom_css || '';
		const jsVal = customCode.js || config.custom_js || '';

		const handleCssChange = ( val ) => {
			updateConfigSection( 'custom_code', 'css', val );
		};

		const handleJsChange = ( val ) => {
			updateConfigSection( 'custom_code', 'js', val );
		};

		const insertCssSnippet = ( snippet, name ) => {
			const current = cssVal ? cssVal.trimEnd() + '\n\n' : '';
			handleCssChange( current + snippet );
			if ( showToast ) {
				showToast( sprintf( __( 'Inserted "%s" snippet into Custom CSS.', 'ajax-filter-posts' ), name ), 'success', 'yes-alt' );
			}
		};

		const insertJsSnippet = ( snippet, name ) => {
			const current = jsVal ? jsVal.trimEnd() + '\n\n' : '';
			handleJsChange( current + snippet );
			if ( showToast ) {
				showToast( sprintf( __( 'Inserted "%s" snippet into Custom JavaScript.', 'ajax-filter-posts' ), name ), 'success', 'yes-alt' );
			}
		};

		const handleKeyDown = ( e, updateFn, currentValue ) => {
			if ( e.key === 'Tab' ) {
				e.preventDefault();
				const target = e.target;
				const start = target.selectionStart;
				const end = target.selectionEnd;
				const nextVal = currentValue.substring( 0, start ) + '  ' + currentValue.substring( end );
				updateFn( nextVal );
				requestAnimationFrame( () => {
					target.selectionStart = target.selectionEnd = start + 2;
				} );
			}
		};

		// CSS Selector Helpers
		const cssSelectors = [
			{ label: '{{GRID}}', desc: __( 'Scoped Grid Wrapper', 'ajax-filter-posts' ), snippet: '{{GRID}} {\n  \n}' },
			{ label: '.gm-card', desc: __( 'Post Card Container', 'ajax-filter-posts' ), snippet: '{{GRID}} .gm-card {\n  \n}' },
			{ label: '.gm-card-media', desc: __( 'Featured Media', 'ajax-filter-posts' ), snippet: '{{GRID}} .gm-card-media img {\n  \n}' },
			{ label: '.gm-card-title', desc: __( 'Post Title', 'ajax-filter-posts' ), snippet: '{{GRID}} .gm-card-title a {\n  \n}' },
			{ label: '.gm-filter-btn', desc: __( 'Filter Buttons', 'ajax-filter-posts' ), snippet: '{{GRID}} .gm-filter-btn {\n  \n}' },
			{ label: '.gm-pagination', desc: __( 'Pagination Controls', 'ajax-filter-posts' ), snippet: '{{GRID}} .gm-pagination {\n  \n}' },
		];

		// Out-of-the-Box CSS Style Presets
		const cssPresets = [
			{
				id: 'hover-lift',
				name: __( 'Hover 3D Lift & Shadow', 'ajax-filter-posts' ),
				snippet: `/* Hover 3D Lift & Elevation */\n{{GRID}} .gm-card {\n  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;\n}\n{{GRID}} .gm-card:hover {\n  transform: translateY(-8px);\n  box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.15);\n}`,
			},
			{
				id: 'glassmorphism',
				name: __( 'Modern Glassmorphism', 'ajax-filter-posts' ),
				snippet: `/* Modern Glassmorphism Card */\n{{GRID}} .gm-card {\n  background: rgba(255, 255, 255, 0.72) !important;\n  backdrop-filter: blur(16px);\n  -webkit-backdrop-filter: blur(16px);\n  border: 1px solid rgba(255, 255, 255, 0.45) !important;\n  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08) !important;\n}`,
			},
			{
				id: 'gradient-pills',
				name: __( 'Gradient Filter Active Pills', 'ajax-filter-posts' ),
				snippet: `/* Gradient Filter Active Pills */\n{{GRID}} .gm-filter-btn {\n  border-radius: 9999px !important;\n  transition: all 0.25s ease;\n}\n{{GRID}} .gm-filter-btn.gm-active {\n  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%) !important;\n  color: #ffffff !important;\n  border-color: transparent !important;\n  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);\n}`,
			},
			{
				id: 'media-zoom',
				name: __( 'Smooth Media Zoom on Hover', 'ajax-filter-posts' ),
				snippet: `/* Smooth Media Zoom on Hover */\n{{GRID}} .gm-card .gm-card-media,\n{{GRID}} .gm-card .gm-card__media {\n  overflow: hidden;\n  border-radius: 8px;\n}\n{{GRID}} .gm-card .gm-card-media img,\n{{GRID}} .gm-card .gm-card__media img {\n  transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);\n}\n{{GRID}} .gm-card:hover .gm-card-media img,\n{{GRID}} .gm-card:hover .gm-card__media img {\n  transform: scale(1.06);\n}`,
			},
			{
				id: 'soft-neumorphism',
				name: __( 'Soft Neumorphic Card', 'ajax-filter-posts' ),
				snippet: `/* Soft Neumorphic Card */\n{{GRID}} .gm-card {\n  background: #f8fafc !important;\n  border: 1px solid #e2e8f0 !important;\n  box-shadow: 6px 6px 16px #e2e8f0, -6px -6px 16px #ffffff !important;\n  border-radius: 16px;\n}`,
			},
		];

		// JavaScript Presets
		const jsPresets = [
			{
				id: 'card-click-analytics',
				name: __( 'Card Click Event / Analytics Tracking', 'ajax-filter-posts' ),
				snippet: `// Card Click Tracking\ngridEl.querySelectorAll('.gm-card').forEach(function(card, idx) {\n  card.addEventListener('click', function() {\n    var title = card.querySelector('.gm-card-title, .gm-card__title')?.innerText || 'Card ' + (idx + 1);\n    console.log('[GridMaster Grid #' + gridId + '] Post clicked:', title);\n  });\n});`,
			},
			{
				id: 'stagger-fade-in',
				name: __( 'Staggered Fade-In Card Animation', 'ajax-filter-posts' ),
				snippet: `// Staggered Fade-In Animation\ngridEl.querySelectorAll('.gm-card').forEach(function(card, idx) {\n  card.style.opacity = '0';\n  card.style.transform = 'translateY(16px)';\n  card.style.transition = 'opacity 0.4s ease ' + (idx * 0.05) + 's, transform 0.4s ease ' + (idx * 0.05) + 's';\n  requestAnimationFrame(function() {\n    card.style.opacity = '1';\n    card.style.transform = 'translateY(0)';\n  });\n});`,
			},
			{
				id: 'external-link-target',
				name: __( 'Auto Open External Links in New Tab', 'ajax-filter-posts' ),
				snippet: `// External Links New Tab\ngridEl.querySelectorAll('.gm-card a[href^="http"]').forEach(function(link) {\n  if (link.hostname !== window.location.hostname) {\n    link.setAttribute('target', '_blank');\n    link.setAttribute('rel', 'noopener noreferrer');\n  }\n});`,
			},
		];

		return el(
			'div',
			{ className: 'gm-tab-panel-container gm-code-tab-container' },

			// Top Intro Guidance Banner
			el(
				'div',
				{ className: 'gm-code-intro-card' },
				el(
					'h3',
					{ className: 'gm-code-intro-title' },
					el( 'span', { className: 'dashicons dashicons-editor-code' } ),
					__( 'Custom CSS Styles & JavaScript Lifecycle Scripts', 'ajax-filter-posts' )
				),
				el(
					'p',
					{ className: 'gm-code-intro-desc' },
					__( 'Write custom CSS rules to modify or invent out-of-the-box card styles, hover animations, filter gradients, and responsive layouts. Use custom JavaScript to bind interactive behaviors that execute on render and re-trigger automatically on AJAX filter/pagination updates.', 'ajax-filter-posts' )
				)
			),

			// Card 1: Custom CSS
			el(
				'div',
				{ className: 'gm-settings-card' },
				el(
					'div',
					{ className: 'gm-code-editor-header' },
					el(
						'h4',
						{ className: 'gm-code-editor-heading' },
						el( 'span', { className: 'dashicons dashicons-art' } ),
						__( 'Custom CSS Stylesheet', 'ajax-filter-posts' )
					)
				),

				// Quick Selector Insertion Chips
				el(
					'div',
					{ className: 'gm-code-toolbar-section' },
					el( 'span', { className: 'gm-code-toolbar-label' }, __( 'Target Selectors (Click to Insert):', 'ajax-filter-posts' ) ),
					el(
						'div',
						{ className: 'gm-code-chips-group' },
						cssSelectors.map( ( item ) =>
							el(
								'button',
								{
									key: item.label,
									type: 'button',
									className: 'gm-code-chip-btn',
									title: item.desc,
									onClick: () => insertCssSnippet( item.snippet, item.label ),
								},
								'+ ' + item.label
							)
						)
					)
				),

				// Style Presets
				el(
					'div',
					{ className: 'gm-code-toolbar-section' },
					el( 'span', { className: 'gm-code-toolbar-label' }, __( 'Out-of-the-Box Style Presets:', 'ajax-filter-posts' ) ),
					el(
						'div',
						{ className: 'gm-code-preset-pills' },
						cssPresets.map( ( preset ) =>
							el(
								'button',
								{
									key: preset.id,
									type: 'button',
									className: 'gm-code-preset-pill',
									onClick: () => insertCssSnippet( preset.snippet, preset.name ),
								},
								el( 'span', { className: 'dashicons dashicons-plus-alt2' } ),
								preset.name
							)
						)
					)
				),

				// CSS Code Textarea Editor
				el(
					'div',
					{ className: 'gm-code-textarea-wrapper' },
					el( 'textarea', {
						className: 'gm-code-textarea',
						value: cssVal,
						rows: 12,
						spellCheck: false,
						placeholder: `/* Write custom CSS scoped to this grid */\n{{GRID}} .gm-card {\n  /* Your styles here */\n}`,
						onChange: ( e ) => handleCssChange( e.target.value ),
						onKeyDown: ( e ) => handleKeyDown( e, handleCssChange, cssVal ),
					} )
				),

				el(
					'div',
					{ className: 'gm-code-footer-help' },
					el( 'span', null,
						__( 'Scope macro: Use ', 'ajax-filter-posts' ),
						el( 'span', { className: 'gm-code-tag-badge' }, '{{GRID}}' ),
						__( ' to automatically prefix rules with #gm-grid-', 'ajax-filter-posts' ) + ( gridId || '<id>' ) + '.'
					),
					el( 'span', null, __( 'Live in Card canvas & preview', 'ajax-filter-posts' ) )
				)
			),

			// Card 2: Custom JavaScript Lifecycle Scripts
			el(
				'div',
				{ className: 'gm-settings-card' },
				el(
					'div',
					{ className: 'gm-code-editor-header' },
					el(
						'h4',
						{ className: 'gm-code-editor-heading' },
						el( 'span', { className: 'dashicons dashicons-admin-generic' } ),
						__( 'Custom JavaScript / Lifecycle Scripts', 'ajax-filter-posts' )
					)
				),

				! canUnfilteredHtml &&
					el(
						Notice,
						{
							status: 'warning',
							isDismissible: false,
						},
						__( 'Security Notice: Your current WordPress user role cannot edit raw JavaScript (unfiltered_html capability required). Custom CSS remains fully functional.', 'ajax-filter-posts' )
					),

				// JS Presets
				el(
					'div',
					{ className: 'gm-code-toolbar-section' },
					el( 'span', { className: 'gm-code-toolbar-label' }, __( 'Starter Script Snippets:', 'ajax-filter-posts' ) ),
					el(
						'div',
						{ className: 'gm-code-preset-pills' },
						jsPresets.map( ( preset ) =>
							el(
								'button',
								{
									key: preset.id,
									type: 'button',
									className: 'gm-code-preset-pill',
									disabled: ! canUnfilteredHtml,
									onClick: () => insertJsSnippet( preset.snippet, preset.name ),
								},
								el( 'span', { className: 'dashicons dashicons-plus-alt2' } ),
								preset.name
							)
						)
					)
				),

				// JS Code Textarea Editor
				el(
					'div',
					{ className: 'gm-code-textarea-wrapper' },
					el( 'textarea', {
						className: 'gm-code-textarea',
						value: jsVal,
						rows: 10,
						disabled: ! canUnfilteredHtml,
						spellCheck: false,
						placeholder: `// Executes on render & every AJAX page / filter reload\n// Parameters available: gridEl (DOM node), gridId (integer)\nconsole.log('Grid initialized:', gridId);`,
						onChange: ( e ) => handleJsChange( e.target.value ),
						onKeyDown: ( e ) => handleKeyDown( e, handleJsChange, jsVal ),
					} )
				),

				el(
					'div',
					{ className: 'gm-code-footer-help' },
					el( 'span', null,
						__( 'Lifecycle parameters: ', 'ajax-filter-posts' ),
						el( 'span', { className: 'gm-code-tag-badge' }, 'gridEl' ),
						', ',
						el( 'span', { className: 'gm-code-tag-badge' }, 'gridId' ),
						__( '. Runs on DOM ready and gridmaster_loaded_posts events.', 'ajax-filter-posts' )
					),
					el( 'span', null, __( 'Protected in isolated IIFE wrapper', 'ajax-filter-posts' ) )
				)
			)
		);
	}

	/**
	 * Top Contextual Tools (Undo/Redo/ListView/FocusMode/Inspector)
	 */
	function ContextualEditorTools( props ) {
		const {
			activeTab,
			isListViewOpen,
			setIsListViewOpen,
			isInspectorOpen,
			setIsInspectorOpen,
			isFocusMode,
			setIsFocusMode,
			history,
		} = props;

		return el(
			'div',
			{ className: 'gm-contextual-tools' },

			// Undo / Redo (Card tab only)
			activeTab === 'card' &&
				el(
					'div',
					{ className: 'gm-history-group', role: 'group', 'aria-label': __( 'Edit history', 'ajax-filter-posts' ) },
					el( Button, {
						icon: 'undo',
						isSmall: true,
						disabled: ! history.hasUndo,
						onClick: history.undo,
						label: __( 'Undo', 'ajax-filter-posts' ),
					} ),
					el( Button, {
						icon: 'redo',
						isSmall: true,
						disabled: ! history.hasRedo,
						onClick: history.redo,
						label: __( 'Redo', 'ajax-filter-posts' ),
					} )
				),

			// List View Toggle (Card tab only)
			activeTab === 'card' &&
				el( Button, {
					icon: 'list-view',
					isSmall: true,
					className: 'gm-tool-btn' + ( isListViewOpen ? ' is-active' : '' ),
					onClick: () => setIsListViewOpen( ! isListViewOpen ),
					label: __( 'Toggle List View', 'ajax-filter-posts' ),
					'aria-pressed': isListViewOpen,
				} ),

			// Focus Mode Toggle (Always visible)
			el( Button, {
				icon: isFocusMode ? 'fullscreen-exit-alt' : 'fullscreen-alt',
				isSmall: true,
				className: 'gm-tool-btn' + ( isFocusMode ? ' is-active' : '' ),
				onClick: () => setIsFocusMode( ! isFocusMode ),
				label: isFocusMode ? __( 'Exit Focus Mode', 'ajax-filter-posts' ) : __( 'Enter Focus Mode', 'ajax-filter-posts' ),
				'aria-pressed': isFocusMode,
			} ),

			// Inspector Toggle (Card tab only)
			activeTab === 'card' &&
				el( Button, {
					icon: 'admin-generic',
					isSmall: true,
					className: 'gm-tool-btn' + ( isInspectorOpen ? ' is-active' : '' ),
					onClick: () => setIsInspectorOpen( ! isInspectorOpen ),
					label: __( 'Toggle Settings Panel', 'ajax-filter-posts' ),
					'aria-pressed': isInspectorOpen,
				} )
		);
	}

	/**
	 * Starter Grid Blueprints Modal (full Query + Facets + Layout + Card presets).
	 */
	function StarterBlueprintsModal( props ) {
		const { onClose, onApply, isPro } = props;

		const blueprints = [
			{
				id: 'saas-blog',
				title: __( 'SaaS Blog & Resource Hub', 'ajax-filter-posts' ),
				desc: __( 'Category pill tabs, keyword search, sort dropdown, 3-column card grid, and numbered pagination.', 'ajax-filter-posts' ),
				icon: 'welcome-write-blog',
				requiresPro: false,
				tags: [ 'Grid (3 Col)', 'Pills + Search', 'Numbered' ],
				cardAttrs: { layoutMode: 'vertical', padding: '20px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', boxShadow: '0 6px 18px rgba(15,23,42,0.05)', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				config: {
					query: { post_type: 'post', posts_per_page: 9, orderby: 'date', order: 'DESC' },
					facets: [
						{ id: 'bp_cat', type: 'taxonomy-tabs', taxonomy: 'category', label: __( 'Category', 'ajax-filter-posts' ), style: 'pills', show_all: true, show_count: true },
						{ id: 'bp_search', type: 'search', label: '', placeholder: __( 'Search articles…', 'ajax-filter-posts' ) },
						{ id: 'bp_sort', type: 'sort', label: __( 'Sort', 'ajax-filter-posts' ) },
					],
					layout: { type: 'grid', filter_position: 'top', columns: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 }, col_gap: 24, row_gap: 24 },
					pagination: { type: 'numbered', load_more_text: __( 'Load More', 'ajax-filter-posts' ) },
				},
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '16/9', borderRadius: '8px', zoomOnHover: true } ),
					createBlock( 'core/group', { layout: { type: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
						createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
						createBlock( 'gridmaster/reading-time', {} ),
					] ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '19px', isLink: true } ),
					createBlock( 'gridmaster/excerpt', { length: 18 } ),
					createBlock( 'gridmaster/read-more', { text: __( 'Read Article', 'ajax-filter-posts' ) } ),
				],
			},
			{
				id: 'editorial-metro',
				title: __( 'Editorial Metro Magazine', 'ajax-filter-posts' ),
				desc: __( 'Asymmetric Metro mosaic featuring a double-span lead story, category tabs, and Load More pagination.', 'ajax-filter-posts' ),
				icon: 'grid-view',
				requiresPro: false,
				tags: [ 'Metro Mosaic', 'Top Tabs', 'Load More' ],
				cardAttrs: { layoutMode: 'vertical', padding: '18px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				config: {
					query: { post_type: 'post', posts_per_page: 7, orderby: 'date', order: 'DESC' },
					facets: [
						{ id: 'bp_metro_cat', type: 'taxonomy-tabs', taxonomy: 'category', label: __( 'Topics', 'ajax-filter-posts' ), style: 'tabs', show_all: true },
						{ id: 'bp_metro_search', type: 'search', placeholder: __( 'Filter stories…', 'ajax-filter-posts' ) },
					],
					layout: { type: 'metro', filter_position: 'top', columns: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 }, col_gap: 20, row_gap: 20 },
					pagination: { type: 'load_more', load_more_text: __( 'Load More Stories', 'ajax-filter-posts' ) },
				},
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'large', aspectRatio: '16/9', borderRadius: '6px', zoomOnHover: true } ),
					createBlock( 'gridmaster/badge', { text: __( 'Featured', 'ajax-filter-posts' ), backgroundColor: '#4f46e5', textColor: '#ffffff' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '20px', isLink: true } ),
					createBlock( 'gridmaster/post-date', { format: 'M j, Y' } ),
					createBlock( 'gridmaster/excerpt', { length: 20 } ),
				],
			},
			{
				id: 'masonry-portfolio',
				title: __( 'Masonry Showcase + Filter Drawer', 'ajax-filter-posts' ),
				desc: __( 'True dynamic masonry heights paired with an off-canvas filter drawer for categories and tags.', 'ajax-filter-posts' ),
				icon: 'align-left',
				requiresPro: false,
				tags: [ 'Masonry', 'Off-Canvas Drawer', 'Checkboxes' ],
				cardAttrs: { layoutMode: 'vertical', padding: '14px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				config: {
					query: { post_type: 'post', posts_per_page: 9, orderby: 'date', order: 'DESC' },
					facets: [
						{ id: 'bp_m_search', type: 'search', placeholder: __( 'Search showcase…', 'ajax-filter-posts' ) },
						{ id: 'bp_m_cat', type: 'taxonomy-checkbox', taxonomy: 'category', label: __( 'Categories', 'ajax-filter-posts' ), show_count: true },
						{ id: 'bp_m_tag', type: 'taxonomy-checkbox', taxonomy: 'post_tag', label: __( 'Tags', 'ajax-filter-posts' ), show_count: true },
					],
					layout: { type: 'masonry', filter_position: 'drawer', drawer_button_label: __( 'Filter Showcase', 'ajax-filter-posts' ), columns: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 }, col_gap: 18, row_gap: 18 },
					pagination: { type: 'numbered' },
				},
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: 'auto', borderRadius: '6px' } ),
					createBlock( 'gridmaster/post-terms', { taxonomy: 'category' } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '17px', isLink: true } ),
					createBlock( 'gridmaster/read-more', { text: __( 'View Details', 'ajax-filter-posts' ) } ),
				],
			},
			{
				id: 'woo-storefront',
				title: __( 'WooCommerce Storefront + Sticky Sidebar', 'ajax-filter-posts' ),
				desc: __( 'Shop catalog grid with sticky left sidebar facets (Price, Rating, Stock Status) and Woo product cards.', 'ajax-filter-posts' ),
				icon: 'cart',
				requiresPro: true,
				tags: [ 'Pro Storefront', 'Left Sidebar', 'Woo Facets' ],
				cardAttrs: { layoutMode: 'vertical', padding: '16px', borderRadius: '10px', borderWidth: '1px', borderColor: '#e2e8f0', backgroundColor: '#ffffff', hoverEffect: 'lift' },
				config: {
					query: { post_type: 'product', posts_per_page: 9, orderby: 'date', order: 'DESC' },
					facets: [
						{ id: 'bp_woo_search', type: 'search', placeholder: __( 'Search products…', 'ajax-filter-posts' ) },
						{ id: 'bp_woo_cat', type: 'taxonomy-checkbox', taxonomy: 'product_cat', label: __( 'Categories', 'ajax-filter-posts' ), show_count: true },
						{ id: 'bp_woo_price', type: 'price-range', label: __( 'Price Range', 'ajax-filter-posts' ) },
						{ id: 'bp_woo_status', type: 'woo-status', label: __( 'Availability', 'ajax-filter-posts' ) },
						{ id: 'bp_woo_rating', type: 'woo-rating', label: __( 'Minimum Rating', 'ajax-filter-posts' ) },
					],
					layout: { type: 'grid', filter_position: 'left_sidebar', sidebar_width: 280, sticky_sidebar: true, columns: { xs: 1, sm: 2, md: 3, lg: 3, xl: 3 }, col_gap: 20, row_gap: 20 },
					pagination: { type: 'numbered' },
				},
				getBlocks: () => [
					createBlock( 'gridmaster/featured-image', { size: 'medium_large', aspectRatio: '1/1', borderRadius: '8px', zoomOnHover: true } ),
					createBlock( 'gridmaster/title', { tagName: 'h3', fontSize: '16px', isLink: true } ),
					createBlock( 'gridmaster-pro/woo-price', {} ),
					createBlock( 'gridmaster-pro/woo-rating', {} ),
					createBlock( 'gridmaster-pro/woo-add-to-cart', {} ),
				],
			},
		];

		return el(
			Modal,
			{
				title: __( 'Starter Grid Blueprints', 'ajax-filter-posts' ),
				onRequestClose: onClose,
				className: 'gm-blueprints-modal',
			},
			el(
				'p',
				{ className: 'gm-blueprints-intro' },
				__( 'Apply a complete starting blueprint (Query, Filters, Layout & Card design). You can customize every setting afterwards.', 'ajax-filter-posts' )
			),
			el(
				'div',
				{ className: 'gm-blueprints-grid' },
				blueprints.map( ( bp ) => {
					const locked = bp.requiresPro && ! isPro;
					return el(
						'div',
						{
							key: bp.id,
							className: 'gm-blueprint-card' + ( locked ? ' is-locked' : '' ),
						},
						el(
							'div',
							{ className: 'gm-blueprint-card-head' },
							el( 'span', { className: 'dashicons dashicons-' + bp.icon } ),
							el(
								'div',
								null,
								el(
									'h4',
									null,
									bp.title,
									bp.requiresPro && el( 'span', { className: 'gm-pro-badge', style: { marginLeft: '6px' } }, 'PRO' )
								),
								el( 'p', null, bp.desc )
							)
						),
						el(
							'div',
							{ className: 'gm-blueprint-tags' },
							bp.tags.map( ( tag ) => el( 'span', { key: tag, className: 'gm-blueprint-tag' }, tag ) )
						),
						el(
							'div',
							{ className: 'gm-blueprint-actions' },
							el(
								Button,
								{
									isPrimary: ! locked,
									isSecondary: locked,
									disabled: locked,
									onClick: () => onApply( bp ),
								},
								locked ? __( 'Requires Pro', 'ajax-filter-posts' ) : __( 'Use Blueprint', 'ajax-filter-posts' )
							)
						)
					);
				} )
			)
		);
	}

	/**
	 * Main Builder Root Application.
	 */
	function BuilderApp() {
		const [ gridId, setGridId ] = useState( initData.id || 0 );
		const [ title, setTitle ] = useState( initData.title || __( 'Untitled Grid', 'ajax-filter-posts' ) );
		const [ editingTitleVal, setEditingTitleVal ] = useState( initData.title || __( 'Untitled Grid', 'ajax-filter-posts' ) );
		const [ isRenaming, setIsRenaming ] = useState( false );
		const [ config, setConfig ] = useState( initData.config || {} );
		const initialBlocks = useRef( null );
		if ( ! initialBlocks.current ) initialBlocks.current = parse( initData.block_content || '' );
		const history = wp.compose.useStateWithHistory( initialBlocks.current );
		const blocks = history.value;
		const [ activeTab, setActiveTab ] = useState( 'card' ); // 'card', 'content', 'filters', 'pagination', 'layout'

		// Panel toggles
		const [ isListViewOpen, setIsListViewOpen ] = useState( false );
		const [ isInspectorOpen, setIsInspectorOpen ] = useState( true );
		const isNarrow = wp.compose.useMediaQuery( '(max-width: 1100px)' );
		useEffect( () => {
			if ( isNarrow ) {
				setIsInspectorOpen( false );
				setIsListViewOpen( false );
			}
		}, [ isNarrow ] );
		const [ isFocusMode, setIsFocusMode ] = useState( () => {
			try { return localStorage.getItem( 'gm_builder_focus_mode' ) === 'true'; } catch ( e ) { return false; }
		} );

		// Shortcode copied status
		const [ isCopied, setIsCopied ] = useState( false );

		// Save state
		const [ isSaving, setIsSaving ] = useState( false );
		const [ saveError, setSaveError ] = useState( null );
		const [ lastSavedAt, setLastSavedAt ] = useState( null );

		// Toast notification state
		const [ toast, setToast ] = useState( null );
		const toastTimerRef = useRef( null );

		const showToast = useCallback( ( message, type = 'success', icon = 'yes-alt' ) => {
			if ( toastTimerRef.current ) clearTimeout( toastTimerRef.current );
			setToast( { message, type, icon } );
			toastTimerRef.current = setTimeout( () => {
				setToast( null );
			}, 2800 );
		}, [] );

		// Preview & Blueprints Modal state
		const [ isPreviewOpen, setIsPreviewOpen ] = useState( false );
		const [ isBlueprintsOpen, setIsBlueprintsOpen ] = useState( false );
		const previewBtnRef = useRef( null );

		// Snapshot for dirty tracking (initialized with parsed and serialized blocks for exact whitespace matching)
		const [ savedSnapshot, setSavedSnapshot ] = useState( () =>
			JSON.stringify( {
				title: initData.title || __( 'Untitled Grid', 'ajax-filter-posts' ),
				config: initData.config || {},
				blocks: serialize( initialBlocks.current ),
			} )
		);

		const currentSnapshot = JSON.stringify( {
			title: title,
			config: config,
			blocks: serialize( blocks ),
		} );

		const isDirty = currentSnapshot !== savedSnapshot;

		// Focus mode class on document body
		useEffect( () => {
			if ( isFocusMode ) {
				document.body.classList.add( 'gm-focus-mode' );
			} else {
				document.body.classList.remove( 'gm-focus-mode' );
			}
			try { localStorage.setItem( 'gm_builder_focus_mode', isFocusMode ? 'true' : 'false' ); } catch ( e ) { /* Optional preference storage. */ }
		}, [ isFocusMode ] );

		// Account for WordPress notices above the shell instead of pushing panels below the viewport.
		useEffect( () => {
			const root = document.getElementById( 'gridmaster-builder-root' );
			let frame;
			const update = () => {
				cancelAnimationFrame( frame );
				frame = requestAnimationFrame( () => {
					root.style.setProperty( '--gm-workspace-top', Math.max( 0, root.getBoundingClientRect().top ) + 'px' );
				} );
			};
			update();
			window.addEventListener( 'resize', update );
			window.addEventListener( 'scroll', update, { passive: true } );
			const observer = new ResizeObserver( update );
			observer.observe( root.parentElement );
			return () => {
				cancelAnimationFrame( frame ); observer.disconnect();
				window.removeEventListener( 'resize', update ); window.removeEventListener( 'scroll', update );
			};
		}, [ isFocusMode ] );

		// Beforeunload session protection
		useEffect( () => {
			const handleBeforeUnload = ( e ) => {
				if ( isDirty ) {
					e.preventDefault();
					e.returnValue = '';
				}
			};
			window.addEventListener( 'beforeunload', handleBeforeUnload );
			return () => window.removeEventListener( 'beforeunload', handleBeforeUnload );
		}, [ isDirty ] );

		// Save Grid Handler
		const handleSave = () => {
			setIsSaving( true );
			setSaveError( null );

			const blockMarkup = serialize( blocks );
			const payload = {
				id: gridId,
				title: title,
				config: config,
				block_content: blockMarkup,
				migration_status: initData.status === 'legacy' ? 'converted' : 'new_builder',
			};

			const url = gridId ? initData.restUrl + 'grids/' + gridId : initData.restUrl + 'grids';

			fetch( url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': initData.nonce,
				},
				body: JSON.stringify( payload ),
			} )
				.then( readApiResponse )
				.then( ( data ) => {
					setIsSaving( false );
					if ( data && data.id ) {
						setGridId( data.id );
						setLastSavedAt( new Date() );
						showToast( __( 'Grid saved successfully!', 'ajax-filter-posts' ), 'success', 'saved' );
						setSavedSnapshot( JSON.stringify( {
							title: title,
							config: config,
							blocks: blockMarkup,
						} ) );
						if ( ! gridId ) {
							const newUrl = window.location.pathname + '?page=gridmaster-builder&id=' + data.id;
							window.history.replaceState( null, '', newUrl );
						}
					} else {
						setSaveError( data.message || __( 'Save failed. Please try again.', 'ajax-filter-posts' ) );
					}
				} )
				.catch( ( err ) => {
					setIsSaving( false );
					setSaveError( err.message || __( 'Network error. Could not save grid.', 'ajax-filter-posts' ) );
				} );
		};

		// Copy Shortcode
		const handleCopyShortcode = () => {
			if ( ! gridId ) return;
			const code = `[gridmaster id="${gridId}"]`;
			safeCopy( code ).then( () => {
				setIsCopied( true );
				showToast( sprintf( __( 'Copied shortcode to clipboard: %s', 'ajax-filter-posts' ), code ), 'info', 'clipboard' );
				setTimeout( () => setIsCopied( false ), 2000 );
			} ).catch( ( e ) => {
				console.error( 'Copy error:', e );
			} );
		};

		// Title rename handlers
		const startRenaming = () => {
			setEditingTitleVal( title );
			setIsRenaming( true );
		};

		const confirmRenaming = () => {
			if ( editingTitleVal.trim() ) {
				setTitle( editingTitleVal.trim() );
			}
			setIsRenaming( false );
		};

		const cancelRenaming = () => {
			setEditingTitleVal( title );
			setIsRenaming( false );
		};

		const updateConfigSection = ( section, key, val ) => {
			setConfig( ( prev ) => {
				const next = { ...prev };
				next[ section ] = { ...next[ section ], [ key ]: val };
				return next;
			} );
		};

		const applyStarterBlueprint = ( blueprint ) => {
			setConfig( ( prev ) => ( {
				...prev,
				query: { ...( prev.query || {} ), ...( blueprint.config.query || {} ) },
				facets: blueprint.config.facets || [],
				pagination: { ...( prev.pagination || {} ), ...( blueprint.config.pagination || {} ) },
				layout: { ...( prev.layout || {} ), ...( blueprint.config.layout || {} ) },
			} ) );
			if ( ! title || title === __( 'Untitled Grid', 'ajax-filter-posts' ) ) {
				setTitle( blueprint.title );
				setEditingTitleVal( blueprint.title );
			}
			const baseCardAttrs = ( blocks && blocks[ 0 ] && blocks[ 0 ].name === 'gridmaster/card' && blocks[ 0 ].attributes ) ? blocks[ 0 ].attributes : {};
			const cardBlock = createBlock( 'gridmaster/card', { ...baseCardAttrs, ...( blueprint.cardAttrs || {} ) }, blueprint.getBlocks() );
			history.setValue( [ cardBlock ], false );
			setIsBlueprintsOpen( false );
			showToast( sprintf( __( 'Applied "%s" blueprint!', 'ajax-filter-posts' ), blueprint.title ) );
		};

		const metadata = initData.metadata || { post_types: [], taxonomies: [] };

		// Compute save status readout
		let saveStatusText = __( 'All changes saved', 'ajax-filter-posts' );
		let saveStatusIcon = 'yes';
		let saveStatusClass = 'is-saved';

		if ( isSaving ) {
			saveStatusText = __( 'Saving…', 'ajax-filter-posts' );
			saveStatusIcon = 'update';
			saveStatusClass = 'is-saving';
		} else if ( saveError ) {
			saveStatusText = __( 'Save failed', 'ajax-filter-posts' );
			saveStatusIcon = 'warning';
			saveStatusClass = 'is-error';
		} else if ( ! gridId ) {
			saveStatusText = __( 'Not saved yet', 'ajax-filter-posts' );
			saveStatusIcon = 'edit';
			saveStatusClass = 'is-new';
		} else if ( isDirty ) {
			saveStatusText = __( 'Unsaved changes', 'ajax-filter-posts' );
			saveStatusIcon = 'marker';
			saveStatusClass = 'is-dirty';
		}

		return el(
			BlockEditorProvider,
			{
				value: blocks,
				settings: { templateLock: blocks.length === 1 && blocks[ 0 ].name === 'gridmaster/card' ? 'all' : false },
				onInput: ( newBlocks ) => history.setValue( newBlocks, true ),
				onChange: ( newBlocks ) => history.setValue( newBlocks, false ),
			},
			el(
				'div',
				{
					className: 'gm-builder-workspace',
					onKeyDown: ( event ) => {
						if ( ( event.metaKey || event.ctrlKey ) && event.key.toLowerCase() === 'z' &&
							! event.altKey && ! /^(INPUT|TEXTAREA|SELECT)$/.test( event.target.tagName ) ) {
							event.preventDefault();
							if ( event.shiftKey ) history.redo(); else history.undo();
						}
						if ( event.key === 'Escape' ) {
							setIsInspectorOpen( false );
							setIsListViewOpen( false );
						}
					},
				},

				// ==========================================
				// ROW 1: Identity & Primary Actions
				// ==========================================
				el(
					'header',
					{ className: 'gm-header-row-primary' },

					// Left Identity & Title
					el(
						'div',
						{ className: 'gm-identity-cluster' },
						el(
							'a',
							{
								href: initData.adminUrl,
								className: 'gm-back-btn',
								title: __( 'Back to All Grids', 'ajax-filter-posts' ),
							},
							el( 'span', { className: 'dashicons dashicons-arrow-left-alt2' } ),
							el( 'span', { className: 'gm-back-label' }, __( 'All Grids', 'ajax-filter-posts' ) )
						),

						el( 'div', { className: 'gm-header-divider' } ),

						// Accessible Title / Rename
						el(
							'div',
							{ className: 'gm-title-container' },
							isRenaming
								? el(
										'div',
										{ className: 'gm-title-rename-box' },
										el( TextControl, {
											className: 'gm-title-input',
											value: editingTitleVal,
											onChange: setEditingTitleVal,
											onKeyDown: ( e ) => {
												if ( e.key === 'Enter' ) confirmRenaming();
												if ( e.key === 'Escape' ) cancelRenaming();
											},
											autoFocus: true,
											'aria-label': __( 'Grid title', 'ajax-filter-posts' ),
										} ),
										el( Button, { isPrimary: true, isSmall: true, onClick: confirmRenaming }, __( 'OK', 'ajax-filter-posts' ) ),
										el( Button, { isSecondary: true, isSmall: true, onClick: cancelRenaming }, __( 'Cancel', 'ajax-filter-posts' ) )
								  )
								: el(
										'div',
										{ className: 'gm-title-display-cluster' },
										el( 'h1', { className: 'gm-grid-title-text', title: title }, title ),
										el( Button, {
											icon: 'edit',
											isSmall: true,
											className: 'gm-rename-btn',
											onClick: startRenaming,
											label: __( 'Rename grid', 'ajax-filter-posts' ),
										} )
								  )
						),

						// Save Status Readout
						el(
							'div',
							{ className: 'gm-save-status-readout ' + saveStatusClass, role: 'status', 'aria-live': 'polite' },
							isSaving
								? el( Spinner )
								: el( 'span', { className: 'dashicons dashicons-' + saveStatusIcon } ),
							el( 'span', { className: 'gm-status-label' }, saveStatusText ),
							saveError &&
								el(
									Button,
									{
										isLink: true,
										className: 'gm-retry-btn',
										onClick: handleSave,
									},
									__( 'Retry', 'ajax-filter-posts' )
								)
						)
					),

					// Right Primary Actions
					el(
						'div',
						{ className: 'gm-actions-cluster' },

						// Copy Shortcode action
						gridId
							? el(
									'div',
									{ className: 'gm-shortcode-wrap' },
									el(
										Button,
										{
											isSecondary: true,
											isSmall: true,
											className: 'gm-copy-shortcode-btn' + ( isCopied ? ' is-copied' : '' ),
											onClick: handleCopyShortcode,
											title: `[gridmaster id="${gridId}"]`,
										},
										el( 'span', { className: 'dashicons ' + ( isCopied ? 'dashicons-yes' : 'dashicons-clipboard' ) } ),
										' ' + ( isCopied ? __( 'Copied!', 'ajax-filter-posts' ) : __( 'Copy shortcode', 'ajax-filter-posts' ) )
									)
							  )
							: null,

						// Starter Blueprints button
						el(
							Button,
							{
								isSecondary: true,
								onClick: () => setIsBlueprintsOpen( true ),
								className: 'gm-blueprints-trigger-btn',
							},
							el( 'span', { className: 'dashicons dashicons-layout' } ),
							' ' + __( 'Blueprints', 'ajax-filter-posts' )
						),

						// Preview button
						el(
							Button,
							{
								ref: previewBtnRef,
								isSecondary: true,
								onClick: () => setIsPreviewOpen( true ),
								className: 'gm-preview-trigger-btn',
							},
							el( 'span', { className: 'dashicons dashicons-visibility' } ),
							' ' + __( 'Preview', 'ajax-filter-posts' )
						),

						// Primary Save button
						el(
							Button,
							{
								isPrimary: true,
								onClick: handleSave,
								disabled: isSaving || ( !! gridId && ! isDirty && ! saveError ),
								className: 'gm-primary-save-btn',
							},
							isSaving && el( Spinner ),
							' ' + ( isSaving ? __( 'Saving…', 'ajax-filter-posts' ) : ( gridId ? ( isDirty ? __( 'Save changes', 'ajax-filter-posts' ) : __( 'Saved', 'ajax-filter-posts' ) ) : __( 'Save grid', 'ajax-filter-posts' ) ) )
						)
					)
				),

				// ==========================================
				// ROW 2: Navigation Tabs & Contextual Tools
				// ==========================================
				el(
					'div',
					{ className: 'gm-header-row-secondary' },

					// Left: Navigation Tabs
					el(
						'nav',
						{ className: 'gm-workspace-tabs', role: 'tablist' },
						[
							{ id: 'card', label: __( 'Card', 'ajax-filter-posts' ), icon: 'format-aside' },
							{ id: 'content', label: __( 'Content', 'ajax-filter-posts' ), icon: 'database' },
							{ id: 'filters', label: __( 'Filters', 'ajax-filter-posts' ), icon: 'filter' },
							{ id: 'pagination', label: __( 'Pagination', 'ajax-filter-posts' ), icon: 'ellipsis' },
							{ id: 'layout', label: __( 'Layout', 'ajax-filter-posts' ), icon: 'layout' },
							{ id: 'code', label: __( 'Custom Code', 'ajax-filter-posts' ), icon: 'editor-code' },
						].map( ( tab ) =>
							el(
								'button',
								{
									key: tab.id,
									type: 'button',
									role: 'tab',
									id: 'gm-tab-' + tab.id,
									'aria-controls': 'gm-builder-panel',
									tabIndex: activeTab === tab.id ? 0 : -1,
									'aria-selected': activeTab === tab.id,
									className: 'gm-tab-nav-btn' + ( activeTab === tab.id ? ' is-active' : '' ),
									onClick: () => setActiveTab( tab.id ),
									onKeyDown: ( event ) => {
										const tabs = Array.from( event.currentTarget.parentNode.querySelectorAll( '[role="tab"]' ) );
										let index = tabs.indexOf( event.currentTarget );
										if ( event.key === 'ArrowRight' ) index = ( index + 1 ) % tabs.length;
										else if ( event.key === 'ArrowLeft' ) index = ( index + tabs.length - 1 ) % tabs.length;
										else if ( event.key === 'Home' ) index = 0;
										else if ( event.key === 'End' ) index = tabs.length - 1;
										else return;
										event.preventDefault();
										tabs[ index ].click();
										tabs[ index ].focus();
									},
								},
								el( 'span', { className: 'dashicons dashicons-' + tab.icon } ),
								el( 'span', { className: 'gm-tab-label' }, tab.label )
							)
						)
					),

					// Right: Contextual Editor Tools
					el( ContextualEditorTools, {
						activeTab: activeTab,
						isListViewOpen: isListViewOpen,
						setIsListViewOpen: ( open ) => {
							setIsListViewOpen( open );
							if ( isNarrow && open ) setIsInspectorOpen( false );
						},
						isInspectorOpen: isInspectorOpen,
						setIsInspectorOpen: ( open ) => {
							setIsInspectorOpen( open );
							if ( isNarrow && open ) setIsListViewOpen( false );
						},
						isFocusMode: isFocusMode,
						setIsFocusMode: setIsFocusMode,
						history: history,
					} )
				),

				// Persistent Save Error Notice
				saveError &&
					el(
						'div',
						{ className: 'gm-save-error-notice' },
						el( Notice, {
							status: 'error',
							onRemove: () => setSaveError( null ),
							actions: [
								{
									label: __( 'Retry Save', 'ajax-filter-posts' ),
									onClick: handleSave,
								},
							],
						}, saveError )
					),

				// ==========================================
				// WORKSPACE BOUNDED BODY
				// ==========================================
				el(
					'div',
					{ className: 'gm-workspace-bounded-body', id: 'gm-builder-panel', role: 'tabpanel', 'aria-labelledby': 'gm-tab-' + activeTab },

					// Tab 1: Card Studio (Display toggled to preserve Gutenberg history & state)
					el(
						'div',
						{
							className: 'gm-card-studio-container',
							style: { display: activeTab === 'card' ? 'flex' : 'none' },
						},
						isNarrow && ( isListViewOpen || isInspectorOpen ) && el( 'button', {
							type: 'button', className: 'gm-panel-backdrop',
							'aria-label': __( 'Close editor panel', 'ajax-filter-posts' ),
							onClick: () => { setIsListViewOpen( false ); setIsInspectorOpen( false ); },
						} ),

						// Left Drawer / Panel: List View
						isListViewOpen &&
							el( CardListView, {
								onClose: () => setIsListViewOpen( false ),
							} ),

						// Center Canvas Stage
						el( CardCanvasStage, {
							isPro: initData.isPro,
							customCss: config.custom_code?.css || config.custom_css || '',
							showToast: showToast,
							config: config,
						} ),

						// Right Drawer / Panel: Contextual Inspector
						isInspectorOpen &&
							el( ContextualInspector, {
								onClose: () => setIsInspectorOpen( false ),
							} )
					),

					// Tab 2: Content (Source, Include/Exclude, Ordering)
					activeTab === 'content' &&
						el( ContentTab, {
							config: config,
							updateConfigSection: updateConfigSection,
							metadata: metadata,
							isPro: initData.isPro,
						} ),

					// Tab 3: Filters (Facets)
					activeTab === 'filters' &&
						el( FiltersTab, {
							config: config,
							setConfig: setConfig,
							metadata: metadata,
							isPro: initData.isPro,
						} ),

					// Tab 4: Pagination (Radio Cards)
					activeTab === 'pagination' &&
						el( PaginationTab, {
							config: config,
							updateConfigSection: updateConfigSection,
						} ),

					// Tab 5: Layout (Presets, Responsive Columns, Gaps)
					activeTab === 'layout' &&
						el( LayoutTab, {
							config: config,
							setConfig: setConfig,
							updateConfigSection: updateConfigSection,
							isPro: initData.isPro,
							showToast: showToast,
						} ),

					// Tab 6: Custom Code (CSS & Scripts)
					activeTab === 'code' &&
						el( CustomCodeTab, {
							config: config,
							setConfig: setConfig,
							updateConfigSection: updateConfigSection,
							canUnfilteredHtml: initData.canUnfilteredHtml !== false,
							gridId: gridId,
							showToast: showToast,
						} )
				),

				// Isolated Iframe Preview Modal
				isPreviewOpen &&
					el( PreviewModal, {
						isOpen: isPreviewOpen,
						onClose: () => {
							setIsPreviewOpen( false );
							if ( previewBtnRef.current ) {
								previewBtnRef.current.focus?.();
							}
						},
						title: title,
						config: config,
						blocks: blocks,
						gridId: gridId,
						metadata: metadata,
					} ),

				// Starter Grid Blueprints Modal
				isBlueprintsOpen &&
					el( StarterBlueprintsModal, {
						onClose: () => setIsBlueprintsOpen( false ),
						onApply: applyStarterBlueprint,
						isPro: initData.isPro,
					} ),

				// Fixed Toast Notification Container (Bottom-Right, Zero Layout Shift)
				toast &&
					el(
						'div',
						{ className: 'gm-toast-container' },
						el(
							'div',
							{
								className: 'gm-toast-notification gm-toast-' + ( toast.type || 'success' ),
								role: 'status',
								'aria-live': 'polite',
							},
							el( 'span', { className: 'dashicons dashicons-' + ( toast.icon || 'yes-alt' ) + ' gm-toast-icon' } ),
							el( 'span', { className: 'gm-toast-message' }, toast.message ),
							el(
								'button',
								{
									type: 'button',
									className: 'gm-toast-close',
									'aria-label': __( 'Dismiss', 'ajax-filter-posts' ),
									onClick: () => setToast( null ),
								},
								el( 'span', { className: 'dashicons dashicons-no-alt' } )
							)
						)
					)
			)
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const root = document.getElementById( 'gridmaster-builder-root' );
		if ( root ) {
			render( el( BuilderApp ), root );
		}
	} );

} )( window.wp );

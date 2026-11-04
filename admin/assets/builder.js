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

		if ( ! isOpen ) return null;

		const blockCategories = [
			{
				title: __( 'Post Elements', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster/post-date', title: __( 'Post Date', 'ajax-filter-posts' ), icon: 'calendar-alt', desc: __( 'Display published or modified date', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/post-author', title: __( 'Post Author', 'ajax-filter-posts' ), icon: 'admin-users', desc: __( 'Author name and optional avatar', 'ajax-filter-posts' ), attrs: {} },
					{ name: 'gridmaster/post-terms', title: __( 'Categories & Tags', 'ajax-filter-posts' ), icon: 'tag', desc: __( 'Display assigned taxonomy terms', 'ajax-filter-posts' ), attrs: { taxonomy: 'category' } },
					{ name: 'gridmaster/comments-count', title: __( 'Comments Count', 'ajax-filter-posts' ), icon: 'admin-comments', desc: __( 'Number of comments on the post', 'ajax-filter-posts' ), attrs: {} },
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
				title: __( 'Pro Elements', 'ajax-filter-posts' ),
				blocks: [
					{ name: 'gridmaster-pro/product-price', title: __( 'Product Price (Pro)', 'ajax-filter-posts' ), icon: 'money-alt', desc: __( 'WooCommerce regular and sale price', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/add-to-cart', title: __( 'Add to Cart (Pro)', 'ajax-filter-posts' ), icon: 'cart', desc: __( 'AJAX add-to-cart button', 'ajax-filter-posts' ), isPro: true, attrs: {} },
					{ name: 'gridmaster-pro/badge', title: __( 'Custom Badge (Pro)', 'ajax-filter-posts' ), icon: 'star-filled', desc: __( 'Dynamic badges like New, Featured, or Sale', 'ajax-filter-posts' ), isPro: true, attrs: {} },
				],
			},
		];

		const lowerSearch = searchTerm.toLowerCase();
		const filteredCategories = blockCategories.map( ( cat ) => {
			const matching = cat.blocks.filter( ( b ) =>
				b.title.toLowerCase().includes( lowerSearch ) ||
				b.desc.toLowerCase().includes( lowerSearch )
			);
			return { ...cat, blocks: matching };
		} ).filter( ( cat ) => cat.blocks.length > 0 );

		return el(
			Popover,
			{
				className: 'gm-more-blocks-popover',
				onClose: onClose,
				position: 'bottom right',
			},
			el(
				'div',
				{ className: 'gm-popover-content' },
				el(
					'div',
					{ className: 'gm-popover-search' },
					el( TextControl, {
						label: __( 'Search blocks', 'ajax-filter-posts' ),
						placeholder: __( 'Search post blocks...', 'ajax-filter-posts' ),
						value: searchTerm,
						onChange: setSearchTerm,
						autoFocus: true,
					} )
				),
				el(
					'div',
					{ className: 'gm-popover-list' },
					filteredCategories.length === 0
						? el( 'p', { className: 'gm-no-blocks' }, __( 'No matching blocks found.', 'ajax-filter-posts' ) )
						: filteredCategories.map( ( cat, idx ) =>
								el(
									'div',
									{ key: idx, className: 'gm-popover-cat-group' },
									el( 'h4', { className: 'gm-popover-cat-title' }, cat.title ),
									cat.blocks.map( ( b ) =>
										el(
											'button',
											{
												key: b.name,
												type: 'button',
												className: 'gm-popover-item-btn' + ( b.isPro ? ' is-pro' : '' ),
												disabled: b.isPro && ! isPro,
												onClick: () => {
													onSelectBlock( b.name, b.attrs );
													onClose();
												},
											},
											el( 'span', { className: 'dashicons dashicons-' + b.icon + ' gm-item-icon' } ),
											el(
												'div',
												{ className: 'gm-item-details' },
												el( 'span', { className: 'gm-item-title' }, b.title ),
												el( 'span', { className: 'gm-item-desc' }, b.desc )
											)
										)
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
			'gridmaster/read-more': { title: __( 'Button (Read More)', 'ajax-filter-posts' ), icon: 'button' },
			'gridmaster/post-date': { title: __( 'Post Date', 'ajax-filter-posts' ), icon: 'calendar-alt' },
			'gridmaster/post-author': { title: __( 'Post Author', 'ajax-filter-posts' ), icon: 'admin-users' },
			'gridmaster/post-terms': { title: __( 'Categories / Terms', 'ajax-filter-posts' ), icon: 'tag' },
			'gridmaster/comments-count': { title: __( 'Comments Count', 'ajax-filter-posts' ), icon: 'admin-comments' },
			'gridmaster-pro/product-price': { title: __( 'Price (Pro)', 'ajax-filter-posts' ), icon: 'money-alt' },
			'gridmaster-pro/add-to-cart': { title: __( 'Add to Cart (Pro)', 'ajax-filter-posts' ), icon: 'cart' },
			'gridmaster-pro/badge': { title: __( 'Badge (Pro)', 'ajax-filter-posts' ), icon: 'star-filled' },
			'core/group': { title: __( 'Group', 'ajax-filter-posts' ), icon: 'layout' },
			'core/columns': { title: __( 'Columns', 'ajax-filter-posts' ), icon: 'columns' },
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
	 * Card Canvas Stage: contains quick-add bar, helper text, and Gutenberg canvas.
	 */
	function CardCanvasStage( props ) {
		const { isPro } = props;
		const { insertBlock } = useDispatch( 'core/block-editor' );
		const registry = wp.data.useRegistry();
		const paperRef = useRef( null );
		const { rootBlocks, selectedClientId } = useSelect( ( select ) => {
			const be = select( 'core/block-editor' );
			return { rootBlocks: be.getBlocks(), selectedClientId: be.getSelectedBlockClientId() };
		} );

		const [ isMoreOpen, setIsMoreOpen ] = useState( false );
		const moreBtnRef = useRef( null );

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
							onClick: () => setIsMoreOpen( ! isMoreOpen ),
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
					{ className: 'gm-card-canvas-paper', ref: paperRef },
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
		useEffect( () => {
			if ( iframeRef.current && previewHtml ) {
				const iframeDoc = iframeRef.current.contentDocument || iframeRef.current.contentWindow.document;
				if ( iframeDoc ) {
					const previewData = previewDraft;

					const docContent = `<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>${escapeHtml( title || 'Preview' )}</title>
	<link rel="stylesheet" href="${initData.frontendCssUrl || ''}">
	<link rel="stylesheet" href="${initData.coreCssUrl || ''}">
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
	<script>
		window.__gm_preview_data = ${scriptJson( previewData )};
		window.gridmasterV2 = ${scriptJson( previewRuntime )};
	</script>
	<script src="${initData.frontendJsUrl || ''}"></script>
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
			const docContent = `<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>${escapeHtml( title || 'GridMaster Preview' )}</title>
	<link rel="stylesheet" href="${initData.frontendCssUrl || ''}">
	<link rel="stylesheet" href="${initData.coreCssUrl || ''}">
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
	<script>
		window.__gm_preview_data = ${scriptJson( previewData )};
		window.gridmasterV2 = ${scriptJson( previewRuntime )};
	</script>
	<script src="${initData.frontendJsUrl || ''}"></script>
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
		const { config, updateConfigSection, metadata } = props;
		const clauses = Array.isArray( config.query?.tax_query ) ? config.query.tax_query : [];
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
					label: __( 'Post Type', 'ajax-filter-posts' ),
					value: config.query?.post_type || 'post',
					options: ( metadata.post_types || [] ).map( ( pt ) => ( { label: pt.label, value: pt.name } ) ),
					onChange: ( val ) => updateConfigSection( 'query', 'post_type', val ),
					help: __( 'Select the WordPress post type to display.', 'ajax-filter-posts' ),
				} ),
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
				clauses.length > 1 && el( 'p', null, __( 'Additional saved taxonomy restrictions are also applied.', 'ajax-filter-posts' ) )
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
				current.push( {
					type: type,
					taxonomy: defaultTax,
					heading: type === 'search' ? '' : __( 'Categories', 'ajax-filter-posts' ),
					show_all: true,
					all_label: __( 'All', 'ajax-filter-posts' ),
				} );
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
			{ id: 'search', label: __( 'Search Bar', 'ajax-filter-posts' ), icon: 'search' },
			{ id: 'reset', label: __( 'Reset Button', 'ajax-filter-posts' ), icon: 'undo' },
			{ id: 'multi-select', label: __( 'Multi-Select (Pro)', 'ajax-filter-posts' ), icon: 'list-view', isPro: true },
			{ id: 'date-range', label: __( 'Date Range (Pro)', 'ajax-filter-posts' ), icon: 'calendar', isPro: true },
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
									facet.type.startsWith( 'taxonomy' ) &&
										el( SelectControl, {
											label: __( 'Taxonomy Source', 'ajax-filter-posts' ),
											value: facet.taxonomy || 'category',
											options: ( metadata.taxonomies || [] ).map( ( t ) => ( { label: t.label, value: t.name } ) ),
											onChange: ( val ) => updateFacet( idx, 'taxonomy', val ),
										} ),
									el( TextControl, {
										label: __( 'Filter Label / Heading', 'ajax-filter-posts' ),
										value: facet.heading || '',
										onChange: ( val ) => updateFacet( idx, 'heading', val ),
									} ),
									facet.type === 'taxonomy-tabs' &&
										el(
											Fragment,
											null,
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
					)
			)
		);
	}

	/**
	 * Layout Tab: Design & Layout presets, responsive columns, and grid gaps.
	 */
	function LayoutTab( props ) {
		const { config, setConfig, updateConfigSection, isPro } = props;
		const { updateBlockAttributes } = useDispatch( 'core/block-editor' );
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
					backgroundColor: '#ffffff',
					padding: '20px',
					borderRadius: '8px',
					borderWidth: '1px',
					borderColor: '#dcdcde',
					boxShadow: '0 2px 4px rgba(0,0,0,0.04)',
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
					backgroundColor: '#ffffff',
					padding: '24px',
					borderRadius: '14px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: '0 10px 25px rgba(0,0,0,0.08)',
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
					backgroundColor: '#ffffff',
					padding: '0px',
					borderRadius: '0px',
					borderWidth: '0px',
					borderColor: 'transparent',
					boxShadow: 'none',
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
					backgroundColor: '#ffffff',
					padding: '14px',
					borderRadius: '6px',
					borderWidth: '1px',
					borderColor: '#e2e8f0',
					boxShadow: 'none',
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
					backgroundColor: '#1e293b',
					padding: '20px',
					borderRadius: '12px',
					borderWidth: '1px',
					borderColor: '#334155',
					boxShadow: '0 4px 12px rgba(0,0,0,0.25)',
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
					backgroundColor: '#ffffff',
					padding: '16px',
					borderRadius: '10px',
					borderWidth: '1px',
					borderColor: '#e5e7eb',
					boxShadow: '0 4px 12px rgba(0,0,0,0.05)',
				},
			},
		];

		const applyPreset = ( preset ) => {
			setActivePresetId( preset.id );
			setConfig( ( prev ) => ( {
				...prev,
				layout: {
					...prev.layout,
					columns: { ...preset.columns },
					col_gap: preset.col_gap,
					row_gap: preset.row_gap,
				},
			} ) );
			if ( rootCardBlock && preset.card ) {
				updateBlockAttributes( rootCardBlock.clientId, { ...preset.card } );
			}
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

		const colLg = config.layout?.columns?.lg ?? 3;
		const colMd = config.layout?.columns?.md ?? 2;
		const colXs = config.layout?.columns?.xs ?? 1;

		// Exact 0px support
		const colGap = typeof config.layout?.col_gap !== 'undefined' ? config.layout.col_gap : 30;
		const rowGap = typeof config.layout?.row_gap !== 'undefined' ? config.layout.row_gap : 30;

		return el(
			'div',
			{ className: 'gm-tab-panel-container' },

			// Presets Card
			el(
				'div',
				{ className: 'gm-settings-card' },
				el( 'h3', { className: 'gm-card-heading' }, el( 'span', { className: 'dashicons dashicons-art' } ), ' ' + __( 'Design & Layout Presets', 'ajax-filter-posts' ) ),
				el( 'p', { className: 'gm-preset-intro' }, __( 'Choose a pre-styled foundation for your grid layout and card aesthetics. You can further customize any setting below.', 'ajax-filter-posts' ) ),
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

		// Preview Modal state
		const [ isPreviewOpen, setIsPreviewOpen ] = useState( false );
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
						el( CardCanvasStage, { isPro: initData.isPro } ),

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
					} )
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

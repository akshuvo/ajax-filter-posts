/**
 * GridMaster Page Builder Live Preview Re-Initialization Script
 */
( function () {
	'use strict';

	function reinitGrids() {
		if ( typeof window.gridmasterInit === 'function' ) {
			window.gridmasterInit();
		}
		if ( typeof window.jQuery === 'function' ) {
			window.jQuery( document ).trigger( 'gridmaster_init_all_sliders' );
		}
	}

	// 1. Elementor Frontend Hook
	if ( typeof window.elementorFrontend !== 'undefined' && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/gridmaster-grid.default',
			function () {
				reinitGrids();
			}
		);
	} else {
		// Elementor might load asynchronously
		window.addEventListener( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/gridmaster-grid.default',
					function () {
						reinitGrids();
					}
				);
			}
		} );
	}

	// 2. Beaver Builder Events
	if ( typeof window.jQuery === 'function' ) {
		window.jQuery( document ).on( 'fl-builder.layout-rendered fl-builder.preview-rendered', function () {
			reinitGrids();
		} );
	}

	// 3. Bricks Builder Global Callback ($scripts property)
	window.gridmasterBricksInit = function () {
		reinitGrids();
	};

	// 4. Fallback MutationObserver for reactive canvas updates (WPBakery / Ajax re-renders)
	var observerTimeout = null;
	var observer = new MutationObserver( function ( mutations ) {
		for ( var i = 0; i < mutations.length; i++ ) {
			var added = mutations[ i ].addedNodes;
			for ( var j = 0; j < added.length; j++ ) {
				var node = added[ j ];
				if ( node.nodeType === 1 ) {
					if ( node.classList && ( node.classList.contains( 'gm-v2-grid' ) || node.classList.contains( 'am_ajax_post_grid_wrap' ) || node.querySelector( '.gm-v2-grid, .am_ajax_post_grid_wrap' ) ) ) {
						if ( observerTimeout ) {
							clearTimeout( observerTimeout );
						}
						observerTimeout = setTimeout( reinitGrids, 100 );
						return;
					}
				}
			}
		}
	} );

	if ( document.body ) {
		observer.observe( document.body, { childList: true, subtree: true } );
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			observer.observe( document.body, { childList: true, subtree: true } );
		} );
	}

	// 5. In builder preview canvas, prevent clicks on card links from redirecting the builder window
	document.addEventListener(
		'click',
		function ( e ) {
			var link = e.target.closest( 'a' );
			if ( ! link ) {
				return;
			}

			// Allow edit badge, create button, and pagination / facet links to work normally
			if ( link.closest( '.gm-builder-edit-badge' ) || link.closest( '.gm-builder-btn' ) || link.classList.contains( 'gm-filter-btn' ) || link.classList.contains( 'gm-pagination-btn' ) || link.classList.contains( 'gm-page-num' ) ) {
				return;
			}

			if ( link.closest( '.gm-builder-preview-wrapper' ) ) {
				e.preventDefault();
				e.stopPropagation();
			}
		},
		true
	);

	// Run initial reinit on load
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', reinitGrids );
	} else {
		reinitGrids();
	}
} )();

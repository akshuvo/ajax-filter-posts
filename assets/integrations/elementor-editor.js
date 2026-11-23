/**
 * GridMaster Elementor Panel Controls Integration Script
 */
( function ( $ ) {
	'use strict';

	$( window ).on( 'elementor:init', function () {
		if ( typeof elementor === 'undefined' || ! elementor.channels || ! elementor.channels.editor ) {
			return;
		}

		elementor.channels.editor.on( 'gridmaster:edit_grid', function ( view ) {
			var gridId = view.container.settings.get( 'grid_id' );
			gridId = parseInt( gridId, 10 );

			if ( ! gridId ) {
				alert( ( window.gridmasterElementorData && window.gridmasterElementorData.i18n && window.gridmasterElementorData.i18n.selectGridFirst ) || 'Please select a grid first.' );
				return;
			}

			var editUrl = '';
			if ( window.gridmasterElementorData && window.gridmasterElementorData.editUrls && window.gridmasterElementorData.editUrls[ gridId ] ) {
				editUrl = window.gridmasterElementorData.editUrls[ gridId ];
			} else if ( window.gridmasterElementorData && window.gridmasterElementorData.builderUrl ) {
				editUrl = window.gridmasterElementorData.builderUrl + '&id=' + gridId;
			}

			if ( editUrl ) {
				window.open( editUrl, '_blank' );

				// On returning to the editor window, refresh the widget canvas to show saved modifications
				var onWindowFocus = function () {
					try {
						if ( view && view.container ) {
							if ( typeof view.container.render === 'function' ) {
								view.container.render();
							} else if ( typeof view.renderRemoteServer === 'function' ) {
								view.renderRemoteServer();
							}
						}
					} catch ( err ) {
						// Ignore refresh errors
					}
				};

				window.addEventListener( 'focus', onWindowFocus, { once: true } );
			}
		} );
	} );
} )( jQuery );

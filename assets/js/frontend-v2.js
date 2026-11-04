/**
 * GridMaster 2.0 — Frontend Interactive AJAX Controller
 *
 * Provides isolated state, facet filtering, URL sync, and pagination
 * across multiple independent grids on the same page.
 */
( function () {
	'use strict';

	// Map of grid ID -> GridInstance controller
	const activeGrids = new Map();

	class GridInstance {
		constructor( element ) {
			this.container = element;
			this.gridId = parseInt( element.getAttribute( 'data-grid-id' ), 10 );
			this.itemsContainer = element.querySelector( '.gm-grid-items' );
			this.filtersWrap = element.querySelector( '.gm-filters-wrap' );
			this.paginationWrap = element.querySelector( '.gm-pagination' );

			this.state = {
				page: 1,
				facets: {},
				search: '',
			};

			this.activeAbortController = null;
			this.isLoading = false;
			this.searchTimeout = null;

			this.initFilters();
			this.initPagination();
			this.initInfiniteScroll();
		}

		initFilters() {
			if ( ! this.filtersWrap ) {
				return;
			}

			// 1. Tab buttons
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '.gm-filter-btn' );
				if ( ! btn ) return;

				const facetEl = btn.closest( '.gm-facet' );
				const taxonomy = facetEl.getAttribute( 'data-taxonomy' );
				const termId = parseInt( btn.getAttribute( 'data-term-id' ), 10 );

				// Update active classes within this tab group
				facetEl.querySelectorAll( '.gm-filter-btn' ).forEach( ( b ) => {
					b.classList.remove( 'gm-active' );
					b.setAttribute( 'aria-selected', 'false' );
				} );
				btn.classList.add( 'gm-active' );
				btn.setAttribute( 'aria-selected', 'true' );

				if ( termId === 0 ) {
					delete this.state.facets[ taxonomy ];
				} else {
					this.state.facets[ taxonomy ] = [ termId ];
				}

				this.state.page = 1;
				this.fetchResults( false );
			} );

			// 2. Dropdown select
			this.filtersWrap.addEventListener( 'change', ( e ) => {
				const select = e.target.closest( '.gm-filter-select' );
				if ( ! select ) return;

				const taxonomy = select.getAttribute( 'data-taxonomy' );
				const termId = parseInt( select.value, 10 );

				if ( termId === 0 ) {
					delete this.state.facets[ taxonomy ];
				} else {
					this.state.facets[ taxonomy ] = [ termId ];
				}

				this.state.page = 1;
				this.fetchResults( false );
			} );

			// 3. Checkboxes
			this.filtersWrap.addEventListener( 'change', ( e ) => {
				const checkbox = e.target.closest( '.gm-filter-checkbox' );
				if ( ! checkbox ) return;

				const taxonomy = checkbox.getAttribute( 'data-taxonomy' );
				const checked = Array.from(
					this.filtersWrap.querySelectorAll( `.gm-filter-checkbox[data-taxonomy="${taxonomy}"]:checked` )
				).map( ( cb ) => parseInt( cb.value, 10 ) );

				if ( checked.length === 0 ) {
					delete this.state.facets[ taxonomy ];
				} else {
					this.state.facets[ taxonomy ] = checked;
				}

				this.state.page = 1;
				this.fetchResults( false );
			} );

			// 4. Radio buttons
			this.filtersWrap.addEventListener( 'change', ( e ) => {
				const radio = e.target.closest( '.gm-filter-radio' );
				if ( ! radio ) return;

				const taxonomy = radio.getAttribute( 'data-taxonomy' );
				const termId = parseInt( radio.value, 10 );

				if ( termId === 0 ) {
					delete this.state.facets[ taxonomy ];
				} else {
					this.state.facets[ taxonomy ] = [ termId ];
				}

				this.state.page = 1;
				this.fetchResults( false );
			} );

			// 5. Keyword search (debounced)
			const searchInput = this.filtersWrap.querySelector( '.gm-filter-search' );
			if ( searchInput ) {
				searchInput.addEventListener( 'input', ( e ) => {
					clearTimeout( this.searchTimeout );
					this.searchTimeout = setTimeout( () => {
						this.state.search = e.target.value.trim();
						this.state.page = 1;
						this.fetchResults( false );
					}, 350 );
				} );
			}

			// 6. Reset Filters button
			const resetBtn = this.filtersWrap.querySelector( '.gm-btn-reset' );
			if ( resetBtn ) {
				resetBtn.addEventListener( 'click', () => {
					this.state.facets = {};
					this.state.search = '';
					this.state.page = 1;

					// Reset form inputs in this grid
					this.filtersWrap.querySelectorAll( '.gm-filter-btn' ).forEach( ( b ) => {
						const isAll = parseInt( b.getAttribute( 'data-term-id' ), 10 ) === 0;
						b.classList.toggle( 'gm-active', isAll );
						b.setAttribute( 'aria-selected', isAll ? 'true' : 'false' );
					} );
					this.filtersWrap.querySelectorAll( '.gm-filter-select' ).forEach( ( s ) => ( s.value = '0' ) );
					this.filtersWrap.querySelectorAll( '.gm-filter-checkbox' ).forEach( ( c ) => ( c.checked = false ) );
					this.filtersWrap.querySelectorAll( '.gm-filter-radio' ).forEach( ( r ) => {
						r.checked = parseInt( r.value, 10 ) === 0;
					} );
					if ( searchInput ) searchInput.value = '';

					this.fetchResults( false );
				} );
			}
		}

		initPagination() {
			this.container.addEventListener( 'click', ( e ) => {
				// Numbered pagination or prev/next
				const pageBtn = e.target.closest( '.gm-page-btn' );
				if ( pageBtn ) {
					const page = parseInt( pageBtn.getAttribute( 'data-page' ), 10 );
					if ( page && page !== this.state.page ) {
						this.state.page = page;
						this.fetchResults( false );
						this.container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					}
					return;
				}

				// Load More button
				const loadMoreBtn = e.target.closest( '.gm-btn-load-more' );
				if ( loadMoreBtn && ! this.isLoading ) {
					const nextPage = parseInt( loadMoreBtn.getAttribute( 'data-page' ), 10 );
					if ( nextPage ) {
						this.state.page = nextPage;
						this.fetchResults( true ); // Append mode
					}
				}
			} );
		}

		initInfiniteScroll() {
			const infiniteEl = this.container.querySelector( '.gm-pagination-infinite' );
			if ( ! infiniteEl || ! ( 'IntersectionObserver' in window ) ) return;

			const observer = new IntersectionObserver( ( entries ) => {
				if ( entries[ 0 ].isIntersecting && ! this.isLoading ) {
					const nextPage = parseInt( infiniteEl.getAttribute( 'data-page' ), 10 );
					const maxPages = parseInt( infiniteEl.getAttribute( 'data-max-pages' ), 10 );
					if ( nextPage && nextPage <= maxPages ) {
						this.state.page = nextPage;
						this.fetchResults( true );
					}
				}
			}, { rootMargin: '200px' } );

			observer.observe( infiniteEl );
			this.infiniteObserver = observer;
		}

		fetchResults( append = false ) {
			if ( this.activeAbortController ) {
				this.activeAbortController.abort();
			}

			this.activeAbortController = new AbortController();
			this.isLoading = true;

			this.setLoadingState( true, append );

			const isPreview = this.container.getAttribute( 'data-preview-mode' ) === '1' || window.__gm_preview_mode;
			const restBase = window.gridmasterV2 ? window.gridmasterV2.restUrl : '/wp-json/gridmaster/v2/';
			
			let url;
			let payload = {
				page: this.state.page,
				facets: this.state.facets,
				search: this.state.search,
			};

			if ( isPreview && window.__gm_preview_data ) {
				url = restBase + 'preview/render';
				payload.config = window.__gm_preview_data.config;
				payload.block_content = window.__gm_preview_data.block_content;
				payload.id = window.__gm_preview_data.id || 0;
			} else {
				const safeId = isNaN( this.gridId ) || ! this.gridId ? 0 : this.gridId;
				url = restBase + 'grids/' + safeId + '/render';
			}

			fetch( url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.gridmasterV2 ? window.gridmasterV2.nonce : '',
				},
				body: JSON.stringify( payload ),
				signal: this.activeAbortController.signal,
			} )
				.then( ( res ) => res.json() )
				.then( ( data ) => {
					this.isLoading = false;
					this.setLoadingState( false, append );

					if ( data && data.html ) {
						const temp = document.createElement( 'div' );
						temp.innerHTML = data.html;

						const newItems = temp.querySelectorAll( '.gm-grid-item' );
						const newPagination = temp.querySelector( '.gm-pagination' );

						if ( append ) {
							newItems.forEach( ( item ) => {
								// Prevent duplicate post cards
								const postId = item.getAttribute( 'data-post-id' );
								if ( ! this.itemsContainer.querySelector( `.gm-grid-item[data-post-id="${postId}"]` ) ) {
									this.itemsContainer.appendChild( item );
								}
							} );
						} else {
							this.itemsContainer.innerHTML = '';
							if ( newItems.length > 0 ) {
								newItems.forEach( ( item ) => this.itemsContainer.appendChild( item ) );
							} else {
								const noRes = temp.querySelector( '.gm-no-results' );
								this.itemsContainer.innerHTML = noRes ? noRes.outerHTML : '<div class="gm-no-results"><p>No posts found.</p></div>';
							}
						}

						// Update pagination container
						const currentPagination = this.container.querySelector( '.gm-pagination' );
						if ( currentPagination ) {
							if ( newPagination ) {
								currentPagination.outerHTML = newPagination.outerHTML;
							} else {
								currentPagination.remove();
							}
						} else if ( newPagination ) {
							this.container.appendChild( newPagination );
						}

						// Re-observe if infinite scroll
						this.initInfiniteScroll();
					}
				} )
				.catch( ( err ) => {
					if ( err.name !== 'AbortError' ) {
						this.isLoading = false;
						this.setLoadingState( false, append );
						console.error( 'GridMaster fetch error:', err );
					}
				} );
		}

		setLoadingState( loading, append ) {
			if ( loading ) {
				this.container.classList.add( 'gm-loading' );
				if ( ! append ) {
					this.itemsContainer.style.opacity = '0.5';
				} else {
					const loadBtn = this.container.querySelector( '.gm-btn-load-more' );
					if ( loadBtn ) {
						loadBtn.classList.add( 'gm-loading-spinner' );
						const loadText = loadBtn.getAttribute( 'data-loading-text' );
						const textSpan = loadBtn.querySelector( '.gm-btn-text' );
						if ( textSpan && loadText ) textSpan.textContent = loadText;
					}
				}
			} else {
				this.container.classList.remove( 'gm-loading' );
				this.itemsContainer.style.opacity = '1';
			}
		}
	}

	// Auto-initialize all grids on page load
	function initAllGrids() {
		document.querySelectorAll( '.gm-v2-grid' ).forEach( ( el ) => {
			if ( ! activeGrids.has( el ) ) {
				activeGrids.set( el, new GridInstance( el ) );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllGrids );
	} else {
		initAllGrids();
	}
} )();

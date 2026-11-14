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
				orderby: '',
				min_price: '',
				max_price: '',
				min_rating: 0,
				on_sale: false,
				in_stock: false,
				featured: false,
				meta: {},
				date_from: '',
				date_to: '',
			};

			this.activeAbortController = null;
			this.isLoading = false;
			this.searchTimeout = null;
			this.priceTimeout = null;
			this.metaTimeout = null;

			this.initFilters();
			this.initFilterDrawer();
			this.initPagination();
			this.initInfiniteScroll();
			this.initSlider();
			this.initVerticalReels();
			this.initMasonry();

			const hadUrlParams = this.hydrateFromUrl();
			if ( hadUrlParams && this.container.getAttribute( 'data-url-hydrated' ) !== '1' && ! this.isPreviewMode() ) {
				this.fetchResults( false );
			}
		}

		isPreviewMode() {
			return this.container.getAttribute( 'data-preview-mode' ) === '1' || !! window.__gm_preview_mode;
		}

		initFilters() {
			if ( ! this.filtersWrap ) {
				return;
			}

			// 1. Tab buttons (Supports single-select and multi-select chips)
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '.gm-filter-btn' );
				if ( ! btn ) return;

				const facetEl = btn.closest( '.gm-facet' );
				const taxonomy = facetEl.getAttribute( 'data-taxonomy' );
				const termId = parseInt( btn.getAttribute( 'data-term-id' ), 10 );
				const isMulti = facetEl.getAttribute( 'data-multi-select' ) === '1';

				if ( isMulti ) {
					if ( termId === 0 ) {
						// Reset group to 'All'
						facetEl.querySelectorAll( '.gm-filter-btn' ).forEach( ( b ) => {
							const isAll = parseInt( b.getAttribute( 'data-term-id' ), 10 ) === 0;
							b.classList.toggle( 'gm-active', isAll );
							b.setAttribute( 'aria-selected', isAll ? 'true' : 'false' );
						} );
						delete this.state.facets[ taxonomy ];
					} else {
						// Deselect the 'All' button
						const allBtn = facetEl.querySelector( '.gm-filter-btn[data-term-id="0"]' );
						if ( allBtn ) {
							allBtn.classList.remove( 'gm-active' );
							allBtn.setAttribute( 'aria-selected', 'false' );
						}

						btn.classList.toggle( 'gm-active' );
						const isActive = btn.classList.contains( 'gm-active' );
						btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );

						const current = Array.isArray( this.state.facets[ taxonomy ] ) ? [ ...this.state.facets[ taxonomy ] ] : [];
						const idx = current.indexOf( termId );
						if ( isActive && idx === -1 ) {
							current.push( termId );
						} else if ( ! isActive && idx !== -1 ) {
							current.splice( idx, 1 );
						}

						if ( current.length === 0 ) {
							delete this.state.facets[ taxonomy ];
							if ( allBtn ) {
								allBtn.classList.add( 'gm-active' );
								allBtn.setAttribute( 'aria-selected', 'true' );
							}
						} else {
							this.state.facets[ taxonomy ] = current;
						}
					}
				} else {
					// Update active classes within single-select tab group
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

					// Sync any sibling dropdown for the same taxonomy
					const siblingSelect = this.filtersWrap.querySelector( `.gm-filter-select[data-taxonomy="${taxonomy}"]` );
					if ( siblingSelect ) {
						siblingSelect.value = String( termId );
					}
				}

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
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

				// Sync any sibling tabs for the same taxonomy
				this.filtersWrap.querySelectorAll( `.gm-facet[data-taxonomy="${taxonomy}"] .gm-filter-btn` ).forEach( ( b ) => {
					const bTermId = parseInt( b.getAttribute( 'data-term-id' ), 10 );
					const isActive = bTermId === termId;
					b.classList.toggle( 'gm-active', isActive );
					b.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				} );

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 3. Checkboxes & Pro Multi-Select Pills (<input> and <button>)
			this.filtersWrap.addEventListener( 'change', ( e ) => {
				const checkbox = e.target.closest( 'input.gm-filter-checkbox, input.gm-filter-checkbox-pill' );
				if ( ! checkbox ) return;

				const taxonomy = checkbox.getAttribute( 'data-taxonomy' );
				const pillLabel = checkbox.closest( '.gm-pill-option, label' );
				if ( pillLabel ) {
					pillLabel.classList.toggle( 'gm-active', checkbox.checked );
				}

				const checked = Array.from(
					this.filtersWrap.querySelectorAll(
						`input.gm-filter-checkbox[data-taxonomy="${taxonomy}"]:checked, input.gm-filter-checkbox-pill[data-taxonomy="${taxonomy}"]:checked`
					)
				).map( ( cb ) => parseInt( cb.value, 10 ) ).filter( Boolean );

				if ( checked.length === 0 ) {
					delete this.state.facets[ taxonomy ];
				} else {
					this.state.facets[ taxonomy ] = checked;
				}

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const pillBtn = e.target.closest( 'button.gm-filter-checkbox-pill' );
				if ( ! pillBtn ) return;

				const taxonomy = pillBtn.getAttribute( 'data-taxonomy' ) || pillBtn.closest( '.gm-facet' )?.getAttribute( 'data-taxonomy' );
				if ( ! taxonomy ) return;

				const isActive = ! pillBtn.classList.contains( 'gm-active' );
				pillBtn.classList.toggle( 'gm-active', isActive );
				pillBtn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );

				const selectedIds = [];
				this.filtersWrap
					.querySelectorAll(
						`button.gm-filter-checkbox-pill[data-taxonomy="${taxonomy}"].gm-active, input.gm-filter-checkbox[data-taxonomy="${taxonomy}"]:checked, input.gm-filter-checkbox-pill[data-taxonomy="${taxonomy}"]:checked`
					)
					.forEach( ( el ) => {
						const id = parseInt( el.getAttribute( 'data-term-id' ) || el.value, 10 );
						if ( id && ! selectedIds.includes( id ) ) {
							selectedIds.push( id );
						}
					} );

				if ( selectedIds.length === 0 ) {
					delete this.state.facets[ taxonomy ];
				} else {
					this.state.facets[ taxonomy ] = selectedIds;
				}

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
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
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 5. Keyword search (debounced) + Clear button
			const searchInput = this.filtersWrap.querySelector( '.gm-filter-search' );
			if ( searchInput ) {
				searchInput.addEventListener( 'input', ( e ) => {
					clearTimeout( this.searchTimeout );
					this.searchTimeout = setTimeout( () => {
						this.state.search = e.target.value.trim();
						this.state.page = 1;
						this.syncUrl();
						this.updateActiveFiltersBar();
						this.fetchResults( false );
					}, 350 );
				} );
			}
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const clearSearch = e.target.closest( '.gm-search-clear' );
				if ( ! clearSearch ) return;
				if ( searchInput ) {
					searchInput.value = '';
				}
				this.state.search = '';
				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 6. WooCommerce Price Range Slider & Number Inputs (.gm-facet-price-range)
			this.filtersWrap.querySelectorAll( '.gm-facet-price-range' ).forEach( ( priceFacet ) => {
				const minSlider = priceFacet.querySelector( '.gm-price-range-min' );
				const maxSlider = priceFacet.querySelector( '.gm-price-range-max' );
				const minInput = priceFacet.querySelector( '.gm-price-input-min' );
				const maxInput = priceFacet.querySelector( '.gm-price-input-max' );
				const fillBar = priceFacet.querySelector( '.gm-price-range-fill' );

				const absMin = parseFloat( priceFacet.getAttribute( 'data-min' ) ?? minSlider?.min ?? 0 );
				const absMax = parseFloat( priceFacet.getAttribute( 'data-max' ) ?? maxSlider?.max ?? 500 );

				const updateFill = ( low, high ) => {
					if ( ! fillBar ) return;
					const span = Math.max( 1, absMax - absMin );
					const leftPct = Math.max( 0, Math.min( 100, ( ( low - absMin ) / span ) * 100 ) );
					const rightPct = Math.max( 0, Math.min( 100, ( ( high - absMin ) / span ) * 100 ) );
					fillBar.style.left = leftPct + '%';
					fillBar.style.width = Math.max( 0, rightPct - leftPct ) + '%';
				};

				const triggerPriceChange = ( low, high ) => {
					this.state.min_price = low <= absMin ? '' : low;
					this.state.max_price = high >= absMax ? '' : high;
					this.state.page = 1;
					this.syncUrl();
					this.updateActiveFiltersBar();
					clearTimeout( this.priceTimeout );
					this.priceTimeout = setTimeout( () => {
						this.fetchResults( false );
					}, 300 );
				};

				const syncFromSliders = () => {
					let low = parseFloat( minSlider ? minSlider.value : absMin );
					let high = parseFloat( maxSlider ? maxSlider.value : absMax );
					if ( low > high ) {
						const tmp = low;
						low = high;
						high = tmp;
					}
					if ( minInput ) minInput.value = low;
					if ( maxInput ) maxInput.value = high;
					updateFill( low, high );
					triggerPriceChange( low, high );
				};

				const syncFromInputs = () => {
					let low = parseFloat( minInput && minInput.value !== '' ? minInput.value : absMin );
					let high = parseFloat( maxInput && maxInput.value !== '' ? maxInput.value : absMax );
					low = Math.max( absMin, Math.min( absMax, isNaN( low ) ? absMin : low ) );
					high = Math.max( absMin, Math.min( absMax, isNaN( high ) ? absMax : high ) );
					if ( low > high ) {
						low = high;
					}
					if ( minSlider ) minSlider.value = low;
					if ( maxSlider ) maxSlider.value = high;
					updateFill( low, high );
					triggerPriceChange( low, high );
				};

				if ( minSlider ) minSlider.addEventListener( 'input', syncFromSliders );
				if ( maxSlider ) maxSlider.addEventListener( 'input', syncFromSliders );
				if ( minInput ) minInput.addEventListener( 'change', syncFromInputs );
				if ( maxInput ) maxInput.addEventListener( 'change', syncFromInputs );

				// Initialize fill bar position
				updateFill(
					parseFloat( minSlider?.value ?? absMin ),
					parseFloat( maxSlider?.value ?? absMax )
				);
			} );

			// 7. WooCommerce Attribute Swatch Filter Buttons (.gm-swatch-filter-btn)
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const swatchBtn = e.target.closest( '.gm-swatch-filter-btn' );
				if ( ! swatchBtn ) return;

				const facetEl = swatchBtn.closest( '.gm-facet, .gm-facet-swatches' );
				const taxonomy = swatchBtn.getAttribute( 'data-taxonomy' ) || facetEl?.getAttribute( 'data-taxonomy' );
				const termId = parseInt( swatchBtn.getAttribute( 'data-term-id' ) || swatchBtn.value, 10 );
				if ( ! taxonomy || isNaN( termId ) ) return;

				const isMulti = facetEl ? facetEl.getAttribute( 'data-multi-select' ) !== '0' : true;

				if ( termId === 0 ) {
					if ( facetEl ) {
						facetEl.querySelectorAll( '.gm-swatch-filter-btn' ).forEach( ( b ) => {
							const isAll = parseInt( b.getAttribute( 'data-term-id' ), 10 ) === 0;
							b.classList.toggle( 'gm-active', isAll );
							b.setAttribute( 'aria-pressed', isAll ? 'true' : 'false' );
						} );
					}
					delete this.state.facets[ taxonomy ];
				} else if ( isMulti ) {
					swatchBtn.classList.toggle( 'gm-active' );
					const isActive = swatchBtn.classList.contains( 'gm-active' );
					swatchBtn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );

					const current = Array.isArray( this.state.facets[ taxonomy ] ) ? [ ...this.state.facets[ taxonomy ] ] : [];
					const idx = current.indexOf( termId );
					if ( isActive && idx === -1 ) {
						current.push( termId );
					} else if ( ! isActive && idx !== -1 ) {
						current.splice( idx, 1 );
					}

					if ( current.length === 0 ) {
						delete this.state.facets[ taxonomy ];
					} else {
						this.state.facets[ taxonomy ] = current;
					}
				} else {
					const wasActive = swatchBtn.classList.contains( 'gm-active' );
					if ( facetEl ) {
						facetEl.querySelectorAll( '.gm-swatch-filter-btn' ).forEach( ( b ) => {
							b.classList.remove( 'gm-active' );
							b.setAttribute( 'aria-pressed', 'false' );
						} );
					}
					if ( wasActive ) {
						delete this.state.facets[ taxonomy ];
					} else {
						swatchBtn.classList.add( 'gm-active' );
						swatchBtn.setAttribute( 'aria-pressed', 'true' );
						this.state.facets[ taxonomy ] = [ termId ];
					}
				}

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 8. WooCommerce Star Rating Filter Buttons (.gm-rating-filter-btn)
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const ratingBtn = e.target.closest( '.gm-rating-filter-btn' );
				if ( ! ratingBtn ) return;

				const rating = parseInt( ratingBtn.getAttribute( 'data-rating' ) || ratingBtn.value, 10 ) || 0;
				const facetEl = ratingBtn.closest( '.gm-facet, .gm-facet-rating' ) || this.filtersWrap;

				if ( this.state.min_rating === rating || rating === 0 ) {
					this.state.min_rating = 0;
					facetEl.querySelectorAll( '.gm-rating-filter-btn' ).forEach( ( b ) => {
						b.classList.remove( 'gm-active' );
						b.setAttribute( 'aria-pressed', 'false' );
					} );
				} else {
					this.state.min_rating = rating;
					facetEl.querySelectorAll( '.gm-rating-filter-btn' ).forEach( ( b ) => {
						const isMatch = parseInt( b.getAttribute( 'data-rating' ) || b.value, 10 ) === rating;
						b.classList.toggle( 'gm-active', isMatch );
						b.setAttribute( 'aria-pressed', isMatch ? 'true' : 'false' );
					} );
				}

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 9. WooCommerce Status Toggles (.gm-status-toggle-btn)
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const toggleBtn = e.target.closest( '.gm-status-toggle-btn' );
				if ( ! toggleBtn ) return;

				const statusKey = toggleBtn.getAttribute( 'data-status' ) || toggleBtn.getAttribute( 'data-status-key' );
				if ( ! [ 'on_sale', 'in_stock', 'featured' ].includes( statusKey ) ) return;

				const nextVal = ! this.state[ statusKey ];
				this.state[ statusKey ] = nextVal;
				toggleBtn.classList.toggle( 'gm-active', nextVal );
				toggleBtn.setAttribute( 'aria-pressed', nextVal ? 'true' : 'false' );

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 10. WooCommerce Sort Dropdown (.gm-woo-sort-select)
			this.filtersWrap.addEventListener( 'change', ( e ) => {
				const sortSelect = e.target.closest( '.gm-woo-sort-select' );
				if ( ! sortSelect ) return;

				this.state.orderby = sortSelect.value || '';
				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 11. Pro Date Range Inputs (.gm-date-from, .gm-date-to)
			this.filtersWrap.addEventListener( 'change', ( e ) => {
				const dateFrom = e.target.closest( '.gm-date-from' );
				const dateTo = e.target.closest( '.gm-date-to' );
				if ( ! dateFrom && ! dateTo ) return;

				if ( dateFrom ) {
					this.state.date_from = dateFrom.value || '';
				}
				if ( dateTo ) {
					this.state.date_to = dateTo.value || '';
				}

				this.state.page = 1;
				this.syncUrl();
				this.updateActiveFiltersBar();
				this.fetchResults( false );
			} );

			// 12. Pro Custom Meta Field Inputs (.gm-meta-input)
			this.filtersWrap.addEventListener( 'input', ( e ) => {
				const metaInput = e.target.closest( '.gm-meta-input' );
				if ( ! metaInput ) return;

				const metaKey = metaInput.getAttribute( 'data-meta-key' ) || metaInput.closest( '[data-meta-key]' )?.getAttribute( 'data-meta-key' );
				if ( ! metaKey ) return;

				clearTimeout( this.metaTimeout );
				this.metaTimeout = setTimeout( () => {
					const val = metaInput.value.trim();
					if ( val ) {
						this.state.meta[ metaKey ] = val;
					} else {
						delete this.state.meta[ metaKey ];
					}
					this.state.page = 1;
					this.syncUrl();
					this.updateActiveFiltersBar();
					this.fetchResults( false );
				}, 300 );
			} );

			// 13. Active Filter Chips Removal & Clear All (.gm-facet-active-filters)
			this.filtersWrap.addEventListener( 'click', ( e ) => {
				const clearAllBtn = e.target.closest( '.gm-clear-all-filters' );
				if ( clearAllBtn ) {
					e.preventDefault();
					this.resetAllFilters();
					return;
				}

				const chipBtn = e.target.closest( '.gm-active-filter-chip' );
				if ( chipBtn ) {
					e.preventDefault();
					const chipType = chipBtn.getAttribute( 'data-chip-type' );
					const chipKey = chipBtn.getAttribute( 'data-chip-key' );
					const chipVal = chipBtn.getAttribute( 'data-chip-val' );

					if ( chipType === 'taxonomy' && chipKey ) {
						const termId = parseInt( chipVal, 10 );
						if ( Array.isArray( this.state.facets[ chipKey ] ) ) {
							this.state.facets[ chipKey ] = this.state.facets[ chipKey ].filter( ( id ) => id !== termId );
							if ( this.state.facets[ chipKey ].length === 0 ) {
								delete this.state.facets[ chipKey ];
							}
						}
						// Sync UI controls for this term
						this.filtersWrap
							.querySelectorAll( `[data-taxonomy="${chipKey}"][data-term-id="${termId}"], [data-taxonomy="${chipKey}"][value="${termId}"]` )
							.forEach( ( ctrl ) => {
								ctrl.classList.remove( 'gm-active' );
								ctrl.setAttribute( 'aria-selected', 'false' );
								ctrl.setAttribute( 'aria-pressed', 'false' );
								if ( ctrl.type === 'checkbox' || ctrl.type === 'radio' ) {
									ctrl.checked = false;
									ctrl.closest( '.gm-pill-option, label' )?.classList.remove( 'gm-active' );
								}
							} );
						const selectCtrl = this.filtersWrap.querySelector( `.gm-filter-select[data-taxonomy="${chipKey}"]` );
						if ( selectCtrl && parseInt( selectCtrl.value, 10 ) === termId ) {
							selectCtrl.value = '0';
						}
						if ( ! this.state.facets[ chipKey ] ) {
							const allBtn = this.filtersWrap.querySelector( `.gm-facet[data-taxonomy="${chipKey}"] .gm-filter-btn[data-term-id="0"]` );
							if ( allBtn ) {
								allBtn.classList.add( 'gm-active' );
								allBtn.setAttribute( 'aria-selected', 'true' );
							}
						}
					} else if ( chipType === 'price' ) {
						this.state.min_price = '';
						this.state.max_price = '';
						this.filtersWrap.querySelectorAll( '.gm-facet-price-range' ).forEach( ( pf ) => {
							const absMin = pf.getAttribute( 'data-min' ) || '0';
							const absMax = pf.getAttribute( 'data-max' ) || '500';
							const minS = pf.querySelector( '.gm-price-range-min' );
							const maxS = pf.querySelector( '.gm-price-range-max' );
							const minI = pf.querySelector( '.gm-price-input-min' );
							const maxI = pf.querySelector( '.gm-price-input-max' );
							const fill = pf.querySelector( '.gm-price-range-fill' );
							if ( minS ) minS.value = absMin;
							if ( maxS ) maxS.value = absMax;
							if ( minI ) minI.value = absMin;
							if ( maxI ) maxI.value = absMax;
							if ( fill ) {
								fill.style.left = '0%';
								fill.style.width = '100%';
							}
						} );
					} else if ( chipType === 'rating' ) {
						this.state.min_rating = 0;
						this.filtersWrap.querySelectorAll( '.gm-rating-filter-btn' ).forEach( ( b ) => {
							b.classList.remove( 'gm-active' );
							b.setAttribute( 'aria-pressed', 'false' );
						} );
					} else if ( chipType === 'status' && chipKey ) {
						this.state[ chipKey ] = false;
						this.filtersWrap.querySelectorAll( `.gm-status-toggle-btn[data-status="${chipKey}"], .gm-status-toggle-btn[data-status-key="${chipKey}"]` ).forEach( ( b ) => {
							b.classList.remove( 'gm-active' );
							b.setAttribute( 'aria-pressed', 'false' );
						} );
					} else if ( chipType === 'search' ) {
						this.state.search = '';
						if ( searchInput ) searchInput.value = '';
					} else if ( chipType === 'date' ) {
						this.state.date_from = '';
						this.state.date_to = '';
						this.filtersWrap.querySelectorAll( '.gm-date-from, .gm-date-to' ).forEach( ( d ) => ( d.value = '' ) );
					} else if ( chipType === 'meta' && chipKey ) {
						delete this.state.meta[ chipKey ];
						this.filtersWrap
							.querySelectorAll( `.gm-meta-input[data-meta-key="${chipKey}"], .gm-facet-meta[data-meta-key="${chipKey}"] .gm-meta-input` )
							.forEach( ( m ) => ( m.value = '' ) );
					}

					this.state.page = 1;
					this.syncUrl();
					this.updateActiveFiltersBar();
					this.fetchResults( false );
				}
			} );

			// 14. Reset Filters button
			const resetBtn = this.filtersWrap.querySelector( '.gm-btn-reset' );
			if ( resetBtn ) {
				resetBtn.addEventListener( 'click', () => {
					this.resetAllFilters();
				} );
			}

			// Initial render of active filters bar if present
			this.updateActiveFiltersBar();
		}

		resetAllFilters() {
			this.state.facets = {};
			this.state.search = '';
			this.state.orderby = '';
			this.state.min_price = '';
			this.state.max_price = '';
			this.state.min_rating = 0;
			this.state.on_sale = false;
			this.state.in_stock = false;
			this.state.featured = false;
			this.state.meta = {};
			this.state.date_from = '';
			this.state.date_to = '';
			this.state.page = 1;

			if ( this.filtersWrap ) {
				this.filtersWrap.querySelectorAll( '.gm-filter-btn' ).forEach( ( b ) => {
					const isAll = parseInt( b.getAttribute( 'data-term-id' ), 10 ) === 0;
					b.classList.toggle( 'gm-active', isAll );
					b.setAttribute( 'aria-selected', isAll ? 'true' : 'false' );
				} );
				this.filtersWrap.querySelectorAll( '.gm-filter-select' ).forEach( ( s ) => ( s.value = '0' ) );
				this.filtersWrap.querySelectorAll( '.gm-woo-sort-select' ).forEach( ( s ) => ( s.value = '' ) );
				this.filtersWrap.querySelectorAll( '.gm-filter-checkbox, .gm-filter-checkbox-pill' ).forEach( ( c ) => {
					if ( c.tagName === 'INPUT' ) {
						c.checked = false;
						c.closest( '.gm-pill-option, label' )?.classList.remove( 'gm-active' );
					} else {
						c.classList.remove( 'gm-active' );
						c.setAttribute( 'aria-pressed', 'false' );
					}
				} );
				this.filtersWrap.querySelectorAll( '.gm-filter-radio' ).forEach( ( r ) => {
					r.checked = parseInt( r.value, 10 ) === 0;
				} );
				this.filtersWrap.querySelectorAll( '.gm-swatch-filter-btn, .gm-rating-filter-btn, .gm-status-toggle-btn' ).forEach( ( b ) => {
					const isAll = parseInt( b.getAttribute( 'data-term-id' ), 10 ) === 0 && b.classList.contains( 'gm-swatch-filter-btn' );
					b.classList.toggle( 'gm-active', isAll );
					b.setAttribute( 'aria-pressed', isAll ? 'true' : 'false' );
				} );
				this.filtersWrap.querySelectorAll( '.gm-facet-price-range' ).forEach( ( pf ) => {
					const absMin = pf.getAttribute( 'data-min' ) || '0';
					const absMax = pf.getAttribute( 'data-max' ) || '500';
					const minS = pf.querySelector( '.gm-price-range-min' );
					const maxS = pf.querySelector( '.gm-price-range-max' );
					const minI = pf.querySelector( '.gm-price-input-min' );
					const maxI = pf.querySelector( '.gm-price-input-max' );
					const fill = pf.querySelector( '.gm-price-range-fill' );
					if ( minS ) minS.value = absMin;
					if ( maxS ) maxS.value = absMax;
					if ( minI ) minI.value = absMin;
					if ( maxI ) maxI.value = absMax;
					if ( fill ) {
						fill.style.left = '0%';
						fill.style.width = '100%';
					}
				} );
				this.filtersWrap.querySelectorAll( '.gm-filter-search, .gm-date-from, .gm-date-to, .gm-meta-input' ).forEach( ( inp ) => {
					inp.value = '';
				} );
			}

			this.syncUrl();
			this.updateActiveFiltersBar();
			this.fetchResults( false );
		}

		updateActiveFiltersBar() {
			if ( ! this.filtersWrap ) return;
			const bar = this.filtersWrap.querySelector( '.gm-facet-active-filters' );
			if ( ! bar ) return;

			let listEl = bar.querySelector( '.gm-active-filters-list' );
			if ( ! listEl ) {
				listEl = document.createElement( 'div' );
				listEl.className = 'gm-active-filters-list';
				bar.insertBefore( listEl, bar.firstChild );
			}

			const chips = [];

			// 1. Taxonomy term chips
			Object.keys( this.state.facets ).forEach( ( tax ) => {
				const termIds = this.state.facets[ tax ];
				if ( ! Array.isArray( termIds ) ) return;
				termIds.forEach( ( termId ) => {
					if ( ! termId ) return;
					let label = `#${termId}`;
					const btnEl = this.filtersWrap.querySelector(
						`[data-taxonomy="${tax}"][data-term-id="${termId}"], .gm-swatch-filter-btn[data-taxonomy="${tax}"][data-term-id="${termId}"]`
					);
					const optEl = this.filtersWrap.querySelector( `.gm-filter-select[data-taxonomy="${tax}"] option[value="${termId}"]` );
					const cbEl = this.filtersWrap.querySelector(
						`.gm-filter-checkbox[data-taxonomy="${tax}"][value="${termId}"], .gm-filter-checkbox-pill[data-taxonomy="${tax}"][value="${termId}"]`
					);
					if ( btnEl ) {
						label =
							btnEl.getAttribute( 'data-label' ) ||
							btnEl.querySelector( '.gm-pill-label, .gm-swatch-label' )?.textContent.trim() ||
							btnEl.getAttribute( 'title' ) ||
							btnEl.textContent.trim();
					} else if ( optEl ) {
						label = optEl.textContent.trim();
					} else if ( cbEl ) {
						const parentLabel = cbEl.closest( 'label' );
						label = parentLabel ? parentLabel.textContent.trim() : label;
					}
					chips.push( { type: 'taxonomy', key: tax, val: String( termId ), label } );
				} );
			} );

			// 2. Price range chip
			if ( this.state.min_price !== '' || this.state.max_price !== '' ) {
				const minLabel = this.state.min_price !== '' ? this.state.min_price : '0';
				const maxLabel = this.state.max_price !== '' ? this.state.max_price : '∞';
				chips.push( { type: 'price', key: 'price', val: '1', label: `Price: ${minLabel} – ${maxLabel}` } );
			}

			// 3. Rating chip
			if ( this.state.min_rating > 0 ) {
				chips.push( { type: 'rating', key: 'rating', val: String( this.state.min_rating ), label: `${this.state.min_rating}★ & Up` } );
			}

			// 4. Status toggle chips
			if ( this.state.on_sale ) {
				chips.push( { type: 'status', key: 'on_sale', val: '1', label: 'On Sale' } );
			}
			if ( this.state.in_stock ) {
				chips.push( { type: 'status', key: 'in_stock', val: '1', label: 'In Stock' } );
			}
			if ( this.state.featured ) {
				chips.push( { type: 'status', key: 'featured', val: '1', label: 'Featured' } );
			}

			// 5. Date range chip
			if ( this.state.date_from || this.state.date_to ) {
				chips.push( {
					type: 'date',
					key: 'date',
					val: '1',
					label: `${this.state.date_from || '…'} → ${this.state.date_to || '…'}`,
				} );
			}

			// 6. Meta filter chips
			Object.keys( this.state.meta ).forEach( ( metaKey ) => {
				if ( this.state.meta[ metaKey ] ) {
					chips.push( {
						type: 'meta',
						key: metaKey,
						val: this.state.meta[ metaKey ],
						label: `${metaKey}: ${this.state.meta[ metaKey ]}`,
					} );
				}
			} );

			// 7. Search keyword chip
			if ( this.state.search ) {
				chips.push( { type: 'search', key: 'search', val: this.state.search, label: `"${this.state.search}"` } );
			}

			listEl.innerHTML = '';
			chips.forEach( ( chip ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'gm-active-filter-chip';
				btn.setAttribute( 'data-chip-type', chip.type );
				btn.setAttribute( 'data-chip-key', chip.key );
				btn.setAttribute( 'data-chip-val', chip.val );
				const textSpan = document.createElement( 'span' );
				textSpan.className = 'gm-chip-label';
				textSpan.textContent = chip.label;
				const removeSpan = document.createElement( 'span' );
				removeSpan.className = 'gm-chip-remove';
				removeSpan.setAttribute( 'aria-hidden', 'true' );
				removeSpan.textContent = '×';
				btn.append( textSpan, removeSpan );
				listEl.appendChild( btn );
			} );

			const clearBtn = bar.querySelector( '.gm-clear-all-filters' );
			if ( clearBtn ) {
				clearBtn.style.display = chips.length > 0 ? '' : 'none';
			}
			bar.classList.toggle( 'has-active-filters', chips.length > 0 );
			this.updateDrawerFilterBadge( chips.length );
		}

		updateDrawerFilterBadge( count ) {
			const badge = this.container.querySelector( '.gm-drawer-count' );
			if ( ! badge ) return;
			let activeCount = typeof count === 'number' ? count : 0;
			if ( typeof count !== 'number' ) {
				Object.keys( this.state.facets || {} ).forEach( ( k ) => {
					if ( Array.isArray( this.state.facets[ k ] ) ) {
						activeCount += this.state.facets[ k ].length;
					}
				} );
				if ( this.state.search ) activeCount++;
				if ( this.state.min_price !== '' || this.state.max_price !== '' ) activeCount++;
				if ( this.state.min_rating > 0 ) activeCount++;
				if ( this.state.on_sale ) activeCount++;
				if ( this.state.in_stock ) activeCount++;
				if ( this.state.featured ) activeCount++;
				if ( this.state.date_from || this.state.date_to ) activeCount++;
				activeCount += Object.keys( this.state.meta || {} ).length;
			}
			badge.textContent = String( activeCount );
			badge.hidden = activeCount === 0;
		}

		initFilterDrawer() {
			const toggleBtn = this.container.querySelector( '.gm-filter-drawer-toggle' );
			const closeBtn = this.container.querySelector( '.gm-filter-drawer-close' );
			const backdrop = this.container.querySelector( '.gm-filters-drawer-backdrop' );
			if ( ! toggleBtn || ! this.filtersWrap ) return;

			const openDrawer = () => {
				this.filtersWrap.classList.add( 'is-open' );
				toggleBtn.setAttribute( 'aria-expanded', 'true' );
				if ( backdrop ) backdrop.hidden = false;
				( closeBtn || this.filtersWrap ).focus?.();
			};

			const closeDrawer = () => {
				this.filtersWrap.classList.remove( 'is-open' );
				toggleBtn.setAttribute( 'aria-expanded', 'false' );
				if ( backdrop ) backdrop.hidden = true;
			};

			toggleBtn.addEventListener( 'click', () => {
				if ( this.filtersWrap.classList.contains( 'is-open' ) ) {
					closeDrawer();
				} else {
					openDrawer();
				}
			} );

			if ( closeBtn ) closeBtn.addEventListener( 'click', closeDrawer );
			if ( backdrop ) backdrop.addEventListener( 'click', closeDrawer );

			this.container.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this.filtersWrap.classList.contains( 'is-open' ) ) {
					closeDrawer();
					toggleBtn.focus?.();
				}
			} );

			this.updateDrawerFilterBadge();
		}

		updateFacetCounts( facetCounts, hideEmptyMap = {} ) {
			if ( ! this.filtersWrap || ! facetCounts || typeof facetCounts !== 'object' ) return;

			Object.keys( facetCounts ).forEach( ( tax ) => {
				const counts = facetCounts[ tax ] || {};
				const hideEmpty = !! hideEmptyMap[ tax ];
				const activeSet = new Set( Array.isArray( this.state.facets[ tax ] ) ? this.state.facets[ tax ] : [] );

				// Checkboxes & Radios
				this.filtersWrap
					.querySelectorAll( `.gm-facet[data-taxonomy="${tax}"] input.gm-filter-checkbox, .gm-facet[data-taxonomy="${tax}"] input.gm-filter-radio` )
					.forEach( ( inp ) => {
						const tid = parseInt( inp.value, 10 );
						if ( ! tid || ! Object.prototype.hasOwnProperty.call( counts, String( tid ) ) ) return;
						const count = parseInt( counts[ String( tid ) ], 10 ) || 0;
						const isActive = activeSet.has( tid ) || inp.checked;
						const label = inp.closest( 'label' );
						if ( label ) {
							const countEl = label.querySelector( '.gm-term-count' );
							if ( countEl ) {
								countEl.textContent = '(' + count + ')';
							}
							label.classList.toggle( 'gm-term-empty', count === 0 );
							label.classList.toggle( 'gm-term-hidden', hideEmpty && count === 0 && ! isActive );
						}
					} );

				// Dropdown options
				this.filtersWrap.querySelectorAll( `select.gm-filter-select[data-taxonomy="${tax}"] option` ).forEach( ( opt ) => {
					const tid = parseInt( opt.value, 10 );
					if ( ! tid || ! Object.prototype.hasOwnProperty.call( counts, String( tid ) ) ) return;
					if ( ! opt.dataset.baseLabel ) {
						opt.dataset.baseLabel = opt.textContent.replace( /\s*\(\d+\)\s*$/, '' ).trim();
					}
					const count = parseInt( counts[ String( tid ) ], 10 ) || 0;
					const isActive = activeSet.has( tid ) || opt.selected;
					opt.textContent = opt.dataset.baseLabel + ' (' + count + ')';
					opt.disabled = count === 0 && ! isActive;
					opt.hidden = hideEmpty && count === 0 && ! isActive;
				} );

				// Multi-select pills
				this.filtersWrap.querySelectorAll( `button.gm-filter-checkbox-pill[data-taxonomy="${tax}"]` ).forEach( ( pill ) => {
					const tid = parseInt( pill.getAttribute( 'data-term-id' ), 10 );
					if ( ! tid || ! Object.prototype.hasOwnProperty.call( counts, String( tid ) ) ) return;
					const count = parseInt( counts[ String( tid ) ], 10 ) || 0;
					const isActive = activeSet.has( tid ) || pill.classList.contains( 'gm-active' );
					const countEl = pill.querySelector( '.gm-pill-count' );
					if ( countEl ) {
						countEl.textContent = String( count );
					}
					pill.classList.toggle( 'gm-term-empty', count === 0 );
					pill.classList.toggle( 'gm-term-hidden', hideEmpty && count === 0 && ! isActive );
					pill.disabled = count === 0 && ! isActive;
				} );

				// Swatches
				this.filtersWrap.querySelectorAll( `.gm-swatch-filter-btn[data-taxonomy="${tax}"]` ).forEach( ( sw ) => {
					const tid = parseInt( sw.getAttribute( 'data-term-id' ), 10 );
					if ( ! tid || ! Object.prototype.hasOwnProperty.call( counts, String( tid ) ) ) return;
					const count = parseInt( counts[ String( tid ) ], 10 ) || 0;
					const isActive = activeSet.has( tid ) || sw.classList.contains( 'gm-active' );
					const countEl = sw.querySelector( '.gm-swatch-count' );
					if ( countEl ) {
						countEl.textContent = '(' + count + ')';
					}
					sw.classList.toggle( 'gm-term-empty', count === 0 );
					sw.classList.toggle( 'gm-term-hidden', hideEmpty && count === 0 && ! isActive );
					sw.disabled = count === 0 && ! isActive;
				} );

				// Tabs
				this.filtersWrap.querySelectorAll( `.gm-facet-tabs[data-taxonomy="${tax}"] .gm-filter-btn[data-term-id]` ).forEach( ( tab ) => {
					const tid = parseInt( tab.getAttribute( 'data-term-id' ), 10 );
					if ( ! tid || ! Object.prototype.hasOwnProperty.call( counts, String( tid ) ) ) return;
					const count = parseInt( counts[ String( tid ) ], 10 ) || 0;
					const isActive = activeSet.has( tid ) || tab.classList.contains( 'gm-active' );
					const countEl = tab.querySelector( '.gm-term-count' );
					if ( countEl ) {
						countEl.textContent = '(' + count + ')';
					}
					tab.classList.toggle( 'gm-term-empty', count === 0 );
					tab.classList.toggle( 'gm-term-hidden', hideEmpty && count === 0 && ! isActive );
				} );
			} );
		}

		initPagination() {
			this.container.addEventListener( 'click', ( e ) => {
				// Numbered pagination or prev/next
				const pageBtn = e.target.closest( '.gm-page-btn' );
				if ( pageBtn ) {
					const page = parseInt( pageBtn.getAttribute( 'data-page' ), 10 );
					if ( page && page !== this.state.page ) {
						this.state.page = page;
						this.syncUrl();
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
			if ( this.infiniteObserver ) this.infiniteObserver.disconnect();
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
			}, { rootMargin: '300px' } );

			observer.observe( infiniteEl );
			this.infiniteObserver = observer;
		}

		fetchResults( append = false ) {
			if ( this.activeAbortController ) {
				this.activeAbortController.abort();
			}

			this.activeAbortController = new AbortController();
			const controller = this.activeAbortController;
			this.isLoading = true;
			this.container.querySelector( '.gm-request-error' )?.remove();

			this.setLoadingState( true, append );

			const isPreview = this.container.getAttribute( 'data-preview-mode' ) === '1' || window.__gm_preview_mode;
			const restBase = window.gridmasterV2 ? window.gridmasterV2.restUrl : '/wp-json/gridmaster/v2/';

			let guestBookmarks = [];
			try {
				const rawBookmarks = window.localStorage ? window.localStorage.getItem( 'gm_bookmarks' ) : null;
				if ( rawBookmarks ) {
					const parsed = JSON.parse( rawBookmarks );
					if ( Array.isArray( parsed ) ) {
						guestBookmarks = parsed.map( ( id ) => parseInt( id, 10 ) ).filter( Boolean );
					}
				}
			} catch ( e ) {}
			
			let gridContext = {};
			try {
				const rawCtx = this.container.getAttribute( 'data-context' );
				if ( rawCtx ) {
					const parsedCtx = JSON.parse( rawCtx );
					if ( parsedCtx && typeof parsedCtx === 'object' ) {
						gridContext = parsedCtx;
					}
				}
			} catch ( e ) {}

			let url;
			let payload = {
				page: this.state.page,
				facets: this.state.facets,
				search: this.state.search,
				orderby: this.state.orderby,
				min_price: this.state.min_price,
				max_price: this.state.max_price,
				min_rating: this.state.min_rating,
				on_sale: this.state.on_sale,
				in_stock: this.state.in_stock,
				featured: this.state.featured,
				meta: this.state.meta,
				date_from: this.state.date_from,
				date_to: this.state.date_to,
				bookmarks: guestBookmarks,
				context: gridContext,
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
				.then( async ( res ) => {
					const data = await res.json();
					if ( ! res.ok || ! data || ! data.html ) {
						throw new Error( data?.message || window.gridmasterV2?.errorText || 'Could not load posts. Please retry.' );
					}
					return data;
				} )
				.then( ( data ) => {
					if ( controller.signal.aborted || controller !== this.activeAbortController ) return;
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
								const noRes = temp.querySelector( '.gm-no-results, .gm-empty-results' );
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
							( this.container.querySelector( '.gm-grid-main' ) || this.container ).appendChild( newPagination );
						}

						// Re-observe if infinite scroll
						this.initInfiniteScroll();
						this.initSlider();
						this.initVerticalReels();
						this.layoutMasonry();

						if ( data.facet_counts ) {
							this.updateFacetCounts( data.facet_counts, data.facet_hide_empty || {} );
						}

						// Dispatch loaded event for Pro blocks and external widgets
						document.dispatchEvent( new CustomEvent( 'gridmaster_loaded_posts', { detail: { gridId: this.gridId } } ) );
						document.dispatchEvent( new CustomEvent( 'gridmaster:rendered', { detail: { gridId: this.gridId, container: this.container } } ) );
						if ( window.jQuery ) {
							window.jQuery( document ).trigger( 'gridmaster_loaded_posts', [ this.gridId ] );
							window.jQuery( document ).trigger( 'gridmaster_v2_rendered', [ this.container ] );
						}
					}
				} )
				.catch( ( err ) => {
					if ( err.name !== 'AbortError' && controller === this.activeAbortController ) {
						this.isLoading = false;
						this.setLoadingState( false, append );
						const error = document.createElement( 'div' );
						error.className = 'gm-request-error';
						error.setAttribute( 'role', 'alert' );
						const message = document.createElement( 'span' );
						message.textContent = err.message;
						const retry = document.createElement( 'button' );
						retry.type = 'button';
						retry.textContent = window.gridmasterV2?.retryText || 'Retry';
						retry.addEventListener( 'click', () => this.fetchResults( append ) );
						error.append( message, retry );
						this.container.appendChild( error );
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
							if ( textSpan && loadText ) {
								loadBtn.dataset.originalText = textSpan.textContent;
								textSpan.textContent = loadText;
							}
					}
				}
			} else {
				this.container.classList.remove( 'gm-loading' );
				this.itemsContainer.style.opacity = '1';
				const loadBtn = this.container.querySelector( '.gm-btn-load-more' );
				if ( loadBtn ) {
					loadBtn.classList.remove( 'gm-loading-spinner' );
					const text = loadBtn.querySelector( '.gm-btn-text' );
					if ( text && loadBtn.dataset.originalText ) text.textContent = loadBtn.dataset.originalText;
				}
			}
		}

		syncUrl() {
			if ( this.isPreviewMode() ) {
				return;
			}
			try {
				const url = new URL( window.location.href );
				const prevUrlStr = url.toString();

				const setOrDelete = ( key, val ) => {
					if ( val !== undefined && val !== null && val !== '' ) {
						url.searchParams.set( key, String( val ) );
					} else {
						url.searchParams.delete( key );
					}
				};

				setOrDelete( 'gm_q', this.state.search );
				setOrDelete( 'gm_paged', this.state.page > 1 ? this.state.page : '' );
				setOrDelete( 'gm_sort', this.state.orderby );
				setOrDelete( 'gm_min_price', this.state.min_price !== '' ? this.state.min_price : '' );
				setOrDelete( 'gm_max_price', this.state.max_price !== '' ? this.state.max_price : '' );
				setOrDelete( 'gm_rating', this.state.min_rating > 0 ? this.state.min_rating : '' );
				setOrDelete( 'gm_sale', this.state.on_sale ? '1' : '' );
				setOrDelete( 'gm_stock', this.state.in_stock ? '1' : '' );
				setOrDelete( 'gm_featured', this.state.featured ? '1' : '' );
				setOrDelete( 'gm_from', this.state.date_from );
				setOrDelete( 'gm_to', this.state.date_to );

				// Persist meta filters (`gm_meta_<key>`) and remove stale meta params
				const activeMetaKeys = new Set();
				Object.keys( this.state.meta || {} ).forEach( ( metaKey ) => {
					const val = this.state.meta[ metaKey ];
					if ( val !== undefined && val !== null && val !== '' ) {
						const paramKey = 'gm_meta_' + metaKey;
						url.searchParams.set( paramKey, String( val ) );
						activeMetaKeys.add( paramKey );
					}
				} );

				// Persist taxonomy facets (`gm_<taxonomy>`)
				const activeFacetKeys = new Set();
				Object.keys( this.state.facets || {} ).forEach( ( tax ) => {
					const vals = this.state.facets[ tax ];
					if ( Array.isArray( vals ) && vals.length > 0 ) {
						const paramKey = 'gm_' + tax;
						url.searchParams.set( paramKey, vals.join( ',' ) );
						activeFacetKeys.add( paramKey );
					}
				} );

				const reservedParams = new Set( [
					'gm_q',
					'gm_paged',
					'gm_sort',
					'gm_min_price',
					'gm_max_price',
					'gm_rating',
					'gm_sale',
					'gm_stock',
					'gm_featured',
					'gm_from',
					'gm_to',
				] );

				for ( const param of Array.from( url.searchParams.keys() ) ) {
					if ( ! param.startsWith( 'gm_' ) || reservedParams.has( param ) ) {
						continue;
					}
					if ( param.startsWith( 'gm_meta_' ) ) {
						if ( ! activeMetaKeys.has( param ) ) {
							url.searchParams.delete( param );
						}
					} else if ( ! activeFacetKeys.has( param ) ) {
						url.searchParams.delete( param );
					}
				}

				const nextUrlStr = url.toString();
				if ( nextUrlStr !== prevUrlStr ) {
					window.history.replaceState( null, '', nextUrlStr );
				}
			} catch ( e ) {}
		}

		hydrateFromUrl() {
			if ( this.isPreviewMode() ) {
				return false;
			}
			try {
				const params = new URLSearchParams( window.location.search );
				let hasAny = false;
				const reservedParams = new Set( [
					'gm_q',
					'gm_paged',
					'gm_sort',
					'gm_min_price',
					'gm_max_price',
					'gm_rating',
					'gm_sale',
					'gm_stock',
					'gm_featured',
					'gm_from',
					'gm_to',
				] );

				const q = params.get( 'gm_q' );
				if ( q ) {
					this.state.search = q.trim();
					const searchInput = this.filtersWrap?.querySelector( '.gm-filter-search' );
					if ( searchInput ) searchInput.value = this.state.search;
					hasAny = true;
				}

				const paged = parseInt( params.get( 'gm_paged' ) || '', 10 );
				if ( paged && paged > 1 ) {
					this.state.page = paged;
					hasAny = true;
				}

				const sort = params.get( 'gm_sort' );
				if ( sort ) {
					this.state.orderby = sort;
					const sortSelect = this.filtersWrap?.querySelector( '.gm-woo-sort-select' );
					if ( sortSelect ) sortSelect.value = sort;
					hasAny = true;
				}

				const minPrice = params.get( 'gm_min_price' );
				const maxPrice = params.get( 'gm_max_price' );
				if ( minPrice !== null && minPrice !== '' ) {
					this.state.min_price = parseFloat( minPrice );
					hasAny = true;
				}
				if ( maxPrice !== null && maxPrice !== '' ) {
					this.state.max_price = parseFloat( maxPrice );
					hasAny = true;
				}
				if ( ( minPrice !== null && minPrice !== '' ) || ( maxPrice !== null && maxPrice !== '' ) ) {
					this.filtersWrap?.querySelectorAll( '.gm-facet-price-range' ).forEach( ( pf ) => {
						const minS = pf.querySelector( '.gm-price-range-min' );
						const maxS = pf.querySelector( '.gm-price-range-max' );
						const minI = pf.querySelector( '.gm-price-input-min' );
						const maxI = pf.querySelector( '.gm-price-input-max' );
						const fill = pf.querySelector( '.gm-price-range-fill' );
						const absMin = parseFloat( pf.getAttribute( 'data-min' ) ?? minS?.min ?? 0 );
						const absMax = parseFloat( pf.getAttribute( 'data-max' ) ?? maxS?.max ?? 500 );
						const low = this.state.min_price !== '' ? Math.max( absMin, Math.min( absMax, Number( this.state.min_price ) ) ) : absMin;
						const high = this.state.max_price !== '' ? Math.max( low, Math.min( absMax, Number( this.state.max_price ) ) ) : absMax;
						if ( minS ) minS.value = String( low );
						if ( maxS ) maxS.value = String( high );
						if ( minI ) minI.value = String( low );
						if ( maxI ) maxI.value = String( high );
						if ( fill ) {
							const span = Math.max( 1, absMax - absMin );
							const leftPct = Math.max( 0, Math.min( 100, ( ( low - absMin ) / span ) * 100 ) );
							const rightPct = Math.max( 0, Math.min( 100, ( ( high - absMin ) / span ) * 100 ) );
							fill.style.left = leftPct + '%';
							fill.style.width = Math.max( 0, rightPct - leftPct ) + '%';
						}
					} );
				}

				const rating = parseInt( params.get( 'gm_rating' ) || '', 10 );
				if ( rating > 0 ) {
					this.state.min_rating = rating;
					this.filtersWrap?.querySelectorAll( '.gm-rating-filter-btn' ).forEach( ( b ) => {
						const isMatch = parseInt( b.getAttribute( 'data-rating' ) || b.value, 10 ) === rating;
						b.classList.toggle( 'gm-active', isMatch );
						b.setAttribute( 'aria-pressed', isMatch ? 'true' : 'false' );
					} );
					hasAny = true;
				}

				[
					[ 'gm_sale', 'on_sale' ],
					[ 'gm_stock', 'in_stock' ],
					[ 'gm_featured', 'featured' ],
				].forEach( ( [ paramKey, stateKey ] ) => {
					const raw = params.get( paramKey );
					if ( raw === '1' || raw === 'true' ) {
						this.state[ stateKey ] = true;
						this.filtersWrap
							?.querySelectorAll( `.gm-status-toggle-btn[data-status="${stateKey}"], .gm-status-toggle-btn[data-status-key="${stateKey}"]` )
							.forEach( ( b ) => {
								b.classList.add( 'gm-active' );
								b.setAttribute( 'aria-pressed', 'true' );
							} );
						hasAny = true;
					}
				} );

				const dateFrom = params.get( 'gm_from' );
				const dateTo = params.get( 'gm_to' );
				if ( dateFrom ) {
					this.state.date_from = dateFrom;
					this.filtersWrap?.querySelectorAll( '.gm-date-from' ).forEach( ( d ) => ( d.value = dateFrom ) );
					hasAny = true;
				}
				if ( dateTo ) {
					this.state.date_to = dateTo;
					this.filtersWrap?.querySelectorAll( '.gm-date-to' ).forEach( ( d ) => ( d.value = dateTo ) );
					hasAny = true;
				}

				params.forEach( ( val, key ) => {
					if ( ! key.startsWith( 'gm_' ) || reservedParams.has( key ) || ! val ) {
						return;
					}
					if ( key.startsWith( 'gm_meta_' ) ) {
						const metaKey = key.slice( 'gm_meta_'.length );
						if ( metaKey ) {
							this.state.meta[ metaKey ] = val;
							this.filtersWrap
								?.querySelectorAll( `.gm-meta-input[data-meta-key="${metaKey}"], .gm-facet-meta[data-meta-key="${metaKey}"] .gm-meta-input` )
								.forEach( ( m ) => ( m.value = val ) );
							hasAny = true;
						}
						return;
					}

					const tax = key.slice( 'gm_'.length );
					const termIds = val
						.split( ',' )
						.map( ( id ) => parseInt( id.trim(), 10 ) )
						.filter( ( id ) => id > 0 );
					if ( tax && termIds.length > 0 ) {
						this.state.facets[ tax ] = termIds;
						hasAny = true;

						if ( this.filtersWrap ) {
							this.filtersWrap.querySelectorAll( `.gm-facet[data-taxonomy="${tax}"] .gm-filter-btn` ).forEach( ( b ) => {
								const bId = parseInt( b.getAttribute( 'data-term-id' ), 10 );
								const match = termIds.includes( bId );
								b.classList.toggle( 'gm-active', match );
								b.setAttribute( 'aria-selected', match ? 'true' : 'false' );
							} );
							const select = this.filtersWrap.querySelector( `.gm-filter-select[data-taxonomy="${tax}"]` );
							if ( select ) {
								select.value = String( termIds[ 0 ] );
							}
							this.filtersWrap.querySelectorAll( `input.gm-filter-checkbox[data-taxonomy="${tax}"], input.gm-filter-checkbox-pill[data-taxonomy="${tax}"]` ).forEach( ( cb ) => {
								const match = termIds.includes( parseInt( cb.value, 10 ) );
								cb.checked = match;
								cb.closest( '.gm-pill-option, label' )?.classList.toggle( 'gm-active', match );
							} );
							this.filtersWrap.querySelectorAll( `button.gm-filter-checkbox-pill[data-taxonomy="${tax}"]` ).forEach( ( pill ) => {
								const pId = parseInt( pill.getAttribute( 'data-term-id' ), 10 );
								const match = termIds.includes( pId );
								pill.classList.toggle( 'gm-active', match );
								pill.setAttribute( 'aria-pressed', match ? 'true' : 'false' );
							} );
							this.filtersWrap.querySelectorAll( `.gm-filter-radio[data-taxonomy="${tax}"]` ).forEach( ( r ) => {
								r.checked = termIds.includes( parseInt( r.value, 10 ) );
							} );
							this.filtersWrap.querySelectorAll( `.gm-swatch-filter-btn[data-taxonomy="${tax}"]` ).forEach( ( sw ) => {
								const sId = parseInt( sw.getAttribute( 'data-term-id' ), 10 );
								const match = termIds.includes( sId );
								sw.classList.toggle( 'gm-active', match );
								sw.setAttribute( 'aria-pressed', match ? 'true' : 'false' );
							} );
						}
					}
				} );

				if ( hasAny ) {
					this.updateActiveFiltersBar();
				}
				return hasAny;
			} catch ( e ) {
				return false;
			}
		}

		initSlider() {
			if ( this.sliderCleanup ) {
				this.sliderCleanup();
				this.sliderCleanup = null;
			}

			const isSlider = this.container.classList.contains( 'gm-layout-slider' ) || this.container.getAttribute( 'data-layout' ) === 'slider';
			if ( ! isSlider ) {
				return;
			}

			const track = this.container.querySelector( '.gm-slider-track' );
			if ( ! track ) {
				return;
			}

			const slides = Array.from( track.querySelectorAll( '.gm-slider-slide' ) );
			if ( ! slides.length ) {
				return;
			}

			let cfg = {
				arrows: true,
				dots: true,
				autoplay: false,
				autoplay_speed: 3000,
				pause_on_hover: true,
				infinite: true,
			};

			try {
				const rawCfg = this.container.getAttribute( 'data-slider-config' );
				if ( rawCfg ) {
					cfg = Object.assign( cfg, JSON.parse( rawCfg ) );
				}
			} catch ( e ) {}

			const prevBtn = this.container.querySelector( '.gm-slider-prev' );
			const nextBtn = this.container.querySelector( '.gm-slider-next' );
			const dotsWrap = this.container.querySelector( '.gm-slider-dots' );
			const playPauseBtn = this.container.querySelector( '.gm-slider-playpause' );

			const getLeftMostIndex = () => {
				let bestIdx = 0;
				let minDiff = Infinity;
				const currentScroll = track.scrollLeft;
				slides.forEach( ( slide, idx ) => {
					const slideOffset = slide.offsetLeft - track.offsetLeft;
					const diff = Math.abs( slideOffset - currentScroll );
					if ( diff < minDiff ) {
						minDiff = diff;
						bestIdx = idx;
					}
				} );
				return bestIdx;
			};

			const updateDots = () => {
				if ( ! dotsWrap ) return;
				const cur = getLeftMostIndex();
				const dots = dotsWrap.querySelectorAll( '.gm-slider-dot' );
				dots.forEach( ( dot, idx ) => {
					const isActive = idx === cur;
					dot.classList.toggle( 'is-active', isActive );
					dot.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
					dot.setAttribute( 'tabindex', isActive ? '0' : '-1' );
				} );
			};

			const hideNonVisibleCards = () => {
				const trackRect = track.getBoundingClientRect();
				const trackLeft = trackRect.left;
				const trackRight = trackRect.right;
				const tolerance = 4;

				slides.forEach( ( slide ) => {
					const slideRect = slide.getBoundingClientRect();
					const isVisible = ( slideRect.right > trackLeft + tolerance ) && ( slideRect.left < trackRight - tolerance );

					if ( isVisible ) {
						slide.removeAttribute( 'aria-hidden' );
						const interactives = slide.querySelectorAll( 'a, button, input, select, textarea, [data-gm-orig-tabindex]' );
						interactives.forEach( ( el ) => {
							if ( el.hasAttribute( 'data-gm-orig-tabindex' ) ) {
								const orig = el.getAttribute( 'data-gm-orig-tabindex' );
								if ( orig === 'none' ) {
									el.removeAttribute( 'tabindex' );
								} else {
									el.setAttribute( 'tabindex', orig );
								}
								el.removeAttribute( 'data-gm-orig-tabindex' );
							} else if ( el.getAttribute( 'tabindex' ) === '-1' ) {
								el.removeAttribute( 'tabindex' );
							}
						} );
					} else {
						slide.setAttribute( 'aria-hidden', 'true' );
						const interactives = slide.querySelectorAll( 'a, button, input, select, textarea, [tabindex]' );
						interactives.forEach( ( el ) => {
							if ( ! el.hasAttribute( 'data-gm-orig-tabindex' ) ) {
								el.setAttribute( 'data-gm-orig-tabindex', el.getAttribute( 'tabindex' ) || 'none' );
							}
							el.setAttribute( 'tabindex', '-1' );
						} );
					}
				} );

				updateDots();

				if ( ! cfg.infinite ) {
					if ( prevBtn ) {
						const isStart = track.scrollLeft <= 4;
						prevBtn.disabled = isStart;
						prevBtn.setAttribute( 'aria-disabled', isStart ? 'true' : 'false' );
					}
					if ( nextBtn ) {
						const isEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
						nextBtn.disabled = isEnd;
						nextBtn.setAttribute( 'aria-disabled', isEnd ? 'true' : 'false' );
					}
				}
			};

			const goToSlide = ( index, smooth = true ) => {
				if ( index < 0 ) {
					index = cfg.infinite ? slides.length - 1 : 0;
				} else if ( index >= slides.length ) {
					index = cfg.infinite ? 0 : slides.length - 1;
				}

				const targetSlide = slides[ index ];
				if ( ! targetSlide ) return;

				slides.forEach( ( s ) => s.removeAttribute( 'aria-hidden' ) );

				const targetLeft = targetSlide.offsetLeft - track.offsetLeft;
				track.scrollTo( {
					left: targetLeft,
					behavior: smooth ? 'smooth' : 'auto',
				} );

				setTimeout( hideNonVisibleCards, smooth ? 350 : 50 );
			};

			const nextSlide = () => {
				const cur = getLeftMostIndex();
				if ( cur < slides.length - 1 ) {
					goToSlide( cur + 1 );
				} else if ( cfg.infinite ) {
					goToSlide( 0 );
				}
			};

			const prevSlide = () => {
				const cur = getLeftMostIndex();
				if ( cur > 0 ) {
					goToSlide( cur - 1 );
				} else if ( cfg.infinite ) {
					goToSlide( slides.length - 1 );
				}
			};

			const onPrevClick = () => {
				prevSlide();
				pauseAutoplay();
			};

			const onNextClick = () => {
				nextSlide();
				pauseAutoplay();
			};

			if ( prevBtn ) prevBtn.addEventListener( 'click', onPrevClick );
			if ( nextBtn ) nextBtn.addEventListener( 'click', onNextClick );

			// Build dots
			if ( dotsWrap && cfg.dots ) {
				dotsWrap.innerHTML = '';
				slides.forEach( ( _, idx ) => {
					const dot = document.createElement( 'button' );
					dot.className = 'gm-slider-dot' + ( idx === 0 ? ' is-active' : '' );
					dot.type = 'button';
					dot.setAttribute( 'role', 'tab' );
					dot.setAttribute( 'aria-label', `Go to slide ${idx + 1}` );
					dot.setAttribute( 'aria-selected', idx === 0 ? 'true' : 'false' );
					dot.setAttribute( 'tabindex', idx === 0 ? '0' : '-1' );
					dot.addEventListener( 'click', () => {
						goToSlide( idx );
						pauseAutoplay();
					} );
					dotsWrap.appendChild( dot );
				} );
			}

			// Track keyboard navigation
			const onKeyDown = ( e ) => {
				if ( e.key === 'ArrowRight' || e.key === 'Right' ) {
					e.preventDefault();
					nextSlide();
				} else if ( e.key === 'ArrowLeft' || e.key === 'Left' ) {
					e.preventDefault();
					prevSlide();
				} else if ( e.key === 'Home' ) {
					e.preventDefault();
					goToSlide( 0 );
				} else if ( e.key === 'End' ) {
					e.preventDefault();
					goToSlide( slides.length - 1 );
				}
			};
			track.addEventListener( 'keydown', onKeyDown );

			let scrollTimeout = null;
			const onScroll = () => {
				if ( scrollTimeout ) clearTimeout( scrollTimeout );
				scrollTimeout = setTimeout( hideNonVisibleCards, 100 );
			};
			track.addEventListener( 'scroll', onScroll, { passive: true } );

			// Dragging / Finger Tap Scrolling & Swiping Gestures
			let isPointerDown = false;
			let isDragging = false;
			let startX = 0;
			let startScrollLeft = 0;
			let startTime = 0;
			let preventClick = false;

			const onPointerDown = ( e ) => {
				// Only accept primary mouse click (0) or touch/pen inputs
				if ( e.button !== undefined && e.button !== 0 ) return;

				isPointerDown = true;
				isDragging = false;
				preventClick = false;
				startX = e.pageX;
				startScrollLeft = track.scrollLeft;
				startTime = Date.now();

				pauseAutoplay();
			};

			const onPointerMove = ( e ) => {
				if ( ! isPointerDown ) return;

				const currentX = e.pageX;
				const walk = currentX - startX;

				if ( ! isDragging && Math.abs( walk ) > 6 ) {
					isDragging = true;
					preventClick = true;
					track.classList.add( 'is-dragging' );
					slides.forEach( ( s ) => s.removeAttribute( 'aria-hidden' ) );
				}

				if ( isDragging ) {
					track.scrollLeft = startScrollLeft - walk;
				}
			};

			const onPointerUp = ( e ) => {
				if ( ! isPointerDown ) return;
				isPointerDown = false;

				if ( isDragging ) {
					track.classList.remove( 'is-dragging' );
					const totalWalk = e.pageX - startX;
					const elapsed = Date.now() - startTime;
					const velocity = Math.abs( totalWalk ) / ( elapsed || 1 );

					// Swiped with drag distance > 40px or flick velocity > 0.35 px/ms
					if ( totalWalk < -40 || ( velocity > 0.35 && totalWalk < -15 ) ) {
						nextSlide();
					} else if ( totalWalk > 40 || ( velocity > 0.35 && totalWalk > 15 ) ) {
						prevSlide();
					} else {
						// Snap back to closest slide
						goToSlide( getLeftMostIndex() );
					}

					setTimeout( () => {
						preventClick = false;
						isDragging = false;
					}, 60 );
				} else {
					resumeAutoplay();
				}
			};

			const onPointerCancel = () => {
				if ( ! isPointerDown ) return;
				isPointerDown = false;
				if ( isDragging ) {
					track.classList.remove( 'is-dragging' );
					goToSlide( getLeftMostIndex() );
					setTimeout( () => {
						preventClick = false;
						isDragging = false;
					}, 60 );
				}
			};

			const onClickCapture = ( e ) => {
				if ( preventClick ) {
					e.preventDefault();
					e.stopPropagation();
					e.stopImmediatePropagation();
					return false;
				}
			};

			track.addEventListener( 'pointerdown', onPointerDown );
			window.addEventListener( 'pointermove', onPointerMove );
			window.addEventListener( 'pointerup', onPointerUp );
			window.addEventListener( 'pointercancel', onPointerCancel );
			track.addEventListener( 'click', onClickCapture, true );

			// Autoplay logic
			let isPlaying = !! cfg.autoplay && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			let autoplayInterval = null;

			const startAutoplay = () => {
				if ( ! isPlaying || autoplayInterval ) return;
				autoplayInterval = setInterval( () => {
					nextSlide();
				}, Math.max( 1500, cfg.autoplay_speed || 3000 ) );
			};

			const stopAutoplay = () => {
				if ( autoplayInterval ) {
					clearInterval( autoplayInterval );
					autoplayInterval = null;
				}
			};

			const pauseAutoplay = () => {
				stopAutoplay();
			};

			const resumeAutoplay = () => {
				if ( isPlaying ) startAutoplay();
			};

			const onMouseEnter = () => pauseAutoplay();
			const onMouseLeave = () => resumeAutoplay();
			const onFocusIn = () => pauseAutoplay();
			const onFocusOut = () => resumeAutoplay();

			if ( cfg.autoplay ) {
				if ( cfg.pause_on_hover ) {
					this.container.addEventListener( 'mouseenter', onMouseEnter );
					this.container.addEventListener( 'mouseleave', onMouseLeave );
				}
				this.container.addEventListener( 'focusin', onFocusIn );
				this.container.addEventListener( 'focusout', onFocusOut );

				if ( playPauseBtn ) {
					playPauseBtn.addEventListener( 'click', () => {
						isPlaying = ! isPlaying;
						if ( isPlaying ) {
							playPauseBtn.setAttribute( 'aria-label', 'Pause carousel autoplay' );
							const icon = playPauseBtn.querySelector( '.dashicons' );
							if ( icon ) icon.className = 'dashicons dashicons-controls-pause';
							const sr = playPauseBtn.querySelector( '.screen-reader-text' );
							if ( sr ) sr.textContent = 'Pause autoplay';
							startAutoplay();
						} else {
							playPauseBtn.setAttribute( 'aria-label', 'Start carousel autoplay' );
							const icon = playPauseBtn.querySelector( '.dashicons' );
							if ( icon ) icon.className = 'dashicons dashicons-controls-play';
							const sr = playPauseBtn.querySelector( '.screen-reader-text' );
							if ( sr ) sr.textContent = 'Start autoplay';
							stopAutoplay();
						}
					} );
				}

				startAutoplay();
			}

			// Clean up function
			this.sliderCleanup = () => {
				stopAutoplay();
				if ( prevBtn ) prevBtn.removeEventListener( 'click', onPrevClick );
				if ( nextBtn ) nextBtn.removeEventListener( 'click', onNextClick );
				track.removeEventListener( 'keydown', onKeyDown );
				track.removeEventListener( 'scroll', onScroll );
				track.removeEventListener( 'pointerdown', onPointerDown );
				window.removeEventListener( 'pointermove', onPointerMove );
				window.removeEventListener( 'pointerup', onPointerUp );
				window.removeEventListener( 'pointercancel', onPointerCancel );
				track.removeEventListener( 'click', onClickCapture, true );
				this.container.removeEventListener( 'mouseenter', onMouseEnter );
				this.container.removeEventListener( 'mouseleave', onMouseLeave );
				this.container.removeEventListener( 'focusin', onFocusIn );
				this.container.removeEventListener( 'focusout', onFocusOut );
			};

			// Initial visibility calculation
			hideNonVisibleCards();
		}

		initVerticalReels() {
			if ( this.reelsCleanup ) {
				this.reelsCleanup();
				this.reelsCleanup = null;
			}

			const isReels = this.container.classList.contains( 'gm-layout-reels' ) || this.container.getAttribute( 'data-layout' ) === 'reels';
			if ( ! isReels ) {
				return;
			}

			const track = this.container.querySelector( '.gm-reels-track' ) || this.container.querySelector( '.gm-grid-items' );
			if ( ! track ) return;

			const prevBtn = this.container.querySelector( '.gm-reels-prev' );
			const nextBtn = this.container.querySelector( '.gm-reels-next' );
			const getSlides = () => Array.from( track.querySelectorAll( '.gm-grid-item' ) );

			const getCurrentIndex = () => {
				const slides = getSlides();
				if ( ! slides.length ) return 0;
				const trackScrollTop = track.scrollTop;
				let closestIdx = 0;
				let minDiff = Infinity;
				slides.forEach( ( slide, idx ) => {
					const diff = Math.abs( ( slide.offsetTop - track.offsetTop ) - trackScrollTop );
					if ( diff < minDiff ) {
						minDiff = diff;
						closestIdx = idx;
					}
				} );
				return closestIdx;
			};

			const scrollToSlide = ( index, smooth = true ) => {
				const slides = getSlides();
				if ( index < 0 || index >= slides.length ) return;
				const target = slides[ index ];
				if ( ! target ) return;
				const top = target.offsetTop - track.offsetTop;
				track.scrollTo( {
					top,
					behavior: smooth ? 'smooth' : 'auto',
				} );
			};

			const nextReel = () => {
				const cur = getCurrentIndex();
				const slides = getSlides();
				if ( cur < slides.length - 1 ) {
					scrollToSlide( cur + 1 );
				}
			};

			const prevReel = () => {
				const cur = getCurrentIndex();
				if ( cur > 0 ) {
					scrollToSlide( cur - 1 );
				}
			};

			if ( prevBtn ) prevBtn.addEventListener( 'click', prevReel );
			if ( nextBtn ) nextBtn.addEventListener( 'click', nextReel );

			// Restart feed handler for "All Caught Up" end slide
			const onRestartClick = ( e ) => {
				const btn = e.target.closest( '.gm-reels-restart-btn' );
				if ( ! btn ) return;
				e.preventDefault();
				scrollToSlide( 0 );
			};
			track.addEventListener( 'click', onRestartClick );

			// Update reels navigation buttons disabled states
			const updateNavState = () => {
				const slides = getSlides();
				const cur = getCurrentIndex();

				if ( prevBtn ) {
					const isStart = cur <= 0;
					prevBtn.disabled = isStart;
					prevBtn.setAttribute( 'aria-disabled', isStart ? 'true' : 'false' );
				}
				if ( nextBtn ) {
					const isEnd = cur >= slides.length - 1;
					nextBtn.disabled = isEnd;
					nextBtn.setAttribute( 'aria-disabled', isEnd ? 'true' : 'false' );
				}
			};

			// Keyboard navigation (ArrowUp / ArrowDown / PageUp / PageDown)
			const onKeyDown = ( e ) => {
				if ( e.key === 'ArrowDown' || e.key === 'PageDown' ) {
					e.preventDefault();
					nextReel();
				} else if ( e.key === 'ArrowUp' || e.key === 'PageUp' ) {
					e.preventDefault();
					prevReel();
				}
			};
			track.addEventListener( 'keydown', onKeyDown );

			// Single-active video coordinator for vertical reels
			const syncActiveVideos = () => {
				const slides = getSlides();
				const activeIdx = getCurrentIndex();

				slides.forEach( ( slide, idx ) => {
					const isActive = ( idx === activeIdx );
					slide.classList.toggle( 'is-active-reel', isActive );

					const vidWrap = slide.querySelector( '.gm-card-video-wrap' );
					if ( ! vidWrap ) return;
					const vid = vidWrap.querySelector( 'video' );

					if ( isActive ) {
						if ( vid ) {
							if ( ! vid.getAttribute( 'src' ) ) {
								vid.src = vid.getAttribute( 'data-src' ) || vidWrap.getAttribute( 'data-video-url' );
								vid.load();
							}
							if ( window.__gmReelsUnmuted ) {
								vid.muted = false;
							} else {
								vid.muted = true;
							}
							const soundBtn = vidWrap.querySelector( '.gm-video-sound-toggle' );
							if ( soundBtn ) {
								const isUnmuted = ! vid.muted;
								soundBtn.setAttribute( 'aria-pressed', isUnmuted ? 'true' : 'false' );
								soundBtn.classList.toggle( 'is-unmuted', isUnmuted );
							}
							const p = vid.play();
							if ( p && p.catch ) p.catch( () => {} );
							vidWrap.classList.add( 'is-playing' );
						}
					} else {
						if ( vid ) {
							try { vid.pause(); } catch ( e ) {}
							vidWrap.classList.remove( 'is-playing' );
						}
					}
				} );

				updateNavState();
			};

			// IntersectionObserver to accurately detect snapped reel
			let reelObserver = null;
			if ( 'IntersectionObserver' in window ) {
				reelObserver = new IntersectionObserver( ( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting && entry.intersectionRatio >= 0.55 ) {
							syncActiveVideos();
						}
					} );
				}, {
					root: track,
					threshold: [ 0.55 ],
				} );

				getSlides().forEach( ( slide ) => reelObserver.observe( slide ) );
			}

			// Scroll listener for nav buttons & infinite auto-paging
			let scrollDebounce = null;
			const onScroll = () => {
				if ( scrollDebounce ) clearTimeout( scrollDebounce );
				scrollDebounce = setTimeout( () => {
					updateNavState();

					// Infinite auto-paging within vertical track
					const isNearBottom = ( track.scrollTop + track.clientHeight ) >= ( track.scrollHeight - track.clientHeight * 1.5 );
					if ( isNearBottom && ! this.isLoading ) {
						const infiniteEl = this.container.querySelector( '.gm-pagination-infinite, .gm-load-more-btn, .gm-btn-load-more' );
						if ( infiniteEl ) {
							const nextPage = parseInt( infiniteEl.getAttribute( 'data-page' ), 10 ) || ( this.state.page + 1 );
							const maxPages = parseInt( infiniteEl.getAttribute( 'data-max-pages' ), 10 ) || 0;
							if ( ! maxPages || nextPage <= maxPages ) {
								this.state.page = nextPage;
								this.fetchResults( true );
							}
						}
					}
				}, 40 );
			};

			track.addEventListener( 'scroll', onScroll, { passive: true } );

			// Initial sync on ready
			setTimeout( () => {
				syncActiveVideos();
				if ( prevBtn ) {
					prevBtn.disabled = true;
					prevBtn.setAttribute( 'aria-disabled', 'true' );
				}
			}, 100 );

			this.reelsCleanup = () => {
				if ( prevBtn ) prevBtn.removeEventListener( 'click', prevReel );
				if ( nextBtn ) nextBtn.removeEventListener( 'click', nextReel );
				track.removeEventListener( 'click', onRestartClick );
				track.removeEventListener( 'keydown', onKeyDown );
				track.removeEventListener( 'scroll', onScroll );
				if ( reelObserver ) reelObserver.disconnect();
			};
		}

		initMasonry() {
			const isMasonry = this.container.classList.contains( 'gm-layout-masonry' ) || this.container.getAttribute( 'data-layout' ) === 'masonry';
			if ( ! isMasonry || ! this.itemsContainer ) return;

			this.layoutMasonry();

			if ( ! this.masonryResizeObserver && typeof ResizeObserver !== 'undefined' ) {
				this.masonryResizeObserver = new ResizeObserver( () => this.layoutMasonry() );
				this.masonryResizeObserver.observe( this.itemsContainer );
			}
			this.itemsContainer.querySelectorAll( 'img' ).forEach( ( img ) => {
				if ( ! img.complete && ! img.dataset.gmMasonryBound ) {
					img.dataset.gmMasonryBound = '1';
					img.addEventListener( 'load', () => this.layoutMasonry(), { once: true } );
				}
			} );
		}

		layoutMasonry() {
			const isMasonry = this.container.classList.contains( 'gm-layout-masonry' ) || this.container.getAttribute( 'data-layout' ) === 'masonry';
			if ( ! isMasonry || ! this.itemsContainer ) return;

			const computed = window.getComputedStyle( this.container );
			const rowGap = parseInt( computed.getPropertyValue( '--gm-row-gap' ), 10 ) || 24;

			const items = Array.from( this.itemsContainer.querySelectorAll( '.gm-grid-item' ) );
			items.forEach( ( item ) => {
				item.style.marginBottom = rowGap + 'px';
				item.style.gridRowEnd = 'auto';
			} );

			window.requestAnimationFrame( () => {
				items.forEach( ( item ) => {
					const card = item.querySelector( '.gm-card' ) || item;
					const height = Math.ceil( card.getBoundingClientRect().height );
					const span = Math.max( 1, height + rowGap );
					item.style.gridRowEnd = 'span ' + span;
				} );
			} );

			this.itemsContainer.querySelectorAll( 'img' ).forEach( ( img ) => {
				if ( ! img.complete && ! img.dataset.gmMasonryBound ) {
					img.dataset.gmMasonryBound = '1';
					img.addEventListener( 'load', () => this.layoutMasonry(), { once: true } );
				}
			} );
		}
	}

	// Auto-initialize all grids on page load
	function initAllGrids() {
		document.querySelectorAll( '.gm-v2-grid' ).forEach( ( el ) => {
			if ( ! activeGrids.has( el ) ) {
				activeGrids.set( el, new GridInstance( el ) );
			} else {
				// Re-init slider, reels, or masonry if already existing
				const inst = activeGrids.get( el );
				if ( inst ) {
					if ( inst.initSlider ) inst.initSlider();
					if ( inst.initVerticalReels ) inst.initVerticalReels();
					if ( inst.layoutMasonry ) inst.layoutMasonry();
				}
			}
		} );
	}

	// Expose for builder preview
	window.gridmasterInitAllSliders = initAllGrids;
	window.gridmasterInitAllReels = initAllGrids;
	window.gridmasterInit = initAllGrids;

	// Delegated Copy Link handler for social share
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-gm-copy="true"]' );
		if ( ! btn ) return;
		e.preventDefault();

		const url = btn.getAttribute( 'data-url' ) || window.location.href;
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( () => {
				const originalTitle = btn.getAttribute( 'title' ) || '';
				btn.setAttribute( 'title', 'Copied to clipboard!' );
				btn.classList.add( 'gm-copied' );
				setTimeout( () => {
					btn.setAttribute( 'title', originalTitle );
					btn.classList.remove( 'gm-copied' );
				}, 2000 );
			} );
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllGrids );
	} else {
		initAllGrids();
	}
} )();

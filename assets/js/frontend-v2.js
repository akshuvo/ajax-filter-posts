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
			this.initSlider();
			this.initVerticalReels();
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
				}

				this.state.page = 1;
				this.syncUrl();
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
							this.container.appendChild( newPagination );
						}

						// Re-observe if infinite scroll
						this.initInfiniteScroll();
						this.initSlider();
						this.initVerticalReels();

						// Dispatch loaded event for Pro blocks and external widgets
						document.dispatchEvent( new CustomEvent( 'gridmaster_loaded_posts', { detail: { gridId: this.gridId } } ) );
						if ( window.jQuery ) {
							window.jQuery( document ).trigger( 'gridmaster_loaded_posts', [ this.gridId ] );
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
			try {
				const url = new URL( window.location.href );
				let changed = false;

				if ( this.state.search ) {
					url.searchParams.set( 'gm_q', this.state.search );
					changed = true;
				} else if ( url.searchParams.has( 'gm_q' ) ) {
					url.searchParams.delete( 'gm_q' );
					changed = true;
				}

				Object.keys( this.state.facets ).forEach( ( tax ) => {
					const vals = this.state.facets[ tax ];
					if ( Array.isArray( vals ) && vals.length > 0 ) {
						url.searchParams.set( 'gm_' + tax, vals.join( ',' ) );
						changed = true;
					} else if ( url.searchParams.has( 'gm_' + tax ) ) {
						url.searchParams.delete( 'gm_' + tax );
						changed = true;
					}
				} );

				for ( const param of Array.from( url.searchParams.keys() ) ) {
					if ( param.startsWith( 'gm_' ) && param !== 'gm_q' && param !== 'gm_paged' ) {
						const tax = param.replace( 'gm_', '' );
						if ( ! this.state.facets[ tax ] || this.state.facets[ tax ].length === 0 ) {
							url.searchParams.delete( param );
							changed = true;
						}
					}
				}

				if ( this.state.page > 1 ) {
					url.searchParams.set( 'gm_paged', this.state.page );
					changed = true;
				} else if ( url.searchParams.has( 'gm_paged' ) ) {
					url.searchParams.delete( 'gm_paged' );
					changed = true;
				}

				if ( changed ) {
					window.history.replaceState( null, '', url.toString() );
				}
			} catch ( e ) {}
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
				}, 60 );
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
	}

	// Auto-initialize all grids on page load
	function initAllGrids() {
		document.querySelectorAll( '.gm-v2-grid' ).forEach( ( el ) => {
			if ( ! activeGrids.has( el ) ) {
				activeGrids.set( el, new GridInstance( el ) );
			} else {
				// Re-init slider or reels if already existing
				const inst = activeGrids.get( el );
				if ( inst ) {
					if ( inst.initSlider ) inst.initSlider();
					if ( inst.initVerticalReels ) inst.initVerticalReels();
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

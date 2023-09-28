jQuery(document).ready(function($) {

	// Post loading
	jQuery(document).on('change', '.gm-taxonomy-item input', function(){
        let $this = jQuery(this);
		var term_id = $this.attr('data_id');   		
 
		// if( !$this.hasClass('active') ) {
        //     $this.addClass('active').siblings().removeClass('active');
            
            
        // }

        // Load Grid
        asr_ajax_get_postdata('', $this);

	});

    // Pagination
    jQuery( document ).on('click', '.am_posts_navigation_init a.page-numbers, .am-post-grid-load-more', function(e){
        e.preventDefault();

        var term_id = "-1";
        let $this = jQuery(this);

        var paged = $this.text();
        var loadMore = false;

        // Try infinity loading
        if ( $this.hasClass('am-post-grid-load-more') ) {
            paged = $this.data('next');
            loadMore = true;
        } 
        else if ( $this.hasClass('next') ) {
            let currentPage = $this.closest('.am_posts_navigation_init').find('.page-numbers.current').text();
            paged = parseInt(currentPage) + 1;
        }
        else if ( $this.hasClass('prev') ) {
            let currentPage = $this.closest('.am_posts_navigation_init').find('.page-numbers.current').text();
            paged = parseInt(currentPage) - 1;
        }

        var theSelector = $this.closest('.am_ajax_post_grid_wrap').find('.asr_texonomy');
        var activeSelector = $this.closest('.am_ajax_post_grid_wrap').find('.asr_texonomy.active');

        if( activeSelector.length > 0 ){
            term_id = activeSelector.attr('data_id');
        } else {
            activeSelector = theSelector;
        }

        // Load Post Grids
        asr_ajax_get_postdata('-1', $this, paged, loadMore);

    });

    // Uncheck other checkboxes
    jQuery(document).on('change', '.gm-taxonomy-item.gm-taxonomy-all input', function(e){
        let $this = jQuery(this);
        
        // Is checked
        if ( $this.is(':checked') ) {
            $this.closest('.gm-taxonomy-item').siblings().find('input').prop('checked', false);
        }

    });

    // Uncheck gm-taxonomy-all checkbox if other checkbox is checked
    jQuery(document).on('change', '.gm-taxonomy-item:not(.gm-taxonomy-all) input', function(e){
        let $this = jQuery(this);
        let $all = $this.closest('.gm-taxonomy-item').siblings('.gm-taxonomy-all').find('input');

        // Is checked
        if ( $this.is(':checked') ) {
            $all.prop('checked', false);
        }

    });

    // Set scroll flag
    var flag = false;

	//ajax filter function
	function asr_ajax_get_postdata(term_ID, selector, paged, loadMore){
        let $wrapper = jQuery(selector).closest('.am_ajax_post_grid_wrap');

        let getLayout = $wrapper.find(".asr-filter-div").attr("data-layout");
        let pagination_type = $wrapper.attr("data-pagination_type");
        let jsonData = $wrapper.attr('data-am_ajax_post_grid');
        let taxInput = $wrapper.find(".gm-taxonomy-item input:checked").serialize();

        let $args = JSON.parse(jsonData);

        let data = {
            action: 'asr_filter_posts',
            asr_ajax_nonce: asr_ajax_params.asr_ajax_nonce,
            term_ID: term_ID,
            taxInput: taxInput,
            layout: (getLayout) ? getLayout : "1",
            argsArray: $args,
            // jsonData: JSON.stringify( $args ),
            pagination_type: pagination_type,
            loadMore: loadMore,
        }

        if( paged ){
            data['paged'] = paged;
        }

		$.ajax({
			type: 'post',
			url: asr_ajax_params.asr_ajax_url,
			data: data,
            // dataType: 'json',
			beforeSend: function(data){
				
                if ( loadMore ) {
                    // Loading Animation Start
                    $wrapper.find('.am-post-grid-load-more').addClass('loading');
                    flag = true;
                } else {
                    $wrapper.find('.asr-loader').show();
                }
			},
			complete: function(data){
				
                if ( loadMore ) {
                    // Loading Animation End
                    $wrapper.find('.am-post-grid-load-more').removeClass('loading');
                } else {
                    $wrapper.find('.asr-loader').hide();
                }
			},
			success: function(data){
				
                if ( loadMore ) {

                    var newPosts = jQuery('.am_post_grid', data).html();
                    var newPagination = jQuery('.am_posts_navigation', data).html();

                    $wrapper.find('.asrafp-filter-result .am_post_grid').append(newPosts);
                    $wrapper.find('.asrafp-filter-result .am_posts_navigation').html(newPagination);

                } else {

                    $wrapper.find('.asrafp-filter-result').hide().html(data).fadeIn(0, function() {
                        //jQuery(this).html(data).fadeIn(300);
                    });
                }

                flag = false;
                jQuery( window ).trigger('scroll');

                // Animation
                if( $args.animation == "true" ){
                    $wrapper.find('.am_grid_col').slideDown();
                }
                
			},
			error: function(data){
				alert('Cannot load the Post Grid.');
			},

		});
	}

    // Initial Custom Trigger
    jQuery(document).on('am_ajax_post_grid_init', function(){
        
        jQuery('.am_ajax_post_grid_wrap').each(function(i,el){                
            var amPGdata = jQuery(this).data('am_ajax_post_grid');
            if( amPGdata && amPGdata.initial ){
                asr_ajax_get_postdata(amPGdata.initial, jQuery(this).find('.asr-ajax-container'));
            }
        });
    });

    // Function to check if an element is in the viewport
    function isElementInViewport(el, pixelsBeforeInview = 0) {
        var rect = el.getBoundingClientRect();
        return (
            rect.top >= -pixelsBeforeInview &&
            rect.left >= -pixelsBeforeInview &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) + pixelsBeforeInview &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth) + pixelsBeforeInview
        );
    }

    // Handle Infinite scroll
    jQuery( window ).on('scroll', function(e){
        jQuery('.infinite_scroll.am-post-grid-load-more').each(function(i, el) {
            // Check if the element is in the viewport
            if (flag === false && isElementInViewport(el, 1000)) {
                console.log('inview');
                // Trigger a click event on the button
                jQuery(el).trigger('click');
            } else {
                console.log('outview');
            }
        });

    });

});

// Load Each Grid on Page Load
window.addEventListener('load', (event) => {
    // Trigger slide down animation
    jQuery('.am_grid_col').slideDown();

    // Trigger scroll event
    jQuery(window).trigger('scroll');
});
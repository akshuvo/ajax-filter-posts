jQuery(document).ready(function($) {
    	
	// Post loading
	jQuery(document).on('click', '.asr_texonomy', function(){
        let $this = jQuery(this);
		var term_id = $this.attr('data_id');   		
 
		if( !$this.hasClass('active') ) {
            $this.addClass('active').siblings().removeClass('active');
            
            // Load Grid
            asr_ajax_get_postdata(term_id, $this);
        }

	});

    // Pagination
    jQuery( document ).on('click', '#am_posts_navigation_init a.page-numbers, .am-post-grid-load-more', function(e){
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

        var theSelector = $this.closest('.am_ajax_post_grid_wrap').find('.asr_texonomy');
        var activeSelector = $this.closest('.am_ajax_post_grid_wrap').find('.asr_texonomy.active');

        if( activeSelector.length > 0 ){
            term_id = activeSelector.attr('data_id');
        } else {
            activeSelector = theSelector;
        }

        // Load Post Grids
        asr_ajax_get_postdata(term_id, activeSelector, paged, loadMore);

    });

    // Set scroll flag
    var flag = false;

	//ajax filter function
	function asr_ajax_get_postdata(term_ID, selector, paged, loadMore){

        var getLayout = jQuery(selector).closest('.am_ajax_post_grid_wrap').find(".asr-filter-div").attr("data-layout");
        var pagination_type = jQuery(selector).closest('.am_ajax_post_grid_wrap').attr("data-pagination_type");
        var jsonData = jQuery(selector).closest('.am_ajax_post_grid_wrap').attr('data-am_ajax_post_grid');

        var $args = JSON.parse(jsonData);
        
        var data = {
            action: 'asr_filter_posts',
            asr_ajax_nonce: asr_ajax_params.asr_ajax_nonce,
            term_ID: term_ID,
            layout: (getLayout) ? getLayout : "1",
            jsonData: jsonData,
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
			beforeSend: function(data){
				
                if ( loadMore ) {
                    // Loading Animation Start
                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.am-post-grid-load-more').addClass('loading');
                    flag = true;
                } else {
                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.asr-loader').show();
                }
			},
			complete: function(data){
				
                if ( loadMore ) {
                    // Loading Animation End
                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.am-post-grid-load-more').removeClass('loading');
                } else {
                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.asr-loader').hide();
                }
			},
			success: function(data){
				
                if ( loadMore ) {

                    var newPosts = jQuery('.am_post_grid', data).html();
                    var newPagination = jQuery('.am_posts_navigation', data).html();

                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.asrafp-filter-result .am_post_grid').append(newPosts);
                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.asrafp-filter-result .am_posts_navigation').html(newPagination);

                } else {

                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.asrafp-filter-result').hide().html(data).fadeIn(0, function() {
                        //jQuery(this).html(data).fadeIn(300);
                    });
                }

                flag = false;
                jQuery( window ).trigger('scroll');

                // Animation
                if( $args.animation == "true" ){
                    jQuery(selector).closest('.am_ajax_post_grid_wrap').find('.am_grid_col').slideDown();
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

    // Handle Infinite scroll
    jQuery( window ).on('scroll', function(e){
        jQuery('.infinite_scroll.am-post-grid-load-more').each(function(i,el){

            var $this = jQuery(this);

            var H = jQuery(window).height(),
                r = el.getBoundingClientRect(),
                t=r.top,
                b=r.bottom;

            var tAdj = parseInt(t-(H/2));

            if ( flag === false && (H >= tAdj) ) {
                //console.log( 'inview' );
                $this.trigger('click');
            } else {
                //console.log( 'outview' );
            }
        });
    });

});

// Load Each Grid on Page Load
window.addEventListener('load', (event) => {
    // jQuery(document).trigger('am_ajax_post_grid_init');
    // console.log('on load triggered')
});
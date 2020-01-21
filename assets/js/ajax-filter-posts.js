(function($) {
    'use strict';

    jQuery(document).ready(function() {
    	
    	
    	$('.asr_texonomy').on('click',function(){
    		var term_id = $(this).attr('data_id');
    		

    		if(!$(this).hasClass('active')){
                $(this).addClass('active').siblings().removeClass('active');
                //console.log(term_id);
                asr_ajax_get_postdata(term_id, $(this));
            }

    	});

    	//ajax filter function
    	function asr_ajax_get_postdata(term_ID, selector){
            var getLayout = $(selector).closest('.am_ajax_post_grid_wrap').find(".asr-filter-div").attr("data-layout");
            var data = {
                action: 'asr_filter_posts',
                asr_ajax_nonce: asr_ajax_params.asr_ajax_nonce,
                term_ID: term_ID,
                layout: (getLayout) ? getLayout : "1",
                jsonData: $(selector).closest('.am_ajax_post_grid_wrap').attr('data-am_ajax_post_grid'),
            }

            console.log(data);

    		$.ajax({
    			type: 'post',
    			url: asr_ajax_params.asr_ajax_url,
    			data: data,
    			beforeSend: function(data){
    				$(selector).closest('.am_ajax_post_grid_wrap').find('.asr-loader').show();
    			},
    			complete: function(data){
    				$(selector).closest('.am_ajax_post_grid_wrap').find('.asr-loader').hide();
    			},    			
    			success: function(data){
    				$(selector).closest('.am_ajax_post_grid_wrap').find('.asrafp-filter-result').hide().html(data).fadeIn(0, function() {
						//$(this).html(data).fadeIn(300);
					});
    			},
    			error: function(data){
    				console.log(data);
    			},

    		});
    	}

        // Initial Custom Trigger
        $(document).on('am_ajax_post_grid_init', function(){
            
            $('.am_ajax_post_grid_wrap').each(function(i,el){                
                var amPGdata = $(this).data('am_ajax_post_grid');
                if(amPGdata && amPGdata.initial){
                    asr_ajax_get_postdata(amPGdata.initial,$(this).find('.asr-ajax-container'));
                }
            });
        });


    });

    $(window).load(function(){
        $(document).trigger('am_ajax_post_grid_init');
        
    });

})(jQuery);

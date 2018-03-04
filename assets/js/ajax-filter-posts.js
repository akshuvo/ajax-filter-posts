(function($) {
    'use strict';

    jQuery(document).ready(function() {
    	$('.asr_texonomy').on('click',function(){
    		var term_id = $(this).attr('data_id');
    		$(this).addClass('active').siblings().removeClass('active');

    		//console.log(term_id);
    		asr_ajax_get_postdata(term_id);

    	});

    	function asr_ajax_get_postdata(term_ID){
    		$.ajax({
    			type: 'post',
    			url: asr_ajax_params.asr_ajax_url,
    			data: {
    				action: 'asr_filter_posts',
    				asr_ajax_nonce: asr_ajax_params.asr_ajax_nonce,
    				term_ID: term_ID,
    			},
    			beforeSend: function(data){
    				$('.asr-loader').show();
    			},
    			complete: function(data){
    				$('.asr-loader').hide();
    			},    			
    			success: function(data){
    				$('.asrafp-filter-result').fadeOut(300, function() {
						$(this).html(data).fadeIn(300);
					});
    			},
    			error: function(data){
    				console.log(data);
    			},

    		});
    	}


    });

})(jQuery);

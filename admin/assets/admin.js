/**
 * GridMaster Admin JS
 * @package GridMaster
 * @since 1.0.0
 * @version 1.0.0
 */
jQuery(document).ready(function($) {
    // Slide Toggle
    jQuery( document ).on( 'click', '.gm-slide-toggle .postbox-header', function(e) {
        jQuery(this).parent().toggleClass('closed').find('.inside').slideToggle('fast');
    });
});
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

    // Shortcode Generator
    jQuery( document ).on( 'change', '#gm-shortcode-generator select, #gm-shortcode-generator input', function(e) {
        let $fields = jQuery("#gm-shortcode-generator").serializeArray()

        let shortCode = '[gridmaster';
        jQuery.each($fields, function(i, field) {
            if (field.value) {
                shortCode += ' ' + field.name + '="' + field.value + '"';
            }
        } );
        
        shortCode += ']';

        // Update Shortcode
        jQuery(".gm-copy-inp").val(shortCode);
    });

    // Copy Shortcode
    jQuery( document ).on( 'click', '.gm-copy-btn', function(e) {
        jQuery(".gm-copy-inp").select();
        document.execCommand("copy");
    } );


});
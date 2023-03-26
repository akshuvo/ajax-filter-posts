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

    // Device Panel Toggle
    jQuery( document ).on( 'click', '.gridmaster-responsive-fields-selected-devices', function(e) {
        jQuery(this).parent().toggleClass('open').find('.gridmaster-responsive-fields-devices').slideToggle('fast');
    });

    // Select Device
    jQuery( document ).on( 'click', '.gridmaster-responsive-fields-device', function(e) {
        let $this = jQuery(this);
        let device = $this.attr('data-device');
        $this.closest('.gridmaster-responsive-fields').find('.gridmaster-responsive-fields-content .responsive-field').removeClass('hidden').hide();
        $this.closest('.gridmaster-responsive-fields').find('.gridmaster-input-' + device).removeClass('hidden').fadeIn('fast');
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

        // Update Preview
        //?gm_shortcode_preview=1&shortcode='.urlencode( '[gridmaster]' ) )
        jQuery("#gm-iframe").attr('src', gridmaster_params.home_url + '?gm_shortcode_preview=1&shortcode=' + encodeURIComponent(shortCode) );
    });

    // Copy Shortcode
    jQuery( document ).on( 'click', '.gm-copy-btn', function(e) {
        jQuery(".gm-copy-inp").select();
        document.execCommand("copy");
    } );


});
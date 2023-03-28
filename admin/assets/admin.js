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

    // Close All Panels
    let closeAllPanels = () => {
        jQuery(document).find('.gridmaster-responsive-fields-devices-wrap.open').removeClass('open').find('.gridmaster-responsive-fields-devices').hide();
    }

    // Device Panel Toggle
    jQuery( document ).on( 'click', '.gridmaster-responsive-fields-selected-devices', function(e) {
        // Close All Panels
        closeAllPanels();
        // Toggle Current Panel
        jQuery(this).parent().toggleClass('open').find('.gridmaster-responsive-fields-devices').slideToggle('fast');
    });

    // Select Device
    jQuery( document ).on( 'click', '.gridmaster-responsive-fields-device', function(e) {
        let $this = jQuery(this);
        let device = $this.attr('data-device');

        // Remove Selected Class
        $this.parent().find('.gridmaster-responsive-fields-device').removeClass('selected');
        // Hide All Devices
        // $this.closest('.gridmaster-responsive-fields').find('.gridmaster-responsive-fields-content .responsive-field').removeClass('hidden').hide();
        jQuery('.gridmaster-responsive-fields-content .responsive-field').removeClass('hidden').hide();
        // Select Device
        $this.addClass('selected').closest('.gridmaster-responsive-fields').find('.gridmaster-input-' + device).removeClass('hidden').fadeIn('fast');
        jQuery('.gridmaster-responsive-fields').find('.gridmaster-input-' + device).removeClass('hidden').fadeIn('fast');
        // Show Current Device
        // $this.closest('.gridmaster-responsive-fields').find('.gridmaster-responsive-fields-selected-devices').html($this.html()).click();
        jQuery('.gridmaster-responsive-fields-selected-devices').html($this.html());
        // $this.closest('.gridmaster-responsive-fields').find('.gridmaster-responsive-fields-selected-devices').click();
        // Close All Panels
        closeAllPanels();
    });

    // Shortcode Generator
    jQuery( document ).on( 'change', '#gm-shortcode-generator select, #gm-shortcode-generator input', function(e) {
        let $fields = jQuery("#gm-shortcode-generator").serializeArray()
        let responsiveFields = [];

        let shortCode = '[gridmaster';
        jQuery.each($fields, function(i, field) {
            let fieldName = field.name;
            let fieldVal = field.value;
            let $thisField = jQuery('#gm-shortcode-generator [name="' + fieldName + '"]');
            
            // Responsive Fields
            if ( $thisField.hasClass('responsive-field') ) {
                let dataName = $thisField.attr('data-name');
                responsiveFields.push(dataName);
                return;
            }

            if ( fieldVal ) {
                shortCode += ' ' + fieldName + '="' + fieldVal + '"';
            }
        } );

        // Responsive Fields Make Unique
        responsiveFields = [...new Set(responsiveFields)];

        // Responsive Fields
        jQuery.each(responsiveFields, function(i, resFieldId) {
            let $thisField = jQuery('#gm-shortcode-generator [data-name="' + resFieldId + '"]');
            let fieldValArr = $thisField.serializeArray()

            // Value Object
            let valObj = {};

            jQuery.each(fieldValArr, function(i, field) {
                let fieldName = field.name;
                let fieldVal = field.value;
                // Get Device
                let device = fieldName.replace(resFieldId + '[', '').replace(']', '');
                // Push to Object
                valObj[device] = fieldVal;
            } );


            // Object to json string
            let valObjStr = JSON.stringify(valObj);

            if ( valObjStr ) {
                // shortCode += ' ' + resFieldId + '="' + valObjStr + '"';
                shortCode += ' ' + resFieldId + '=\'' + valObjStr + '\'';
            }

       
        } );
        
        
        shortCode += ']';

        // Update Shortcode
        jQuery(".gm-copy-inp").val(shortCode);
        console.log(shortCode);

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
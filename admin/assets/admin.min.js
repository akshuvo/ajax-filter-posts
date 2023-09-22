/**
 * GridMaster Admin JS
 * @package GridMaster
 * @since 1.0.0
 * @version 1.0.0
 */
jQuery(document).ready(function($) {

    // Close All Panels
    let closeAllPanels = () => {
        jQuery(document).find('.gridmaster-responsive-fields-devices-wrap.open').removeClass('open').find('.gridmaster-responsive-fields-devices').hide();
    }

    let breakpoints = gridmaster_params.breakpoints;

    // Slide Toggle
    jQuery( document ).on( 'click', '.gm-slide-toggle .postbox-header', function(e) {
        jQuery(this).parent().toggleClass('closed').find('.inside').slideToggle('fast');
    });

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
        jQuery('.gridmaster-responsive-fields-device').removeClass('selected');
        
        // Hide All Devices
        jQuery('.gridmaster-responsive-fields-content .responsive-field').removeClass('hidden').hide();
        
        // Select Device
        jQuery('.gridmaster-responsive-fields-device[data-device="' + device + '"]').addClass('selected');
        jQuery('.gridmaster-responsive-fields').find('.gridmaster-input-' + device).removeClass('hidden').fadeIn('fast');
        
        // Show Current Device
        jQuery('.gridmaster-responsive-fields-selected-devices').html($this.html());

        // Close All Panels
        closeAllPanels();

        // Update Preview iframe width
        let iframeWidth = breakpoints[device].value;
        // Add padding
        iframeWidth = parseInt(iframeWidth) + 32;
        jQuery("#gm-iframe").css('width', iframeWidth + 'px');

    });

    // Taxonomy on Change
    let changeTaxonomyTerms = () => {
        let $val = jQuery('#taxonomy').val();

        // Check If Has Pro
        let inpDisabled = '';
        if( !gridmaster_params.has_pro && $val != 'category' ) {
            jQuery('#terms_field').addClass('gm-pro-field');
            inpDisabled = ' disabled ';
        } else {
            jQuery('#terms_field').removeClass('gm-pro-field');
        }

        // Get Terms
        let terms = window.gm_terms[$val];
        let html = '';
        let options = '<option value="">All - Default</option><option value="auto">Auto Select</option>';

        if ( terms && terms.length != 0 ) {
            jQuery.each(terms, function(term_id, term_name) {
                html += '<span class="gm-checkbox-wrapper"><input type="checkbox" class="input-radio " value="' + term_id + '" name="terms" id="terms_' + term_id + '" '+inpDisabled+'><label for="terms_' + term_id + '" class="radio gm-field-label">' + term_name + '</label></span>';
                options += '<option value="' + term_id + '">' + term_name + '</option>';
            } );
        } else {
            html = '<i>No Terms Found</i>';
        }

        jQuery('#terms_field .gridmaster-input-wrapper').html(html);
        jQuery('#initial_term').html(options);
    }
    

    // Shortcode Generator
    jQuery( document ).on( 'change', '#gm-shortcode-generator select, #gm-shortcode-generator input:not(.skip-reload)', function(e) {
        let $this = jQuery(this);

        // Change Taxonomy Terms
        if ( $this.attr('id') == 'taxonomy' ) {
            changeTaxonomyTerms();
        }

        let $fields = jQuery("#gm-shortcode-generator").serializeArray()
        let responsiveFields = [];
        let terms = [];

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

            // If has value
            if ( fieldVal ) {

                // Terms
                if ( fieldName == 'terms' ) {
                    terms.push(fieldVal);
                    return;
                }

                // Push to Shortcode
                shortCode += ' ' + fieldName + '="' + fieldVal + '"';
            }
        } );

        // Terms
        if ( terms.length ) {
            shortCode += ' terms="' + terms.join(',') + '"';
        }

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

        jQuery(".gm-iframe-wrap").addClass('loading');

        // Update Preview
        //?gm_shortcode_preview=1&shortcode='.urlencode( '[gridmaster]' ) )
        jQuery("#gm-iframe").attr('src', gridmaster_params.home_url + '?gm_shortcode_preview=1&shortcode=' + encodeURIComponent(shortCode) );
    });

    // Copy Shortcode
    jQuery( document ).on( 'click', '.gm-copy-btn', function(e) {
        jQuery(".gm-copy-inp").select();
        document.execCommand("copy");
    } );

    // Change Filter Style
    jQuery(document).on( 'change', '#filter_style', function(e) {
        let $val = jQuery(this).val();
        // Hide
        jQuery('.filter-demo-link-button').hide().html('');
        // Add Link if exists
        if(gridmaster_params.filter_demo_links[$val]){
            let link = `<a href="${gridmaster_params.demo_link + gridmaster_params.filter_demo_links[$val]}" target="_blank" class="align-items-center button button-secondary d-inline-flex"><span class="me-2 dashicons dashicons-external"></span>View Demo</a>`;
            jQuery('.filter-demo-link-button').html( link ).fadeIn('fast')
        }
    } );

    // Change Grid Style
    jQuery(document).on( 'change', '#grid_style', function(e) {
        let $val = jQuery(this).val();
        // Hide
        jQuery('.grid-demo-link-button').hide().html('');
        // Add Link if exists
        if(gridmaster_params.grid_demo_links[$val]){
            let link = `<a href="${gridmaster_params.demo_link + gridmaster_params.grid_demo_links[$val]}" target="_blank" class="align-items-center button button-secondary d-inline-flex"><span class="me-2 dashicons dashicons-external"></span>View Demo</a>`;
            jQuery('.grid-demo-link-button').html( link ).fadeIn('fast')
        }
    } );

    // Change Image Size
    jQuery(document).on( 'change', '#grid_image_size', function(e) {
        let $val = jQuery(this).val();
        jQuery('.show-if-image-size-custom').hide();
        jQuery('.show-if-image-size-' + $val ).fadeIn('fast');
    } );

    // Change Image Size
    jQuery(document).on( 'change', '[name="link_thumbnail"]', function(e) {
        let $val = jQuery(this).val();
        console.log($val);
        jQuery('.show-if-link_thumbnail-yes').hide();
        jQuery('.show-if-link_thumbnail-' + $val ).fadeIn('fast');
    } );

    // Notice Timeout
    let noticeTimeout = false;
    
    // Ajax form submit
    jQuery( document ).on( 'submit', '.gm-ajax-form', function(e) {
        e.preventDefault();
        let $form = jQuery(this);
        let $data = $form.serialize();
        
        // Disable Button
        $form.find('[type="submit"]').attr('disabled', 'disabled');

        // Spinner
        $form.find('.spinner').addClass('is-active');

        // Ajax Response 
        $form.find('.gm-ajax-response').html('');

        // Clear Timeout
        if ( noticeTimeout ) {
            clearTimeout(noticeTimeout);
        }

        // License Form Submit
        // jQuery('.lmfwppt-license-submit-btn').trigger('click');

        // Ajax
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: $data,
            dataType: 'json',
            success: function (response) {
                
                // Enable Button
                $form.find('[type="submit"]').removeAttr('disabled');

                // Success
                if ( response.success ) {
                    $form.find('.gm-ajax-response').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                } else {
                    $form.find('.gm-ajax-response').html('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                }
              
            },
            error: function (response) {
              
                // Enable Button
                $form.find('[type="submit"]').removeAttr('disabled');

                // Error
                $form.find('.gm-ajax-response').html('<div class="notice notice-error is-dismissible"><p>Something went wrong. Please try again.</p></div>');
                
            },
            complete: function (response) { 
                // Spinner
                $form.find('.spinner').removeClass('is-active');
                jQuery(document).trigger( 'wp-updates-notice-added' );

                // Hide Notice after 5 seconds
                noticeTimeout = setTimeout(function() {
                    $form.find('.gm-ajax-response .notice').fadeOut('fast');
                } , 5000);
            }

        });
    } );

    // Block Pro Features
    if( !gridmaster_params.has_pro ) {
        jQuery('.gm-pro-inp-disable input').attr('disabled', 'disabled');
    }

});
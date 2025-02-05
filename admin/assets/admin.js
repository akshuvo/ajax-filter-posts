/**
 * GridMaster Admin JS
 * @package GridMaster
 * @since 1.0.0
 * @version 1.0.0
 */
jQuery(document).ready(function ($) {

	// Close All Panels
	let closeAllPanels = () => {
		jQuery(document).find('.gridmaster-responsive-fields-devices-wrap.open').removeClass('open').find('.gridmaster-responsive-fields-devices').hide();
	}

	let breakpoints = gridmaster_params.breakpoints;

	// Slide Toggle
	jQuery(document).on('click', '.gm-slide-toggle .postbox-header', function (e) {
		jQuery(this).parent().toggleClass('closed').find('.inside').slideToggle('fast');
	});

	// Device Panel Toggle
	jQuery(document).on('click', '.gridmaster-responsive-fields-selected-devices', function (e) {
		// Close All Panels
		closeAllPanels();
		// Toggle Current Panel
		jQuery(this).parent().toggleClass('open').find('.gridmaster-responsive-fields-devices').slideToggle('fast');
	});

	// Select Device
	jQuery(document).on('click', '.gridmaster-responsive-fields-device', function (e) {
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
		if (!gridmaster_params.has_pro && $val != 'category') {
			jQuery('#terms_field').addClass('gm-pro-field');
			inpDisabled = ' disabled ';
		} else {
			jQuery('#terms_field').removeClass('gm-pro-field');
		}

		// Get Terms
		let terms = window.gm_terms[$val];
		let html = '';
		let options = '<option value="">All - Default</option><option value="auto">Auto Select</option>';

		if (terms && terms.length != 0) {
			jQuery.each(terms, function (term_id, term_name) {
				html += '<span class="gm-checkbox-wrapper"><input type="checkbox" class="input-radio " value="' + term_id + '" name="terms" id="terms_' + term_id + '" ' + inpDisabled + '><label for="terms_' + term_id + '" class="radio gm-field-label">' + term_name + '</label></span>';
				options += '<option value="' + term_id + '">' + term_name + '</option>';
			});
		} else {
			html = '<i>No Terms Found</i>';
		}

		jQuery('#terms_field .gridmaster-input-wrapper').html(html);
		jQuery('#initial_term').html(options);
	}


	// Shortcode Generator
	jQuery(document).on('change', '#gm-shortcode-generator select, #gm-shortcode-generator input:not(.skip-reload)', function (e) {
		let $this = jQuery(this);

		// Change Taxonomy Terms
		if ($this.attr('id') == 'taxonomy') {
			changeTaxonomyTerms();
		}

		let $fields = jQuery("#gm-shortcode-generator").serializeArray()
		let responsiveFields = [];
		let terms = [];
		let sliderOptions = {};


		let shortCode = '[gridmaster';
		jQuery.each($fields, function (i, field) {
			let fieldName = field.name;
			let fieldVal = field.value;
			let $thisField = jQuery('#gm-shortcode-generator [name="' + fieldName + '"]');

			// Ignore Fields
			if (fieldName === 'id' || fieldName === 'title' || $thisField.hasClass('gm-ignore-field')) {
				return;
			}

			// Responsive Fields
			if ($thisField.hasClass('responsive-field')) {
				let dataName = $thisField.attr('data-name');
				responsiveFields.push(dataName);
				return;
			}

			// If has value
			if (fieldVal) {

				// Terms
				if (fieldName == 'terms') {
					terms.push(fieldVal);
					return;
				} else if (fieldName.startsWith('slider_')) {
					// sliderOptions.push({fieldName:fieldVal});
					sliderOptions[fieldName.substring(7)] = fieldVal;
					return;
				}

				// Push to Shortcode
				shortCode += ' ' + fieldName + '="' + fieldVal + '"';
			}
		});

		// Terms
		if (terms.length) {
			shortCode += ' terms="' + terms.join(',') + '"';
		}

		// Responsive Fields Make Unique
		responsiveFields = [...new Set(responsiveFields)];

		// Responsive Fields
		jQuery.each(responsiveFields, function (i, resFieldId) {
			let $thisField = jQuery('#gm-shortcode-generator [data-name="' + resFieldId + '"]');
			let fieldValArr = $thisField.serializeArray()

			// Value Object
			let valObj = {};

			jQuery.each(fieldValArr, function (i, field) {
				let fieldName = field.name;
				let fieldVal = field.value;
				// Get Device
				let device = fieldName.replace(resFieldId + '[', '').replace(']', '');
				// Push to Object
				valObj[device] = fieldVal;
			});


			// Object to json string
			let valObjStr = JSON.stringify(valObj);

			// Slider Options
			if (resFieldId.startsWith('slider_')) {
				sliderOptions[resFieldId.substring(7)] = valObj;
				return;
			}

			if (valObjStr) {
				// shortCode += ' ' + resFieldId + '="' + valObjStr + '"';
				shortCode += ' ' + resFieldId + '=\'' + valObjStr + '\'';
			}

		});

		// Slider Options
		shortCode += ' slider_args=\'' + JSON.stringify(sliderOptions) + '\'';

		// Close Shortcode
		shortCode += ']';

		// Update Shortcode
		jQuery(".gm-copy-inp").val(shortCode);
		jQuery(".gm-iframe-wrap").addClass('loading');

		// Update Preview
		const iframeEl = jQuery('#gm-iframe');
		const nonce = iframeEl.attr('data-nonce');
		iframeEl.attr('src', gridmaster_params.home_url + '?gm_shortcode_preview=1&_wpnonce=' + nonce + '&shortcode=' + encodeURIComponent(shortCode));

		// Data need save
		dataNeedSave(1);

	});

	// Copy Shortcode
	jQuery(document).on('click', '.gm-copy-btn', function (e) {
		jQuery(this).closest(".gm-copy-wrap").find(".gm-copy-val").select();
		document.execCommand("copy");
	});

	// Modal Open
	jQuery(document).on('click', '.gm-toggle-modal', function (e) {
		let modalId = jQuery(this).attr('data-modal-id');
		jQuery('#' + modalId).fadeIn('fast');
	});
	// Modal Close
	jQuery(document).on('click', '.gm-modal-close', function (e) {
		jQuery(this).closest('.gm-modal-wrap').fadeOut('fast');
	});

	// Change Filter Style
	jQuery(document).on('change', '#filter_style', function (e) {
		let $val = jQuery(this).val();
		// Hide
		jQuery('.filter-demo-link-button').hide().html('');
		// Add Link if exists
		if (gridmaster_params.filter_demo_links[$val]) {
			let link = `<a href="${gridmaster_params.demo_link + gridmaster_params.filter_demo_links[$val]}" target="_blank" class="align-items-center button button-secondary d-inline-flex"><span class="me-2 dashicons dashicons-external"></span>View Demo</a>`;
			jQuery('.filter-demo-link-button').html(link).fadeIn('fast')
		}
	});

	// Change Grid Style
	jQuery(document).on('change', '#grid_style', function (e) {
		let $val = jQuery(this).val();
		// Hide
		jQuery('.grid-demo-link-button').hide().html('');
		// Add Link if exists
		if (gridmaster_params.grid_demo_links[$val]) {
			let link = `<a href="${gridmaster_params.demo_link + gridmaster_params.grid_demo_links[$val]}" target="_blank" class="align-items-center button button-secondary d-inline-flex"><span class="me-2 dashicons dashicons-external"></span>View Demo</a>`;
			jQuery('.grid-demo-link-button').html(link).fadeIn('fast')
		}
	});

	// Trigger on load.
	jQuery('#grid_style').change();

	// Change Image Size
	jQuery(document).on('change', '.gridmaster-input-wrapper select, .gridmaster-input-wrapper input[type="radio"]', function (e) {
		let $val = jQuery(this).val();
		let $id = jQuery(this).attr('name');

		// Hide All
		jQuery('div[class^="show-if-' + $id + '"]').hide();

		// Show Current
		jQuery('.show-if-' + $id + '-' + $val).fadeIn('fast');
	});

	// Trigger on load.
	jQuery('.gridmaster-input-wrapper select, .gridmaster-input-wrapper input[type="radio"]').change();

	// Change Image Size
	jQuery(document).on('change', '[name="link_thumbnail"]', function (e) {
		let $val = jQuery(this).val();
		jQuery('.show-if-link_thumbnail-yes').hide();
		jQuery('.show-if-link_thumbnail-' + $val).fadeIn('fast');
	});

	// Trigger on load.
	jQuery('[name="link_thumbnail"]').change();

	// Submit form button
	jQuery(document).on('click', '.gm-save-grid', function (e) {
		jQuery('#gm-shortcode-generator').submit();
	});

	// Notice Timeout
	let noticeTimeout = false;

	// Ajax form submit
	jQuery(document).on('submit', '.gm-ajax-form', function (e) {
		e.preventDefault();
		let $form = jQuery(this);
		let $data = $form.serialize();
		// let $data = new FormData( this );

		// Disable Button
		$form.find('[type="submit"]').attr('disabled', 'disabled');

		// Action Value
		const gmAction = $form.find('[name="gm-action"]').val();

		// Spinner
		$form.find('.spinner').addClass('is-active');

		// Ajax Response 
		$form.find('.gm-ajax-response').html('');

		// Clear Timeout
		if (noticeTimeout) {
			clearTimeout(noticeTimeout);
		}

		// Append terms.
		// $data += '&terms=50,49'; 

		console.log($data)

		// License Form Submit
		// jQuery('.lmfwppt-license-submit-btn').trigger('click');

		// Ajax
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: $data,
			// contentType : false,
			// processData : false,
			// cache       : true,
			dataType: 'json',

			success: function (response) {

				// Enable Button
				$form.find('[type="submit"]').removeAttr('disabled');

				// Success
				if (response.success) {
					$form.find('.gm-ajax-response').html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
				} else {
					$form.find('.gm-ajax-response').html('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
				}

				jQuery(document).trigger('gm-ajax-success-' + gmAction, [response.data])

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
				jQuery(document).trigger('wp-updates-notice-added');

				// Hide Notice after 5 seconds
				noticeTimeout = setTimeout(function () {
					$form.find('.gm-ajax-response .notice').fadeOut('fast');
				}, 5000);
			}

		});
	});

	// Auto submit
	jQuery('#gm-list-grids').submit();

	// Window Load
	jQuery(window).load(() => {
		dataNeedSave(0);
	})

	// Block Pro Features
	if (!gridmaster_params.has_pro) {
		jQuery('.gm-pro-inp-disable input').attr('disabled', 'disabled');
	}

});

// Data Need Save on Build grid
const dataNeedSave = (save = 1) => {
	jQuery('.gm-page-build-grid').attr('data-need-save', save);
}

<?php
// Include the admin functions
require_once GRIDMASTER_PATH . '/admin/admin-functions.php';

$setting = gridmaster_get_settings();
?>
<div class="gridmaster-wrap gm-settings-container pt-5">
	<form class="gridmaster-options-form gm-ajax-form" method="post" action="">
		<div class="gm-card">

			<?php
			gridmaster_form_field(
				'gridmaster_options[debug-mode]',
				array(
					'type'        => 'radio',
					'label'       => __( 'Debug Mode', 'gridmaster' ),
					'options'     => array(
						'yes' => 'Yes',
						'no'  => 'No',
					),
					'default'     => $setting['debug-mode'] ?? 'no',
					'description' => __( 'Enable debug mode to see the notices generated by the plugin.', 'gridmaster' ),
				)
			);
			?>



			<?php
			gridmaster_form_field(
				'gridmaster_options[disable-nonce-check]',
				array(
					'type'        => 'radio',
					'label'       => __( 'Disable Nonce Check', 'gridmaster' ),
					'options'     => array(
						'yes' => 'Yes',
						'no'  => 'No',
					),
					'default'     => $setting['disable-nonce-check'] ?? 'no',
					'description' => __( 'Disable if you are facing any issues with the nonce check.', 'gridmaster' ),
				)
			);
			?>

			<?php

			$taxonomies       = gm_get_taxonomies( true );
			$taxonomy_options = $taxonomies['options'];

			$field_name = 'gridmaster_options[disable-icon-color-options][]';
			?>
			<div class="form-row d-flex gm-pro-field gm-pro-inp-disable" id="<?php echo esc_attr( $field_name ); ?>_field" data-priority="">
				<div class="gm-field-label">
					<label><?php esc_html_e( 'Disable Icon & Color Options on Taxonomies', 'gridmaster' ); ?></label>
					<span class="description"><?php esc_html_e( 'Disable icon and color options from Taxonomies.', 'gridmaster' ); ?></span>
				</div>
				<div class="gridmaster-input-wrapper">
					<?php foreach ( $taxonomy_options as $tax_name => $tax_label ) : ?>
						<div> <label class="radio gm-field-label">
							<input type="checkbox" class="input-radio " value="<?php echo esc_attr( $tax_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_name . $tax_name ); ?>" <?php checked( in_array( $tax_name, $setting['disable-icon-color-options'] ?? array() ) ); ?>>
							<?php echo esc_attr( $tax_label ); ?>
						</label></div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="form-row d-flex" id="license_activation_field">
				<div class="gm-field-label">
					<label><?php esc_html_e( 'License Activation', 'gridmaster' ); ?></label>
					<span class="description"><?php esc_html_e( 'Enter your license key to activate GridMaster Pro.', 'gridmaster' ); ?></span>
				</div>
				<div class="gridmaster-input-wrapper">
					<?php do_action( 'lmfwppt_license_activation_form_fields' ); ?>
				</div>
			</div>
		</div>

		<!-- Submit Button  -->

		<div class="align-items-center d-flex justify-content-end">
			<div class="gm-ajax-response"></div>
			<span class="spinner"></span>
			<button type="submit" class="gm-btn gm-btn-fill"><?php esc_html_e( 'Save Changes', 'gridmaster' ); ?></button>
			<input type="hidden" name="action" value="gridmaster_ajax">
			<input type="hidden" name="gm-action" value="save_settings">
			<?php wp_nonce_field( 'gm-ajax-nonce', 'gm_nonce' ); ?>
		</div>

	</form>
</div>
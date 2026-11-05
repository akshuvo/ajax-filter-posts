<?php
/**
 * Settings Page View
 *
 * @package GridMaster
 */

// Direct Access is not allowed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the admin functions.
require_once GRIDMASTER_PATH . '/admin/admin-functions.php';

$setting  = gridmaster_get_settings();
$is_pro   = function_exists( 'gridmaster_is_pro' ) && gridmaster_is_pro();
$taxonomies       = gm_get_taxonomies( true );
$taxonomy_options = isset( $taxonomies['options'] ) ? $taxonomies['options'] : array();
$disabled_tax     = isset( $setting['disable-icon-color-options'] ) && is_array( $setting['disable-icon-color-options'] ) ? $setting['disable-icon-color-options'] : array();
$debug_mode       = isset( $setting['debug-mode'] ) ? $setting['debug-mode'] : 'no';
$nonce_check      = isset( $setting['disable-nonce-check'] ) ? $setting['disable-nonce-check'] : 'no';
?>

<div class="gm-settings-wrap">

	<div class="gm-settings-header-banner">
		<div>
			<h2 class="gm-settings-title"><?php esc_html_e( 'Plugin Settings', 'ajax-filter-posts' ); ?></h2>
			<p class="gm-settings-subtitle"><?php esc_html_e( 'Configure system preferences, caching options, taxonomy enhancements, and license activation.', 'ajax-filter-posts' ); ?></p>
		</div>
		<div class="gm-settings-quick-info">
			<span class="gm-badge-status-pill <?php echo $is_pro ? 'gm-pill-pro' : 'gm-pill-free'; ?>">
				<span class="gm-status-dot"></span>
				<?php echo $is_pro ? esc_html__( 'GridMaster Pro Enabled', 'ajax-filter-posts' ) : esc_html__( 'GridMaster Free Edition', 'ajax-filter-posts' ); ?>
			</span>
		</div>
	</div>

	<form class="gridmaster-options-form gm-ajax-form" method="post" action="">

		<!-- General Settings Section -->
		<div class="gm-settings-section-card">
			<div class="gm-section-header">
				<div class="gm-section-icon gm-icon-indigo">
					<span class="dashicons dashicons-admin-settings"></span>
				</div>
				<div class="gm-section-title-wrap">
					<h3><?php esc_html_e( 'General & Performance', 'ajax-filter-posts' ); ?></h3>
					<p><?php esc_html_e( 'Control query debugging and frontend security checks.', 'ajax-filter-posts' ); ?></p>
				</div>
			</div>

			<div class="gm-settings-fields-body">
				<!-- Debug Mode Field -->
				<div class="gm-setting-row">
					<div class="gm-setting-info">
						<label class="gm-setting-label"><?php esc_html_e( 'Debug Mode', 'ajax-filter-posts' ); ?></label>
						<p class="gm-setting-description">
							<?php esc_html_e( 'Displays diagnostic notices and query execution details in the console for troubleshooting grid rendering and AJAX responses.', 'ajax-filter-posts' ); ?>
						</p>
					</div>
					<div class="gm-setting-control">
						<div class="gm-segmented-switch">
							<label class="gm-switch-option <?php echo 'no' === $debug_mode ? 'gm-active' : ''; ?>">
								<input type="radio" name="gridmaster_options[debug-mode]" value="no" <?php checked( $debug_mode, 'no' ); ?>>
								<span><?php esc_html_e( 'Disabled', 'ajax-filter-posts' ); ?></span>
							</label>
							<label class="gm-switch-option <?php echo 'yes' === $debug_mode ? 'gm-active' : ''; ?>">
								<input type="radio" name="gridmaster_options[debug-mode]" value="yes" <?php checked( $debug_mode, 'yes' ); ?>>
								<span><?php esc_html_e( 'Enabled', 'ajax-filter-posts' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<!-- Disable Nonce Check Field -->
				<div class="gm-setting-row">
					<div class="gm-setting-info">
						<label class="gm-setting-label"><?php esc_html_e( 'Disable Nonce Check', 'ajax-filter-posts' ); ?></label>
						<p class="gm-setting-description">
							<?php esc_html_e( 'Bypasses nonce validation on frontend AJAX filter & pagination requests. Enable this if page caching plugins (such as WP Rocket, LiteSpeed, or Cloudflare) serve expired nonces to visitors.', 'ajax-filter-posts' ); ?>
						</p>
					</div>
					<div class="gm-setting-control">
						<div class="gm-segmented-switch">
							<label class="gm-switch-option <?php echo 'no' === $nonce_check ? 'gm-active' : ''; ?>">
								<input type="radio" name="gridmaster_options[disable-nonce-check]" value="no" <?php checked( $nonce_check, 'no' ); ?>>
								<span><?php esc_html_e( 'Check Nonces (Recommended)', 'ajax-filter-posts' ); ?></span>
							</label>
							<label class="gm-switch-option <?php echo 'yes' === $nonce_check ? 'gm-active' : ''; ?>">
								<input type="radio" name="gridmaster_options[disable-nonce-check]" value="yes" <?php checked( $nonce_check, 'yes' ); ?>>
								<span><?php esc_html_e( 'Disable Nonce Validation', 'ajax-filter-posts' ); ?></span>
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Taxonomy Customization Options -->
		<div class="gm-settings-section-card">
			<div class="gm-section-header">
				<div class="gm-section-icon gm-icon-purple">
					<span class="dashicons dashicons-tag"></span>
				</div>
				<div class="gm-section-title-wrap">
					<h3><?php esc_html_e( 'Taxonomy Badge Options', 'ajax-filter-posts' ); ?></h3>
					<p><?php esc_html_e( 'Disable custom icon and color controls for specific taxonomies.', 'ajax-filter-posts' ); ?></p>
				</div>
			</div>

			<div class="gm-settings-fields-body">
				<div class="gm-setting-row gm-setting-row-block">
					<div class="gm-setting-info">
						<label class="gm-setting-label"><?php esc_html_e( 'Disable Icon & Color Options on Taxonomies', 'ajax-filter-posts' ); ?></label>
						<p class="gm-setting-description">
							<?php esc_html_e( 'Select which taxonomies should hide custom icon and color fields on their term edit screens.', 'ajax-filter-posts' ); ?>
						</p>
					</div>

					<div class="gm-taxonomy-grid">
						<?php
						$tax_field_name = 'gridmaster_options[disable-icon-color-options][]';
						foreach ( $taxonomy_options as $tax_name => $tax_label ) :
							$is_checked = in_array( $tax_name, $disabled_tax, true );
							?>
							<label class="gm-taxonomy-card <?php echo $is_checked ? 'gm-tax-selected' : ''; ?>">
								<input type="checkbox" name="<?php echo esc_attr( $tax_field_name ); ?>" value="<?php echo esc_attr( $tax_name ); ?>" <?php checked( $is_checked ); ?> class="gm-tax-checkbox">
								<div class="gm-tax-icon">
									<?php if ( 'category' === $tax_name ) : ?>
										<span class="dashicons dashicons-category"></span>
									<?php elseif ( 'post_tag' === $tax_name ) : ?>
										<span class="dashicons dashicons-tag"></span>
									<?php elseif ( 'post_format' === $tax_name ) : ?>
										<span class="dashicons dashicons-format-standard"></span>
									<?php else : ?>
										<span class="dashicons dashicons-networking"></span>
									<?php endif; ?>
								</div>
								<div class="gm-tax-text">
									<strong class="gm-tax-name"><?php echo esc_html( $tax_label ); ?></strong>
									<code class="gm-tax-slug"><?php echo esc_html( $tax_name ); ?></code>
								</div>
								<span class="gm-tax-check-indicator dashicons dashicons-yes"></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- License Activation Section (GridMaster Pro) -->
		<div class="gm-settings-section-card" id="license_activation_field">
			<div class="gm-section-header">
				<div class="gm-section-icon gm-icon-amber">
					<span class="dashicons dashicons-admin-network"></span>
				</div>
				<div class="gm-section-title-wrap">
					<h3><?php esc_html_e( 'License Activation', 'ajax-filter-posts' ); ?></h3>
					<p><?php esc_html_e( 'Manage your GridMaster Pro license to enable automatic one-click updates and priority technical support.', 'ajax-filter-posts' ); ?></p>
				</div>
			</div>

			<div class="gm-settings-fields-body">
				<div class="gm-setting-row gm-setting-row-block">
					<div class="gm-license-card-body">
						<?php if ( $is_pro ) : ?>
							<div class="gm-license-input-wrapper">
								<?php do_action( 'lmfwppt_license_activation_form_fields' ); ?>
							</div>
							<div class="gm-license-links">
								<a href="https://portal.addonmaster.com/my-account/" target="_blank" class="gm-license-helper-link">
									<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Find license in My Account', 'ajax-filter-posts' ); ?>
								</a>
								<a href="https://addonmaster.com/gridmaster/" target="_blank" class="gm-license-helper-link">
									<span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Purchase extra licenses', 'ajax-filter-posts' ); ?>
								</a>
							</div>
						<?php else : ?>
							<div class="gm-pro-upgrade-inline-banner">
								<div class="gm-pro-upgrade-text">
									<h4><?php esc_html_e( 'Upgrade to GridMaster Pro to unlock License Management', 'ajax-filter-posts' ); ?></h4>
									<p><?php esc_html_e( 'Gain access to visual Gutenberg card builder, 20+ premium presets, live AJAX search, and infinite scroll.', 'ajax-filter-posts' ); ?></p>
								</div>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&path=free-vs-pro' ) ); ?>" class="gm-btn gm-btn-primary">
									<?php esc_html_e( 'Explore Pro Features →', 'ajax-filter-posts' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Sticky / Action Save Bar -->
		<div class="gm-save-bar">
			<div class="gm-save-bar-inner">
				<div class="gm-save-status">
					<div class="gm-ajax-response"></div>
				</div>
				<div class="gm-save-actions">
					<span class="spinner"></span>
					<button type="submit" class="gm-btn gm-btn-primary gm-btn-save">
						<span class="dashicons dashicons-saved"></span>
						<span><?php esc_html_e( 'Save Changes', 'ajax-filter-posts' ); ?></span>
					</button>
					<input type="hidden" name="action" value="gridmaster_ajax">
					<input type="hidden" name="gm-action" value="save_settings">
					<?php wp_nonce_field( 'gm-ajax-nonce', 'gm_nonce' ); ?>
				</div>
			</div>
		</div>

	</form>

</div>

<script>
(function() {
	function initSettingsInteractions() {
		// Segmented Switch toggle active state
		document.querySelectorAll('.gm-segmented-switch input[type="radio"]').forEach(function(radio) {
			radio.addEventListener('change', function() {
				var container = this.closest('.gm-segmented-switch');
				if (container) {
					container.querySelectorAll('.gm-switch-option').forEach(function(opt) {
						opt.classList.remove('gm-active');
					});
					var parentLabel = this.closest('.gm-switch-option');
					if (parentLabel) {
						parentLabel.classList.add('gm-active');
					}
				}
			});
		});

		// Taxonomy Checkbox card active state
		document.querySelectorAll('.gm-taxonomy-card input[type="checkbox"]').forEach(function(checkbox) {
			checkbox.addEventListener('change', function() {
				var card = this.closest('.gm-taxonomy-card');
				if (card) {
					if (this.checked) {
						card.classList.add('gm-tax-selected');
					} else {
						card.classList.remove('gm-tax-selected');
					}
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSettingsInteractions);
	} else {
		initSettingsInteractions();
	}
})();
</script>

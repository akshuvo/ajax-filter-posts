<?php
namespace GridMaster\Facets\Handlers;

use GridMaster\Facets\Facet_Handler_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category / Taxonomy Button Tabs Filter Handler.
 */
class Taxonomy_Tabs implements Facet_Handler_Interface {

	public static function get_label() {
		return __( 'Taxonomy Tabs / Buttons', 'ajax-filter-posts' );
	}

	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false ) {
		$taxonomy   = ! empty( $facet['taxonomy'] ) ? sanitize_key( $facet['taxonomy'] ) : 'category';
		$heading    = ! empty( $facet['heading'] ) ? sanitize_text_field( $facet['heading'] ) : '';
		$show_all   = ! isset( $facet['show_all'] ) || (bool) $facet['show_all'];
		$all_label  = ! empty( $facet['all_label'] ) ? sanitize_text_field( $facet['all_label'] ) : __( 'All', 'ajax-filter-posts' );

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$active_terms = ! empty( $runtime_params['facets'][ $taxonomy ] ) ? (array) $runtime_params['facets'][ $taxonomy ] : array();
		$is_all_active = empty( $active_terms ) || in_array( 'all', $active_terms, true ) || in_array( 0, $active_terms, true );

		ob_start();
		?>
		<div class="gm-facet gm-facet-tabs" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
			<?php if ( $heading ) : ?>
				<h4 class="gm-facet-title"><?php echo esc_html( $heading ); ?></h4>
			<?php endif; ?>
			<div class="gm-tabs-list" role="tablist">
				<?php if ( $show_all ) : ?>
					<button type="button" class="gm-filter-btn<?php echo $is_all_active ? ' gm-active' : ''; ?>" data-term-id="0" role="tab" aria-selected="<?php echo $is_all_active ? 'true' : 'false'; ?>">
						<?php echo esc_html( $all_label ); ?>
					</button>
				<?php endif; ?>
				<?php foreach ( $terms as $term ) :
					$is_active = in_array( $term->term_id, $active_terms, false );
					?>
					<button type="button" class="gm-filter-btn<?php echo $is_active ? ' gm-active' : ''; ?>" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" role="tab" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
						<?php echo esc_html( $term->name ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'gridmaster_grid',
			__( 'GridMaster Grid', 'ajax-filter-posts' ),
			array(
				'description' => __( 'Insert a reusable GridMaster Gutenberg grid template.', 'ajax-filter-posts' ),
			)
		);
	}

	public function widget( $args, $instance ) {
		$grid_ref     = isset( $instance['grid_ref'] ) ? sanitize_text_field( $instance['grid_ref'] ) : '';
		$filter_style = isset( $instance['filter_style'] ) ? sanitize_text_field( $instance['filter_style'] ) : 'default';

		echo isset( $args['before_widget'] ) ? $args['before_widget'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Renderer::render_builder_selection(
			array(
				'gridRef'     => $grid_ref,
				'filterStyle' => $filter_style,
				'grid_id'     => 'gm-widget-' . $this->number,
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo isset( $args['after_widget'] ) ? $args['after_widget'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$grid_ref     = isset( $instance['grid_ref'] ) ? sanitize_text_field( $instance['grid_ref'] ) : '';
		$filter_style = isset( $instance['filter_style'] ) ? sanitize_text_field( $instance['filter_style'] ) : 'default';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'grid_ref' ) ); ?>"><?php esc_html_e( 'Grid', 'ajax-filter-posts' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'grid_ref' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'grid_ref' ) ); ?>">
				<option value=""><?php esc_html_e( 'Select a grid', 'ajax-filter-posts' ); ?></option>
				<?php foreach ( $this->get_grid_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $grid_ref, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'filter_style' ) ); ?>"><?php esc_html_e( 'Filter', 'ajax-filter-posts' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'filter_style' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'filter_style' ) ); ?>">
				<?php foreach ( $this->get_filter_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter_style, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'grid_ref'     => isset( $new_instance['grid_ref'] ) ? sanitize_text_field( $new_instance['grid_ref'] ) : '',
			'filter_style' => isset( $new_instance['filter_style'] ) ? sanitize_text_field( $new_instance['filter_style'] ) : 'default',
		);
	}

	private function get_grid_options() {
		$options = array();

		$templates = get_posts(
			array(
				'post_type'      => array( 'gm_grid_template', 'gm_grid_style' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $templates as $template ) {
			$options[ 'template:' . $template->ID ] = sprintf( __( 'Template: %s', 'ajax-filter-posts' ), $template->post_title );
		}

		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		$legacy_grids = Grids::init()->list();
		if ( ! empty( $legacy_grids ) ) {
			foreach ( $legacy_grids as $grid ) {
				$options[ 'legacy:' . $grid->id ] = sprintf( __( 'Saved Grid: %s', 'ajax-filter-posts' ), $grid->title );
			}
		}

		return $options;
	}

	private function get_filter_options() {
		return apply_filters(
			'gridmaster_filter_styles',
			array(
				'default' => __( 'Style 1 (Default)', 'ajax-filter-posts' ),
				'style-2' => __( 'Style 2 (New)', 'ajax-filter-posts' ),
				'style-3' => __( 'Style 3 (New)', 'ajax-filter-posts' ),
				'style-4' => __( 'Style 4 (New)', 'ajax-filter-posts' ),
			)
		);
	}
}

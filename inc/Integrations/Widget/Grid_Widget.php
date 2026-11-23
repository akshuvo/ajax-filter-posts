<?php
namespace GridMaster\Integrations\Widget;

use GridMaster\Integrations\Embed_Helper;
use WP_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic WordPress Widget for Post Grid Master.
 */
class Grid_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'gridmaster_grid_widget',
			__( 'Post Grid Master', 'ajax-filter-posts' ),
			array(
				'description' => __( 'Display a saved Post Grid Master layout.', 'ajax-filter-posts' ),
			)
		);
	}

	/**
	 * Frontend display of widget.
	 *
	 * @param array $args Widget display arguments.
	 * @param array $instance Stored values.
	 */
	public function widget( $args, $instance ) {
		$grid_id = ! empty( $instance['grid_id'] ) ? absint( $instance['grid_id'] ) : 0;
		$title   = ! empty( $instance['title'] ) ? $instance['title'] : '';

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo Embed_Helper::render( $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Current values.
	 */
	public function form( $instance ) {
		$title   = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$grid_id = ! empty( $instance['grid_id'] ) ? absint( $instance['grid_id'] ) : 0;
		$choices = Embed_Helper::get_grid_choices();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ajax-filter-posts' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'grid_id' ) ); ?>"><?php esc_html_e( 'Select Grid:', 'ajax-filter-posts' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'grid_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'grid_id' ) ); ?>">
				<?php foreach ( $choices as $id => $label ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $grid_id, $id ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
		if ( current_user_can( 'manage_options' ) ) :
			$edit_url   = $grid_id ? Embed_Helper::get_edit_url( $grid_id ) : '';
			$create_url = Embed_Helper::get_create_url();
			?>
			<p style="font-size:12px;color:#64748b;line-height:1.6;margin-top:8px;">
				<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#4f46e5;text-decoration:none;">✎ <?php esc_html_e( 'Edit this grid', 'ajax-filter-posts' ); ?></a><br>
				<?php endif; ?>
				<?php if ( $create_url ) : ?>
					<a href="<?php echo esc_url( $create_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#64748b;text-decoration:underline;">+ <?php esc_html_e( 'Create New Grid', 'ajax-filter-posts' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance            = array();
		$instance['title']   = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['grid_id'] = ! empty( $new_instance['grid_id'] ) ? absint( $new_instance['grid_id'] ) : 0;
		return $instance;
	}
}

<div class="am_grid_col">
	<div class="am_single_grid">
		<div class="am_thumb">
			<?php gridmaster_post_thumbnail( apply_filters( 'gridmaster_post_thumb_size', 'full' ) ); ?>
		</div>
		
		<div class="am_cont">

			<div class="gm-postmeta">
                <?php echo gridmaster_get_the_terms(); // phpcs:ignore ?> 
				<span><?php esc_html_e( 'on', 'ajax-filter-posts'  ); ?></span>
                <?php echo gridmaster_get_the_date( 'M d, Y' ); // phpcs:ignore ?>
                <?php echo gridmaster_posted_by(); // phpcs:ignore ?>
			</div>

            <?php echo gridmaster_get_post_title(); // phpcs:ignore ?>
            <?php echo gridmaster_the_content(); // phpcs:ignore ?>
            <?php echo gridmaster_read_more_link( __('Read More', 'ajax-filter-posts' ) ); // phpcs:ignore ?>
		</div>
	</div>
</div>
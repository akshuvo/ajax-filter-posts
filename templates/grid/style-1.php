<div class="am_grid_col">
    <div class="am_single_grid">
        <div class="am_thumb">
            <?php the_post_thumbnail( apply_filters('gridmaster_post_thumb_size', 'full') ); ?>
        </div>
        <div class="am_cont">
            <a  href="<?php echo get_the_permalink(); ?>"><h4 class="am__title"><?php echo get_the_title(); ?></h4>  </a>
            
            <?php echo gridmaster_the_content(); ?>

            <?php echo gridmaster_read_more_link( __('Read More', 'gridmaster') ); ?>

        </div>
    </div>
</div>

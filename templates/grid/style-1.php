<div class="am_grid_col">
    <div class="am_single_grid">
        <div class="am_thumb">
            <?php the_post_thumbnail('full'); ?>
        </div>
        <div class="am_cont">
            <a href="<?php echo get_the_permalink(); ?>"><h2 class="am__title"><?php echo get_the_title(); ?></h2></a>
            <div class="am__excerpt">
                <?php echo wp_trim_words( get_the_excerpt(), 15, null ); ?>
            </div>
            <!-- <a href="<?php echo get_the_permalink(); ?>" class="am__readmore"><?php echo esc_html__('Read More','ajax-filter-posts');?></a> -->
        </div>
    </div>
</div>
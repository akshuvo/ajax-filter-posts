<?php
/**
 * Gridmaster Taxonomy Filter Template
 * Name: Style 2
 *
 * @package Gridmaster
 * @since 1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Taxonomy Args
$tax_args = $args['tax_args'];

// Get category terms
$tax_terms = get_terms($tax_args); 

$taxonomy = $tax_args['taxonomy'];
$grid_id = $args['grid_id'];
$input_type = $args['input_type'];
$filter_style = $args['filter_style'];

$input_name = 'tax_input[' . $taxonomy . '][]';
$input_id = $grid_id . '-' . $taxonomy . '_all';

if( $tax_terms && !is_wp_error( $tax_terms ) ) : ?>
    
    <div class="gm-taxonomy-filter <?php esc_attr_e(" gm-tax-filter-style-{$filter_style} gm-filter-grid-id-{$grid_id} ")?>">

        <?php if( $args['btn_all'] != "no" ) : ?>
            <div class="gm-taxonomy-item gm-taxonomy-all">
                <input type="<?php esc_attr_e( $input_type ); ?>" name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" value="-1" />
                <label class="asr_texonomy" for="<?php echo $input_id; ?>"><?php echo esc_html('All','gridmaster'); ?></label>
            </div>
        <?php endif; ?>

        <?php foreach( $tax_terms as $term ) :
            $taxonomy = $term->taxonomy;
            $input_id = $grid_id . '-' . $taxonomy . '_' . $term->term_id;
            $input_name = 'tax_input[' . $taxonomy . '][]';
            ?>
            <div class="gm-taxonomy-item">
                <input type="<?php esc_attr_e( $input_type ); ?>" name="<?php echo $input_name; ?>" id="<?php echo $input_id; ?>" value="<?php echo $term->term_id; ?>" />
                <label class="asr_texonomy" for="<?php echo $input_id; ?>"><?php echo $term->name; ?></label>
            </div>
        <?php endforeach; ?>

    </div>
<?php endif; ?>

<style>
.gm-taxonomy-filter.gm-tax-filter-style-style-2 {
    flex-wrap: nowrap;
    overflow: scroll;
}
.asr-filter-div .gm-tax-filter-style-style-2 .asr_texonomy {
    margin-right: 15px;
    background: #f9f9f9;
    padding: 18px 20px;
    border-radius: 14px;
    transition: all .2s;
    cursor: pointer;
    box-shadow: rgba(0,0,0,.05) 0 0 0 1px;
    min-width: 82px;
    font-size: 16px;
    white-space: nowrap;
}
.gm-tax-filter-style-style-2 .asr_texonomy:hover{
    transform: translateY(-4px);
    background: #fff5d0;
}
.gm-tax-filter-style-style-2 .gm-taxonomy-item input:checked + label {
    box-shadow: rgba(0,0,0,.16) 0 1px 10px, #b89932 0 0 0 1px;
    background: #fffae7;
}
</style>
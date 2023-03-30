<?php

// Responsive Breakpoints
function gm_get_breakpoints() {
    // All values are in pixels and are used for media queries up to the next breakpoint.
    return array(
        'xs' => array(
            'label' => __( 'Extra Small', 'gridmaster' ),
            'value' => '320',
            'default' => false,
            'icon' => 'dashicons dashicons-smartphone'
        ),
        'sm' => array(
            'label' => __( 'Small', 'gridmaster' ),
            'value' => '768',
            'default' => false,
            'icon' => 'dashicons dashicons-smartphone'
        ),
        'md' => array(
            'label' => __( 'Medium', 'gridmaster' ),
            'value' => '992',
            'default' => false,
            'icon' => 'dashicons dashicons-tablet'
        ),
        'lg'  => array(
            'label' => __( 'Large', 'gridmaster' ),
            'value' => '1200',
            'default' => true,
            'icon' => 'dashicons dashicons-laptop'
        ),
        'xl' => array(
            'label' => __( 'Extra Large', 'gridmaster' ),
            'value' => '1600',
            'default' => false,
            'icon' => 'dashicons dashicons-desktop'
        ),
    );
}

// Post content
function gridmaster_the_content() {

    // Shortcode atts
    $args = apply_filters( 'gridmaster_get_render_grid_args', [] );
    $content_from = isset( $args['content_from'] ) ? $args['content_from'] : 'excerpt';

    $output = '<div class="am__excerpt">';

    if( $content_from == 'content' ) {
        $content = get_the_content();
        $content = apply_filters( 'the_content', $content );
        $content = str_replace( ']]>', ']]&gt;', $content );
        $output .= $content;
    } else {
        $excerpt_length = apply_filters('gridmaster_excerpt_length', 15);
        $content = wp_trim_words( get_the_excerpt(), $excerpt_length, '');
        $output .= $content;
    }

    $output .= '</div>';

    return $output;
}

// Read More Link
function gridmaster_read_more_link( $link_text = '' ) {

    // Shortcode atts
    $args = apply_filters( 'gridmaster_get_render_grid_args', [] );
    $show_read_more = isset( $args['show_read_more'] ) ? $args['show_read_more'] : 'yes';
    $read_more_text = isset( $args['read_more_text'] ) ? $args['read_more_text'] : '';

    if( $show_read_more == 'no' ) {
        return;
    }

    if( !empty( $read_more_text ) ) {
        $link_text = $read_more_text;
    } elseif( empty( $link_text ) ) {
        $link_text = __('Read More', 'gridmaster');
    }

    $output = '<div class="gm_read_more">';
    $output .= '<a href="'. get_the_permalink() .'" class="am__readmore am__read_more_link">'. $link_text .'</a>';
    $output .= '</div>';

    return $output;
}
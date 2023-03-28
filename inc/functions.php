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
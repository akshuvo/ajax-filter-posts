<?php

/**
 * Outputs a checkout/address form field.
 *
 * @param string $key Key.
 * @param mixed  $args Arguments.
 * @param string $value (default: null).
 * @return string
 */
function gridmaster_form_field( $key = '', $args = [], $value = null ) {
    $defaults = array(
        'type'              => 'text',
        'label'             => '',
        'description'       => '',
        'placeholder'       => '',
        'maxlength'         => false,
        'required'          => false,
        'autocomplete'      => false,
        'id'                => $key,
        'class'             => array(),
        'label_class'       => array('gm-field-label'),
        'input_class'       => array(),
        'return'            => false,
        'options'           => array(),
        'custom_attributes' => array(),
        'validate'          => array(),
        'default'           => '',
        'autofocus'         => '',
        'priority'          => '',
        'is_pro'            => false,
        'responsive_field'  => false,
    );

    $args = wp_parse_args( $args, $defaults );
    $args = apply_filters( 'gridmaster_form_field_args', $args, $key, $value );

    if ( is_string( $args['class'] ) ) {
        $args['class'] = array( $args['class'] );
    }

    if ( $args['required'] ) {
        $args['class'][] = 'validate-required';
        $required        = '&nbsp;<abbr class="required" title="' . esc_attr__( 'required', 'gridmaster' ) . '">*</abbr>';
    } else {
        $required = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'gridmaster' ) . ')</span>';
    }

    // If is_pro is true, add pro class.
    if ( $args['is_pro'] ) {
        $args['class'][] = 'gm-pro-field';
    }

    $required = '';

    if ( is_string( $args['label_class'] ) ) {
        $args['label_class'] = array( $args['label_class'] );
    }

    if ( is_null( $value ) ) {
        $value = $args['default'];
    }

    // Custom attribute handling.
    $custom_attributes         = array();
    $args['custom_attributes'] = array_filter( (array) $args['custom_attributes'], 'strlen' );

    if ( $args['maxlength'] ) {
        $args['custom_attributes']['maxlength'] = absint( $args['maxlength'] );
    }

    if ( ! empty( $args['autocomplete'] ) ) {
        $args['custom_attributes']['autocomplete'] = $args['autocomplete'];
    }

    if ( true === $args['autofocus'] ) {
        $args['custom_attributes']['autofocus'] = 'autofocus';
    }

    if ( $args['description'] ) {
        $args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
    }

    if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
        foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
            $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
        }
    }

    if ( ! empty( $args['validate'] ) ) {
        foreach ( $args['validate'] as $validate ) {
            $args['class'][] = 'validate-' . $validate;
        }
    }

    $field           = '';
    $label_id        = $args['id'];
    $sort            = $args['priority'] ? $args['priority'] : '';
    $field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';

    switch ( $args['type'] ) {
        
        case 'textarea':
            $field .= '<textarea name="' . esc_attr( $key ) . '" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" ' . ( empty( $args['custom_attributes']['rows'] ) ? ' rows="2"' : '' ) . ( empty( $args['custom_attributes']['cols'] ) ? ' cols="5"' : '' ) . implode( ' ', $custom_attributes ) . '>' . esc_textarea( $value ) . '</textarea>';

            break;
        case 'checkbox':
            $field = '<label class="checkbox ' . implode( ' ', $args['label_class'] ) . '" ' . implode( ' ', $custom_attributes ) . '>
                    <input type="' . esc_attr( $args['type'] ) . '" class="input-checkbox ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( $value, 1, false ) . ' /> ' . $args['label'] . $required . '</label>';

            break;
        case 'checkbox-yes':
            $field = '<label class="checkbox ' . implode( ' ', $args['label_class'] ) . '" ' . implode( ' ', $custom_attributes ) . '>
                    <input type="' . esc_attr( $args['type'] ) . '" class="input-checkbox ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="yes" ' . checked( $value, 1, false ) . ' /> ' . $args['label'] . $required . '</label>';

            break;
        case 'text':
        case 'password':
        case 'datetime':
        case 'datetime-local':
        case 'date':
        case 'month':
        case 'time':
        case 'week':
        case 'number':
        case 'email':
        case 'url':
        case 'tel':
            $field .= '<input type="' . esc_attr( $args['type'] ) . '" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

            break;
        case 'hidden':
            $field .= '<input type="' . esc_attr( $args['type'] ) . '" class="input-hidden ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

            break;
        case 'select':
            $field   = '';
            $options = '';

            if ( ! empty( $args['options'] ) ) {
                foreach ( $args['options'] as $option_key => $option_text ) {
                    if ( '' === $option_key ) {
                        // If we have a blank option, select2 needs a placeholder.
                        if ( empty( $args['placeholder'] ) ) {
                            $args['placeholder'] = $option_text ? $option_text : __( 'Choose an option', 'gridmaster' );
                        }
                        $custom_attributes[] = 'data-allow_clear="true"';
                    }
                    $options .= '<option value="' . esc_attr( $option_key ) . '" ' . selected( $value, $option_key, false ) . '>' . esc_html( $option_text ) . '</option>';
                }

                $field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="select ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" ' . implode( ' ', $custom_attributes ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ) . '">
                        ' . $options . '
                    </select>';
            }

            break;
        case 'radio':
            $label_id .= '_' . current( array_keys( $args['options'] ) );

            if ( ! empty( $args['options'] ) ) {
                foreach ( $args['options'] as $option_key => $option_text ) {
                    $field .= '<input type="radio" class="input-radio ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '" ' . implode( ' ', $custom_attributes ) . ' id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
                    $field .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="radio ' . implode( ' ', $args['label_class'] ) . '">' . esc_html( $option_text ) . '</label>';
                }
            }

            break;
    }

    if ( ! empty( $field ) ) {
        $field_html = '';

        if ( $args['label'] && 'checkbox' !== $args['type'] ) {
            $field_html .= '<label for="' . esc_attr( $label_id ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . wp_kses_post( $args['label'] ) . $required . '</label>';
        }

        $field_html .= '<span class="gridmaster-input-wrapper">';
            
        // If responsive_field is true then add xsmall, small, medium, large and xlarge fields
        if ( $args['responsive_field'] ) {
            $field_html .= '<span class="gridmaster-responsive-fields">';

            $field_html .= '<span class="gridmaster-responsive-fields-devices-wrap">';
                $field_html .= '<span class="gridmaster-responsive-fields-selected-devices">
                    <span class="dashicons dashicons-dashicons dashicons-smartphone"></span>
                </span>';

                $field_html .= '<span class="gridmaster-responsive-fields-devices">';
                foreach( gm_get_breakpoints() as $device => $breakpoint ) {
                    $classes = '';
                    if( $breakpoint['default'] ) {
                        $classes = ' selected ';
                    } 
                    $field_html .= '<span class="gridmaster-responsive-fields-device ' . esc_attr( $classes ) . '" data-device="' . esc_attr( $device ) . '" title="' . esc_attr( $breakpoint['label'] ) . '">' 
                        . '<span class="dashicons dashicons-' . esc_attr( $breakpoint['icon'] ) . '"></span>' . 
                    '</span>';
                }
                $field_html .= '</span>';
            $field_html .= '</span>';

            $field_html .= '<span class="gridmaster-responsive-fields-content">';
                foreach( gm_get_breakpoints() as $device => $breakpoint ) {

                    if( !$breakpoint['default'] ) {
                        $args['input_class'][] = 'hidden';
                    } 

                    // $field_html .= '<span class="gridmaster-responsive-fields-content-' . esc_attr( $device ) . '">';
                    $field_html .= '<input type="' . esc_attr( $args['type'] ) . '" class="responsive-field input-text gridmaster-input-' . esc_attr( $device ) . ' ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" data-name="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '[' . esc_attr( $device ) . ']" id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $device ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
                    // $field_html .= '</span>';
                }
            $field_html .= '</span>';

        } else {
            $field_html .= $field;
        }



        $field_html .= '</span>';


        if ( $args['description'] ) {
            $field_html .= '<span class="description" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
        }

        $container_class = esc_attr( implode( ' ', $args['class'] ) );
        $container_id    = esc_attr( $args['id'] ) . '_field';
        $field           = sprintf( $field_container, $container_class, $container_id, $field_html );
    }

    /**
     * Filter by type.
     */
    $field = apply_filters( 'gridmaster_form_field_' . $args['type'], $field, $key, $args, $value );

    /**
     * General filter on form fields.
     *
     * @since 3.4.0
     */
    $field = apply_filters( 'gridmaster_form_field', $field, $key, $args, $value );

    if ( $args['return'] ) {
        return $field;
    } else {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $field;
    }
}

// Shortcode Field name
function gm_field_name( $name ) {
    return $name;
    return 'gridmaster_shortcode[' . $name . ']';
}

// Get Post Types
function gm_get_post_types() {
    $post_types = get_post_types( array( 'public' => true ), 'objects' );
    $options    = array();

    foreach ( $post_types as $post_type ) {
        $options[ $post_type->name ] = $post_type->label;
    }

    return $options;
}

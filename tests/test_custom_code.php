<?php
/**
 * Test suite for Grid Builder Custom CSS and Custom Scripts.
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GRIDMASTER_PATH', __DIR__ . '/../' );
define( 'GRIDMASTER_URL', 'http://example.com/wp-content/plugins/ajax-filter-posts' );
define( 'GRIDMASTER_ASSETS', GRIDMASTER_URL . '/assets/' );
define( 'GRIDMASTER_VERSION', '2.0.0' );

// Mock WordPress functions
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $str ) { return htmlspecialchars( $str, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( $str ) { return addslashes( $str ); }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $str ) { return htmlspecialchars( $str, ENT_QUOTES ); }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( $val ) { return abs( intval( $val ) ); }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
}

require_once GRIDMASTER_PATH . '/inc/Storage/Grid_Repository.php';
require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';

use GridMaster\Storage\Grid_Repository;
use GridMaster\Engine\Grid_Renderer;

$tests_passed = 0;
$tests_failed = 0;

function it( $description, $test_fn ) {
    global $tests_passed, $tests_failed;
    try {
        $test_fn();
        echo "[PASS] " . $description . "\n";
        $tests_passed++;
    } catch ( Exception $e ) {
        echo "[FAIL] " . $description . ": " . $e->getMessage() . "\n";
        $tests_failed++;
    }
}

// 1. Verify default_config schema
it( 'Grid_Repository::default_config contains custom_code schema', function() {
    $defaults = Grid_Repository::default_config();
    if ( ! isset( $defaults['custom_code'] ) ) {
        throw new Exception( 'custom_code key missing from default_config' );
    }
    if ( ! array_key_exists( 'css', $defaults['custom_code'] ) || ! array_key_exists( 'js', $defaults['custom_code'] ) ) {
        throw new Exception( 'custom_code must have css and js keys' );
    }
} );

// 2. Verify custom CSS compilation with {{GRID}} macro
it( 'Grid_Renderer::render_custom_code replaces {{GRID}} and {{GRID_ID}} with scoped selector', function() {
    $config = array(
        'custom_code' => array(
            'css' => '{{GRID}} .gm-card { transform: scale(1.05); } {{GRID_ID}} .gm-filter-btn { color: red; }',
            'js'  => '',
        ),
    );
    $html = Grid_Renderer::render_custom_code( $config, 42 );

    if ( false === strpos( $html, '<style class="gm-grid-custom-css" data-grid-id="42">' ) ) {
        throw new Exception( 'Expected style tag with data-grid-id="42"' );
    }
    if ( false === strpos( $html, '#gm-grid-42 .gm-card { transform: scale(1.05); }' ) ) {
        throw new Exception( 'Expected {{GRID}} to be replaced with #gm-grid-42' );
    }
    if ( false === strpos( $html, '#gm-grid-42 .gm-filter-btn { color: red; }' ) ) {
        throw new Exception( 'Expected {{GRID_ID}} to be replaced with #gm-grid-42' );
    }
} );

// 3. Verify rogue <style> tags are stripped from CSS
it( 'Grid_Renderer::render_custom_code strips rogue <style> tags', function() {
    $config = array(
        'custom_code' => array(
            'css' => '<style type="text/css">{{GRID}} { background: #000; }</style>',
            'js'  => '',
        ),
    );
    $html = Grid_Renderer::render_custom_code( $config, 5 );

    if ( 1 !== substr_count( $html, '<style' ) ) {
        throw new Exception( 'Rogue <style> tags should have been stripped' );
    }
    if ( 1 !== substr_count( $html, '</style>' ) ) {
        throw new Exception( 'Rogue </style> tags should have been stripped' );
    }
    if ( false === strpos( $html, '#gm-grid-5 { background: #000; }' ) ) {
        throw new Exception( 'CSS body should remain intact' );
    }
} );

// 4. Verify live preview scoping
it( 'Grid_Renderer::render_custom_code uses preview selector when in preview mode', function() {
    $config = array(
        'custom_code' => array(
            'css' => '{{GRID}} .gm-card { opacity: 0.9; }',
            'js'  => '',
        ),
    );
    $html = Grid_Renderer::render_custom_code( $config, 0, true );

    if ( false === strpos( $html, '[data-preview-mode="1"]' ) ) {
        throw new Exception( 'Expected preview mode selector [data-preview-mode="1"]' );
    }
} );

// 5. Verify custom JS wrapping, parameters, and event listener
it( 'Grid_Renderer::render_custom_code compiles safe custom JS with lifecycle handlers', function() {
    $config = array(
        'custom_code' => array(
            'css' => '',
            'js'  => 'console.log("Card count:", gridEl.querySelectorAll(".gm-card").length);',
        ),
    );
    $html = Grid_Renderer::render_custom_code( $config, 77 );

    if ( false === strpos( $html, '<script class="gm-grid-custom-js" data-grid-id="77">' ) ) {
        throw new Exception( 'Expected script tag with data-grid-id="77"' );
    }
    if ( false === strpos( $html, 'function gmInitCustom_77()' ) ) {
        throw new Exception( 'Expected function gmInitCustom_77()' );
    }
    if ( false === strpos( $html, 'gridmaster_loaded_posts' ) ) {
        throw new Exception( 'Expected gridmaster_loaded_posts event listener for AJAX reload re-trigger' );
    }
    if ( false === strpos( $html, 'console.log("Card count:", gridEl.querySelectorAll(".gm-card").length);' ) ) {
        throw new Exception( 'Expected user JS body to be included' );
    }
} );

// 6. Verify rogue <script> tags are stripped from JS
it( 'Grid_Renderer::render_custom_code strips rogue <script> tags from JS', function() {
    $config = array(
        'custom_code' => array(
            'css' => '',
            'js'  => '<script>alert("hello");</script>',
        ),
    );
    $html = Grid_Renderer::render_custom_code( $config, 10 );

    if ( 1 !== substr_count( $html, '<script' ) ) {
        throw new Exception( 'Rogue <script> tags should have been stripped' );
    }
    if ( 1 !== substr_count( $html, '</script>' ) ) {
        throw new Exception( 'Rogue </script> tags should have been stripped' );
    }
    if ( false === strpos( $html, 'alert("hello");' ) ) {
        throw new Exception( 'JS body should remain intact' );
    }
} );

// 7. Verify empty return when neither CSS nor JS configured
it( 'Grid_Renderer::render_custom_code returns empty string when no custom code set', function() {
    $config = array(
        'custom_code' => array(
            'css' => '',
            'js'  => '',
        ),
    );
    $html = Grid_Renderer::render_custom_code( $config, 1 );

    if ( '' !== $html ) {
        throw new Exception( 'Expected empty string when no custom code configured' );
    }
} );

// 8. Verify fallback to top-level custom_css / custom_js for backward compatibility
it( 'Grid_Renderer::render_custom_code supports top-level custom_css and custom_js fallback', function() {
    $config = array(
        'custom_css' => '{{GRID}} { border: 1px solid green; }',
        'custom_js'  => 'console.log(gridId);',
    );
    $html = Grid_Renderer::render_custom_code( $config, 99 );

    if ( false === strpos( $html, '#gm-grid-99 { border: 1px solid green; }' ) ) {
        throw new Exception( 'Fallback to top-level custom_css failed' );
    }
    if ( false === strpos( $html, 'console.log(gridId);' ) ) {
        throw new Exception( 'Fallback to top-level custom_js failed' );
    }
} );

echo "\nTest Summary: {$tests_passed} passed, {$tests_failed} failed.\n";
if ( $tests_failed > 0 ) {
    exit( 1 );
}

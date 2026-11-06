<?php
/**
 * Test suite for Dynamic Sources, Card Video Block, and Video Presets.
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'GRIDMASTER_PATH', __DIR__ . '/../' );
define( 'GRIDMASTER_URL', 'http://example.com/wp-content/plugins/ajax-filter-posts' );
define( 'GRIDMASTER_ASSETS', GRIDMASTER_URL . '/assets/' );
define( 'GRIDMASTER_VERSION', '2.0.0' );
define( 'GRIDMASTER_PRO_VERSION', '2.0.0' );
define( 'GRIDMASTER_PRO_URL', 'http://example.com/wp-content/plugins/gridmaster-pro' );

// Mock WordPress functions
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
    function sanitize_hex_color( $color ) { return $color; }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $str ) { return htmlspecialchars( $str, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return $url; }
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
if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() { return 101; }
}
if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $id ) { return 'Sample Routine Lesson #' . $id; }
}
if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $id ) { return 'http://example.com/post/' . $id; }
}
if ( ! function_exists( 'has_post_thumbnail' ) ) {
    function has_post_thumbnail( $id ) { return $id === 101; }
}
if ( ! function_exists( 'get_the_post_thumbnail' ) ) {
    function get_the_post_thumbnail( $id, $size, $attr ) {
        return '<img src="http://example.com/thumb.jpg" class="' . $attr['class'] . '" style="' . $attr['style'] . '" />';
    }
}
if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
    function get_the_post_thumbnail_url( $id, $size ) { return 'http://example.com/thumb.jpg'; }
}
if ( ! function_exists( 'get_post_field' ) ) {
    function get_post_field( $field, $id ) {
        if ( $field === 'post_content' ) {
            return '<p>Check out this video: https://www.youtube.com/watch?v=dQw4w9WgXcQ and <img src="http://example.com/content-img.png" /></p>';
        }
        if ( $field === 'post_author' ) return 1;
        return '';
    }
}
if ( ! function_exists( 'get_avatar_url' ) ) {
    function get_avatar_url( $user_id, $args = array() ) { return 'http://example.com/avatar.jpg'; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $id, $key, $single = true ) {
        if ( $key === 'video_url' ) return 'https://mutterschiff.b-cdn.net/preview.mp4';
        if ( $key === 'custom_img' ) return 'http://example.com/custom-acf.png';
        return '';
    }
}
if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args ) {
        return array(
            (object) array( 'term_id' => 10, 'name' => 'Scratch' ),
            (object) array( 'term_id' => 11, 'name' => 'Toneplay' ),
        );
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return false; }
}
if ( ! function_exists( 'register_block_type' ) ) {
    function register_block_type( $name, $args ) { return true; }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}

require_once 'ajax-filter-posts/inc/Blocks.php';
require_once 'gridmaster-pro/inc/Blocks/Pro_Blocks.php';
require_once 'ajax-filter-posts/inc/Facets/Facet_Handler_Interface.php';
require_once 'ajax-filter-posts/inc/Facets/Handlers/Taxonomy_Tabs.php';

$blocks = new \GridMaster\Blocks();
$pro_blocks = new \GridMasterPro\Blocks\Pro_Blocks();

$passed = 0;
$failed = 0;

function assert_test( $cond, $name ) {
    global $passed, $failed;
    if ( $cond ) {
        echo "[PASS] " . $name . "
";
        $passed++;
    } else {
        echo "[FAIL] " . $name . "
";
        $failed++;
    }
}

// 1. Test Featured Image with Dynamic Source: Featured Image
$mock_block = new stdClass();
$mock_block->context = array( 'postId' => 101 );
$html = $blocks->render_featured_image( array( 'sourceType' => 'featured_image', 'aspectRatio' => '16/9' ), '', $mock_block );
assert_test( strpos( $html, 'thumb.jpg' ) !== false && strpos( $html, 'aspect-ratio:16/9' ) !== false, 'Featured image renders post thumbnail' );

// 2. Test Featured Image with Dynamic Source: Custom Meta
$html_meta = $blocks->render_featured_image( array( 'sourceType' => 'custom_meta', 'metaKey' => 'custom_img', 'aspectRatio' => '1/1' ), '', $mock_block );
assert_test( strpos( $html_meta, 'custom-acf.png' ) !== false && strpos( $html_meta, 'aspect-ratio:1/1' ) !== false, 'Featured image renders from custom meta' );

// 3. Test Featured Image with Dynamic Source: Content First
$mock_block2 = new stdClass();
$mock_block2->context = array( 'postId' => 102 ); // post 102 has no featured image
$html_content = $blocks->render_featured_image( array( 'sourceType' => 'content_first', 'aspectRatio' => '9/16' ), '', $mock_block2 );
assert_test( strpos( $html_content, 'content-img.png' ) !== false && strpos( $html_content, 'aspect-ratio:9/16' ) !== false, 'Featured image extracts first image from post content with 9/16 ratio' );

// 4. Test Featured Image with Dynamic Source: Static URL with Fallback
$html_static = $blocks->render_featured_image( array( 'sourceType' => 'static_url', 'staticUrl' => 'http://example.com/static.png' ), '', $mock_block2 );
assert_test( strpos( $html_static, 'static.png' ) !== false, 'Featured image renders static URL' );

// 5. Test Card Video Block (Pro): Inline Click mode (Superhero DJs style)
$html_vid = $pro_blocks->render_card_video( array(
    'videoSourceType' => 'custom_meta',
    'videoMetaKey' => 'video_url',
    'playbackMode' => 'inline_click',
    'aspectRatio' => '16/9',
    'showPlayButton' => true,
    'showProgressBar' => true,
), '', $mock_block );
assert_test( strpos( $html_vid, 'gm-card-video-wrap' ) !== false, 'Card video renders container wrap' );
assert_test( strpos( $html_vid, 'preview.mp4' ) !== false, 'Card video resolves MP4 URL from meta' );
assert_test( strpos( $html_vid, 'gm-card-inline-video' ) !== false, 'Card video renders inline HTML5 video element' );
assert_test( strpos( $html_vid, 'gm-card-play-btn' ) !== false, 'Card video renders play button overlay' );
assert_test( strpos( $html_vid, 'gm-video-progress-bar' ) !== false, 'Card video renders progress bar' );

// 6. Test Card Video Block (Pro): Autoplay Loop mode (TikTok / Shorts 9:16)
$html_shorts = $pro_blocks->render_card_video( array(
    'videoSourceType' => 'custom_meta',
    'videoMetaKey' => 'video_url',
    'playbackMode' => 'autoplay_loop',
    'aspectRatio' => '9/16',
    'showSoundToggle' => true,
), '', $mock_block );
assert_test( strpos( $html_shorts, 'gm-mode-autoplay_loop' ) !== false, 'Card video renders autoplay_loop mode class' );
assert_test( strpos( $html_shorts, 'aspect-ratio:9/16' ) !== false, 'Card video applies 9/16 aspect ratio' );
assert_test( strpos( $html_shorts, 'gm-video-sound-toggle' ) !== false, 'Card video renders sound mute/unmute toggle' );

// 7. Test Video Lightbox with Pill Button and Dynamic Source
$html_lb = $pro_blocks->render_video_lightbox( array(
    'sourceType' => 'custom_meta',
    'videoMetaKey' => 'video_url',
    'buttonStyle' => 'pill_button',
    'buttonText' => 'Watch Lesson Video',
), '', $mock_block );
assert_test( strpos( $html_lb, 'gm-video-pill-btn' ) !== false && strpos( $html_lb, 'Watch Lesson Video' ) !== false, 'Video lightbox renders pill button with dynamic video URL' );

// 8. Test Taxonomy Tabs with Chips and Multi-select
$html_chips = \GridMaster\Facets\Handlers\Taxonomy_Tabs::render( array(
    'taxonomy' => 'category',
    'style' => 'chips_scroll',
    'multi_select' => true,
), 1 );
assert_test( strpos( $html_chips, 'gm-chips-scroll' ) !== false, 'Taxonomy tabs renders gm-chips-scroll class' );
assert_test( strpos( $html_chips, 'data-multi-select="1"' ) !== false, 'Taxonomy tabs outputs data-multi-select=1' );

echo "
Test Summary: {$passed} passed, {$failed} failed.
";
exit( $failed > 0 ? 1 : 0 );

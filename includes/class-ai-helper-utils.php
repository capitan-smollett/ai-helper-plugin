<?php
/**
 * Utils for AI Helper.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Common helper methods.
 */
class AI_Helper_Utils {
/**
 * Validate text length.
 *
 * @param string $text Text to validate.
 * @param int    $max  Max allowed length.
 *
 * @return bool
 */
public static function is_text_within_limit( $text, $max = 20000 ) {
return mb_strlen( $text ) <= $max;
}

/**
 * Check if string is URL.
 *
 * @param string $value Value to check.
 *
 * @return bool
 */
public static function is_url( $value ) {
return (bool) filter_var( $value, FILTER_VALIDATE_URL );
}

/**
 * Determine if URL belongs to current site.
 *
 * @param string $url URL.
 *
 * @return bool
 */
public static function is_internal_url( $url ) {
$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
$url_host  = wp_parse_url( $url, PHP_URL_HOST );

return $site_host && $url_host && strtolower( $site_host ) === strtolower( $url_host );
}

/**
 * Collect post data by URL.
 *
 * @param string $url URL.
 *
 * @return array|WP_Error
 */
public static function collect_post_data_from_url( $url ) {
if ( ! self::is_internal_url( $url ) ) {
return new WP_Error( 'ai_helper_external_url', __( 'URL должен относиться к текущему сайту.', 'ai-helper' ) );
}

$post_id = url_to_postid( $url );

if ( ! $post_id ) {
return new WP_Error( 'ai_helper_post_not_found', __( 'Не удалось определить запись по указанному URL.', 'ai-helper' ) );
}

$post = get_post( $post_id );

if ( ! $post ) {
return new WP_Error( 'ai_helper_post_not_found', __( 'Запись не найдена.', 'ai-helper' ) );
}

$categories       = array();
$category_objects = get_the_category( $post_id );
if ( ! empty( $category_objects ) && ! is_wp_error( $category_objects ) ) {
$categories = wp_list_pluck( $category_objects, 'name' );
}

$tags        = array();
$tag_objects = get_the_tags( $post_id );
if ( ! empty( $tag_objects ) && ! is_wp_error( $tag_objects ) ) {
$tags = wp_list_pluck( $tag_objects, 'name' );
}

$rank_math = array(
'focus_keyword'  => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
'title'          => get_post_meta( $post_id, 'rank_math_title', true ),
'description'    => get_post_meta( $post_id, 'rank_math_description', true ),
'og_title'       => get_post_meta( $post_id, 'rank_math_facebook_title', true ),
'og_description' => get_post_meta( $post_id, 'rank_math_facebook_description', true ),
);

return array(
'id'           => $post_id,
'post_title'   => get_the_title( $post_id ),
'post_content' => $post->post_content,
'excerpt'      => $post->post_excerpt,
'categories'   => $categories,
'tags'         => $tags,
'rank_math'    => $rank_math,
);
}

/**
 * Sanitize text area content.
 *
 * @param string $text Text.
 *
 * @return string
 */
public static function clean_textarea( $text ) {
return trim( wp_kses_post( wp_unslash( $text ) ) );
}

/**
 * Prepare array recursively.
 *
 * @param array $data Data.
 *
 * @return array
 */
public static function sanitize_array( $data ) {
$cleaned = array();

foreach ( (array) $data as $key => $value ) {
$clean_key = sanitize_key( $key );
if ( is_array( $value ) ) {
$cleaned[ $clean_key ] = self::sanitize_array( $value );
} else {
$cleaned[ $clean_key ] = sanitize_text_field( wp_unslash( $value ) );
}
}

return $cleaned;
}
}

<?php
/**
 * Utility helpers for AI Helper plugin.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Utility collection.
 */
class AI_Helper_Utils {
    /**
     * Determine if provided string is URL belonging to current site.
     *
     * @param string $value Raw input.
     * @return bool
     */
    public static function is_internal_url( $value ) {
        if ( empty( $value ) ) {
            return false;
        }

        $url = esc_url_raw( trim( $value ) );

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $site_url = wp_parse_url( home_url() );
        $input    = wp_parse_url( $url );

        return isset( $input['host'], $site_url['host'] ) && strtolower( $input['host'] ) === strtolower( $site_url['host'] );
    }

    /**
     * Fetch post details from URL.
     *
     * @param string $url Post URL.
     * @return array
     */
    public static function extract_post_data( $url ) {
        $post_id = url_to_postid( $url );

        if ( ! $post_id ) {
            return array();
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            return array();
        }

        $seo_meta = array();

        if ( class_exists( '\\RankMath\\Helper' ) ) {
            $seo_meta['rank_math_focus_keyword'] = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
            $seo_meta['rank_math_title']         = get_post_meta( $post_id, 'rank_math_title', true );
            $seo_meta['rank_math_description']   = get_post_meta( $post_id, 'rank_math_description', true );
        }

        return array(
            'ID'         => $post_id,
            'title'      => get_the_title( $post_id ),
            'content'    => apply_filters( 'the_content', $post->post_content ),
            'excerpt'    => wp_strip_all_tags( $post->post_excerpt ),
            'categories' => wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ),
            'tags'       => wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ),
            'seo_meta'   => $seo_meta,
        );
    }

    /**
     * Sanitize HTML to remove script tags.
     *
     * @param string $html HTML string.
     * @return string
     */
    public static function sanitize_html( $html ) {
        if ( empty( $html ) ) {
            return '';
        }

        return wp_kses( $html, self::allowed_html_tags() );
    }

    /**
     * Allowed HTML tags for output.
     *
     * @return array
     */
    public static function allowed_html_tags() {
        $allowed = wp_kses_allowed_html( 'post' );
        unset( $allowed['script'] );

        return $allowed;
    }
}

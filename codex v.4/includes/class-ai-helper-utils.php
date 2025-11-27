<?php
/**
 * Utility helpers for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Helper_Utils {
    /**
     * Validate if string is internal URL.
     *
     * @param string $maybe_url Potential URL.
     * @return bool
     */
    public static function is_internal_url( $maybe_url ) {
        if ( empty( $maybe_url ) || ! filter_var( $maybe_url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $site_url = home_url();
        return strpos( trailingslashit( $maybe_url ), trailingslashit( $site_url ) ) === 0;
    }

    /**
     * Extract post data by URL.
     *
     * @param string $url URL.
     * @return array|null
     */
    public static function extract_post_data( $url ) {
        if ( ! self::is_internal_url( $url ) ) {
            return null;
        }

        $post_id = url_to_postid( $url );

        if ( ! $post_id ) {
            return null;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        $data = array(
            'id'           => $post_id,
            'post_title'   => $post->post_title,
            'post_content' => wp_strip_all_tags( $post->post_content, true ),
            'excerpt'      => $post->post_excerpt,
            'categories'   => wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) ),
            'tags'         => wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) ),
        );

        if ( function_exists( 'get_post_meta' ) ) {
            $data['rank_math_focus_keyword'] = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
            $data['rank_math_description']   = get_post_meta( $post_id, 'rank_math_description', true );
        }

        return $data;
    }

    /**
     * Sanitize HTML by stripping scripts.
     *
     * @param string $html HTML content.
     * @return string
     */
    public static function sanitize_html( $html ) {
        $allowed_tags = wp_kses_allowed_html( 'post' );
        unset( $allowed_tags['script'] );

        return wp_kses( $html, $allowed_tags );
    }
}

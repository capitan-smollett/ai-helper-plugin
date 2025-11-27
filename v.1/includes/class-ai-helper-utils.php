<?php
/**
 * Utility helpers for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Utilities collection.
 */
class AI_Helper_Utils {
    /**
     * Find the nearest free menu position after posts.
     *
     * @return float
     */
    public static function get_menu_position_after_posts() {
        global $menu;

        $posts_position = 5;
        $position       = 6;

        if ( ! is_array( $menu ) ) {
            return $position;
        }

        $positions = wp_list_pluck( $menu, 2 );

        while ( in_array( (string) $position, $positions, true ) ) {
            $position += 0.1;
        }

        return $position;
    }

    /**
     * Sanitize and trim long text.
     *
     * @param string $text Raw text.
     * @param int    $limit Max characters.
     *
     * @return string
     */
    public static function sanitize_text_input( $text, $limit = 20000 ) {
        $text    = wp_unslash( (string) $text );
        $allowed = wp_kses_allowed_html( 'post' );
        unset( $allowed['script'] );
        $text = wp_kses( $text, $allowed );

        if ( mb_strlen( $text ) > $limit ) {
            $text = mb_substr( $text, 0, $limit );
        }

        return trim( $text );
    }

    /**
     * Determine whether a URL belongs to the current site.
     *
     * @param string $url URL.
     *
     * @return bool
     */
    public static function is_internal_url( $url ) {
        $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $url_host  = wp_parse_url( $url, PHP_URL_HOST );

        return $home_host && $url_host && strtolower( $home_host ) === strtolower( $url_host );
    }

    /**
     * Extract post data for a given URL.
     *
     * @param string $url URL to inspect.
     *
     * @return array
     */
    public static function extract_post_data_from_url( $url ) {
        $post_id = url_to_postid( $url );

        if ( ! $post_id ) {
            return array();
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            return array();
        }

        $categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
        $tags       = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
        $rank_math  = array(
            'focus_keyword'    => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
            'focus_keywords'   => get_post_meta( $post_id, 'rank_math_focus_keywords', true ),
            'seo_title'        => get_post_meta( $post_id, 'rank_math_title', true ),
            'seo_description'  => get_post_meta( $post_id, 'rank_math_description', true ),
            'og_title'         => get_post_meta( $post_id, 'rank_math_facebook_title', true ),
            'og_description'   => get_post_meta( $post_id, 'rank_math_facebook_description', true ),
        );

        return array(
            'id'         => $post_id,
            'title'      => get_the_title( $post ),
            'content'    => $post->post_content,
            'excerpt'    => $post->post_excerpt,
            'categories' => $categories,
            'tags'       => $tags,
            'rank_math'  => array_filter( $rank_math ),
        );
    }

    /**
     * Prepare HTML for safe previewing.
     *
     * @param string $html Raw HTML.
     *
     * @return string
     */
    public static function clean_result_html( $html ) {
        $allowed = wp_kses_allowed_html( 'post' );
        unset( $allowed['script'] );

        return wp_kses( (string) $html, $allowed );
    }
}

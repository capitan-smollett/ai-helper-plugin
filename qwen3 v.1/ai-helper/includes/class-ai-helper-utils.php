<?php
/**
 * Utility functions
 */

class AI_Helper_Utils {

	public static function parse_post_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		return [
			'post_id'     => $post_id,
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'excerpt'     => ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 ),
			'categories'  => wp_get_post_categories( $post_id, [ 'fields' => 'names' ] ),
			'tags'        => wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ),
			'rank_math'   => self::get_rank_math_data( $post_id ),
		];
	}

	public static function get_rank_math_data( $post_id ) {
		$data = [];

		if ( class_exists( 'RankMath\Post_Meta' ) ) {
			$meta = \RankMath\Post_Meta::get_meta( $post_id );
			$data = [
				'title'            => $meta['title'] ?? '',
				'description'      => $meta['description'] ?? '',
				'focus_keyword'    => $meta['focus_keyword'] ?? '',
				'primary_taxonomy' => $meta['primary_taxonomy'] ?? '',
				'robots'           => $meta['robots'] ?? [],
			];
		}

		return $data;
	}
}

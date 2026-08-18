<?php
/**
 * Plugin Name:       WP Make Block Editor Links Relative
 * Plugin URI:        https://github.com/derpixler/wp-make-gutenberg-links-relative
 * Description:       Stops the block editor from baking hard-coded domains into your content. Your site's own URLs are stored and rendered root-relative, so staging, production and every future domain change just work.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            derpixler
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-make-block-editor-links-relative
 *
 * @package WP_Make_Block_Editor_Links_Relative
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/functions.php';

if ( ! function_exists( 'wp_mglr_get_base_urls' ) ) {
	/**
	 * Collect the site's own base URLs (home, site, content URLs).
	 *
	 * @return string[] Base URLs, scheme + host + optional port (e.g. "https://example.com").
	 */
	function wp_mglr_get_base_urls() {
		$bases = array();

		foreach ( array( home_url(), site_url(), content_url() ) as $url ) {
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$port   = wp_parse_url( $url, PHP_URL_PORT );

			if ( '' === $host || '' === $scheme ) {
				continue;
			}

			$base = $scheme . '://' . $host . ( $port ? ':' . $port : '' );
			if ( ! in_array( $base, $bases, true ) ) {
				$bases[] = $base;
			}
		}

		return $bases;
	}
}

if ( ! function_exists( 'wp_mglr_make_content_relative' ) ) {
	/**
	 * Filter callback: normalize a content string.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	function wp_mglr_make_content_relative( $content ) {
		return wp_mglr_make_relative( $content );
	}
}

if ( ! function_exists( 'wp_mglr_rest_pre_insert_post' ) ) {
	/**
	 * Normalize post_content before the block editor (REST) persists it.
	 *
	 * @param stdClass|WP_Post $prepared_post Prepared post object.
	 * @param WP_REST_Request  $request       Request object.
	 * @return stdClass|WP_Post
	 */
	function wp_mglr_rest_pre_insert_post( $prepared_post, $request ) {
		if ( isset( $prepared_post->post_content ) && is_string( $prepared_post->post_content ) ) {
			$prepared_post->post_content = wp_mglr_make_relative( $prepared_post->post_content );
		}

		return $prepared_post;
	}
}

if ( function_exists( 'add_filter' ) ) {
	// Render-time normalization (output layer): never emit a baked-in domain.
	add_filter( 'the_content', 'wp_mglr_make_content_relative', 99 );
	add_filter( 'the_excerpt', 'wp_mglr_make_content_relative', 99 );
	add_filter( 'widget_block_content', 'wp_mglr_make_content_relative', 99 );
	add_filter( 'widget_text_content', 'wp_mglr_make_content_relative', 99 );

	/**
	 * Filters whether the save-time normalization is active.
	 *
	 * Disable this (return false) if you prefer to keep absolute URLs in the
	 * database and rely on the render-time layer only.
	 *
	 * @param bool $enabled Whether save-time normalization is active. Default true.
	 */
	if ( apply_filters( 'wp_mglr_enable_save_normalization', true ) ) {
		add_filter( 'content_save_pre', 'wp_mglr_make_content_relative', 10 );
		add_filter( 'rest_pre_insert_post', 'wp_mglr_rest_pre_insert_post', 10, 2 );
	}
}

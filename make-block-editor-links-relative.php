<?php
/**
 * Plugin Name:       Make Block Editor Links Relative
 * Plugin URI:        https://github.com/derpixler/make-block-editor-links-relative
 * Description:       Stops the block editor from baking hard-coded domains into your content. Your site's own URLs are stored and rendered root-relative, so staging, production and every future domain change just work.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            derpixler
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       make-block-editor-links-relative
 *
 * @package Make_Block_Editor_Links_Relative
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/functions.php';

if ( ! function_exists( 'mbelr_get_base_urls' ) ) {
	/**
	 * Collect the site's own base URLs (home, site, content URLs).
	 *
	 * @return string[] Base URLs, scheme + host + optional port (e.g. "https://example.com").
	 */
	function mbelr_get_base_urls() {
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

if ( ! function_exists( 'mbelr_make_content_relative' ) ) {
	/**
	 * Filter callback: normalize a content string.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	function mbelr_make_content_relative( $content ) {
		return mbelr_make_relative( $content );
	}
}

if ( ! function_exists( 'mbelr_feed_content_absolute' ) ) {
	/**
	 * Filter callback: restore absolute URLs for feed output.
	 *
	 * `the_content_feed` and `the_excerpt_rss` only fire while building RSS/Atom
	 * feeds, after block markup has already been rendered to HTML — the right,
	 * isolated place to undo root-relative URLs for readers with no site origin
	 * of their own to resolve them against.
	 *
	 * @param string $content Feed content.
	 * @return string
	 */
	function mbelr_feed_content_absolute( $content ) {
		return mbelr_make_absolute( $content );
	}
}

if ( ! function_exists( 'mbelr_rest_prepare_absolute' ) ) {
	/**
	 * Restore absolute URLs in a REST post response, except for editor requests.
	 *
	 * `context=edit` is how the block editor (and any other client with
	 * `edit_post` capability) asks for the editing representation of a post;
	 * WordPress enforces the capability check before this filter ever runs, so
	 * it cannot be spoofed by an anonymous or lower-privileged client. Every
	 * other context (the default `view`, or `embed`) is public read access —
	 * exactly the headless-frontend / third-party-consumer case that has no
	 * site origin of its own to resolve a root-relative "/path" against.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_Post          $post     Post object.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response
	 */
	function mbelr_rest_prepare_absolute( $response, $post, $request ) {
		if ( 'edit' === $request->get_param( 'context' ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( isset( $data['content']['rendered'] ) && is_string( $data['content']['rendered'] ) ) {
			$data['content']['rendered'] = mbelr_make_absolute( $data['content']['rendered'] );
		}

		if ( isset( $data['excerpt']['rendered'] ) && is_string( $data['excerpt']['rendered'] ) ) {
			$data['excerpt']['rendered'] = mbelr_make_absolute( $data['excerpt']['rendered'] );
		}

		$response->set_data( $data );

		return $response;
	}
}

if ( ! function_exists( 'mbelr_register_rest_absolute_filters' ) ) {
	/**
	 * Hook mbelr_rest_prepare_absolute() into every REST-exposed post type.
	 *
	 * WordPress fires a per-post-type filter ("rest_prepare_{$post_type}"),
	 * so there is no single generic hook to attach to.
	 */
	function mbelr_register_rest_absolute_filters() {
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'names' ) as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", 'mbelr_rest_prepare_absolute', 10, 3 );
		}
	}
}

if ( ! function_exists( 'mbelr_rest_pre_insert_post' ) ) {
	/**
	 * Normalize post_content before the block editor (REST) persists it.
	 *
	 * @param stdClass|WP_Post $prepared_post Prepared post object.
	 * @param WP_REST_Request  $request       Request object.
	 * @return stdClass|WP_Post
	 */
	function mbelr_rest_pre_insert_post( $prepared_post, $request ) {
		if ( isset( $prepared_post->post_content ) && is_string( $prepared_post->post_content ) ) {
			$prepared_post->post_content = mbelr_make_relative( $prepared_post->post_content );
		}

		return $prepared_post;
	}
}

if ( function_exists( 'add_filter' ) ) {
	// Render-time normalization (output layer): never emit a baked-in domain.
	add_filter( 'the_content', 'mbelr_make_content_relative', 99 );
	add_filter( 'the_excerpt', 'mbelr_make_content_relative', 99 );
	add_filter( 'widget_block_content', 'mbelr_make_content_relative', 99 );
	add_filter( 'widget_text_content', 'mbelr_make_content_relative', 99 );

	/**
	 * Filters whether feed output gets its URLs restored to absolute.
	 *
	 * RSS/Atom readers have no site origin of their own to resolve a
	 * root-relative "/path" against, so by default this restores the site's
	 * own URLs to absolute for feed consumers only — the database and normal
	 * page rendering are unaffected.
	 *
	 * @param bool $enabled Whether feed absolutization is active. Default true.
	 */
	if ( apply_filters( 'mbelr_enable_feed_absolutization', true ) ) {
		add_filter( 'the_content_feed', 'mbelr_feed_content_absolute', 10 );
		add_filter( 'the_excerpt_rss', 'mbelr_feed_content_absolute', 10 );
	}

	/**
	 * Filters whether public REST responses get their URLs restored to absolute.
	 *
	 * A headless/decoupled frontend or any other third-party REST consumer
	 * reading `content.rendered` / `excerpt.rendered` has no site origin of its
	 * own to resolve a root-relative "/path" against, so by default this
	 * restores absolute URLs for every REST context except `edit` (the block
	 * editor's own reads, which are expected to work relative like the rest of
	 * wp-admin).
	 *
	 * @param bool $enabled Whether REST absolutization is active. Default true.
	 */
	if ( apply_filters( 'mbelr_enable_rest_absolutization', true ) ) {
		add_action( 'rest_api_init', 'mbelr_register_rest_absolute_filters' );
	}

	/**
	 * Filters whether the save-time normalization is active.
	 *
	 * Disable this (return false) if you prefer to keep absolute URLs in the
	 * database and rely on the render-time layer only.
	 *
	 * @param bool $enabled Whether save-time normalization is active. Default true.
	 */
	if ( apply_filters( 'mbelr_enable_save_normalization', true ) ) {
		add_filter( 'content_save_pre', 'mbelr_make_content_relative', 10 );
		add_filter( 'rest_pre_insert_post', 'mbelr_rest_pre_insert_post', 10, 2 );
	}
}

<?php
/**
 * Core URL-normalization logic.
 *
 * This file is intentionally free of WordPress dependencies so the
 * normalization can be unit-tested in isolation. The plugin entry point
 * (make-block-editor-links-relative.php) wires these functions into
 * WordPress hooks and supplies the real base-URL list.
 *
 * @package Make_Block_Editor_Links_Relative
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_mglr_make_relative' ) ) {
	/**
	 * Convert a site's own absolute URLs to root-relative URLs.
	 *
	 * Both plain URLs (https://host/path) and JSON-escaped URLs used inside
	 * block attributes (https:\/\/host\/path) are handled. External URLs are
	 * left untouched.
	 *
	 * @param string        $content   Raw content (HTML or serialized block markup).
	 * @param string[]|null $base_urls Base URLs to strip (scheme + host + optional port,
	 *                                 e.g. "https://example.com"). When null, the list is
	 *                                 detected via WordPress.
	 * @return string
	 */
	function wp_mglr_make_relative( $content, $base_urls = null ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( null === $base_urls ) {
			$base_urls = function_exists( 'wp_mglr_get_base_urls' ) ? wp_mglr_get_base_urls() : array();
		}

		foreach ( (array) $base_urls as $base ) {
			$base = trim( (string) $base );
			if ( '' === $base ) {
				continue;
			}
			$content = wp_mglr_strip_host( $content, $base );
		}

		return $content;
	}
}

if ( ! function_exists( 'wp_mglr_strip_host' ) ) {
	/**
	 * Strip a single base URL from all absolute URLs in the given content.
	 *
	 * @param string $content Content to process.
	 * @param string $base    Base URL to strip, scheme included (e.g. "https://example.com").
	 * @return string
	 */
	function wp_mglr_strip_host( $content, $base ) {
		$guard = '(?![a-z0-9.-])'; // Never match example.com inside example.com.evil.tld.

		// 1) Plain absolute URLs: https://example.com.
		$content = preg_replace(
			'#' . preg_quote( $base, '#' ) . $guard . '#i',
			'',
			$content
		);

		// 2) JSON-escaped URLs (block attributes): https:\/\/example.com.
		$escaped = str_replace( '//', '\\/\\/', $base );
		$content = preg_replace(
			'#' . preg_quote( $escaped, '#' ) . $guard . '#i',
			'',
			$content
		);

		return $content;
	}
}

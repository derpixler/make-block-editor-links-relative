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

if ( ! function_exists( 'mbelr_make_relative' ) ) {
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
	function mbelr_make_relative( $content, $base_urls = null ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( null === $base_urls ) {
			$base_urls = function_exists( 'mbelr_get_base_urls' ) ? mbelr_get_base_urls() : array();
		}

		foreach ( (array) $base_urls as $base ) {
			$base = trim( (string) $base );
			if ( '' === $base ) {
				continue;
			}
			$content = mbelr_strip_host( $content, $base );
		}

		return $content;
	}
}

if ( ! function_exists( 'mbelr_make_absolute' ) ) {
	/**
	 * Restore root-relative URLs to absolute URLs.
	 *
	 * Used for output that is consumed outside the site's own origin (RSS/Atom
	 * feeds), where a root-relative "/path" has no browser location to resolve
	 * against and would be meaningless to the reader.
	 *
	 * By the time feed hooks run, block markup has already been rendered to
	 * plain HTML, so only real href/src/srcset attributes need restoring —
	 * unlike mbelr_make_relative(), there is no JSON-escaped form to handle here.
	 *
	 * @param string        $content   Rendered HTML.
	 * @param string[]|null $base_urls Base URLs; the first one is used as the
	 *                                 canonical host to restore. When null, the
	 *                                 list is detected via WordPress.
	 * @return string
	 */
	function mbelr_make_absolute( $content, $base_urls = null ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( null === $base_urls ) {
			$base_urls = function_exists( 'mbelr_get_base_urls' ) ? mbelr_get_base_urls() : array();
		}

		$base = isset( $base_urls[0] ) ? trim( (string) $base_urls[0] ) : '';
		if ( '' === $base ) {
			return $content;
		}

		return mbelr_add_host( $content, $base );
	}
}

if ( ! function_exists( 'mbelr_add_host' ) ) {
	/**
	 * Prepend a base URL to root-relative href/src/srcset values.
	 *
	 * Protocol-relative URLs ("//host/path") are left untouched.
	 *
	 * @param string $content Content to process.
	 * @param string $base    Base URL to restore, scheme included (e.g. "https://example.com").
	 * @return string
	 */
	function mbelr_add_host( $content, $base ) {
		// href="/path", src='/path', poster="/path" — single-value attributes.
		$content = preg_replace(
			'#\b(href|src|poster)(\s*=\s*)([\'"])/(?!/)#i',
			'$1$2$3' . $base . '/',
			$content
		);

		// srcset="/a 300w, /b 600w" — comma-separated list of candidate URLs.
		$content = preg_replace_callback(
			'#\bsrcset(\s*=\s*)([\'"])(.*?)\2#i',
			function ( $matches ) use ( $base ) {
				$entries = array_map(
					function ( $entry ) use ( $base ) {
						$leading = substr( $entry, 0, strspn( $entry, " \t\n\r" ) );
						$value   = substr( $entry, strlen( $leading ) );
						$is_root_relative = isset( $value[0] ) && '/' === $value[0]
							&& ( ! isset( $value[1] ) || '/' !== $value[1] );

						return $is_root_relative ? $leading . $base . $value : $entry;
					},
					explode( ',', $matches[3] )
				);

				return 'srcset' . $matches[1] . $matches[2] . implode( ',', $entries ) . $matches[2];
			},
			$content
		);

		return $content;
	}
}

if ( ! function_exists( 'mbelr_strip_host' ) ) {
	/**
	 * Strip a single base URL from all absolute URLs in the given content.
	 *
	 * @param string $content Content to process.
	 * @param string $base    Base URL to strip, scheme included (e.g. "https://example.com").
	 * @return string
	 */
	function mbelr_strip_host( $content, $base ) {
		$guard = '(?![a-z0-9.-])'; // Never match example.com inside example.com.evil.tld.

		// 1) Plain absolute URLs: https://example.com.
		// Strip the host when a path follows; otherwise substitute the host
		// with "/" so a bare host becomes root-relative instead of empty.
		$content = preg_replace(
			'#' . preg_quote( $base, '#' ) . '(?=/)#i',
			'',
			$content
		);
		$content = preg_replace(
			'#' . preg_quote( $base, '#' ) . $guard . '#i',
			'/',
			$content
		);

		// 2) JSON-escaped URLs (block attributes): https:\/\/example.com.
		$escaped = str_replace( '//', '\\/\\/', $base );
		$content = preg_replace(
			'#' . preg_quote( $escaped, '#' ) . '(?=\\\/)#i',
			'',
			$content
		);
		$content = preg_replace(
			'#' . preg_quote( $escaped, '#' ) . $guard . '#i',
			'\\/',
			$content
		);

		return $content;
	}
}

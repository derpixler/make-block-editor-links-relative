<?php
/**
 * Unit tests for the URL-normalization core.
 *
 * No WordPress bootstrap required — the pure functions in src/functions.php
 * are exercised directly with an explicit base-URL list.
 *
 * @package WP_Make_Block_Editor_Links_Relative
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MakeRelativeTest extends TestCase {

	#[DataProvider( 'plain_url_provider' )]
	public function test_makes_own_plain_urls_relative( $input, $expected ) {
		$this->assertSame( $expected, wp_mglr_make_relative( $input, array( 'https://example.com' ) ) );
	}

	public static function plain_url_provider() {
		return array(
			'https path'             => array( 'https://example.com/path', '/path' ),
			'other scheme untouched' => array( 'http://example.com/path', 'http://example.com/path' ),
			'protocol relative untouched' => array( '//example.com/path', '//example.com/path' ),
			'bare host'              => array( 'https://example.com', '' ),
			'trailing slash'         => array( 'https://example.com/', '/' ),
			'query and fragment'     => array( 'https://example.com/path?q=1#frag', '/path?q=1#frag' ),
			'inline anchor'          => array( '<a href="https://example.com/path">x</a>', '<a href="/path">x</a>' ),
			'inline image'           => array( '<img src="https://example.com/wp-content/x.jpg">', '<img src="/wp-content/x.jpg">' ),
			'uppercase scheme'       => array( 'HTTPS://EXAMPLE.COM/path', '/path' ),
			'mixed case host'        => array( 'https://Example.Com/path', '/path' ),
			'external untouched'     => array( 'https://example.org/path', 'https://example.org/path' ),
			'subdomain not stripped' => array( 'https://example.com.evil.com/path', 'https://example.com.evil.com/path' ),
			'www not stripped'       => array( 'https://www.example.com/path', 'https://www.example.com/path' ),
			'hyphen suffix safe'     => array( 'https://example.com-foo.com/path', 'https://example.com-foo.com/path' ),
			'mixed content'          => array(
				'go to https://example.com/a and https://example.org/b',
				'go to /a and https://example.org/b',
			),
		);
	}

	#[DataProvider( 'escaped_url_provider' )]
	public function test_makes_own_json_escaped_urls_relative( $input, $expected ) {
		$this->assertSame( $expected, wp_mglr_make_relative( $input, array( 'https://example.com' ) ) );
	}

	public static function escaped_url_provider() {
		return array(
			'escaped https'          => array( 'https:\/\/example.com\/path', '\/path' ),
			'escaped other scheme untouched' => array( 'http:\/\/example.com\/path', 'http:\/\/example.com\/path' ),
			'escaped protocol rel untouched' => array( '\/\/example.com\/path', '\/\/example.com\/path' ),
			'block attribute'        => array(
				'{"url":"https:\/\/example.com\/wp-content\/uploads\/x.jpg"}',
				'{"url":"\/wp-content\/uploads\/x.jpg"}',
			),
			'block markup'           => array(
				'<!-- wp:image {"url":"https:\/\/example.com\/x.jpg","id":1} -->',
				'<!-- wp:image {"url":"\/x.jpg","id":1} -->',
			),
			'escaped external safe'  => array( 'https:\/\/example.org\/path', 'https:\/\/example.org\/path' ),
			'escaped subdomain safe' => array( 'https:\/\/example.com.evil.com\/path', 'https:\/\/example.com.evil.com\/path' ),
		);
	}

	#[DataProvider( 'base_urls_provider' )]
	public function test_strips_multiple_base_urls( $input, $expected, $base_urls ) {
		$this->assertSame( $expected, wp_mglr_make_relative( $input, $base_urls ) );
	}

	public static function base_urls_provider() {
		return array(
			'multiple base urls' => array(
				'https://example.com/a https://cdn.example.net/b https://elsewhere.org/c',
				'/a /b https://elsewhere.org/c',
				array( 'https://example.com', 'https://cdn.example.net' ),
			),
			'base url with port' => array(
				'https://example.com:8080/a',
				'/a',
				array( 'https://example.com:8080' ),
			),
			'base url with whitespace' => array(
				'https://example.com/a',
				'/a',
				array( ' https://example.com ' ),
			),
		);
	}

	public function test_returns_non_string_unchanged() {
		$this->assertSame( 42, wp_mglr_make_relative( 42, array( 'https://example.com' ) ) );
		$this->assertNull( wp_mglr_make_relative( null, array( 'https://example.com' ) ) );
		$this->assertSame( '', wp_mglr_make_relative( '', array( 'https://example.com' ) ) );
	}

	public function test_null_base_urls_without_wordpress_is_noop() {
		// In the unit context wp_mglr_get_base_urls() (a WP helper) is undefined,
		// so a null base-URL list must simply leave the content untouched.
		$this->assertSame( 'https://example.com/a', wp_mglr_make_relative( 'https://example.com/a' ) );
	}
}

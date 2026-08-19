const { test, expect } = require( '@playwright/test' );

const SITE_URL = process.env.WP_ENV_SITE_URL || 'http://localhost:8888';
const INTERNAL_PATH = '/internal-target/';

test( 'RSS feed content restores root-relative URLs back to absolute', async ( { request } ) => {
	const response = await request.get( SITE_URL + '/feed/' );
	expect( response.ok() ).toBeTruthy();

	const body = await response.text();

	// A feed reader has no site of its own to resolve a root-relative "/path"
	// against, so the internal link must come back absolute inside
	// <content:encoded> — the exact opposite of the render layer.
	expect( body ).toContain( 'href="' + SITE_URL + INTERNAL_PATH + '"' );
	expect( body ).not.toContain( 'href="' + INTERNAL_PATH + '"' );

	// External link is untouched by any layer.
	expect( body ).toContain( 'href="https://example.org/ext"' );
} );

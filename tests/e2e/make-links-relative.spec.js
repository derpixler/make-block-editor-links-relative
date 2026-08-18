const { test, expect } = require( '@playwright/test' );

const SITE_URL = process.env.WP_ENV_SITE_URL || 'http://localhost:8888';
const INTERNAL_PATH = '/internal-target/';

test( 'renders legacy absolute internal URLs root-relative and keeps external URLs absolute', async ( { page, request } ) => {
	// Locate the seeded post via the public REST endpoint (no auth needed for
	// published posts).
	const response = await request.get( SITE_URL + '/wp-json/wp/v2/posts', {
		params: { slug: 'e2e-links-relative' },
	} );
	expect( response.ok() ).toBeTruthy();

	const posts = await response.json();
	expect( posts.length ).toBeGreaterThan( 0 );

	await page.goto( posts[ 0 ].link );

	// Internal link: rendered root-relative (domain baked out on render).
	await expect( page.locator( 'a[href="' + INTERNAL_PATH + '"]' ) ).toHaveCount( 1 );

	// The absolute internal URL must be gone from the rendered HTML.
	await expect(
		page.locator( 'a[href="' + SITE_URL + INTERNAL_PATH + '"]' )
	).toHaveCount( 0 );

	// External link: must stay absolute.
	await expect( page.locator( 'a[href="https://example.org/ext"]' ) ).toHaveCount( 1 );
} );

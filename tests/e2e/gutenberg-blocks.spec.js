const { test, expect } = require( '@playwright/test' );
const { SITE_URL, readAppPassword, basicAuth, landingPageBlocks } = require( './helpers' );

const PAGE_SLUG = 'e2e-landing-page';

test( 'Gutenberg save path stores link-generating blocks root-relative', async ( { page, request } ) => {
	const auth = basicAuth( 'admin', readAppPassword() );

	// Idempotency: drop any landing page from a previous run.
	const previous = await request.get( SITE_URL + '/wp-json/wp/v2/pages', {
		params: { slug: PAGE_SLUG },
	} );
	for ( const p of await previous.json() ) {
		await request.delete( SITE_URL + '/wp-json/wp/v2/pages/' + p.id, {
			params: { force: true },
			headers: { Authorization: auth },
		} );
	}

	// Create a landing page through the real block-editor save endpoint
	// (POST /wp/v2/pages → rest_pre_insert_post).
	const create = await request.post( SITE_URL + '/wp-json/wp/v2/pages', {
		headers: { Authorization: auth },
		data: {
			title: 'E2E Landing Page',
			slug: PAGE_SLUG,
			status: 'publish',
			content: landingPageBlocks( SITE_URL ),
		},
	} );
	expect( create.ok() ).toBeTruthy();
	const created = await create.json();

	// Save layer: the raw stored content must no longer contain the absolute
	// domain — every link-generating block attribute was stripped before write.
	const fetched = await request.get( SITE_URL + '/wp-json/wp/v2/pages/' + created.id, {
		params: { context: 'edit' },
		headers: { Authorization: auth },
	} );
	expect( fetched.ok() ).toBeTruthy();
	const raw = ( await fetched.json() ).content.raw;
	expect( raw ).not.toContain( SITE_URL );
	expect( raw ).toContain( '"/wp-content/uploads/2026/08/hero.jpg"' );

	// Render layer: the frontend resolves every block link root-relative,
	// external links stay absolute.
	await page.goto( created.link );

	await expect( page.locator( 'a[href="/about/"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'a[href="/pricing/"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'a[href="/cta/"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'a[href="/service-a/"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'img[src="/wp-content/uploads/2026/08/hero.jpg"]' ) ).toHaveCount( 1 );

	// External link untouched.
	await expect( page.locator( 'a[href="https://example.org/external"]' ) ).toHaveCount( 1 );

	// No absolute internal URL may remain.
	await expect( page.locator( 'a[href="' + SITE_URL + '/about/"]' ) ).toHaveCount( 0 );
} );

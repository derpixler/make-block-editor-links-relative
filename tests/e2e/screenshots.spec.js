const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { SITE_URL, readAppPassword, basicAuth } = require( './helpers' );

const PAGE_SLUG = 'demo-links-relative';
const ASSETS_DIR = path.join( __dirname, '..', '..', 'assets' );

test.use( { viewport: { width: 1280, height: 720 } } );
test.describe.configure( { mode: 'serial' } );

function landingContent( siteUrl, imageUrl ) {
	return [
		'<!-- wp:heading --><h2 class="wp-block-heading"><a href="' + siteUrl + '/about/">About us</a></h2><!-- /wp:heading -->',
		'',
		'<!-- wp:paragraph --><p>Read more <a href="' + siteUrl + '/pricing/">here</a>.</p><!-- /wp:paragraph -->',
		'',
		'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' + siteUrl + '/cta/">Get started</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
		'',
		'<!-- wp:image {"url":"' + imageUrl + '"} --><figure class="wp-block-image"><img src="' + imageUrl + '" alt=""/></figure><!-- /wp:image -->',
		'',
		'<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><a href="' + siteUrl + '/service-a/">Service A</a></li><!-- /wp:list-item --><!-- wp:list-item --><li><a href="https://example.org/external">External</a></li><!-- /wp:list-item --></ul><!-- /wp:list -->',
	].join( '\n' );
}

test( 'capture plugin screenshots', async ( { page, request } ) => {
	test.setTimeout( 180000 );

	const auth = basicAuth( 'admin', readAppPassword() );

	// Upload a real image so the demo page doesn't show a broken placeholder.
	const heroPath = path.join( ASSETS_DIR, 'hero.jpg' );
	const upload = await request.post( SITE_URL + '/wp-json/wp/v2/media', {
		headers: { Authorization: auth },
		multipart: {
			file: { name: 'hero.jpg', mimeType: 'image/jpeg', buffer: fs.readFileSync( heroPath ) },
		},
	} );
	expect( upload.ok() ).toBeTruthy();
	const imageUrl = ( await upload.json() ).source_url;

	// Idempotency: drop any demo page from a previous run.
	const previous = await request.get( SITE_URL + '/wp-json/wp/v2/pages', {
		params: { slug: PAGE_SLUG },
	} );
	for ( const p of await previous.json() ) {
		await request.delete( SITE_URL + '/wp-json/wp/v2/pages/' + p.id, {
			params: { force: true },
			headers: { Authorization: auth },
		} );
	}

	// Create a demo landing page through the real block-editor save endpoint so
	// the save layer strips the domain before it is written.
	const create = await request.post( SITE_URL + '/wp-json/wp/v2/pages', {
		headers: { Authorization: auth },
		data: {
			title: 'Demo',
			slug: PAGE_SLUG,
			status: 'publish',
			content: landingContent( SITE_URL, imageUrl ),
		},
	} );
	expect( create.ok() ).toBeTruthy();
	const created = await create.json();

	// 1) Frontend: the rendered page with working root-relative links.
	await page.goto( created.link );
	await expect( page.locator( 'a[href="/cta/"]' ) ).toHaveCount( 1 );
	await page.screenshot( { path: path.join( ASSETS_DIR, 'screenshot-1.png' ) } );

	// Login for the editor screenshots.
	await page.goto( SITE_URL + '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	// 2) Visual block editor with the demo page loaded.
	await page.goto( SITE_URL + '/wp-admin/post.php?post=' + created.id + '&action=edit' );
	const welcomeClose = page.getByRole( 'button', { name: 'Close' } );
	if ( await welcomeClose.isVisible().catch( () => false ) ) {
		await welcomeClose.click();
	}
	// The editor mode is persisted per user, so a previous run may have left it
	// in code-editor mode. Reset to the visual editor for a stable screenshot.
	const exitCode = page.getByRole( 'button', { name: 'Exit code editor' } );
	if ( await exitCode.isVisible().catch( () => false ) ) {
		await exitCode.click();
	}
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await canvas.getByRole( 'link', { name: 'About us' } ).waitFor( { state: 'visible', timeout: 30000 } );
	await page.screenshot( { path: path.join( ASSETS_DIR, 'screenshot-2.png' ) } );

	// 3) Code editor: the stored markup is domain-free.
	await page.getByRole( 'button', { name: 'Options', exact: true } ).click();
	await page.getByRole( 'menuitemradio', { name: 'Code editor' } ).click();
	const codeArea = page.locator( 'textarea.editor-post-text-editor' );
	await codeArea.waitFor( { state: 'visible', timeout: 30000 } );
	await expect( codeArea ).toHaveValue( /src="\/wp-content\/uploads/ );
	await page.screenshot( { path: path.join( ASSETS_DIR, 'screenshot-3.png' ) } );
} );

const { test, expect } = require( '@playwright/test' );
const { SITE_URL, readAppPassword, basicAuth } = require( './helpers' );

test.setTimeout( 120000 );

test( 'a link inserted in the block editor is stored domain-free', async ( { page, request } ) => {
	// Login.
	await page.goto( SITE_URL + '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	// New post.
	await page.goto( SITE_URL + '/wp-admin/post-new.php' );

	// Dismiss the first-run "Welcome to the editor" dialog (top-level frame).
	const welcomeClose = page.getByRole( 'button', { name: 'Close' } );
	if ( await welcomeClose.isVisible().catch( () => false ) ) {
		await welcomeClose.click();
	}

	// The editor mode is persisted per user, so a previous run may have left it
	// in code-editor mode. Reset to the visual editor for a stable test.
	const exitCode = page.getByRole( 'button', { name: 'Exit code editor' } );
	if ( await exitCode.isVisible().catch( () => false ) ) {
		await exitCode.click();
	}

	// The block editor canvas lives in its own iframe.
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );

	const title = canvas.getByRole( 'textbox', { name: 'Add title' } );
	await title.waitFor( { state: 'visible', timeout: 30000 } );
	await title.click();
	await page.keyboard.type( 'E2E UI Links' );

	// Type a URL in the paragraph block; Gutenberg turns it into a link.
	await canvas.getByRole( 'button', { name: 'Add default block' } ).click();
	await page.keyboard.type( 'Visit ' + SITE_URL + '/editor-link/ now.' );

	// Save as draft (goes through rest_pre_insert_post, the block-editor save path).
	await page.getByRole( 'button', { name: 'Save draft' } ).click();
	await page.waitForURL( /post\.php\?post=\d+/, { timeout: 30000 } );

	// Resolve the stored content via the authenticated REST API and assert the
	// absolute domain is gone (and the link is now root-relative).
	const postId = /post=(\d+)/.exec( page.url() )[ 1 ];
	const auth = basicAuth( 'admin', readAppPassword() );
	const fetched = await request.get( SITE_URL + '/wp-json/wp/v2/posts/' + postId, {
		params: { context: 'edit' },
		headers: { Authorization: auth },
	} );
	expect( fetched.ok() ).toBeTruthy();

	const raw = ( await fetched.json() ).content.raw;
	expect( raw ).not.toContain( SITE_URL );
	expect( raw ).toContain( '/editor-link/' );
} );

const { test, expect } = require( '@playwright/test' );
const { SITE_URL, readAppPassword, basicAuth } = require( './helpers' );

const INTERNAL_PATH = '/internal-target/';

test( 'public REST reads restore absolute URLs, editor context stays relative', async ( { request } ) => {
	const list = await request.get( SITE_URL + '/wp-json/wp/v2/posts', {
		params: { slug: 'e2e-links-relative' },
	} );
	expect( list.ok() ).toBeTruthy();
	const posts = await list.json();
	expect( posts.length ).toBeGreaterThan( 0 );
	const postId = posts[ 0 ].id;

	// Default (public) context: the same position as a headless/third-party
	// consumer with no site origin of its own to resolve "/path" against.
	const viewResponse = await request.get( SITE_URL + '/wp-json/wp/v2/posts/' + postId );
	expect( viewResponse.ok() ).toBeTruthy();
	const viewData = await viewResponse.json();
	expect( viewData.content.rendered ).toContain( 'href="' + SITE_URL + INTERNAL_PATH + '"' );
	expect( viewData.content.rendered ).not.toContain( 'href="' + INTERNAL_PATH + '"' );

	// External link untouched regardless of context.
	expect( viewData.content.rendered ).toContain( 'href="https://example.org/ext"' );

	// context=edit is the block editor's own read (requires edit_post
	// capability, checked by WordPress before this plugin's filter ever
	// runs) — it keeps working root-relative like the rest of wp-admin.
	const auth = basicAuth( 'admin', readAppPassword() );
	const editResponse = await request.get( SITE_URL + '/wp-json/wp/v2/posts/' + postId, {
		params: { context: 'edit' },
		headers: { Authorization: auth },
	} );
	expect( editResponse.ok() ).toBeTruthy();
	const editData = await editResponse.json();
	expect( editData.content.rendered ).toContain( 'href="' + INTERNAL_PATH + '"' );
	expect( editData.content.rendered ).not.toContain( 'href="' + SITE_URL + INTERNAL_PATH + '"' );
} );

/**
 * Shared helpers for the Playwright tests.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const SITE_URL = process.env.WP_ENV_SITE_URL || 'http://localhost:8888';
const ADMIN_USER = 'admin';
const ADMIN_PASSWORD = 'password';

function readAppPassword() {
	const file = path.join( __dirname, '.auth', 'app-password' );
	return fs.readFileSync( file, 'utf8' ).trim();
}

function basicAuth( user, pass ) {
	return 'Basic ' + Buffer.from( user + ':' + pass, 'utf8' ).toString( 'base64' );
}

/**
 * A "landing page" of block-markup whose link-generating blocks reference the
 * site's own absolute URL (plus one external URL that must stay untouched).
 *
 * Covered block types: heading, paragraph, button, image, list.
 */
function landingPageBlocks( siteUrl ) {
	return [
		'<!-- wp:heading --><h2 class="wp-block-heading"><a href="' + siteUrl + '/about/">About us</a></h2><!-- /wp:heading -->',
		'',
		'<!-- wp:paragraph --><p>Read more <a href="' + siteUrl + '/pricing/">here</a>.</p><!-- /wp:paragraph -->',
		'',
		'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' + siteUrl + '/cta/">Get started</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
		'',
		'<!-- wp:image {"url":"' + siteUrl + '/wp-content/uploads/2026/08/hero.jpg"} --><figure class="wp-block-image"><img src="' + siteUrl + '/wp-content/uploads/2026/08/hero.jpg" alt=""/></figure><!-- /wp:image -->',
		'',
		'<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><a href="' + siteUrl + '/service-a/">Service A</a></li><!-- /wp:list-item --><!-- wp:list-item --><li><a href="https://example.org/external">External</a></li><!-- /wp:list-item --></ul><!-- /wp:list -->',
	].join( '\n' );
}

module.exports = {
	SITE_URL,
	ADMIN_USER,
	ADMIN_PASSWORD,
	readAppPassword,
	basicAuth,
	landingPageBlocks,
};

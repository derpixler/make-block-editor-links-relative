/**
 * Seeding for the Playwright run.
 *
 * The headline guarantee is the *render layer*: content that already contains
 * absolute URLs (legacy content, or content written before the plugin was
 * active) must be neutralized when rendered. So the post is created while the
 * plugin is DEACTIVATED (absolute URL is persisted verbatim), and only then is
 * the plugin activated for rendering.
 *
 * The seed is idempotent: any post from a previous run is deleted first.
 */
const { execSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const SITE_URL = process.env.WP_ENV_SITE_URL || 'http://localhost:8888';
const SLUG = 'e2e-links-relative';
const PLUGIN_SLUG = 'wp-make-gutenberg-links-relative';
const INTERNAL_PATH = '/internal-target/';

function run( command ) {
	execSync( 'npx wp-env run cli ' + command, { stdio: 'inherit' } );
}

function runQuiet( command ) {
	try {
		execSync( 'npx wp-env run cli ' + command, { stdio: 'ignore' } );
		return true;
	} catch ( e ) {
		return false;
	}
}

// wp-env's webServer may report "ready" before `wp core install` has finished
// (especially in CI). Poll `wp core is-installed` so no wp-cli command runs
// against a half-installed site.
async function waitUntilInstalled() {
	for ( let i = 0; i < 60; i++ ) {
		if ( runQuiet( 'wp core is-installed' ) ) {
			return;
		}
		await new Promise( ( r ) => setTimeout( r, 2000 ) );
	}
	throw new Error( 'WordPress was not installed within 120s' );
}

// Runs a command and returns the meaningful output line (skipping the
// "ℹ Starting…" / "✔ Ran…" chatter that wp-env prints around it).
function runCapture( command ) {
	const out = execSync( 'npx wp-env run cli ' + command, { encoding: 'utf8' } );
	const lines = out.split( '\n' ).map( ( l ) => l.trim() ).filter( Boolean );
	return lines.find( ( l ) => ! l.startsWith( 'ℹ' ) && ! l.startsWith( '✔' ) );
}

// Runs a script inside the container's bash. Single-quote wrapping ensures the
// host shell does not expand `$(...)` or `|` — the container's bash does.
function runBash( script ) {
	const safe = script.replace( /'/g, "'\\''" );
	run( "bash -c '" + safe + "'" );
}

module.exports = async () => {
	await waitUntilInstalled();

	run( 'wp plugin deactivate ' + PLUGIN_SLUG );
	run( "wp rewrite structure '/%postname%/' --hard" );

	// Remove any post from a previous run (slug collision would otherwise
	// create a suffixed duplicate like `e2e-links-relative-2`).
	runBash(
		'wp post delete $(wp post list --name=' + SLUG + ' --post_type=post --field=ID) --force 2>/dev/null || true'
	);

	const content =
		'<p><a href="' + SITE_URL + INTERNAL_PATH + '">internal link</a></p>' +
		'<p><a href="https://example.org/ext">external link</a></p>';

	const b64 = Buffer.from( content, 'utf8' ).toString( 'base64' );
	runBash(
		'echo ' + b64 + ' | base64 -d | wp post create - ' +
			'--post_title="E2E Links Relative" --post_name=' + SLUG + ' --post_status=publish'
	);

	// Now activate the plugin: the render layer must neutralize the stored
	// absolute internal URL without touching the database.
	run( 'wp plugin activate ' + PLUGIN_SLUG );

	// Create an application password so the authenticated REST tests (Gutenberg
	// save path) can talk to the backend. Stored for the specs to read.
	const appPassword = runCapture( 'wp user application-password create admin playwright --porcelain' );
	fs.mkdirSync( path.join( __dirname, '.auth' ), { recursive: true } );
	fs.writeFileSync( path.join( __dirname, '.auth', 'app-password' ), appPassword + '\n' );
};

const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: __dirname,
	globalSetup: require.resolve( './global-setup.js' ),
	fullyParallel: true,
	retries: process.env.CI ? 2 : 0,
	outputDir: 'test-results',
	reporter: [
		[ 'html', { outputFolder: 'playwright-report', open: 'never' } ],
		[ 'list' ],
	],
	use: {
		baseURL: 'http://localhost:8888',
		trace: 'on',
		video: 'on',
		screenshot: 'on',
	},
	webServer: {
		command: 'npx wp-env start',
		url: 'http://localhost:8888/wp-login.php',
		reuseExistingServer: ! process.env.CI,
		timeout: 300000,
	},
} );

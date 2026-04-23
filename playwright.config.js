/**
 * Playwright configuration for VK Link Target Controller e2e tests.
 *
 * PR #141 の CPT リダイレクトURL保存・表示・更新の e2e 検証用。
 * WP_BASE_URL 環境変数、または .wp-env.override.json のポート(8896)を baseURL に使う。
 */
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	timeout: 60 * 1000,
	retries: 0,
	workers: 1,
	reporter: 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8896',
		headless: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { browserName: 'chromium' },
		},
	],
} );

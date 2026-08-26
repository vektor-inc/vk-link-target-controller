/**
 * playwright.config.js
 *
 * VK Link Target Controller の Playwright e2e テスト実行設定。
 * issue #155（Playwright e2e テスト基盤の導入）に基づき、
 * `@wordpress/scripts` が提供するデフォルト設定をベースに拡張する。
 * ベースを流用することで、ヘッドレス実行・ログイン済みセッション
 * （storageState）を用意する globalSetup・レポーター設定など、
 * WordPress の管理画面を扱う e2e に必要な既定値を重複実装せずに
 * 引き継げる。
 */

const { defineConfig } = require( '@playwright/test' );

// @wordpress/scripts のデフォルト Playwright 設定。
// baseURL の環境変数優先ロジック・headless 既定・globalSetup（管理者ログイン
// のセッションを保存する処理）などをここから継承する。
const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

// wp-env の「tests」環境（テスト専用サイト）へアクセスするためのベースURL。
// - 優先: 環境変数 WP_BASE_URL（例: WP_BASE_URL=http://localhost:9291）
//   ポートは開発者ごとに .wp-env.override.json で変わるため、実行時は
//   必ずこの環境変数を明示すること（e2e ルール: 絶対URLのハードコード禁止）。
// - フォールバック: http://localhost:8889
//   これは wp-env をポート指定なし（.wp-env.override.json なし）で起動した
//   場合の、tests 環境（テストサイト）の既定ポート。あくまで保険的な既定値
//   であり、実際の開発ではポートが衝突しないよう各自 .wp-env.override.json
//   でポートを変更する運用のため、通常は WP_BASE_URL を明示して上書きする。
const baseURL = process.env.WP_BASE_URL || 'http://localhost:8889';

module.exports = defineConfig( {
	...baseConfig,

	// テスト本体の置き場所。issue #155 の設計判断に合わせ、
	// tests/e2e/specs 配下にすべてのスペックファイルを置く。
	testDir: './tests/e2e/specs',

	use: {
		...baseConfig.use,
		baseURL,
		// ブラウザは常にヘッドレス（非表示）で実行する。
		// e2e ルール: スクリーンショット・録画の本番撮影も含め、
		// `--headed` を既定にしない。デバッグ時のみ一時的に headed を使ってよい。
		headless: true,
	},

	// wp-env の起動・停止はこのファイルからは行わない。
	// 実行前に `wp-env start`（または `.wp-env.override.json` を使った tests 環境の起動）
	// が完了していることを前提とする。baseConfig の webServer はポート 8889 決め打ちの
	// ヘルスチェックを行うため、開発者ごとに変わるポート運用と噛み合わない。
	// そのため明示的に無効化し、テスト実行前に環境が起動済みであることを
	// 呼び出し側（npm script 実行者）の責任とする。
	webServer: undefined,
} );

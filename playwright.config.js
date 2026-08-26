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
//
// 注意: ここでの `process.env.WP_BASE_URL || 'http://localhost:8889'` という
// ロジック自体は、実は baseConfig（@wordpress/scripts のデフォルト設定）が
// 既に内部で行っているものと同じで、baseConfig.use.baseURL を素通しすれば
// 重複を避けられる。それでもここで明示的に再掲しているのは、issue #155 の
// 設計判断「baseURL は process.env.WP_BASE_URL を優先し、固定値をハードコード
// しない」という意図をこの設定ファイル単体を読むだけで追えるようにするため。
// そのため、将来 baseConfig 側の既定値（現在は 'http://localhost:8889'）が
// 変わった場合は、ここが古いままにならないよう合わせて見直すこと。
//
// - 優先: 環境変数 WP_BASE_URL（例: WP_BASE_URL=http://localhost:9291）
//   ポートは開発者ごとに .wp-env.override.json で変わるため、実行時は
//   必ずこの環境変数を明示すること（e2e ルール: 絶対URLのハードコード禁止）。
// - フォールバック: http://localhost:8889
//   これは wp-env をポート指定なし（.wp-env.override.json なし）で起動した
//   場合の、tests 環境（テストサイト）の既定ポート。あくまで保険的な既定値
//   であり、実際の開発ではポートが衝突しないよう各自 .wp-env.override.json
//   でポートを変更する運用のため、通常は WP_BASE_URL を明示して上書きする。
//
// baseConfig と同様、URL として正規化（末尾スラッシュの付与など）しておく。
const baseURL = new URL( process.env.WP_BASE_URL || 'http://localhost:8889' )
	.href;

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
	// が完了していることを前提とする。
	//
	// baseConfig.webServer は `port: baseUrl.port`（= WP_BASE_URL 由来のポート）に
	// 対してヘルスチェックし、未起動なら `npm run wp-env start` を自動実行しようと
	// する。これ自体はポート固定ではなく WP_BASE_URL に追従する作りだが、
	// このプロジェクトでは wp-env の起動・停止を npm script 側で管理しておらず
	// （`wp-env` script は `wp-env` コマンドの素通しで `start` を含まない）、
	// 「テスト実行前に環境が起動済みであること」を呼び出し側（npm run test:e2e を
	// 叩く開発者）の責任とする運用にしているため、明示的に無効化する。
	webServer: undefined,
} );

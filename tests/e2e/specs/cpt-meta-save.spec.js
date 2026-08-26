/**
 * cpt-meta-save.spec.js
 *
 * Regression test for issue #140 (custom post type (CPT) meta save bug).
 * issue #140（カスタム投稿タイプ（CPT）でのmeta保存不具合）の回帰テスト。
 *
 * VK Link Target Controller keeps the following meta per post. What this
 * test actually exercises is `vk_ltc_register_post_meta()` in
 * inc/register-meta.php (which registers these meta keys for the REST API)
 * together with the React panel in src/index.js that reads/writes them via
 * that REST-registered meta. `render_meta_box()` / `save_link()` in
 * vk-link-target-controller.php are the *classic meta box fallback* side
 * (used only when no build exists — see "IMPORTANT" below) and are NOT
 * what this test targets.
 * VK Link Target Controller は投稿ごとに以下のメタを保持する。このテストが
 * 実際に検証しているのは、inc/register-meta.php の `vk_ltc_register_post_meta()`
 * （これらのmetaキーをREST APIに登録する処理）と、そのREST登録済みmetaを
 * 読み書きする src/index.js のReactパネルの組み合わせである。
 * vk-link-target-controller.php の `render_meta_box()` / `save_link()` は
 * *classic meta box フォールバック側*（ビルドが無い場合にのみ使われる。
 * 下記「IMPORTANT」参照）の関数であり、このテストの検証対象ではない。
 *   - `vk-ltc-link`   : Redirect URL / リダイレクト先URL
 *   - `vk-ltc-target` : Open-in-new-window flag / 別ウィンドウで開くフラグ
 *
 * IMPORTANT — which UI this test exercises / 重要: このテストが検証するUIについて
 * ---------------------------------------------------------------------------
 * The actual fix for issue #140 lives on the block editor's React sidebar
 * panel path: the `PluginDocumentSettingPanel` (name="vk-ltc-panel") defined
 * in src/index.js, which reads/writes meta via `useEntityProp('postType',
 * postType, 'meta')` — i.e. through the REST API — and is only rendered when
 * `window.vkLtcEditor?.postTypes` (passed by
 * `enqueue_block_editor_assets()` in vk-link-target-controller.php) includes
 * the current post type.
 * issue #140 の実際の修正が入っているのは、ブロックエディタのReactサイドバー
 * パネル経路（src/index.js の `PluginDocumentSettingPanel`、name="vk-ltc-panel"）
 * である。このパネルは `useEntityProp('postType', postType, 'meta')` 経由、
 * つまりREST API経由でmetaを読み書きし、`window.vkLtcEditor?.postTypes`
 * （vk-link-target-controller.php の enqueue_block_editor_assets() が渡す）に
 * 現在の投稿タイプが含まれる場合のみ表示される。
 *
 * This React panel only renders when `build/index.asset.php` exists
 * (vk-link-target-controller.php's add_link_meta_box() adds a legacy classic
 * meta box with the `__back_compat_meta_box` flag — which stays hidden in the
 * block editor — only when that build file is present; otherwise it falls
 * back to a *visible* classic meta box, so the plugin still works without a
 * build). So this test intentionally requires a build to exist first (see
 * the beforeAll check below) — without it, the assertions here would be
 * exercising the classic-meta-box fallback UI instead, which is NOT what
 * issue #140 fixed, and would give a false sense of coverage.
 * このReactパネルは `build/index.asset.php` が存在する場合のみ描画される
 * （vk-link-target-controller.php の add_link_meta_box() は、そのビルド
 * ファイルが存在する場合のみ `__back_compat_meta_box` フラグ付きのレガシー
 * classic meta box を追加する。このフラグが付くとブロックエディタ上では
 * 非表示になる。ビルドが無い場合は*表示される* classic meta box にフォール
 * バックするため、ビルド無しでもプラグイン自体は動作する）。そのためこの
 * テストは、事前にビルドが存在することを意図的に要求する（下記 beforeAll の
 * チェック参照）。ビルドが無いまま実行すると、ここでのアサーションは
 * issue #140 の修正対象ではない classic meta box フォールバックUIを検証して
 * しまい、実際には検証できていないのに緑になる誤った安心感を生む。
 *
 * Scope / スコープ:
 * This test covers only the React panel (build present) path. The classic
 * meta box fallback path (used when no build exists, e.g. before running
 * `npm run build`) is NOT covered by any e2e test in this repository. Do
 * not assume that path is tested just because this file exists.
 * このテストがカバーするのはReactパネル（ビルドあり）経路のみ。classic meta
 * box フォールバック経路（ビルドが無い場合。例: `npm run build` 実行前）は、
 * このリポジトリのe2eテストではどれもカバーしていない。このファイルがある
 * ことをもって、そちらの経路もテスト済みだと誤解しないこと。
 *
 * Prerequisite: run `npm run build` first so build/index.asset.php exists,
 * then have wp-env's tests environment running with WP_BASE_URL pointing to
 * it (e.g. `npm run build && WP_BASE_URL=http://localhost:9291 npm run
 * test:e2e`). If the build is missing, the beforeAll check below fails the
 * suite immediately with an explanatory message instead of silently
 * exercising the wrong UI.
 * 実行前提: 先に `npm run build` を実行して build/index.asset.php を用意し、
 * WP_BASE_URL がそれを指す状態で wp-env の tests 環境を起動しておくこと
 * （例: `npm run build && WP_BASE_URL=http://localhost:9291 npm run
 * test:e2e`）。ビルドが無い場合、下記 beforeAll のチェックが説明付きで
 * 即座にスイートを失敗させる（誤ったUIを黙って検証してしまうことを防ぐため）。
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

// Slug of the CPT registered by tests/e2e/mu-plugins/register-test-cpt.php.
// Keep both in sync — letting the value drift means the test can no longer
// find the target CPT. Since register_post_type() does not set the REST
// base explicitly, it is also this same slug (/wp/v2/<slug>), which the
// deleteAllPosts() cleanup below also relies on.
// tests/e2e/mu-plugins/register-test-cpt.php で登録しているCPTのスラッグ。
// 値をずらすとテストが対象のCPTを見つけられなくなるため、双方をそろえること。
// REST base（/wp/v2/<slug>）も register_post_type() で明示していないため
// このスラッグそのものになる（後始末の deleteAllPosts() でも使用）。
const TEST_CPT_SLUG = 'vk_ltc_e2e_cpt';

// A dummy redirect URL used to verify saving. It does not need to be a
// real, resolvable URL.
// 保存確認用のダミーのリダイレクト先URL。実在URLである必要はない。
const TEST_REDIRECT_URL = 'https://example.com/vk-ltc-e2e-test/';

// Absolute path to the build output that gates whether the React sidebar
// panel (the code path this test targets) is rendered at all. See the
// file-level comment above for why this matters.
// Reactサイドバーパネル（このテストが対象とする経路）がそもそも描画される
// かどうかを左右するビルド成果物への絶対パス。重要性の理由はファイル冒頭の
// コメントを参照。
const BUILD_ASSET_FILE = path.join(
	__dirname,
	'..',
	'..',
	'..',
	'build',
	'index.asset.php'
);

/**
 * Expand the "URL to redirect to" React sidebar panel
 * (PluginDocumentSettingPanel, name="vk-ltc-panel", registered by
 * src/index.js) if it is currently collapsed.
 * 「URL to redirect to」React サイドバーパネル（PluginDocumentSettingPanel、
 * name="vk-ltc-panel"、src/index.js で登録）が折りたたまれている場合に展開する。
 *
 * WordPress persists each PluginDocumentSettingPanel's open/closed state as
 * a per-user preference (core/preferences), so once some test run expands
 * it, later runs under the same admin session (shared storageState) may
 * already find it open. We therefore check aria-expanded first and only
 * click when it is actually needed.
 * WordPress は各 PluginDocumentSettingPanel の開閉状態をユーザーごとの
 * プリファレンス（core/preferences）として永続化するため、一度どこかの
 * テスト実行で展開すると、以降は同じ管理者セッション（共有 storageState）の
 * もとで既に開いた状態になっていることがある。そのため、先に aria-expanded
 * を確認し、実際に必要な場合のみクリックする。
 *
 * IMPORTANT: this panel only renders inside the "Post" tab of the Document
 * Settings sidebar. That sidebar's own open/closed state is *also* a
 * per-user preference (`wp_persisted_preferences` user meta), persisted
 * across requests under the same shared admin session (storageState). If
 * anyone (a developer poking at the tests site, another test file, etc.)
 * ever closes it, this test would start failing with "panel not found"
 * from then on — indistinguishable from a real regression, and entirely
 * dependent on environment state rather than the code under test. This is
 * exactly the class of problem this test already got burned by once
 * (build/ presence silently changing which UI was exercised), so we close
 * that door here too by explicitly (re)opening the sidebar every time via
 * the official `editor.openDocumentSettingsSidebar()` utility, which is
 * itself idempotent (checks aria-expanded and only clicks when closed).
 * 重要: このパネルは Document Settings サイドバーの「Post」タブの中にしか
 * 描画されない。そしてそのサイドバー自体の開閉状態も、ユーザーごとの
 * プリファレンス（`wp_persisted_preferences` ユーザーメタ）として、共有している
 * 管理者セッション（storageState）をまたいで永続化される。誰か（tests サイトを
 * 触った開発者、他のテストファイルなど）が一度でもサイドバーを閉じると、
 * このテストはそれ以降「パネルが見つからない」で落ち続ける ― 本物の回帰と
 * 見分けがつかず、しかも検証対象のコードではなく環境の状態に依存した失敗になる。
 * これはこのテストが一度実際に踏んだ罠（build/ の有無で検証対象のUIが
 * 黙って変わっていたこと）と同じ形の依存であるため、ここでも公式ユーティリティ
 * `editor.openDocumentSettingsSidebar()`（aria-expanded を見て閉じている時だけ
 * クリックする冪等な実装）で毎回明示的に開き直すことで塞いでおく。
 *
 * @param {import('@playwright/test').Page}   page   Playwright's page object. / Playwright の page オブジェクト。
 * @param {import('@wordpress/e2e-test-utils-playwright').Editor} editor Editor utils (for openDocumentSettingsSidebar). / editor ユーティリティ（openDocumentSettingsSidebar 用）。
 * @return {Promise<void>}
 */
async function expandRedirectUrlPanel( page, editor ) {
	// See the IMPORTANT note above: guarantee the sidebar itself is open
	// before looking for a panel that only exists inside it.
	// 上記IMPORTANTの通り、その中にしか存在しないパネルを探す前に、
	// サイドバー自体が開いていることを保証する。
	await editor.openDocumentSettingsSidebar();

	// Scope the toggle to `.vk-ltc-panel` (the panel's own className), not
	// just the page, so that if the panel itself is ever absent, the
	// failure points at the panel rather than a same-named button
	// elsewhere on the page (defense in depth — the visibility assertion
	// below already catches this case regardless).
	// ページ全体ではなく `.vk-ltc-panel`（パネル自身のclassName）にスコープする。
	// こうしておくと、万一パネル自体が存在しない場合に、ページ上の同名の
	// 別ボタンではなく「パネルが無い」ことを指す失敗になる（下の可視性
	// アサーションが最終的な関門にはなっているが、念のための多重の安全策）。
	const toggle = page
		.locator( '.vk-ltc-panel' )
		.getByRole( 'button', { name: 'URL to redirect to', exact: true } );
	await toggle.waitFor();
	const isExpanded = await toggle.getAttribute( 'aria-expanded' );
	if ( 'true' !== isExpanded ) {
		await toggle.click();
	}
	// Wait for the panel body (and the URL field inside it) to actually
	// mount and become interactable.
	// パネル本体（内部のURL欄）が実際にマウント・操作可能になるまで待つ。
	await expect(
		page.locator( '.vk-ltc-panel' ).getByLabel( 'URL', { exact: true } )
	).toBeVisible();
}

test.describe( 'VK Link Target Controller: CPTでのmeta保存（issue #140 回帰）', () => {
	// Fail fast, with an explanatory message, if the React panel this test
	// targets cannot possibly be rendered because the build is missing.
	// Without this check, running the suite pre-build silently exercises the
	// classic-meta-box fallback UI instead and still turns green — exactly
	// the trap that let this test miss the real regression once already.
	// このテストが対象とするReactパネルがそもそも描画され得ない
	// （ビルドが無い）場合、説明付きで即座に失敗させる。このチェックが無いと、
	// ビルド前にスイートを実行しても classic meta box フォールバックUIを
	// 黙って検証してしまい、それでも緑になる — 実際に一度このテストが
	// 本来の回帰を見逃す原因になった罠そのもの。
	test.beforeAll( async () => {
		if ( ! fs.existsSync( BUILD_ASSET_FILE ) ) {
			throw new Error(
				'build/index.asset.php not found. This test targets the React sidebar panel (src/index.js), which vk-link-target-controller.php only renders when a build exists — without it, the plugin falls back to a *visible* classic meta box, and this test would end up exercising that different UI instead while still reporting PASS. Run `npm run build` first, then re-run this test. / ' +
					'build/index.asset.php が見つかりません。このテストが対象とするReactサイドバー' +
					'パネル（src/index.js）は、vk-link-target-controller.php がビルドの存在を前提に' +
					'描画するものです。ビルドが無いと、プラグインは*表示される* classic meta box に' +
					'フォールバックしてしまい、このテストはそちらの別のUIを検証したままPASSしてしまいます。' +
					'先に `npm run build` を実行してから再実行してください。'
			);
		}
	} );

	// The tests environment's DB is shared with PHPUnit, so we don't want to
	// leave behind the CPT posts this test creates on every run. beforeAll
	// also cleans up leftover data (e.g. from a previous abnormal exit),
	// and afterAll cleans up what this run created. This only deletes posts
	// (content) this test created, via the REST API — it is not an
	// operation that rebuilds the DB itself (such as wp db reset).
	// tests 環境のDBはPHPUnitとも共有しているため、このテストが作成したCPT投稿を
	// 実行のたびに残さない。beforeAll では（前回異常終了時などの）残存データも
	// 含めて掃除し、afterAll ではこの実行で作成した投稿を掃除する。
	// あくまで「テストが作った投稿（コンテンツ）」をREST API経由で削除するだけで、
	// DBそのものを作り直す操作（wp db reset 等）ではない。
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts( TEST_CPT_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts( TEST_CPT_SLUG );
	} );

	test( 'CPT編集画面でリダイレクト先URLと別ウィンドウ設定を保存し、再読み込み後も値が保持される', async ( {
		admin,
		editor,
		page,
	} ) => {
		// 1. Open the "add new post" screen for the e2e-only test CPT.
		//    admin.createNewPost() navigates internally with a relative
		//    path (post-new.php).
		//    e2eテスト専用CPTの新規投稿画面を開く。
		//    admin.createNewPost() は内部で相対パス（post-new.php）に遷移する。
		await admin.createNewPost( {
			postType: TEST_CPT_SLUG,
			title: 'VK LTC E2E CPT テスト投稿',
		} );

		// 2. Operate the fields inside the "URL to redirect to" React
		//    sidebar panel (PluginDocumentSettingPanel). It is collapsed by
		//    default, so expand it first. Both fields are scoped to
		//    `.vk-ltc-panel` (the panel's own className) because the hidden
		//    wpLink dialog markup elsewhere on the page also has an input
		//    labeled "URL", which would otherwise make the locator
		//    ambiguous.
		//    「URL to redirect to」Reactサイドバーパネル（PluginDocumentSettingPanel）
		//    内のフィールドを操作する。既定で折りたたまれているため先に展開する。
		//    ページ内の非表示のwpLinkダイアログにも "URL" ラベルの入力欄が
		//    存在し、ロケータが曖昧になってしまうため、両フィールドとも
		//    `.vk-ltc-panel`（パネル自身のclassName）にスコープしている。
		await expandRedirectUrlPanel( page, editor );
		const panel = page.locator( '.vk-ltc-panel' );
		const linkField = panel.getByLabel( 'URL', { exact: true } );
		await expect( linkField ).toBeVisible();
		await linkField.fill( TEST_REDIRECT_URL );

		// 3. Also turn on the "open in a separate window" checkbox, so we
		//    also confirm multiple fields are retained together.
		//    「別ウィンドウで開く」チェックボックスもONにし、
		//    複数フィールドが同時に保持されることも合わせて確認する。
		const targetCheckbox = panel.getByLabel(
			'Open the link in a separate window',
			{ exact: true }
		);
		await targetCheckbox.check();

		// 4. Publish the post. Unlike the classic meta box fallback (which
		//    saves via a separate, asynchronous `meta-box-loader` POST),
		//    this React panel reads/writes meta through
		//    `useEntityProp('postType', postType, 'meta')`, so the meta
		//    values are included directly in the same REST request that
		//    saves the post itself (confirmed by measurement: the POST
		//    body to /wp/v2/<post_type>/<id> contains a `meta` object with
		//    `vk-ltc-link` / `vk-ltc-target`). editor.publishPost() already
		//    waits for that REST save to succeed (it resolves only after
		//    the "published" notice appears, which itself only appears
		//    after a successful save), so no extra wait for a separate
		//    save request is needed here.
		//    投稿を公開する。classic meta box フォールバック（別の非同期
		//    `meta-box-loader` POSTで保存する）と異なり、このReactパネルは
		//    `useEntityProp('postType', postType, 'meta')` 経由でmetaを
		//    読み書きするため、meta の値は投稿本体を保存する同じREST
		//    リクエストにそのまま含まれる（実測で確認済み: /wp/v2/<post_type>/<id>
		//    へのPOSTボディに `vk-ltc-link` / `vk-ltc-target` を含む `meta`
		//    オブジェクトが入っている）。editor.publishPost() は既にその
		//    REST保存の成功を待っている（「公開しました」の通知は保存成功後
		//    にのみ表示され、publishPost() はその通知が出るまで解決しない）
		//    ため、ここで別途保存リクエストを待つ必要はない。
		await editor.publishPost();

		// 5. Reload the published edit screen as-is, and check whether the
		//    value saved on the server is re-rendered (reload the current
		//    page rather than using an absolute URL).
		//    公開後の編集画面をそのままリロードし、サーバーに保存された値が
		//    再表示されるかを確認する（絶対URLを使わず現在のページを再読み込み）。
		await page.reload();

		// 6. The panel may return to its collapsed state after the reload
		//    (state persistence behavior can vary), so expand it again
		//    defensively, then confirm the value is retained.
		//    リロード後もパネルが折りたたまれた状態に戻ることがある（状態の
		//    永続化の挙動は一定しないため）ため、念のため再度展開してから
		//    値が保持されていることを確認する。
		await expandRedirectUrlPanel( page, editor );
		const reloadedPanel = page.locator( '.vk-ltc-panel' );
		const reloadedLinkField = reloadedPanel.getByLabel( 'URL', {
			exact: true,
		} );
		await expect( reloadedLinkField ).toBeVisible();
		await expect( reloadedLinkField ).toHaveValue( TEST_REDIRECT_URL );

		const reloadedTargetCheckbox = reloadedPanel.getByLabel(
			'Open the link in a separate window',
			{ exact: true }
		);
		await expect( reloadedTargetCheckbox ).toBeChecked();
	} );
} );

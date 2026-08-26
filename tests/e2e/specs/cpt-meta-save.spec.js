/**
 * cpt-meta-save.spec.js
 *
 * Regression test for issue #140 (custom post type (CPT) meta save bug).
 * issue #140（カスタム投稿タイプ（CPT）でのmeta保存不具合）の回帰テスト。
 *
 * VK Link Target Controller keeps the following meta per post (see
 * render_meta_box() / save_link() in inc/register-meta.php and
 * vk-link-target-controller.php).
 * VK Link Target Controller は投稿ごとに以下のメタを保持する
 * （inc/register-meta.php・vk-link-target-controller.php の render_meta_box() /
 * save_link() 参照）。
 *   - `vk-ltc-link`   : Redirect URL / リダイレクト先URL（テキスト入力欄 #vk-ltc-link-field）
 *   - `vk-ltc-target` : Open-in-new-window flag / 別ウィンドウで開くフラグ（チェックボックス #vk-ltc-target-check）
 *
 * This test enters a redirect URL into the "URL to redirect to" meta box on
 * a CPT edit screen, publishes, and confirms the value is still there
 * (correctly saved on the server and re-rendered) after reloading the page.
 * このテストでは、CPT の編集画面で「URL to redirect to」メタボックスに
 * リダイレクト先URLを入力・公開し、ページを再読み込みしても値が
 * 保持されている（サーバー側で正しく保存され、再表示されている）ことを確認する。
 *
 * The target CPT is the e2e-only CPT (vk_ltc_e2e_cpt, public: true)
 * registered by tests/e2e/mu-plugins/register-test-cpt.php.
 * VK_Link_Target_Controller::get_option() targets all public post types
 * when the candidate post type option (vk_ltc_custom_post_types) is unset,
 * so the meta box appears for this CPT with no extra admin configuration.
 * 対象CPTは tests/e2e/mu-plugins/register-test-cpt.php で登録する
 * e2e テスト専用CPT（vk_ltc_e2e_cpt、public: true）。
 * VK_Link_Target_Controller::get_option() は候補投稿タイプ用のオプション
 * （vk_ltc_custom_post_types）が未保存の場合、全公開投稿タイプを対象にする
 * ため、このCPTでも追加の管理画面設定なしにメタボックスが表示される。
 *
 * Prerequisite: wp-env's tests environment is running, and the WP_BASE_URL
 * environment variable points to that environment's URL
 * (e.g. WP_BASE_URL=http://localhost:9291 npm run test:e2e).
 * 実行前提: wp-env の tests 環境が起動済みで、
 * WP_BASE_URL 環境変数がその環境のURLを指していること
 * （例: WP_BASE_URL=http://localhost:9291 npm run test:e2e）。
 */

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

/**
 * Expand the "Meta Boxes" panel (the resizable area at the bottom of the
 * block editor that groups classic meta boxes together).
 * ブロックエディタ下部の「Meta Boxes」パネル（classic meta box をまとめて
 * 表示するリサイズ可能な領域）を開く。
 *
 * This panel defaults to a collapsed state (height 32px) both right after
 * creating a new post and after reloading the page, and the "URL to
 * redirect to" meta box inside it exists in the DOM but stays hidden. The
 * panel header's expand/collapse button visually overlaps the drag resize
 * handle (role="separator"), so a mouse click can sometimes be captured by
 * the handle instead and fail to toggle it; focusing the element and using
 * the keyboard (Enter) reliably fires the toggle.
 * このパネルは新規投稿作成時・ページ再読み込み時のどちらも既定で
 * 折りたたまれた状態（高さ32px）になっており、中の「URL to redirect to」
 * メタボックスは DOM 上には存在するが非表示（hidden）のままになる。
 * パネル見出しの開閉ボタンはドラッグ用のリサイズハンドル（role="separator"）と
 * 見た目上重なっており、マウスクリックだとハンドル側にイベントを取られて
 * 開閉できないことがあるため、フォーカスしてキーボード操作（Enter）で
 * 確実に開閉トグルを発火させる。
 *
 * @param {import('@playwright/test').Page} page Playwright's page object. / Playwright の page オブジェクト。
 * @return {Promise<void>}
 */
async function expandMetaBoxesPanel( page ) {
	// We confirmed by measurement that the accessible name is a single
	// exact match, "Meta Boxes". A broad regex combined with .first() tends
	// to silently operate on a different button if one is ever added later,
	// so we use an exact match instead and let the locator itself guarantee
	// there is only one element (a strict-mode error on multiple matches
	// would surface the problem instead of hiding it).
	// アクセシブルネームは実測で "Meta Boxes"（完全一致）の1件のみであることを
	// 確認済み。広い正規表現 + .first() の組み合わせは、将来ボタンが増えた際に
	// 「別のボタンを黙って操作してしまう」失敗を招きやすいため、exact指定にして
	// 単一要素であることをロケータ自体に保証させる（複数マッチ時は strict エラーで
	// 気づける状態にしておく）。
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes', exact: true } );
	// The "Meta Boxes" panel header can mount asynchronously a little after
	// the editor itself first renders, so wait for it to appear in the DOM
	// before reading its state (aria-expanded). Skipping this wait makes
	// the test flaky, failing only when it happens to run before the panel
	// has mounted.
	// 「Meta Boxes」パネルの見出しは、エディタ本体の初期表示より少し遅れて
	// 非同期にマウントされることがあるため、先にDOMへの出現を待ってから
	// 状態（aria-expanded）を読む。ここを待たずに読むと、マウント前の
	// タイミングに当たった場合だけ稀に失敗する（flaky になる）。
	await toggle.waitFor();
	// Do nothing if it is already open (aria-expanded="true").
	// 既に開いている場合は何もしない（aria-expanded="true"）。
	const isExpanded = await toggle.getAttribute( 'aria-expanded' );
	if ( 'true' === isExpanded ) {
		return;
	}
	await toggle.focus();
	await page.keyboard.press( 'Enter' );
	// Wait for the panel's animation and its contents to mount.
	// パネルのアニメーション・中身のマウントを待つ。
	await expect( page.locator( '#vk-ltc-link-field' ) ).toBeVisible();
}

test.describe( 'VK Link Target Controller: CPTでのmeta保存（issue #140 回帰）', () => {
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

		// 2. Operate the URL field inside the "URL to redirect to" meta
		//    box. In an environment where build/index.asset.php has not
		//    been built, the block editor still shows the legacy classic
		//    meta box rather than the React panel (see add_link_meta_box()
		//    in vk-link-target-controller.php). However, the classic meta
		//    box lives inside the "Meta Boxes" panel at the bottom of the
		//    editor, and that panel is collapsed by default, so expand it
		//    first.
		//    「URL to redirect to」メタボックス内のURL入力欄を操作する。
		//    build/index.asset.php が未ビルドの環境では、ブロックエディタ上でも
		//    Reactパネルではなくレガシーの classic meta box がそのまま表示される
		//    （vk-link-target-controller.php の add_link_meta_box() 参照）。
		//    ただし classic meta box はエディタ下部の「Meta Boxes」パネル内に
		//    格納されており、パネルが既定で折りたたまれているため先に展開する。
		await expandMetaBoxesPanel( page );
		const linkField = page.locator( '#vk-ltc-link-field' );
		await expect( linkField ).toBeVisible();
		await linkField.fill( TEST_REDIRECT_URL );

		// 3. Also turn on the "open in a separate window" checkbox, so we
		//    also confirm multiple fields are retained together.
		//    「別ウィンドウで開く」チェックボックスもONにし、
		//    複数フィールドが同時に保持されることも合わせて確認する。
		const targetCheckbox = page.locator( '#vk-ltc-target-check' );
		await targetCheckbox.check();

		// 4. Publish the post. The classic meta box (this plugin's "URL to
		//    redirect to") is saved via a separate, asynchronous POST to
		//    `wp-admin/post.php?...&meta-box-loader=1` (WordPress core's
		//    meta box compatibility layer), distinct from the block
		//    editor's own REST save (/wp/v2/<post_type>). editor.publishPost()
		//    resolves once the "published" notice appears, but that does
		//    not guarantee the meta-box-loader POST has finished (confirmed
		//    by measurement that it fires after the publish notice). So we
		//    set up the wait before publishing and always await its
		//    completion before reloading. Reloading without waiting for it
		//    can cancel the in-flight request, leaving the value unsaved
		//    and producing a false "not saved" failure (flaky).
		//    投稿を公開する。
		//    classic meta box（本プラグインの「URL to redirect to」）の保存は、
		//    ブロックエディタ本体の REST 保存（/wp/v2/<post_type>）とは別に、
		//    `wp-admin/post.php?...&meta-box-loader=1` への非同期POSTで行われる
		//    （WordPressコアの meta box 互換レイヤー）。
		//    editor.publishPost() は「公開しました」の通知が出た時点で解決するが、
		//    この meta-box-loader へのPOSTが完了している保証はない
		//    （実測でも公開の通知より後にこのPOSTが飛ぶことを確認済み）。
		//    そのため publish の前に待ち受けを仕込み、公開後に必ず完了を待ってから
		//    reload する。これを待たずに reload すると、リクエストがキャンセルされ
		//    値が保存されないまま「保存されていない」という誤検知（flaky）になり得る。
		const metaBoxSaveResponse = page.waitForResponse( ( response ) =>
			response.url().includes( 'meta-box-loader' )
		);
		await editor.publishPost();
		await metaBoxSaveResponse;

		// 5. Reload the published edit screen as-is, and check whether the
		//    value saved on the server is re-rendered (reload the current
		//    page rather than using an absolute URL).
		//    公開後の編集画面をそのままリロードし、サーバーに保存された値が
		//    再表示されるかを確認する（絶対URLを使わず現在のページを再読み込み）。
		await page.reload();

		// 6. The panel returns to its collapsed state after the reload
		//    too, so expand it again, then confirm the value is retained.
		//    リロード後もパネルは折りたたまれた状態に戻るため、再度展開してから
		//    値が保持されていることを確認する。
		await expandMetaBoxesPanel( page );
		const reloadedLinkField = page.locator( '#vk-ltc-link-field' );
		await expect( reloadedLinkField ).toBeVisible();
		await expect( reloadedLinkField ).toHaveValue( TEST_REDIRECT_URL );

		const reloadedTargetCheckbox = page.locator( '#vk-ltc-target-check' );
		await expect( reloadedTargetCheckbox ).toBeChecked();
	} );
} );

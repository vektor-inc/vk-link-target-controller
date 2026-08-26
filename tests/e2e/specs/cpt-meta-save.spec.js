/**
 * cpt-meta-save.spec.js
 *
 * issue #140（カスタム投稿タイプ（CPT）でのmeta保存不具合）の回帰テスト。
 *
 * VK Link Target Controller は投稿ごとに以下のメタを保持する
 * （inc/register-meta.php・vk-link-target-controller.php の render_meta_box() /
 * save_link() 参照）。
 *   - `vk-ltc-link`   : リダイレクト先URL（テキスト入力欄 #vk-ltc-link-field）
 *   - `vk-ltc-target` : 別ウィンドウで開くフラグ（チェックボックス #vk-ltc-target-check）
 *
 * このテストでは、CPT の編集画面で「URL to redirect to」メタボックスに
 * リダイレクト先URLを入力・公開し、ページを再読み込みしても値が
 * 保持されている（サーバー側で正しく保存され、再表示されている）ことを確認する。
 *
 * 対象CPTは tests/e2e/mu-plugins/register-test-cpt.php で登録する
 * e2e テスト専用CPT（vk_ltc_e2e_cpt、public: true）。
 * VK_Link_Target_Controller::get_option() は候補投稿タイプ用のオプション
 * （vk_ltc_custom_post_types）が未保存の場合、全公開投稿タイプを対象にする
 * ため、このCPTでも追加の管理画面設定なしにメタボックスが表示される。
 *
 * 実行前提: wp-env の tests 環境が起動済みで、
 * WP_BASE_URL 環境変数がその環境のURLを指していること
 * （例: WP_BASE_URL=http://localhost:9291 npm run test:e2e）。
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

// tests/e2e/mu-plugins/register-test-cpt.php で登録しているCPTのスラッグ。
// 値をずらすとテストが対象のCPTを見つけられなくなるため、双方をそろえること。
const TEST_CPT_SLUG = 'vk_ltc_e2e_cpt';

// 保存確認用のダミーのリダイレクト先URL。実在URLである必要はない。
const TEST_REDIRECT_URL = 'https://example.com/vk-ltc-e2e-test/';

/**
 * ブロックエディタ下部の「Meta Boxes」パネル（classic meta box をまとめて
 * 表示するリサイズ可能な領域）を開く。
 *
 * このパネルは新規投稿作成時・ページ再読み込み時のどちらも既定で
 * 折りたたまれた状態（高さ32px）になっており、中の「URL to redirect to」
 * メタボックスは DOM 上には存在するが非表示（hidden）のままになる。
 * パネル見出しの開閉ボタンはドラッグ用のリサイズハンドル（role="separator"）と
 * 見た目上重なっており、マウスクリックだとハンドル側にイベントを取られて
 * 開閉できないことがあるため、フォーカスしてキーボード操作（Enter）で
 * 確実に開閉トグルを発火させる。
 *
 * @param {import('@playwright/test').Page} page Playwright の page オブジェクト。
 * @return {Promise<void>}
 */
async function expandMetaBoxesPanel( page ) {
	const toggle = page.getByRole( 'button', { name: /meta boxes/i } );
	// 既に開いている場合は何もしない（aria-expanded="true"）。
	const isExpanded = await toggle
		.first()
		.getAttribute( 'aria-expanded' );
	if ( 'true' === isExpanded ) {
		return;
	}
	await toggle.first().focus();
	await page.keyboard.press( 'Enter' );
	// パネルのアニメーション・中身のマウントを待つ。
	await expect( page.locator( '#vk-ltc-link-field' ) ).toBeVisible();
}

test.describe( 'VK Link Target Controller: CPTでのmeta保存（issue #140 回帰）', () => {
	test( 'CPT編集画面でリダイレクト先URLと別ウィンドウ設定を保存し、再読み込み後も値が保持される', async ( {
		admin,
		editor,
		page,
	} ) => {
		// 1. e2eテスト専用CPTの新規投稿画面を開く。
		//    admin.createNewPost() は内部で相対パス（post-new.php）に遷移する。
		await admin.createNewPost( {
			postType: TEST_CPT_SLUG,
			title: 'VK LTC E2E CPT テスト投稿',
		} );

		// 2. 「URL to redirect to」メタボックス内のURL入力欄を操作する。
		//    build/index.asset.php が未ビルドの環境では、ブロックエディタ上でも
		//    Reactパネルではなくレガシーの classic meta box がそのまま表示される
		//    （vk-link-target-controller.php の add_link_meta_box() 参照）。
		//    ただし classic meta box はエディタ下部の「Meta Boxes」パネル内に
		//    格納されており、パネルが既定で折りたたまれているため先に展開する。
		await expandMetaBoxesPanel( page );
		const linkField = page.locator( '#vk-ltc-link-field' );
		await expect( linkField ).toBeVisible();
		await linkField.fill( TEST_REDIRECT_URL );

		// 3. 「別ウィンドウで開く」チェックボックスもONにし、
		//    複数フィールドが同時に保持されることも合わせて確認する。
		const targetCheckbox = page.locator( '#vk-ltc-target-check' );
		await targetCheckbox.check();

		// 4. 投稿を公開する（block editor の2段階Publishフローを内部で処理する）。
		await editor.publishPost();

		// 5. 公開後の編集画面をそのままリロードし、サーバーに保存された値が
		//    再表示されるかを確認する（絶対URLを使わず現在のページを再読み込み）。
		await page.reload();

		// 6. リロード後もパネルは折りたたまれた状態に戻るため、再度展開してから
		//    値が保持されていることを確認する。
		await expandMetaBoxesPanel( page );
		const reloadedLinkField = page.locator( '#vk-ltc-link-field' );
		await expect( reloadedLinkField ).toBeVisible();
		await expect( reloadedLinkField ).toHaveValue( TEST_REDIRECT_URL );

		const reloadedTargetCheckbox = page.locator( '#vk-ltc-target-check' );
		await expect( reloadedTargetCheckbox ).toBeChecked();
	} );
} );

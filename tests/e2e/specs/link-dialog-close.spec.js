/**
 * link-dialog-close.spec.js
 *
 * Regression test for issue #226 (the internal link search dialog opened
 * from the block editor sidebar panel could not be closed).
 * issue #226（ブロックエディタのサイドバーパネルから開いたサイト内リンク
 * 検索ダイアログが閉じられない不具合）の回帰テスト。
 *
 * Cause / 原因:
 * The "Search internal link" button in the React panel (src/index.js) calls
 * `wpLink.open( <id>, ... )` (the WordPress core link dialog). wpLink keeps
 * the element with that id as "the field being edited" (`wpLink.textarea`)
 * and reads `selectionStart` from it when opening, and calls `focus()` on it
 * when closing. When no element with that id exists in the page, both calls
 * throw a TypeError, so the dialog opens with no data and can never be
 * closed (cancel / close button / Esc) until the page is reloaded.
 * React パネル（src/index.js）の「Search internal link」ボタンは、
 * WordPress 本体のリンクダイアログ `wpLink.open( <id>, ... )` を呼ぶ。
 * wpLink はその id の要素を「編集中の入力欄」（`wpLink.textarea`）として保持し、
 * 開くときに `selectionStart` を読み、閉じるときに `focus()` を呼ぶ。
 * その id の要素がページに無いと、どちらも TypeError になり、ダイアログは
 * 中身が空のまま開き、ページを再読み込みするまで（キャンセル・閉じるボタン・
 * Esc のどれでも）閉じられなくなる。
 *
 * Detection / 検知方法:
 * These errors are uncaught exceptions, so the `console` event cannot see
 * them. We collect them with Playwright's `pageerror` event instead.
 * これらは捕捉されない例外のため、`console` イベントでは拾えない。
 * Playwright の `pageerror` イベントで収集する。
 *
 * Prerequisite / 実行前提:
 * Same as cpt-meta-save.spec.js (needs `npm run build:block` first so that
 * build/index.asset.php exists, and wp-env's tests environment running with
 * WP_BASE_URL pointing to it).
 * cpt-meta-save.spec.js と同じ（先に `npm run build:block` で
 * build/index.asset.php を用意し、WP_BASE_URL が指す wp-env の tests 環境を
 * 起動しておくこと）。
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { disableWelcomeGuide } = require( '../helpers/disable-welcome-guide' );

// Slug of the CPT registered by tests/e2e/mu-plugins/register-test-cpt.php.
// tests/e2e/mu-plugins/register-test-cpt.php で登録しているCPTのスラッグ。
const TEST_CPT_SLUG = 'vk_ltc_e2e_cpt';

// Redirect URL already saved on the test post (a dummy; it does not need to
// be resolvable). Used to check that an existing value is not broken by
// opening / closing the dialog.
// テスト投稿に保存済みのリダイレクト先URL（ダミー。実在URLである必要はない）。
// ダイアログの開閉で既存の保存値が壊れないことの確認に使う。
const SAVED_REDIRECT_URL = 'https://example.com/vk-ltc-e2e-dialog/';

// Build output that gates whether the React sidebar panel is rendered.
// Reactサイドバーパネルが描画されるかどうかを左右するビルド成果物。
const BUILD_ASSET_FILE = path.join(
	__dirname,
	'..',
	'..',
	'..',
	'build',
	'index.asset.php'
);

// Dialog elements of the WordPress core link dialog (wpLink). They are
// addressed by id because the ids are fixed by WordPress core and do not
// depend on the admin language.
// WordPress 本体のリンクダイアログ（wpLink）の要素。id は本体が固定しており
// 管理画面の言語に依存しないため、id で指定する。
const DIALOG = '#wp-link-wrap';
const DIALOG_URL_FIELD = '#wp-link-url';
const DIALOG_CANCEL_BUTTON = '#wp-link-cancel button';
const DIALOG_CLOSE_BUTTON = '#wp-link-close';
const DIALOG_SUBMIT_BUTTON = '#wp-link-submit';
const DIALOG_RECENT_ITEMS = '#most-recent-results ul li';

test.describe( 'Issue #226: 内部リンク検索ダイアログの開閉', () => {
	// ID of the post created for this spec.
	// このスペック用に作成した投稿のID。
	let postId;

	// Uncaught exceptions collected during each test.
	// 各テスト中に収集した、捕捉されなかった例外。
	let pageErrors;

	test.beforeAll( async ( { requestUtils } ) => {
		// Same guard as cpt-meta-save.spec.js: without the build, the classic
		// meta box fallback would be exercised instead of the React panel.
		// cpt-meta-save.spec.js と同じガード。ビルドが無いと、Reactパネルでは
		// なく classic meta box フォールバックを検証してしまう。
		if ( ! fs.existsSync( BUILD_ASSET_FILE ) ) {
			throw new Error(
				'build/index.asset.php not found. Run `npm run build:block` first. / build/index.asset.php が見つかりません。先に `npm run build:block` を実行してください。'
			);
		}

		// Turn the "Welcome to the editor" guide off before any editor is
		// opened, so it does not cover the editor on a freshly created
		// environment (issue #238).
		// 作りたての環境で「ようこそ」ガイドがエディターを覆わないよう、
		// エディターを開く前にガイドをオフにしておく（issue #238）。
		await disableWelcomeGuide( requestUtils );

		// Create a published post that already has a redirect URL saved,
		// through the REST API (the same path the editor uses to save).
		// リダイレクト先URLが保存済みの公開投稿を、REST API 経由で作成する
		// （エディタが保存に使うのと同じ経路）。
		const post = await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/${ TEST_CPT_SLUG }`,
			data: {
				title: 'VK LTC E2E ダイアログ開閉テスト投稿',
				status: 'publish',
				meta: { 'vk-ltc-link': SAVED_REDIRECT_URL },
			},
		} );
		postId = post.id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Delete only the post this spec created (permanently).
		// このスペックが作成した投稿だけを完全に削除する。
		if ( postId ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/${ TEST_CPT_SLUG }/${ postId }`,
				params: { force: true },
			} );
		}
	} );

	test.beforeEach( async ( { admin, page } ) => {
		// Start collecting uncaught exceptions before the editor loads, so
		// nothing thrown during loading or opening is missed.
		// 読み込み中・ダイアログを開く最中の例外を取りこぼさないよう、
		// エディタを開く前から捕捉されなかった例外の収集を始める。
		pageErrors = [];
		page.on( 'pageerror', ( error ) => {
			pageErrors.push( error.message );
		} );

		await admin.editPost( postId );
	} );

	/**
	 * Open the sidebar, expand the "URL to redirect to" panel and click
	 * "Search internal link". Resolves once the dialog is visible.
	 * サイドバーを開き、「URL to redirect to」パネルを展開して
	 * 「Search internal link」を押す。ダイアログが表示された時点で解決する。
	 *
	 * @param {import('@playwright/test').Page}                       page   Playwright's page object. / Playwright の page オブジェクト。
	 * @param {import('@wordpress/e2e-test-utils-playwright').Editor} editor Editor utils. / editor ユーティリティ。
	 * @return {Promise<void>}
	 */
	async function openSearchDialog( page, editor ) {
		// The first editor load right after wp-env starts can be slow, so wait
		// for the top bar's Settings button with a longer timeout first.
		// wp-env 起動直後の最初のエディタ読み込みは遅いことがあるため、
		// 先に上部バーの「Settings」ボタンを長めのタイムアウトで待つ。
		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Settings', exact: true } )
			.waitFor( { timeout: 60000 } );

		// The sidebar and the panel's open/closed state are saved as user
		// preferences, so open them explicitly instead of relying on them.
		// サイドバーとパネルの開閉状態はユーザー設定として保存されるため、
		// 既存の状態に頼らず明示的に開く。
		await editor.openDocumentSettingsSidebar();
		const panel = page.locator( '.vk-ltc-panel' );
		const toggle = panel.getByRole( 'button', {
			name: 'URL to redirect to',
			exact: true,
		} );
		await toggle.waitFor();
		if ( 'true' !== ( await toggle.getAttribute( 'aria-expanded' ) ) ) {
			await toggle.click();
		}

		await panel
			.getByRole( 'button', {
				name: 'Search internal link',
				exact: true,
			} )
			.click();
		await expect( page.locator( DIALOG ) ).toBeVisible();
	}

	/**
	 * Assert the state that must hold after the dialog has been closed.
	 * ダイアログを閉じた後に成り立つべき状態を確認する。
	 *
	 * @param {import('@playwright/test').Page} page Playwright's page object. / Playwright の page オブジェクト。
	 * @return {Promise<void>}
	 */
	async function expectClosedCleanly( page ) {
		// The dialog is hidden.
		// ダイアログが閉じている。
		await expect( page.locator( DIALOG ) ).toBeHidden();

		// The plugin's internal "opened from the panel" flag is reset.
		// プラグイン内部の「パネルから開いた」フラグが戻っている。
		expect( await page.evaluate( () => window.vkLtcLinkMode ) ).toBe(
			false
		);

		// The saved value in the panel is untouched.
		// パネルの保存済みの値が変わっていない。
		await expect(
			page.locator( '.vk-ltc-panel' ).getByLabel( 'URL', { exact: true } )
		).toHaveValue( SAVED_REDIRECT_URL );

		// No uncaught exception happened.
		// 捕捉されなかった例外が出ていない。
		expect( pageErrors ).toEqual( [] );
	}

	test( '「Search internal link」を押すと、例外なくダイアログが開き、最近の項目が出る', async ( {
		page,
		editor,
	} ) => {
		await openSearchDialog( page, editor );

		// No uncaught exception happened while opening (this is the first
		// symptom of the issue: reading `selectionStart` of `undefined`).
		// 開く時に、捕捉されなかった例外が出ていない
		// （issueの1つ目の症状: `undefined` の `selectionStart` を読む例外）。
		expect( pageErrors ).toEqual( [] );

		// The element wpLink keeps as "the field being edited" is the
		// panel's URL field (it holds the saved URL).
		// wpLink が「編集中の入力欄」として保持している要素が、パネルの
		// URL欄（保存済みURLが入っている入力欄）になっている。
		expect(
			await page.evaluate(
				() => window.wpLink.textarea && window.wpLink.textarea.value
			)
		).toBe( SAVED_REDIRECT_URL );

		// Recent items are loaded (they stay empty when opening throws).
		// 最近の項目が読み込まれている（開く時に例外が出ると空のまま）。
		await expect(
			page.locator( DIALOG_RECENT_ITEMS ).first()
		).toBeVisible();
	} );

	test( '「キャンセル」でダイアログが閉じる', async ( { page, editor } ) => {
		await openSearchDialog( page, editor );
		await page.locator( DIALOG_CANCEL_BUTTON ).click();
		await expectClosedCleanly( page );
	} );

	test( '「×」（閉じるボタン）でダイアログが閉じる', async ( {
		page,
		editor,
	} ) => {
		await openSearchDialog( page, editor );
		await page.locator( DIALOG_CLOSE_BUTTON ).click();
		await expectClosedCleanly( page );
	} );

	test( 'Esc キーでダイアログが閉じる', async ( { page, editor } ) => {
		await openSearchDialog( page, editor );
		await page.keyboard.press( 'Escape' );
		await expectClosedCleanly( page );
	} );

	test( 'ダイアログで内部リンクを選んで「リンクを追加」を押すと、閉じてパネルのURL欄に入る', async ( {
		page,
		editor,
	} ) => {
		await openSearchDialog( page, editor );

		// Pick the first recent item. wpLink copies its permalink into the
		// dialog's URL field.
		// 最近の項目の先頭を選ぶ。wpLink がそのパーマリンクをダイアログの
		// URL欄へ入れる。
		await page.locator( DIALOG_RECENT_ITEMS ).first().click();
		const pickedUrl = await page.locator( DIALOG_URL_FIELD ).inputValue();
		expect( pickedUrl ).toMatch( /^https?:\/\// );
		expect( pickedUrl ).not.toBe( SAVED_REDIRECT_URL );

		await page.locator( DIALOG_SUBMIT_BUTTON ).click();

		// The dialog is closed, the flag is reset, and the picked URL is now
		// in the panel's URL field.
		// ダイアログが閉じ、フラグが戻り、選んだURLがパネルのURL欄に入る。
		await expect( page.locator( DIALOG ) ).toBeHidden();
		expect( await page.evaluate( () => window.vkLtcLinkMode ) ).toBe(
			false
		);
		await expect(
			page.locator( '.vk-ltc-panel' ).getByLabel( 'URL', { exact: true } )
		).toHaveValue( pickedUrl );
		expect( pageErrors ).toEqual( [] );
	} );
} );

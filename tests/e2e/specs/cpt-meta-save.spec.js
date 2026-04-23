/**
 * PR #141 / Issue #140 のテスト:
 * カスタム投稿タイプ（CPT）で vk-ltc-link / vk-ltc-target メタが
 * ブロックエディタ（REST API）経由で保存・表示・更新できることを検証する。
 *
 * 前提: tests/e2e/mu-plugins/register-test-cpt.php で CPT "product" が init 優先度 10 で登録されている。
 * 前提: vk_ltc_custom_post_types オプションに ["post","page","product"] が保存されている。
 */
const { test, expect } = require( '@playwright/test' );

// Basic 認証ヘッダを生成するヘルパー。REST API を認証付きで叩く際に使う。
function basicAuthHeader( user, pass ) {
	return 'Basic ' + Buffer.from( user + ':' + pass ).toString( 'base64' );
}

// wp-env の Application Password 機能を使う代わりに、
// 初期ユーザー admin/password でログイン Cookie を取得してから REST を叩く。
// wp-json/wp/v2/* は nonce 認証を要求するため、REST で書き込みたい場合は wp-cli 経由で検証する方針にする。
// ただし読み出し（取得）は Cookie 認証 + _wpnonce が楽なので、ブラウザ経由で REST メタを読む。

test.describe( 'PR #141: CPT リダイレクト用URLのメタ保存・表示・更新', () => {
	test.beforeEach( async ( { page } ) => {
		// WordPress 管理画面にログインする。
		// 失敗時はスクリーンショットが自動取得される。
		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
		await page.waitForURL( /wp-admin/ );
	} );

	test( 'VK-LTC 設定画面で product CPT がチェック対象として表示されている', async ( { page } ) => {
		// options-general.php?page=vk-ltc に遷移して product のチェックボックスが存在することを確認する。
		await page.goto( '/wp-admin/options-general.php?page=vk-ltc' );
		// product のチェックボックスが存在し、かつチェック済み（事前に wp-cli でセット）であることを確認する。
		const checkbox = page.locator( '#vk_ltc_custom_post_types-product' );
		await expect( checkbox ).toBeVisible();
		await expect( checkbox ).toBeChecked();
	} );

	test( 'registered_meta_keys に product CPT 向けの vk-ltc-link / vk-ltc-target が登録されている', async ( { page } ) => {
		// REST API のスキーマ経由ではなく、PHP 側で get_registered_meta_keys() が
		// 対象 CPT (product) 向けに vk-ltc-link / vk-ltc-target を返すことを検証する。
		// これが本 PR の core: 修正前は CPT が init 10 で登録される前に
		// vk_ltc_register_post_meta() が走り、CPT 向けメタ登録が発生しなかった。
		// 修正後は init 99 に遅延 + post_type_exists() ガードで適切に登録される。
		//
		// wp eval で直接確認する手段が取れないため、ブロックエディタの投稿作成画面で
		// wp.data の editor store に meta が公開されていることを間接的に検証する。
		await page.goto( '/wp-admin/post-new.php?post_type=product' );
		// エディタの初期化完了を待つ（canvas / skelton の描画）。
		await page
			.locator( '.interface-interface-skeleton, .edit-post-layout, .editor-editor-canvas' )
			.first()
			.waitFor( { timeout: 30000 } );

		// wp.data.select('core').getEntityRecord('postType','product','<id>') 経由での
		// meta フィールド利用が可能か、代わりに editor の getEditedPostAttribute('meta') で確認する。
		const meta = await page.evaluate( () => {
			const editor = window.wp?.data?.select( 'core/editor' );
			if ( ! editor ) {
				return null;
			}
			return editor.getEditedPostAttribute( 'meta' );
		} );
		expect( meta ).not.toBeNull();
		// meta オブジェクトに vk-ltc-link / vk-ltc-target がプロパティとして存在していることを確認する。
		// （show_in_rest 登録されていれば register_post_meta のデフォルト値として空文字で含まれる）。
		expect( meta ).toHaveProperty( 'vk-ltc-link' );
		expect( meta ).toHaveProperty( 'vk-ltc-target' );
	} );

	test( 'REST API で product 投稿を作成し、vk-ltc-link を保存・取得できる', async ( { page, request } ) => {
		// ログイン済み Cookie を使って REST の nonce を取得する。
		await page.goto( '/wp-admin/post-new.php?post_type=product' );
		// wpApiSettings.nonce から X-WP-Nonce を取る。
		const nonce = await page.evaluate( () => window.wpApiSettings?.nonce );
		expect( nonce ).toBeTruthy();

		const cookies = await page.context().cookies();
		const cookieHeader = cookies.map( ( c ) => `${ c.name }=${ c.value }` ).join( '; ' );

		// product を 1 件作成する (vk-ltc-link も同時に保存)。
		const createRes = await request.post( '/wp-json/wp/v2/product', {
			headers: {
				'X-WP-Nonce': nonce,
				Cookie: cookieHeader,
				'Content-Type': 'application/json',
			},
			data: {
				title: 'e2e test product ' + Date.now(),
				status: 'publish',
				meta: {
					'vk-ltc-link': 'https://example.com/foo',
					'vk-ltc-target': '1',
				},
			},
		} );
		expect( createRes.status() ).toBe( 201 );
		const created = await createRes.json();
		const postId = created.id;
		// レスポンスに meta が含まれ、値が期待通りであることを確認する。
		expect( created?.meta?.[ 'vk-ltc-link' ] ).toBe( 'https://example.com/foo' );
		expect( created?.meta?.[ 'vk-ltc-target' ] ).toBe( '1' );

		// 改めて GET で取得し、永続化されていることを確認する（修正前はここで meta が空になる）。
		const getRes = await request.get( `/wp-json/wp/v2/product/${ postId }?context=edit`, {
			headers: {
				'X-WP-Nonce': nonce,
				Cookie: cookieHeader,
			},
		} );
		expect( getRes.status() ).toBe( 200 );
		const fetched = await getRes.json();
		expect( fetched?.meta?.[ 'vk-ltc-link' ] ).toBe( 'https://example.com/foo' );
		expect( fetched?.meta?.[ 'vk-ltc-target' ] ).toBe( '1' );

		// 値を更新し、更新結果が保持されることを確認する。
		const updateRes = await request.post( `/wp-json/wp/v2/product/${ postId }`, {
			headers: {
				'X-WP-Nonce': nonce,
				Cookie: cookieHeader,
				'Content-Type': 'application/json',
			},
			data: {
				meta: {
					'vk-ltc-link': 'https://example.com/bar',
					'vk-ltc-target': '',
				},
			},
		} );
		expect( updateRes.status() ).toBe( 200 );
		const updated = await updateRes.json();
		expect( updated?.meta?.[ 'vk-ltc-link' ] ).toBe( 'https://example.com/bar' );
		// cleanup
		await request.delete( `/wp-json/wp/v2/product/${ postId }?force=true`, {
			headers: { 'X-WP-Nonce': nonce, Cookie: cookieHeader },
		} );
	} );

	test( 'デグレ: post（投稿）でも vk-ltc-link を保存・取得できる', async ( { page, request } ) => {
		// 既存機能が壊れていないことを検証する（デグレ確認）。
		await page.goto( '/wp-admin/post-new.php' );
		const nonce = await page.evaluate( () => window.wpApiSettings?.nonce );
		const cookies = await page.context().cookies();
		const cookieHeader = cookies.map( ( c ) => `${ c.name }=${ c.value }` ).join( '; ' );

		const res = await request.post( '/wp-json/wp/v2/posts', {
			headers: {
				'X-WP-Nonce': nonce,
				Cookie: cookieHeader,
				'Content-Type': 'application/json',
			},
			data: {
				title: 'e2e regression post ' + Date.now(),
				status: 'publish',
				meta: {
					'vk-ltc-link': 'https://example.com/post-regression',
					'vk-ltc-target': '1',
				},
			},
		} );
		expect( res.status() ).toBe( 201 );
		const body = await res.json();
		expect( body?.meta?.[ 'vk-ltc-link' ] ).toBe( 'https://example.com/post-regression' );
		expect( body?.meta?.[ 'vk-ltc-target' ] ).toBe( '1' );
		await request.delete( `/wp-json/wp/v2/posts/${ body.id }?force=true`, {
			headers: { 'X-WP-Nonce': nonce, Cookie: cookieHeader },
		} );
	} );

	test( 'デグレ: page（固定ページ）でも vk-ltc-link を保存・取得できる', async ( { page, request } ) => {
		// 固定ページでもデグレしていないことを確認する。
		await page.goto( '/wp-admin/post-new.php?post_type=page' );
		const nonce = await page.evaluate( () => window.wpApiSettings?.nonce );
		const cookies = await page.context().cookies();
		const cookieHeader = cookies.map( ( c ) => `${ c.name }=${ c.value }` ).join( '; ' );

		const res = await request.post( '/wp-json/wp/v2/pages', {
			headers: {
				'X-WP-Nonce': nonce,
				Cookie: cookieHeader,
				'Content-Type': 'application/json',
			},
			data: {
				title: 'e2e regression page ' + Date.now(),
				status: 'publish',
				meta: {
					'vk-ltc-link': 'https://example.com/page-regression',
					'vk-ltc-target': '',
				},
			},
		} );
		expect( res.status() ).toBe( 201 );
		const body = await res.json();
		expect( body?.meta?.[ 'vk-ltc-link' ] ).toBe( 'https://example.com/page-regression' );
		await request.delete( `/wp-json/wp/v2/pages/${ body.id }?force=true`, {
			headers: { 'X-WP-Nonce': nonce, Cookie: cookieHeader },
		} );
	} );

	test( 'ブロックエディタ（product 新規作成画面）で URL フィールドの要素が読み込まれる', async ( { page } ) => {
		// 実際のブロックエディタ画面で VK-LTC サイドバー関連 JS がエラーなくロードされることを確認する。
		const consoleErrors = [];
		page.on( 'pageerror', ( err ) => consoleErrors.push( String( err ) ) );
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				consoleErrors.push( msg.text() );
			}
		} );

		await page.goto( '/wp-admin/post-new.php?post_type=product' );
		// ブロックエディタの読み込みを待つ（h1 や .edit-post-header などが出現するまで）。
		await page
			.locator( '.interface-interface-skeleton, .edit-post-layout, .editor-editor-canvas' )
			.first()
			.waitFor( { timeout: 30000 } );
		// JS 致命的エラー（vk-ltc- 由来）がないことを検証する。
		const vkErrors = consoleErrors.filter( ( e ) => /vk-ltc|ltc-link/i.test( e ) );
		expect( vkErrors, `VK-LTC 関連 JS エラー: ${ vkErrors.join( '\n' ) }` ).toHaveLength( 0 );
	} );
} );

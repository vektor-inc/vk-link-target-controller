/**
 * disable-welcome-guide.js
 *
 * Helper that turns off the block editor's "Welcome to the editor" guide
 * for the e2e admin user, on the server side (issue #238).
 * e2e の管理者ユーザーについて、ブロックエディターの「ようこそ」ガイド
 * （ウェルカムガイド）をサーバー側でオフにするヘルパー（issue #238）。
 *
 * Why this is needed / 必要な理由:
 * On a freshly created wp-env site the admin user has no saved
 * preferences, so the guide opens on the first editor load and covers the
 * whole editor (buttons such as "Settings" or the publish notice become
 * unreachable). 2nd and later runs pass only because the guide has been
 * marked as read by then.
 * 作りたての wp-env サイトでは管理者ユーザーの保存済みプリファレンスが
 * 無いため、最初のエディター読み込み時にガイドが開き、エディター全体を
 * 覆う（「Settings」ボタンや公開通知に届かなくなる）。2回目以降に通るのは、
 * その時点でガイドが既読になっているからにすぎない。
 *
 * Why not `editor.setPreferences()` / `admin.createNewPost()`:
 * They only change the editor's in-browser preference store. A fresh
 * user's saved preferences are fetched from the server asynchronously
 * (`GET /wp/v2/users/me`) and, when that response arrives (it can be well
 * after the page looks loaded), it overwrites the in-browser value and
 * the guide comes back. We confirmed this by measurement: the guide
 * reappeared right after publishing. So we write the saved preference
 * itself (user meta `persisted_preferences`) through the REST API
 * before the editor is opened.
 * `editor.setPreferences()` や `admin.createNewPost()` が変えるのはブラウザ内の
 * プリファレンスのストアだけである。作りたてのユーザーの保存済みプリファレンスは
 * サーバーから非同期（`GET /wp/v2/users/me`）で取得され、そのレスポンスが届くと
 * （ページが読み込み済みに見えた後になることもある）ブラウザ内の値を上書きして
 * ガイドが戻ってくる。実測で、公開操作の直後にガイドが再表示されることを
 * 確認している。そのため、エディターを開く前に REST API で保存済みプリファレンス
 * 自体（ユーザーメタ `persisted_preferences`）を書き込む。
 */

/**
 * Save `welcomeGuide: false` into the admin user's persisted preferences,
 * keeping the other saved preferences as they are.
 * 管理者ユーザーの保存済みプリファレンスに `welcomeGuide: false` を保存する。
 * 他の保存済みプリファレンスはそのまま残す。
 *
 * Call this before opening the editor (e.g. in `beforeAll`).
 * エディターを開く前（`beforeAll` など）に呼ぶ。
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils Request utils. / requestUtils。
 * @return {Promise<void>}
 */
async function disableWelcomeGuide( requestUtils ) {
	const me = await requestUtils.rest( {
		path: '/wp/v2/users/me',
		params: { context: 'edit' },
	} );
	const saved = me?.meta?.persisted_preferences || {};

	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users/me',
		data: {
			meta: {
				persisted_preferences: {
					...saved,
					'core/edit-post': {
						...( saved[ 'core/edit-post' ] || {} ),
						welcomeGuide: false,
					},
					// The editor uses the newer `_modified` value when it
					// compares saved data with its local copy.
					// エディターは保存済みデータとローカルのコピーを比べる際、
					// 新しい方の `_modified` を採用する。
					_modified: new Date().toISOString(),
				},
			},
		},
	} );
}

module.exports = { disableWelcomeGuide };

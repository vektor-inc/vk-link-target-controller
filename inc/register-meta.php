<?php
/**
 * Register post meta for REST API access.
 * ブロックエディタからREST API経由でメタデータを読み書きするために登録する。
 *
 * @package vk-link-target-controller
 */

/**
 * Authorization callback for vk-ltc-* post meta.
 * vk-ltc-* メタキーの編集権限チェック。
 *
 * register_meta の auth_callback シグネチャに従い、対象投稿に対する
 * `edit_post` キャパビリティで判定する。`edit_post`（単数形）は WordPress の
 * map_meta_cap により各 CPT の capabilities マッピング（例: `site` CPT では
 * `edit_site`）に解決されるため、capability_type をカスタマイズした CPT でも
 * 正しく権限判定される。
 *
 * @param bool   $allowed   Whether the user is allowed to edit the meta (default from core).
 * @param string $meta_key  Meta key being checked.
 * @param int    $object_id Post ID.
 * @return bool True if the current user can edit the post.
 */
function vk_ltc_meta_auth_callback( $allowed, $meta_key, $object_id ) {
	// 投稿IDが無い場合（一覧や作成前など）は、汎用的な edit_posts にフォールバックする。
	// これは従来と同等の挙動で、post_id ベース判定ができない呼び出し経路への後方互換。
	if ( empty( $object_id ) ) {
		return current_user_can( 'edit_posts' );
	}
	return current_user_can( 'edit_post', (int) $object_id );
}

/**
 * Register vk-ltc-link and vk-ltc-target meta keys for the REST API.
 * vk-ltc-link と vk-ltc-target メタキーをREST APIに登録する。
 *
 * @return void
 */
function vk_ltc_register_post_meta() {
	$vk_ltc = new VK_Link_Target_Controller();
	// カスタム投稿タイプが他プラグイン（CPT UI / ExUnit など）で登録済みであることを前提に、
	// 有効化された投稿タイプ一覧を取得する。
	// register_post_meta() は post_type 単位で呼び出す必要があるため、
	// その post_type が登録されていないとブロックエディタ（REST API）側で
	// 正しくメタが扱われず、リダイレクト用URLが保存・表示されない不具合となる。
	$post_types = $vk_ltc->get_option();

	if ( empty( $post_types ) || ! is_array( $post_types ) ) {
		return;
	}

	foreach ( $post_types as $post_type ) {
		// 対象の post_type が未登録の場合はスキップする。
		// CPT UI や ExUnit のカスタム投稿タイプマネージャーで登録される投稿タイプは
		// `init` フックで登録されるため、本関数の実行タイミングによっては
		// 未登録の状態でここに到達する可能性がある。その場合 register_post_meta() を
		// 呼んでも REST API 側で post_type に紐づくメタとして正しく機能しない。
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}

		register_post_meta(
			$post_type,
			'vk-ltc-link',
			array(
				'type'              => 'string',
				'description'       => 'Redirect URL / リダイレクト先URL',
				'single'            => true,
				'sanitize_callback' => 'esc_url_raw',
				'show_in_rest'      => true,
				// 投稿ID単位で capability を判定する。
				// `edit_post`（単数形）は `map_meta_cap` により各 CPT の実 cap
				// （例: `site` CPT の `edit_site`）に解決されるため、
				// capability_type をカスタマイズした CPT でも正しく権限判定される。
				'auth_callback'     => 'vk_ltc_meta_auth_callback',
			)
		);

		register_post_meta(
			$post_type,
			'vk-ltc-target',
			array(
				'type'              => 'string',
				'description'       => 'Open in new window flag / 別ウィンドウで開くフラグ',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => 'vk_ltc_meta_auth_callback',
			)
		);
	}
}
// CPT UI や ExUnit などで登録されるカスタム投稿タイプは `init` フックで
// 登録されるため、それらの登録処理より後に本関数が実行されるよう
// 優先度を遅らせる（デフォルト 10 → 99）。
// これにより、カスタム投稿タイプに対しても register_post_meta() が
// 確実に呼び出され、ブロックエディタ（REST API）経由のリダイレクト用URL
// 保存・表示が正常に動作する。
add_action( 'init', 'vk_ltc_register_post_meta', 99 );

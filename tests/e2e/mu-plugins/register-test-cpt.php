<?php
/**
 * Register a custom post type for VK Link Target Controller e2e tests.
 * VK Link Target Controller の e2e テストでのみ使用するカスタム投稿タイプを登録する mu-plugin。
 *
 * このファイルは wp-env の「tests」環境（テストサイト）にのみマウントされる
 * 想定で、プロダクト（配布物）には含めない。詳細は `.wp-env.json` の
 * `env.tests.mappings`（`wp-content/mu-plugins` へのマッピング）と、
 * リポジトリルートの `.distignore`（`tests/` を配布対象から除外）を参照。
 *
 * issue #140（CPTでのmeta保存不具合）の回帰テスト（tests/e2e/specs/cpt-meta-save.spec.js）
 * で使うCPTを提供する。VK Link Target Controller は候補投稿タイプが未設定
 * （オプション `vk_ltc_custom_post_types` 未保存）の場合、公開（public）投稿タイプを
 * すべて対象にするため（vk-link-target-controller.php の get_option() / get_public_post_types()
 * 参照）、ここで `public => true` として登録するだけで、追加の管理画面設定なしに
 * 「URL to redirect to」メタボックスが編集画面に表示される。
 *
 * @package vk-link-target-controller
 */

// 直接アクセスを禁止する。
// Disallow direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the e2e test custom post type.
 * e2e テスト専用カスタム投稿タイプを登録する。
 *
 * カスタム投稿タイプの登録は `init` フックで行うのが WordPress の標準的な
 * タイミングであり、VK Link Target Controller 側のメタ登録処理
 * （vk_ltc_register_post_meta、優先度99）より前に投稿タイプが存在している
 * 必要があるため、デフォルト優先度（10）のまま登録する。
 *
 * @return void
 */
function vk_ltc_e2e_register_test_cpt() {
	register_post_type(
		'vk_ltc_e2e_cpt',
		array(
			'label'        => 'VK LTC E2E Test CPT',
			'labels'       => array(
				'name'          => 'VK LTC E2E Test CPT',
				'singular_name' => 'VK LTC E2E Test Post',
			),
			// 公開投稿タイプとして登録する。
			// VK Link Target Controller の候補投稿タイプ判定
			// （オプション未設定時は全公開投稿タイプが対象）に自動的に
			// 含まれるようにするため。
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			// ブロックエディタ（Gutenberg）・REST API 経由での編集を有効化する。
			'show_in_rest' => true,
			// custom-fields を含めることで、REST レスポンスに meta フィールドが
			// 含まれる（register_post_meta 側の前提条件）。
			'supports'     => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'    => 'dashicons-admin-links',
		)
	);
}
add_action( 'init', 'vk_ltc_e2e_register_test_cpt' );

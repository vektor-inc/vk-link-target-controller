<?php
/**
 * Register a custom post type for VK Link Target Controller e2e tests.
 * VK Link Target Controller の e2e テストでのみ使用するカスタム投稿タイプを登録する mu-plugin。
 *
 * This file is intended to be mounted only into wp-env's "tests" environment
 * (the test site) and is not included in the product (distribution). See
 * `.wp-env.json`'s `env.tests.mappings` (mapping to `wp-content/mu-plugins`)
 * and the repository root `.distignore` (excludes `tests/` from distribution).
 * このファイルは wp-env の「tests」環境（テストサイト）にのみマウントされる
 * 想定で、プロダクト（配布物）には含めない。詳細は `.wp-env.json` の
 * `env.tests.mappings`（`wp-content/mu-plugins` へのマッピング）と、
 * リポジトリルートの `.distignore`（`tests/` を配布対象から除外）を参照。
 *
 * Provides the CPT used by the regression test for issue #140 (CPT meta
 * save bug), tests/e2e/specs/cpt-meta-save.spec.js. When VK Link Target
 * Controller's candidate post types option (`vk_ltc_custom_post_types`) is
 * unset, it targets all public post types (see get_option() /
 * get_public_post_types() in vk-link-target-controller.php), so simply
 * registering this CPT with `public => true` makes the "URL to redirect to"
 * meta box appear on the edit screen with no extra admin configuration.
 * issue #140（CPTでのmeta保存不具合）の回帰テスト（tests/e2e/specs/cpt-meta-save.spec.js）
 * で使うCPTを提供する。VK Link Target Controller は候補投稿タイプが未設定
 * （オプション `vk_ltc_custom_post_types` 未保存）の場合、公開（public）投稿タイプを
 * すべて対象にするため（vk-link-target-controller.php の get_option() / get_public_post_types()
 * 参照）、ここで `public => true` として登録するだけで、追加の管理画面設定なしに
 * 「URL to redirect to」メタボックスが編集画面に表示される。
 *
 * @package vk-link-target-controller
 */

// Disallow direct access.
// 直接アクセスを禁止する。
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Do not register this CPT while the PHPUnit test suite is running.
// PHPUnit のテストスイートが実行中の場合は、このCPTを登録しない。
//
// wp-env's "tests" environment shares a single WordPress installation/DB
// between the test site that e2e uses (regular browser access) and PHPUnit
// (WP_UnitTestCase). Custom post type registration is code that runs on
// every request via the `init` hook rather than a value written to the DB
// (such as an option), so it is not subject to the DB rollback
// (transaction) PHPUnit performs after each test. As a result, the
// `vk_ltc_e2e_cpt` registered here for e2e would always exist during
// PHPUnit runs too, breaking existing tests that verify "the list of
// public post types" (e.g. test_get_option in tests/phpunit/test-option.php).
// To avoid this, when PHPUnit execution can be detected, we avoid calling
// register_post_type() at all (early return before the hook is registered).
// wp-env の「tests」環境は、e2e が使うテストサイト（ブラウザからの通常アクセス）と
// PHPUnit（WP_UnitTestCase）が同一のWordPressインストール・DBを共有している。
// カスタム投稿タイプの登録は毎リクエストの `init` フックで実行されるコードであり、
// DBに書き込まれた値（オプション等）ではないため、PHPUnitが各テストごとに行う
// データベースの巻き戻し（トランザクションロールバック）の対象にならない。
// そのため、e2e用にここで登録した `vk_ltc_e2e_cpt` がPHPUnit実行時にも
// 常に存在してしまい、「公開投稿タイプの一覧」を検証する既存テスト
// （tests/phpunit/test-option.php の test_get_option など）を壊してしまう。
// これを避けるため、PHPUnit実行中であることを検出できる場合は
// register_post_type() 自体を呼ばないようにする（フックを登録する前に早期returnする）。
//
// We use `function_exists( 'tests_add_filter' )` for the detection.
// WordPress's test suite loads `includes/functions.php` (the file that
// defines test-only helper functions such as `tests_add_filter()`) at
// startup, before loading `wp-settings.php`. mu-plugins are loaded inside
// `wp-settings.php`, so `tests_add_filter()` is always already defined
// while PHPUnit is running (this repository's tests/phpunit/bootstrap.php
// also runs `require_once $_tests_dir . '/includes/functions.php';` first,
// before proceeding to load the plugin). Regular browser access, which is
// what e2e tests use, never loads the test suite, so this function does not
// exist there.
// 判定には `function_exists( 'tests_add_filter' )` を使う。
// WordPressのテストスイートは、起動時に `includes/functions.php`
// （`tests_add_filter()` などテスト専用のヘルパー関数を定義するファイル）を
// 読み込んでから `wp-settings.php` を読み込む。mu-pluginが読み込まれるのは
// `wp-settings.php` の中であるため、PHPUnit実行中は `tests_add_filter()` が
// 必ず定義済みになっている（このリポジトリの tests/phpunit/bootstrap.php でも
// `require_once $_tests_dir . '/includes/functions.php';` を先に実行してから
// プラグイン読み込みに進んでいる）。一方、e2eテストが使うブラウザ経由の
// 通常アクセスではテストスイート自体が読み込まれないため、この関数は存在しない。
//
// Note: we previously tried using `defined( 'WP_TESTS_DOMAIN' )` for this
// check, but that does not work. In wp-env's tests environment, this
// constant is always defined in `wp-config.php` (written there when
// `wp-env start` runs), so it indicates "is this the tests-port install?"
// rather than "is PHPUnit currently running?" — it was also true during
// e2e's regular access, which made the e2e CPT disappear too (confirmed by
// measurement). Recording this here so nobody falls into the same trap
// again.
// 注意: 以前は `defined( 'WP_TESTS_DOMAIN' )` を判定に使おうとしたが、これは
// 使えない。wp-env の tests 環境では、この定数は `wp-config.php` に
// 常時定義されており（`wp-env start` 時に書き込まれる）、「PHPUnit実行中かどうか」
// ではなく「tests ポートのインストールかどうか」を表すだけだったため、
// e2eの通常アクセスでも真になってしまい、e2e用CPTまで消えてしまっていた
// （実測で確認済み）。同じ落とし穴を踏まないよう、ここに記録しておく。
if ( function_exists( 'tests_add_filter' ) ) {
	return;
}

/**
 * Register the e2e test custom post type.
 * e2e テスト専用カスタム投稿タイプを登録する。
 *
 * Custom post types are registered on the `init` hook per WordPress
 * convention, and the post type must exist before VK Link Target
 * Controller's own meta registration (vk_ltc_register_post_meta, priority
 * 99) runs, so this keeps the default priority (10).
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
			// Register as a public post type so it is automatically
			// included by VK Link Target Controller's candidate post type
			// check (when the option is unset, all public post types are
			// targeted).
			// 公開投稿タイプとして登録する。
			// VK Link Target Controller の候補投稿タイプ判定
			// （オプション未設定時は全公開投稿タイプが対象）に自動的に
			// 含まれるようにするため。
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			// Enable editing via the block editor (Gutenberg) / REST API.
			// ブロックエディタ（Gutenberg）・REST API 経由での編集を有効化する。
			'show_in_rest' => true,
			// Including custom-fields makes the REST response include a
			// meta field (a prerequisite for register_post_meta on the
			// plugin side).
			// custom-fields を含めることで、REST レスポンスに meta フィールドが
			// 含まれる（register_post_meta 側の前提条件）。
			'supports'     => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'    => 'dashicons-admin-links',
		)
	);
}
add_action( 'init', 'vk_ltc_e2e_register_test_cpt' );

<?php
/**
 * Uninstall handler for VK Link Target Controller.
 * VK Link Target Controller のアンインストール処理。
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * WordPress管理画面からプラグインを削除した際に実行される。
 *
 * Removes all plugin-specific data from the database:
 * データベースからプラグイン固有のデータを全て削除する:
 * - post meta: vk-ltc-link, vk-ltc-target
 * - option: custom-post-types
 *
 * @package vk-link-target-controller
 */

// Exit if not called by WordPress uninstall process.
// WordPress のアンインストールプロセスから呼ばれていない場合は終了する。
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all post meta entries created by this plugin.
 * このプラグインが作成した全ての投稿メタを削除する。
 *
 * Uses delete_post_meta_by_key() to efficiently remove all rows
 * for each meta key from the postmeta table.
 * delete_post_meta_by_key() を使用して、postmeta テーブルから
 * 各メタキーの全行を効率的に削除する。
 */
delete_post_meta_by_key( 'vk-ltc-link' );
delete_post_meta_by_key( 'vk-ltc-target' );

/**
 * Delete plugin options from the options table.
 * オプションテーブルからプラグインのオプションを削除する。
 */
delete_option( 'custom-post-types' );

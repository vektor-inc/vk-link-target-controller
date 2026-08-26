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
 * - option: vk_ltc_custom_post_types (and legacy key: custom-post-types)
 *
 * On multisite, this always removes the data from every site in the
 * network, regardless of whether the plugin was network activated.
 * This is because deleting a plugin on multisite requires super admin
 * privileges, and the plugin must already be deactivated (network-wide
 * or per site) before it can be deleted, so there is no reliable
 * network-activation flag left to branch on at uninstall time.
 * マルチサイトの場合、ネットワーク有効化の有無に関わらず、常にネットワーク内の
 * 全サイトからデータを削除する。マルチサイトでのプラグイン削除はスーパー管理者
 * 権限が必要で、削除前に必ず無効化（ネットワーク全体または各サイト単位）が
 * 済んでいる必要があるため、アンインストール実行時点で判定に使えるネットワーク
 * 有効化フラグが残っていないことによる。
 *
 * @package vk-link-target-controller
 */

// Exit if not called by WordPress uninstall process.
// WordPress のアンインストールプロセスから呼ばれていない場合は終了する。
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Guard the declaration with function_exists() because uninstall.php has
// no include guard of its own and can legitimately be `require`d more
// than once within the same PHP process (e.g. PHPUnit tests that
// re-run the uninstall routine for multiple scenarios). Without the
// guard, a second `require` would trigger a fatal
// "Cannot redeclare function" error.
// uninstall.php 自体にはインクルードガードが無く、同一 PHP プロセス内で
// 複数回 require されることが正当にあり得る（例: 複数シナリオのために
// アンインストール処理を繰り返し実行する PHPUnit テスト）。このガードが
// ないと、2回目の require で関数の再宣言による Fatal error になる。
if ( ! function_exists( 'vk_ltc_uninstall_delete_site_data' ) ) {
	/**
	 * Delete all plugin-specific data (post meta and options) for the current site.
	 * 現在のサイトにおけるプラグイン固有データ（投稿メタ・オプション）を全て削除する。
	 *
	 * On multisite this is called once per site while switched into that site's
	 * context via switch_to_blog(). On single site it is called once directly.
	 * マルチサイトでは switch_to_blog() でサイトを切り替えた状態でサイトごとに
	 * 1回ずつ呼ばれる。シングルサイトでは直接1回だけ呼ばれる。
	 *
	 * @return void
	 */
	function vk_ltc_uninstall_delete_site_data() {
		// Delete all post meta entries created by this plugin.
		// このプラグインが作成した全ての投稿メタを削除する。
		// Uses delete_post_meta_by_key() to efficiently remove all rows
		// for each meta key from the postmeta table.
		// delete_post_meta_by_key() を使用して、postmeta テーブルから
		// 各メタキーの全行を効率的に削除する。
		delete_post_meta_by_key( 'vk-ltc-link' );
		delete_post_meta_by_key( 'vk-ltc-target' );

		// Delete plugin options from the options table.
		// オプションテーブルからプラグインのオプションを削除する。
		// Delete both the new prefixed key and the legacy key to ensure
		// complete cleanup regardless of whether migration has occurred.
		// 移行済みかどうかに関わらず完全にクリーンアップするため、
		// 新しいプレフィックス付きキーとレガシーキーの両方を削除する。
		delete_option( 'vk_ltc_custom_post_types' );
		delete_option( 'custom-post-types' );
	}
}

if ( ! is_multisite() ) {
	// Single site: delete data for the current (only) site as before.
	// シングルサイト：これまでと同様、現在の（唯一の）サイトのデータを削除する。
	vk_ltc_uninstall_delete_site_data();
} else {
	/**
	 * Multisite: delete data from every site in the network.
	 * マルチサイト：ネットワーク内の全サイトからデータを削除する。
	 *
	 * Sites are fetched in batches via get_sites() rather than a direct
	 * query on $wpdb->blogs so that site enumeration is delegated to
	 * WordPress's own API, instead of this plugin having to handle the
	 * underlying table structure (and multi-network setups) itself.
	 * By default get_sites() does NOT filter out archived, spam, or
	 * deleted-flagged sites (those args default to null, which means
	 * "no filter" in WP_Site_Query) - it simply returns every site in
	 * the network. That is intentional here: uninstall must remove this
	 * plugin's data everywhere, so sites in any of those states are
	 * deliberately included rather than skipped. No wp_is_large_network()
	 * branch is used; instead sites are simply processed in batches using
	 * number/offset so memory use stays bounded even on large networks.
	 * get_sites() を使ってサイトIDをバッチ取得する（$wpdb->blogs への直接
	 * クエリは使わない）。これは、対象を絞り込むためではなく、サイト一覧の
	 * 取得を WordPress 標準の API に任せ、テーブル構造やマルチネットワーク
	 * 構成の違いをこのプラグイン側で個別に扱わずに済ませるため。
	 * get_sites() は既定では archived・spam・削除済みフラグの立ったサイトを
	 * 除外しない（これらの引数は既定値が null で、WP_Site_Query 上は
	 * 「絞り込みなし」を意味する）。つまりネットワーク内の全サイトがそのまま
	 * 返る。アンインストールはこのプラグインのデータをどこにも残さず消す
	 * ための処理なので、これらの状態のサイトもあえて除外せず削除対象に
	 * 含めている。wp_is_large_network() による分岐は行わず、number/offset
	 * でバッチ処理することで、大規模ネットワークでもメモリ使用量を一定範囲に
	 * 抑える。
	 */
	$batch_size = 100;
	$offset     = 0;

	do {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $batch_size,
				'offset' => $offset,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			try {
				vk_ltc_uninstall_delete_site_data();
			} finally {
				// Always restore the previous blog, even if an exception
				// or fatal-triggering condition occurs during deletion,
				// so that a failure on one site never leaves subsequent
				// sites (or the rest of the uninstall process) operating
				// against the wrong site's context.
				// 削除処理中に例外が発生しても必ず元のサイトへ戻す。
				// 1サイトでの失敗が、以降のサイト（やアンインストール処理の
				// 残り）を誤ったサイトのコンテキストのまま動かす原因になる
				// ことを防ぐ。
				restore_current_blog();
			}
		}

		$offset += $batch_size;
	} while ( count( $site_ids ) === $batch_size );
}

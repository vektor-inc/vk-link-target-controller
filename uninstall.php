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

		// Delete the new, plugin-prefixed option unconditionally: its name
		// is specific enough to this plugin that no ownership check is
		// needed here.
		// 新しいプレフィックス付きオプションは無条件で削除する。この名前は
		// このプラグイン専用と分かるため、所有権の確認は不要。
		delete_option( 'vk_ltc_custom_post_types' );

		// The legacy option key `custom-post-types` has no plugin-specific
		// prefix, so another plugin could plausibly be using the exact same
		// option name for something unrelated. On single site this was
		// never a real risk in practice, because uninstall.php only ran
		// against the one site an admin chose to delete the plugin from.
		// On multisite this loop now runs the same delete on every site in
		// the network, including sites that may never have had this plugin
		// installed - so an unguarded delete_option() here would newly risk
		// wiping another plugin's same-named option on those sites.
		// Guard it by checking the stored value looks like what this plugin
		// would have saved (an array of post type slugs - see
		// VK_Link_Target_Controller::sanitize_settings()). This is not
		// proof of ownership (another plugin could coincidentally also
		// store an array under this name), but it is strictly safer than
		// no check at all, and costs nothing since deleting a non-existent
		// option is already a no-op.
		// レガシーキー `custom-post-types` はこのプラグイン専用のプレフィックスが
		// 無いため、他のプラグインが全く別の用途で同名のオプションキーを使って
		// いる可能性がある。シングルサイトでは uninstall.php は管理者が削除を
		// 選んだ1サイトに対してしか実行されなかったため、実質的なリスクには
		// なっていなかった。マルチサイトでは同じ削除処理がネットワーク内の
		// 全サイト（このプラグインを一度も導入していないサイトを含む）に対して
		// 走るようになったため、無条件の delete_option() はそれらのサイトで
		// 他プラグインの同名オプションを消してしまう新たなリスクになる。
		// 保存値がこのプラグインが保存する形（投稿タイプスラッグの配列。
		// VK_Link_Target_Controller::sanitize_settings() を参照）かどうかで
		// 絞り込む。これは所有権の証明にはならない（他プラグインが偶然同名で
		// 配列を保存している可能性は残る）が、ノーガードよりは確実に安全側で
		// あり、存在しないオプションの削除はもともと no-op なのでコストも無い。
		$legacy_option = get_option( 'custom-post-types', null );
		if ( is_array( $legacy_option ) ) {
			delete_option( 'custom-post-types' );
		}
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
				'fields'                 => 'ids',
				'number'                 => $batch_size,
				'offset'                 => $offset,
				// update_site_cache / update_site_meta_cache default to true,
				// which would otherwise prime the site and blogmeta caches
				// (_prime_site_caches()) for every batch even though
				// 'fields' => 'ids' only needs the IDs, and this data is
				// discarded immediately after the delete loop below runs.
				// Disable both so uninstall doesn't pay for cache warming
				// that has no one left to benefit from it.
				// update_site_cache / update_site_meta_cache は既定で true の
				// ため、'fields' => 'ids' で ID しか使わないにもかかわらず、
				// バッチ毎にサイト・blogmeta キャッシュ（_prime_site_caches()）
				// が投入されてしまう。しかもこのデータは直後の削除ループが
				// 終わればすぐ捨てられる。恩恵を受ける相手がいないキャッシュ
				// 投入のコストを払わずに済むよう、両方無効化する。
				'update_site_cache'      => false,
				'update_site_meta_cache' => false,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			try {
				vk_ltc_uninstall_delete_site_data();
			} finally {
				// What this `finally` guarantees is narrower than "the loop
				// keeps going": if deletion on this site throws, the
				// exception still propagates and the loop stops - the
				// remaining sites in this and later batches are NOT
				// processed. What `finally` does guarantee is that, even
				// when that happens, we always restore the blog context we
				// switched away from before the exception is allowed to
				// propagate further, instead of leaving PHP's blog stack in
				// whatever state switch_to_blog() left it in.
				// この finally が保証しているのは「ループが止まらない」ことでは
				// ない。このサイトでの削除処理が例外を投げた場合、例外はその
				// まま伝播してループは止まり、このバッチ・以降のバッチに残って
				// いるサイトは処理されない。finally が保証しているのは、その
				// 場合でも、例外がさらに外へ伝播する前に、switch_to_blog() で
				// 切り替える前の元のサイトのコンテキストへ必ず戻すということ
				// （switch_to_blog() が残したままの状態でスタックを放置しない）。
				restore_current_blog();
			}
		}

		$offset += $batch_size;
	} while ( count( $site_ids ) === $batch_size );
}

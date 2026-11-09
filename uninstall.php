<?php
/**
 * Uninstall handler for VK Link Target Controller.
 * VK Link Target Controller のアンインストール処理。
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * WordPress管理画面からプラグインを削除した際に実行される。
 *
 * Deletes post meta and settings only on sites that explicitly opted in via
 * vk_ltc_delete_data_on_uninstall. By default all data is retained.
 * vk_ltc_delete_data_on_uninstall が ON のサイトのみ投稿メタと設定を削除する。
 * 既定では全てのデータを保持する。
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
	 * Delete plugin-specific data for the current site only when opted in.
	 * 現在のサイトが削除を許可した場合のみプラグイン固有データを削除する。
	 *
	 * On multisite this is called once per site while switched into that site's
	 * context via switch_to_blog(). On single site it is called once directly.
	 * マルチサイトでは switch_to_blog() でサイトを切り替えた状態でサイトごとに
	 * 1回ずつ呼ばれる。シングルサイトでは直接1回だけ呼ばれる。
	 *
	 * @return void
	 */
	function vk_ltc_uninstall_delete_site_data() {
		// Require explicit consent in the current site's context before deleting anything.
		// サイトごとの明示的な許可を確認してから削除する.
		if ( ! in_array( get_option( 'vk_ltc_delete_data_on_uninstall', 0 ), array( 1, '1', true ), true ) ) {
			return;
		}

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
		delete_option( 'vk_ltc_delete_data_on_uninstall' );

		// Even with explicit consent, retain non-array legacy values: the unprefixed
		// key may belong to another plugin. An array matches our historical format,
		// but is not proof of ownership.
		// 明示的な削除許可があっても、他プラグインが使う可能性のある配列以外の
		// レガシー値は保持する。配列は旧形式と一致するが、所有権の証明ではない。
		$legacy_option = get_option( 'custom-post-types', null );
		if ( is_array( $legacy_option ) ) {
			delete_option( 'custom-post-types' );
		}
	}
}

if ( ! is_multisite() ) {
	// Single site: apply the current site's uninstall preference.
	// シングルサイト：現在のサイトの削除設定を適用する。
	vk_ltc_uninstall_delete_site_data();
} else {
	/**
	 * Multisite: apply each site's uninstall preference.
	 * マルチサイト：各サイトの削除設定を適用する。
	 *
	 * Sites are fetched in batches via get_sites() rather than a direct
	 * query on $wpdb->blogs so that site enumeration is delegated to
	 * WordPress's own API, instead of this plugin having to handle the
	 * underlying table structure (and multi-network setups) itself.
	 * By default get_sites() does NOT filter out archived, spam, or
	 * deleted-flagged sites (those args default to null, which means
	 * "no filter" in WP_Site_Query) - it simply returns every site in
	 * the network. That is intentional here: uninstall must check each
	 * site's preference, so sites in any of those states are
	 * deliberately included rather than skipped.
	 * get_sites() を使ってサイトIDをバッチ取得する（$wpdb->blogs への直接
	 * クエリは使わない）。これは、対象を絞り込むためではなく、サイト一覧の
	 * 取得を WordPress 標準の API に任せ、テーブル構造やマルチネットワーク
	 * 構成の違いをこのプラグイン側で個別に扱わずに済ませるため。
	 * get_sites() は既定では archived・spam・削除済みフラグの立ったサイトを
	 * 除外しない（これらの引数は既定値が null で、WP_Site_Query 上は
	 * 「絞り込みなし」を意味する）。つまりネットワーク内の全サイトがそのまま
	 * 返る。アンインストールは各サイトの削除設定を確認する
	 * 処理なので、これらの状態のサイトもあえて除外せず削除対象に
	 * 含めている。
	 *
	 * Note on scope: "network" here is a loose description, not a literal
	 * one. WP_Site_Query only adds a `site_id = %d` (network) restriction
	 * when its `network_id` arg is non-empty, and we don't pass one (it
	 * defaults to 0 / "no restriction"), so on a multi-network install this
	 * actually returns every site across ALL networks, not just the one
	 * the currently active network happens to be. That matches reality
	 * though: this plugin's files are shared install-wide (not per
	 * network), and deleting it removes those files for the whole install,
	 * so cleaning up data install-wide here is the correct scope, not an
	 * accidental over-reach.
	 * スコープについての注記: ここで言う「ネットワーク」は正確な表現ではない。
	 * WP_Site_Query は `network_id` 引数を渡した場合のみ `site_id = %d`
	 * （ネットワーク）による絞り込みを追加する。今回はこの引数を渡していない
	 * （既定値は 0 で「絞り込みなし」を意味する）ため、マルチネットワーク構成
	 * では現在のネットワークだけでなく、**インストール全体（全ネットワーク）の
	 * 全サイト**が対象になる。ただしこれは実態として正しい範囲でもある。この
	 * プラグインのファイルはネットワーク単位ではなくインストール全体で共有
	 * されており、削除するとインストール全体からファイルが消えるため、データの
	 * 後始末もインストール全体を対象にするのが妥当な範囲であり、意図しない
	 * 過剰な範囲ではない。
	 *
	 * No wp_is_large_network() branch is used; instead sites are simply
	 * processed in batches using number/offset so memory use stays bounded
	 * even on large networks. Note, though, that batching only bounds
	 * memory - it does not change the fact that this whole loop still runs
	 * inside a single HTTP request (delete_plugins() calls
	 * uninstall_plugin() synchronously, before it deletes the plugin
	 * files). On an install with thousands of sites, the constraint that
	 * actually bites first in practice is PHP's max_execution_time, not
	 * memory: if the request times out partway through this loop, it dies
	 * before ever reaching the plugin file deletion, so from the admin's
	 * point of view the plugin still appears installed and clicking
	 * "Delete" again restarts this whole loop from site 1, re-deleting
	 * already-clean sites' (already absent) data along the way.
	 * wp_is_large_network() による分岐は行わず、number/offset でバッチ処理
	 * することで、大規模ネットワークでもメモリ使用量を一定範囲に抑える。
	 * ただし、バッチ処理が抑えるのはメモリ使用量だけであり、このループ全体が
	 * 1回の HTTP リクエスト内で完結する処理であること自体は変わらない
	 * （delete_plugins() はプラグインファイルを削除する前に、同期的に
	 * uninstall_plugin() を呼び出す）。数千サイト規模のインストールでは、
	 * 実際に先に効いてくる制約はメモリではなく PHP の max_execution_time で
	 * ある。ループの途中でリクエストがタイムアウトすると、プラグインファイルの
	 * 削除まで到達せずに処理が落ちるため、管理者から見るとプラグインは
	 * インストールされたままに見え、再度「削除」を押すたびにこのループが
	 * サイト1件目からやり直しになる（既にクリーンなサイトに対しても、既に
	 * 存在しないデータの削除を再度試みることになる）。
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

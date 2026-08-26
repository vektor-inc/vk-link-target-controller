<?php
/**
 * Test uninstall cleanup functionality on a WordPress multisite network.
 * WordPress マルチサイトネットワーク上でのアンインストール時のDB掃除機能のテスト。
 *
 * This test only runs when the PHPUnit process is booted in multisite mode
 * (e.g. `WP_MULTISITE=1 vendor/bin/phpunit -c .phpunit.xml`). It is skipped
 * on a normal single-site run, so it does not affect the existing
 * single-site PHPUnit job.
 * このテストは PHPUnit プロセスがマルチサイトモードで起動された場合のみ実行される
 * （例: `WP_MULTISITE=1 vendor/bin/phpunit -c .phpunit.xml`）。通常のシングルサイト
 * 実行時はスキップされるため、既存のシングルサイト用 PHPUnit ジョブに影響しない。
 *
 * @package vk-link-target-controller
 */

/**
 * Uninstall multisite test case.
 * アンインストールのマルチサイトテストケース。
 */
class UninstallMultisiteTest extends WP_UnitTestCase {

	/**
	 * Skip the whole class when the test run is not multisite.
	 *
	 * This is intentionally just a skip, not a skip-or-fail based on
	 * getenv( 'WP_MULTISITE' ): WordPress's own test bootstrap
	 * (vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php) decides
	 * whether to install as multisite using that exact same
	 * '1' === getenv( 'WP_MULTISITE' ) check. Reading it again here can
	 * only ever agree with what the bootstrap already decided - if the
	 * env var reached this PHP process, multisite is already active and
	 * this branch isn't taken at all; if it didn't reach this process, it
	 * didn't reach the bootstrap either, so `getenv()` here reads exactly
	 * as empty as it did there. There is no scenario where this check
	 * would see '1' while is_multisite() is still false, so a fail()
	 * based on it would be dead code.
	 *
	 * The failure mode this is actually guarding against - the
	 * "PHP Unit Test (Multisite)" CI job finishing green while never
	 * actually running in multisite mode (e.g. WP_MULTISITE=1 never
	 * reaching the phpunit process inside the container) - is instead
	 * caught at the CI job level, by grepping the multisite job's test
	 * output for the "Running as multisite..." line that bootstrap.php
	 * prints when it does install as multisite (see the
	 * "Run PHP Unit Test (Multisite)" step in
	 * .github/workflows/php_unit_test.yml). That check has access to
	 * bootstrap's actual decision as it happened; this test does not.
	 *
	 * テスト実行がマルチサイトでない場合はクラス全体をスキップする。
	 *
	 * ここであえて単純な skip のみとし、getenv( 'WP_MULTISITE' ) を見て
	 * skip か fail かを切り替えることはしていない。WordPress 本体のテスト
	 * ブートストラップ（vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php）
	 * 自体が、マルチサイトとしてインストールするかどうかを全く同じ
	 * '1' === getenv( 'WP_MULTISITE' ) という式で判定している。ここで
	 * もう一度読んでも、ブートストラップが既に下した判定と一致する結果
	 * にしかならない。環境変数がこの PHP プロセスに届いていれば、その時点で
	 * 既にマルチサイトが有効になっており、このブロック自体に入らない。届いて
	 * いなければ、ブートストラップにも届いていないということなので、ここでの
	 * getenv() もブートストラップ側と同じく空を返す。つまり
	 * is_multisite() が false のまま getenv() が '1' を返すケースは存在
	 * せず、それを根拠にした fail() はデッドコードになってしまう。
	 *
	 * ここで実際に防ぎたい失敗モード ――「PHP Unit Test (Multisite)」
	 * ジョブが、実際にはマルチサイトで一度も走らないまま緑で終わってしまう
	 * こと（例: コンテナ内の phpunit プロセスまで WP_MULTISITE=1 が届かない
	 * 場合）―― は、代わりに CI ジョブ側で担保している。マルチサイトジョブの
	 * テスト出力に対して、bootstrap.php が実際にマルチサイトとしてインストール
	 * した際に出力する "Running as multisite..." という行を grep で確認する
	 * （.github/workflows/php_unit_test.yml の
	 * "Run PHP Unit Test (Multisite)" ステップを参照）。この検証はブート
	 * ストラップが実際に下した判定そのものにアクセスできるが、このテスト側の
	 * getenv() には見えない。
	 */
	public function set_up() {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test only runs in multisite mode (WP_MULTISITE=1). See .github/workflows/php_unit_test.yml for the CI-side check that this actually ran in multisite mode.' );
		}
	}

	/**
	 * Create a post on the given site, optionally with plugin-specific post
	 * meta and options set as well.
	 * 指定したサイトに投稿を作成する。$with_plugin_data が true の場合は
	 * プラグイン固有の投稿メタとオプションも合わせて設定する。
	 *
	 * A real post is always created (even when $with_plugin_data is false)
	 * so that later assertions check get_post_meta() against an existing
	 * post with no meta (which returns ''), not a non-existent post
	 * (which would return false and make the assertion meaningless).
	 * $with_plugin_data が false でも必ず投稿を作成する。これにより、後の
	 * アサーションで get_post_meta() を「メタの無い実在の投稿」に対して
	 * 確認できる（この場合は '' が返る）。存在しない投稿に対する確認だと
	 * false が返ってしまいアサーションの意味が無くなるため。
	 *
	 * @param int  $blog_id          Site ID to create the post on.
	 * @param bool $with_plugin_data Whether to also set plugin-specific post meta and options.
	 * @return int The ID of the post that was created.
	 */
	private function create_site_data( $blog_id, $with_plugin_data ) {
		switch_to_blog( $blog_id );

		$post_id = $this->factory->post->create();

		if ( $with_plugin_data ) {
			update_post_meta( $post_id, 'vk-ltc-link', 'https://example.com/site-' . $blog_id );
			update_post_meta( $post_id, 'vk-ltc-target', '1' );
			update_option( 'vk_ltc_custom_post_types', array( 'post', 'page' ) );
			update_option( 'custom-post-types', array( 'post' ) );
		}

		restore_current_blog();

		return $post_id;
	}

	/**
	 * Assert that plugin-specific post meta and options are absent on the given site.
	 * 指定したサイトにプラグイン固有の投稿メタとオプションが存在しないことを確認する。
	 *
	 * @param int    $blog_id Site ID to check.
	 * @param int    $post_id Post ID to check meta on.
	 * @param string $message Assertion failure message context.
	 * @return void
	 */
	private function assert_site_data_deleted( $blog_id, $post_id, $message ) {
		switch_to_blog( $blog_id );

		$this->assertSame( '', get_post_meta( $post_id, 'vk-ltc-link', true ), $message . " (blog {$blog_id} vk-ltc-link)" );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'vk-ltc-link' ), $message . " (blog {$blog_id} vk-ltc-link should be deleted)" );
		$this->assertSame( '', get_post_meta( $post_id, 'vk-ltc-target', true ), $message . " (blog {$blog_id} vk-ltc-target)" );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'vk-ltc-target' ), $message . " (blog {$blog_id} vk-ltc-target should be deleted)" );
		$this->assertFalse( get_option( 'vk_ltc_custom_post_types', false ), $message . " (blog {$blog_id} vk_ltc_custom_post_types option)" );
		$this->assertFalse( get_option( 'custom-post-types', false ), $message . " (blog {$blog_id} legacy custom-post-types option)" );

		restore_current_blog();
	}

	/**
	 * Build a network of sites, optionally seed them with plugin data, run
	 * uninstall.php once, then assert every site ends up clean and the blog
	 * context uninstall.php ran in was restored. Shared by the individual
	 * test_* methods below so each scenario is its own test method (clearer
	 * failure attribution) without duplicating the setup/run/assert steps.
	 * サイトのネットワークを構築し、必要に応じてプラグインのデータを投入した上で
	 * uninstall.php を1回実行し、全サイトがクリーンになっていること、および
	 * uninstall.php 実行時のサイトコンテキストが復元されていることを確認する。
	 * 各シナリオを個別のテストメソッドに分ける（失敗時にどのケースかが分かり
	 * やすくなる）一方で、共通のセットアップ・実行・確認手順を重複させない
	 * ために、下の test_* メソッドから共通で呼び出す。
	 *
	 * @param int    $additional_site_count Number of extra subsites to create beyond the main site.
	 * @param string $data_on               'all' to seed every site with plugin data, 'partial' to seed only every other site.
	 * @param string $test_condition_name   Japanese description of this scenario, used in assertion failure messages.
	 * @return void
	 */
	private function run_uninstall_multisite_case( $additional_site_count, $data_on, $test_condition_name ) {

		// Build the list of sites for this case: the main site plus
		// `$additional_site_count` freshly created subsites.
		// このケース用のサイト一覧を構築する：メインサイト＋ `$additional_site_count` 件の新規サブサイト。
		$blog_ids = array( get_current_blog_id() );
		for ( $i = 0; $i < $additional_site_count; $i++ ) {
			$blog_ids[] = $this->factory->blog->create();
		}

		// Set up conditions: which sites get plugin data. A post is
		// created on every site regardless, only the plugin meta/options
		// are conditional (see create_site_data() for why).
		// 条件の設定：どのサイトにプラグインのデータを持たせるか。投稿は
		// 全サイトに作成し、プラグインのメタ・オプションのみ条件付きにする
		// （理由は create_site_data() を参照）。
		$post_ids = array();
		foreach ( $blog_ids as $index => $blog_id ) {
			$should_set_data = ( 'all' === $data_on )
				|| ( 'partial' === $data_on && 0 === $index % 2 );

			$post_ids[ $blog_id ] = $this->create_site_data( $blog_id, $should_set_data );
		}

		$blog_id_before_uninstall = get_current_blog_id();

		// Define WP_UNINSTALL_PLUGIN if not already defined, then include uninstall.php.
		// WP_UNINSTALL_PLUGIN が未定義の場合は定義し、uninstall.php を読み込む。
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		// Assert the current blog context was restored to whatever it was
		// before uninstall.php ran, proving switch_to_blog()/restore_current_blog()
		// stayed balanced across the whole loop.
		// uninstall.php 実行前のサイトコンテキストへ復帰していることを確認する。
		// これにより switch_to_blog() / restore_current_blog() がループ全体で
		// 対になっていることが証明される。
		$this->assertSame(
			$blog_id_before_uninstall,
			get_current_blog_id(),
			$test_condition_name . ' (current blog should be restored after uninstall)'
		);

		// Assert every site (whether or not it had data) ends up clean.
		// データの有無に関わらず、全サイトがクリーンな状態になっていることを確認する。
		foreach ( $blog_ids as $blog_id ) {
			$this->assert_site_data_deleted( $blog_id, $post_ids[ $blog_id ], $test_condition_name );
		}
	}

	/**
	 * Test: main site and both subsites all have plugin data => everything
	 * is deleted network-wide.
	 * テスト: メインサイトと2つのサブサイト全てにデータがある場合 => 全サイトの
	 * データが削除される。
	 */
	function test_uninstall_multisite_all_sites_have_data() {
		$this->run_uninstall_multisite_case(
			2,
			'all',
			'メインサイトと2つのサブサイト全てにデータがある状態でアンインストールを実行した場合 => 全サイトのデータが削除される'
		);
	}

	/**
	 * Test: only some subsites have plugin data => uninstall still finishes
	 * cleanly and every site (with or without data) ends up clean.
	 * テスト: 一部のサブサイトにのみデータがある場合 => データがあるサイトも
	 * 無いサイトも正常にクリーンな状態で完了する。
	 */
	function test_uninstall_multisite_partial_sites_have_data() {
		$this->run_uninstall_multisite_case(
			2,
			'partial',
			'一部のサブサイトにのみデータがある状態でアンインストールを実行した場合 => データがあるサイトも無いサイトも正常にクリーンな状態で完了する'
		);
	}

	/**
	 * Test (boundary case): no subsites exist, only the main site, and it
	 * has plugin data => the get_sites() batch loop still works correctly
	 * with the smallest possible network (a single site).
	 * テスト（境界値）: サブサイトが1つも無く、メインサイトのみでデータがある
	 * 場合 => 最小構成（サイト1件）のネットワークでも get_sites() のバッチ
	 * ループが正しく動作し、メインサイトのデータが削除される。
	 */
	function test_uninstall_multisite_no_subsites() {
		$this->run_uninstall_multisite_case(
			0,
			'all',
			'サブサイトが1つも無く、メインサイトのみでデータがある状態でアンインストールを実行した場合 => メインサイトのデータが削除される'
		);
	}

	/**
	 * Test: a subsite that never had this plugin's data, but happens to
	 * have a non-array value stored under the legacy option name
	 * `custom-post-types` (as another plugin might, since that name has no
	 * plugin-specific prefix) => uninstall must NOT delete that value on
	 * that site. This pins down the actual reason
	 * vk_ltc_uninstall_delete_site_data()'s is_array() guard exists:
	 * without it, running uninstall network-wide would delete data that
	 * never belonged to this plugin, on a site that never installed it.
	 * The other uninstall tests only ever seed `custom-post-types` with
	 * arrays, so they could not have caught a regression that removed or
	 * weakened this guard.
	 * テスト: このプラグインを一度も導入していないサブサイトに、レガシー
	 * オプション名 custom-post-types で配列でない値が入っている場合（この
	 * 名前にはプレフィックスが無いため、他プラグインが同名で使っている
	 * 想定）=> アンインストールを実行してもその値は削除されない。これは
	 * vk_ltc_uninstall_delete_site_data() の is_array() ガードが実際に
	 * 存在する理由そのものを固定するテストである。ガードが無ければ、
	 * ネットワーク全体でのアンインストール実行が、このプラグインを一度も
	 * 導入していないサイト上の、このプラグインとは無関係なデータまで
	 * 消してしまう。他のアンインストールテストは custom-post-types に
	 * 配列しか投入していないため、このガードが失われる・弱まる regression
	 * を検知できなかった。
	 */
	function test_uninstall_multisite_preserves_non_array_legacy_option_on_untouched_site() {
		$main_blog_id = get_current_blog_id();

		// Main site: seeded with real plugin data, to prove normal cleanup
		// still works correctly alongside the untouched-site scenario below.
		// メインサイト：実際のプラグインデータを投入し、以下の「導入していない
		// サイト」のシナリオと同時に走っても通常の削除が問題なく動くことを
		// 確認する。
		$main_post_id = $this->create_site_data( $main_blog_id, true );

		// Subsite that never had this plugin installed: no post meta, no
		// plugin-prefixed option - only a non-array value under the legacy
		// option name, simulating another plugin's unrelated use of it.
		// このプラグインを一度も導入していないサブサイト：投稿メタも新オプション
		// も無く、レガシーオプション名にのみ配列でない値が入っている（他
		// プラグインが無関係な用途で同名を使っている状態を想定）。
		$untouched_blog_id = $this->factory->blog->create();
		switch_to_blog( $untouched_blog_id );
		$foreign_legacy_value = 'not-an-array-value-from-another-plugin';
		update_option( 'custom-post-types', $foreign_legacy_value );
		restore_current_blog();

		// Define WP_UNINSTALL_PLUGIN if not already defined, then include uninstall.php.
		// WP_UNINSTALL_PLUGIN が未定義の場合は定義し、uninstall.php を読み込む。
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		// The main site's real plugin data is still cleaned up as normal.
		// メインサイトの実際のプラグインデータは通常どおり削除される。
		$this->assert_site_data_deleted( $main_blog_id, $main_post_id, 'メインサイトの実データ' );

		// The untouched site's foreign-looking legacy value must survive
		// completely unchanged: this is the guard's whole reason for existing.
		// 「導入していないサイト」の他プラグイン由来に見えるレガシー値は、
		// 一切変更されずに残らなければならない：これがガードの存在理由そのもの
		// である。
		switch_to_blog( $untouched_blog_id );
		$this->assertSame(
			$foreign_legacy_value,
			get_option( 'custom-post-types', false ),
			'このプラグインを導入していないサイトの custom-post-types（配列でない値）は削除されずに残る'
		);
		restore_current_blog();
	}
}

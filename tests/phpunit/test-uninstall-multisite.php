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
	 * テスト実行がマルチサイトでない場合はクラス全体をスキップする。
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test only runs in multisite mode (WP_MULTISITE=1).' );
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
	 * @param int  $blog_id         Site ID to create the post on.
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
	 * Test that uninstall.php removes post meta and options from every site
	 * on the network, and always restores the original blog context.
	 * uninstall.php がネットワーク内の全サイトから投稿メタとオプションを削除し、
	 * 常に元のサイトのコンテキストへ復帰することをテストする。
	 */
	function test_uninstall_multisite() {

		// テストケースの配列
		// Test cases array. Each case builds its own set of sites, sets data
		// according to `data_on` (index into the created site list), runs
		// uninstall.php once, then expects every site to be clean.
		// 各ケースは独自のサイト集合を構築し、`data_on`（作成したサイト一覧への
		// インデックス）に従ってデータを設定し、uninstall.php を1回実行した後、
		// 全サイトがクリーンになっていることを期待する。
		$test_cases = array(
			array(
				'test_condition_name' => 'メインサイトと2つのサブサイト全てにデータがある状態でアンインストールを実行した場合 => 全サイトのデータが削除される',
				'site_count'          => 2, // Additional subsites beyond the main site. メインサイト以外に作成する追加サイト数。
				'data_on'             => 'all',
			),
			array(
				'test_condition_name' => '一部のサブサイトにのみデータがある状態でアンインストールを実行した場合 => データがあるサイトも無いサイトも正常にクリーンな状態で完了する',
				'site_count'          => 2,
				'data_on'             => 'partial',
			),
			array(
				'test_condition_name' => 'サブサイトが1つも無く、メインサイトのみでデータがある状態でアンインストールを実行した場合 => メインサイトのデータが削除される',
				'site_count'          => 0,
				'data_on'             => 'all',
			),
		);

		foreach ( $test_cases as $case ) {

			// Build the list of sites for this case: the main site plus
			// `site_count` freshly created subsites.
			// このケース用のサイト一覧を構築する：メインサイト＋ `site_count` 件の新規サブサイト。
			$blog_ids = array( get_current_blog_id() );
			for ( $i = 0; $i < $case['site_count']; $i++ ) {
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
				$should_set_data = ( 'all' === $case['data_on'] )
					|| ( 'partial' === $case['data_on'] && 0 === $index % 2 );

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
				$case['test_condition_name'] . ' (current blog should be restored after uninstall)'
			);

			// Assert every site (whether or not it had data) ends up clean.
			// データの有無に関わらず、全サイトがクリーンな状態になっていることを確認する。
			foreach ( $blog_ids as $blog_id ) {
				$this->assert_site_data_deleted( $blog_id, $post_ids[ $blog_id ], $case['test_condition_name'] );
			}
		}
	}
}

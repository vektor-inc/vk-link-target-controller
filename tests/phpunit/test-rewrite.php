<?php
/**
 * Class rewriteTest
 *
 * @package Vk_Link_Target_Controller
 */

/**
 * Option test case.
 */
class rewriteTest extends WP_Ajax_UnitTestCase {

	/**
	 * PHP Unit テストにあたって、各種投稿やカスタム投稿タイプ、カテゴリーを登録します。
	 *
	 * @return array $test_posts : 作成した投稿の記事idなどを配列で返します。
	 */
	public static function create_test_posts() {

		$test_posts = array();

		/******************************************
		 * テスト用投稿の登録 */

		// 通常の投稿 Test Post を投稿.
		$post                  = array(
			'post_title'   => 'Test Post',
			'post_status'  => 'publish',
			'post_content' => 'content',
		);
		$test_posts['post_id'] = wp_insert_post( $post );
		return $test_posts;
	}


	/**
	 *
	 */
	function test_rewrite() {

		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'rewrite_link_filter()' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		// Create test posts.
		$test_posts = self::create_test_posts();

		$post = get_post( $test_posts['post_id'] );

		$test_array = array(
			array(
				'original' => get_permalink( $test_posts['post_id'] ),
				'expected' => get_permalink( $test_posts['post_id'] ),
			),
			array(
				'original'  => get_permalink( $test_posts['post_id'] ),
				'post_meta' => array(
					'vk-ltc-link' => 'https://google.com',
				),
				'expected'  => 'https://google.com',
			),
		);
		$instance   = new VK_Link_Target_Controller();
		foreach ( $test_array as $key => $value ) {
			if ( isset( $value['post_meta']['vk-ltc-link'] ) ) {
				update_post_meta( $test_posts['post_id'], 'vk-ltc-link', $value['post_meta']['vk-ltc-link'] );
			} else {
				delete_post_meta( $test_posts['post_id'], 'vk-ltc-link' );
			}
			$actual = $instance->rewrite_link_filter( $value['original'], $post );

			print 'actual   :' . $actual . PHP_EOL;
			print 'expected :' . $value['expected'] . PHP_EOL;

			$this->assertEquals( $value['expected'], $actual );
		}

		wp_delete_post( $test_posts['post_id'], true );
	}

	/**
	 * Test that ajax_rewrite_ids() returns URLs with HTML entities decoded back to raw characters.
	 * ajax_rewrite_ids() が HTML エンティティを元の文字へ復元した URL を返すことをテストする。
	 *
	 * rewrite_link() is entity-encoded by esc_url() for HTML display ( & -> &#038;, ' -> &#039; ),
	 * but the 're' value returned as JSON is assigned directly to the href attribute by JavaScript,
	 * so it must be restored by html_entity_decode() with ENT_QUOTES.
	 * On PHP 8.0 or earlier the default flags ( ENT_COMPAT ) leave single quotes undecoded,
	 * so this test also works as a regression test for URLs containing single quotes.
	 * rewrite_link() は HTML 表示用に esc_url() でエンティティ化（ & -> &#038;、' -> &#039; ）されるが、
	 * JSONで返される 're' はJavaScriptがhref属性へ直接代入するため、
	 * html_entity_decode() に ENT_QUOTES を指定して復元されている必要がある。
	 * PHP 8.0 以前はデフォルトフラグ（ ENT_COMPAT ）でシングルクォートが復元されないため、
	 * このテストはシングルクォートを含む URL の回帰テストを兼ねる。
	 * Fires the wp_ajax_ids action for real to verify the actual JSON response.
	 * wp_ajax_ids アクションを実際に発火させて実レスポンスを確認する。
	 */
	function test_ajax_rewrite_ids() {

		$test_posts = self::create_test_posts();

		// Test cases: each URL must come back exactly as saved, with no HTML entities left.
		// テストケース: 各 URL が保存時のまま返り、HTML エンティティが残らないこと。
		$test_cases = array(
			array(
				'test_condition_name' => '複数クエリパラメータを含む URL の場合 => & がエンティティ化されずそのまま返る',
				'url'                 => 'https://example.com/page.php?foo=1&bar=2',
			),
			array(
				'test_condition_name' => 'シングルクォートを含む URL の場合 => \' がエンティティ化されずそのまま返る（PHP 8.0 以前の回帰テスト）',
				'url'                 => 'https://example.com/page.php?q=O\'Reilly',
			),
			array(
				'test_condition_name' => 'シングルクォートと & の両方を含む URL の場合 => どちらもエンティティ化されずそのまま返る',
				'url'                 => 'https://example.com/page.php?q=O\'Reilly&x=1',
			),
		);

		foreach ( $test_cases as $case ) {

			// Save the URL to redirect to. / リダイレクト先 URL を保存する。
			update_post_meta( $test_posts['post_id'], 'vk-ltc-link', $case['url'] );

			// Reset the response buffer because the Ajax die handler appends to it.
			// Ajax の die ハンドラはレスポンスを追記するため、実行前にバッファをリセットする。
			$this->_last_response = '';

			try {
				$this->_handleAjax( 'ids' );
			} catch ( WPAjaxDieContinueException $e ) {
				// ajax_rewrite_ids() echoes the JSON response then calls wp_die(); expected.
				// ajax_rewrite_ids() は JSON を出力後 wp_die() するので、この例外は想定どおり。
			}

			$response = json_decode( $this->_last_response, true );

			$this->assertIsArray( $response, $case['test_condition_name'] );
			$this->assertArrayHasKey( $test_posts['post_id'], $response, $case['test_condition_name'] );
			// The URL must be returned exactly as saved. / URL が保存時のまま返ること。
			$this->assertSame( $case['url'], $response[ $test_posts['post_id'] ]['re'], $case['test_condition_name'] );
			// No HTML entities may remain in the raw response. / 生レスポンスに HTML エンティティが残らないこと。
			$this->assertStringNotContainsString( '&#038;', $this->_last_response, $case['test_condition_name'] );
			$this->assertStringNotContainsString( '&#039;', $this->_last_response, $case['test_condition_name'] );

			// Clean up the post meta. / postmeta をクリーンアップする。
			delete_post_meta( $test_posts['post_id'], 'vk-ltc-link' );
		}

		wp_delete_post( $test_posts['post_id'], true );
	}
}

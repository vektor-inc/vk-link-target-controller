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
	 * Issue #176: 複数のクエリパラメータを含む外部URLが、Ajaxレスポンス経由で
	 * JavaScriptに渡された際に、HTMLエンティティ化された "&#038;" のままとならず
	 * 正しい "&" として復元されることを確認する。
	 *
	 * rewrite_link() 自体は the_permalink フィルタ（HTML表示用）でも使われるため
	 * "&#038;" にエンティティ化されたままでよいが、ajax_rewrite_ids() がJSONで
	 * JavaScriptへ返す 're' はhref属性へ直接設定されるため、html_entity_decode() で
	 * 復元されている必要がある。wp_ajax_ids アクションを実際に発火させて確認する。
	 */
	function test_ajax_rewrite_ids_keeps_ampersand_in_query_params() {

		$test_posts = self::create_test_posts();

		$external_url = 'https://example.com/page.php?foo=1&bar=2';
		update_post_meta( $test_posts['post_id'], 'vk-ltc-link', $external_url );

		try {
			$this->_handleAjax( 'ids' );
		} catch ( WPAjaxDieContinueException $e ) {
			// ajax_rewrite_ids() echoes the JSON response then calls wp_die(); expected.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( $test_posts['post_id'], $response );
		$this->assertSame( $external_url, $response[ $test_posts['post_id'] ]['re'] );
		$this->assertStringNotContainsString( '&#038;', $this->_last_response );

		wp_delete_post( $test_posts['post_id'], true );
	}
}

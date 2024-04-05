<?php
/**
 * Class rewriteTest
 *
 * @package Vk_Link_Target_Controller
 */

/**
 * Option test case.
 */
class rewriteTest extends WP_UnitTestCase {

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
}

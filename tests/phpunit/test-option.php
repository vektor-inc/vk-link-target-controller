<?php
/**
 * Class OptionTest
 *
 * @package Vk_Link_Target_Controller
 */

/**
 * Option test case.
 */
class OptionTest extends WP_UnitTestCase {

	/**
	 * Test that get_option() returns the correct post types based on saved option.
	 * get_option() が保存されたオプションに基づいて正しい投稿タイプを返すことをテストする。
	 */
	function test_get_option() {

		/**
		 * カスタム投稿タイプを設置
		 */
		register_post_type(
			'event',
			array(
				'has_archive' => true,
				'public'      => true,
			)
		);

		$test_array = array(
			array(
				'options'  => false,
				'correct' => array( 'post', 'event', 'page' ),
			),
			array(
				'options'  => array( 'post' ),
				'correct' => array( 'post' ),
			),
			array(
				'options'  => array( 'page' ),
				'correct' => array( 'page' ),
			),
			array(
				'options'  => array( 'event' ),
				'correct' => array( 'event' ),
			),
			array(
				'options'  => array( 'post', 'page' ),
				'correct' => array( 'post', 'page' ),
			),
			array(
				'options'  => array( 'post', 'event' ),
				'correct' => array( 'post', 'event' ),
			),
			array(
				'options'  => array( 'page', 'event' ),
				'correct' => array( 'page', 'event' ),
			),
			array(
				'options'  => array( 'post', 'page', 'event' ),
				'correct' => array( 'post', 'page', 'event' ),
			),
		);
		foreach ( $test_array as $key => $value ) {
			update_option( 'vk_ltc_custom_post_types', $value['options'] );
			$instanse = new VK_Link_Target_Controller();
			$result   = $instanse->get_option();
			$correct  = $value['correct'];
			$this->assertEquals( $correct, $result );

			// Clean up option for next iteration.
			// 次のイテレーションのためにオプションをクリーンアップする。
			delete_option( 'vk_ltc_custom_post_types' );
		}
	}

	/**
	 * Test that get_option() falls back to the legacy option key when the new key does not exist.
	 * get_option() が新キーが存在しない場合に旧キーにフォールバックすることをテストする。
	 *
	 * This covers the scenario where the plugin is updated but admin_init has not yet run
	 * (e.g., a frontend request immediately after update), so the migration has not occurred.
	 * プラグイン更新直後に admin_init がまだ実行されていない場合（例：更新直後のフロントエンドリクエスト）の
	 * シナリオをカバーする。
	 */
	function test_get_option_legacy_key_fallback() {

		// Register a custom post type for testing.
		// テスト用カスタム投稿タイプを登録する。
		register_post_type(
			'event',
			array(
				'has_archive' => true,
				'public'      => true,
			)
		);

		$old_key = 'custom-post-types';
		$new_key = 'vk_ltc_custom_post_types';

		$test_cases = array(
			array(
				'test_condition_name' => 'Only legacy key exists => should return legacy key value',
				'conditions'          => array(
					'old_key_value' => array( 'post', 'page' ),
					'new_key_value' => null,
				),
				'expected'            => array( 'post', 'page' ),
			),
			array(
				'test_condition_name' => 'Both keys exist => should return new key value (new key takes priority)',
				'conditions'          => array(
					'old_key_value' => array( 'post' ),
					'new_key_value' => array( 'post', 'page', 'event' ),
				),
				'expected'            => array( 'post', 'page', 'event' ),
			),
			array(
				'test_condition_name' => 'Neither key exists => should return all public post type slugs as default',
				'conditions'          => array(
					'old_key_value' => null,
					'new_key_value' => null,
				),
				'expected'            => array( 'post', 'event', 'page' ),
			),
			array(
				'test_condition_name' => 'Only new key exists => should return new key value',
				'conditions'          => array(
					'old_key_value' => null,
					'new_key_value' => array( 'event' ),
				),
				'expected'            => array( 'event' ),
			),
		);

		foreach ( $test_cases as $case ) {

			// Set up conditions.
			// 条件の設定。
			if ( null !== $case['conditions']['old_key_value'] ) {
				update_option( $old_key, $case['conditions']['old_key_value'] );
			}
			if ( null !== $case['conditions']['new_key_value'] ) {
				update_option( $new_key, $case['conditions']['new_key_value'] );
			}

			// Execute get_option() method.
			// get_option() メソッドを実行する。
			$instance = new VK_Link_Target_Controller();
			$result   = $instance->get_option();

			// Assert the returned value matches the expected value.
			// 返り値が期待値と一致することを検証する。
			$this->assertSame(
				$case['expected'],
				$result,
				$case['test_condition_name']
			);

			// Clean up for next iteration.
			// 次のイテレーションのためにクリーンアップする。
			delete_option( $old_key );
			delete_option( $new_key );
		}
	}

	/**
	 * Test that vk_ltc_migrate_option_key() migrates the old option key to the new one.
	 * vk_ltc_migrate_option_key() が旧オプションキーを新オプションキーに移行することをテストする。
	 */
	function test_vk_ltc_migrate_option_key() {

		$old_key = 'custom-post-types';
		$new_key = 'vk_ltc_custom_post_types';

		$test_cases = array(
			array(
				'test_condition_name' => '旧キーが存在し新キーが存在しない場合 => 旧キーの値が新キーに移行され、旧キーが削除される',
				'conditions'         => array(
					'old_key_value' => array( 'post', 'page' ),
					'new_key_value' => null,
				),
				'expected'           => array(
					'new_key_value'    => array( 'post', 'page' ),
					'old_key_exists'   => false,
				),
			),
			array(
				'test_condition_name' => '旧キーが存在し新キーも既に存在する場合 => 新キーの値は上書きされず、旧キーのみ削除される',
				'conditions'         => array(
					'old_key_value' => array( 'post' ),
					'new_key_value' => array( 'post', 'page', 'event' ),
				),
				'expected'           => array(
					'new_key_value'    => array( 'post', 'page', 'event' ),
					'old_key_exists'   => false,
				),
			),
			array(
				'test_condition_name' => '旧キーが存在しない場合 => 何も変更されない',
				'conditions'         => array(
					'old_key_value' => null,
					'new_key_value' => null,
				),
				'expected'           => array(
					'new_key_value'    => false,
					'old_key_exists'   => false,
				),
			),
			array(
				'test_condition_name' => '旧キーが存在せず新キーが既に存在する場合 => 新キーの値はそのまま保持される',
				'conditions'         => array(
					'old_key_value' => null,
					'new_key_value' => array( 'event' ),
				),
				'expected'           => array(
					'new_key_value'    => array( 'event' ),
					'old_key_exists'   => false,
				),
			),
		);

		foreach ( $test_cases as $case ) {

			// Set up conditions.
			// 条件の設定。
			if ( null !== $case['conditions']['old_key_value'] ) {
				update_option( $old_key, $case['conditions']['old_key_value'] );
			}
			if ( null !== $case['conditions']['new_key_value'] ) {
				update_option( $new_key, $case['conditions']['new_key_value'] );
			}

			// Execute migration function.
			// 移行関数を実行する。
			vk_ltc_migrate_option_key();

			// Assert new key value.
			// 新キーの値を検証する。
			$this->assertSame(
				$case['expected']['new_key_value'],
				get_option( $new_key, false ),
				$case['test_condition_name'] . ' (new key value)'
			);

			// Assert old key existence using a unique sentinel value.
			// ユニークなセンチネル値を使って旧キーの存在を検証する。
			$sentinel         = 'vk_ltc_test_sentinel_not_found';
			$old_key_result   = get_option( $old_key, $sentinel );
			$old_key_actually = ( $sentinel !== $old_key_result );
			$this->assertSame(
				$case['expected']['old_key_exists'],
				$old_key_actually,
				$case['test_condition_name'] . ' (old key exists)'
			);

			// Clean up for next iteration.
			// 次のイテレーションのためにクリーンアップする。
			delete_option( $old_key );
			delete_option( $new_key );
		}
	}
}
<?php
/**
 * Class registerMetaTest
 *
 * register_post_meta によるメタキーの REST API 登録と、
 * ブロックエディタ（REST 経由）保存時に save_link() が干渉しないことをテストする。
 *
 * @package Vk_Link_Target_Controller
 */

/**
 * Register meta test case.
 */
class registerMetaTest extends WP_UnitTestCase {

	/**
	 * Set up test environment.
	 * テスト環境のセットアップ。init フックが発火済みでない場合に備えて手動で登録する。
	 */
	public function set_up() {
		parent::set_up();
		// Ensure post meta is registered for tests.
		// テスト用にメタを確実に登録する。
		vk_ltc_register_post_meta();
	}

	/**
	 * Test that vk-ltc-link and vk-ltc-target are registered for enabled post types.
	 * 有効な投稿タイプでメタキーが登録されていることを確認する。
	 */
	public function test_vk_ltc_register_post_meta() {
		$test_cases = array(
			array(
				'test_condition_name' => 'post タイプで vk-ltc-link が登録されている場合 => true',
				'post_type'          => 'post',
				'meta_key'           => 'vk-ltc-link',
				'expected'           => true,
			),
			array(
				'test_condition_name' => 'post タイプで vk-ltc-target が登録されている場合 => true',
				'post_type'          => 'post',
				'meta_key'           => 'vk-ltc-target',
				'expected'           => true,
			),
			array(
				'test_condition_name' => 'page タイプで vk-ltc-link が登録されている場合 => true',
				'post_type'          => 'page',
				'meta_key'           => 'vk-ltc-link',
				'expected'           => true,
			),
		);

		foreach ( $test_cases as $case ) {
			$registered = registered_meta_key_exists( 'post', $case['meta_key'], $case['post_type'] );
			$this->assertEquals( $case['expected'], $registered, $case['test_condition_name'] );
		}
	}

	/**
	 * Test that vk-ltc-link has show_in_rest enabled.
	 * vk-ltc-link の show_in_rest が有効であることを確認する。
	 */
	public function test_vk_ltc_meta_show_in_rest() {
		$registered_keys = get_registered_meta_keys( 'post', 'post' );

		$test_cases = array(
			array(
				'test_condition_name' => 'vk-ltc-link の show_in_rest が true の場合 => true',
				'meta_key'           => 'vk-ltc-link',
				'expected'           => true,
			),
			array(
				'test_condition_name' => 'vk-ltc-target の show_in_rest が true の場合 => true',
				'meta_key'           => 'vk-ltc-target',
				'expected'           => true,
			),
		);

		foreach ( $test_cases as $case ) {
			$show_in_rest = isset( $registered_keys[ $case['meta_key'] ]['show_in_rest'] )
				? (bool) $registered_keys[ $case['meta_key'] ]['show_in_rest']
				: false;
			$this->assertEquals( $case['expected'], $show_in_rest, $case['test_condition_name'] );
		}
	}

	/**
	 * Test that save_link() does not interfere when $_POST fields are absent.
	 * $_POST にフィールドがない場合（REST 経由の保存）に save_link() が干渉しないことを確認する。
	 */
	public function test_save_link_no_interference_on_rest_save() {
		// save_link() は current_user_can('edit_posts') をチェックするため、
		// テスト用に editor ロールのユーザーを作成してログインする。
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// テスト用の投稿を作成
		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'REST Save Test',
				'post_status' => 'publish',
			)
		);

		// REST 経由でメタデータを設定（save_link は $_POST を使わない）
		update_post_meta( $post_id, 'vk-ltc-link', 'https://example.com/rest-test' );
		update_post_meta( $post_id, 'vk-ltc-target', '1' );

		// $_POST を空にして save_link() を呼ぶ（REST 経由の保存をシミュレート）
		$_POST = array();
		$vk_ltc = new VK_Link_Target_Controller();
		$result = $vk_ltc->save_link( $post_id );

		$test_cases = array(
			array(
				'test_condition_name' => '$_POST が空の場合、vk-ltc-link が変更されていない => https://example.com/rest-test',
				'actual'             => get_post_meta( $post_id, 'vk-ltc-link', true ),
				'expected'           => 'https://example.com/rest-test',
			),
			array(
				'test_condition_name' => '$_POST が空の場合、vk-ltc-target が変更されていない => 1',
				'actual'             => get_post_meta( $post_id, 'vk-ltc-target', true ),
				'expected'           => '1',
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertEquals( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}

		// クリーンアップ
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that the init hook for vk_ltc_register_post_meta uses a late priority.
	 * カスタム投稿タイプ（CPT UI / ExUnit 等）の `init` 登録より後に
	 * register_post_meta() が実行されるよう、十分遅い優先度で init にフックされていることを確認する。
	 */
	public function test_vk_ltc_register_post_meta_init_priority() {
		// init フックで vk_ltc_register_post_meta が登録されている優先度を取得する。
		$priority = has_action( 'init', 'vk_ltc_register_post_meta' );

		$test_cases = array(
			array(
				'test_condition_name' => 'init への登録が存在する場合 => false 以外',
				'actual'             => ( false !== $priority ),
				'expected'           => true,
			),
			array(
				'test_condition_name' => 'デフォルト(10)より遅い優先度で登録されている場合 => true',
				'actual'             => ( is_int( $priority ) && $priority > 10 ),
				'expected'           => true,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertEquals( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}

	/**
	 * Test that register_post_meta is skipped for unregistered post types.
	 * 未登録の投稿タイプに対しては register_post_meta が呼ばれない（スキップされる）ことを確認する。
	 */
	public function test_vk_ltc_register_post_meta_skips_unregistered_post_type() {
		// 存在しない投稿タイプを有効化した状態をシミュレートする。
		$unregistered_slug = 'vk_ltc_nonexistent_cpt';
		update_option( 'vk_ltc_custom_post_types', array( 'post', $unregistered_slug ) );

		// 途中のアサーション失敗でもクリーンアップが実行されるよう try/finally で保証する。
		try {
			// 事前条件: 対象の post_type が未登録であることを確認する。
			$this->assertFalse( post_type_exists( $unregistered_slug ), '未登録投稿タイプであることの前提確認' );

			// 登録関数を実行する（例外や警告が出ないこと）。
			vk_ltc_register_post_meta();

			// 未登録の投稿タイプには meta が登録されていないはずである。
			$registered = registered_meta_key_exists( 'post', 'vk-ltc-link', $unregistered_slug );
			$this->assertFalse( $registered, '未登録の投稿タイプに対しては vk-ltc-link が登録されていない' );

			// 登録済みの投稿タイプに対しては従来通り登録されていること。
			$this->assertTrue(
				registered_meta_key_exists( 'post', 'vk-ltc-link', 'post' ),
				'登録済みの post タイプには vk-ltc-link が登録されている'
			);
		} finally {
			// クリーンアップ。
			delete_option( 'vk_ltc_custom_post_types' );
		}
	}

	/**
	 * Test that vk-ltc-link sanitize_callback works correctly.
	 * vk-ltc-link の sanitize_callback が正しく動作することを確認する。
	 */
	public function test_vk_ltc_link_sanitize() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Sanitize Test',
				'post_status' => 'publish',
			)
		);

		$test_cases = array(
			array(
				'test_condition_name' => '正常な URL を保存した場合 => そのまま保存される',
				'input'              => 'https://example.com/test',
				'expected'           => 'https://example.com/test',
			),
			array(
				'test_condition_name' => 'javascript: スキームの URL を保存した場合 => 空文字になる',
				'input'              => 'javascript:alert(1)',
				'expected'           => '',
			),
			array(
				'test_condition_name' => '空文字を保存した場合 => 空文字のまま',
				'input'              => '',
				'expected'           => '',
			),
		);

		foreach ( $test_cases as $case ) {
			update_post_meta( $post_id, 'vk-ltc-link', $case['input'] );
			$actual = get_post_meta( $post_id, 'vk-ltc-link', true );
			$this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );
		}

		// クリーンアップ
		wp_delete_post( $post_id, true );
	}
}

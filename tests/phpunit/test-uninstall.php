<?php
/**
 * Test uninstall cleanup functionality.
 * アンインストール時のDB掃除機能のテスト。
 *
 * @package vk-link-target-controller
 */

/**
 * Uninstall test case.
 * アンインストールのテストケース。
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Test that uninstall.php removes post meta and options from the database.
	 * uninstall.php が投稿メタとオプションをデータベースから削除することをテストする。
	 */
	function test_uninstall() {

		// Test data setup: create posts and set plugin-specific post meta.
		// テストデータの準備：投稿を作成し、プラグイン固有の投稿メタを設定する。
		$post_id_1 = $this->factory->post->create();
		$post_id_2 = $this->factory->post->create();

		// テストケースの配列
		// Test cases array.
		$test_cases = array(
			array(
				'test_condition_name' => '投稿メタとオプションが設定されている状態でアンインストールを実行した場合 => 全て削除される',
				'conditions'         => array(
					'post_meta' => array(
						$post_id_1 => array(
							'vk-ltc-link'   => 'https://example.com/',
							'vk-ltc-target' => '1',
						),
						$post_id_2 => array(
							'vk-ltc-link'   => 'https://example.org/page',
							'vk-ltc-target' => '0',
						),
					),
					'options'   => array(
						'vk_ltc_custom_post_types' => array( 'post', 'page' ),
						'custom-post-types'        => array( 'post' ),
					),
				),
				'expected'           => array(
					'post_meta_link_1'   => '',
					'post_meta_target_1' => '',
					'post_meta_link_2'   => '',
					'post_meta_target_2' => '',
					'option_new'         => false,
					'option_legacy'      => false,
				),
			),
			array(
				'test_condition_name' => '片方の投稿にのみメタが設定されている状態でアンインストールを実行した場合 => 全て削除される',
				'conditions'         => array(
					'post_meta' => array(
						$post_id_1 => array(
							'vk-ltc-link'   => 'https://example.com/single',
							'vk-ltc-target' => '1',
						),
					),
					'options'   => array(
						'vk_ltc_custom_post_types' => array( 'post' ),
					),
				),
				'expected'           => array(
					'post_meta_link_1'   => '',
					'post_meta_target_1' => '',
					'post_meta_link_2'   => '',
					'post_meta_target_2' => '',
					'option_new'         => false,
					'option_legacy'      => false,
				),
			),
			array(
				'test_condition_name' => 'メタもオプションも存在しない状態でアンインストールを実行した場合 => エラーなく完了する',
				'conditions'         => array(
					'post_meta' => array(),
					'options'   => array(),
				),
				'expected'           => array(
					'post_meta_link_1'   => '',
					'post_meta_target_1' => '',
					'post_meta_link_2'   => '',
					'post_meta_target_2' => '',
					'option_new'         => false,
					'option_legacy'      => false,
				),
			),
			array(
				'test_condition_name' => 'レガシーオプション custom-post-types が投稿タイプ配列ではない値（他プラグインが同名で使っている可能性がある値）の場合 => レガシーオプションは削除されず残り、新オプションのみ削除される',
				'conditions'         => array(
					'post_meta' => array(),
					'options'   => array(
						'vk_ltc_custom_post_types' => array( 'post' ),
						'custom-post-types'        => 'not-an-array-value',
					),
				),
				'expected'           => array(
					'post_meta_link_1'   => '',
					'post_meta_target_1' => '',
					'post_meta_link_2'   => '',
					'post_meta_target_2' => '',
					'option_new'         => false,
					'option_legacy'      => 'not-an-array-value',
				),
			),
		);

		foreach ( $test_cases as $case ) {

			// Set up conditions: post meta.
			// 条件の設定：投稿メタ。
			if ( ! empty( $case['conditions']['post_meta'] ) ) {
				foreach ( $case['conditions']['post_meta'] as $pid => $metas ) {
					foreach ( $metas as $meta_key => $meta_value ) {
						update_post_meta( $pid, $meta_key, $meta_value );
					}
				}
			}

			// Set up conditions: options.
			// 条件の設定：オプション。
			if ( ! empty( $case['conditions']['options'] ) ) {
				foreach ( $case['conditions']['options'] as $option_name => $option_value ) {
					update_option( $option_name, $option_value );
				}
			}

			// Define WP_UNINSTALL_PLUGIN if not already defined, then include uninstall.php.
			// WP_UNINSTALL_PLUGIN が未定義の場合は定義し、uninstall.php を読み込む。
			if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
				define( 'WP_UNINSTALL_PLUGIN', true );
			}
			require dirname( dirname( __DIR__ ) ) . '/uninstall.php';

			// Assert post meta is deleted using both get_post_meta and metadata_exists.
			// get_post_meta と metadata_exists の両方を使って投稿メタが削除されていることを確認する。
			$this->assertSame(
				$case['expected']['post_meta_link_1'],
				get_post_meta( $post_id_1, 'vk-ltc-link', true ),
				$case['test_condition_name'] . ' (vk-ltc-link post 1)'
			);
			$this->assertFalse(
				metadata_exists( 'post', $post_id_1, 'vk-ltc-link' ),
				$case['test_condition_name'] . ' (vk-ltc-link post 1 should be deleted)'
			);
			$this->assertSame(
				$case['expected']['post_meta_target_1'],
				get_post_meta( $post_id_1, 'vk-ltc-target', true ),
				$case['test_condition_name'] . ' (vk-ltc-target post 1)'
			);
			$this->assertFalse(
				metadata_exists( 'post', $post_id_1, 'vk-ltc-target' ),
				$case['test_condition_name'] . ' (vk-ltc-target post 1 should be deleted)'
			);
			$this->assertSame(
				$case['expected']['post_meta_link_2'],
				get_post_meta( $post_id_2, 'vk-ltc-link', true ),
				$case['test_condition_name'] . ' (vk-ltc-link post 2)'
			);
			$this->assertFalse(
				metadata_exists( 'post', $post_id_2, 'vk-ltc-link' ),
				$case['test_condition_name'] . ' (vk-ltc-link post 2 should be deleted)'
			);
			$this->assertSame(
				$case['expected']['post_meta_target_2'],
				get_post_meta( $post_id_2, 'vk-ltc-target', true ),
				$case['test_condition_name'] . ' (vk-ltc-target post 2)'
			);
			$this->assertFalse(
				metadata_exists( 'post', $post_id_2, 'vk-ltc-target' ),
				$case['test_condition_name'] . ' (vk-ltc-target post 2 should be deleted)'
			);

			// Assert the new (prefixed) option key is always deleted, and the
			// legacy option key is deleted only when its stored value looked
			// like something this plugin would have saved (an array of post
			// type slugs). A non-array legacy value must be left untouched,
			// since it may belong to another plugin using the same option name.
			// 新しい（プレフィックス付き）オプションキーは常に削除されることを
			// 確認する。レガシーオプションキーは、保存値がこのプラグインの
			// 保存形式（投稿タイプスラッグの配列）に見える場合のみ削除される
			// ことを確認する。配列でないレガシー値は、他プラグインが同名の
			// オプションを使っている可能性があるため、変更されずに残らなければ
			// ならない。
			$this->assertSame(
				$case['expected']['option_new'],
				get_option( 'vk_ltc_custom_post_types', false ),
				$case['test_condition_name'] . ' (vk_ltc_custom_post_types option)'
			);
			$this->assertSame(
				$case['expected']['option_legacy'],
				get_option( 'custom-post-types', false ),
				$case['test_condition_name'] . ' (legacy custom-post-types option)'
			);

			// Clean up for next iteration: delete any remaining meta and options.
			// 次のイテレーションのためにクリーンアップ：残っているメタとオプションを削除する。
			delete_post_meta( $post_id_1, 'vk-ltc-link' );
			delete_post_meta( $post_id_1, 'vk-ltc-target' );
			delete_post_meta( $post_id_2, 'vk-ltc-link' );
			delete_post_meta( $post_id_2, 'vk-ltc-target' );
			delete_option( 'vk_ltc_custom_post_types' );
			delete_option( 'custom-post-types' );
		}
	}
}

<?php
/**
 * Plugin Name: Register Test CPT for e2e
 * Description: e2e テスト用のカスタム投稿タイプ "product" を登録する。
 *              `init` フックの優先度 11 で登録することで、VK-LTC のデフォルト優先度 10 より
 *              後に CPT が登録されるケース（修正前バグの発生条件）を再現する。
 *
 * @package vk-link-target-controller
 */

// テスト用カスタム投稿タイプ "product" を init 優先度 11 で登録する。
// 実運用の CPT UI / ExUnit 等でも VK-LTC（優先度 10）より後に CPT が登録されるケースが発生する。
// 修正前は `vk_ltc_register_post_meta` が CPT 登録より先に走るためメタが未登録となるバグが発生する。
// 修正後 (priority 99) は必ず CPT 登録後に呼ばれることで本テストが PASS するようになる。
add_action(
	'init',
	function () {
		register_post_type(
			'product',
			array(
				'labels'       => array(
					'name'          => 'Products',
					'singular_name' => 'Product',
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-cart',
			)
		);
	},
	11
);

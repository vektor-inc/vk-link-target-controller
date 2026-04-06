<?php
/**
 * Register post meta for REST API access.
 * ブロックエディタからREST API経由でメタデータを読み書きするために登録する。
 *
 * @package vk-link-target-controller
 */

/**
 * Register vk-ltc-link and vk-ltc-target meta keys for the REST API.
 * vk-ltc-link と vk-ltc-target メタキーをREST APIに登録する。
 *
 * @return void
 */
function vk_ltc_register_post_meta() {
	$vk_ltc = new VK_Link_Target_Controller();
	$post_types = $vk_ltc->get_option();

	if ( empty( $post_types ) || ! is_array( $post_types ) ) {
		return;
	}

	foreach ( $post_types as $post_type ) {
		register_post_meta(
			$post_type,
			'vk-ltc-link',
			array(
				'type'              => 'string',
				'description'       => 'Redirect URL / リダイレクト先URL',
				'single'            => true,
				'sanitize_callback' => 'esc_url_raw',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			$post_type,
			'vk-ltc-target',
			array(
				'type'              => 'string',
				'description'       => 'Open in new window flag / 別ウィンドウで開くフラグ',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'vk_ltc_register_post_meta' );

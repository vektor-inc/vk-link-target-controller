<?php
/**
 * Tests for explicit uninstall consent.
 * アンインストール時の明示的な削除許可のテスト。
 *
 * @package vk-link-target-controller
 */

/**
 * Verify site-local consent and the settings form.
 * サイト単位の許可と設定フォームを検証する。
 */
class UninstallPreferenceTest extends WP_UnitTestCase {

	/**
	 * Verify that only explicit ON values survive sanitization.
	 * 明示的な ON の値だけが許可されることを検証する。
	 *
	 * @return void
	 */
	public function test_sanitize_delete_data_on_uninstall() {
		$controller = new VK_Link_Target_Controller();
		$test_cases = array(
			array(
				'test_condition_name' => 'フォームの ON => 1',
				'input'               => '1',
				'expected'            => 1,
			),
			array(
				'test_condition_name' => '整数の ON => 1',
				'input'               => 1,
				'expected'            => 1,
			),
			array(
				'test_condition_name' => '真偽値の ON => 1',
				'input'               => true,
				'expected'            => 1,
			),
			array(
				'test_condition_name' => 'フォームの OFF => 0',
				'input'               => '0',
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '整数の OFF => 0',
				'input'               => 0,
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '真偽値の OFF => 0',
				'input'               => false,
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '未送信 => 0',
				'input'               => null,
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '空文字 => 0',
				'input'               => '',
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '任意の文字列 => 0',
				'input'               => 'yes',
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '配列 => 0',
				'input'               => array( '1' ),
				'expected'            => 0,
			),
		);
		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $controller->sanitize_delete_data_on_uninstall( $case['input'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * Check Settings API registration, saving, and checkbox state.
	 * Settings API 登録・保存とチェックボックスの状態を検証する。
	 *
	 * @return void
	 */
	public function test_create_settings_page() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$original_user_id = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		try {
			$controller = new VK_Link_Target_Controller();
			$controller->create_settings_page();
			$registered = get_registered_settings();
			$this->assertSame( 'vk-ltc-options', $registered['vk_ltc_delete_data_on_uninstall']['group'] );
			$this->assertSame( 0, $registered['vk_ltc_delete_data_on_uninstall']['default'] );

			// Exercise missing, enabled, and disabled states through the registered sanitizer.
			// 登録されたサニタイズ処理を通して未保存・ON・OFF を検証する.
			$test_cases = array(
				array(
					'test_condition_name' => '未保存 => 未チェック',
					'input'               => null,
					'checked'             => false,
				),
				array(
					'test_condition_name' => 'ON を保存 => チェック',
					'input'               => '1',
					'checked'             => true,
				),
				array(
					'test_condition_name' => 'OFF を保存 => 未チェック',
					'input'               => '0',
					'checked'             => false,
				),
			);
			foreach ( $test_cases as $case ) {
				if ( null === $case['input'] ) {
					delete_option( 'vk_ltc_delete_data_on_uninstall' );
					$this->assertSame( 0, get_option( 'vk_ltc_delete_data_on_uninstall' ) );
				} else {
					update_option( 'vk_ltc_delete_data_on_uninstall', $case['input'] );
					$this->assertEquals( (int) $case['checked'], get_option( 'vk_ltc_delete_data_on_uninstall' ), $case['test_condition_name'] );
				}
				ob_start();
				try {
					$controller->settings_page_html();
					$html = ob_get_contents();
				} finally {
					ob_end_clean();
				}
				$this->assertSame( 1, preg_match( '/<input type="checkbox"[^>]+id="vk_ltc_delete_data_on_uninstall"[^>]*>/', $html, $matches ) );
				$this->assertSame( (int) $case['checked'], preg_match( '/checked="checked"/', $matches[0] ), $case['test_condition_name'] );
				$this->assertStringContainsString( 'aria-describedby="vk-ltc-uninstall-description"', $matches[0] );
				$this->assertStringContainsString( 'name="vk_ltc_delete_data_on_uninstall" value="0"', $html );
				$document = new DOMDocument();
				$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
				$xpath = new DOMXPath( $document );
				$this->assertSame( 'Data on plugin deletion', trim( $xpath->query( '//th[@scope="row"][following-sibling::td//input[@id="vk_ltc_delete_data_on_uninstall"]]' )->item( 0 )->textContent ) );
				$label = $xpath->query( '//label[@for="vk_ltc_delete_data_on_uninstall"]' );
				$this->assertSame( 1, $label->length );
				$this->assertSame( 'Delete data when the plugin is deleted', trim( $label->item( 0 )->textContent ) );
				$this->assertSame( 1, $xpath->query( './input[@type="checkbox" and @id="vk_ltc_delete_data_on_uninstall"]', $label->item( 0 ) )->length );
				// The hidden fallback must precede the checkbox so checked submissions win.
				// チェック時の値を優先するため、hidden はチェックボックスより前に置く。
				$this->assertSame( 1, $xpath->query( 'preceding-sibling::*[1][self::input[@type="hidden" and @name="vk_ltc_delete_data_on_uninstall" and @value="0"]]', $label->item( 0 ) )->length );
				$this->assertSame( 0, $xpath->query( './/input[@type="hidden"]', $label->item( 0 ) )->length );
				$descriptions = $xpath->query( '../p[@class="description"]', $label->item( 0 ) );
				$this->assertSame( 2, $descriptions->length );
				$this->assertSame( 'vk-ltc-uninstall-description', $descriptions->item( 0 )->getAttribute( 'id' ) );
				$this->assertSame( 'Leave this unchecked to keep your data (default). To delete your data, check this option, save the changes, and then delete the plugin.', $xpath->evaluate( 'normalize-space(.)', $descriptions->item( 0 ) ) );
				$this->assertSame( 'vk-ltc-uninstall-warning', $descriptions->item( 1 )->getAttribute( 'id' ) );
				$warning = $xpath->query( './strong', $descriptions->item( 1 ) );
				$this->assertSame( 1, $warning->length );
				$this->assertSame( "Warning: The link target URLs and open-in-a-new-window settings for posts on this site, and this plugin's settings, will be permanently deleted and cannot be restored. Posts themselves and their content are not deleted. This setting applies only to this site, including on multisite networks.", $xpath->evaluate( 'normalize-space(.)', $warning->item( 0 ) ) );
				$this->assertSame( $xpath->evaluate( 'normalize-space(.)', $warning->item( 0 ) ), $xpath->evaluate( 'normalize-space(.)', $descriptions->item( 1 ) ) );
			}
		} finally {
			unregister_setting( 'vk-ltc-options', 'vk_ltc_delete_data_on_uninstall' );
			unregister_setting( 'vk-ltc-options', 'vk_ltc_custom_post_types' );
			wp_set_current_user( $original_user_id );
		}
	}

	/**
	 * Verify ON/OFF/missing on one site and mixed preferences across sites.
	 * 単一サイトの ON/OFF/未保存と複数サイトの設定混在を検証する。
	 *
	 * @return void
	 */
	public function test_uninstall() {
		$test_cases = array(
			array(
				'test_condition_name' => 'ON => 削除',
				'preference'          => 1,
				'deleted'             => true,
			),
			array(
				'test_condition_name' => 'OFF => 全て保持',
				'preference'          => 0,
				'deleted'             => false,
			),
			array(
				'test_condition_name' => '未保存 => 全て保持',
				'preference'          => null,
				'deleted'             => false,
			),
		);
		$origin     = get_current_blog_id();
		$sites      = array();
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		// On multisite each fixture gets its own site; single site runs fixtures sequentially.
		// マルチサイトでは別サイトに配置し、シングルサイトでは順番に検証する.
		foreach ( $test_cases as $index => $case ) {
			$site_id = is_multisite() && 0 < $index ? self::factory()->blog->create() : $origin;
			if ( is_multisite() ) {
				switch_to_blog( $site_id );
			}
			$post_id = self::factory()->post->create( array( 'post_content' => 'Content to preserve.' ) );
			update_post_meta( $post_id, 'vk-ltc-link', 'https://example.com/keep' );
			update_post_meta( $post_id, 'vk-ltc-target', '1' );
			update_post_meta( $post_id, 'unrelated-meta', 'keep' );
			update_option( 'vk_ltc_custom_post_types', array( 'post' ) );
			update_option( 'custom-post-types', array( 'page' ) );
			update_option( 'unrelated-option', 'keep' );
			delete_option( 'vk_ltc_delete_data_on_uninstall' );
			if ( null !== $case['preference'] ) {
				update_option( 'vk_ltc_delete_data_on_uninstall', $case['preference'] );
			}
			if ( is_multisite() ) {
				restore_current_blog();
			}
			$sites[] = array(
				'site_id' => $site_id,
				'post_id' => $post_id,
				'case'    => $case,
			);
			if ( ! is_multisite() ) {
				require dirname( __DIR__, 2 ) . '/uninstall.php';
				$this->assert_preference_result( $site_id, $post_id, $case );
			}
		}
		if ( is_multisite() ) {
			// Start from an OFF site to catch accidental use of the initiating site's option.
			// OFF のサイトから実行し、起点サイトの設定を全体に適用する不具合を検知する.
			switch_to_blog( $sites[1]['site_id'] );
			try {
				require dirname( __DIR__, 2 ) . '/uninstall.php';
				$this->assertSame( $sites[1]['site_id'], get_current_blog_id() );
			} finally {
				restore_current_blog();
			}
			foreach ( $sites as $site ) {
				$this->assert_preference_result( $site['site_id'], $site['post_id'], $site['case'] );
			}
		}
		$this->assertSame( $origin, get_current_blog_id() );
	}

	/**
	 * Assert deletion boundaries for one fixture.
	 * 各ケースで削除対象と保持対象を確認する。
	 *
	 * @param int   $site_id Site ID.
	 * @param int   $post_id Post ID.
	 * @param array $scenario Preference and expected result.
	 * @return void
	 */
	private function assert_preference_result( $site_id, $post_id, $scenario ) {
		if ( is_multisite() ) {
			switch_to_blog( $site_id );
		}
		try {
			$message = $scenario['test_condition_name'];
			$this->assertNotNull( get_post( $post_id ), $message );
			$this->assertSame( 'Content to preserve.', get_post_field( 'post_content', $post_id ), $message );
			$this->assertSame( ! $scenario['deleted'], metadata_exists( 'post', $post_id, 'vk-ltc-link' ), $message );
			$this->assertSame( $scenario['deleted'] ? '' : 'https://example.com/keep', get_post_meta( $post_id, 'vk-ltc-link', true ), $message );
			$this->assertSame( $scenario['deleted'] ? '' : '1', get_post_meta( $post_id, 'vk-ltc-target', true ), $message );
			$this->assertSame( $scenario['deleted'] ? null : array( 'post' ), get_option( 'vk_ltc_custom_post_types', null ), $message );
			$this->assertSame( $scenario['deleted'] ? null : array( 'page' ), get_option( 'custom-post-types', null ), $message );
			$preference = get_option( 'vk_ltc_delete_data_on_uninstall', null );
			if ( null === $scenario['preference'] || $scenario['deleted'] ) {
				$this->assertNull( $preference, $message );
			} else {
				$this->assertNotNull( $preference, $message );
				$this->assertSame( 0, (int) $preference, $message );
			}
			$this->assertSame( 'keep', get_post_meta( $post_id, 'unrelated-meta', true ), $message );
			$this->assertSame( 'keep', get_option( 'unrelated-option' ), $message );
		} finally {
			if ( is_multisite() ) {
				restore_current_blog();
			}
		}
	}
}

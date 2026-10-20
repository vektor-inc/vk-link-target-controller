<?php
/**
 * Class FrontScriptTest
 *
 * @package Vk_Link_Target_Controller
 */

/**
 * Front script and Ajax handler registration test case.
 * フロント用スクリプトと Ajax 受け口の登録のテストケース。
 */
class FrontScriptTest extends WP_UnitTestCase {

	/**
	 * Script queue saved in set_up() and restored in tear_down().
	 * WP_UnitTestCase does not restore $wp_scripts, so this test class saves and restores it itself.
	 * set_up() で退避し tear_down() で復元するスクリプトキュー。
	 * WP_UnitTestCase は $wp_scripts を復元しないため、このテストクラスで退避・復元する。
	 *
	 * @var WP_Scripts|null
	 */
	private $original_wp_scripts;

	/**
	 * Save the global script queue and start every test from an empty one.
	 * グローバルのスクリプトキューを退避し、空のキューからテストを始める。
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->original_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		$GLOBALS['wp_scripts']     = new WP_Scripts();
	}

	/**
	 * Restore the global script queue saved in set_up().
	 * set_up() で退避したグローバルのスクリプトキューを復元する。
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null === $this->original_wp_scripts ) {
			unset( $GLOBALS['wp_scripts'] );
		} else {
			$GLOBALS['wp_scripts'] = $this->original_wp_scripts;
		}
		parent::tear_down();
	}

	/**
	 * Get the plugin instance that registered its callbacks on WordPress hooks.
	 * The plugin file is loaded inside a function by bootstrap.php, so the instance is not a global variable and is found through the 'wp' hook instead.
	 * プラグインがフックにコールバックを登録したインスタンスを返す。
	 * プラグインファイルは bootstrap.php の関数内で読み込まれ、インスタンスがグローバル変数にならないため、'wp' フックから探す。
	 *
	 * @return VK_Link_Target_Controller|null The registered instance, or null when not found. / 登録済みのインスタンス。見つからない場合は null。
	 */
	private function get_registered_instance() {
		global $wp_filter;
		foreach ( $wp_filter['wp']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof VK_Link_Target_Controller ) {
					return $callback['function'][0];
				}
			}
		}
		return null;
	}

	/**
	 * Run only the callbacks of this plugin that are registered on the given hook.
	 * do_action() would also re-run every other plugin and theme callback on 'init' (post type registration and so on), so only this plugin's callbacks are run, in the order WordPress would run them.
	 * 指定したフックに登録されたこのプラグインのコールバックだけを実行する。
	 * do_action() では他のプラグインやテーマの 'init' コールバック（投稿タイプ登録など）も再実行されるため、このプラグインのコールバックだけを、WordPress が実行する順序で実行する。
	 *
	 * @param string $hook_name Hook name. / フック名.
	 * @return void
	 */
	private function run_plugin_callbacks( $hook_name ) {
		global $wp_filter;
		$instance = $this->get_registered_instance();
		if ( ! isset( $wp_filter[ $hook_name ] ) ) {
			return;
		}
		foreach ( $wp_filter[ $hook_name ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $instance === $callback['function'][0] ) {
					call_user_func( $callback['function'] );
				}
			}
		}
	}

	/**
	 * Test that initialize_front_script() outputs the front script on the public site only, never on admin screens.
	 * initialize_front_script() が公開サイトでだけフロント用スクリプトを出力し、管理画面では出力しないことをテストする。
	 *
	 * The front script rewrites links inside any element that has a post-{ID} class. WordPress core gives that class to each row of the admin posts list, so the script on admin screens rewrites the "View" row action link.
	 * フロント用スクリプトは post-{ID} クラスを持つ要素内のリンクを書き換える。WordPress 本体は管理画面の投稿一覧の各行にこのクラスを付けるため、管理画面に出力されると行アクションの「表示」リンクが書き換わる。
	 * Each case runs the hooks WordPress fires for that kind of request, limited to this plugin's callbacks, then checks the footer scripts that would be printed.
	 * 各ケースで、そのリクエストの種類で WordPress が実行するフックを、このプラグインのコールバックに限って実行し、出力されるフッタースクリプトを確認する。
	 *
	 * @return void
	 */
	public function test_initialize_front_script() {

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		$printed_expected     = array(
			'enqueued'  => true,
			'printed'   => true,
			'localized' => true,
		);
		$not_printed_expected = array(
			'enqueued'  => false,
			'printed'   => false,
			'localized' => false,
		);

		$test_cases = array(
			array(
				'test_condition_name' => '公開サイトのトップページで init と wp_enqueue_scripts が実行される場合 => vk-ltc-js が出力され vkLtc に ajaxurl と editLabel が渡る',
				'url'                 => home_url( '/' ),
				'screen'              => null,
				'hooks'               => array( 'init', 'wp_enqueue_scripts' ),
				'expected'            => $printed_expected,
			),
			array(
				'test_condition_name' => '公開サイトの投稿ページで init と wp_enqueue_scripts が実行される場合 => vk-ltc-js が出力され vkLtc に ajaxurl と editLabel が渡る',
				'url'                 => get_permalink( $post_id ),
				'screen'              => null,
				'hooks'               => array( 'init', 'wp_enqueue_scripts' ),
				'expected'            => $printed_expected,
			),
			array(
				'test_condition_name' => '管理画面の投稿一覧（edit-post）で init と admin_enqueue_scripts が実行される場合 => vk-ltc-js は出力されない',
				'url'                 => null,
				'screen'              => 'edit-post',
				'hooks'               => array( 'init', 'admin_enqueue_scripts' ),
				'expected'            => $not_printed_expected,
			),
			array(
				'test_condition_name' => '管理画面のダッシュボード（dashboard）で init と admin_enqueue_scripts が実行される場合 => vk-ltc-js は出力されない',
				'url'                 => null,
				'screen'              => 'dashboard',
				'hooks'               => array( 'init', 'admin_enqueue_scripts' ),
				'expected'            => $not_printed_expected,
			),
			array(
				'test_condition_name' => '管理画面の投稿編集画面（post）で init と admin_enqueue_scripts が実行される場合 => vk-ltc-js は出力されない',
				'url'                 => null,
				'screen'              => 'post',
				'hooks'               => array( 'init', 'admin_enqueue_scripts' ),
				'expected'            => $not_printed_expected,
			),
		);

		foreach ( $test_cases as $case ) {
			// Start every case from an empty script queue and the public site context. / ケースごとに空のスクリプトキューと公開サイトの状態から始める.
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			unset( $GLOBALS['current_screen'] );

			// Move to the public page or the admin screen. / 公開ページまたは管理画面へ移動する.
			if ( $case['screen'] ) {
				set_current_screen( $case['screen'] );
			} else {
				$this->go_to( $case['url'] );
			}

			// Run the hooks WordPress fires for this kind of request. / このリクエストの種類で WordPress が実行するフックを実行する.
			foreach ( $case['hooks'] as $hook_name ) {
				$this->run_plugin_callbacks( $hook_name );
			}

			// Print the footer scripts as the page footer does. / ページのフッターと同様にフッタースクリプトを出力する.
			ob_start();
			wp_print_footer_scripts();
			$footer_html = ob_get_clean();

			// Read vkLtc from the printed inline script and check that it carries the Ajax URL and the edit label. / 出力されたインラインスクリプトから vkLtc を読み取り、Ajax の URL と編集リンクのラベルが渡っているか確認する.
			$localized = false;
			if ( preg_match( '/var vkLtc = (\{.*?\});/s', $footer_html, $matches ) ) {
				$vk_ltc    = json_decode( $matches[1], true );
				$localized = is_array( $vk_ltc )
					&& isset( $vk_ltc['ajaxurl'], $vk_ltc['editLabel'] )
					&& admin_url( 'admin-ajax.php' ) === $vk_ltc['ajaxurl']
					&& __( 'Edit', 'vk-link-target-controller' ) === $vk_ltc['editLabel'];
			}

			$actual = array(
				'enqueued'  => wp_script_is( 'vk-ltc-js', 'enqueued' ),
				'printed'   => false !== strpos( $footer_html, 'vk-ltc-js-js' ),
				'localized' => $localized,
			);

			$this->assertSame( $case['expected'], $actual, $case['test_condition_name'] );
		}
	}

	/**
	 * Test that register_ajax_ids_actions() registers the Ajax handlers for both logged-in and logged-out visitors when init runs.
	 * register_ajax_ids_actions() が、init の実行時に、ログイン中・未ログイン両方の訪問者向けの Ajax 受け口を登録することをテストする。
	 *
	 * The front script is no longer loaded on admin screens, but the Ajax handlers must still exist on every request, including admin-ajax.php requests, which WordPress treats as admin requests.
	 * フロント用スクリプトは管理画面で読み込まなくなったが、Ajax 受け口は、WordPress が管理画面扱いにする admin-ajax.php へのリクエストを含め、すべてのリクエストに必要。
	 * The JSON response of the registered handler itself is covered by rewriteTest::test_ajax_rewrite_ids().
	 * 登録された受け口の JSON レスポンスそのものは rewriteTest::test_ajax_rewrite_ids() で確認している。
	 *
	 * @return void
	 */
	public function test_register_ajax_ids_actions() {

		$instance = $this->get_registered_instance();
		$this->assertInstanceOf( VK_Link_Target_Controller::class, $instance, 'プラグインのインスタンスがフックに登録されている' );

		$test_cases = array(
			array(
				'test_condition_name' => '公開サイトで init が実行される場合 => wp_ajax_ids と wp_ajax_nopriv_ids の両方が登録される',
				'screen'              => null,
				'expected'            => array(
					'wp_ajax_ids'        => true,
					'wp_ajax_nopriv_ids' => true,
				),
			),
			array(
				'test_condition_name' => '管理画面（admin-ajax.php のリクエスト）で init が実行される場合 => wp_ajax_ids と wp_ajax_nopriv_ids の両方が登録される',
				'screen'              => 'edit-post',
				'expected'            => array(
					'wp_ajax_ids'        => true,
					'wp_ajax_nopriv_ids' => true,
				),
			),
		);

		foreach ( $test_cases as $case ) {
			// Remove the handlers registered at bootstrap so that only the init callbacks can register them. / bootstrap 時に登録済みの受け口を外し、init のコールバックだけが登録できる状態にする.
			remove_all_actions( 'wp_ajax_ids' );
			remove_all_actions( 'wp_ajax_nopriv_ids' );

			unset( $GLOBALS['current_screen'] );
			if ( $case['screen'] ) {
				set_current_screen( $case['screen'] );
			}

			$this->run_plugin_callbacks( 'init' );

			$actual = array(
				'wp_ajax_ids'        => false !== has_action( 'wp_ajax_ids', array( $instance, 'ajax_rewrite_ids' ) ),
				'wp_ajax_nopriv_ids' => false !== has_action( 'wp_ajax_nopriv_ids', array( $instance, 'ajax_rewrite_ids' ) ),
			);

			$this->assertSame( $case['expected'], $actual, $case['test_condition_name'] );
		}
	}
}

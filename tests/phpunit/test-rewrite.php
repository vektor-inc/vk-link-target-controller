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
	 * Test that rewrite_link_filter() returns equivalent HTML URLs for raw and legacy ampersands.
	 * rewrite_link_filter() が生のアンパサンドと旧形式のアンパサンドで同等の HTML URL を返すことを確認する。
	 *
	 * @return void
	 */
	public function test_rewrite() {

		print PHP_EOL;
		print '------------------------------------' . PHP_EOL;
		print 'rewrite_link_filter()' . PHP_EOL;
		print '------------------------------------' . PHP_EOL;

		// Create test posts.
		$test_posts = self::create_test_posts();

		$post = get_post( $test_posts['post_id'] );

		$test_array = array(
			array(
				'test_condition_name' => 'リダイレクト URL がない場合 => 元のパーマリンクを返す',
				'original'            => get_permalink( $test_posts['post_id'] ),
				'expected'            => get_permalink( $test_posts['post_id'] ),
			),
			array(
				'test_condition_name' => '外部 URL が保存されている場合 => 外部 URL を返す',
				'original'            => get_permalink( $test_posts['post_id'] ),
				'post_meta'           => array(
					'vk-ltc-link' => 'https://google.com',
				),
				'expected'            => 'https://google.com',
			),
			array(
				'test_condition_name' => '生の & を含む URL が保存されている場合 => HTML 用の &#038; を返す',
				'original'            => get_permalink( $test_posts['post_id'] ),
				'post_meta'           => array(
					'vk-ltc-link' => 'https://example.com/?a=1&b=2',
				),
				'expected'            => 'https://example.com/?a=1&#038;b=2',
			),
			array(
				'test_condition_name' => '旧 &#038; を含む URL が保存されている場合 => 生の & と同じ HTML URL を返す',
				'original'            => get_permalink( $test_posts['post_id'] ),
				'post_meta'           => array(
					'vk-ltc-link' => 'https://example.com/?a=1&#038;b=2',
				),
				'expected'            => 'https://example.com/?a=1&#038;b=2',
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

			$this->assertEquals( $value['expected'], $actual, $value['test_condition_name'] );
		}

		wp_delete_post( $test_posts['post_id'], true );
	}

	/**
	 * Test that redirection() sends the expected destination for each saved URL and each home URL.
	 * redirection() が、保存された URL とホーム URL の組み合わせごとに想定どおりの遷移先へリダイレクトすることを確認する。
	 *
	 * redirection() calls exit after wp_redirect(), so the wp_redirect filter captures the destination and aborts the call.
	 * redirection() は wp_redirect() の後に exit するため、wp_redirect フィルターで遷移先を捕まえて処理を中断する。
	 * The captured value is what wp_redirect() receives, before WordPress core's wp_sanitize_redirect() runs, so the actual Location can differ (e.g. a single quote is removed; existing core behavior, out of scope for this fix).
	 * 捕まえるのは wp_redirect() に渡される値で、WordPress 本体の wp_sanitize_redirect() による整形の前のため、実際の Location とは異なりうる（例: シングルクォートは除去される。本体の既存の挙動で、この修正の対象外）。
	 *
	 * Case keys: 'home_url' overrides the home URL with the pre_option_home filter (default home URL when omitted),
	 * 'via_wp_action' goes through the wp action fired by go_to() like a real direct access instead of calling redirection() itself,
	 * and 'expected_list_link' also checks that the list link (rewrite_link_filter()) and the direct access lead to the same destination.
	 * ケースのキー: 'home_url' は pre_option_home フィルターでホーム URL を上書きする（省略時は既定のホーム URL）。
	 * 'via_wp_action' は redirection() を直接呼ばず、go_to() が発火する wp アクション経由で実際の直接アクセスと同じ経路を通る。
	 * 'expected_list_link' は、一覧のリンク（rewrite_link_filter()）と直接アクセスが同じ遷移先になることも確認する。
	 *
	 * @return void
	 */
	public function test_redirection() {

		$test_posts = self::create_test_posts();
		$post_id    = $test_posts['post_id'];
		$instance   = new VK_Link_Target_Controller();

		// Preserve the global post that redirection() reads. / redirection() が参照するグローバルの投稿を退避する.
		$original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		// Open the post with the default home URL in every case, so that only redirection() sees the overridden home URL. / redirection() だけが上書きしたホーム URL を見るよう、投稿は常に既定のホーム URL で開く.
		$post_url = get_permalink( $post_id );

		$test_cases = array(
			array(
				'test_condition_name' => '生の & を含む URL が保存されている場合 => 生の & の遷移先へリダイレクトする',
				'saved_url'           => 'https://example.com/?a=1&b=2',
				'expected'            => 'https://example.com/?a=1&b=2',
			),
			array(
				'test_condition_name' => '旧 &#038; を含む URL が保存されている場合 => 生の & と同じ遷移先へリダイレクトする',
				'saved_url'           => 'https://example.com/?a=1&#038;b=2',
				'expected'            => 'https://example.com/?a=1&b=2',
			),
			array(
				'test_condition_name' => '入れ子の %XX URL が保存されている場合 => %XX のまま遷移先になる',
				'saved_url'           => 'https://outer.example/?url=https%3A%2F%2Finner.example%2Fa%3Fb%3D2%26c%3D3',
				'expected'            => 'https://outer.example/?url=https%3A%2F%2Finner.example%2Fa%3Fb%3D2%26c%3D3',
			),
			array(
				'test_condition_name' => 'シングルクォートを含む URL が保存されている場合 => wp_redirect() に渡す値はクォートを保持している（実際の Location からは WordPress 本体が後段で除去する）',
				'saved_url'           => 'https://example.com/O\'Reilly',
				'expected'            => 'https://example.com/O\'Reilly',
			),
			array(
				'test_condition_name' => '空文字が保存されている場合 => リダイレクトしない',
				'saved_url'           => '',
				'expected'            => null,
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置（ホーム URL が /blog 付き）で相対パス /about/ が保存されている場合 => ホームのパスを補った /blog/about/ へリダイレクトし、一覧リンクと同じ遷移先になる',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '/about/',
				'expected'            => '/blog/about/',
				'expected_list_link'  => 'https://example.com/blog/about/',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で wp アクション経由（実際の直接アクセスと同じ経路）の場合 => /blog/about/ へリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'via_wp_action'       => true,
				'saved_url'           => '/about/',
				'expected'            => '/blog/about/',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置でクエリとフラグメントを含む相対パスの場合 => パスだけにホームのパスを補い、クエリとフラグメントはそのまま残す',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '/about/?a=1&b=2#top',
				'expected'            => '/blog/about/?a=1&b=2#top',
				'expected_list_link'  => 'https://example.com/blog/about/?a=1&b=2#top',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置でサイトのトップ / が保存されている場合 => /blog/ へリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '/',
				'expected'            => '/blog/',
				'expected_list_link'  => 'https://example.com/blog/',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で旧 &#038; を含む相対パスの場合 => ホームのパスを補い、生の & の遷移先へリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '/about/?a=1&#038;b=2',
				'expected'            => '/blog/about/?a=1&b=2',
				'expected_list_link'  => 'https://example.com/blog/about/?a=1&b=2',
			),
			array(
				'test_condition_name' => 'ドメイン直下に設置（ホーム URL にパスなし）で相対パス /about/ が保存されている場合 => 従来どおり /about/ のままリダイレクトし、一覧リンクと同じ遷移先になる',
				'home_url'            => 'https://example.com',
				'saved_url'           => '/about/',
				'expected'            => '/about/',
				'expected_list_link'  => 'https://example.com/about/',
			),
			array(
				'test_condition_name' => 'ドメイン直下に設置でホーム URL の末尾にスラッシュがある場合 => 従来どおり /about/ のままリダイレクトする（//about/ にならない）',
				'home_url'            => 'https://example.com/',
				'saved_url'           => '/about/',
				'expected'            => '/about/',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で外部の絶対 URL が保存されている場合 => 変えずにリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => 'https://external.example/about/',
				'expected'            => 'https://external.example/about/',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置でサイト内の絶対 URL が保存されている場合 => /blog を重ねずそのままリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => 'https://example.com/blog/about/',
				'expected'            => 'https://example.com/blog/about/',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置でプロトコル相対 //host/path が保存されている場合 => 変えずにリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '//cdn.example/file.pdf',
				'expected'            => '//cdn.example/file.pdf',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で mailto: が保存されている場合 => 変えずにリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => 'mailto:info@example.com',
				'expected'            => 'mailto:info@example.com',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で tel: が保存されている場合 => 変えずにリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => 'tel:+81312345678',
				'expected'            => 'tel:+81312345678',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で / 始まりでないクエリだけの値が保存されている場合 => 変えずにリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '?x=1',
				'expected'            => '?x=1',
			),
			array(
				'test_condition_name' => 'サブディレクトリ設置で / 始まりでないフラグメントだけの値が保存されている場合 => 変えずにリダイレクトする',
				'home_url'            => 'https://example.com/blog',
				'saved_url'           => '#section',
				'expected'            => '#section',
			),
		);

		/**
		 * Abort redirection() by throwing the destination passed to wp_redirect().
		 * wp_redirect() に渡された遷移先を例外で投げて redirection() を中断する。
		 *
		 * @param string $location Redirect destination. / リダイレクト先。
		 * @throws RuntimeException Always thrown with the destination as its message. / 遷移先をメッセージにして必ず投げる。
		 */
		$capture_redirect = function ( $location ) {
			throw new RuntimeException( $location );
		};

		// Home URL of the current case, or false to leave the home URL option untouched. / 現在のケースのホーム URL（false の場合はホーム URL のオプションを変更しない）.
		$home_override = false;

		/**
		 * Return the home URL of the current case for the pre_option_home filter.
		 * pre_option_home フィルター用に、現在のケースのホーム URL を返す。
		 *
		 * @return string|false Home URL, or false to use the stored option. / ホーム URL（false の場合は保存されているオプションを使う）。
		 */
		$override_home = function () use ( &$home_override ) {
			return $home_override;
		};

		add_filter( 'pre_option_home', $override_home );
		try {
			foreach ( $test_cases as $case ) {
				$home_override = false;
				$via_wp_action = ! empty( $case['via_wp_action'] );

				delete_post_meta( $post_id, 'vk-ltc-link' );
				if ( ! $via_wp_action ) {
					// Open the post before saving the URL because go_to() fires the wp action, which runs redirection() with exit. / go_to() は exit を伴う redirection() を wp アクションで実行するため、URL を保存する前に投稿を開く.
					$this->go_to( $post_url );
					$GLOBALS['post'] = get_post( $post_id );
				}
				update_post_meta( $post_id, 'vk-ltc-link', $case['saved_url'] );

				// Switch the home URL only while redirection() runs. / redirection() を実行している間だけホーム URL を切り替える.
				$home_override = isset( $case['home_url'] ) ? $case['home_url'] : false;

				// Record the destination that redirection() sends, or null when it does not redirect. / redirection() の遷移先を記録する（リダイレクトしない場合は null）.
				$actual = null;
				add_filter( 'wp_redirect', $capture_redirect );
				try {
					if ( $via_wp_action ) {
						// go_to() fires the wp action, so the plugin's own hook calls redirection() as in a real direct access. / go_to() が wp アクションを発火するため、実際の直接アクセスと同じくプラグイン自身のフックが redirection() を呼ぶ.
						$this->go_to( $post_url );
					} else {
						$instance->redirection();
					}
				} catch ( RuntimeException $redirect ) {
					$actual = $redirect->getMessage();
				} finally {
					remove_filter( 'wp_redirect', $capture_redirect );
				}

				$this->assertSame( $case['expected'], $actual, $case['test_condition_name'] );

				// Check that the list link and the direct access lead to the same destination. / 一覧のリンクと直接アクセスが同じ遷移先になることを確認する.
				if ( isset( $case['expected_list_link'] ) ) {
					$list_link = html_entity_decode( $instance->rewrite_link_filter( $post_url, get_post( $post_id ) ), ENT_QUOTES, 'UTF-8' );
					$this->assertSame( $case['expected_list_link'], $list_link, $case['test_condition_name'] . '（一覧リンク）' );

					// A path starting with a single slash is resolved from the home URL's scheme and host, as a browser does. / 単一のスラッシュで始まるパスは、ブラウザと同じくホーム URL のスキームとホストを基準に解決する.
					$resolved = $actual;
					if ( 0 === strpos( $actual, '/' ) && 0 !== strpos( $actual, '//' ) ) {
						$home_parts = wp_parse_url( home_url() );
						$resolved   = $home_parts['scheme'] . '://' . $home_parts['host'] . ( isset( $home_parts['port'] ) ? ':' . $home_parts['port'] : '' ) . $actual;
					}
					$this->assertSame( $case['expected_list_link'], $resolved, $case['test_condition_name'] . '（直接アクセスの遷移先）' );
				}
			}
		} finally {
			remove_filter( 'pre_option_home', $override_home );

			// Restore the global post. / グローバルの投稿を復元する.
			if ( null === $original_post ) {
				unset( $GLOBALS['post'] );
			} else {
				$GLOBALS['post'] = $original_post;
			}
		}

		wp_delete_post( $post_id, true );
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
	public function test_ajax_rewrite_ids() {

		$test_posts = self::create_test_posts();

		// Test cases: each URL must come back exactly as saved, with no HTML entities left.
		// テストケース: 各 URL が保存時のまま返り、HTML エンティティが残らないこと。
		$test_cases = array(
			array(
				'test_condition_name' => '複数クエリパラメータを含む URL の場合 => & がエンティティ化されずそのまま返る',
				'saved_url'           => 'https://example.com/page.php?foo=1&bar=2',
				'expected'            => 'https://example.com/page.php?foo=1&bar=2',
			),
			array(
				'test_condition_name' => '旧 &#038; を含む URL の場合 => 生の & と同じ URL が返る',
				'saved_url'           => 'https://example.com/page.php?foo=1&#038;bar=2',
				'expected'            => 'https://example.com/page.php?foo=1&bar=2',
			),
			array(
				'test_condition_name' => 'シングルクォートを含む URL の場合 => \' がエンティティ化されずそのまま返る（PHP 8.0 以前の回帰テスト）',
				'saved_url'           => 'https://example.com/page.php?q=O\'Reilly',
				'expected'            => 'https://example.com/page.php?q=O\'Reilly',
			),
			array(
				'test_condition_name' => 'シングルクォートと & の両方を含む URL の場合 => どちらもエンティティ化されずそのまま返る',
				'saved_url'           => 'https://example.com/page.php?q=O\'Reilly&x=1',
				'expected'            => 'https://example.com/page.php?q=O\'Reilly&x=1',
			),
		);

		foreach ( $test_cases as $case ) {

			// Save the URL to redirect to. / リダイレクト先 URL を保存する。
			update_post_meta( $test_posts['post_id'], 'vk-ltc-link', $case['saved_url'] );

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
			$this->assertSame( $case['expected'], $response[ $test_posts['post_id'] ]['re'], $case['test_condition_name'] );
			// No HTML entities may remain in the raw response. / 生レスポンスに HTML エンティティが残らないこと。
			$this->assertStringNotContainsString( '&#038;', $this->_last_response, $case['test_condition_name'] );
			$this->assertStringNotContainsString( '&#039;', $this->_last_response, $case['test_condition_name'] );

			// Clean up the post meta. / postmeta をクリーンアップする。
			delete_post_meta( $test_posts['post_id'], 'vk-ltc-link' );
		}

		wp_delete_post( $test_posts['post_id'], true );
	}
}

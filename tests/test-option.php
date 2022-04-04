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
	 * A single example test.
	 */
	function test_option() {
		// Replace this with some actual testing code.

		/**
		 * カスタム投稿タイプを設置
		 */
		register_post_type(
			'custom',
			array(
				'has_archive' => true,
				'public'      => true,
			)
		);

		$test_array = array(
			array(
				'options'  => false,
				'correct' => array( 'post', 'custom', 'page' ),
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
				'options'  => array( 'custom' ),
				'correct' => array( 'custom' ),
			),
			array(
				'options'  => array( 'post', 'page' ),
				'correct' => array( 'post', 'page' ),
			),
			array(
				'options'  => array( 'post', 'custom' ),
				'correct' => array( 'post', 'custom' ),
			),
			array(
				'options'  => array( 'page', 'custom' ),
				'correct' => array( 'page', 'custom' ),
			),
			array(
				'options'  => array( 'post', 'page', 'custom' ),
				'correct' => array( 'post', 'page', 'custom' ),
			),
		);
		foreach ( $test_array as $key => $value ) {
			update_option( 'custom-post-types', $value['options'] );
			$instanse = new VK_Link_Target_Controller();
			$result   = $instanse->get_option();
			$correct  = $value['correct'];
			$this->assertEquals( $result, $correct );

		}
	}
}
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
			update_option( 'custom-post-types', $value['options'] );
			$instanse = new VK_Link_Target_Controller();
			$result   = $instanse->get_option();
			$correct  = $value['correct'];
			$this->assertEquals( $correct, $result );

		}
	}
}
<?php
/*
Plugin Name: VK Link Target Controller
Plugin URI: https://github.com/kurudrive/vk-link-target-controller
Description: Allow you to link a post title from the recent posts list to another page (internal or external link) rather than link to the actual post page
Version: 0.1
Author: Vektor,Inc.
Author URI: http://www.vektor-inc.co.jp/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: vk-link-target-controller
*/

if ( ! class_exists( 'VK_Link_Target_Controller' ) ) {

	class VK_Link_Target_Controller {

		private $user_capability = 'manage_links'; //can save a link for a redirection

		/**
		* initialize_admin function
		* Activates plugin features on WordPress administration
		* @access public
		* @return void
		*/
		function initialize_admin() {
			//allow meta box for user with permission
			if ( current_user_can( $this->user_capability ) ) {
				add_action( 'add_meta_boxes', array( $this, 'add_link_meta_box' ) ); //add a meta box for the link to the post edit screen
				add_action( 'save_post', array( $this, 'save_link' ) ); //save meta box data
			}
		}

		/**
		* add_link_meta_box function
		* Add a meta box for the link to the post edit screen
		* @access public
		* @link http://codex.wordpress.org/Function_Reference/add_meta_box WordPress documentation
		* @return void
		*/
		function add_link_meta_box() {

			//load meta box only for post and custom post types based on post
			$current_screen = get_current_screen();
			if ( 'post' == $current_screen->base ) {
				add_meta_box( 
					'vk-ltc-url', //meta value key
					__( 'URL to redirect to', 'vk-link-target-controller' ),
					array( $this, 'render_link_meta_box' ),
					null,
					'normal',
					'high'
				);				
			}
		}

		/**
		* render_link_meta_box function
		* Display HTML form on post/custom post edit screen
		* @param WP_Post $post The object for the current post/custom post
		* @return void
		*/
		function render_link_meta_box( $post ) {

			//nonce field
			wp_nonce_field( 'vk-ltc-link', 'vk-ltc-link-nonce' );

			//retrieve existing string value from BD (empty if doesn't exist)
			$value = get_post_meta( $post->ID, 'vk-ltc-url', true );

			//display form
			echo '<label class="hidden" for="vk-ltc-link-field">';
			_e( 'URL to redirect to', 'vk-link-target-controller' );
			echo '</label> ';
			echo '<input type="text" id="vk-ltc-link-field" name="vk-ltc-link-field"';
			echo ' value="' . esc_attr( $value ) . '" size="50" />';
			echo '<p>' . __( 'Enter here the URL you want the title of this post to redirect to.', 'vk-link-target-controller' ) . '</p>';
		}


		/**
		* save_link function
		* Save the link when the post is saved
		* @access public		
		* @param int $post_id The ID of the post being saved
		* @return int $post_id|void The ID of the post or nothing if saved in DB
		*/
		function save_link( $post_id ) {

			//kill unauthorized user
			if ( ! current_user_can( $this->user_capability ) ) { 
				wp_die( 'You do not have sufficient permissions to access this page.', 'vk-link-target-controller' );
			} else {
				//check form
				if ( isset( $_POST['vk-ltc-link-field'] ) && wp_verify_nonce( $_POST['vk-ltc-link-nonce'], 'vk-ltc-link' ) ) {
					
					//sanitize the user input
					$link = sanitize_text_field( $_POST['vk-ltc-link-field'] );

					//verify it is an URL
					if ( $this->is_url( $link ) ) {
						//update the meta field
						update_post_meta( $post_id, 'vk-ltc-url', $link );
					} else {
						return $post_id;	
					}
				} else {
					return $post_id;
				}
			}
		}

		/**
		* redirect function
		* Check if the requested URL has to be redirected,
		* if yes redirect the user to the right URL
		* @access public		
		* @return void
		*/
		function redirect() {

		}

		/**
		* is_url function
		* Utility function to check if given string is an URL
		* @access public		
		* @param string $str The string to test
		* credit: http://php.net/manual/en/function.preg-match.php#93824 URLs checking regex
		* @return bool
		*/
		function is_url( $str ) {
			$regex = '((https?|ftp)\:\/\/)?'; // SCHEME
			$regex .= '([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?'; // User and Pass
			$regex .= '([a-z0-9-.]*)\.([a-z]{2,3})'; // Host or IP
			$regex .= '(\:[0-9]{2,5})?'; // Port
			$regex .= '(\/([a-z0-9+\$_-]\.?)+)*\/?'; // Path
			$regex .= '(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?'; // GET Query
			$regex .= '(#[a-z_.-][a-z0-9+\$_.-]*)?'; // Anchor 

			if( preg_match( '/^$regex$/', $str ) ) {
				return true;
			} else {
				return false;
			}
		}

	}

}

//instanciation
$vk_link_target_controller = new VK_Link_Target_Controller();

if ( isset( $vk_link_target_controller ) ) {
	//add_action( 'init', array( $vk_link_target_controller, 'redirect' ), 1 ); // add the redirect action, high priority

	//set up admin
	add_action( 'admin_init', array( $vk_link_target_controller, 'initialize_admin' ) );
}

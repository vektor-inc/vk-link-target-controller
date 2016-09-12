<?php
/*
Plugin Name: VK Link Target Controller
Plugin URI: https://github.com/kurudrive/vk-link-target-controller
Description: Allow you to link a post title from the recent posts list to another page (internal or external link) rather than link to the actual post page
Version: 1.2.2
Author: Vektor,Inc.
Author URI: http://www.vektor-inc.co.jp/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: vk-link-target-controller
*/

if ( ! class_exists( 'VK_Link_Target_Controller' ) ) {

	class VK_Link_Target_Controller {

		public $user_capability_link 	 = 'edit_posts'; //can save a link for a redirection
		public $user_capability_settings = 'manage_options'; //can access to the settings page

		/**
		* initialize_front function
		* Activate plugin features on front-end
		* @access public
		* @return void
		*/
		function initialize_front() {
			
			//rewrite link
			Global $post;
			if ( isset( $post ) ) {

				$link   = get_post_meta( $post->ID, 'vk-ltc-link', true );

				//activate link rewriting only for concerned posts
				if ( ! empty( $link ) && $this->candidate_post_type() ) {
					add_filter( 'the_permalink', array( $this, 'rewrite_link' ) );		
				} else {
					//remove the filter to re-establish default the_permalink behaviour
					remove_filter( 'the_permalink', array( $this, 'rewrite_link' ) );
				}
			}
		}

		/**
		* initialize_front_script function
		* Load plugin script on front-end
		* @access public
		* @return void
		*/
		function initialize_front_script() {

			//add script for target blank support
			$path_to_script = plugins_url() . '/vk-link-target-controller/js/script.js';
			
			wp_register_script( 'vk-ltc-js', $path_to_script, array( 'jquery' ), null, true );
			wp_enqueue_script( 'vk-ltc-js' );

			//Ajax
			wp_localize_script( 'vk-ltc-js', 'vkLtc', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
			add_action( 'wp_ajax_ids', array( $this, 'ajax_rewrite_ids' ) );
			add_action( 'wp_ajax_nopriv_ids', array( $this, 'ajax_rewrite_ids' ) );
		}

		/**
		* initialize_admin function
		* Activate plugin features on WordPress admin
		* @access public
		* @return void
		*/
		function initialize_admin() {

			//allow meta box for user with permission
			if ( current_user_can( $this->user_capability_link ) ) {
				add_action( 'add_meta_boxes', array( $this, 'add_link_meta_box' ) ); //add a meta box for the link to the post edit screen
				add_action( 'save_post', array( $this, 'save_link' ) ); //save meta box data
			}
		}

		/**
		* translation function
		* Load WordPress text domain in order to show translations
		* @access public
		* @return void
		*/
		function translation() {
			load_plugin_textdomain( 'vk-link-target-controller', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		* redirection function
		* Redirect to the associated link when an user tries to access directly a post that has a link to redirect to
		* @access public
		* @return void
		*/
		function redirection() {
			
			Global $post;
			//prevent unwanted redirection on admin or archive page
			if ( isset ( $post ) && ( is_single() || is_page() ) ) {
				$redirect = $this->has_redirection( $post->ID );
				//redirect to the associated link
				if ( false != $redirect && $this->candidate_post_type() ) {
					wp_redirect( html_entity_decode( esc_url( $redirect ) ) );
					exit;
				}
			}
		}

		/**
		* robots function
		* Add a specific robots meta for posts that link to another content
		* @access public
		* @return void
		*/
		function robots() {
			Global $post;

			if ( isset( $post ) ) {
				if ( false != $this->has_redirection( $post->ID ) && $this->candidate_post_type() ) {
					//remove WordPress default actions for the robots meta
					remove_action( 'wp_head', 'noindex', 1 );
					remove_action( 'wp_head', 'wp_no_robots' );
					//add specific meta robots
					if (is_single()) add_action( 'wp_head', array( $this, 'robots_html' ), 2 );
				}
			}
		}
		
		/**
		* robots_output function
		* Display HTML for the meta robots on the front end
		* @access public
		* @return void
		*/		
		function robots_html() {
			echo '<meta name="robots" content="noindex,nofollow,noarchive,noodp,noydir" />' . "\n";
		}

		/**
		* create_settings_page function
		* Build the settings page
		* @access public
		* @link http://codex.wordpress.org/Settings_API WordPress documentation
		* @link http://codex.wordpress.org/Function_Reference/add_options_page WordPress documentation
		* @return void
		*/
		function create_settings_page() {
			
			//create page for user with permission
			if ( current_user_can( $this->user_capability_settings ) ) {
				
				add_options_page( 
					esc_html_x( 'VK Link Target Controller', 'settings page title', 'vk-link-target-controller' ), 
					esc_html_x( 'Link Target Controller', 'admin menu link label', 'vk-link-target-controller' ), 
					$this->user_capability_settings, 
					'vk-ltc', 
					array( $this, 'settings_page_html' )
				); 	//add link inside options menu and related settings page
				
				register_setting( 
					'vk-ltc-options', 
					'custom-post-types',
					array( $this, 'sanitize_settings' )
				); //create settings options in DB (use WordPress Settings API)
			}	
		}
		
		/**
		* settings_page_html function
		* Display HTML for the settings page on WordPress admin
		* @access public
		* @return void
		*/
		function settings_page_html() { ?>

			<div class="wrap" id="vk-link-target-controller">
				<h2><?php echo esc_html_x( 'VK Link Target Controller', 'settings page title', 'vk-link-target-controller' ); ?></h2>

				<div style="width:68%;display:inline-block;vertical-align:top;">
					<form method="post" action="options.php">
						<?php settings_fields( 'vk-ltc-options' ); //display nonce and other control hidden fields ?>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									<?php esc_html_e( 'Display on the following post types', 'vk-link-target-controller' );  ?>
								</th>
								<td>
									<?php $post_types = $this->get_public_post_types(); //array of post types to create a checkbox list
									$post_types['page'] = __('Pages');
									foreach ( $post_types as $slug => $label ) { 
										$options_exist = get_option( 'custom-post-types', 0 );
										$checked = ( 0 != $options_exist  && in_array( $slug, $options_exist ) ) ? 'checked="checked"' : '' ; ?>
										<input type="checkbox" name="custom-post-types[]" id="custom-post-types-<?php echo $slug; ?>" value="<?php echo $slug; ?>" <?php echo $checked; ?> />
										<label for="custom-post-types-<?php echo $slug; ?>"><?php echo $label; ?></label><br /><?php 
									} ?>
								</td>
							</tr>
						</table>
						<?php submit_button(); ?>
					</form>
				</div>
		
				<!-- div for banner -->
				<div style="width:29%;display:block; overflow:hidden;float:right;">
				<?php if ( strtoupper( get_locale() ) == 'JA' ) : ?>
					<a href="http://lightning.vektor-inc.co.jp/ja/" target="_blank">
						<img style="max-width:100%;" src="<?php echo plugins_url( 'img/336_280_lightning.png', __FILE__ ) ?>" alt="WordPress Theme Lightning" />
					</a>
					<a href="http://ex-unit.vektor-inc.co.jp/ja/" target="_blank">
						<img style="max-width:100%;" src="<?php echo plugins_url( 'img/336_280_ExUnit.png', __FILE__ ) ?>" alt="VK All in One Expansion Unit" />
					</a>
				<?php else : ?>

					<a href="http://lightning.vektor-inc.co.jp/" target="_blank">
						<img style="max-width:100%;" src="<?php echo plugins_url( 'img/lightning_bnr_en.jpg', __FILE__ ) ?>" alt="lightning_bnr_en" />
					</a>
					<a href="http://bizvektor.com/en/" target="_blank" title="<?php esc_html_e( 'Free Wordpress theme for businesses', 'vk-link-target-controller' ); ?>">
						<img style="max-width:100%;" src="<?php echo plugins_url( 'img/bizVektor-ad-banner-vert.jpg', __FILE__ ) ?>" alt="<?php esc_html_e( 'Download Biz Vektor free Wordpress theme for businesses', 'vk-link-target-controller' ); ?>" />
					</a>
				<?php endif; ?>
				</div>
			
			</div>
		<?php
		}

		/**
		* sanitize_settings function
		* Callback function that sanitizes the option's value
		* @access public
		* @param $input array of data sent by the form
		* @return void
		*/
		function sanitize_settings( $input ) {	
			if ( isset( $input ) ) {
				//post types the meta box can be applied to
				$post_types 	 = $this->get_public_post_types();
				$post_type_slugs = array_keys( $post_types );

				foreach( $input as $slug_to_test ) {
					if ( ! in_array( sanitize_title( $slug_to_test ), $post_type_slugs )  ) {
						empty( $input );
					}
				}
			}
			return $input;
		}

		/**
		* add_link_meta_box function
		* Add the plugin meta box to the post edit screen
		* Note: named that way to avoid conflicts with WordPress add_meta_box function
		* @access public
		* @link http://codex.wordpress.org/Function_Reference/add_meta_box WordPress documentation
		* @return void
		*/
		function add_link_meta_box() {
			if ( $this->candidate_post_type() ) {
				add_meta_box( 
					'vk-ltc-url', //meta box html id
					esc_html__( 'URL to redirect to', 'vk-link-target-controller' ),
					array( $this, 'render_meta_box' ),
					null,
					'normal',
					'high'
				);				
			}
		}

		/**
		* render_meta_box function
		* Display HTML form for link insertion
		* @access public
		* @param WP_Post $post The object for the current post/custom post
		* @return void
		*/
		function render_meta_box( $post ) {

			//nonce field
			wp_nonce_field( 'vk-ltc-link', 'vk-ltc-link-nonce' );

			//retrieve existing values from DB (empty if doesn't exist)
			$link   = get_post_meta( $post->ID, 'vk-ltc-link', true );
			$target = get_post_meta( $post->ID, 'vk-ltc-target', true );

			$checked = $target > 0 ? 'checked="checked"' : '';

			//display form ?>
			<p>
				<?php esc_html_e( 'If you enter an URL here your visitors will access that URL directly when they click on the title of this post in Recent Posts list.', 'vk-link-target-controller' ); ?><br>
				<?php esc_html_e( 'If you want to link to the file, please upload select the file from the "File Link" button.', 'vk-link-target-controller' ); ?>
			</p>
			<p>
				<label style="display:inline-block;width:150px;" for="vk-ltc-link-field"><?php esc_html_e( 'URL', 'vk-link-target-controller' ); ?></label>
				<input type="text" id="vk-ltc-link-field" name="vk-ltc-link-field" value="<?php echo esc_url( urldecode( $link ) ); ?>" size="35" />
				<button id="media_vk-ltc-link-field" class="media_btn button button-default"><?php _e('File Link', 'vk-link-target-controller') ;?></button>
			</p>

<script type="text/javascript">
jQuery(document).ready(function($){
    var custom_uploader;
// var media_id = new Array(2);　//配列の宣言
// media_id[0] = "head_logo";
// media_id[1] = "foot_logo";

//for (i = 0; i < media_id.length; i++) {　//iという変数に0をいれループ一回ごとに加算する

        // var media_btn = '#media_' + media_id[i];
        // var media_target = '#' + media_id[i];
        jQuery('.media_btn').click(function(e) {
            media_target = jQuery(this).attr('id').replace(/media_/g,'#');
            e.preventDefault();
            if (custom_uploader) {
                custom_uploader.open();
                return;
            }
            custom_uploader = wp.media({
                title: 'Choose File',
                // 以下のコメントアウトを解除すると画像のみに限定される。
                // library: {
                //     type: 'image'
                // },
                button: {
                    text: 'Choose File'
                },
                multiple: false, // falseにすると画像を1つしか選択できなくなる
            });
            custom_uploader.on('select', function() {
                var images = custom_uploader.state().get('selection');
                images.each(function(file){
                    //$('#head_logo').append('<img src="'+file.toJSON().url+'" />');
                    jQuery(media_target).attr('value', file.toJSON().url );
                });
            });
            custom_uploader.open();
        });
//}

});
</script>

			<p>
				<label style="display:inline-block;width:150px;" for="vk-ltc-target-check"><?php esc_html_e( 'Open the link in a separate window', 'vk-link-target-controller' ); ?></label>
				<input type="checkbox" id="vk-ltc-target-check" name="vk-ltc-target-check" <?php echo $checked; ?>/>
			</p>
			<?php
		}


		/**
		* save_link function
		* Save the link when the post is saved
		* @access public		
		* @param int $post_id The ID of the post being saved.
		* @return int $post_id|void The ID of the post or nothing if saved in DB.
		*/
		function save_link( $post_id ) {

			//kill unauthorized user (double verification)
			if ( ! current_user_can( $this->user_capability_link ) ) { 
				wp_die( 'You do not have sufficient permissions to access this page.', 'vk-link-target-controller' );
			} else {
				//check form
				if ( isset( $_POST['vk-ltc-link-field'] ) 
					&& wp_verify_nonce( $_POST['vk-ltc-link-nonce'], 'vk-ltc-link' ) ) {
					
					//link field
					if ( isset( $_POST['vk-ltc-link-field'] ) ) {
						//sanitize the user input
						$link = esc_url( $_POST['vk-ltc-link-field'] );

						update_post_meta( $post_id, 'vk-ltc-link', esc_url( $link ) ); 

						/*
						//check is link is allowed content
						if ( $this->is_url( $link ) || empty( $link ) ) {
							//update the meta field
							update_post_meta( $post_id, 'vk-ltc-link', $link );
						}
						*/
					}

					//target blank option
					if ( isset( $_POST['vk-ltc-target-check'] ) ) {
						update_post_meta( $post_id, 'vk-ltc-target', 1 );
					} else {
						update_post_meta( $post_id, 'vk-ltc-target', 0 );
					}
				}
				return $post_id;
			}
		}

		/**
		* rewrite_link function
		* Filter function for the_permalink filter
		* Rewrite the link that the the_permalink() function prints out
 		* @access public
 		* @param int $id The ID of the post.	
		* @return string
		*/
		function rewrite_link( $id = 0 ) {

			if ( 0 == $id ) {
				Global $post; //use $post object
				$id = $post->ID; //id of current post
			} 

			$modified_url = '';

			$link   = get_post_meta( $id, 'vk-ltc-link', true );
			$target = get_post_meta( $id, 'vk-ltc-target', true );

			if ( empty( $link ) ) {
				$modified_url = get_permalink( $id );
			} elseif ( strpos( $link, '.' ) ) {
				$modified_url = esc_url( $link ); //complete url (extern url)
			} else {
				$modified_url = esc_url( home_url() . $link ); //partial url (internal url)
			}
			return $modified_url;
		}

		/**
		* is_url function
		* Utility function to check if given string is an URL
		* @access public		
		* @param string $url The string to test
		* @return bool
		*/
		function is_url( $url ) {

			$is_url 	= false;
			$no_failure = true;

			//prevent parse_url from causing warning error
			$parse_url_fails_on = array(
				'http:///' => 8,
				'http://:' => 8,
				);

			foreach ( $parse_url_fails_on as $fail_on_this => $length ) {
				$check_on = substr( $url, 0, $length );
				if ( $check_on == $fail_on_this ) {
					$no_failure = false;
				}
			}

			if ( 'http://' != $url && $no_failure ) {
				$components = parse_url( $url );
				if ( false != $components && isset( $components->scheme ) ) {
					$is_url = true;
				}
			}
			return $is_url;
		}

		/**
		* get_public_post_types function
		* Utility function to get post types and custom post types slugs and labels
		* @access public		
		* @return array( slug => label )
		*/
		function get_public_post_types() {

			$public_post_types = array();

			//default post type
			$post_obj = get_post_type_object( 'post' );

			$public_post_types[ $post_obj->name ] = $post_obj->label;

			//gets all custom post types set PUBLIC
			$args = array(
				'public'   => true,
				'_builtin' => false,
			);
			$custom_types_obj = get_post_types( $args, 'objects' ); 

			foreach ( $custom_types_obj as $custom_type_obj ) {
				$public_post_types[ $custom_type_obj->name ] = $custom_type_obj->label;
			}

			return $public_post_types;
		}

		/**
		* has_redirection function
		* Utility function to check if a post has a redirection link
		* @access public
		* @param int $post_id The ID of the post we want to check.
		* @return string|bool The URL to redirect to or false is none.
		*/
		function has_redirection( $post_id ) {
			$link = get_post_meta( $post_id, 'vk-ltc-link', true );
			if ( empty( $link ) ) {
				return false;
			} else {
				return $link;
			}
		}

		/**
		* candidate_post_type function
		* Utility function that checks if the plugin features should be activated for the current post type 
		* Used on both front and back end
		* @access public
		* @return bool
		*/
		function candidate_post_type() {

			$candidates   = get_option( 'custom-post-types' ); //post types where the meta box shows
			$current_post = get_post(); //object of the post being modified

			if ( ! empty( $candidates ) ) {
				if ( in_array( $current_post->post_type, $candidates ) ) {
					return true;			
				} else {
					return false;
				}
			}
		}		

		/**
		* ajax_rewrite_ids function
		* Used by jQuery script to dynamically add target="_blank" on the corresponding posts
		* @access public		
		* @return void
		*/
		function ajax_rewrite_ids() {

			$ids = array();

			$post_types 	  = $this->get_public_post_types();
			$post_types_slugs = array_keys( $post_types );
			$post_types_slugs[] = 'page';
			
			//get posts with specific post meta and post meta value
			$args = array( 
				'posts_per_page' => -1,
				'paged' 		 => 0,
				'post_type' 	 => $post_types_slugs,
				'meta_key'  	 => 'vk-ltc-target',
				'meta_value'	 => 1,
 			);
			$query = new WP_Query( $args );

			//create an array( 'id' => 'link' ) of ids from the posts found in the query
			if ( $query->found_posts > 0 ) {
				$matching_posts = $query->posts;
				foreach ( $matching_posts as $post ) {
					$ids[ $post->ID ] = html_entity_decode( $this->rewrite_link( $post->ID ) );
				}
			}

			//convert php array to json format for use in jQuery
			$json_ids = json_encode( $ids );
			
			//send data to the front
			header( 'Content-Type: application/json' );
			echo $json_ids;
			exit;
		}
	}

}

//instanciation
$vk_link_target_controller = new VK_Link_Target_Controller();

if ( isset( $vk_link_target_controller ) ) {
	//activate on front
	add_action( 'the_post', array( $vk_link_target_controller, 'initialize_front' ), 1 );
	add_action( 'init', array( $vk_link_target_controller, 'initialize_front_script' ) );
	add_action( 'wp' , array( $vk_link_target_controller, 'redirection' ) );
	add_action( 'get_header', array( $vk_link_target_controller, 'robots' ) );

	//set up admin
	add_action( 'admin_init', array( $vk_link_target_controller, 'initialize_admin' ) );
	add_action( 'admin_menu', array( $vk_link_target_controller, 'create_settings_page' ) );
	add_action( 'plugins_loaded', array( $vk_link_target_controller, 'translation' ) );
}

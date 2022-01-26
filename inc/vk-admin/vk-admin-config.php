<?php
/************************************************
 * Load modules
 */

if ( ! class_exists( 'Vk_Admin' ) ) {
	require_once dirname( __FILE__ ) . '/package/class-vk-admin.php';
}

global $vk_admin_textdomain;
$vk_admin_textdomain = 'vk-link-target-controller';

/************************************************
 * Add link setting screen
 */

// Add a link to this plugin's settings page
function vkltc_set_plugin_meta( $links ) {
	$settings_link = '<a href="' . admin_url() . 'options-general.php?page=vk-ltc">' . __( 'Setting', 'vk-link-target-controller' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
 add_filter( 'plugin_action_links_' . VK_LTC_BASENAME, 'vkltc_set_plugin_meta', 10, 1 );

<?php
/**
 * Plugin Name:       4WP Smart Link
 * Plugin URI:        https://4wp.dev/plugin/4wp-smart-link/
 * Description:       Smart Gutenberg blocks for advanced static and dynamic linking from blocks.
 * Version:           1.4.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            4WP
 * Author URI:        https://4wp.dev/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       4wp-smart-link
 *
 * @package ForWP\SmartLink
 */

defined( 'ABSPATH' ) || exit;

define( 'FORWP_SMART_LINK_VERSION', '1.4.0' );
define( 'FORWP_SMART_LINK_FILE', __FILE__ );
define( 'FORWP_SMART_LINK_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_SMART_LINK_URL', plugin_dir_url( __FILE__ ) );

require_once FORWP_SMART_LINK_PATH . 'includes/class-plugin-integrity.php';

add_action( 'admin_notices', array( ForWP\SmartLink\Plugin_Integrity::class, 'maybe_render_notice' ) );

if ( ! ForWP\SmartLink\Plugin_Integrity::verify_and_load() ) {
	return;
}

ForWP\SmartLink\Bootstrap::init();

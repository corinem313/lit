<?php
/**
 * Plugin Name:  CRNM WPCode Editor CSS
 * Plugin URI:   https://corinem.com
 * Description:  Choose which WPCode CSS snippets also load in the block editor (post editor and Site Editor).
 * Version:      1.0.0
 * Author:       Corinem LLC
 * Author URI:   https://corinem.com
 * License:      GPL-2.0-or-later
 * Text Domain:  crnm-wpcode-editor-css
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * Requires Plugins: insert-headers-and-footers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRNM_WPEC_VERSION', '1.0.0' );
define( 'CRNM_WPEC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRNM_WPEC_URL', plugin_dir_url( __FILE__ ) );
define( 'CRNM_WPEC_META_KEY', '_crnm_load_in_editor' );

/**
 * Bootstrap after plugins are loaded so WPCode is available.
 */
function crnm_wpec_init() {
	if ( ! crnm_wpec_wpcode_is_active() ) {
		add_action( 'admin_notices', 'crnm_wpec_missing_wpcode_notice' );
		return;
	}

	require_once CRNM_WPEC_PATH . 'includes/helpers.php';
	require_once CRNM_WPEC_PATH . 'includes/editor-styles.php';
	require_once CRNM_WPEC_PATH . 'includes/admin.php';

	crnm_wpec_editor_styles_init();
	crnm_wpec_admin_init();
}
add_action( 'plugins_loaded', 'crnm_wpec_init' );

/**
 * Whether WPCode (Insert Headers and Footers) is available.
 *
 * @return bool
 */
function crnm_wpec_wpcode_is_active() {
	return defined( 'WPCODE_VERSION' ) || class_exists( 'WPCode_Snippet' );
}

/**
 * Admin notice when WPCode is not active.
 */
function crnm_wpec_missing_wpcode_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'CRNM WPCode Editor CSS', 'crnm-wpcode-editor-css' ); ?>:</strong>
			<?php esc_html_e( 'This plugin requires WPCode (Insert Headers and Footers) to be installed and active.', 'crnm-wpcode-editor-css' ); ?>
		</p>
	</div>
	<?php
}

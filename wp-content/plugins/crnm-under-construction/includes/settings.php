<?php
/**
 * Settings screen and admin notices.
 *
 * @package CRNM_Under_Construction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize the enabled option to 0 or 1.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function crnm_uc_sanitize_enabled( $value ) {
	return empty( $value ) ? 0 : 1;
}

/**
 * Register the setting with the Settings API.
 */
function crnm_uc_register_setting() {
	register_setting(
		'crnm_uc_settings',
		CRNM_UC_OPTION,
		array(
			'type'              => 'boolean',
			'description'       => __( 'Serve the under-construction page to logged-out visitors.', 'crnm-under-construction' ),
			'default'           => false,
			'sanitize_callback' => 'crnm_uc_sanitize_enabled',
		)
	);
}
add_action( 'admin_init', 'crnm_uc_register_setting' );

/**
 * Add Settings → Under Construction.
 */
function crnm_uc_admin_menu() {
	add_options_page(
		__( 'Under Construction', 'crnm-under-construction' ),
		__( 'Under Construction', 'crnm-under-construction' ),
		'manage_options',
		'crnm-under-construction',
		'crnm_uc_render_settings_page'
	);
}
add_action( 'admin_menu', 'crnm_uc_admin_menu' );

/**
 * Handle Reset to blueprint on the settings screen.
 */
function crnm_uc_handle_reset() {
	if ( ! isset( $_POST['crnm_uc_reset'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'crnm_uc_reset_blueprint' );

	$ok = crnm_uc_install_blueprint( true );

	$redirect = add_query_arg(
		array(
			'page'            => 'crnm-under-construction',
			'crnm_uc_reset'   => $ok ? '1' : '0',
		),
		admin_url( 'options-general.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_init', 'crnm_uc_handle_reset' );

/**
 * Whether the WebFactory Under Construction plugin is active.
 *
 * @return bool
 */
function crnm_uc_webfactory_active() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'under-construction-page/under-construction.php' );
}

/**
 * Admin notices: mode on, missing file, WebFactory conflict, reset result.
 */
function crnm_uc_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$on_settings = $screen && 'settings_page_crnm-under-construction' === $screen->id;

	if ( isset( $_GET['crnm_uc_reset'] ) && $on_settings ) {
		if ( '1' === $_GET['crnm_uc_reset'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Working page reset to the blueprint.', 'crnm-under-construction' );
			echo '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>';
			esc_html_e( 'Could not reset the working page to the blueprint. Check file permissions.', 'crnm-under-construction' );
			echo '</p></div>';
		}
	}

	if ( crnm_uc_webfactory_active() ) {
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>' . esc_html__( 'CRNM Under Construction', 'crnm-under-construction' ) . ':</strong> ';
		esc_html_e( 'The WebFactory Under Construction plugin is also active. Disable one of them so they do not both try to replace the front end.', 'crnm-under-construction' );
		echo '</p></div>';
	}

	if ( crnm_uc_is_enabled() && ! crnm_uc_page_exists() ) {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'CRNM Under Construction', 'crnm-under-construction' ) . ':</strong> ';
		esc_html_e( 'Mode is on, but index.html is missing. The normal site is still being shown. Restore the file or use Reset to blueprint.', 'crnm-under-construction' );
		echo '</p></div>';
	} elseif ( crnm_uc_is_enabled() && ! $on_settings ) {
		$settings_url = admin_url( 'options-general.php?page=crnm-under-construction' );
		echo '<div class="notice notice-info"><p>';
		echo '<strong>' . esc_html__( 'CRNM Under Construction', 'crnm-under-construction' ) . ':</strong> ';
		echo esc_html__( 'The public site is showing the under-construction page to logged-out visitors.', 'crnm-under-construction' );
		echo ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'crnm-under-construction' ) . '</a>';
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'crnm_uc_admin_notices' );

/**
 * Render the settings page.
 */
function crnm_uc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$enabled     = crnm_uc_is_enabled();
	$page_exists = crnm_uc_page_exists();
	$page_dir    = trailingslashit( crnm_uc_path_from_root( CRNM_UC_PAGE_DIR ) );
	$preview_url = add_query_arg( 'crnm_uc_preview', '1', home_url( '/' ) );
	$blueprint   = trailingslashit( crnm_uc_path_from_root( CRNM_UC_BLUEPRINT_DIR ) );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Under Construction', 'crnm-under-construction' ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'crnm_uc_settings' ); ?>

			<h2><?php esc_html_e( 'Status', 'crnm-under-construction' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Currently:', 'crnm-under-construction' ); ?></strong>
				<?php
				echo $enabled
					? esc_html__( 'On', 'crnm-under-construction' )
					: esc_html__( 'Off', 'crnm-under-construction' );
				?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Public page', 'crnm-under-construction' ); ?></th>
					<td>
						<label for="crnm_uc_enabled">
							<input type="hidden" name="<?php echo esc_attr( CRNM_UC_OPTION ); ?>" value="0">
							<input
								name="<?php echo esc_attr( CRNM_UC_OPTION ); ?>"
								type="checkbox"
								id="crnm_uc_enabled"
								value="1"
								<?php checked( $enabled ); ?>
							>
							<?php esc_html_e( 'Serve the under-construction page to logged-out visitors.', 'crnm-under-construction' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Logged-in users still see the normal site. Log in at /wp-login.php.', 'crnm-under-construction' ); ?>
						</p>
						<?php if ( $enabled && ! $page_exists ) : ?>
							<p class="description" style="color:#b32d2e;">
								<?php esc_html_e( 'index.html is missing. The front end will not be replaced until the file is restored.', 'crnm-under-construction' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save changes', 'crnm-under-construction' ) ); ?>
		</form>

		<hr>

		<h2><?php esc_html_e( 'Preview', 'crnm-under-construction' ); ?></h2>
		<p>
			<?php esc_html_e( 'Open the under-construction page while logged in, even when the switch is off.', 'crnm-under-construction' ); ?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Preview under-construction page', 'crnm-under-construction' ); ?>
			</a>
		</p>
		<?php if ( ! $page_exists ) : ?>
			<p class="description" style="color:#b32d2e;">
				<?php esc_html_e( 'Preview needs index.html in the working page folder. Use Reset to blueprint below.', 'crnm-under-construction' ); ?>
			</p>
		<?php endif; ?>

		<hr>

		<h2><?php esc_html_e( 'Where to create or edit the page', 'crnm-under-construction' ); ?></h2>
		<p>
			<?php esc_html_e( 'Edit these files in a code editor. There is no Gutenberg screen for this page.', 'crnm-under-construction' ); ?>
		</p>
		<ul>
			<li>
				<strong><?php esc_html_e( 'HTML:', 'crnm-under-construction' ); ?></strong>
				<code><?php echo esc_html( trailingslashit( $page_dir ) . 'index.html' ); ?></code>
			</li>
			<li>
				<strong><?php esc_html_e( 'SCSS (edit this):', 'crnm-under-construction' ); ?></strong>
				<code><?php echo esc_html( trailingslashit( $page_dir ) . 'style.scss' ); ?></code>
			</li>
			<li>
				<strong><?php esc_html_e( 'CSS (compiled):', 'crnm-under-construction' ); ?></strong>
				<code><?php echo esc_html( trailingslashit( $page_dir ) . 'style.css' ); ?></code>
			</li>
			<li>
				<strong><?php esc_html_e( 'JavaScript:', 'crnm-under-construction' ); ?></strong>
				<code><?php echo esc_html( trailingslashit( $page_dir ) . 'script.js' ); ?></code>
			</li>
			<li>
				<strong><?php esc_html_e( 'Images and other files:', 'crnm-under-construction' ); ?></strong>
				<code><?php echo esc_html( trailingslashit( $page_dir ) . 'images/' ); ?></code>
			</li>
		</ul>
		<ol>
			<li><?php esc_html_e( 'index.html must be a full document (<!DOCTYPE html>, <head>, <body>).', 'crnm-under-construction' ); ?></li>
			<li><?php esc_html_e( 'Link the stylesheet and script with relative paths: style.css and script.js. Put images in images/ and refer to them as images/file.png.', 'crnm-under-construction' ); ?></li>
			<li><?php esc_html_e( 'Edit style.scss, then compile to style.css. In the working page folder run: npm install, then npm run watch (or npm run build). Do not link style.scss in the HTML.', 'crnm-under-construction' ); ?></li>
			<li><?php esc_html_e( 'The plugin inserts a <base> tag so those relative paths resolve while the visitor’s address bar stays on the URL they requested.', 'crnm-under-construction' ); ?></li>
			<li>
				<?php
				printf(
					/* translators: %s: path to the blueprint directory relative to the WordPress root */
					esc_html__( 'The blueprint (safe starting point) is %s. Use Reset to blueprint rather than copying by hand.', 'crnm-under-construction' ),
					'<code>' . esc_html( $blueprint ) . '</code>'
				);
				?>
			</li>
		</ol>

		<form method="post" action="" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Replace the working page with the blueprint? Local edits will be lost.', 'crnm-under-construction' ) ) ); ?>);">
			<?php wp_nonce_field( 'crnm_uc_reset_blueprint' ); ?>
			<?php submit_button( __( 'Reset to blueprint', 'crnm-under-construction' ), 'secondary', 'crnm_uc_reset', false ); ?>
		</form>
	</div>
	<?php
}

<?php
/**
 * Settings page and settings utilities.
 *
 * @package Save_And_Go
 */

/**
 * Copyright 2026 Alisha Thomas (https://eightysevenweb.com)
 *
 * This file is part of the "Save and Go" WordPress plugin,
 * based on "Improved Save Button" by Label Blanc (GPLv3).
 *
 * Save and Go is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Save_And_Go_Settings' ) ) {

	/**
	 * Manages the settings page and settings utilities.
	 */
	class Save_And_Go_Settings {

		/**
		 * Constants used in defining settings names, settings page and
		 * settings menu.
		 */
		const OPTION_GROUP      = 'save_and_go';
		const MAIN_SETTING_NAME = 'save_and_go_options';
		const MENU_SLUG         = 'save-and-go';

		/**
		 * Version of the settings format this plugin version uses.
		 */
		const SETTINGS_VERSION = '1.0';

		/**
		 * Cached options.
		 *
		 * @var array
		 */
		protected static $cached_options;

		/**
		 * Cached default options.
		 *
		 * @var array
		 */
		protected static $cached_default_options;

		/**
		 * Main entry point. Sets up all the WordPress hooks.
		 *
		 * @return void
		 */
		public static function setup() {
			add_action( 'admin_init', array( __CLASS__, 'setup_settings' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_admin_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_menu_icon_style' ) );
			add_action( 'admin_menu', array( __CLASS__, 'create_administration_menu' ) );

			$plugin = Save_And_Go_Utils::plugin_main_file_basename();
			add_filter( "plugin_action_links_{$plugin}", array( __CLASS__, 'plugin_settings_link' ) );
		}

		/**
		 * Hook suffixes of the plugin's admin pages, as returned by
		 * add_menu_page()/add_submenu_page().
		 *
		 * @var array
		 */
		protected static $page_hooks = array();

		/**
		 * Adds the JavaScript and CSS files required on the plugin's
		 * admin pages.
		 *
		 * @param string $page_id Id of the page currently shown.
		 * @return void
		 */
		public static function add_admin_scripts( $page_id ) {
			if ( ! in_array( $page_id, self::$page_hooks, true ) ) {
				return;
			}

			wp_enqueue_script(
				'save-and-go-settings-page',
				Save_And_Go_Utils::plugins_url( 'js/settings-page.js' ),
				array(),
				Save_And_Go_Utils::asset_version( 'js/settings-page.js' ),
				true
			);

			wp_enqueue_script(
				'save-and-go-bulk-add',
				Save_And_Go_Utils::plugins_url( 'js/bulk-add.js' ),
				array(),
				Save_And_Go_Utils::asset_version( 'js/bulk-add.js' ),
				true
			);

			wp_enqueue_style(
				'save-and-go-settings-page',
				Save_And_Go_Utils::plugins_url( 'css/settings-page.css' ),
				array(),
				Save_And_Go_Utils::asset_version( 'css/settings-page.css' )
			);
		}

		/**
		 * Registers the setting where the options are saved.
		 *
		 * @return void
		 */
		public static function setup_settings() {
			register_setting(
				self::OPTION_GROUP,
				self::MAIN_SETTING_NAME,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'validate_settings' ),
				)
			);
		}

		/**
		 * Adds the plugin's own top-level admin menu, with "Settings"
		 * and "Bulk Add" as its subpages.
		 *
		 * @return void
		 */
		public static function create_administration_menu() {
			$page_title = sprintf(
				/* translators: %s: plugin name. */
				_x( '%s Settings', 'Settings page <title>. %s = plugin name', 'save-and-go' ),
				Save_And_Go::get_localized_name()
			);

			$bulk_title = sprintf(
				/* translators: %s: plugin name. */
				_x( '%s — Bulk Add', 'Bulk Add page <title>. %s = plugin name', 'save-and-go' ),
				Save_And_Go::get_localized_name()
			);

			if ( 'settings' === self::get_menu_location() ) {
				/*
				 * Tucked-away mode: one entry under the WordPress
				 * Settings menu. The Bulk Add page has no menu entry —
				 * it is reached from the buttons on the post list
				 * screens, the Plugins page link, and the page header.
				 */
				self::$page_hooks[] = add_options_page(
					$page_title,
					__( 'Save & Go', 'save-and-go' ),
					'manage_options',
					self::MENU_SLUG,
					array( __CLASS__, 'create_options_page' )
				);

				// Hidden registrations so admin.php?page=… URLs (the
				// Plugins page links, bookmarks from the sidebar mode)
				// keep working.
				self::$page_hooks[] = add_submenu_page(
					'options.php',
					$page_title,
					$page_title,
					'manage_options',
					self::MENU_SLUG,
					array( __CLASS__, 'create_options_page' )
				);

				self::$page_hooks[] = add_submenu_page(
					'options.php',
					$bulk_title,
					$bulk_title,
					'edit_posts',
					Save_And_Go_Bulk_Add::MENU_SLUG,
					array( __CLASS__, 'create_bulk_add_page' )
				);

				return;
			}

			// Sidebar mode: own top-level menu with two subpages.
			self::$page_hooks[] = add_menu_page(
				$page_title,
				__( 'Save & Go', 'save-and-go' ),
				'manage_options',
				self::MENU_SLUG,
				array( __CLASS__, 'create_options_page' ),
				self::get_menu_icon(),
				80
			);

			// Rename the auto-created first submenu item to "Settings".
			add_submenu_page(
				self::MENU_SLUG,
				$page_title,
				_x( 'Settings', 'Submenu label of the plugin admin menu', 'save-and-go' ),
				'manage_options',
				self::MENU_SLUG,
				array( __CLASS__, 'create_options_page' )
			);

			self::$page_hooks[] = add_submenu_page(
				self::MENU_SLUG,
				$bulk_title,
				_x( 'Bulk Add', 'Submenu label of the plugin admin menu', 'save-and-go' ),
				'edit_posts',
				Save_And_Go_Bulk_Add::MENU_SLUG,
				array( __CLASS__, 'create_bulk_add_page' )
			);

			/*
			 * Hidden extra registration of the Bulk Add page. When its
			 * URL carries a &post_type= parameter (the list screen
			 * buttons and the "Bulk Add" entries in each post type's
			 * menu), WordPress core resolves the page under a different
			 * internal hook name than the submenu above provides — and
			 * dies with "Cannot load save-and-go-bulk-add." without
			 * this alias.
			 */
			self::$page_hooks[] = add_submenu_page(
				'options.php',
				$bulk_title,
				$bulk_title,
				'edit_posts',
				Save_And_Go_Bulk_Add::MENU_SLUG,
				array( __CLASS__, 'create_bulk_add_page' )
			);

			// Hidden alias so options-general.php?page=… URLs (bookmarks
			// from the tucked-away mode, the redirect right after
			// switching modes) keep working.
			self::$page_hooks[] = add_options_page(
				$page_title,
				$page_title,
				'manage_options',
				self::MENU_SLUG,
				array( __CLASS__, 'create_options_page' )
			);

			remove_submenu_page( 'options-general.php', self::MENU_SLUG );
		}

		/**
		 * The admin menu (sidebar) icon: the Save & Go brand mark
		 * (white floppy disk with the blue-to-mint "go" arrow) on a
		 * transparent background.
		 *
		 * Served as a real SVG file URL on purpose: base64 data URIs are
		 * repainted by WordPress' svg-painter (which fills this
		 * stroke-based mark into a solid block), and non-base64 data
		 * URIs are stripped by esc_url(). A plain file URL renders as a
		 * regular <img>, untouched by both, and inherits the admin
		 * menu's native dim/hover behavior.
		 *
		 * @return string
		 */
		public static function get_menu_icon() {
			return Save_And_Go_Utils::plugins_url( 'assets/menu-icon.svg' );
		}

		/**
		 * Vertically centers the sidebar menu icon.
		 *
		 * Core gives menu icon images a 9px top padding (tuned for its
		 * dashicons); a 20px image in the 34px menu row needs 7px to be
		 * truly centered with the label. Loaded on every admin page,
		 * since the menu is everywhere; scoped to our menu item only.
		 *
		 * @return void
		 */
		public static function add_menu_icon_style() {
			if ( 'sidebar' !== self::get_menu_location() ) {
				return;
			}

			wp_register_style( 'save-and-go-admin-menu', false, array(), SAVE_AND_GO_VERSION );
			wp_enqueue_style( 'save-and-go-admin-menu' );
			wp_add_inline_style(
				'save-and-go-admin-menu',
				'#adminmenu #toplevel_page_save-and-go .wp-menu-image img{padding-top:7px}'
			);
		}

		/**
		 * Returns where the plugin's admin pages live: 'sidebar' (own
		 * top-level menu, the default) or 'settings' (tucked under the
		 * WordPress Settings menu).
		 *
		 * Reads the raw option on purpose: this runs on 'admin_menu',
		 * which fires before the actions are registered on 'admin_init',
		 * so the full get_options() cache must not be primed here.
		 *
		 * @return string 'sidebar' or 'settings'.
		 */
		public static function get_menu_location() {
			$options = get_option( self::MAIN_SETTING_NAME );

			if ( is_array( $options ) && isset( $options['menu-location'] ) && 'settings' === $options['menu-location'] ) {
				return 'settings';
			}

			return 'sidebar';
		}

		/**
		 * Returns the URL of the plugin's settings page for the current
		 * menu location.
		 *
		 * @return string
		 */
		public static function get_settings_url() {
			if ( 'settings' === self::get_menu_location() ) {
				return admin_url( 'options-general.php?page=' . self::MENU_SLUG );
			}

			return admin_url( 'admin.php?page=' . self::MENU_SLUG );
		}

		/**
		 * The inline SVG of the Save & Go brand mark (floppy disk with
		 * the blue-to-mint "go" arrow) used in the plugin UI.
		 *
		 * @return string Static, hardcoded SVG markup, safe to output.
		 */
		public static function get_icon_svg() {
			return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><defs><linearGradient id="sng-arrow-grad" x1="9" y1="17" x2="15" y2="17" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#7c9bff"/><stop offset="1" stop-color="#5eead4"/></linearGradient></defs><path d="M5 3h11l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 3v5h7V3" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><rect x="7" y="13" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.5 17h4.4m0 0-1.7-1.7m1.7 1.7-1.7 1.7" stroke="url(#sng-arrow-grad)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		/**
		 * Outputs the shared page header (icon, title, tagline, version)
		 * used by both admin pages. When the plugin is tucked under the
		 * Settings menu (no submenu of its own), a quick link to the
		 * other page is shown in the header.
		 *
		 * @param string $subtitle The subtitle shown under the plugin name.
		 * @param string $nav_url  Optional URL of the header quick link.
		 * @param string $nav_text Optional label of the header quick link.
		 * @return void
		 */
		public static function render_page_header( $subtitle, $nav_url = '', $nav_text = '' ) {
			?>
			<header class="sng-header">
				<div class="sng-header-icon"><?php echo self::get_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, hardcoded SVG. ?></div>
				<div class="sng-header-text">
					<h1><?php echo esc_html( Save_And_Go::get_localized_name() ); ?></h1>
					<p><?php echo esc_html( $subtitle ); ?></p>
				</div>
				<?php if ( $nav_url && 'settings' === self::get_menu_location() ) : ?>
					<a class="sng-header-link" href="<?php echo esc_url( $nav_url ); ?>"><?php echo esc_html( $nav_text ); ?></a>
				<?php endif; ?>
				<span class="sng-version">v<?php echo esc_html( SAVE_AND_GO_VERSION ); ?></span>
			</header>
			<?php
		}

		/**
		 * Outputs the HTML of the Bulk Add page.
		 *
		 * @return void
		 */
		public static function create_bulk_add_page() {
			// Bulk Add is a content tool, not a settings page: everyone
			// who can create content may use it (each row is further
			// checked against the post type's own create/publish
			// capabilities on submit).
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html_x( 'You do not have sufficient permissions to access this page.', 'Shown when trying to access the settings page without proper permissions.', 'save-and-go' ) );
			}
			?>
			<div class="wrap sng-settings-wrap">
				<div class="sng-settings">
					<?php
					self::render_page_header(
						__( 'Create many posts, pages or custom post type entries at once.', 'save-and-go' ),
						current_user_can( 'manage_options' ) ? self::get_settings_url() : '',
						__( '← Settings', 'save-and-go' )
					);
					?>
					<?php Save_And_Go_Bulk_Add::render_tab(); ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Outputs the HTML of the settings page.
		 *
		 * @return void
		 */
		public static function create_options_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html_x( 'You do not have sufficient permissions to access this page.', 'Shown when trying to access the settings page without proper permissions.', 'save-and-go' ) );
			}

			$options        = self::get_options();
			$actions        = Save_And_Go_Actions::get_actions();
			$setting_name   = self::MAIN_SETTING_NAME;
			$default_action = $options['default-action'];
			?>
			<div class="wrap sng-settings-wrap">
				<div class="sng-settings">

					<?php
					self::render_page_header(
						__( 'Save a post and go straight to your next action — in the classic and the block editor.', 'save-and-go' ),
						Save_And_Go_Bulk_Add::get_page_url(),
						__( 'Bulk Add →', 'save-and-go' )
					);
					?>

					<?php
					// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display flag added by options.php after a save.
					if ( isset( $_GET['settings-updated'] ) ) {
						echo '<div class="sng-notice sng-notice-success"><p>' . esc_html__( 'Settings saved.', 'save-and-go' ) . '</p></div>';
					}
					// phpcs:enable WordPress.Security.NonceVerification.Recommended
					?>

					<form method="post" action="options.php" data-sng-settings="form">
						<?php settings_fields( self::OPTION_GROUP ); ?>

						<section class="sng-card">
							<div class="sng-card-header">
								<h2><?php esc_html_e( 'Button behavior', 'save-and-go' ); ?></h2>
								<p><?php esc_html_e( 'Choose how the Save & Go button appears in each editor.', 'save-and-go' ); ?></p>
							</div>
							<div class="sng-card-body">
								<div class="sng-mode-columns">
									<?php
									$button_modes = array(
										'replace' => array(
											'title' => _x( 'Replace the WordPress button', 'Button mode (used in settings page)', 'save-and-go' ),
											'desc'  => _x( 'The Publish/Update button is hidden — Save & Go is the only save button. A plain "Update" option (save and stay on the page) is added to its dropdown.', 'Button mode description (used in settings page)', 'save-and-go' ),
										),
										'primary' => array(
											'title' => _x( 'As the primary button', 'Button mode (used in settings page)', 'save-and-go' ),
											'desc'  => _x( 'Save & Go becomes the main button; the WordPress button stays available.', 'Button mode description (used in settings page)', 'save-and-go' ),
										),
										'beside'  => array(
											'title' => _x( 'Next to the WordPress button', 'Button mode (used in settings page)', 'save-and-go' ),
											'desc'  => _x( 'Both buttons keep their normal look.', 'Button mode description (used in settings page)', 'save-and-go' ),
										),
									);

									$editors = array(
										'button-mode-block'   => __( 'Block editor (Gutenberg)', 'save-and-go' ),
										'button-mode-classic' => __( 'Classic editor', 'save-and-go' ),
									);
									?>
									<?php foreach ( $editors as $mode_key => $editor_label ) : ?>
										<div class="sng-mode-col">
											<h3><?php echo esc_html( $editor_label ); ?></h3>
											<?php foreach ( $button_modes as $mode_value => $mode_labels ) : ?>
												<label class="sng-radio-row">
													<input
														type="radio"
														name="<?php echo esc_attr( $setting_name ); ?>[<?php echo esc_attr( $mode_key ); ?>]"
														value="<?php echo esc_attr( $mode_value ); ?>"
														data-sng-settings="mode"
														<?php checked( $mode_value, $options[ $mode_key ] ); ?>
													/>
													<span class="sng-radio-dot" aria-hidden="true"></span>
													<span class="sng-radio-text">
														<?php echo esc_html( $mode_labels['title'] ); ?>
														<span class="sng-radio-desc"><?php echo esc_html( $mode_labels['desc'] ); ?></span>
													</span>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endforeach; ?>
								</div>
								<p class="sng-mode-note"><?php esc_html_e( 'Note: in the block editor, "Replace" keeps the WordPress Publish button visible until a post is published for the first time, so drafts can still go through the normal publishing flow (visibility, schedule, pre-publish checks). Once published, only Save & Go is shown.', 'save-and-go' ); ?></p>
							</div>
						</section>

						<section class="sng-card">
							<div class="sng-card-header">
								<h2><?php esc_html_e( 'Actions to show', 'save-and-go' ); ?></h2>
								<p><?php esc_html_e( 'Choose which actions appear in the button dropdown.', 'save-and-go' ); ?></p>
							</div>
							<div class="sng-card-body sng-actions-grid">
								<?php foreach ( $actions as $action ) : ?>
									<?php
									$action_id      = $action->get_id();
									$action_enabled = ! empty( $options['actions'][ $action_id ] );
									?>
									<label class="sng-action-tile">
										<input
											type="checkbox"
											name="<?php echo esc_attr( $setting_name ); ?>[actions][<?php echo esc_attr( $action_id ); ?>]"
											value="1"
											data-sng-settings="action"
											data-sng-settings-value="<?php echo esc_attr( $action_id ); ?>"
											<?php checked( true, $action_enabled ); ?>
										/>
										<span class="sng-action-tile-inner">
											<span class="sng-action-check" aria-hidden="true">
												<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
											</span>
											<span class="sng-action-text">
												<span class="sng-action-name"><?php echo wp_kses_post( $action->get_name() ); ?></span>
												<?php if ( $action->get_description() ) : ?>
													<span class="sng-action-desc"><?php echo wp_kses_post( $action->get_description() ); ?></span>
												<?php endif; ?>
											</span>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="sng-card">
							<div class="sng-card-header">
								<h2><?php esc_html_e( 'Default action', 'save-and-go' ); ?></h2>
								<p><?php esc_html_e( 'The action shown on the button when the editor loads.', 'save-and-go' ); ?></p>
							</div>
							<div class="sng-card-body sng-default-list">
								<label class="sng-radio-row">
									<input
										type="radio"
										name="<?php echo esc_attr( $setting_name ); ?>[default-action]"
										value="<?php echo esc_attr( Save_And_Go_Actions::ACTION_LAST ); ?>"
										data-sng-settings="default"
										<?php checked( Save_And_Go_Actions::ACTION_LAST, $default_action ); ?>
									/>
									<span class="sng-radio-dot" aria-hidden="true"></span>
									<span class="sng-radio-text">
										<em><?php echo esc_html_x( 'Last used', '"Last used" action name (used in settings page)', 'save-and-go' ); ?></em>
										<span class="sng-radio-desc"><?php echo esc_html_x( 'The last action that was used.', '"Last used" action description (used in settings page)', 'save-and-go' ); ?></span>
									</span>
								</label>
								<?php $has_replace_mode = ( 'replace' === $options['button-mode-classic'] || 'replace' === $options['button-mode-block'] ); ?>
								<label class="sng-radio-row<?php echo $has_replace_mode ? '' : ' is-hidden'; ?>" data-sng-stay-default>
									<input
										type="radio"
										name="<?php echo esc_attr( $setting_name ); ?>[default-action]"
										value="<?php echo esc_attr( Save_And_Go_Actions::ACTION_STAY ); ?>"
										data-sng-settings="default"
										<?php checked( Save_And_Go_Actions::ACTION_STAY, $default_action ); ?>
									/>
									<span class="sng-radio-dot" aria-hidden="true"></span>
									<span class="sng-radio-text">
										<em><?php echo esc_html_x( 'Update only', '"Update only" option name (used in settings page)', 'save-and-go' ); ?></em>
										<span class="sng-radio-desc"><?php esc_html_e( 'Just saves and stays in the editor. Applies in editors set to "Replace the WordPress button"; elsewhere the first enabled action is used.', 'save-and-go' ); ?></span>
									</span>
								</label>
								<?php foreach ( $actions as $action ) : ?>
									<label class="sng-radio-row" data-sng-default-for="<?php echo esc_attr( $action->get_id() ); ?>">
										<input
											type="radio"
											name="<?php echo esc_attr( $setting_name ); ?>[default-action]"
											value="<?php echo esc_attr( $action->get_id() ); ?>"
											data-sng-settings="default"
											<?php checked( $action->get_id(), $default_action ); ?>
										/>
										<span class="sng-radio-dot" aria-hidden="true"></span>
										<span class="sng-radio-text"><?php echo wp_kses_post( $action->get_name() ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="sng-card">
							<div class="sng-card-header">
								<h2><?php esc_html_e( 'Menu location', 'save-and-go' ); ?></h2>
								<p><?php esc_html_e( 'Choose where Save & Go lives in the admin menu.', 'save-and-go' ); ?></p>
							</div>
							<div class="sng-card-body sng-default-list">
								<label class="sng-radio-row">
									<input
										type="radio"
										name="<?php echo esc_attr( $setting_name ); ?>[menu-location]"
										value="sidebar"
										<?php checked( 'sidebar', $options['menu-location'] ); ?>
									/>
									<span class="sng-radio-dot" aria-hidden="true"></span>
									<span class="sng-radio-text">
										<?php echo esc_html_x( 'Own item in the admin sidebar', 'Menu location option (used in settings page)', 'save-and-go' ); ?>
										<span class="sng-radio-desc"><?php esc_html_e( 'Save & Go gets its own menu with Settings and Bulk Add as subpages.', 'save-and-go' ); ?></span>
									</span>
								</label>
								<label class="sng-radio-row">
									<input
										type="radio"
										name="<?php echo esc_attr( $setting_name ); ?>[menu-location]"
										value="settings"
										<?php checked( 'settings', $options['menu-location'] ); ?>
									/>
									<span class="sng-radio-dot" aria-hidden="true"></span>
									<span class="sng-radio-text">
										<?php echo esc_html_x( 'Tucked under Settings', 'Menu location option (used in settings page)', 'save-and-go' ); ?>
										<span class="sng-radio-desc"><?php esc_html_e( 'Keeps the sidebar clean. Bulk Add stays one click away: in the submenu of each post type (Posts, Pages and custom post types), next to "Add New" on the post list screens, at the top of this page, and on the Plugins page.', 'save-and-go' ); ?></span>
									</span>
								</label>
							</div>
						</section>

						<div class="sng-footer">
							<button type="submit" class="sng-save-button">
								<?php echo self::get_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, hardcoded SVG. ?>
								<?php echo esc_html_x( 'Save Changes', "Settings page's save button", 'save-and-go' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
			<?php
		}

		/**
		 * Creates the "Settings" and "Bulk Add" links in the plugins page.
		 *
		 * @param array $links Existing action links.
		 * @return array
		 */
		public static function plugin_settings_link( $links ) {
			$settings_link = '<a href="' . esc_url( self::get_settings_url() ) . '">' . esc_html_x( 'Settings', 'Settings link for this plugin, in the plugins listing page.', 'save-and-go' ) . '</a>';
			$bulk_add_link = '<a href="' . esc_url( Save_And_Go_Bulk_Add::get_page_url() ) . '">' . esc_html_x( 'Bulk Add', 'Bulk Add link for this plugin, in the plugins listing page.', 'save-and-go' ) . '</a>';

			array_unshift( $links, $settings_link, $bulk_add_link );

			return $links;
		}

		/**
		 * Analyses the arguments received from the request, builds
		 * a new 'clean' settings array and returns it.
		 *
		 * @param array $input Parameters received in the request.
		 * @return array Cleaned settings array.
		 */
		public static function validate_settings( $input ) {
			// The settings form can only be reached by users with
			// manage_options; register_setting + options.php handle
			// the nonce. We still sanitize every value below.
			Save_And_Go_Actions::load_actions();
			$actions = Save_And_Go_Actions::get_actions();

			if ( ! is_array( $input ) ) {
				$input = array();
			}

			$sanitized_input = self::sanitize_options( $input );

			// Per-editor button modes ('replace', 'primary' or 'beside').
			if ( ! isset( $sanitized_input['button-mode-classic'] ) ) {
				$sanitized_input['button-mode-classic'] = 'replace';
			}

			if ( ! isset( $sanitized_input['button-mode-block'] ) ) {
				$sanitized_input['button-mode-block'] = 'replace';
			}

			// The legacy toggles are never saved anymore.
			unset( $sanitized_input['set-as-default'], $sanitized_input['set-as-default-classic'], $sanitized_input['set-as-default-block'] );

			// Menu location, defaulting to the plugin's own sidebar menu.
			if ( ! isset( $sanitized_input['menu-location'] ) ) {
				$sanitized_input['menu-location'] = 'sidebar';
			}

			// If an action is missing, we set it as disabled.
			if ( ! isset( $sanitized_input['actions'] ) ) {
				$sanitized_input['actions'] = array();
			}

			foreach ( $actions as $action ) {
				if ( ! array_key_exists( $action->get_id(), $sanitized_input['actions'] ) ) {
					$sanitized_input['actions'][ $action->get_id() ] = false;
				}
			}

			/*
			 * Determine the default action.
			 * - If none is set, we use the 'use last' action.
			 * - 'Update only' is only valid while at least one editor is in
			 *   "replace" mode (that is the only place the option exists).
			 * - If a regular action is set and it is disabled, we change it
			 *   to the 'use last' action.
			 */
			if ( ! isset( $sanitized_input['default-action'] ) ) {
				$sanitized_input['default-action'] = Save_And_Go_Actions::ACTION_LAST;
			}

			if ( Save_And_Go_Actions::ACTION_STAY === $sanitized_input['default-action'] ) {
				$has_replace_mode = ( 'replace' === $sanitized_input['button-mode-classic'] || 'replace' === $sanitized_input['button-mode-block'] );

				if ( ! $has_replace_mode ) {
					$sanitized_input['default-action'] = Save_And_Go_Actions::ACTION_LAST;
				}
			} elseif ( Save_And_Go_Actions::ACTION_LAST !== $sanitized_input['default-action'] ) {
				if ( true !== $sanitized_input['actions'][ $sanitized_input['default-action'] ] ) {
					$sanitized_input['default-action'] = Save_And_Go_Actions::ACTION_LAST;
				}
			}

			$sanitized_input['version'] = self::SETTINGS_VERSION;

			return $sanitized_input;
		}

		/**
		 * Returns the default options values.
		 *
		 * @return array Associative array of options.
		 */
		public static function get_default_options() {
			if ( ! isset( self::$cached_default_options ) ) {
				$defaults = array(
					'button-mode-classic' => 'replace',
					'button-mode-block'   => 'replace',
					'menu-location'       => 'sidebar',
					'actions'             => array(),
					'default-action'      => Save_And_Go_Actions::ACTION_LAST,
				);

				// By default, all the available actions are enabled.
				$actions = Save_And_Go_Actions::get_actions();

				foreach ( $actions as $action ) {
					$defaults['actions'][ $action->get_id() ] = true;
				}

				self::$cached_default_options = $defaults;
			}

			return self::$cached_default_options;
		}

		/**
		 * Returns an array of all the option values saved in the database,
		 * where non-defined options are set with the defaults provided
		 * by self::get_default_options().
		 *
		 * @return array Associative array of options.
		 */
		public static function get_options() {
			if ( ! isset( self::$cached_options ) ) {
				$options = get_option( self::MAIN_SETTING_NAME );

				if ( ! is_array( $options ) ) {
					$options = array();
				}

				// Sanitizing any invalid value in the database.
				$options = self::sanitize_options( $options );

				self::$cached_options = self::merge_options_with_default( $options );
			}

			return self::$cached_options;
		}

		/**
		 * Returns an options array with the default values overwritten
		 * by the ones in the supplied array.
		 *
		 * @param array $options Overwrites to the defaults.
		 * @return array
		 */
		public static function merge_options_with_default( $options = array() ) {
			return array_replace_recursive( self::get_default_options(), $options );
		}

		/**
		 * Receives an options array and sanitizes its values to ensure
		 * it has correct types and existing actions. Removes any invalid
		 * action.
		 *
		 * @param array $options The options to sanitize.
		 * @return array
		 */
		public static function sanitize_options( $options = array() ) {
			// Migration: a legacy single 'set-as-default' value (from
			// versions with one toggle) seeds both per-editor toggles.
			if ( isset( $options['set-as-default'] ) ) {
				$legacy = (bool) $options['set-as-default'];

				if ( ! isset( $options['set-as-default-classic'] ) ) {
					$options['set-as-default-classic'] = $legacy;
				}

				if ( ! isset( $options['set-as-default-block'] ) ) {
					$options['set-as-default-block'] = $legacy;
				}

				unset( $options['set-as-default'] );
			}

			// Migration: the legacy per-editor booleans (from versions
			// with toggles) seed the per-editor button modes.
			if ( isset( $options['set-as-default-classic'] ) ) {
				if ( ! isset( $options['button-mode-classic'] ) ) {
					$options['button-mode-classic'] = $options['set-as-default-classic'] ? 'primary' : 'beside';
				}

				unset( $options['set-as-default-classic'] );
			}

			if ( isset( $options['set-as-default-block'] ) ) {
				if ( ! isset( $options['button-mode-block'] ) ) {
					$options['button-mode-block'] = $options['set-as-default-block'] ? 'primary' : 'beside';
				}

				unset( $options['set-as-default-block'] );
			}

			// The button modes must be one of the known values.
			$valid_modes = array( 'beside', 'primary', 'replace' );

			foreach ( array( 'button-mode-classic', 'button-mode-block' ) as $mode_key ) {
				if ( isset( $options[ $mode_key ] ) ) {
					$options[ $mode_key ] = sanitize_key( (string) $options[ $mode_key ] );

					if ( ! in_array( $options[ $mode_key ], $valid_modes, true ) ) {
						unset( $options[ $mode_key ] );
					}
				}
			}

			// The menu location must be one of the known values.
			if ( isset( $options['menu-location'] ) ) {
				$options['menu-location'] = sanitize_key( (string) $options['menu-location'] );

				if ( ! in_array( $options['menu-location'], array( 'sidebar', 'settings' ), true ) ) {
					unset( $options['menu-location'] );
				}
			}

			// 'default-action' must be an existing action or a special id.
			if ( isset( $options['default-action'] ) ) {
				$options['default-action'] = sanitize_text_field( (string) $options['default-action'] );

				if (
					Save_And_Go_Actions::ACTION_LAST !== $options['default-action']
					&& Save_And_Go_Actions::ACTION_STAY !== $options['default-action']
					&& ! Save_And_Go_Actions::action_exists( $options['default-action'] )
				) {
					unset( $options['default-action'] );
				}
			}

			// Each action must exist.
			if ( isset( $options['actions'] ) ) {
				if ( ! is_array( $options['actions'] ) ) {
					unset( $options['actions'] );
				} else {
					foreach ( $options['actions'] as $action_id => $action_enabled ) {
						if ( ! Save_And_Go_Actions::action_exists( $action_id ) ) {
							unset( $options['actions'][ $action_id ] );
							continue;
						}

						$options['actions'][ $action_id ] = (bool) $action_enabled;
					}
				}
			}

			if ( isset( $options['version'] ) ) {
				$options['version'] = sanitize_text_field( (string) $options['version'] );
			}

			return $options;
		}

		/**
		 * Returns an associative array of all the actions enabled in the
		 * settings page. The keys are the action ids and the values are
		 * Save_And_Go_Action instances.
		 *
		 * @return array The enabled actions.
		 */
		public static function get_enabled_actions() {
			$options        = self::get_options();
			$active_actions = array();

			if ( isset( $options['actions'] ) ) {
				foreach ( $options['actions'] as $action_id => $action_enabled ) {
					$action = Save_And_Go_Actions::get_action( $action_id );

					if ( ! is_null( $action ) && $action_enabled ) {
						$active_actions[ $action_id ] = $action;
					}
				}
			}

			return $active_actions;
		}
	}
}

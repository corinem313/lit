<?php
/**
 * Plugin bootstrap.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Registers hooks shared across the plugin.
 */
final class Bootstrap {

	/**
	 * Whether host-mode front-end script was requested during render.
	 *
	 * @var bool
	 */
	private static $frontend_script_enqueued = false;

	/**
	 * Whether core/image lightbox assets were requested during render.
	 *
	 * @var bool
	 */
	private static $cover_lightbox_enqueued = false;

	/**
	 * Wire WordPress hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_editor_block' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_assets' ) );

		Block_Attributes::register();
		Block_Link::register();
		Smart_Link_Body_Image_Gallery::register();
		Smart_Link_Page_Lightbox_Gallery::register();
	}

	/**
	 * Registers an invisible block type so editor scripts/styles enqueue via block.json.
	 *
	 * @return void
	 */
	public static function register_editor_block(): void {
		$dir = FORWP_SMART_LINK_PATH . 'build/editor';

		if ( is_readable( $dir . '/block.json' ) ) {
			register_block_type( $dir );
		}
	}

	/**
	 * Core image lightbox trigger styles for the Cover editor indicator.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets(): void {
		if ( wp_style_is( 'wp-block-image', 'registered' ) ) {
			wp_enqueue_style( 'wp-block-image' );
		}
	}

	/**
	 * Enqueue baseline styles; script only when host mode rendered on this request.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'forwp-smart-link-frontend',
			FORWP_SMART_LINK_URL . 'assets/forwp-smart-link-frontend.css',
			array(),
			FORWP_SMART_LINK_VERSION
		);

		if ( self::$frontend_script_enqueued ) {
			self::register_frontend_script();
		}

		if ( self::$cover_lightbox_enqueued ) {
			self::register_cover_lightbox_assets();
		}
	}

	/**
	 * Mark host-mode script for enqueue (safe to call during block render).
	 *
	 * @return void
	 */
	public static function enqueue_frontend_script(): void {
		self::$frontend_script_enqueued = true;

		if ( did_action( 'wp_enqueue_scripts' ) ) {
			self::register_frontend_script();
		}
	}

	/**
	 * Mark core/image lightbox assets for enqueue (safe during block render).
	 *
	 * @return void
	 */
	public static function enqueue_cover_lightbox(): void {
		self::$cover_lightbox_enqueued = true;

		if ( did_action( 'wp_enqueue_scripts' ) ) {
			self::register_cover_lightbox_assets();
		}
	}

	/**
	 * @return void
	 */
	private static function register_cover_lightbox_assets(): void {
		if ( ! wp_style_is( 'wp-block-image', 'enqueued' ) ) {
			wp_enqueue_style( 'wp-block-image' );
		}

		// Load after core image styles so Cover lightbox trigger stays visible (opacity) and pinned (top/right).
		$frontend_css = FORWP_SMART_LINK_PATH . 'assets/forwp-smart-link-frontend.css';
		wp_enqueue_style(
			'forwp-smart-link-frontend',
			FORWP_SMART_LINK_URL . 'assets/forwp-smart-link-frontend.css',
			array( 'wp-block-image' ),
			is_readable( $frontend_css ) ? (string) filemtime( $frontend_css ) : FORWP_SMART_LINK_VERSION
		);

		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		wp_enqueue_script_module( '@wordpress/block-library/image/view' );

		$gallery_path = FORWP_SMART_LINK_PATH . 'assets/forwp-smart-link-lightbox-gallery.js';
		$gallery_url  = FORWP_SMART_LINK_URL . 'assets/forwp-smart-link-lightbox-gallery.js';

		if ( is_readable( $gallery_path ) ) {
			wp_register_script_module(
				'forwp/smart-link-lightbox-gallery',
				$gallery_url,
				array( '@wordpress/block-library/image/view' ),
				(string) filemtime( $gallery_path )
			);
			wp_enqueue_script_module( 'forwp/smart-link-lightbox-gallery' );
		}
	}

	/**
	 * @return void
	 */
	private static function register_frontend_script(): void {
		if ( wp_script_is( 'forwp-smart-link-frontend', 'registered' ) ) {
			return;
		}

		$path = FORWP_SMART_LINK_PATH . 'assets/forwp-smart-link-frontend.js';

		wp_register_script(
			'forwp-smart-link-frontend',
			FORWP_SMART_LINK_URL . 'assets/forwp-smart-link-frontend.js',
			array(),
			FORWP_SMART_LINK_VERSION,
			true
		);

		if ( is_readable( $path ) ) {
			wp_enqueue_script( 'forwp-smart-link-frontend' );
		}
	}
}

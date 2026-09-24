<?php
/**
 * Admin UI: snippet toggle, overview page, and snippets table column.
 *
 * @package crnm-wpcode-editor-css
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin hooks.
 */
function crnm_wpec_admin_init() {
	add_action( 'admin_menu', 'crnm_wpec_register_admin_page', 30 );
	add_action( 'admin_init', 'crnm_wpec_handle_overview_save' );
	add_action( 'admin_enqueue_scripts', 'crnm_wpec_enqueue_admin_assets' );
	add_action( 'wpcode_admin_page_content_wpcode-snippet-manager', 'crnm_wpec_render_snippet_metabox' );
	add_action( 'wpcode_snippet_after_update', 'crnm_wpec_save_snippet_flag', 10, 2 );
	add_filter( 'wpcode_code_snippets_table_columns', 'crnm_wpec_add_snippets_table_column' );
	add_filter( 'wpcode_code_snippets_table_column_value', 'crnm_wpec_snippets_table_column_value', 10, 3 );
}

/**
 * Add a WPCode submenu for choosing editor snippets.
 */
function crnm_wpec_register_admin_page() {
	add_submenu_page(
		'wpcode',
		__( 'Editor CSS', 'crnm-wpcode-editor-css' ),
		__( 'Editor CSS', 'crnm-wpcode-editor-css' ),
		crnm_wpec_manage_capability(),
		'crnm-wpcode-editor-css',
		'crnm_wpec_render_admin_page'
	);
}

/**
 * Styles for the overview page.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function crnm_wpec_enqueue_admin_assets( $hook_suffix ) {
	if ( false === strpos( (string) $hook_suffix, 'crnm-wpcode-editor-css' ) ) {
		return;
	}

	wp_enqueue_style(
		'crnm-wpcode-editor-css-admin',
		CRNM_WPEC_URL . 'assets/admin.css',
		array(),
		CRNM_WPEC_VERSION
	);
}

/**
 * Whether the current request is the WPCode snippet editor (not the library picker).
 *
 * @return bool
 */
function crnm_wpec_is_snippet_editor_screen() {
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'wpcode-snippet-manager' !== $page ) {
		return false;
	}

	if ( isset( $_GET['snippet_id'] ) || isset( $_GET['custom'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}

	return ! apply_filters( 'wpcode_add_snippet_show_library', true );
}

/**
 * Metabox on the WPCode snippet editor for CSS/SCSS snippets.
 */
function crnm_wpec_render_snippet_metabox() {
	if ( ! crnm_wpec_is_snippet_editor_screen() ) {
		return;
	}

	$snippet_id = isset( $_GET['snippet_id'] ) ? absint( $_GET['snippet_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$checked    = $snippet_id ? crnm_wpec_snippet_loads_in_editor( $snippet_id ) : false;
	$types      = implode( ',', crnm_wpec_supported_code_types() );

	$toggle = function_exists( 'wpcode_get_checkbox_toggle' )
		? wpcode_get_checkbox_toggle( $checked, 'crnm_load_in_editor', '', '1' )
		: sprintf(
			'<input type="checkbox" name="crnm_load_in_editor" id="crnm_load_in_editor" value="1" %s />',
			checked( $checked, true, false )
		);
	?>
	<div class="wpcode-metabox" data-show-if-id="#wpcode_snippet_type" data-show-if-value="<?php echo esc_attr( $types ); ?>">
		<div class="wpcode-metabox-title">
			<div class="wpcode-metabox-title-text">
				<?php esc_html_e( 'Block Editor', 'crnm-wpcode-editor-css' ); ?>
			</div>
		</div>
		<div class="wpcode-metabox-content">
			<p><?php esc_html_e( 'WPCode only prints CSS on the front end. Enable this to also load the snippet in the post editor and Site Editor so the canvas matches the front end.', 'crnm-wpcode-editor-css' ); ?></p>
			<div class="wpcode-separator"></div>
			<div class="wpcode-metabox-form">
				<div class="wpcode-metabox-form-row">
					<div class="wpcode-metabox-form-row-label">
						<label for="crnm_load_in_editor"><?php esc_html_e( 'Load in editor', 'crnm-wpcode-editor-css' ); ?></label>
					</div>
					<div class="wpcode-metabox-form-row-input">
						<?php echo $toggle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Save the editor flag when a WPCode snippet is saved from the snippet manager form.
 *
 * @param int         $snippet_id Snippet ID.
 * @param object|null $snippet    WPCode snippet instance.
 */
function crnm_wpec_save_snippet_flag( $snippet_id, $snippet = null ) {
	if ( ! current_user_can( crnm_wpec_manage_capability() ) ) {
		return;
	}

	if ( ! isset( $_POST['wpcode-save-snippet-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$code_type = '';
	if ( is_object( $snippet ) && method_exists( $snippet, 'get_code_type' ) ) {
		$code_type = $snippet->get_code_type();
	} elseif ( isset( $_POST['wpcode_snippet_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code_type = sanitize_text_field( wp_unslash( $_POST['wpcode_snippet_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	if ( ! in_array( $code_type, crnm_wpec_supported_code_types(), true ) ) {
		crnm_wpec_set_snippet_loads_in_editor( $snippet_id, false );
		return;
	}

	$enabled = isset( $_POST['crnm_load_in_editor'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	crnm_wpec_set_snippet_loads_in_editor( $snippet_id, $enabled );
}

/**
 * Add an Editor column to the WPCode snippets table.
 *
 * @param array $columns Table columns.
 * @return array
 */
function crnm_wpec_add_snippets_table_column( $columns ) {
	if ( ! is_array( $columns ) ) {
		return $columns;
	}

	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'code_type' === $key ) {
			$new['crnm_editor'] = esc_html__( 'Editor', 'crnm-wpcode-editor-css' );
		}
	}

	if ( ! isset( $new['crnm_editor'] ) ) {
		$new['crnm_editor'] = esc_html__( 'Editor', 'crnm-wpcode-editor-css' );
	}

	return $new;
}

/**
 * Value for the Editor column.
 *
 * @param string $value       Current cell value.
 * @param object $snippet     WPCode snippet instance.
 * @param string $column_name Column key.
 * @return string
 */
function crnm_wpec_snippets_table_column_value( $value, $snippet, $column_name ) {
	if ( 'crnm_editor' !== $column_name ) {
		return $value;
	}

	if ( ! is_object( $snippet ) || ! method_exists( $snippet, 'get_id' ) ) {
		return '—';
	}

	$code_type = method_exists( $snippet, 'get_code_type' ) ? $snippet->get_code_type() : '';
	if ( ! in_array( $code_type, crnm_wpec_supported_code_types(), true ) ) {
		return '—';
	}

	if ( crnm_wpec_snippet_loads_in_editor( $snippet->get_id() ) ) {
		return esc_html__( 'Yes', 'crnm-wpcode-editor-css' );
	}

	return '—';
}

/**
 * Save the overview page checkboxes.
 */
function crnm_wpec_handle_overview_save() {
	if ( ! isset( $_POST['crnm_wpec_save'] ) ) {
		return;
	}

	if ( ! current_user_can( crnm_wpec_manage_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to manage editor CSS snippets.', 'crnm-wpcode-editor-css' ) );
	}

	check_admin_referer( 'crnm_wpec_save_editor_snippets' );

	$posted = array();
	if ( isset( $_POST['crnm_wpec_snippets'] ) && is_array( $_POST['crnm_wpec_snippets'] ) ) {
		$posted = array_map( 'absint', wp_unslash( $_POST['crnm_wpec_snippets'] ) );
	}

	$snippets = crnm_wpec_get_css_snippets( false );

	foreach ( $snippets as $post ) {
		crnm_wpec_set_snippet_loads_in_editor( $post->ID, in_array( $post->ID, $posted, true ) );
	}

	$redirect = add_query_arg(
		array(
			'page'             => 'crnm-wpcode-editor-css',
			'crnm_wpec_saved'  => '1',
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Overview page: pick which CSS snippets load in the editor.
 */
function crnm_wpec_render_admin_page() {
	if ( ! current_user_can( crnm_wpec_manage_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to manage editor CSS snippets.', 'crnm-wpcode-editor-css' ) );
	}

	$snippets = crnm_wpec_get_css_snippets( false );
	$saved    = isset( $_GET['crnm_wpec_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap crnm-wpec-wrap">
		<h1><?php esc_html_e( 'Editor CSS', 'crnm-wpcode-editor-css' ); ?></h1>
		<p class="crnm-wpec-intro">
			<?php esc_html_e( 'Choose which WPCode CSS snippets should also load in the block editor. Inactive snippets stay listed here but are only injected when they are active.', 'crnm-wpcode-editor-css' ); ?>
		</p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Editor CSS snippets saved.', 'crnm-wpcode-editor-css' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=crnm-wpcode-editor-css' ) ); ?>">
			<?php wp_nonce_field( 'crnm_wpec_save_editor_snippets' ); ?>

			<?php if ( empty( $snippets ) ) : ?>
				<p><?php esc_html_e( 'No CSS snippets found. Create a CSS snippet in WPCode first, then return here to enable it for the editor.', 'crnm-wpcode-editor-css' ); ?></p>
			<?php else : ?>
				<table class="widefat striped crnm-wpec-table">
					<thead>
						<tr>
							<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Load in editor', 'crnm-wpcode-editor-css' ); ?></span></td>
							<th><?php esc_html_e( 'Snippet', 'crnm-wpcode-editor-css' ); ?></th>
							<th><?php esc_html_e( 'Type', 'crnm-wpcode-editor-css' ); ?></th>
							<th><?php esc_html_e( 'Status', 'crnm-wpcode-editor-css' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'crnm-wpcode-editor-css' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $snippets as $post ) : ?>
							<?php
							$code_type = '';
							$terms     = wp_get_post_terms( $post->ID, 'wpcode_type', array( 'fields' => 'slugs' ) );
							if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
								$code_type = (string) $terms[0];
							}
							$priority = (int) get_post_meta( $post->ID, '_wpcode_priority', true );
							if ( $priority <= 0 ) {
								$priority = 10;
							}
							$edit_url = add_query_arg(
								array(
									'page'       => 'wpcode-snippet-manager',
									'snippet_id' => $post->ID,
								),
								admin_url( 'admin.php' )
							);
							$title = $post->post_title ? $post->post_title : __( 'Untitled Snippet', 'crnm-wpcode-editor-css' );
							?>
							<tr>
								<th class="check-column">
									<input
										type="checkbox"
										name="crnm_wpec_snippets[]"
										value="<?php echo esc_attr( (string) $post->ID ); ?>"
										<?php checked( crnm_wpec_snippet_loads_in_editor( $post->ID ) ); ?>
									/>
								</th>
								<td>
									<a href="<?php echo esc_url( $edit_url ); ?>">
										<?php echo esc_html( $title ); ?>
									</a>
									<div class="row-actions">
										<span class="id"><?php echo esc_html( sprintf( /* translators: %d snippet ID */ __( 'ID: %d', 'crnm-wpcode-editor-css' ), $post->ID ) ); ?></span>
									</div>
								</td>
								<td><?php echo esc_html( strtoupper( $code_type ) ); ?></td>
								<td>
									<?php
									if ( 'publish' === $post->post_status ) {
										esc_html_e( 'Active', 'crnm-wpcode-editor-css' );
									} else {
										esc_html_e( 'Inactive', 'crnm-wpcode-editor-css' );
									}
									?>
								</td>
								<td><?php echo esc_html( (string) $priority ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $snippets ) ) : ?>
				<p class="submit">
					<button type="submit" name="crnm_wpec_save" class="button button-primary" value="1">
						<?php esc_html_e( 'Save Changes', 'crnm-wpcode-editor-css' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

<?php
/**
 * Mainframe Theme — Headless Onboarding
 *
 * Guides new activations through optional headless configuration without
 * silently modifying any WordPress core settings. All changes are explicit
 * and user-initiated via the "Headless Quick Setup" card on the settings page.
 *
 * Flow:
 *   1. Theme activates → mainframe_onboarding_pending flag set.
 *   2. Admin notice appears pointing to Mainframe Settings.
 *   3. Setup card on settings page lets user choose which headless defaults to apply.
 *   4. On submit → options are written, mainframe_setup_complete set, card hidden.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Activation — set pending flag
// ---------------------------------------------------------------------------

add_action( 'after_switch_theme', 'mainframe_set_onboarding_flag' );
/**
 * Mark onboarding as pending when the theme is activated.
 *
 * Only sets the flag when setup has not already been completed, so
 * re-activating after a temporary switch does not re-show the wizard.
 */
function mainframe_set_onboarding_flag(): void {
	if ( ! get_option( 'mainframe_setup_complete' ) ) {
		update_option( 'mainframe_onboarding_pending', '1', false );
	}
}

// ---------------------------------------------------------------------------
// Admin notice
// ---------------------------------------------------------------------------

add_action( 'admin_notices', 'mainframe_render_onboarding_notice' );
/**
 * Display a dismissible admin notice pointing to the Headless Quick Setup.
 *
 * Shown only when onboarding is pending, setup is not yet complete, and
 * the current user has manage_options capability.
 */
function mainframe_render_onboarding_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! get_option( 'mainframe_onboarding_pending' ) ) {
		return;
	}
	if ( get_option( 'mainframe_setup_complete' ) ) {
		return;
	}

	$settings_url = admin_url( 'themes.php?page=mainframe-settings' );
	$nonce        = wp_create_nonce( 'mainframe_dismiss_notice' );
	?>
	<div
		class="notice notice-info is-dismissible"
		id="mainframe-onboarding-notice"
		style="padding:12px 40px 12px 16px;"
	>
		<p>
			<strong><?php esc_html_e( 'Mainframe is active.', 'mainframe' ); ?></strong>
			<?php esc_html_e( 'Your site works normally right now. To configure headless mode, run the quick setup.', 'mainframe' ); ?>
			&nbsp;<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Mainframe Settings &rarr;', 'mainframe' ); ?></a>
		</p>
	</div>
	<script>
	(function () {
		var notice = document.getElementById( 'mainframe-onboarding-notice' );
		if ( ! notice ) { return; }
		notice.addEventListener( 'click', function ( e ) {
			if ( ! e.target || ! e.target.classList.contains( 'notice-dismiss' ) ) { return; }
			e.preventDefault();
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.send(
				'action=mainframe_dismiss_onboarding_notice' +
				'&_ajax_nonce=' + encodeURIComponent( <?php echo wp_json_encode( $nonce ); ?> )
			);
			notice.parentNode.removeChild( notice );
		} );
	}());
	</script>
	<?php
}

// ---------------------------------------------------------------------------
// AJAX — dismiss notice
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_mainframe_dismiss_onboarding_notice', 'mainframe_ajax_dismiss_onboarding_notice' );
/**
 * Handle the AJAX request to permanently dismiss the onboarding admin notice.
 */
function mainframe_ajax_dismiss_onboarding_notice(): void {
	check_ajax_referer( 'mainframe_dismiss_notice' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}

	update_option( 'mainframe_onboarding_pending', '0', false );
	wp_send_json_success();
}

// ---------------------------------------------------------------------------
// Setup card — hooked into settings page top
// ---------------------------------------------------------------------------

add_action( 'mainframe_settings_page_top', 'mainframe_render_setup_card' );
/**
 * Render the "Headless Quick Setup" card at the top of the Mainframe Settings
 * page. Hidden once setup has been completed.
 *
 * Also handles the success notice shown immediately after the apply action
 * redirects back with ?mainframe_setup=done.
 */
function mainframe_render_setup_card(): void {
	// Success notice — shown after a successful apply redirect.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( get_option( 'mainframe_setup_complete' ) && isset( $_GET['mainframe_setup'] ) && 'done' === sanitize_key( $_GET['mainframe_setup'] ) ) {
		?>
		<div class="notice notice-success inline" style="margin-bottom:20px;">
			<p>
				<strong><?php esc_html_e( 'Headless defaults applied.', 'mainframe' ); ?></strong>
				<?php esc_html_e( 'Your site is now configured for headless operation. All selected settings are reversible from this page at any time.', 'mainframe' ); ?>
			</p>
		</div>
		<?php
		return;
	}

	if ( get_option( 'mainframe_setup_complete' ) ) {
		return;
	}

	$action_url = admin_url( 'admin-post.php' );
	?>
	<div
		id="mainframe-setup-card"
		style="
			background:#fff;
			border:1px solid #c3c4c7;
			border-left:4px solid #2271b1;
			box-shadow:0 1px 1px rgba(0,0,0,.04);
			margin:0 0 24px;
			padding:20px 24px;
			max-width:680px;
		"
	>
		<h2 style="margin-top:0;font-size:1.1em;">
			<?php esc_html_e( 'Headless Quick Setup', 'mainframe' ); ?>
		</h2>
		<p>
			<?php
			esc_html_e(
				'Mainframe is currently in safe mode — all your WordPress content is publicly accessible at its standard URLs. This is intentional: it lets you verify your content before switching.',
				'mainframe'
			);
			?>
		</p>
		<p>
			<?php
			esc_html_e(
				'When your consuming app (Next.js, Nuxt, SvelteKit, etc.) is ready to be the only public face of your site, apply the headless defaults below. Each setting is reversible from this page at any time.',
				'mainframe'
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-top:16px;">
			<?php wp_nonce_field( 'mainframe_apply_headless' ); ?>
			<input type="hidden" name="action" value="mainframe_apply_headless">

			<table style="border-collapse:collapse;width:100%;">
				<tbody>

					<tr>
						<td style="width:24px;vertical-align:top;padding:6px 0;">
							<input type="checkbox" name="mf_route_behavior" id="mf_route_behavior" value="1" checked>
						</td>
						<td style="padding:4px 0 12px 10px;">
							<label for="mf_route_behavior" style="font-weight:600;display:block;">
								<?php esc_html_e( 'Redirect all public content routes to home', 'mainframe' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Posts, pages, and archives redirect visitors to the home page by default. Individual posts can still be set to "Show content" via the per-post meta box.', 'mainframe' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td style="width:24px;vertical-align:top;padding:6px 0;">
							<input type="checkbox" name="mf_blog_public" id="mf_blog_public" value="1" checked>
						</td>
						<td style="padding:4px 0 12px 10px;">
							<label for="mf_blog_public" style="font-weight:600;display:block;">
								<?php esc_html_e( 'Discourage search engine indexing', 'mainframe' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Tells search engines not to index this WordPress backend. Your consuming frontend should handle SEO instead.', 'mainframe' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td style="width:24px;vertical-align:top;padding:6px 0;">
							<input type="checkbox" name="mf_comments" id="mf_comments" value="1" checked>
						</td>
						<td style="padding:4px 0 12px 10px;">
							<label for="mf_comments" style="font-weight:600;display:block;">
								<?php esc_html_e( 'Disable comments and pingbacks on new posts', 'mainframe' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Comments can still be enabled per-post and are fully accessible via the REST API when a post has comment_status = open.', 'mainframe' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td style="width:24px;vertical-align:top;padding:6px 0;">
							<input type="checkbox" name="mf_uploads" id="mf_uploads" value="1">
						</td>
						<td style="padding:4px 0 12px 10px;">
							<label for="mf_uploads" style="font-weight:600;display:block;">
								<?php esc_html_e( 'Use flat upload folder structure', 'mainframe' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disables year/month subfolders in wp-content/uploads/. Automatically skipped if year-based folders already exist.', 'mainframe' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td style="width:24px;vertical-align:top;padding:6px 0;">
							<input type="checkbox" name="mf_block_manager" id="mf_block_manager" value="1" checked>
						</td>
						<td style="padding:4px 0 12px 10px;">
							<label for="mf_block_manager" style="font-weight:600;display:block;">
								<?php esc_html_e( 'Hide JS-dependent blocks from the editor', 'mainframe' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Removes blocks that require front-end JavaScript (Navigation, Search, Query pagination, etc.) from the block inserter. In a headless setup those scripts never run on your consumer site. Existing content is unaffected — blocks are only hidden from the inserter. Can be fine-tuned per-block in Mainframe Settings.', 'mainframe' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<td style="width:24px;vertical-align:top;padding:6px 0;">
							<input type="checkbox" name="mf_login_slug_enable" id="mf_login_slug_enable" value="1">
						</td>
						<td style="padding:4px 0 12px 10px;">
							<label for="mf_login_slug_enable" style="font-weight:600;display:block;">
								<?php esc_html_e( 'Set a custom login URL slug', 'mainframe' ); ?>
							</label>
							<div style="margin-top:6px;">
								<input
									type="text"
									name="mf_login_slug"
									id="mf_login_slug"
									class="regular-text"
									placeholder="e.g. my-login"
									style="max-width:220px;"
								>
							</div>
							<p class="description">
								<?php esc_html_e( 'Blocks /wp-login.php and serves login at your chosen slug. Leave blank to keep /wp-login.php active.', 'mainframe' ); ?>
							</p>
						</td>
					</tr>

				</tbody>
			</table>

			<div style="margin-top:8px;">
				<?php submit_button( __( 'Apply Headless Defaults', 'mainframe' ), 'primary', 'submit', false ); ?>
				<span style="display:inline-block;margin-left:12px;color:#757575;font-size:13px;vertical-align:middle;">
					<?php esc_html_e( 'All settings are reversible from this page.', 'mainframe' ); ?>
				</span>
			</div>
		</form>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// admin-post handler — apply headless defaults
// ---------------------------------------------------------------------------

add_action( 'admin_post_mainframe_apply_headless', 'mainframe_handle_apply_headless' );
/**
 * Process the "Apply Headless Defaults" form submission.
 *
 * Each setting is only applied when its checkbox was ticked, so the user
 * retains full control over exactly which changes are made.
 */
function mainframe_handle_apply_headless(): void {
	check_admin_referer( 'mainframe_apply_headless' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'mainframe' ), 403 );
	}

	// Redirect all public routes to home by default.
	if ( ! empty( $_POST['mf_route_behavior'] ) ) {
		update_option( 'mainframe_default_route_behavior', 'redirect' );
	}

	// Discourage search engine indexing.
	if ( ! empty( $_POST['mf_blog_public'] ) ) {
		update_option( 'blog_public', 0 );
	}

	// Close comments and pingbacks on new posts.
	if ( ! empty( $_POST['mf_comments'] ) ) {
		update_option( 'default_pingback_flag',  0 );
		update_option( 'default_ping_status',    'closed' );
		update_option( 'default_comment_status', 'closed' );
	}

	// Flat upload folder structure.
	if ( ! empty( $_POST['mf_uploads'] ) ) {
		mainframe_apply_upload_defaults();
	}

	// Block Manager — hide JS-dependent blocks from the inserter.
	// The block manager is on by default; only write to the DB when the admin
	// explicitly opts out during onboarding.
	if ( empty( $_POST['mf_block_manager'] ) ) {
		update_option( 'mainframe_block_manager_enabled', false );
	}

	// Custom login slug.
	if ( ! empty( $_POST['mf_login_slug_enable'] ) && ! empty( $_POST['mf_login_slug'] ) ) {
		$raw      = sanitize_title_with_dashes( wp_unslash( (string) $_POST['mf_login_slug'] ), '', 'save' );
		$reserved = [ 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login', 'wp-signup', 'wp-activate', 'feed', 'sitemap' ];
		if ( $raw && ! in_array( $raw, $reserved, true ) ) {
			update_option( 'mainframe_login_slug', $raw );
		}
	}

	update_option( 'mainframe_setup_complete',     '1', false );
	update_option( 'mainframe_onboarding_pending', '0', false );

	wp_safe_redirect(
		add_query_arg(
			[ 'page' => 'mainframe-settings', 'mainframe_setup' => 'done' ],
			admin_url( 'themes.php' )
		)
	);
	exit;
}

// ---------------------------------------------------------------------------
// Helper — apply flat upload folder structure
// ---------------------------------------------------------------------------

/**
 * Disable year/month upload subfolders if no year-based folders already exist.
 *
 * Called from the onboarding apply handler. Safe to call on existing sites —
 * skips automatically if year-based directories are detected.
 */
function mainframe_apply_upload_defaults(): void {
	$upload_dir = wp_upload_dir();
	$base       = $upload_dir['basedir'];

	if ( is_dir( $base ) ) {
		$entries = (array) scandir( $base );
		foreach ( $entries as $entry ) {
			if ( preg_match( '/^\d{4}$/', $entry ) && is_dir( $base . DIRECTORY_SEPARATOR . $entry ) ) {
				return; // Year/month structure already in use — leave it alone.
			}
		}
	}

	update_option( 'uploads_use_yearmonth_folders', 0 );
}

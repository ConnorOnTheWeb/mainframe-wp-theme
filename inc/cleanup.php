<?php
/**
 * Mainframe Theme — Cleanup & Security
 *
 * Removes unnecessary output from wp_head(): emoji scripts, oEmbed discovery
 * links, the generator meta tag, and the Windows Live Writer manifest link.
 * Disables XML-RPC. Suppresses the WordPress version string from all output.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'mainframe_cleanup_head' );
/**
 * Remove unnecessary items from wp_head().
 *
 * Strips emoji scripts, oEmbed discovery links, the generator meta tag,
 * the Windows Live Writer manifest link, and the RSD link. None of these
 * serve a purpose on a headless/API-only WordPress install.
 */
function mainframe_cleanup_head(): void {
	// Emoji — frontend detection script and inline styles.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	// oEmbed — discovery <link> tags and the inline embed host script.
	// Note: the REST oEmbed endpoint is intentionally left intact.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );

	// Generator meta tag — exposes WordPress version to the public.
	remove_action( 'wp_head', 'wp_generator' );

	// Windows Live Writer manifest link.
	remove_action( 'wp_head', 'wlwmanifest_link' );

	// Really Simple Discovery link (used by blog clients, not needed here).
	remove_action( 'wp_head', 'rsd_link' );
}

add_filter( 'the_generator', '__return_empty_string' );
/**
 * Strip the WordPress version string from all generator contexts (feeds,
 * OPML, etc.) as an additional precaution beyond removing wp_generator.
 *
 * __return_empty_string is a native WP helper — no custom function needed.
 */

add_filter( 'xmlrpc_enabled', '__return_false' );
/**
 * Disable XML-RPC entirely.
 *
 * XML-RPC is a legacy protocol that is a common attack surface (brute-force,
 * DDoS amplification). A headless theme has no use for it; the REST API
 * covers all legitimate remote-access needs.
 *
 * __return_false is a native WP helper — no custom function needed.
 */

add_filter( 'wp_headers', 'mainframe_remove_x_pingback_header' );
/**
 * Remove the X-Pingback header that advertises the XML-RPC endpoint.
 *
 * Disabling XML-RPC via the filter above stops processing, but the
 * X-Pingback header is added separately and must be removed explicitly.
 *
 * @param array $headers Associative array of HTTP response headers.
 * @return array Headers with X-Pingback removed.
 */
function mainframe_remove_x_pingback_header( array $headers ): array {
	unset( $headers['X-Pingback'] );
	return $headers;
}

add_action( 'admin_menu', 'mainframe_remove_appearance_submenu_items' );
/**
 * Remove Appearance submenu items that are irrelevant to this theme.
 *
 * Patterns leads to the Site Editor pattern library, which only applies to
 * block themes. Menus are managed exclusively through the Customizer.
 */
function mainframe_remove_appearance_submenu_items(): void {
	remove_submenu_page( 'themes.php', 'site-editor.php?p=/pattern' );
	remove_submenu_page( 'themes.php', 'nav-menus.php' );
}

add_action( 'admin_menu', 'mainframe_remove_settings_submenu_items' );
/**
 * Remove Settings submenu pages that are not relevant to a headless setup.
 *
 * Reading and Discussion expose options that either don't apply (feed display,
 * homepage type) or should be locked down by default (pingbacks, comments).
 * Both pages remain accessible via direct URL if needed; this just removes
 * the nav entry to reduce noise.
 */
function mainframe_remove_settings_submenu_items(): void {
	remove_submenu_page( 'options-general.php', 'options-reading.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}

// ---------------------------------------------------------------------------
// Suppress automatic update notification emails (opt-in)
// ---------------------------------------------------------------------------

add_action( 'init', 'mainframe_maybe_suppress_update_emails' );
/**
 * Suppress all automatic-update notification emails when the admin has
 * opted in to this setting. Off by default — admins who want update emails
 * (especially for security releases) should leave this disabled.
 *
 * Covers: WordPress core, plugins, and themes.
 */
function mainframe_maybe_suppress_update_emails(): void {
	if ( ! get_option( 'mainframe_suppress_update_emails', false ) ) {
		return;
	}
	add_filter( 'auto_core_update_send_email',   '__return_false' );
	add_filter( 'auto_plugin_update_send_email', '__return_false' );
	add_filter( 'auto_theme_update_send_email',  '__return_false' );
}

// ---------------------------------------------------------------------------
// RSS feed suppression (opt-in)
// ---------------------------------------------------------------------------

add_action( 'init', 'mainframe_maybe_suppress_feeds' );
/**
 * Redirect all RSS/Atom feed URLs to the home page when the admin has opted
 * in to feed suppression. Off by default — some setups consume WP feeds
 * directly or run newsletter tools that depend on them.
 *
 * Covers the global /feed/ endpoint and all per-post/archive variants.
 */
function mainframe_maybe_suppress_feeds(): void {
	if ( ! get_option( 'mainframe_suppress_feeds', false ) ) {
		return;
	}
	add_action( 'do_feed',          'mainframe_redirect_feed', 1 );
	add_action( 'do_feed_rdf',      'mainframe_redirect_feed', 1 );
	add_action( 'do_feed_rss',      'mainframe_redirect_feed', 1 );
	add_action( 'do_feed_rss2',     'mainframe_redirect_feed', 1 );
	add_action( 'do_feed_atom',     'mainframe_redirect_feed', 1 );
	add_action( 'do_feed_rss2_comments', 'mainframe_redirect_feed', 1 );
	add_action( 'do_feed_atom_comments', 'mainframe_redirect_feed', 1 );
	// Also strip the auto-inserted <link rel="alternate"> tags from wp_head.
	remove_action( 'wp_head', 'feed_links',       2 );
	remove_action( 'wp_head', 'feed_links_extra',  3 );
}

/**
 * Send visitors to the home page when a feed URL is requested.
 *
 * @internal Called from the do_feed_* action hooks.
 */
function mainframe_redirect_feed(): void {
	wp_redirect( home_url( '/' ), 301 );
	exit;
}

// ---------------------------------------------------------------------------
// Sitemap suppression (opt-in)
// ---------------------------------------------------------------------------

add_filter( 'wp_sitemaps_enabled', 'mainframe_maybe_suppress_sitemap' );
/**
 * Disable the built-in WordPress sitemap when the admin has opted in.
 *
 * The consuming frontend should own /sitemap.xml. Leaving WP's version
 * active risks duplicate indexing and confusion with search engines.
 *
 * @param bool $enabled Whether the sitemap is enabled.
 * @return bool
 */
function mainframe_maybe_suppress_sitemap( bool $enabled ): bool {
	if ( get_option( 'mainframe_suppress_sitemap', false ) ) {
		return false;
	}
	return $enabled;
}

add_action( 'switch_theme', 'mainframe_cleanup_on_deactivation' );
/**
 * Delete all Mainframe options when the user switches away from this theme.
 *
 * Prevents orphaned wp_options rows if the theme is deactivated or deleted.
 * User-visible settings are included so the database is left fully clean.
 * Re-activating the theme will trigger onboarding again if setup was never
 * completed (the mainframe_setup_complete flag is also deleted).
 */
function mainframe_cleanup_on_deactivation(): void {
	$options = [
		'mainframe_redirect_type',
		'mainframe_404_behavior',
		'mainframe_login_slug',
		'mainframe_cors_origin',
		'mainframe_default_route_behavior',
		'mainframe_default_featured_image_url',
		'mainframe_deploy_hook_url',
		'mainframe_deploy_hook_secret',
		'mainframe_onboarding_pending',
		'mainframe_setup_complete',
		'mainframe_suppress_update_emails',
		'mainframe_suppress_feeds',
		'mainframe_suppress_sitemap',
		'mainframe_frontend_url',
		'mainframe_frontend_posts_base',
		'mainframe_block_manager_enabled',
	];
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	delete_option( 'mainframe_block_overrides' );
	delete_transient( MAINFRAME_UPDATE_CACHE_KEY );
}

// ---------------------------------------------------------------------------
// Robots / sitemap hardening — honour the site visibility setting
// ---------------------------------------------------------------------------

add_action( 'send_headers', 'mainframe_maybe_add_noindex_header' );
/**
 * Add X-Robots-Tag: noindex, nofollow when the site is set to private.
 *
 * WordPress sets blog_public = 0 when the admin chooses "Discourage search
 * engines". On a headless install the public frontend is a separate app,
 * so the WP frontend templates should reinforce this with an HTTP header
 * rather than relying solely on the meta robots tag that live inside
 * individual templates.
 *
 * Only fires on public-facing requests — skips admin, REST API, and cron.
 */
function mainframe_maybe_add_noindex_header(): void {
	if ( get_option( 'blog_public' ) ) {
		return; // Site is public — do not add noindex.
	}
	if ( is_admin() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return;
	}
	header( 'X-Robots-Tag: noindex, nofollow', true );
}

add_filter( 'wp_sitemaps_enabled', 'mainframe_disable_sitemap_when_private' );
/**
 * Disable the WordPress core sitemap when the site is set to private.
 *
 * A headless install that has opted into search-engine discouragement
 * should not broadcast a sitemap that spiders can discover and follow.
 *
 * @param bool $enabled Whether the sitemap feature is enabled.
 * @return bool False when blog_public = 0, unchanged otherwise.
 */
function mainframe_disable_sitemap_when_private( bool $enabled ): bool {
	if ( ! get_option( 'blog_public' ) ) {
		return false;
	}
	return $enabled;
}

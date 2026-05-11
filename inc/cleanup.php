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

add_action( 'after_switch_theme', 'mainframe_set_reading_discussion_defaults' );
/**
 * Set sensible Reading and Discussion defaults on theme activation.
 *
 * These are "new post" defaults and global flags — they do not retroactively
 * change existing posts, so switching to this theme on an existing site is
 * safe. Each option can still be changed per-post or via direct URL if needed.
 *
 * Reading:
 *   blog_public = 0 — discourage search engines. A headless backend should
 *   not be indexed directly; the consuming frontend handles SEO.
 *
 * Discussion:
 *   default_pingback_flag = 0 — don't attempt to notify linked blogs.
 *   default_ping_status   = closed — no pingbacks/trackbacks on new posts.
 *   default_comment_status = closed — no comments on new posts by default.
 *   Comments can still be enabled per-post and are fully accessible via
 *   the REST API when a post has comment_status = open.
 */
function mainframe_set_reading_discussion_defaults(): void {
	update_option( 'blog_public',              0 );
	update_option( 'default_pingback_flag',    0 );
	update_option( 'default_ping_status',      'closed' );
	update_option( 'default_comment_status',   'closed' );
}

add_action( 'after_switch_theme', 'mainframe_set_upload_defaults' );
/**
 * Set upload folder defaults on theme activation.
 *
 * Disables year/month subfolders so all uploads land in /wp-content/uploads/
 * directly — cleaner for a headless setup where paths are referenced via the
 * REST API.
 *
 * Safety: skipped if the uploads directory already contains year/month
 * subfolders (4-digit year directory), which would mean an existing site has
 * content organised that way. Changing mid-stream would split the folder
 * structure; the admin can update the setting manually in Settings > Media.
 */
function mainframe_set_upload_defaults(): void {
	$upload_dir = wp_upload_dir();
	$base       = $upload_dir['basedir'];

	// Check for any existing year-based subdirectory (e.g. 2023/, 2024/).
	if ( is_dir( $base ) ) {
		$entries = scandir( $base );
		foreach ( $entries as $entry ) {
			if ( preg_match( '/^\d{4}$/', $entry ) && is_dir( $base . DIRECTORY_SEPARATOR . $entry ) ) {
				// Year/month structure already in use — leave the setting alone.
				return;
			}
		}
	}

	update_option( 'uploads_use_yearmonth_folders', 0 );
}

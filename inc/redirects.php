<?php
/**
 * Mainframe Theme — Redirects
 *
 * Core redirect logic for the public-facing frontend. Handles 301/302
 * redirects to the home page for all non-home public routes based on
 * per-page meta settings and global theme defaults.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'mainframe_handle_redirects', 1 );
/**
 * Central redirect handler — fires on every front-end request.
 *
 * Evaluation order:
 *  1. Never redirect the real home page.
 *  2. 404 requests — honour the configured 404 behavior option.
 *  3. Non-singular routes (archives, search, author, etc.) — always redirect.
 *  4. Singular posts/pages — defer to the per-post meta value, falling back
 *     to the global default route behavior option.
 *
 * We hook at priority 1 so this fires before any template is loaded, and
 * before other plugins add their own template_redirect logic.
 */
function mainframe_handle_redirects(): void {
	// Never redirect the front page itself.
	// is_home() (the blog posts listing) is intentionally NOT excluded here:
	// in a headless theme it should redirect like any other archive-style
	// route. front-page.php covers every "home" scenario via its own template.
	if ( is_front_page() ) {
		return;
	}

	// Admin, REST API, and cron requests must not be redirected.
	//
	// REST_REQUEST is defined true by WordPress when serving a wp-json route.
	// wp_is_json_request() was intentionally NOT used here — it inspects the
	// Accept/Content-Type headers, which means any request that sends
	// "Accept: application/json" (e.g. a fetch() call to a page URL) would
	// bypass the redirect and expose content that should be hidden.
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return;
	}

	$redirect_code = mainframe_get_redirect_code();

	// --- 404 handling ---------------------------------------------------
	if ( is_404() ) {
		$behavior = get_option( 'mainframe_404_behavior', 'redirect' );
		if ( 'redirect' === $behavior ) {
			mainframe_redirect_home( $redirect_code );
		}
		// 'show' falls through; WordPress renders the 404 template normally.
		return;
	}

	// --- Non-singular routes --------------------------------------------
	// Archives, category/tag/custom taxonomy pages, date archives, author
	// pages, and search results always redirect — there is no per-page
	// toggle for these because they have no single canonical post object.
	if (
		is_archive()
		|| is_search()
		|| is_category()
		|| is_tag()
		|| is_author()
		|| is_date()
	) {
		mainframe_redirect_home( $redirect_code );
		return;
	}

	// --- Singular posts / pages -----------------------------------------
	if ( is_singular() ) {
		$post_id  = get_queried_object_id();
		$behavior = mainframe_get_post_behavior( $post_id );

		if ( 'redirect' === $behavior ) {
			mainframe_redirect_home( $redirect_code );
		}
		// 'show' falls through; WordPress renders the template normally.
		return;
	}

	// Anything not matched above (custom plugin rewrite rules, query vars,
	// or other non-standard routes) is intentionally left alone. WordPress
	// will render a 404 or the plugin will handle it — we do not redirect
	// blindly so plugin-managed pages remain accessible.
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return the HTTP redirect status code configured in theme options.
 *
 * @return int 301 or 302.
 */
function mainframe_get_redirect_code(): int {
	$type = get_option( 'mainframe_redirect_type', '301' );
	return '302' === $type ? 302 : 301;
}

/**
 * Redirect to the site home page and terminate execution.
 *
 * Uses wp_safe_redirect() so WordPress validates the destination URL
 * before sending the header. home_url('/') is always a safe local URL.
 *
 * @param int $status HTTP status code — 301 or 302.
 */
function mainframe_redirect_home( int $status = 301 ): void {
	wp_safe_redirect( home_url( '/' ), $status );
	exit;
}

/**
 * Determine the public behavior for a given post.
 *
 * Checks the per-post meta value first. If no explicit meta value is saved
 * (empty string, not set), falls back to the global default route behavior
 * option. Returns either 'show' or 'redirect'.
 *
 * @param int $post_id The post ID to check.
 * @return string 'show' or 'redirect'.
 */
function mainframe_get_post_behavior( int $post_id ): string {
	$meta = get_post_meta( $post_id, '_mainframe_route_behavior', true );

	if ( in_array( $meta, [ 'show', 'redirect' ], true ) ) {
		return $meta;
	}

	// No explicit meta — use the global default.
	$default = get_option( 'mainframe_default_route_behavior', 'show' );
	return 'redirect' === $default ? 'redirect' : 'show';
}

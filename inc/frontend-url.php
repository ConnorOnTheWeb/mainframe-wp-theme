<?php
/**
 * Mainframe Theme — Frontend App URL
 *
 * When a frontend app URL is configured (Mainframe Settings → Frontend App),
 * rewrites the editor "Preview" button and the admin bar "Visit Site" link
 * so they point to the consuming frontend rather than the WordPress backend.
 *
 * - Posts/CPTs: {frontend_url}/{posts_base}/{slug} (posts base path is optional)
 * - Pages: per-page frontend URL (set in the editor sidebar meta box) if
 *   provided, otherwise {frontend_url}/{slug}
 * - Admin bar "Visit Site": {frontend_url}
 *
 * No filtering is applied when the frontend URL setting is empty.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Preview link rewriting
// ---------------------------------------------------------------------------

add_filter( 'preview_post_link', 'mainframe_rewrite_preview_link', 10, 2 );
/**
 * Rewrite the post preview URL to point at the frontend app.
 *
 * @param string  $preview_link The default WordPress preview URL.
 * @param WP_Post $post         The post being previewed.
 * @return string Rewritten URL, or the original if no frontend URL is configured.
 */
function mainframe_rewrite_preview_link( string $preview_link, WP_Post $post ): string {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) ) {
		return $preview_link;
	}

	return mainframe_build_frontend_url( $post, $frontend_url );
}

// ---------------------------------------------------------------------------
// "View Post / Page" link rewriting (post editor toolbar)
// ---------------------------------------------------------------------------

add_filter( 'post_link',      'mainframe_rewrite_post_link', 10, 2 );
add_filter( 'page_link',      'mainframe_rewrite_page_link', 10, 2 );
add_filter( 'post_type_link', 'mainframe_rewrite_post_type_link', 10, 2 );
add_filter( 'attachment_link', 'mainframe_rewrite_attachment_link', 10, 2 );

/**
 * True when the current request is an admin or REST API context.
 *
 * The block editor fetches the post `link` field via the REST API where
 * is_admin() returns false. We want our rewrites to apply in both contexts.
 *
 * @return bool
 */
function mainframe_is_editorial_context(): bool {
	return is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
}

/**
 * Rewrite the "View Post" link for standard posts.
 *
 * @param string  $permalink The default permalink.
 * @param WP_Post $post      The post object.
 * @return string Rewritten URL, or the original if no frontend URL is configured.
 */
function mainframe_rewrite_post_link( string $permalink, WP_Post $post ): string {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) || ! mainframe_is_editorial_context() ) {
		return $permalink;
	}
	return mainframe_build_frontend_url( $post, $frontend_url );
}

/**
 * Rewrite the "View Page" link for pages.
 *
 * @param string $permalink The default permalink.
 * @param int    $post_id   The page ID.
 * @return string Rewritten URL, or the original if no frontend URL is configured.
 */
function mainframe_rewrite_page_link( string $permalink, int $post_id ): string {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) || ! mainframe_is_editorial_context() ) {
		return $permalink;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return $permalink;
	}
	return mainframe_build_frontend_url( $post, $frontend_url );
}

/**
 * Rewrite the media library "View" link to the direct file URL.
 *
 * WordPress builds attachment page URLs by appending the attachment slug to
 * the parent post's permalink. Since post_link runs in admin contexts, the
 * parent permalink gets rewritten to the frontend domain — producing a broken
 * URL like {frontend}/{parent-slug}/{attachment-slug}/ that doesn't exist.
 * Returning the direct file URL sidesteps this cascade; attachment pages
 * don't exist on a headless frontend anyway.
 *
 * @param string $link    The default attachment page URL.
 * @param int    $post_id The attachment post ID.
 * @return string Direct file URL, or the original link if unconfigured.
 */
function mainframe_rewrite_attachment_link( string $link, int $post_id ): string {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) || ! mainframe_is_editorial_context() ) {
		return $link;
	}
	$file_url = wp_get_attachment_url( $post_id );
	return $file_url ?: $link;
}

/**
 * Rewrite the "View Post" link for custom post types.
 *
 * @param string  $permalink The default permalink.
 * @param WP_Post $post      The post object.
 * @return string Rewritten URL, or the original if no frontend URL is configured.
 */
function mainframe_rewrite_post_type_link( string $permalink, WP_Post $post ): string {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) || ! mainframe_is_editorial_context() ) {
		return $permalink;
	}
	return mainframe_build_frontend_url( $post, $frontend_url );
}

// ---------------------------------------------------------------------------
// Admin bar "Visit Site" rewriting
// ---------------------------------------------------------------------------

add_action( 'admin_bar_menu', 'mainframe_rewrite_admin_bar_site_url', 999 );
/**
 * Rewrite the "Visit Site" admin bar link to point at the frontend app.
 *
 * Runs at priority 999 to ensure it fires after WordPress and any plugins
 * have already registered the node.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
 */
function mainframe_rewrite_admin_bar_site_url( WP_Admin_Bar $wp_admin_bar ): void {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) ) {
		return;
	}

	$node = $wp_admin_bar->get_node( 'site-name' );
	if ( $node ) {
		$node->href = esc_url( $frontend_url );
		$wp_admin_bar->add_node( (array) $node );
	}

	// Also update the view-site node used on some screens.
	$view_site = $wp_admin_bar->get_node( 'view-site' );
	if ( $view_site ) {
		$view_site->href = esc_url( $frontend_url );
		$wp_admin_bar->add_node( (array) $view_site );
	}
}

// ---------------------------------------------------------------------------
// Permalink template rewriting (block editor "View Post", permalink panel)
// ---------------------------------------------------------------------------

add_filter( 'get_sample_permalink', 'mainframe_rewrite_sample_permalink', 10, 5 );
/**
 * Rewrite the permalink template so the block editor's "View Post" button,
 * permalink panel, and post-publish panel all show the frontend app URL.
 *
 * Gutenberg builds URLs client-side from the `permalink_template` REST field
 * (produced by get_sample_permalink). Our post_link/page_link filters only
 * affect the `link` field — a different path — so without this filter the
 * editor shows the WordPress backend domain everywhere the template is used.
 *
 * get_sample_permalink() is only called in admin/REST contexts, so no extra
 * context guard is required here.
 *
 * @param array       $permalink [$template, $slug] from get_sample_permalink().
 * @param int         $post_id   Post ID.
 * @param string|null $title     Post title (unused).
 * @param string|null $name      Post slug (unused).
 * @param WP_Post     $post      Post object.
 * @return array Modified [$template, $slug].
 */
function mainframe_rewrite_sample_permalink( array $permalink, int $post_id, ?string $title, ?string $name, WP_Post $post ): array {
	$frontend_url = get_option( 'mainframe_frontend_url', '' );
	if ( empty( $frontend_url ) ) {
		return $permalink;
	}

	if ( 'page' === $post->post_type ) {
		$permalink[0] = trailingslashit( $frontend_url ) . '%pagename%';
	} else {
		$base         = sanitize_text_field( get_option( 'mainframe_frontend_posts_base', '' ) );
		$permalink[0] = $base
			? trailingslashit( $frontend_url ) . trailingslashit( $base ) . '%postname%'
			: trailingslashit( $frontend_url ) . '%postname%';
	}

	return $permalink;
}

// ---------------------------------------------------------------------------
// URL construction helper
// ---------------------------------------------------------------------------

/**
 * Build the frontend URL for a given post.
 *
 * Resolution order:
 *   1. Per-page meta (_mainframe_frontend_page_url) — exact URL for pages
 *      where the path is not predictable from the slug alone.
 *   2. Page: {frontend_url}/{slug}
 *   3. Post / CPT: {frontend_url}/{posts_base}/{slug}
 *
 * @param WP_Post $post         The post to build a URL for.
 * @param string  $frontend_url The configured frontend root URL (no trailing slash).
 * @return string The constructed frontend URL.
 */
function mainframe_build_frontend_url( WP_Post $post, string $frontend_url ): string {
	// 1. Per-page explicit URL (pages only, but we check all types for flexibility).
	$per_page = get_post_meta( $post->ID, '_mainframe_frontend_page_url', true );
	if ( ! empty( $per_page ) ) {
		return esc_url_raw( $per_page );
	}

	$slug = $post->post_name ?: sanitize_title( $post->post_title );

	// 2. Pages — flat slug off the frontend root.
	if ( 'page' === $post->post_type ) {
		return trailingslashit( $frontend_url ) . $slug;
	}

	// 3. Posts / CPTs — optional base path + slug.
	$base = sanitize_text_field( get_option( 'mainframe_frontend_posts_base', '' ) );
	if ( $base ) {
		return trailingslashit( $frontend_url ) . trailingslashit( $base ) . $slug;
	}

	return trailingslashit( $frontend_url ) . $slug;
}

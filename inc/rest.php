<?php
/**
 * Mainframe Theme — REST API & CORS
 *
 * Ensures the WordPress REST API is fully enabled and all registered post
 * types are exposed via show_in_rest. Adds a configurable CORS
 * Access-Control-Allow-Origin header filter so consuming apps can reach
 * the API without interference from the theme.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

add_action( 'registered_post_type', 'mainframe_expose_post_type_in_rest', 10, 2 );
/**
 * Force show_in_rest on every post type as it is registered.
 *
 * WordPress registers built-in post types (post, page, attachment) during
 * init. Plugins register their own types at various priorities. By hooking
 * into registered_post_type we catch all of them without needing to know
 * the full list in advance.
 *
 * Developers can opt a specific post type out by returning false from the
 * mainframe_expose_post_type_in_rest filter.
 *
 * @param string       $post_type        Post type slug.
 * @param WP_Post_Type $post_type_object Post type object (passed by handle;
 *                                       property changes affect the global).
 */
function mainframe_expose_post_type_in_rest( string $post_type, WP_Post_Type $post_type_object ): void {
	if ( $post_type_object->show_in_rest ) {
		return; // Already opted in — nothing to do.
	}

	/**
	 * Filter whether a post type should be force-exposed in the REST API.
	 *
	 * @param bool   $expose    Whether to expose the post type. Default true.
	 * @param string $post_type Post type slug.
	 */
	if ( ! apply_filters( 'mainframe_expose_post_type_in_rest', true, $post_type ) ) {
		return;
	}

	$post_type_object->show_in_rest = true;

	if ( empty( $post_type_object->rest_base ) ) {
		$post_type_object->rest_base = $post_type;
	}

	if ( empty( $post_type_object->rest_controller_class ) ) {
		$post_type_object->rest_controller_class = 'WP_REST_Posts_Controller';
	}
}

add_action( 'rest_api_init', 'mainframe_maybe_override_cors', 15 );
/**
 * Optionally replace WordPress's default CORS handler with a specific origin.
 *
 * When no origin is configured in Mainframe Settings the default WordPress
 * behaviour (Access-Control-Allow-Origin: *) is left untouched. When an
 * origin is saved, WordPress's own handler is removed and replaced so the
 * header reflects the configured value exactly.
 *
 * Priority 15 runs after core registers its own handler at priority 10,
 * ensuring our removal takes effect.
 */
function mainframe_maybe_override_cors(): void {
	$origin = trim( (string) get_option( 'mainframe_cors_origin', '' ) );

	if ( empty( $origin ) ) {
		return; // No origin configured — keep WordPress default (allow *).
	}

	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_pre_serve_request', 'mainframe_send_cors_headers' );
}

/**
 * Send CORS headers using the origin configured in Mainframe Settings.
 *
 * Hooked into rest_pre_serve_request only when an origin is configured.
 * Must return the $served value unchanged so WordPress continues to
 * handle sending the actual response body.
 *
 * @param bool $served Whether the request has already been served.
 * @return bool Unchanged $served value.
 */
function mainframe_send_cors_headers( bool $served ): bool {
	$origin = trim( (string) get_option( 'mainframe_cors_origin', '' ) );

	if ( empty( $origin ) ) {
		return $served;
	}

	header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
	header( 'Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE' );
	header( 'Access-Control-Allow-Credentials: true' );
	header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
	header( 'Vary: Origin' );

	// Handle OPTIONS preflight — browsers send this before credentialed requests.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
		header( 'Access-Control-Max-Age: 600' );
		status_header( 204 );
		exit;
	}

	return $served;
}

add_action( 'rest_api_init', 'mainframe_register_featured_media_url_field' );
/**
 * Add a `featured_media_url` field to all post type REST API responses.
 *
 * WordPress's built-in `featured_media` field returns only the attachment ID.
 * This field provides the full-size URL directly, removing the need for a
 * second request to /wp/v2/media/:id. It mirrors the behaviour of
 * Jetpack's `jetpack_featured_media_url` field without requiring the plugin.
 *
 * Returns an empty string when no featured image is set so consumers can
 * check truthiness without worrying about null handling.
 */
function mainframe_register_featured_media_url_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'featured_media_url',
		[
			'get_callback' => static function ( array $post ): string {
				$post_id = isset( $post['id'] ) ? (int) $post['id'] : 0;
				if ( ! $post_id ) {
					return '';
				}

				// 1. Theme's own external URL field — highest priority.
				$custom = (string) get_post_meta( $post_id, '_mainframe_featured_image_url', true );
				if ( '' !== $custom ) {
					return $custom;
				}

				// 2. FIFU (Featured Image from URL plugin) migration support.
				// FIFU stores its URL in fifu_image_url. When the plugin is
				// removed its meta remains in the DB; we read it here so
				// existing posts continue to return the correct URL without
				// any manual re-entry.
				$fifu = (string) get_post_meta( $post_id, 'fifu_image_url', true );
				if ( '' !== $fifu ) {
					return $fifu;
				}

				// 3. Standard WordPress attached featured image.
				$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
				if ( $thumbnail_id ) {
					$src = wp_get_attachment_image_url( $thumbnail_id, 'full' );
					if ( $src ) {
						return $src;
					}
				}

				// 4. Site-wide default featured image (Mainframe Settings).
				return (string) get_option( 'mainframe_default_featured_image_url', '' );
			},
			'schema' => [
				'description' => __( 'Full URL of the featured image, or empty string if none.', 'mainframe' ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

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
				$post_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
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

// ---------------------------------------------------------------------------
// featured_media_sizes — all registered sizes for the featured image
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_featured_media_sizes_field' );
/**
 * Add a `featured_media_sizes` field to all post type REST API responses.
 *
 * Returns an object keyed by size name (thumbnail, medium, large, full, …)
 * mapping to URL. For external URLs (custom field, FIFU, default option) only
 * the 'full' key is returned since no resized variants exist.
 * Returns an empty object when no featured image is available at all.
 */
function mainframe_register_featured_media_sizes_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'featured_media_sizes',
		[
			'get_callback' => static function ( array $post ): array {
				$post_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				if ( ! $post_id ) {
					return [];
				}

				// External URL sources — return only 'full', no resized variants.
				$custom = (string) get_post_meta( $post_id, '_mainframe_featured_image_url', true );
				if ( '' !== $custom ) {
					return [ 'full' => $custom ];
				}

				$fifu = (string) get_post_meta( $post_id, 'fifu_image_url', true );
				if ( '' !== $fifu ) {
					return [ 'full' => $fifu ];
				}

				// WordPress attached image — return all registered sizes.
				$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
				if ( $thumbnail_id ) {
					$sizes  = [];
					$labels = get_intermediate_image_sizes();
					$labels[] = 'full';
					foreach ( $labels as $size ) {
						$src = wp_get_attachment_image_url( $thumbnail_id, $size );
						if ( $src ) {
							$sizes[ $size ] = $src;
						}
					}
					if ( ! empty( $sizes ) ) {
						return $sizes;
					}
				}

				// Site-wide default — external URL, return only 'full'.
				$default = (string) get_option( 'mainframe_default_featured_image_url', '' );
				if ( '' !== $default ) {
					return [ 'full' => $default ];
				}

				return [];
			},
			'schema' => [
				'description'          => __( 'Map of image size name to URL for the featured image.', 'mainframe' ),
				'type'                 => 'object',
				'additionalProperties' => [ 'type' => 'string', 'format' => 'uri' ],
				'context'              => [ 'view', 'edit', 'embed' ],
				'readonly'             => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// featured_media_meta — alt text, title, caption, dimensions for the featured image
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_featured_media_meta_field' );
/**
 * Add a `featured_media_meta` field to all post type REST API responses.
 *
 * Returns alt text, title, caption, width, and height for the featured image.
 * For attached WordPress images all five values are populated from the
 * attachment object. For external URL sources (custom field, FIFU, default
 * option) the field returns null — no attachment metadata exists for those.
 *
 * This field is additive and does not affect featured_media_url or
 * featured_media_sizes.
 */
function mainframe_register_featured_media_meta_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'featured_media_meta',
		[
			'get_callback' => static function ( array $post ): ?array {
				$post_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				if ( ! $post_id ) {
					return null;
				}

				// External URL sources have no attachment metadata.
				$custom = (string) get_post_meta( $post_id, '_mainframe_featured_image_url', true );
				if ( '' !== $custom ) {
					return null;
				}

				$fifu = (string) get_post_meta( $post_id, 'fifu_image_url', true );
				if ( '' !== $fifu ) {
					return null;
				}

				// Attached featured image — return full metadata.
				$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
				if ( ! $thumbnail_id ) {
					return null;
				}

				$meta = wp_get_attachment_metadata( $thumbnail_id );

				return [
					'alt'     => (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
					'title'   => get_the_title( $thumbnail_id ),
					'caption' => wp_get_attachment_caption( $thumbnail_id ) ?: '',
					'width'   => isset( $meta['width'] ) ? (int) $meta['width'] : null,
					'height'  => isset( $meta['height'] ) ? (int) $meta['height'] : null,
				];
			},
			'schema' => [
				'description' => __( 'Alt text, title, caption, and dimensions for the featured image. Null for external URL images.', 'mainframe' ),
				'type'        => [ 'object', 'null' ],
				'properties'  => [
					'alt'     => [ 'type' => 'string' ],
					'title'   => [ 'type' => 'string' ],
					'caption' => [ 'type' => 'string' ],
					'width'   => [ 'type' => [ 'integer', 'null' ] ],
					'height'  => [ 'type' => [ 'integer', 'null' ] ],
				],
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// author_info — full author details on the post object
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_author_info_field' );
/**
 * Add an `author_info` field to all post type REST API responses.
 *
 * Returns name, slug, avatar URL, bio, and website URL so consuming apps
 * do not need a second request to /wp/v2/users/:id.
 * Returns null for post types that do not have an author or when no author
 * is assigned.
 */
function mainframe_register_author_info_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'author_info',
		[
			'get_callback' => static function ( array $post ): ?array {
				$author_id = isset( $post['author'] ) ? (int) $post['author'] : 0;
				if ( ! $author_id ) {
					return null;
				}

				$user = get_userdata( $author_id );
				if ( ! $user ) {
					return null;
				}

				return [
					'id'          => $author_id,
					'name'        => $user->display_name,
					'slug'        => $user->user_nicename,
					'avatar_url'  => get_avatar_url( $author_id, [ 'size' => 96 ] ) ?: '',
					'description' => get_the_author_meta( 'description', $author_id ),
					'url'         => $user->user_url,
				];
			},
			'schema' => [
				'description' => __( 'Full author details for the post.', 'mainframe' ),
				'type'        => [ 'object', 'null' ],
				'properties'  => [
					'id'          => [ 'type' => 'integer' ],
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'avatar_url'  => [ 'type' => 'string', 'format' => 'uri' ],
					'description' => [ 'type' => 'string' ],
					'url'         => [ 'type' => 'string' ],
				],
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// ancestor_ids — ordered list of ancestor post IDs for hierarchical types
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_ancestor_ids_field' );
/**
 * Add an `ancestor_ids` field to all post type REST API responses.
 *
 * Returns an array of ancestor IDs ordered nearest-to-root (immediate parent
 * first, root last). Returns an empty array for non-hierarchical post types
 * and for top-level posts with no ancestors.
 */
function mainframe_register_ancestor_ids_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'ancestor_ids',
		[
			'get_callback' => static function ( array $post ): array {
				$post_id   = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				if ( ! $post_id ) {
					return [];
				}
				$post_type = get_post_type( $post_id );
				if ( ! $post_type ) {
					return [];
				}
				$type_obj = get_post_type_object( $post_type );
				if ( ! $type_obj || ! $type_obj->hierarchical ) {
					return [];
				}
				return array_values( get_ancestors( $post_id, $post_type, 'post_type' ) );
			},
			'schema' => [
				'description' => __( 'Ancestor post IDs from immediate parent to root, or empty array.', 'mainframe' ),
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// categories_info — plain name and slug for each category on the post
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_categories_info_field' );
/**
 * Add a `categories_info` field to all post type REST API responses.
 *
 * Returns an array of objects with id, name, and slug for every category
 * term assigned to the post. Returns an empty array when no categories are
 * assigned or when the post type does not support the category taxonomy.
 */
function mainframe_register_categories_info_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'categories_info',
		[
			'get_callback' => static function ( array $post ): array {
				$post_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				if ( ! $post_id ) {
					return [];
				}
				$terms = get_the_terms( $post_id, 'category' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return [];
				}
				$result = [];
				foreach ( $terms as $term ) {
					$result[] = [
						'id'   => (int) $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					];
				}
				return $result;
			},
			'schema' => [
				'description' => __( 'Categories assigned to the post with id, name, and slug.', 'mainframe' ),
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'id'   => [ 'type' => 'integer' ],
						'name' => [ 'type' => 'string' ],
						'slug' => [ 'type' => 'string' ],
					],
				],
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// tags_info — tag details on the post object
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_tags_info_field' );
/**
 * Add a `tags_info` field to all post type REST API responses.
 *
 * Returns an array of objects with id, name, and slug for every post_tag
 * term assigned to the post. Returns an empty array when no tags are
 * assigned or when the post type does not support the post_tag taxonomy.
 */
function mainframe_register_tags_info_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'tags_info',
		[
			'get_callback' => static function ( array $post ): array {
				$post_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				if ( ! $post_id ) {
					return [];
				}
				$terms = get_the_terms( $post_id, 'post_tag' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return [];
				}
				$result = [];
				foreach ( $terms as $term ) {
					$result[] = [
						'id'   => (int) $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					];
				}
				return $result;
			},
			'schema' => [
				'description' => __( 'Tags assigned to the post with id, name, and slug.', 'mainframe' ),
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'id'   => [ 'type' => 'integer' ],
						'name' => [ 'type' => 'string' ],
						'slug' => [ 'type' => 'string' ],
					],
				],
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// reading_time — estimated reading time in minutes
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_reading_time_field' );
/**
 * Add a `reading_time` field to all post type REST API responses.
 *
 * Strips HTML from the post content, counts words, and divides by 200 wpm.
 * Returns an integer of at least 1. Returns 0 for post types with no content.
 */
function mainframe_register_reading_time_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'reading_time',
		[
			'get_callback' => static function ( array $post ): int {
				$post_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				$raw     = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';
				if ( '' === $raw ) {
					return 0;
				}
				$text = '';
				foreach ( parse_blocks( $raw ) as $block ) {
					// Skip custom HTML blocks — they contain scripts, iframes, JSON-LD, etc.
					if ( 'core/html' === $block['blockName'] ) {
						continue;
					}
					$text .= ' ' . render_block( $block );
				}
				$words = str_word_count( wp_strip_all_tags( $text ) );
				return (int) max( 1, ceil( $words / 200 ) );
			},
			'schema' => [
				'description' => __( 'Estimated reading time in minutes, based on 200 words per minute.', 'mainframe' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

// ---------------------------------------------------------------------------
// frontend_link — canonical URL on the consuming frontend app
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_frontend_link_field' );
/**
 * Add a `frontend_link` field to all post type REST API responses.
 *
 * Starts from the WordPress permalink, swaps the WP home URL for the
 * configured Frontend URL, then passes the result through the
 * `mainframe_frontend_link` filter so consuming-app developers can
 * rewrite the path for any routing structure (e.g. adding a /blog/ prefix).
 */
function mainframe_register_frontend_link_field(): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ] );

	register_rest_field(
		array_values( $post_types ),
		'frontend_link',
		[
			'get_callback' => static function ( array $post ): string {
				$id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
				return $id ? mainframe_build_frontend_link( $id ) : '';
			},
			'schema' => [
				'description' => __( 'Canonical URL of the post on the consuming frontend app. Use the mainframe_frontend_link filter to adjust the path for your routing structure.', 'mainframe' ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => [ 'view', 'edit', 'embed' ],
				'readonly'    => true,
			],
		]
	);
}

/**
 * Build the frontend URL for a given post.
 *
 * 1. Gets the WordPress permalink.
 * 2. Replaces the WP home URL with the configured Frontend URL (domain swap).
 * 3. Passes the result through the `mainframe_frontend_link` filter so
 *    developers can rewrite the path for any routing structure.
 *
 * Example — adding a /blog/ prefix to posts:
 *
 *   add_filter( 'mainframe_frontend_link', function ( $url, $post_id, $post_type ) {
 *       if ( 'post' === $post_type ) {
 *           $slug = get_post_field( 'post_name', $post_id );
 *           return 'https://www.yoursite.com/blog/' . $slug;
 *       }
 *       return $url;
 *   }, 10, 3 );
 *
 * @param int $post_id Post ID.
 * @return string Frontend URL without trailing slash, or empty string if no permalink available.
 */
function mainframe_build_frontend_link( int $post_id ): string {
	$permalink = get_permalink( $post_id );
	if ( ! $permalink ) {
		return '';
	}

	$post_type = get_post_type( $post_id ) ?: '';

	/**
	 * Filter the frontend URL for a post.
	 *
	 * Use this hook to map the WordPress permalink to the correct URL on
	 * your consuming frontend app — for example, prefixing posts with
	 * /blog/ or constructing a completely custom URL scheme.
	 *
	 * Return a URL without a trailing slash.
	 *
	 * @param string $url       The WordPress permalink (no trailing slash).
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type slug.
	 */
	return (string) apply_filters( 'mainframe_frontend_link', untrailingslashit( $permalink ), $post_id, $post_type );
}

// ---------------------------------------------------------------------------
// Site data endpoint — /wp-json/mainframe/v1/site
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'mainframe_register_site_endpoint' );
/**
 * Register the /mainframe/v1/site read-only endpoint.
 *
 * Returns a one-call summary of the site's public identity and all nav menu
 * link cards, eliminating the need for multiple bootstrap requests from the
 * consuming frontend app.
 */
function mainframe_register_site_endpoint(): void {
	register_rest_route(
		'mainframe/v1',
		'/site',
		[
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'mainframe_site_endpoint_callback',
			'permission_callback' => '__return_true',
			'schema'              => 'mainframe_site_endpoint_schema',
		]
	);
}

/**
 * Return the JSON schema for the /mainframe/v1/site response.
 *
 * Registering a schema callback makes the structure machine-readable and
 * allows the REST API Reference admin page to display it without hardcoding.
 *
 * @return array JSON Schema array describing the site endpoint response.
 */
function mainframe_site_endpoint_schema(): array {
	return [
		'$schema'    => 'http://json-schema.org/draft-04/schema#',
		'title'      => 'mainframe-site',
		'type'       => 'object',
		'properties' => [
			'name'        => [
				'description' => __( 'Site title.', 'mainframe' ),
				'type'        => 'string',
				'context'     => [ 'view' ],
			],
			'description' => [
				'description' => __( 'Site tagline.', 'mainframe' ),
				'type'        => 'string',
				'context'     => [ 'view' ],
			],
			'url'         => [
				'description' => __( 'Site home URL.', 'mainframe' ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => [ 'view' ],
			],
			'logo_url'    => [
				'description' => __( 'Full URL of the site logo, or empty string if none.', 'mainframe' ),
				'type'        => 'string',
				'context'     => [ 'view' ],
			],
			'logo_id'     => [
				'description' => __( 'Attachment ID of the site logo, or null if none.', 'mainframe' ),
				'type'        => [ 'integer', 'null' ],
				'context'     => [ 'view' ],
			],
			'menus'       => [
				'description' => __( 'All registered nav menus in creation order, top-level items only.', 'mainframe' ),
				'type'        => 'array',
				'context'     => [ 'view' ],
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer', 'description' => __( 'Menu term ID.', 'mainframe' ) ],
						'name'  => [ 'type' => 'string',  'description' => __( 'Menu name.', 'mainframe' ) ],
						'items' => [
							'type'        => 'array',
							'description' => __( 'Top-level menu items.', 'mainframe' ),
							'items'       => [
								'type'       => 'object',
								'properties' => [
									'id'     => [ 'type' => 'integer', 'description' => __( 'Menu item post ID.', 'mainframe' ) ],
									'title'  => [ 'type' => 'string',  'description' => __( 'Menu item label.', 'mainframe' ) ],
									'url'    => [ 'type' => 'string', 'format' => 'uri', 'description' => __( 'Target URL.', 'mainframe' ) ],
									'target' => [ 'type' => 'string',  'description' => __( 'Link target attribute (_blank or empty).', 'mainframe' ) ],
								],
							],
						],
					],
				],
			],
		],
	];
}

/**
 * Callback for /mainframe/v1/site.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response Site identity and menu data.
 */
function mainframe_site_endpoint_callback( WP_REST_Request $request ): WP_REST_Response {
	// Site identity.
	$logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
	$logo_url = '';
	if ( $logo_id ) {
		$src      = wp_get_attachment_image_url( $logo_id, 'full' );
		$logo_url = $src ?: '';
	}

	// Nav menus — all menus in creation order, top-level items only.
	$menus_data = [];
	$all_menus  = wp_get_nav_menus( [ 'orderby' => 'term_id', 'order' => 'ASC' ] );

	if ( is_array( $all_menus ) ) {
		foreach ( $all_menus as $menu ) {
			$raw = wp_get_nav_menu_items( (int) $menu->term_id );
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$items = [];
			foreach ( $raw as $item ) {
				if ( '0' !== (string) $item->menu_item_parent ) {
					continue; // Skip sub-items — only flat custom links are used.
				}
				$items[] = [
					'id'     => (int) $item->ID,
					'title'  => $item->title,
					'url'    => $item->url,
					'target' => $item->target,
				];
			}
			$menus_data[] = [
				'id'    => (int) $menu->term_id,
				'name'  => $menu->name,
				'items' => $items,
			];
		}
	}

	return new WP_REST_Response(
		[
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url(),
			'logo_url'    => $logo_url,
			'logo_id'     => $logo_id ?: null,
			'menus'       => $menus_data,
		],
		200
	);
}

// ---------------------------------------------------------------------------
// Deploy webhook — fire on post publish/unpublish
// ---------------------------------------------------------------------------

add_action( 'transition_post_status', 'mainframe_maybe_trigger_deploy', 10, 3 );
/**
 * Trigger the configured deploy webhook when a post is published or
 * transitions out of publish status.
 *
 * A 10-second site-wide cooldown transient prevents flooding the endpoint
 * when multiple posts are saved in quick succession.
 *
 * The request is non-blocking so the webhook does not delay the admin save.
 * An optional HMAC-SHA256 signature is sent in X-Mainframe-Signature when a
 * secret is configured, allowing the receiving service to verify authenticity.
 *
 * @param string  $new_status The new post status.
 * @param string  $old_status The previous post status.
 * @param WP_Post $post       The post being saved.
 */
function mainframe_maybe_trigger_deploy( string $new_status, string $old_status, WP_Post $post ): void {
	// Only fire on publish transitions — entering or leaving publish.
	$relevant = ( 'publish' === $new_status || 'publish' === $old_status );
	if ( ! $relevant ) {
		return;
	}

	// Skip revisions and auto-drafts.
	if ( in_array( $post->post_type, [ 'revision', 'auto-draft' ], true ) ) {
		return;
	}

	$url = trim( (string) get_option( 'mainframe_deploy_hook_url', '' ) );
	if ( empty( $url ) ) {
		return;
	}

	// Rate-limit: one trigger per 10 seconds site-wide.
	$cooldown_key = 'mainframe_deploy_cooldown';
	if ( get_transient( $cooldown_key ) ) {
		return;
	}
	set_transient( $cooldown_key, '1', 10 );

	$payload = (string) wp_json_encode( [
		'event'     => 'publish' === $new_status ? 'published' : 'unpublished',
		'post_id'   => $post->ID,
		'post_type' => $post->post_type,
		'site_url'  => home_url(),
	] );

	$headers = [
		'Content-Type' => 'application/json',
		'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ) . '; Mainframe-Theme',
	];

	$secret = trim( (string) get_option( 'mainframe_deploy_hook_secret', '' ) );
	if ( ! empty( $secret ) ) {
		$headers['X-Mainframe-Signature'] = 'sha256=' . hash_hmac( 'sha256', $payload, $secret );
	}

	wp_safe_remote_post(
		$url,
		[
			'body'     => $payload,
			'headers'  => $headers,
			'timeout'  => 5,
			'blocking' => false, // Fire-and-forget — do not block the admin save.
		]
	);
}

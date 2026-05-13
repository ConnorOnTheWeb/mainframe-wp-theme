<?php
/**
 * Mainframe Theme — Per-Page Meta
 *
 * Registers and renders the per-page/post meta field that controls whether
 * a given piece of content shows its output or redirects to the home page.
 * Handles saving and sanitizing the meta value.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Meta box registration
// ---------------------------------------------------------------------------

add_action( 'add_meta_boxes', 'mainframe_register_route_meta_box' );
/**
 * Register the "Public Route Behavior" meta box on every public post type
 * that is not excluded from the admin UI.
 *
 * Built-in types 'attachment' and 'revision' are skipped — they have no
 * meaningful public URL in the context of this theme.
 */
function mainframe_register_route_meta_box(): void {
	$post_types = get_post_types( [ 'public' => true ], 'names' );

	foreach ( $post_types as $post_type ) {
		if ( in_array( $post_type, [ 'attachment', 'revision' ], true ) ) {
			continue;
		}

		add_meta_box(
			'mainframe_route_behavior',                       // ID
			__( 'Public Route Behavior', 'mainframe' ),       // Title
			'mainframe_render_route_meta_box',                // Render callback
			$post_type,                                       // Screen
			'side',                                           // Context
			'high'                                            // Priority — near the top of the sidebar
		);
	}
}

// ---------------------------------------------------------------------------
// Meta box render callback
// ---------------------------------------------------------------------------

/**
 * Render the "Public Route Behavior" meta box.
 *
 * Displays a radio group: "Use site default", "Show content", "Redirect to home".
 * The "Use site default" option means no explicit meta is stored, so the
 * global option in Mainframe Settings governs behavior.
 *
 * @param WP_Post $post The current post object.
 */
function mainframe_render_route_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'mainframe_save_route_behavior_' . $post->ID, 'mainframe_route_behavior_nonce' );

	$saved   = get_post_meta( $post->ID, '_mainframe_route_behavior', true );
	$default = get_option( 'mainframe_default_route_behavior', 'show' );

	// Determine which label to show for the site default.
	$default_label = 'redirect' === $default
		? __( 'Redirect to home (site default)', 'mainframe' )
		: __( 'Show content (site default)', 'mainframe' );

	// When $saved is empty the post has no explicit setting — "site default" is selected.
	$selected = in_array( $saved, [ 'show', 'redirect' ], true ) ? $saved : '';
	?>
	<fieldset style="margin: 0; padding: 0; border: 0;">
		<p style="margin-top: 0;">
			<label style="display: block; margin-bottom: 6px;">
				<input
					type="radio"
					name="mainframe_route_behavior"
					value=""
					<?php checked( $selected, '' ); ?>
				>
				<?php echo esc_html( $default_label ); ?>
			</label>
			<label style="display: block; margin-bottom: 6px;">
				<input
					type="radio"
					name="mainframe_route_behavior"
					value="show"
					<?php checked( $selected, 'show' ); ?>
				>
				<?php esc_html_e( 'Show content', 'mainframe' ); ?>
			</label>
			<label style="display: block;">
				<input
					type="radio"
					name="mainframe_route_behavior"
					value="redirect"
					<?php checked( $selected, 'redirect' ); ?>
				>
				<?php esc_html_e( 'Redirect to home', 'mainframe' ); ?>
			</label>
		</p>
	</fieldset>
	<?php
}

// ---------------------------------------------------------------------------
// Save handler
// ---------------------------------------------------------------------------

add_action( 'save_post', 'mainframe_save_route_meta_box', 10, 2 );
/**
 * Save the route behavior meta value when a post is saved.
 *
 * Security checks performed in order:
 *  1. Nonce verification — proves the request originated from our meta box.
 *  2. Autosave bail-out — we never save during autosave.
 *  3. Post revision bail-out — revisions do not have public URLs.
 *  4. Capability check — user must be able to edit the post.
 *
 * An empty string means "use the site default" and is valid; in that case
 * the meta row is deleted so get_post_meta() returns '' cleanly.
 *
 * @param int     $post_id Post ID being saved.
 * @param WP_Post $post    Post object being saved.
 */
function mainframe_save_route_meta_box( int $post_id, WP_Post $post ): void {
	// 1. Nonce check.
	$nonce = isset( $_POST['mainframe_route_behavior_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['mainframe_route_behavior_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'mainframe_save_route_behavior_' . $post_id ) ) {
		return;
	}

	// 2. Skip autosaves.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// 3. Skip revisions.
	if ( 'revision' === $post->post_type ) {
		return;
	}

	// 4. Capability check.
	$post_type_object = get_post_type_object( $post->post_type );
	if (
		! $post_type_object
		|| ! current_user_can( $post_type_object->cap->edit_post, $post_id )
	) {
		return;
	}

	// Sanitize and validate the submitted value.
	$raw   = isset( $_POST['mainframe_route_behavior'] ) ? sanitize_key( $_POST['mainframe_route_behavior'] ) : '';
	$value = in_array( $raw, [ 'show', 'redirect' ], true ) ? $raw : '';

	if ( '' === $value ) {
		// Empty means "use site default" — remove the meta row entirely.
		delete_post_meta( $post_id, '_mainframe_route_behavior' );
	} else {
		update_post_meta( $post_id, '_mainframe_route_behavior', $value );
	}
}

// ---------------------------------------------------------------------------
// Register meta for REST API visibility
// ---------------------------------------------------------------------------

add_action( 'init', 'mainframe_register_route_behavior_meta' );
/**
 * Register the _mainframe_route_behavior post meta so it is accessible
 * via the REST API for any consuming app that needs to know the setting.
 *
 * Registered for all post types ('') with show_in_rest enabled.
 */
function mainframe_register_route_behavior_meta(): void {
	register_post_meta(
		'',  // Empty string = all post types.
		'_mainframe_route_behavior',
		[
			'type'          => 'string',
			'description'   => 'Controls whether this post shows its content or redirects to home on the public frontend.',
			'single'        => true,
			'default'       => '',
			'show_in_rest'  => true,
			'auth_callback' => 'mainframe_route_meta_auth_callback',
		]
	);
}
/**
 * Authorization callback for _mainframe_route_behavior post meta.
 *
 * Restricts REST API writes to users who have permission to edit the
 * specific post. Named function (not closure) so it can be overridden
 * via remove_filter if needed.
 *
 * @param bool   $allowed  Whether the user is allowed to change the meta.
 * @param string $meta_key The meta key being checked.
 * @param int    $post_id  The post ID the meta belongs to.
 * @return bool True if the current user can edit the post.
 */
function mainframe_route_meta_auth_callback( bool $allowed, string $meta_key, int $post_id ): bool {
	return current_user_can( 'edit_post', $post_id );
}

// ---------------------------------------------------------------------------
// Featured Image URL — meta registration, meta box, and block editor panel
// ---------------------------------------------------------------------------

add_action( 'init', 'mainframe_register_featured_image_url_meta' );
/**
 * Register the _mainframe_featured_image_url post meta for all post types.
 *
 * When a value is stored here the REST API `featured_media_url` field will
 * return it instead of the attached featured image URL.
 */
function mainframe_register_featured_image_url_meta(): void {
	register_post_meta(
		'',
		'_mainframe_featured_image_url',
		[
			'type'              => 'string',
			'description'       => 'External featured image URL that overrides the attached featured image in REST API responses.',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => 'mainframe_route_meta_auth_callback',
		]
	);

	// Register FIFU's meta key as read-only when the FIFU plugin is not active.
	// This makes the value visible to the block editor so the Featured Image
	// panel can fall back to it for existing posts migrating away from FIFU.
	if ( ! function_exists( 'fifu_dev_set_image' ) ) {
		register_post_meta(
			'',
			'fifu_image_url',
			[
				'type'              => 'string',
				'description'       => 'FIFU plugin featured image URL (migration compatibility, read-only).',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => '__return_false', // Read-only — never writable via REST.
			]
		);
	}
}

add_action( 'add_meta_boxes', 'mainframe_register_featured_image_url_meta_box' );
/**
 * Register the "Featured Image URL" meta box for the classic editor.
 *
 * Placed in the side column below the built-in Featured Image box.
 * Hidden automatically when the block editor is active (it injects its own
 * sidebar panel via the registered block editor plugin script).
 */
function mainframe_register_featured_image_url_meta_box(): void {
	// Skip if the block editor is active — the JS sidebar panel handles it there.
	//
	// did_action('enqueue_block_editor_assets') cannot be used here: WordPress
	// fires add_meta_boxes at the very top of edit-form-advanced.php, before any
	// script enqueuing, so that counter is always 0 at this point regardless of
	// which editor is active.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->is_block_editor() ) {
		return;
	}

	$post_types = get_post_types( [ 'public' => true ], 'names' );

	foreach ( $post_types as $post_type ) {
		if ( in_array( $post_type, [ 'attachment', 'revision' ], true ) ) {
			continue;
		}

		add_meta_box(
			'mainframe_featured_image_url',
			__( 'Featured Image URL', 'mainframe' ),
			'mainframe_render_featured_image_url_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}

/**
 * Render the "Featured Image URL" classic editor meta box.
 *
 * @param WP_Post $post The current post object.
 */
function mainframe_render_featured_image_url_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'mainframe_save_featured_image_url_' . $post->ID, 'mainframe_featured_image_url_nonce' );
	$url = (string) get_post_meta( $post->ID, '_mainframe_featured_image_url', true );
	?>
	<p style="margin-top:0;">
		<label for="mainframe_featured_image_url" style="display:block;margin-bottom:4px;font-weight:600;">
			<?php esc_html_e( 'External image URL', 'mainframe' ); ?>
		</label>
		<input
			type="url"
			id="mainframe_featured_image_url"
			name="mainframe_featured_image_url"
			value="<?php echo esc_attr( $url ); ?>"
			placeholder="https://"
			style="width:100%;"
		>
		<span style="display:block;margin-top:4px;color:#757575;font-size:12px;">
			<?php esc_html_e( 'Overrides the attached featured image in the REST API response.', 'mainframe' ); ?>
		</span>
	</p>
	<?php if ( $url ) : ?>
		<img src="<?php echo esc_url( $url ); ?>" alt="" style="max-width:100%;display:block;margin-top:6px;border-radius:2px;">
	<?php endif; ?>
	<?php
}

add_action( 'save_post', 'mainframe_save_featured_image_url_meta_box', 10, 2 );
/**
 * Save the featured image URL from the classic editor meta box.
 *
 * @param int     $post_id Post ID being saved.
 * @param WP_Post $post    Post object being saved.
 */
function mainframe_save_featured_image_url_meta_box( int $post_id, WP_Post $post ): void {
	$nonce = isset( $_POST['mainframe_featured_image_url_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['mainframe_featured_image_url_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'mainframe_save_featured_image_url_' . $post_id ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'revision' === $post->post_type ) {
		return;
	}

	$post_type_object = get_post_type_object( $post->post_type );
	if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['mainframe_featured_image_url'] ) ) {
		return;
	}

	$url = esc_url_raw( wp_unslash( $_POST['mainframe_featured_image_url'] ) );

	if ( '' === $url ) {
		delete_post_meta( $post_id, '_mainframe_featured_image_url' );
	} else {
		update_post_meta( $post_id, '_mainframe_featured_image_url', $url );
	}
}

add_action( 'enqueue_block_editor_assets', 'mainframe_enqueue_featured_image_url_editor_script' );
/**
 * Enqueue the block editor sidebar plugin for the Featured Image URL field.
 */
function mainframe_enqueue_featured_image_url_editor_script(): void {
	wp_enqueue_script(
		'mainframe-featured-image-url',
		get_template_directory_uri() . '/assets/js/featured-image-url.js',
		[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-hooks', 'wp-compose' ],
		filemtime( get_template_directory() . '/assets/js/featured-image-url.js' ),
		true
	);

	wp_set_script_translations( 'mainframe-featured-image-url', 'mainframe', get_template_directory() . '/languages' );

	// Remove the Discussion panel from the block editor sidebar — comment and
	// ping status are controlled by site defaults and REST API, not the editor UI.
	wp_add_inline_script(
		'mainframe-featured-image-url',
		'wp.domReady( function () { wp.data.dispatch( "core/edit-post" ).removeEditorPanel( "discussion-panel" ); } );'
	);

	// Hide the permalink display in the block editor. The WP URL is not the
	// consuming app's URL and has no meaningful value for editors.
	// .editor-post-url__permalink — slug panel permalink row (post/page editor)
	// .edit-post-header-permalink  — legacy header permalink bar (older WP)
	wp_add_inline_style(
		'wp-edit-post',
		'.editor-post-url__permalink, .edit-post-header-permalink { display: none !important; }'
	);
}

add_filter( 'preview_post_link', '__return_empty_string' );
/**
 * Remove the Preview button from the classic editor.
 *
 * The WordPress frontend is not the consuming app on a headless install.
 * Returning an empty string causes the classic editor to hide the button
 * entirely. The block editor preview is handled via JS in featured-image-url.js.
 *
 * __return_empty_string is a native WP helper — no custom function needed.
 */
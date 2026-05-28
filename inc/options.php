<?php
/**
 * Mainframe Theme — Options
 *
 * Registers the Appearance > Mainframe Settings admin page. Exposes theme-wide
 * controls: redirect type (301/302), 404 behavior, custom login slug, and the
 * default public route behavior for content without an explicit per-page setting.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Admin menu registration
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'mainframe_add_settings_page' );
/**
 * Register the Mainframe Settings page under the Appearance menu.
 */
function mainframe_add_settings_page(): void {
	add_theme_page(
		__( 'Mainframe Settings', 'mainframe' ),  // Page <title>
		__( 'Mainframe Settings', 'mainframe' ),  // Menu label
		'manage_options',                          // Required capability
		'mainframe-settings',                      // Menu slug
		'mainframe_render_settings_page'           // Render callback
	);
}

add_action( 'admin_init', 'mainframe_handle_check_updates' );
/**
 * Handle the "Check for Updates" button on the Mainframe Settings page.
 *
 * Deletes the cached GitHub release transient so the next update check
 * fetches fresh data from the API, then redirects back to the settings page
 * with a success notice.
 */
function mainframe_handle_check_updates(): void {
	if (
		! isset( $_GET['mainframe_check_updates'] ) ||
		! current_user_can( 'manage_options' ) ||
		! check_admin_referer( 'mainframe_check_updates' )
	) {
		return;
	}

	delete_transient( MAINFRAME_UPDATE_CACHE_KEY );

	// Force WordPress to re-check theme updates immediately.
	delete_site_transient( 'update_themes' );

	wp_safe_redirect( add_query_arg(
		[ 'page' => 'mainframe-settings', 'mainframe_updated' => 'cache_cleared' ],
		admin_url( 'themes.php' )
	) );
	exit;
}

// ---------------------------------------------------------------------------
// Settings API registration
// ---------------------------------------------------------------------------

add_action( 'admin_init', 'mainframe_register_settings' );
/**
 * Register option fields, sections, and settings with the Settings API.
 *
 * All options are stored as individual entries in wp_options so that each
 * can be retrieved cheaply with get_option() from any include file.
 */
function mainframe_register_settings(): void {

	// ------------------------------------------------------------------
	// Register each option with sanitization callbacks.
	// ------------------------------------------------------------------

	register_setting(
		'mainframe_settings_group',
		'mainframe_redirect_type',
		[
			'type'              => 'string',
			'default'           => '301',
			'sanitize_callback' => 'mainframe_sanitize_redirect_type',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_404_behavior',
		[
			'type'              => 'string',
			'default'           => 'redirect',
			'sanitize_callback' => 'mainframe_sanitize_404_behavior',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_login_slug',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'mainframe_sanitize_login_slug',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_cors_origin',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'mainframe_sanitize_cors_origin',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_default_route_behavior',
		[
			'type'              => 'string',
			'default'           => 'show',
			'sanitize_callback' => 'mainframe_sanitize_default_route_behavior',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_default_featured_image_url',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'mainframe_sanitize_default_featured_image_url',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_deploy_hook_url',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'mainframe_sanitize_deploy_hook_url',
		]
	);

	register_setting(
		'mainframe_settings_group',
		'mainframe_deploy_hook_secret',
		[
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		]
	);

	// ------------------------------------------------------------------
	// Section: Public Frontend
	// ------------------------------------------------------------------

	add_settings_section(
		'mainframe_section_frontend',
		__( 'Public Frontend', 'mainframe' ),
		'__return_false',
		'mainframe-settings'
	);

	add_settings_field(
		'mainframe_redirect_type',
		__( 'Redirect Type', 'mainframe' ),
		'mainframe_render_redirect_type_field',
		'mainframe-settings',
		'mainframe_section_frontend'
	);

	add_settings_field(
		'mainframe_404_behavior',
		__( '404 Behavior', 'mainframe' ),
		'mainframe_render_404_behavior_field',
		'mainframe-settings',
		'mainframe_section_frontend'
	);

	add_settings_field(
		'mainframe_default_route_behavior',
		__( 'Default Route Behavior', 'mainframe' ),
		'mainframe_render_default_route_behavior_field',
		'mainframe-settings',
		'mainframe_section_frontend'
	);

	// ------------------------------------------------------------------
	// Section: Security
	// ------------------------------------------------------------------

	add_settings_section(
		'mainframe_section_security',
		__( 'Security', 'mainframe' ),
		'__return_false',
		'mainframe-settings'
	);

	add_settings_field(
		'mainframe_login_slug',
		__( 'Custom Login URL', 'mainframe' ),
		'mainframe_render_login_slug_field',
		'mainframe-settings',
		'mainframe_section_security'
	);

	// ------------------------------------------------------------------
	// Section: REST API
	// ------------------------------------------------------------------

	add_settings_section(
		'mainframe_section_rest',
		__( 'REST API', 'mainframe' ),
		'__return_false',
		'mainframe-settings'
	);

	add_settings_field(
		'mainframe_cors_origin',
		__( 'CORS Origin', 'mainframe' ),
		'mainframe_render_cors_origin_field',
		'mainframe-settings',
		'mainframe_section_rest'
	);

	add_settings_field(
		'mainframe_default_featured_image_url',
		__( 'Default Featured Image', 'mainframe' ),
		'mainframe_render_default_featured_image_field',
		'mainframe-settings',
		'mainframe_section_rest'
	);

	add_settings_field(
		'mainframe_deploy_hook_url',
		__( 'Deploy Hook URL', 'mainframe' ),
		'mainframe_render_deploy_hook_url_field',
		'mainframe-settings',
		'mainframe_section_rest'
	);

	add_settings_field(
		'mainframe_deploy_hook_secret',
		__( 'Deploy Hook Secret', 'mainframe' ),
		'mainframe_render_deploy_hook_secret_field',
		'mainframe-settings',
		'mainframe_section_rest'
	);
}

// ---------------------------------------------------------------------------
// Field render callbacks
// ---------------------------------------------------------------------------

/**
 * Render the Redirect Type radio field (301 / 302).
 */
function mainframe_render_redirect_type_field(): void {
	$value = get_option( 'mainframe_redirect_type', '301' );
	?>
	<fieldset>
		<label>
			<input type="radio" name="mainframe_redirect_type" value="301" <?php checked( $value, '301' ); ?>>
			<?php esc_html_e( '301 — Permanent', 'mainframe' ); ?>
		</label><br>
		<label>
			<input type="radio" name="mainframe_redirect_type" value="302" <?php checked( $value, '302' ); ?>>
			<?php esc_html_e( '302 — Temporary', 'mainframe' ); ?>
		</label>
	</fieldset>
	<?php
}

/**
 * Render the 404 Behavior radio field.
 */
function mainframe_render_404_behavior_field(): void {
	$value = get_option( 'mainframe_404_behavior', 'redirect' );
	?>
	<fieldset>
		<label>
			<input type="radio" name="mainframe_404_behavior" value="redirect" <?php checked( $value, 'redirect' ); ?>>
			<?php esc_html_e( 'Redirect to home page', 'mainframe' ); ?>
		</label><br>
		<label>
			<input type="radio" name="mainframe_404_behavior" value="404" <?php checked( $value, '404' ); ?>>
			<?php esc_html_e( 'Show real 404 page', 'mainframe' ); ?>
		</label>
	</fieldset>
	<?php
}

/**
 * Render the Default Route Behavior radio field.
 */
function mainframe_render_default_route_behavior_field(): void {
	$value = get_option( 'mainframe_default_route_behavior', 'show' );
	?>
	<fieldset>
		<label>
			<input type="radio" name="mainframe_default_route_behavior" value="redirect" <?php checked( $value, 'redirect' ); ?>>
			<?php esc_html_e( 'Redirect to home page', 'mainframe' ); ?>
		</label><br>
		<label>
			<input type="radio" name="mainframe_default_route_behavior" value="show" <?php checked( $value, 'show' ); ?>>
			<?php esc_html_e( 'Show content', 'mainframe' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Applied to posts and pages that have no explicit per-page setting.', 'mainframe' ); ?>
		</p>
	</fieldset>
	<?php
}

/**
 * Render the Custom Login URL slug text field.
 */
function mainframe_render_login_slug_field(): void {
	$value = get_option( 'mainframe_login_slug', '' );
	$home  = trailingslashit( home_url() );
	?>
	<input
		type="text"
		id="mainframe_login_slug"
		name="mainframe_login_slug"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
		placeholder="login"
	>
	<p class="description">
		<?php
		if ( $value ) {
			printf(
				/* translators: %s: full custom login URL */
				esc_html__( 'Login URL: %s', 'mainframe' ),
				'<code>' . esc_html( $home . $value ) . '</code>'
			);
		} else {
			printf(
				/* translators: %s: default wp-login.php URL */
				esc_html__( 'No custom slug set — login is at the default %s', 'mainframe' ),
				'<code>' . esc_html( $home . 'wp-login.php' ) . '</code>'
			);
		}
		?>
	</p>
	<?php
}

/**
 * Render the CORS Origin text field.
 */
function mainframe_render_cors_origin_field(): void {
	$value = get_option( 'mainframe_cors_origin', '' );
	?>
	<input
		type="url"
		id="mainframe_cors_origin"
		name="mainframe_cors_origin"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
		placeholder="https://example.com"
	>
	<p class="description">
		<?php esc_html_e( 'Allowed origin for REST API CORS requests. Leave empty to use the WordPress default (allow all origins).', 'mainframe' ); ?>
	</p>
	<?php
}

/**
 * Render the Default Featured Image URL field with a media library picker button.
 */
function mainframe_render_default_featured_image_field(): void {
	$value = get_option( 'mainframe_default_featured_image_url', '' );
	?>
	<div id="mainframe-default-featured-image-wrap">
		<input
			type="url"
			id="mainframe_default_featured_image_url"
			name="mainframe_default_featured_image_url"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://example.com/image.jpg"
		>
		<button type="button" class="button" id="mainframe-default-featured-image-pick">
			<?php esc_html_e( 'Choose from Media Library', 'mainframe' ); ?>
		</button>
		<div
			id="mainframe-default-featured-image-preview"
			style="margin-top:8px;<?php echo $value ? '' : 'display:none;'; ?>"
		>
			<img
				src="<?php echo esc_url( $value ); ?>"
				alt=""
				style="max-width:200px;max-height:150px;display:block;"
			>
		</div>
		<p class="description">
			<?php esc_html_e( 'Fallback image URL used in REST API responses when a post has no featured image set. Paste a URL or choose from the media library.', 'mainframe' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Render the Deploy Hook URL field.
 */
function mainframe_render_deploy_hook_url_field(): void {
	$value = get_option( 'mainframe_deploy_hook_url', '' );
	?>
	<input
		type="url"
		id="mainframe_deploy_hook_url"
		name="mainframe_deploy_hook_url"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
		placeholder="https://api.vercel.com/v1/integrations/deploy/..."
	>
	<p class="description">
		<?php esc_html_e( 'URL to POST to when a post is published or un-published. Leave empty to disable.', 'mainframe' ); ?>
	</p>
	<?php
}

/**
 * Render the Deploy Hook Secret field.
 */
function mainframe_render_deploy_hook_secret_field(): void {
	$value = get_option( 'mainframe_deploy_hook_secret', '' );
	?>
	<input
		type="password"
		id="mainframe_deploy_hook_secret"
		name="mainframe_deploy_hook_secret"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
		autocomplete="new-password"
	>
	<p class="description">
		<?php esc_html_e( 'Optional secret for HMAC-SHA256 signing. Sent as X-Mainframe-Signature so your deploy service can verify the request is genuine.', 'mainframe' ); ?>
	</p>
	<?php
}

// ---------------------------------------------------------------------------
// Sanitization callbacks
// ---------------------------------------------------------------------------

/**
 * Sanitize the redirect type option.
 *
 * @param mixed $value Raw input value.
 * @return string '301' or '302'.
 */
function mainframe_sanitize_redirect_type( $value ): string {
	return in_array( (string) $value, [ '301', '302' ], true ) ? (string) $value : '301';
}

/**
 * Sanitize the 404 behavior option.
 *
 * @param mixed $value Raw input value.
 * @return string 'redirect' or '404'.
 */
function mainframe_sanitize_404_behavior( $value ): string {
	return in_array( (string) $value, [ 'redirect', '404' ], true ) ? (string) $value : 'redirect';
}

/**
 * Sanitize the default route behavior option.
 *
 * @param mixed $value Raw input value.
 * @return string 'redirect' or 'show'.
 */
function mainframe_sanitize_default_route_behavior( $value ): string {
	return in_array( (string) $value, [ 'redirect', 'show' ], true ) ? (string) $value : 'show';
}

/**
 * Sanitize the custom login slug.
 *
 * Strips anything that is not a URL-safe slug character. An empty string is
 * valid and means "use the default wp-login.php".
 *
 * @param mixed $value Raw input value.
 * @return string Sanitized slug, or empty string.
 */
function mainframe_sanitize_login_slug( $value ): string {
	$slug = sanitize_title_with_dashes( (string) $value, '', 'save' );
	// Prevent collisions with WordPress core paths and files.
	// wp-login   — slug form of wp-login.php; would cause a redirect loop.
	// wp-signup  — WordPress multisite/single-site registration flow.
	// wp-activate — WordPress account activation flow.
	$reserved = [
		'wp-admin',
		'wp-content',
		'wp-includes',
		'wp-json',
		'wp-login',
		'wp-signup',
		'wp-activate',
		'feed',
		'sitemap',
	];
	if ( in_array( $slug, $reserved, true ) ) {
		add_settings_error(
			'mainframe_login_slug',
			'mainframe_login_slug_reserved',
			__( 'That login slug is reserved by WordPress. Please choose a different slug.', 'mainframe' )
		);
		return get_option( 'mainframe_login_slug', '' ); // Keep the previous value.
	}
	return $slug;
}

/**
 * Sanitize the CORS origin URL.
 *
 * Accepts an empty string (no restriction) or a valid absolute URL.
 *
 * @param mixed $value Raw input value.
 * @return string Sanitized URL, or empty string.
 */
function mainframe_sanitize_cors_origin( $value ): string {
	$value = trim( (string) $value );
	if ( empty( $value ) ) {
		return '';
	}
	$url = esc_url_raw( $value, [ 'http', 'https' ] );
	if ( empty( $url ) ) {
		add_settings_error(
			'mainframe_cors_origin',
			'mainframe_cors_origin_invalid',
			__( 'CORS origin must be a valid http or https URL. The value was not saved.', 'mainframe' )
		);
		return get_option( 'mainframe_cors_origin', '' );
	}
	return $url;
}

/**
 * Sanitize the default featured image URL.
 *
 * Accepts an empty string (no default) or a valid absolute URL.
 *
 * @param mixed $value Raw input value.
 * @return string Sanitized URL, or empty string.
 */
function mainframe_sanitize_default_featured_image_url( $value ): string {
	$value = trim( (string) $value );
	if ( empty( $value ) ) {
		return '';
	}
	$url = esc_url_raw( $value, [ 'http', 'https' ] );
	if ( empty( $url ) ) {
		add_settings_error(
			'mainframe_default_featured_image_url',
			'mainframe_default_featured_image_url_invalid',
			__( 'Default featured image must be a valid http or https URL. The value was not saved.', 'mainframe' )
		);
		return get_option( 'mainframe_default_featured_image_url', '' );
	}
	return $url;
}

/**
 * Sanitize the deploy hook URL.
 *
 * Accepts an empty string (disabled) or a valid absolute https URL.
 * http is also permitted for local development.
 *
 * @param mixed $value Raw input value.
 * @return string Sanitized URL, or empty string.
 */
function mainframe_sanitize_deploy_hook_url( $value ): string {
	$value = trim( (string) $value );
	if ( empty( $value ) ) {
		return '';
	}
	$url = esc_url_raw( $value, [ 'http', 'https' ] );
	if ( empty( $url ) ) {
		add_settings_error(
			'mainframe_deploy_hook_url',
			'mainframe_deploy_hook_url_invalid',
			__( 'Deploy hook URL must be a valid http or https URL. The value was not saved.', 'mainframe' )
		);
		return get_option( 'mainframe_deploy_hook_url', '' );
	}
	return $url;
}

/**
 * Sanitize the Front Page short message for the Customizer.
 *
 * Applies the same allowlist used in front-page.php when rendering, so what
 * is stored matches exactly what will be displayed. More restrictive than
 * wp_kses_post and avoids storing markup that can never be rendered.
 *
 * Allowed: a, br, em, strong, code.
 *
 * @param mixed $value Raw Customizer input.
 * @return string Sanitized HTML string.
 */
function mainframe_sanitize_front_message( $value ): string {
	return wp_kses(
		(string) $value,
		[
			'a'      => [ 'href' => [], 'title' => [], 'target' => [], 'rel' => [] ],
			'br'     => [],
			'em'     => [],
			'strong' => [],
			'code'   => [],
		]
	);
}

// ---------------------------------------------------------------------------
// Custom Customizer control — quicktags toolbar on the Short Message textarea
// ---------------------------------------------------------------------------

if ( class_exists( 'WP_Customize_Control' ) ) :

/**
 * Customizer control that attaches WordPress's native quicktags toolbar
 * to a textarea. Gives the user Bold, Italic, Link, etc. buttons without
 * requiring raw HTML knowledge or any third-party library.
 *
 * The quicktags script is part of WordPress core and is always available
 * in the Customizer. The toolbar is initialized via JS when the section
 * containing this control is first expanded.
 */
class Mainframe_Quicktags_Control extends WP_Customize_Control {

	/** @var string Control type identifier. */
	public $type = 'mainframe_quicktags';

	/**
	 * Render the control's content.
	 *
	 * Outputs a label, description, a quicktags toolbar placeholder div,
	 * and the textarea bound to the Customizer setting via $this->link().
	 * Quicktags fills the toolbar div automatically once initialized in JS.
	 */
	public function render_content(): void {
		$textarea_id = 'mainframe-qt-' . esc_attr( $this->id );
		?>
		<?php if ( $this->label ) : ?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php endif; ?>
		<?php if ( $this->description ) : ?>
		<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>
		<div class="wp-core-ui">
			<div id="qt_<?php echo esc_attr( $textarea_id ); ?>_toolbar" class="quicktags-toolbar"></div>
			<textarea
				id="<?php echo esc_attr( $textarea_id ); ?>"
				<?php $this->link(); ?>
				rows="5"
				class="widefat"
			><?php echo esc_textarea( $this->value() ); ?></textarea>
		</div>
		<?php
	}
}

endif; // class_exists WP_Customize_Control

add_action( 'customize_controls_enqueue_scripts', 'mainframe_enqueue_customizer_scripts' );
/**
 * Enqueue scripts and styles needed by the quicktags Customizer control.
 *
 * quicktags — core WordPress script that powers the Text-tab toolbar.
 * buttons   — core stylesheet that styles the toolbar buttons.
 */
function mainframe_enqueue_customizer_scripts(): void {
	wp_enqueue_script( 'quicktags' );
	wp_enqueue_style( 'buttons' );
}

add_action( 'customize_controls_print_footer_scripts', 'mainframe_print_customizer_scripts' );
/**
 * Print the inline JS that initializes quicktags and hides the wpLink
 * "Or link to existing content" section.
 *
 * Both tasks are handled here because customize_controls_print_footer_scripts
 * is a proven hook that fires in the correct Customizer controls document.
 * The style is injected via JS so it lands after WordPress's own stylesheets,
 * making the !important override reliable regardless of load order.
 *
 * Quicktags initialization is deferred until section expansion because the
 * Customizer renders all controls into hidden panels at load time — calling
 * quicktags() on a hidden textarea would produce an empty toolbar.
 */
function mainframe_print_customizer_scripts(): void {
	?>
	<script>
	(function () {
		// Inject CSS into the Customizer controls document:
		// 1. Hide "Or link to existing content" from the wpLink modal —
		//    every public route redirects home so internal page links are misleading.
		// 2. In the nav menu "Add Items" panel, hide every source section except
		//    Custom Links — Pages, Posts, Categories, Tags, and any CPT sections
		//    are all irrelevant since those routes redirect home.
		var style = document.createElement( 'style' );
		style.textContent = [
			'#wplink-link-existing-content, #search-panel { display: none !important; }',
			'#available-menu-items-search { display: none !important; }',
			'#new-custom-menu-item { margin-top: 3.75rem !important; }',
			'#accordion-section-static_front_page { display: none !important; }',
			'#accordion-section-menu_locations { display: none !important; }',
			'.customize-control-nav_menu_locations { display: none !important; }',
			// In the nav menu "Add Items" panel, hide post type and taxonomy source
			// sections (Pages, Posts, Categories, Tags, any CPT). These routes all
			// redirect home so adding them as menu items makes no sense.
			// Explicitly targeting known ID patterns is more reliable than a :not()
			// allowlist, which breaks if WordPress changes the Custom Links section ID.
			'[id^="available-menu-items-post_type-"],' +
			'[id^="available-menu-items-taxonomy-"] { display: none !important; }',
			// Hide the "Automatically add new top-level pages" checkbox — irrelevant
			// when all page routes redirect home. [id$="-auto_add"] matches the
			// dynamically-generated control ID regardless of menu ID number.
			'[id$="-auto_add"] { display: none !important; }',
			// Custom Links is the only item type available. Hide its collapsible
			// header so the form (URL + Link Text) is always visible with no
			// toggle affordance. display:block !important beats WP inline style.
			'#new-custom-menu-item .accordion-section-title { display: none !important; }',
			'#new-custom-menu-item-content { display: block !important; }',
			// Sub-items don't render on the front page — hide the "Move under"
			// button so users cannot nest items via the reorder UI.
			'.menus-move-right { display: none !important; }'
		].join( ' ' );
		document.head.appendChild( style );

		if ( typeof wp === 'undefined' || ! wp.customize ) {
			return;
		}

		var menuRefreshTimer;
		wp.customize.bind( 'change', function ( setting ) {
			// Enforce top-level-only: if a nav menu item's parent-id is set to
			// anything other than 0, immediately reset it back to 0.
			if ( setting.id.indexOf( 'nav_menu_item[' ) === 0 ) {
				var val = setting.get();
				if ( val && val['menu-item-parent-id'] && parseInt( val['menu-item-parent-id'], 10 ) !== 0 ) {
					setting.set( Object.assign( {}, val, { 'menu-item-parent-id': '0' } ) );
				}
			}
			// Refresh the preview whenever any nav menu setting changes so the
			// front page link cards update without needing to toggle the location.
			if ( /^nav_menu/.test( setting.id ) ) {
				clearTimeout( menuRefreshTimer );
				menuRefreshTimer = setTimeout( function () {
					wp.customize.previewer.refresh();
				}, 600 );
			}
		} );

		wp.customize.section( 'mainframe_front_page', function ( section ) {
			section.expanded.bind( function ( isExpanded ) {
				if ( ! isExpanded ) {
					return;
				}
				var textareaId = 'mainframe-qt-mainframe_front_message';
				if ( typeof quicktags === 'function' && ! QTags.getInstance( textareaId ) ) {
					quicktags( { id: textareaId } );
				}
			} );
		} );

		// Re-sort nav menu sections by term_id (creation order) whenever a new
		// menu section is added dynamically so the order is consistent live.
		function sortNavMenuSections() {
			var navSections = [];
			wp.customize.section.each( function ( section ) {
				var m = section.id.match( /^nav_menu\[(-?\d+)\]$/ );
				if ( m ) {
					navSections.push( { section: section, termId: parseInt( m[1], 10 ) } );
				}
			} );
			navSections.sort( function ( a, b ) { return a.termId - b.termId; } );
			navSections.forEach( function ( entry, index ) {
				entry.section.priority( 100 + index );
			} );
		}
		wp.customize.section.bind( 'add', function ( section ) {
			if ( /^nav_menu\[/.test( section.id ) ) {
				sortNavMenuSections();
			}
		} );
		sortNavMenuSections();
	}());
	</script>
	<?php
}

// ---------------------------------------------------------------------------
// Page render callback
// ---------------------------------------------------------------------------

/**
 * Render the Mainframe Settings admin page.
 */
function mainframe_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php
		$check_url = wp_nonce_url(
			add_query_arg( 'mainframe_check_updates', '1', admin_url( 'themes.php?page=mainframe-settings' ) ),
			'mainframe_check_updates'
		);
		?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'themes.php?page=mainframe-rest-reference' ) ); ?>" class="button"><?php esc_html_e( 'REST API Reference', 'mainframe' ); ?></a>
			<a href="<?php echo esc_url( $check_url ); ?>" class="button"><?php esc_html_e( 'Check for Updates', 'mainframe' ); ?></a>
		</p>
		<hr class="wp-header-end">
		<?php if ( isset( $_GET['mainframe_updated'] ) && 'cache_cleared' === $_GET['mainframe_updated'] ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Update cache cleared. WordPress will check for the latest version shortly.', 'mainframe' ); ?></p>
			</div>
		<?php endif; ?>
		<?php settings_errors(); ?>
		<?php do_action( 'mainframe_settings_page_top' ); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'mainframe_settings_group' );
			do_settings_sections( 'mainframe-settings' );
			submit_button( __( 'Save Settings', 'mainframe' ) );
			?>
		</form>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// Default Featured Image — media library picker (settings page only)
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', 'mainframe_enqueue_default_featured_image_scripts' );
/**
 * Enqueue the WordPress media library on the Mainframe Settings page so the
 * "Choose from Media Library" button can open the media picker frame.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function mainframe_enqueue_default_featured_image_scripts( string $hook_suffix ): void {
	if ( 'appearance_page_mainframe-settings' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_media();
}

add_action( 'admin_print_footer_scripts-appearance_page_mainframe-settings', 'mainframe_print_default_featured_image_picker_script' );
/**
 * Print the inline JS that wires up the media library picker on the
 * Mainframe Settings page.
 */
function mainframe_print_default_featured_image_picker_script(): void {
	?>
	<script>
	(function () {
		var btn     = document.getElementById( 'mainframe-default-featured-image-pick' );
		var input   = document.getElementById( 'mainframe_default_featured_image_url' );
		var preview = document.getElementById( 'mainframe-default-featured-image-preview' );

		if ( ! btn || ! input || ! preview ) {
			return;
		}

		// Live-preview when the URL is typed/pasted directly.
		input.addEventListener( 'input', function () {
			var url = input.value.trim();
			var img = preview.querySelector( 'img' );
			if ( url && img ) {
				img.src = url;
				preview.style.display = 'block';
			} else {
				preview.style.display = 'none';
			}
		} );

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title:    <?php echo wp_json_encode( __( 'Select Default Featured Image', 'mainframe' ) ); ?>,
				multiple: false,
				library:  { type: 'image' },
				button:   { text: <?php echo wp_json_encode( __( 'Use this image', 'mainframe' ) ); ?> }
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = ( attachment.sizes && attachment.sizes.full )
					? attachment.sizes.full.url
					: attachment.url;
				input.value = url;
				var img = preview.querySelector( 'img' );
				if ( img ) {
					img.src = url;
				}
				preview.style.display = 'block';
			} );
			frame.open();
		} );
	}());
	</script>
	<?php
}

// ---------------------------------------------------------------------------
// Customizer — Front Page settings
// ---------------------------------------------------------------------------

add_action( 'customize_register', 'mainframe_customize_register' );

/**
 * Sort nav menu Customizer sections by term_id (creation order) at late priority
 * so all menus have been registered by the time we re-assign priorities.
 */
add_action( 'customize_register', function ( WP_Customize_Manager $wp_customize ): void {
	$menus = wp_get_nav_menus( [ 'orderby' => 'term_id', 'order' => 'ASC' ] );
	if ( ! is_array( $menus ) ) {
		return;
	}
	foreach ( $menus as $index => $menu ) {
		$section = $wp_customize->get_section( 'nav_menu[' . $menu->term_id . ']' );
		if ( $section ) {
			$section->priority = 100 + $index;
		}
	}
}, 999 );
/**
 * Register the Mainframe Front Page section in the Customizer.
 *
 * WordPress already provides Site Title, Tagline, and Logo controls under
 * the built-in "Site Identity" section (when custom-logo theme support is
 * declared). This function adds only the extra fields specific to this theme:
 * a headline and a short message. Both default to empty so the front page
 * shows nothing until the developer explicitly sets content.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 */
function mainframe_customize_register( WP_Customize_Manager $wp_customize ): void {

	$wp_customize->add_section(
		'mainframe_front_page',
		[
			'title'    => __( 'Front Page', 'mainframe' ),
			'priority' => 30,
		]
	);

	// -- Headline --------------------------------------------------------

	$wp_customize->add_setting(
		'mainframe_front_headline',
		[
			'default'           => '',
			'transport'         => 'refresh',
			'sanitize_callback' => 'sanitize_text_field',
		]
	);

	$wp_customize->add_control(
		'mainframe_front_headline',
		[
			'label'       => __( 'Headline', 'mainframe' ),
			'description' => __( 'Large heading displayed on the front page. Leave empty to show nothing.', 'mainframe' ),
			'section'     => 'mainframe_front_page',
			'type'        => 'text',
		]
	);

	// -- Short Message ---------------------------------------------------

	$wp_customize->add_setting(
		'mainframe_front_message',
		[
			'default'           => '',
			'transport'         => 'refresh',
			'sanitize_callback' => 'mainframe_sanitize_front_message',
		]
	);

	$wp_customize->add_control(
		new Mainframe_Quicktags_Control(
			$wp_customize,
			'mainframe_front_message',
			[
				'label'       => __( 'Short Message', 'mainframe' ),
				'description' => __( 'Text shown below the headline. Use the toolbar to add bold, italic, or links.', 'mainframe' ),
				'section'     => 'mainframe_front_page',
			]
		)
	);
}

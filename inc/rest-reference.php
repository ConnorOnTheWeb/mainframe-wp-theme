<?php
/**
 * Mainframe Theme — Live REST API Reference
 *
 * Registers an Appearance > REST API Reference admin page that introspects
 * the WordPress REST API at render time and displays a complete, accurate
 * reference.
 *
 * The page is "live": any field registered via register_rest_field() — by
 * this theme, a plugin, or custom code in functions.php — appears here
 * automatically without requiring any manual update.
 *
 * How it works:
 *   1. rest_get_server() is called at page render time. This fires the
 *      rest_api_init action, causing all register_rest_field() calls to run
 *      and populate $GLOBALS['wp_rest_additional_fields'].
 *   2. Internal WP_REST_Request OPTIONS calls retrieve the full per-post-type
 *      schema including extra fields, via the standard REST controller.
 *   3. Extra fields (registered via register_rest_field) are highlighted and
 *      separated from WP core fields. Mainframe's own fields are specifically
 *      labelled.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'mainframe_register_rest_reference_page' );
/**
 * Register "REST API Reference" as an Appearance submenu page.
 */
function mainframe_register_rest_reference_page(): void {
	add_submenu_page(
		'themes.php',
		__( 'REST API Reference', 'mainframe' ),
		__( 'REST API Reference', 'mainframe' ),
		'manage_options',
		'mainframe-rest-reference',
		'mainframe_render_rest_reference_page'
	);
}

// ---------------------------------------------------------------------------
// Schema helper functions
// ---------------------------------------------------------------------------

/**
 * Build the HTML for the type cell of a field row.
 *
 * Handles plain types, union arrays (["object","null"]), array-of-type
 * shorthand (integer[]), and string format hints.
 *
 * @param array $schema Field schema array.
 * @return string Pre-escaped HTML string.
 */
function mainframe_ref_type_html( array $schema ): string {
	$raw = $schema['type'] ?? '';

	if ( is_array( $raw ) ) {
		// Union type e.g. ["object", "null"].
		$label = implode( '|', array_map( 'esc_html', $raw ) );

	} elseif ( 'array' === $raw && isset( $schema['items'] ) ) {
		$item_type = $schema['items']['type'] ?? ( isset( $schema['items']['properties'] ) ? 'object' : 'mixed' );
		if ( is_array( $item_type ) ) {
			$item_type = implode( '|', $item_type );
		}
		$label = esc_html( $item_type ) . '[]';

	} else {
		$label = esc_html( (string) $raw );
		if ( 'string' === $raw && ! empty( $schema['format'] ) ) {
			$label .= '&thinsp;<small class="mf-format">(' . esc_html( $schema['format'] ) . ')</small>';
		}
	}

	return '<code class="mf-type">' . ( $label ?: '<em style="font-style:normal;color:#aaa">—</em>' ) . '</code>';
}

/**
 * Build an HTML property list for object and object[] fields.
 *
 * Shows the sub-property names and types inline in the Description column so
 * consumers can see the shape without needing a separate request.
 *
 * @param array $schema Field schema array.
 * @return string Pre-escaped HTML string, may be empty.
 */
function mainframe_ref_props_html( array $schema ): string {
	$raw_type    = $schema['type'] ?? '';
	$is_object   = ( 'object' === $raw_type || ( is_array( $raw_type ) && in_array( 'object', $raw_type, true ) ) );
	$is_obj_arr  = ( 'array' === $raw_type && isset( $schema['items']['properties'] ) );

	if ( $is_object && ! empty( $schema['properties'] ) ) {
		$props  = $schema['properties'];
		$prefix = '';
	} elseif ( $is_obj_arr ) {
		$props  = $schema['items']['properties'];
		$prefix = '<span class="mf-props-label">' . esc_html__( 'Each item:', 'mainframe' ) . '</span>';
	} elseif ( 'array' === $raw_type && isset( $schema['items']['items']['properties'] ) ) {
		// Two levels deep — menus[].items[].
		$props  = $schema['items']['items']['properties'];
		$prefix = '<span class="mf-props-label">' . esc_html__( 'Each nested item:', 'mainframe' ) . '</span>';
	} else {
		return '';
	}

	$lines = [];
	foreach ( $props as $name => $prop ) {
		$ptype = $prop['type'] ?? '';
		if ( is_array( $ptype ) ) {
			$ptype = implode( '|', $ptype );
		}
		$lines[] = '<li><code>' . esc_html( $name ) . '</code>'
			. ( '' !== $ptype ? ' <span class="mf-prop-type">' . esc_html( $ptype ) . '</span>' : '' )
			. '</li>';
	}

	return $prefix . '<ul class="mf-obj-props">' . implode( '', $lines ) . '</ul>';
}

/**
 * Render <tr> elements for a properties map.
 *
 * @param array    $properties      Field name → schema map.
 * @param string[] $mainframe_names Known Mainframe-added field names (used for
 *                                  source badge). Pass [] to hide source column.
 */
function mainframe_ref_render_rows( array $properties, array $mainframe_names ): void {
	$show_source = ! empty( $mainframe_names );
	foreach ( $properties as $field_name => $field_schema ) :
		$is_mainframe = $show_source && in_array( $field_name, $mainframe_names, true );
		$readonly     = ! empty( $field_schema['readonly'] );
		?>
		<tr>
			<td>
				<code class="mf-fn"><?php echo esc_html( $field_name ); ?></code>
				<?php if ( $readonly ) : ?>
					<abbr class="mf-readonly" title="<?php esc_attr_e( 'Read-only', 'mainframe' ); ?>">ro</abbr>
				<?php endif; ?>
			</td>
			<td><?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper outputs pre-escaped HTML
				echo mainframe_ref_type_html( $field_schema );
			?></td>
			<td>
				<?php echo esc_html( $field_schema['description'] ?? '' ); ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper outputs pre-escaped HTML
				echo mainframe_ref_props_html( $field_schema );
				?>
			</td>
			<?php if ( $show_source ) : ?>
			<td class="mf-col-src">
				<?php if ( $is_mainframe ) : ?>
					<span class="mf-badge mf-badge-mf"><?php esc_html_e( 'Mainframe', 'mainframe' ); ?></span>
				<?php else : ?>
					<span class="mf-badge mf-badge-custom"><?php esc_html_e( 'Custom', 'mainframe' ); ?></span>
				<?php endif; ?>
			</td>
			<?php endif; ?>
		</tr>
		<?php
	endforeach;
}

// ---------------------------------------------------------------------------
// Page render
// ---------------------------------------------------------------------------

/**
 * Render the REST API Reference admin page.
 *
 * Boots the REST server, introspects all routes and fields, and outputs the
 * live reference table.
 */
function mainframe_render_rest_reference_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Bootstrap the REST server — fires rest_api_init so all register_rest_field()
	// calls run and populate $GLOBALS['wp_rest_additional_fields'].
	rest_get_server();

	$base_url     = rtrim( get_rest_url(), '/' );
	$extra_global = $GLOBALS['wp_rest_additional_fields'] ?? [];
	$settings_url = admin_url( 'themes.php?page=mainframe-settings' );

	// Known Mainframe-registered field names for source tagging.
	$mainframe_fields = [
		'featured_media_url',
		'featured_media_sizes',
		'featured_media_meta',
		'author_info',
		'ancestor_ids',
		'categories_info',
		'tags_info',
		'reading_time',
	];

	// -------------------------------------------------------------------------
	// Collect mainframe/v1 routes from the live route table.
	// -------------------------------------------------------------------------
	$mf_routes = [];
	foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
		if ( 0 !== strpos( $route, '/mainframe/' ) ) {
			continue;
		}
		$methods   = [];
		$is_public = false;
		$schema    = null;

		foreach ( $handlers as $handler ) {
			foreach ( array_keys( $handler['methods'] ?? [] ) as $m ) {
				$methods[] = $m;
			}
			// Detect public routes.
			if ( isset( $handler['permission_callback'] ) && '__return_true' === $handler['permission_callback'] ) {
				$is_public = true;
			}
			// Read schema callback if registered.
			if ( null === $schema && isset( $handler['schema'] ) && is_callable( $handler['schema'] ) ) {
				$schema = call_user_func( $handler['schema'] );
			}
		}

		$mf_routes[] = [
			'route'     => $route,
			'methods'   => array_unique( $methods ),
			'is_public' => $is_public,
			'schema'    => $schema,
		];
	}

	// -------------------------------------------------------------------------
	// Collect post type data — full schema via internal OPTIONS dispatch.
	// -------------------------------------------------------------------------
	$pt_data    = [];
	$post_types = get_post_types( [ 'show_in_rest' => true ], 'objects' );
	ksort( $post_types );

	foreach ( $post_types as $slug => $pt ) {
		$rest_ns   = $pt->rest_namespace ?? 'wp/v2';
		$rest_base = $pt->rest_base      ?: $slug;
		$path      = '/' . $rest_ns . '/' . $rest_base;

		// Fetch the full schema via an internal OPTIONS request.
		// WP_REST_Controller::get_item_schema() merges in register_rest_field()
		// entries via add_additional_fields_schema(), so this is truly live.
		$properties = [];
		$req        = new WP_REST_Request( 'OPTIONS', $path );
		$res        = rest_get_server()->dispatch( $req );
		$data       = $res->get_data();
		if ( isset( $data['schema']['properties'] ) && is_array( $data['schema']['properties'] ) ) {
			$properties = $data['schema']['properties'];
		}

		// Split fields into extra (register_rest_field) vs WP core.
		$extra_keys  = array_keys( $extra_global[ $slug ] ?? [] );
		$extra_props = [];
		$core_props  = [];
		foreach ( $properties as $field_name => $field_schema ) {
			if ( in_array( $field_name, $extra_keys, true ) ) {
				$extra_props[ $field_name ] = $field_schema;
			} else {
				$core_props[ $field_name ] = $field_schema;
			}
		}

		$pt_data[] = [
			'label'       => $pt->labels->name,
			'slug'        => $slug,
			'path'        => $path,
			'extra_props' => $extra_props,
			'core_props'  => $core_props,
		];
	}

	?>
	<style>
		.mf-ref { max-width: 1080px; }
		.mf-base-box { display:flex; flex-wrap:wrap; gap:24px; align-items:center; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; padding:10px 14px; margin:14px 0 24px; }
		.mf-base-box code { background:transparent; font-size:13px; }
		.mf-section-heading { border-bottom:1px solid #dcdcde; padding-bottom:6px; margin-top:28px; }
		.mf-endpoint-card { border:1px solid #dcdcde; border-radius:3px; margin-bottom:14px; overflow:hidden; }
		.mf-endpoint-hd { display:flex; flex-wrap:wrap; gap:8px; align-items:center; background:#f6f7f7; padding:8px 12px; border-bottom:1px solid #dcdcde; }
		.mf-endpoint-hd code { font-size:13px; }
		.mf-endpoint-body { padding:12px 14px; }
		.mf-method { display:inline-block; font-size:10px; font-weight:700; letter-spacing:.6px; padding:2px 7px; border-radius:3px; text-transform:uppercase; }
		.mf-method-get { background:#e8f5e9; color:#1b5e20; }
		.mf-method-post { background:#e3f2fd; color:#0d47a1; }
		.mf-method-put,.mf-method-patch { background:#fff8e1; color:#e65100; }
		.mf-method-delete { background:#fce4ec; color:#880e4f; }
		.mf-badge { display:inline-block; font-size:10px; font-weight:700; padding:1px 6px; border-radius:3px; vertical-align:middle; text-transform:uppercase; letter-spacing:.3px; white-space:nowrap; }
		.mf-badge-public { background:#e3f2fd; color:#1565c0; }
		.mf-badge-auth { background:#fff3e0; color:#bf360c; }
		.mf-badge-mf { background:#ede7f6; color:#4527a0; }
		.mf-badge-custom { background:#e8f5e9; color:#1b5e20; }
		.mf-pt-block { margin-bottom:32px; }
		.mf-pt-block h3 { margin-bottom:4px; }
		.mf-pt-route { font-family:'SFMono-Regular',Consolas,monospace; font-size:12px; font-weight:400; color:#999; margin-left:6px; }
		table.mf-tbl { border-collapse:collapse; width:100%; margin-top:8px; table-layout:fixed; }
		table.mf-tbl th { background:#f6f7f7; padding:7px 10px; text-align:left; border-bottom:2px solid #dcdcde; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#50575e; }
		table.mf-tbl td { padding:8px 10px; border-bottom:1px solid #f0f0f0; font-size:13px; vertical-align:top; }
		table.mf-tbl tr:last-child td { border-bottom:none; }
		table.mf-tbl code.mf-fn { font-size:12px; background:#f6f7f7; padding:1px 4px; border-radius:2px; }
		code.mf-type { font-size:11px; background:#f0f0f0; padding:1px 5px; border-radius:3px; border:none; font-weight:400; }
		.mf-format { font-size:10px; color:#aaa; }
		.mf-prop-type { font-size:11px; color:#aaa; font-style:italic; }
		.mf-obj-props { margin:4px 0 0; padding-left:14px; font-size:11px; color:#555; }
		.mf-obj-props li { margin-bottom:1px; }
		.mf-obj-props code { font-size:11px; }
		.mf-props-label { font-size:11px; color:#999; display:block; margin-top:4px; }
		abbr.mf-readonly { font-size:10px; color:#aaa; margin-left:3px; cursor:help; border-bottom:1px dotted #ccc; text-decoration:none; }
		.mf-core-wrap { margin-top:6px; }
		.mf-core-wrap summary { font-size:12px; color:#2271b1; cursor:pointer; display:inline; }
		.mf-core-wrap summary:hover { text-decoration:underline; }
		.mf-no-extra { color:#888; font-size:13px; margin:4px 0; }
		.mf-col-field { width:21%; }
		.mf-col-type { width:17%; }
		.mf-col-desc { width:48%; }
		.mf-col-src { width:14%; text-align:center; }
		.mf-no-src .mf-col-desc { width:62%; }
	</style>
	<div class="wrap mf-ref">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'REST API Reference', 'mainframe' ); ?></h1>
		<a href="<?php echo esc_url( $settings_url ); ?>" class="page-title-action"><?php esc_html_e( '← Mainframe Settings', 'mainframe' ); ?></a>
		<hr class="wp-header-end">

		<p class="description"><?php esc_html_e( 'This page reflects the live state of the REST API — fields added by plugins or custom code appear here automatically.', 'mainframe' ); ?></p>

		<div class="mf-base-box">
			<span><strong><?php esc_html_e( 'Base URL', 'mainframe' ); ?>:</strong>&nbsp;<code><?php echo esc_html( $base_url ); ?></code></span>
			<span><strong><?php esc_html_e( 'Authentication', 'mainframe' ); ?>:</strong>&nbsp;<?php esc_html_e( 'Cookie (admin session) or', 'mainframe' ); ?> <a href="https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/#application-passwords" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Application Passwords', 'mainframe' ); ?></a></span>
		</div>

		<?php // ================================================================= ?>
		<?php // Mainframe Endpoints                                                ?>
		<?php // ================================================================= ?>
		<?php if ( ! empty( $mf_routes ) ) : ?>
		<h2 class="mf-section-heading"><?php esc_html_e( 'Mainframe Endpoints', 'mainframe' ); ?></h2>

		<?php foreach ( $mf_routes as $mf ) : ?>
		<div class="mf-endpoint-card">
			<div class="mf-endpoint-hd">
				<?php foreach ( $mf['methods'] as $method ) : ?>
					<span class="mf-method mf-method-<?php echo esc_attr( strtolower( $method ) ); ?>"><?php echo esc_html( $method ); ?></span>
				<?php endforeach; ?>
				<code><?php echo esc_html( $mf['route'] ); ?></code>
				<?php if ( $mf['is_public'] ) : ?>
					<span class="mf-badge mf-badge-public"><?php esc_html_e( 'Public', 'mainframe' ); ?></span>
				<?php else : ?>
					<span class="mf-badge mf-badge-auth"><?php esc_html_e( 'Auth required', 'mainframe' ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $mf['schema']['properties'] ) ) : ?>
			<div class="mf-endpoint-body">
				<p style="margin:0 0 4px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#50575e;"><?php esc_html_e( 'Response', 'mainframe' ); ?></p>
				<table class="mf-tbl mf-no-src">
					<colgroup>
						<col class="mf-col-field">
						<col class="mf-col-type">
						<col class="mf-col-desc">
					</colgroup>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'mainframe' ); ?></th>
							<th><?php esc_html_e( 'Type', 'mainframe' ); ?></th>
							<th><?php esc_html_e( 'Description', 'mainframe' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php mainframe_ref_render_rows( $mf['schema']['properties'], [] ); ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
		<?php endif; ?>

		<?php // ================================================================= ?>
		<?php // Post Type Endpoints                                                ?>
		<?php // ================================================================= ?>
		<h2 class="mf-section-heading"><?php esc_html_e( 'Post Type Endpoints', 'mainframe' ); ?></h2>

		<?php foreach ( $pt_data as $pt ) : ?>
		<div class="mf-pt-block">
			<h3>
				<?php echo esc_html( $pt['label'] ); ?>
				<span class="mf-pt-route">/wp-json<?php echo esc_html( $pt['path'] ); ?></span>
			</h3>

			<?php if ( ! empty( $pt['extra_props'] ) ) : ?>
			<table class="mf-tbl">
				<colgroup>
					<col class="mf-col-field">
					<col class="mf-col-type">
					<col class="mf-col-desc">
					<col class="mf-col-src">
				</colgroup>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'mainframe' ); ?></th>
						<th><?php esc_html_e( 'Type', 'mainframe' ); ?></th>
						<th><?php esc_html_e( 'Description', 'mainframe' ); ?></th>
						<th class="mf-col-src"><?php esc_html_e( 'Source', 'mainframe' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php mainframe_ref_render_rows( $pt['extra_props'], $mainframe_fields ); ?>
				</tbody>
			</table>
			<?php elseif ( ! empty( $pt['core_props'] ) ) : ?>
			<p class="mf-no-extra"><?php esc_html_e( 'No extra fields registered — only WP core fields present.', 'mainframe' ); ?></p>
			<?php else : ?>
			<p class="mf-no-extra"><?php esc_html_e( 'No schema available for this post type.', 'mainframe' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $pt['core_props'] ) ) : ?>
			<details class="mf-core-wrap">
				<summary>
					<?php
					printf(
						/* translators: %d: number of WP core fields */
						esc_html__( '%d WP core fields', 'mainframe' ),
						count( $pt['core_props'] )
					);
					?>
				</summary>
				<table class="mf-tbl mf-no-src" style="margin-top:8px;">
					<colgroup>
						<col class="mf-col-field">
						<col class="mf-col-type">
						<col class="mf-col-desc">
					</colgroup>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'mainframe' ); ?></th>
							<th><?php esc_html_e( 'Type', 'mainframe' ); ?></th>
							<th><?php esc_html_e( 'Description', 'mainframe' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php mainframe_ref_render_rows( $pt['core_props'], [] ); ?>
					</tbody>
				</table>
			</details>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>

	</div><!-- .wrap.mf-ref -->
	<?php
}

<?php
/**
 * Mainframe Theme — Block Manager
 *
 * Lets admins control which blocks appear in the block editor inserter.
 *
 * Detection: Blocks that declare a front-end view script are auto-detected as
 * JS-dependent. They are hidden from the inserter by default in a headless setup
 * where those scripts never run. All other blocks are shown by default.
 *
 * Data model: Only overrides from defaults are stored (option: mainframe_block_overrides).
 *   - A JS-dependent block in overrides with value TRUE  → explicitly shown.
 *   - A standard block    in overrides with value FALSE → explicitly hidden.
 * New blocks registered by plugins are automatically categorised without any
 * saved-state initialisation — the defaults are always computed dynamically.
 *
 * All changes are non-destructive: existing block instances in saved posts
 * are never modified. Only the inserter is filtered.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// Option keys.
const MAINFRAME_BLOCK_MANAGER_ENABLED_KEY = 'mainframe_block_manager_enabled';
const MAINFRAME_BLOCK_OVERRIDES_KEY       = 'mainframe_block_overrides';

// ---------------------------------------------------------------------------
// Block detection helpers
// ---------------------------------------------------------------------------

/**
 * Core blocks known to require front-end JavaScript regardless of how their
 * scripts are registered in a given WP version.
 *
 * In WP 6.5+ many core blocks migrated to the Interactivity API, registering
 * view script modules via wp_register_script_module() in PHP rather than through
 * viewScriptModule in block.json. This means view_script_module_ids is often
 * empty on the WP_Block_Type object even though the block is JS-dependent.
 * This curated list covers those cases.
 *
 * @internal Used only by mainframe_block_has_frontend_js().
 */
function mainframe_known_js_blocks(): array {
	return [
		// Navigation — mobile toggle, dropdowns, overlay all require JS.
		'core/navigation',
		'core/navigation-submenu',
		'core/navigation-overlay-close',

		// Interactive query blocks — enhanced pagination / filtering.
		'core/query',
		'core/query-pagination',
		'core/query-pagination-next',
		'core/query-pagination-numbers',
		'core/query-pagination-previous',

		// Search — enhanced search interactions (autocomplete, live results).
		'core/search',

		// Image — lightbox is on by default and requires JS.
		'core/image',

		// Comments form — reply/threading interactions.
		'core/post-comments-form',
		'core/comments',

		// Login/out — inline AJAX logout wiring.
		'core/loginout',
	];
}

/**
 * Determine whether a single block type requires front-end JavaScript.
 *
 * Checks in order:
 *   1. view_script_handles  — explicit handles registered for the block (WP 6.1+).
 *   2. view_script          — deprecated single-value form (pre-6.1 blocks).
 *   3. view_script_module_ids — Interactivity API script modules (WP 6.5+).
 *   4. Curated known list   — fallback for blocks that use PHP-side module registration.
 *
 * Note: supports['interactivity'] is deliberately excluded — WP 7.0 added it
 * to almost every core block as a generic capability flag, not a JS-required signal.
 *
 * @param WP_Block_Type $block_type The block type to test.
 * @return bool
 */
function mainframe_block_has_frontend_js( WP_Block_Type $block_type ): bool {
	if ( ! empty( $block_type->view_script_handles ) ) {
		return true;
	}
	if ( ! empty( $block_type->view_script ) ) {
		return true;
	}
	if ( ! empty( $block_type->view_script_module_ids ) ) {
		return true;
	}
	if ( in_array( $block_type->name, mainframe_known_js_blocks(), true ) ) {
		return true;
	}
	return false;
}

/**
 * Return all registered block types that require front-end JavaScript.
 *
 * Result is memoized for the current request.
 *
 * @return WP_Block_Type[] Keyed by block name, sorted alphabetically.
 */
function mainframe_get_js_dependent_blocks(): array {
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}

	$registry  = WP_Block_Type_Registry::get_instance();
	$js_blocks = [];

	foreach ( $registry->get_all_registered() as $name => $block_type ) {
		if ( mainframe_block_has_frontend_js( $block_type ) ) {
			$js_blocks[ $name ] = $block_type;
		}
	}

	ksort( $js_blocks );
	$cache = $js_blocks;
	return $cache;
}

/**
 * Return all registered block types that do NOT require front-end JavaScript.
 *
 * @return WP_Block_Type[] Keyed by block name, sorted alphabetically.
 */
function mainframe_get_standard_blocks(): array {
	$all    = WP_Block_Type_Registry::get_instance()->get_all_registered();
	$non_js = array_diff_key( $all, mainframe_get_js_dependent_blocks() );
	ksort( $non_js );
	return $non_js;
}

/**
 * Return whether a block should be shown in the inserter, accounting for
 * saved overrides.
 *
 * Defaults: JS-dependent = hidden (false); standard = shown (true).
 * An entry in $overrides flips a block from its default.
 *
 * @param string $name      Block name (e.g. "core/paragraph").
 * @param array  $overrides Saved overrides from the mainframe_block_overrides option.
 * @param array  $js_names  Block names that are JS-dependent (keys only).
 * @return bool True = shown in inserter.
 */
function mainframe_block_is_shown( string $name, array $overrides, array $js_names ): bool {
	$default = ! in_array( $name, $js_names, true ); // false for JS, true for standard
	return isset( $overrides[ $name ] ) ? (bool) $overrides[ $name ] : $default;
}

// ---------------------------------------------------------------------------
// Editor filter
// ---------------------------------------------------------------------------

add_filter( 'allowed_block_types_all', 'mainframe_filter_allowed_blocks', 10, 2 );
/**
 * Restrict the block inserter to only the blocks that should be shown.
 *
 * @param bool|string[]           $allowed_block_types Current allowed types.
 * @param WP_Block_Editor_Context $context             Current editor context.
 * @return bool|string[]
 */
function mainframe_filter_allowed_blocks( $allowed_block_types, $context ) {
	if ( ! get_option( MAINFRAME_BLOCK_MANAGER_ENABLED_KEY, true ) ) {
		return $allowed_block_types;
	}

	$overrides = get_option( MAINFRAME_BLOCK_OVERRIDES_KEY, [] );
	if ( ! is_array( $overrides ) ) {
		$overrides = [];
	}

	$js_names = array_keys( mainframe_get_js_dependent_blocks() );
	$allowed  = [];

	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $_ ) {
		if ( mainframe_block_is_shown( $name, $overrides, $js_names ) ) {
			$allowed[] = $name;
		}
	}

	return array_values( $allowed );
}

// ---------------------------------------------------------------------------
// Settings registration
// ---------------------------------------------------------------------------

add_action( 'admin_init', 'mainframe_register_block_manager_settings' );
/**
 * Register the Block Manager settings with the Settings API.
 */
function mainframe_register_block_manager_settings(): void {

	register_setting(
		'mainframe_settings_group',
		MAINFRAME_BLOCK_MANAGER_ENABLED_KEY,
		[
			'type'              => 'boolean',
			'sanitize_callback' => fn( $v ) => (bool) $v,
			'default'           => true,
		]
	);

	register_setting(
		'mainframe_settings_group',
		MAINFRAME_BLOCK_OVERRIDES_KEY,
		[
			'type'              => 'object',
			'sanitize_callback' => 'mainframe_sanitize_block_overrides',
			'default'           => [],
		]
	);

	add_settings_section(
		'mainframe_section_block_manager',
		__( 'Block Manager', 'mainframe' ),
		'mainframe_render_block_manager_section_intro',
		'mainframe-settings'
	);

	add_settings_field(
		MAINFRAME_BLOCK_MANAGER_ENABLED_KEY,
		__( 'Enable', 'mainframe' ),
		'mainframe_render_block_manager_enabled_field',
		'mainframe-settings',
		'mainframe_section_block_manager'
	);

	add_settings_field(
		MAINFRAME_BLOCK_OVERRIDES_KEY,
		__( 'Blocks', 'mainframe' ),
		'mainframe_render_block_overrides_field',
		'mainframe-settings',
		'mainframe_section_block_manager'
	);
}

/**
 * Sanitize the block overrides array.
 *
 * The form posts name[block/name] = "1" (shown) or "0" (hidden) for every block
 * via a hidden input + checkbox pair. We only persist entries that differ from
 * the block's default state so the stored option stays lean and defaults remain
 * fully dynamic — new blocks are automatically categorised without re-saving.
 *
 * @param mixed $value Raw POST value — associative array of {block_name: "0"|"1"}.
 * @return array Sparse {block_name: bool} array for non-default states only.
 */
function mainframe_sanitize_block_overrides( $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}

	$js_names = array_keys( mainframe_get_js_dependent_blocks() );
	$result   = [];

	foreach ( $value as $name => $shown ) {
		$clean = sanitize_text_field( (string) $name );
		if ( ! preg_match( '/^[a-z0-9_-]+\/[a-z0-9_-]+$/', $clean ) ) {
			continue;
		}

		$is_js   = in_array( $clean, $js_names, true );
		$default = ! $is_js; // JS default = hidden; standard default = shown
		$val     = (bool) (int) $shown;

		// Only store entries that override the default.
		if ( $val !== $default ) {
			$result[ $clean ] = $val;
		}
	}

	return $result;
}

// ---------------------------------------------------------------------------
// Settings field renderers
// ---------------------------------------------------------------------------

/**
 * Render the intro paragraph for the Block Manager section.
 */
function mainframe_render_block_manager_section_intro(): void {
	echo '<p>' . esc_html__(
		'Control which blocks appear in the editor inserter. JS-dependent blocks are hidden by default — in a headless setup their front-end scripts never run. All other blocks are shown by default. Changes are non-destructive; existing post content is never affected.',
		'mainframe'
	) . '</p>';
}

/**
 * Render the master enable/disable toggle.
 */
function mainframe_render_block_manager_enabled_field(): void {
	$enabled = get_option( MAINFRAME_BLOCK_MANAGER_ENABLED_KEY, true );
	?>
	<input type="hidden"
	       name="<?php echo esc_attr( MAINFRAME_BLOCK_MANAGER_ENABLED_KEY ); ?>"
	       value="0">
	<label>
		<input type="checkbox"
		       name="<?php echo esc_attr( MAINFRAME_BLOCK_MANAGER_ENABLED_KEY ); ?>"
		       value="1"
		       <?php checked( (bool) $enabled ); ?>>
		<?php esc_html_e( 'Restrict block inserter based on selections below', 'mainframe' ); ?>
	</label>
	<?php
}

/**
 * Render the per-block checkbox list.
 *
 * Checked = shown in inserter. Unchecked = hidden from inserter.
 *
 * Each block row outputs a hidden input (value "0") followed by a checkbox
 * (value "1") with the same name. The hidden input ensures the block always
 * appears in POST data even when unchecked, so the sanitize callback receives
 * the complete state of every block without needing JS form tricks.
 */
function mainframe_render_block_overrides_field(): void {
	$overrides = get_option( MAINFRAME_BLOCK_OVERRIDES_KEY, [] );
	if ( ! is_array( $overrides ) ) {
		$overrides = [];
	}

	$js_blocks  = mainframe_get_js_dependent_blocks();
	$std_blocks = mainframe_get_standard_blocks();
	$js_names   = array_keys( $js_blocks );
	$opt        = MAINFRAME_BLOCK_OVERRIDES_KEY;
	?>
	<div id="mainframe-block-manager" style="max-width:640px">

		<p style="margin-bottom:.75em">
			<button type="button" class="button button-small" id="mf-hide-js">
				<?php esc_html_e( 'Hide all JS-dependent', 'mainframe' ); ?>
			</button>
			<button type="button" class="button button-small" id="mf-show-js">
				<?php esc_html_e( 'Show all JS-dependent', 'mainframe' ); ?>
			</button>
			<button type="button" class="button button-small" id="mf-show-all">
				<?php esc_html_e( 'Show all blocks', 'mainframe' ); ?>
			</button>
		</p>

		<?php if ( ! empty( $js_blocks ) ) : ?>
			<details>
				<summary style="cursor:pointer;font-weight:600;padding:.35em 0;user-select:none">
					<?php printf(
						/* translators: %d: number of auto-detected JS-dependent blocks */
						esc_html__( 'JS-Dependent Blocks (%d auto-detected)', 'mainframe' ),
						count( $js_blocks )
					); ?>
				</summary>
				<p class="description" style="margin:.5em 0 .6em">
					<?php esc_html_e( 'Checked = shown in editor. Hidden by default in headless setups.', 'mainframe' ); ?>
				</p>
				<table class="widefat striped" style="margin-bottom:1.5em">
					<tbody>
					<?php foreach ( $js_blocks as $name => $block_type ) :
						$label    = $block_type->title ?? $name;
						$is_shown = mainframe_block_is_shown( $name, $overrides, $js_names );
						?>
						<tr>
							<td style="width:2.5em;vertical-align:middle">
								<input type="hidden"
								       name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $name ); ?>]"
								       value="0">
								<input type="checkbox"
								       class="mf-block-cb mf-js-block"
								       name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $name ); ?>]"
								       value="1"
								       <?php checked( $is_shown ); ?>>
							</td>
							<td>
								<strong><?php echo esc_html( $label ); ?></strong><br>
								<code style="font-size:.8em"><?php echo esc_html( $name ); ?></code>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No JS-dependent blocks detected.', 'mainframe' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $std_blocks ) ) : ?>
			<details>
				<summary style="cursor:pointer;font-weight:600;padding:.35em 0;user-select:none">
					<?php printf(
						/* translators: %d: number of standard blocks */
						esc_html__( 'Standard Blocks (%d)', 'mainframe' ),
						count( $std_blocks )
					); ?>
				</summary>
				<p class="description" style="margin:.5em 0 .6em">
					<?php esc_html_e( 'Checked = shown in editor. Shown by default.', 'mainframe' ); ?>
				</p>
				<table class="widefat striped">
					<tbody>
					<?php foreach ( $std_blocks as $name => $block_type ) :
						$label    = $block_type->title ?? $name;
						$is_shown = mainframe_block_is_shown( $name, $overrides, $js_names );
						?>
						<tr>
							<td style="width:2.5em;vertical-align:middle">
								<input type="hidden"
								       name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $name ); ?>]"
								       value="0">
								<input type="checkbox"
								       class="mf-block-cb mf-std-block"
								       name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $name ); ?>]"
								       value="1"
								       <?php checked( $is_shown ); ?>>
							</td>
							<td>
								<strong><?php echo esc_html( $label ); ?></strong><br>
								<code style="font-size:.8em"><?php echo esc_html( $name ); ?></code>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endif; ?>
	</div>

	<script>
	(function () {
		var jsBoxes  = document.querySelectorAll( '.mf-js-block' );
		var allBoxes = document.querySelectorAll( '.mf-block-cb' );

		document.getElementById( 'mf-hide-js' ).addEventListener( 'click', function () {
			jsBoxes.forEach( function (cb) { cb.checked = false; } );
		} );
		document.getElementById( 'mf-show-js' ).addEventListener( 'click', function () {
			jsBoxes.forEach( function (cb) { cb.checked = true; } );
		} );
		document.getElementById( 'mf-show-all' ).addEventListener( 'click', function () {
			allBoxes.forEach( function (cb) { cb.checked = true; } );
		} );
	}());
	</script>
	<?php
}

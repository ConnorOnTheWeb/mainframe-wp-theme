<?php
/**
 * Mainframe Theme — GitHub Releases Updater
 *
 * Hooks into WordPress's native theme update system and polls the GitHub
 * Releases API to surface update notices in Appearance → Themes — exactly
 * like a wordpress.org theme. No plugins required.
 *
 * Flow:
 *   1. WordPress checks for theme updates (daily, or manually triggered).
 *   2. pre_set_site_transient_update_themes fires — we fetch the latest
 *      GitHub release (cached for 12 hours) and inject update data when
 *      a newer version is available.
 *   3. WordPress shows the standard "Update available" notice and handles
 *      the download/install using the package URL we supply.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

/** GitHub repository slug — owner/repo. */
const MAINFRAME_GITHUB_REPO = 'ConnorOnTheWeb/mainframe-wp-theme';

/** Theme directory slug (must match the folder name). */
const MAINFRAME_THEME_SLUG = 'mainframe';

/** Transient key used to cache the GitHub API response. */
const MAINFRAME_UPDATE_CACHE_KEY = 'mainframe_github_release';

/** How long to cache the GitHub API response (12 hours). */
const MAINFRAME_UPDATE_CACHE_TTL = 12 * HOUR_IN_SECONDS;

// ---------------------------------------------------------------------------
// Inject update data into WordPress's theme update transient
// ---------------------------------------------------------------------------

add_filter( 'pre_set_site_transient_update_themes', 'mainframe_check_for_update' );
/**
 * Compare the installed theme version against the latest GitHub release and,
 * if a newer version exists, inject update data so WordPress shows the native
 * "Update available" notice in Appearance → Themes.
 *
 * @param object $transient The update_themes site transient.
 * @return object Transient, potentially with update data injected.
 */
function mainframe_check_for_update( object $transient ): object {
	// WordPress populates $transient->checked with installed theme versions
	// before firing this filter. If it's empty the check is not yet ready.
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$release = mainframe_get_latest_release();
	if ( ! $release ) {
		return $transient;
	}

	$latest_version  = ltrim( $release['tag_name'], 'v' );
	$current_version = wp_get_theme( MAINFRAME_THEME_SLUG )->get( 'Version' );

	if ( version_compare( $latest_version, $current_version, '>' ) ) {
		$transient->response[ MAINFRAME_THEME_SLUG ] = [
			'theme'        => MAINFRAME_THEME_SLUG,
			'new_version'  => $latest_version,
			'url'          => 'https://github.com/' . MAINFRAME_GITHUB_REPO,
			'package'      => 'https://github.com/' . MAINFRAME_GITHUB_REPO . '/releases/latest/download/mainframe.zip',
			'requires'     => '6.0',
			'requires_php' => '8.0',
		];
	}

	return $transient;
}

// ---------------------------------------------------------------------------
// Provide theme details for the "View version details" popup
// ---------------------------------------------------------------------------

add_filter( 'themes_api', 'mainframe_themes_api', 10, 3 );
/**
 * Supply theme metadata for the version-details popup shown when the user
 * clicks "View version details" on the update notice.
 *
 * @param false|object $result   Current result; false means not yet handled.
 * @param string       $action   API action being requested.
 * @param object       $args     Request arguments including the theme slug.
 * @return false|object Theme data object, or the original $result.
 */
function mainframe_themes_api( false|object $result, string $action, object $args ): false|object {
	if ( 'theme_information' !== $action || MAINFRAME_THEME_SLUG !== ( $args->slug ?? '' ) ) {
		return $result;
	}

	$release = mainframe_get_latest_release();
	if ( ! $release ) {
		return $result;
	}

	$latest_version = ltrim( $release['tag_name'], 'v' );
	$changelog      = isset( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : '';

	return (object) [
		'name'          => 'Mainframe',
		'slug'          => MAINFRAME_THEME_SLUG,
		'version'       => $latest_version,
		'author'        => '<a href="https://github.com/ConnorOnTheWeb">connorontheweb</a>',
		'homepage'      => 'https://github.com/' . MAINFRAME_GITHUB_REPO,
		'requires'      => '6.0',
		'requires_php'  => '8.0',
		'sections'      => [
			'description' => 'Headless WordPress theme — full dashboard, full REST API, minimal public face.',
			'changelog'   => $changelog ?: 'See <a href="https://github.com/' . MAINFRAME_GITHUB_REPO . '/releases">GitHub Releases</a> for the changelog.',
		],
		'download_link' => 'https://github.com/' . MAINFRAME_GITHUB_REPO . '/releases/latest/download/mainframe.zip',
	];
}

// ---------------------------------------------------------------------------
// Clear the cached release after an update completes
// ---------------------------------------------------------------------------

add_action( 'upgrader_process_complete', 'mainframe_clear_update_cache', 10, 2 );
/**
 * Delete the cached GitHub release data after a theme update completes so
 * the next update check fetches fresh data rather than stale cached info.
 *
 * @param WP_Upgrader $upgrader Upgrader instance.
 * @param array       $options  {
 *     @type string $action  'update', 'install', etc.
 *     @type string $type    'theme', 'plugin', etc.
 * }
 */
function mainframe_clear_update_cache( WP_Upgrader $upgrader, array $options ): void {
	if ( 'update' === $options['action'] && 'theme' === $options['type'] ) {
		delete_transient( MAINFRAME_UPDATE_CACHE_KEY );
	}
}

// ---------------------------------------------------------------------------
// GitHub API — fetch latest release with transient cache
// ---------------------------------------------------------------------------

/**
 * Fetch the latest release from the GitHub Releases API.
 *
 * Results are cached in a site transient for 12 hours to avoid hammering
 * the API. Returns false on network error or unexpected response.
 *
 * @return array|false Decoded release data array, or false on failure.
 */
function mainframe_get_latest_release(): array|false {
	$cached = get_transient( MAINFRAME_UPDATE_CACHE_KEY );
	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . MAINFRAME_GITHUB_REPO . '/releases/latest',
		[
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			'headers'    => [ 'Accept' => 'application/vnd.github+json' ],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['tag_name'] ) ) {
		return false;
	}

	set_transient( MAINFRAME_UPDATE_CACHE_KEY, $body, MAINFRAME_UPDATE_CACHE_TTL );

	return $body;
}

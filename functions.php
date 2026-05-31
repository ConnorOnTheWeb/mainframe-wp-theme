<?php
/**
 * Mainframe Theme — Functions
 *
 * Bootstraps the theme by loading all feature modules from inc/.
 * No logic lives here directly; each concern is isolated in its own file.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// Load order matters: options must be available before redirects or login
// customization run, and cleanup/REST filters should register early.
require_once get_template_directory() . '/inc/options.php';
require_once get_template_directory() . '/inc/block-manager.php';
require_once get_template_directory() . '/inc/cleanup.php';
require_once get_template_directory() . '/inc/rest.php';
require_once get_template_directory() . '/inc/redirects.php';
require_once get_template_directory() . '/inc/meta.php';
require_once get_template_directory() . '/inc/login.php';
require_once get_template_directory() . '/inc/onboarding.php';
require_once get_template_directory() . '/inc/rest-reference.php';
require_once get_template_directory() . '/inc/updater.php';

add_action( 'after_setup_theme', 'mainframe_theme_setup' );
/**
 * Register theme feature support required by the front-page template.
 *
 * - title-tag     : delegates <title> management to WordPress via wp_head().
 * - custom-logo   : enables the logo upload control in Customizer > Site
 *                   Identity and the the_custom_logo() template tag.
 *                   unlink-homepage-logo removes the anchor wrapper on the
 *                   front page so the logo does not link to itself.
 */
function mainframe_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		[
			'height'               => 200,
			'width'                => 400,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => true,
		]
	);
	// A single menu location used to populate the front-page link cards.
	register_nav_menus( [
		'mainframe-links' => __( 'Front Page Links', 'mainframe' ),
	] );
}

<?php
/**
 * Mainframe Theme — 404 Template
 *
 * Reached only when the 404 behavior option is set to "Show real 404 page".
 * When set to "Redirect to home", inc/redirects.php handles the redirect
 * earlier in template_redirect and this file is never loaded.
 *
 * A safety check is included so the correct behavior is enforced even if
 * template_redirect was bypassed.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// Safety net: redirect if the option says so.
if ( 'redirect' === get_option( 'mainframe_404_behavior', 'redirect' ) ) {
	mainframe_redirect_home( mainframe_get_redirect_code() );
}

// Ensure correct HTTP status and cache headers in the 'show' path.
status_header( 404 );
nocache_headers();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		html, body { height: 100%; }
		body {
			background: #fff;
			color: #000;
			font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			text-align: center;
		}
		.mf-404 { max-width: 28rem; padding: 3rem 1.5rem; }
		.mf-404-code {
			font-size: 6rem;
			font-weight: 800;
			line-height: 1;
			letter-spacing: -0.04em;
			opacity: 0.1;
		}
		.mf-404-title {
			font-size: 1.375rem;
			font-weight: 700;
			margin-top: 0.5rem;
		}
		.mf-404-message {
			margin-top: 0.75rem;
			font-size: 0.9375rem;
			opacity: 0.6;
		}
		.mf-404-home {
			display: inline-block;
			margin-top: 1.75rem;
			color: inherit;
			font-size: 0.875rem;
			text-underline-offset: 3px;
		}
	</style>
</head>
<body <?php body_class( 'error404' ); ?>>
<main class="mf-404">
	<p class="mf-404-code" aria-hidden="true">404</p>
	<h1 class="mf-404-title"><?php esc_html_e( 'Page not found', 'mainframe' ); ?></h1>
	<p class="mf-404-message">
		<?php esc_html_e( 'The page you requested does not exist.', 'mainframe' ); ?>
	</p>
	<a class="mf-404-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		&larr; <?php esc_html_e( 'Go home', 'mainframe' ); ?>
	</a>
</main>
<?php wp_footer(); ?>
</body>
</html>

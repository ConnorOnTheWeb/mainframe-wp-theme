<?php
/**
 * Mainframe Theme — Singular Template
 *
 * Handles individual posts and pages. By the time WordPress selects this
 * template the redirect decision has already been made in inc/redirects.php
 * via the template_redirect hook — if behavior was 'redirect' we never reach
 * here. A safety check is included below for defence in depth.
 *
 * When behavior is 'show', a clean, minimal single-post layout is rendered.
 * No nav, sidebar, or footer widgets.
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

// Safety net: honour the redirect setting in case template_redirect was
// bypassed (e.g., a plugin calling load_template() directly).
if ( is_singular() ) {
	$behavior = mainframe_get_post_behavior( get_queried_object_id() );
	if ( 'redirect' === $behavior ) {
		mainframe_redirect_home( mainframe_get_redirect_code() );
	}
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			background: #fff;
			color: #000;
			font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
			line-height: 1.6;
			padding: 3rem 1.5rem;
		}
		.mf-content { max-width: 42rem; margin: 0 auto; }
		.mf-content h1 {
			font-size: clamp( 1.375rem, 3.5vw, 2rem );
			font-weight: 700;
			line-height: 1.25;
			margin-bottom: 1.5rem;
		}
		.mf-entry-content > * + * { margin-top: 1em; }
		.mf-entry-content a { color: inherit; }
		.mf-entry-content img { max-width: 100%; height: auto; }
	</style>
</head>
<body <?php body_class(); ?>>
<main class="mf-content">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h1><?php echo esc_html( get_the_title() ); ?></h1>
				<div class="mf-entry-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
	endif;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>

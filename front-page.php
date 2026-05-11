<?php
/**
 * Mainframe Theme — Front Page Template
 *
 * Renders the public-facing placeholder page. All visible content is pulled
 * from the WordPress Customizer; nothing is displayed by default.
 *
 * Customizable via Appearance > Customize > Front Page:
 *   - Headline        (text, default empty)
 *   - Short Message   (text/basic HTML, default empty)
 *
 * Customizable via Appearance > Customize > Site Identity:
 *   - Logo            (image upload, default none)
 *   - Site Title      (used only if developer reads it; not auto-displayed)
 *   - Tagline         (used only if developer reads it; not auto-displayed)
 *
 * @package Mainframe
 */

defined( 'ABSPATH' ) || exit;

$headline = (string) get_theme_mod( 'mainframe_front_headline', '' );
$message  = (string) get_theme_mod( 'mainframe_front_message', '' );
$has_logo = has_custom_logo();

// Gather front-page link cards from ALL nav menus, in creation order.
$link_items = [];
$all_menus  = wp_get_nav_menus( [ 'orderby' => 'term_id', 'order' => 'ASC' ] );
if ( is_array( $all_menus ) ) {
	// Newly created (unsaved) menus get a negative temporary term_id in the
	// Customizer preview. Sort saved menus (positive IDs) first in ascending
	// order, then unsaved menus (negative IDs) last in ascending order.
	usort( $all_menus, function ( $a, $b ) {
		$a_neg = $a->term_id < 0;
		$b_neg = $b->term_id < 0;
		if ( $a_neg !== $b_neg ) {
			return $a_neg ? 1 : -1;
		}
		return $a->term_id <=> $b->term_id;
	} );
	foreach ( $all_menus as $menu ) {
		$raw = wp_get_nav_menu_items( $menu->term_id );
		if ( is_array( $raw ) ) {
			foreach ( $raw as $item ) {
				if ( '0' === (string) $item->menu_item_parent ) {
					$link_items[] = $item;
				}
			}
		}
	}
}

// Determine whether there is anything to render in the content area.
$has_content = $headline || $message || $has_logo || $link_items;

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
		}
		.mf-front {
			text-align: center;
			max-width: 36rem;
			padding: 3rem 1.5rem;
		}
		.mf-logo { margin-bottom: 1.5rem; }
		.mf-logo img { max-width: 200px; height: auto; display: block; margin: 0 auto; }
		.mf-headline {
			font-size: clamp( 1.5rem, 4vw, 2.5rem );
			font-weight: 700;
			line-height: 1.2;
			letter-spacing: -0.02em;
		}
		.mf-message {
			margin-top: 1rem;
			font-size: 1rem;
			line-height: 1.6;
			opacity: 0.65;
		}
		.mf-message a { color: inherit; }
		/* Link cards */
		.mf-links {
			width: 100%;
			display: flex;
			flex-direction: column;
			gap: 0.625rem;
			margin-top: 2rem;
		}
		.mf-links:first-child { margin-top: 0; }
		.mf-link-card {
			display: block;
			padding: 0.875rem 1.25rem;
			border: 1.5px solid #000;
			border-radius: 0.375rem;
			color: #000;
			text-decoration: none;
			font-weight: 500;
			font-size: 0.9375rem;
			text-align: center;
			transition: background 0.15s, color 0.15s;
		}
		.mf-link-card:hover { background: #000; color: #fff; }
	</style>
</head>
<body <?php body_class( 'mainframe-front-page' ); ?>>
<?php if ( $has_content ) : ?>
	<main class="mf-front">

		<?php if ( $has_logo ) : ?>
			<div class="mf-logo">
				<?php the_custom_logo(); ?>
			</div>
		<?php endif; ?>

		<?php if ( $headline ) : ?>
			<h1 class="mf-headline"><?php echo esc_html( $headline ); ?></h1>
		<?php endif; ?>

		<?php if ( $message ) : ?>
			<p class="mf-message">
				<?php
				echo wp_kses(
					$message,
					[
						'a'      => [ 'href' => [], 'title' => [], 'target' => [], 'rel' => [] ],
						'br'     => [],
						'em'     => [],
						'strong' => [],
						'code'   => [],
					]
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( $link_items ) : ?>
			<div class="mf-links">
				<?php foreach ( $link_items as $item ) : ?>
					<a
						href="<?php echo esc_url( $item->url ); ?>"
						class="mf-link-card"
						<?php if ( '_blank' === $item->target ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
					><?php echo esc_html( $item->title ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</main>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>

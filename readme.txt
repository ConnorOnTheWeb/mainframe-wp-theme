=== Mainframe - Headless WordPress Theme ===

Contributors: connorontheweb
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, custom-logo, custom-menu, featured-images, one-column, translation-ready

Headless WordPress theme — full dashboard, full REST API, minimal public face.

== Description ==

Mainframe is a headless-adjacent WordPress theme. It keeps the full WordPress
admin dashboard and REST API intact while presenting a minimal public frontend —
a blank, white canvas you replace with your own consuming app (Next.js, Nuxt,
SvelteKit, plain fetch(), etc.).

Features:

* All registered post types are force-exposed via show_in_rest
* Every REST response includes `featured_media_url` — a direct image URL,
  no second request needed
* Configurable CORS Access-Control-Allow-Origin header
* Per-post Featured Image URL field (external URL, overrides attached image)
* Default featured image fallback for posts with no image set
* FIFU (Featured Image from URL plugin) backwards compatibility
* Linktree-style front page with logo, headline, message, and nav menu link cards
* Custom login URL slug (blocks /wp-login.php when active)
* Per-post public route behavior: show content, redirect to home, or use site default
* Sensible admin cleanup — irrelevant Customizer sections, menu items hidden

== Installation ==

1. Upload the `mainframe` folder to `/wp-content/themes/`.
2. Activate the theme in **Appearance > Themes**.
3. Configure settings in **Appearance > Mainframe Settings**.
4. Optionally customize the front page in **Appearance > Customize > Front Page**.

== Frequently Asked Questions ==

= Does this theme require any plugins? =

No. Mainframe is fully self-contained. The Featured Image URL field is built in,
and CORS/REST configuration is handled through the theme settings page.

= Can I use this theme with a block-based frontend? =

Yes. The REST API is fully exposed and all content is available via `/wp-json/`.
Use any JavaScript framework or static site generator as your consuming frontend.

= What happens to the public-facing WordPress site? =

By default all content is visible at its standard WordPress URL. You can configure
individual posts to redirect to home, or change the site-wide default in
Appearance > Mainframe Settings > Default Route Behavior.

= Does it work with plugins? =

Yes. Plugin-generated routes and custom rewrite rules are left untouched.
Only standard WordPress route types (archives, search, author, date, singular)
are subject to the configured redirect behavior.

== Screenshots ==

1. Minimal front page with logo, headline, and link cards.

== Changelog ==

= 1.0.0 =
* Initial release.

=== Mainframe - Headless WordPress Theme ===

Contributors: connorontheweb
Requires at least: 6.0
Tested up to: 7.0
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
* First-run Headless Quick Setup wizard (opt-in — safe mode by default)
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

= 1.0.5 =
* Custom HTML blocks now show a dashed outline and minimum height in the block editor so script-only blocks (e.g. ld+json) are not invisible.

= 1.0.4 =
* Confirmed compatibility with WordPress 7.0.

= 1.0.3 =
* Fixed REST field callbacks (`featured_media_url`, `featured_media_sizes`, `author_info`, `ancestor_ids`, `categories_info`, `frontend_link`) failing to return data in some contexts due to a `$post['id']` vs `$post['ID']` key mismatch. All custom fields now return correct values on both single-post and collection endpoints.

= 1.0.2 =
* Fixed post-publish panel hiding — updated to correct CSS class names (`post-publish-panel__postpublish-*`) and suppressed the full "What's next?" section including address input, View Post button, and Add Post button.
* Removed non-functional `editor.PostLink` JS filter (WP 6.5+ uses Slot/Fill for this panel; no `applyFilters` hook exists).

= 1.0.1 =
* Added `frontend_link` REST field to all post type responses. Passes the WP permalink through the `mainframe_frontend_link` filter so developers can map to their frontend app's URL structure.
* Added `featured_media_url`, `featured_media_sizes`, `author_info`, `ancestor_ids`, and `categories_info` REST fields to all post type responses.
* Removed Frontend URL admin setting — per-post-type routing cannot be handled by a single domain setting; use the `mainframe_frontend_link` filter instead.
* Block editor: hid WP permalink display in the slug panel and legacy header permalink bar.
* Block editor: hid the Preview button (WP frontend is not the consuming app on a headless install).
* Block editor: hid the post-publish "View Post" link.
* Removed WP sample permalink rewriting from the editor.

= 1.0.0 =
* Initial release.

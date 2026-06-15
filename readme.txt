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

= 1.0.22 =
* Fixed `reading_time` REST field incorrectly counting content inside Custom HTML blocks (e.g. JSON-LD `<script>` blocks) as readable words. The field now uses `parse_blocks()` on the raw post content and skips `core/html` blocks entirely before counting words.

= 1.0.21 =
* Fixed cursor jumping to end of text when editing inside the Custom HTML block textarea. Cause was the block editor's Redux store propagating attribute updates back as new props, causing React to reset the textarea cursor on each keystroke. The textarea value is now buffered in local component state; undo/redo sync from the block store is handled separately via a effect.

= 1.0.20 =
* Fixed media library "View" links pointing to a broken frontend URL instead of the actual file. WordPress builds attachment page URLs by appending the attachment slug to the parent post's permalink; because post link filters run in admin contexts, the parent permalink was being rewritten to the frontend domain, cascading into a non-existent URL. A new `attachment_link` filter now returns the direct file URL instead, which is the correct behaviour for a headless install where attachment pages do not exist on the frontend.

= 1.0.19 =
* Replaced the Custom HTML block's modal code editor (HTML/CSS/JS tabs) with an inline textarea and Preview toggle. The editing surface is now always visible without opening a lightbox. HTML mode shows a plain monospace textarea; Preview mode renders the HTML inline using the browser's parser (script tags are not executed in preview — they work normally in published content).

= 1.0.18 =
* Fixed "Remove" button in the Featured Image panel not working when the image came from legacy FIFU plugin meta (`fifu_image_url`). The button now correctly clears `fifu_image_url` when that is the active source, and `_mainframe_featured_image_url` when that is the source. Requires FIFU plugin to be inactive (migration scenario).
* `fifu_image_url` is now writable via REST when the FIFU plugin is not active, so the block editor can clear leftover migration data.

= 1.0.17 =
* Block editor Featured Image panel now distinguishes between a manually-set Featured Image URL (`_mainframe_featured_image_url`) and one inherited from the legacy FIFU plugin (`fifu_image_url`). The status label reflects the source ("Featured Image URL is set" vs "Inherited from FIFU").

= 1.0.16 =
* Removed `core/image` from the JS-dependent block list. The block's lightbox feature requires JS but the block itself renders without it; images should not be hidden from the inserter on a headless install.
* Block Manager now propagates JS-dependency to child blocks: if a block declares `parent` and every named parent is JS-dependent, the child is automatically classified as JS-dependent too. Handles arbitrary nesting depth (e.g. accordion sub-blocks). Previously, child blocks of JS-dependent blocks appeared in the inserter even though they were unusable without their parent.

= 1.0.13 =
* Fixed block editor "View Post / View Page" button showing the WordPress backend URL instead of the configured frontend app URL. Added a `get_sample_permalink` filter that rewrites the permalink template Gutenberg builds its editor links from, covering the toolbar View button, the permalink panel, and the post-publish panel.

= 1.0.12 =
* Added RSS/Atom feed suppression: redirects all WordPress feed URLs to the home page. Available as an onboarding checkbox (checked by default) and as a toggle under Mainframe Settings → Admin.
* Added XML sitemap suppression: disables the built-in /wp-sitemap.xml endpoint. Available as an onboarding checkbox (checked by default) and as a toggle under Mainframe Settings → Admin.
* Added Frontend App URL settings (Mainframe Settings → Frontend App): configure the consuming frontend's root URL and an optional posts base path.
* "View Post/Page" links, the post-publish panel, and the admin bar "Visit Site" link all now point to the configured frontend app URL. Link rewrites apply in both admin and REST API contexts so the block editor sees the correct URL.
* Admin bar site-name node also rewritten to the frontend app URL.
* Added "Frontend Page URL" meta box on published pages: pre-filled with {frontend_url}/{slug}, editable when the frontend path differs from the WordPress slug. Value exposed via REST API.
* Block editor Preview button hidden on desktop (JS filter) and mobile (CSS targeting editor-header__post-preview-button). Preview is not useful on a headless install because unpublished content is not accessible to the frontend.
* Pre-publish checks panel: hid the site card showing the WP backend name and URL (not relevant to the consuming frontend).
* Post-publish panel: hid the raw WP backend address input; kept View Post/Add Post buttons (now link to frontend). Header title link restored and functional (also links to frontend).

= 1.0.11 =
* Added "Suppress automatic update notification emails" option. Available as an onboarding checkbox (unchecked by default) and as a toggle under Mainframe Settings → Admin. Disables the emails WordPress sends after automatic core, plugin, and theme updates.

= 1.0.10 =
* JS-Dependent Blocks section is now a collapsed <details>/<summary> dropdown, matching the Standard Blocks section. Both sections start collapsed, and the quick-action buttons still work on their contents regardless of open/closed state.

= 1.0.9 =
* Added Block Manager settings section. Auto-detects blocks that require front-end JavaScript (via viewScript / viewScriptModule) and disables them in the editor inserter by default. Each block can be individually toggled. Fully non-destructive — existing post content is never modified.

= 1.0.8 =
* Moved "REST API Reference" and "Check for Updates" buttons below the settings page title.

= 1.0.7 =
* Reduced update check cache from 12 hours to 2 hours so new releases are detected sooner.
* Added "Check for Updates" button to Mainframe Settings — clears the update cache and forces WordPress to re-check immediately.

= 1.0.6 =
* Added `featured_media_meta` REST field to all post type responses: returns `{alt, title, caption, width, height}` for attached featured images; `null` for external URL images.
* Added `tags_info` REST field to all post type responses: returns `[{id, name, slug}]` for each assigned tag.
* Added `reading_time` REST field to all post type responses: estimated reading time in minutes based on 200 wpm.

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

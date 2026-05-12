# Mainframe - Headless WordPress Theme

Headless WordPress theme - full dashboard, full REST API, minimal public face.

**No plugins required. No build tools. No external dependencies.**

---

## Requirements

- WordPress 6.0+
- PHP 8.0+

---

## What it does

### REST API
- All registered post types are force-exposed via `show_in_rest` (opt-out available via the `mainframe_expose_post_type_in_rest` filter)
- Every REST response includes `featured_media_url` — a direct image URL, no second request needed
- `featured_media_sizes` field — map of registered size names to URLs (`thumbnail`, `medium`, `full`, …); external URLs return `{"full": url}`
- `author_info` field — `{id, name, slug, avatar_url, description, url}` on every post, no second request to `/wp/v2/users/:id`
- `ancestor_ids` field — ordered array of ancestor IDs (nearest-to-root) for hierarchical post types; `[]` for flat types
- `categories_info` field — array of `{id, name, slug}` objects for each assigned category
- `/wp-json/mainframe/v1/site` endpoint — one-call site summary: name, description, URL, logo, and all nav menus with top-level items
- Configurable `Access-Control-Allow-Origin` header for cross-origin consuming apps

### Public frontend
- Front page is a blank white canvas — optionally displays logo, headline, short message, and linktree-style link cards populated from nav menus
- Archive, search, author, and date routes always redirect to home
- Singular posts/pages redirect by default; overridable per-post via a meta box ("show content" / "redirect" / site default)
- Plugin-generated routes and custom rewrite rules are left untouched — only standard WordPress route types are redirected

### Custom login URL
- When a slug is configured, `/wp-login.php` is blocked and login is served at the custom slug (e.g. `/login`)
- All WordPress login/logout/lost-password URLs rewritten automatically
- No custom slug by default — `/wp-login.php` remains active until a slug is saved

### Admin cleanup
- Irrelevant Customizer sections hidden (Static Front Page, Menu Locations, Patterns)
- Appearance > Patterns and Menus removed from admin nav (Menus managed in Customizer)
- Settings > Reading and Discussion removed from admin nav
- Block editor Discussion panel removed
- Block editor **Preview** button removed — the WP frontend is not the consuming app
- Classic editor Preview button removed via `preview_post_link` filter
- Sensible defaults set on theme activation (see below)

### Featured Image URL field
- Per-post "Featured Image URL" field in the block editor sidebar and classic editor
- Stores an external image URL that overrides the attached featured image in REST responses
- Preview shown in the native Featured Image panel; Remove button to clear
- **FIFU compatibility**: posts previously using the [Featured Image from URL](https://wordpress.org/plugins/featured-image-from-url/) plugin will automatically display their existing images — no re-entry needed after removing FIFU

### Default featured image
- Site-wide fallback URL used in `featured_media_url` REST responses when a post has no featured image of any kind
- Configured in Appearance > Mainframe Settings — paste a URL or pick from the media library
- Never written to post meta; the post editor is unaffected

### Deploy webhook
- Fires a non-blocking HTTP POST to a configured URL whenever a post is published or un-published
- JSON body: `{event, post_id, post_type, site_url}` — consumed directly by Vercel, Netlify, Cloudflare Pages deploy hooks
- Optional secret adds an `X-Mainframe-Signature: sha256=<hmac>` header so the receiving service can verify authenticity
- 10-second site-wide cooldown prevents flooding when multiple posts are saved at once
- Configured in Appearance > Mainframe Settings (REST API section)

### Robots / sitemap hardening
- When "Discourage search engines" (`blog_public = 0`) is enabled, the WordPress core XML sitemap is disabled
- `X-Robots-Tag: noindex, nofollow` is added to all public-facing page responses when the site is set to private
- Both settings follow `blog_public` — toggled on/off during Headless Quick Setup

### Live REST API Reference
- **Appearance > REST API Reference** — a full, browseable reference page linked from a button in Mainframe Settings
- Introspects the live REST API at render time: any field added by a plugin or custom code appears automatically without any manual update
- Shows all `mainframe/v1` endpoints with their response schemas (field / type / description)
- For every `show_in_rest` post type: extra fields (from `register_rest_field`) listed prominently with **Mainframe** or **Custom** source badges; WP core fields collapsed under a togglable disclosure
- Object and array fields show their sub-property shapes inline

### Headless Quick Setup
On first activation the theme runs in **safe mode** — all WordPress content is publicly accessible at its standard URLs. A persistent admin notice points to the Quick Setup card in Mainframe Settings.

The setup card lets you opt into headless defaults one checkbox at a time:

| Option | What it does |
|---|---|
| Redirect all public routes to home | Sets Default Route Behavior to Redirect |
| Discourage search engine indexing | Sets `blog_public = 0` |
| Disable comments and pingbacks | Closes comments/pings on new posts |
| Flat upload folder structure | Disables year/month subfolders (skipped if folders exist) |
| Custom login URL slug | Sets a slug and blocks `/wp-login.php` |

All settings are reversible from Mainframe Settings after setup. Dismissing the admin notice does not apply any settings.

---

## Customizer

**Appearance > Customize > Front Page**
- **Headline** — displayed above the short message
- **Short Message** — basic HTML supported (links, bold, italic, line breaks); quicktags toolbar included

**Appearance > Customize > Site Identity**
- Logo, Site Title, Tagline (standard WordPress)

**Appearance > Customize > Menus**
- Create menus; top-level items appear as link cards on the front page in creation order
- Only Custom Links are supported as menu items (post/page/taxonomy types redirect home)

---

## Appearance > Mainframe Settings

| Option | Description | Default |
|---|---|---|
| Redirect type | HTTP 301 or 302 for frontend redirects | 301 |
| 404 behavior | Return a real 404 page or redirect home | redirect |
| Login slug | URL slug for the login page | *(empty — wp-login.php active)* |
| CORS origin | Allowed origin for REST API requests (empty = `*`) | *(empty)* |
| Default route behavior | What singular posts do by default | show |
| Default featured image | Fallback image URL for posts with no featured image | *(empty)* |
| Deploy hook URL | POST target for publish/unpublish events | *(empty)* |
| Deploy hook secret | HMAC-SHA256 signing secret for deploy requests | *(empty)* |

A **REST API Reference** button in the page header links to the live reference page.

---

## Per-post meta

Each post/page has a **Public Route Behavior** meta box:
- **Use site default** — inherits from Mainframe Settings
- **Show content** — renders the singular template (useful if you want WP to serve some pages directly)
- **Redirect to home** — always redirects regardless of site default

The `_mainframe_route_behavior` meta is exposed in the REST API.

---

## Developer hooks

### Opt a post type out of REST exposure
```php
add_filter( 'mainframe_expose_post_type_in_rest', function ( $expose, $post_type ) {
    if ( 'secret_type' === $post_type ) {
        return false;
    }
    return $expose;
}, 10, 2 );
```

### Override the redirect HTTP code at runtime
```php
add_filter( 'mainframe_redirect_code', fn() => 302 );
```

---

## File structure

```
mainframe/
├── style.css                  # Theme header (no styles)
├── functions.php              # Bootstrap loader + theme supports
├── front-page.php             # Public-facing front page template
├── singular.php               # Single post/page template (with safety redirect)
├── archive.php                # Archive safety redirect
├── 404.php                    # 404 or redirect based on setting
├── index.php                  # Required WP fallback
├── assets/
│   └── js/
│       └── featured-image-url.js  # Block editor Featured Image URL panel
└── inc/
    ├── cleanup.php            # wp_head cleanup, XML-RPC disable, admin menu cleanup
    ├── login.php              # Custom login URL slug
    ├── meta.php               # Per-post route behavior + featured image URL meta
    ├── onboarding.php         # First-run headless setup wizard
    ├── options.php            # Mainframe Settings page + Customizer
    ├── redirects.php          # Public frontend redirect logic
    ├── rest-reference.php     # Live REST API Reference admin page
    └── rest.php               # REST API exposure + CORS + all extra REST fields + site endpoint + deploy webhook
```

---

## License

GPL-2.0-or-later — same as WordPress.

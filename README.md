# Mainframe

Headless WordPress theme — full dashboard, full REST API, minimal public face.

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
- Configurable `Access-Control-Allow-Origin` header for cross-origin consuming apps

### Public frontend
- Front page is a blank white canvas — optionally displays logo, headline, short message, and linktree-style link cards populated from nav menus
- All archive, search, author, date, and singular routes redirect to home by default
- Per-post route behavior overridable via a meta box ("show content" / "redirect" / site default)

### Custom login URL
- `/wp-login.php` is blocked; login is served at a configurable slug (default: `/login`)
- All WordPress login/logout/lost-password URLs rewritten automatically

### Admin cleanup
- Irrelevant Customizer sections hidden (Static Front Page, Menu Locations, Patterns)
- Appearance > Patterns and Menus removed from admin nav (Menus managed in Customizer)
- Settings > Reading and Discussion removed from admin nav
- Block editor Discussion panel removed
- Sensible defaults set on theme activation (see below)

### Featured Image URL field
- Per-post "Featured Image URL" field in the block editor sidebar and classic editor
- Stores an external image URL that overrides the attached featured image in REST responses
- Preview shown in the native Featured Image panel; Remove button to clear

### Defaults set on theme activation
These are applied once when the theme is activated and do not affect existing content:

| Setting | Value | Reason |
|---|---|---|
| `uploads_use_yearmonth_folders` | `0` | Flat upload structure (skipped if year folders already exist) |
| `blog_public` | `0` | Discourage search engine indexing of the backend |
| `default_pingback_flag` | `0` | No outbound ping notifications |
| `default_ping_status` | `closed` | No inbound pingbacks/trackbacks on new posts |
| `default_comment_status` | `closed` | No comments on new posts by default |

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
| 404 behavior | Return a real 404 page or redirect home | 404 |
| Login slug | URL slug for the login page | `login` |
| CORS origin | Allowed origin for REST API requests (empty = `*`) | *(empty)* |
| Default route behavior | What singular posts do by default | redirect |

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
    ├── options.php            # Mainframe Settings page + Customizer
    ├── redirects.php          # Public frontend redirect logic
    └── rest.php               # REST API exposure + CORS + featured_media_url field
```

---

## License

GPL-2.0-or-later — same as WordPress.

# AGENTS.md

## What this is

WordPress webroot (`app/public/`) managed by **Local** (LocalWP). Not a traditional repo — no git, no npm, no composer, no build pipeline. CSS/JS are loaded directly; no compilation step.

**Read `CLAUDE.md` for full architecture reference.** This file supplements it with agent-specific gotchas.

## Where custom code lives

Only two directories — everything else is WordPress core or third-party plugins:

- `wp-content/themes/vatan-event/` — the **entire product**. Standalone theme, not a child theme.
- `wp-content/mu-plugins/` — glue + one-shot seeders/admin tools.

**Never edit** `wp-admin/`, `wp-includes/`, or top-level `wp-*.php` — they get overwritten on WP upgrades.

## CLI tooling

There is no npm, no build command, no linter, no test suite. All CLI work uses **WP-CLI** via Local's "Open site shell":

```
wp plugin list
wp option get vatan_theme_settings --format=json
wp post list --post_type=event
wp pll lang list
wp db export / wp db import
```

No `package.json`, no `composer.json`, no CI workflows exist in this repo.

## Critical conventions

- **Polylang, NOT WPML.** The multilingual layer is Polylang. Code integrates via `pll_set_post_language`, `pll_save_post_translations`, `-fa` slug suffix on Persian clones. Ignore any older doc or comment that says WPML.
- **Polylang hook timing.** Custom term-meta hooks must run at priority **≥ 50** so Polylang's clone hooks (priority 10) fire first. The `-fa` clones get their meta filled on save.
- **ACF fields are programmatic.** Defined in `inc/acf-fields.php` (theme) and `inc/music/acf-fields.php`. Do **not** add field groups via wp-admin and leave them DB-only.
- **WooCommerce-first for money.** Do not invent cart/checkout/payment flows — hook WC. The seat-hold table (`{prefix}vatan_seat_holds`) exists because WC's stock model doesn't fit per-seat allocation.
- **Function prefix:** all theme functions use `vatan_`. Text domain is `vatan-event` on every user-facing string.
- **Currency is GBP** (set by the events seeder). Don't assume USD.
- **Theme is the product.** Stay inside `wp-content/themes/vatan-event/` unless adding cross-cutting glue (then use `wp-content/mu-plugins/`).

## Module load order

`functions.php` requires ~20 modules under `inc/`. Order matters:

`helpers → i18n → CPTs → ACF → music/* → seat-holds → WC → REST → AJAX → newsletter → view-counter → create-event → my-events → earnings → page-builder → admin-panel → checkin → auth → admin-dashboard`

When adding a new `inc/` module, `require_once` it from `functions.php` in the correct dependency position.

## Auth & admin access

- Branded login/signup at `/login/` and `/signup/` — never link to `wp-login.php`.
- wp-admin is locked to an allow-list: `VATAN_WP_ADMIN_USER_IDS` constant in `wp-config.php`. If undefined, only user ID 1 gets in.
- One-shot admin tools follow: `add_action('admin_init', ...)` → `$_GET['vatan_*']` gate → `current_user_can('manage_options')` → run → `wp_die()` with HTML report.

## Mu-plugin seeders (one-shot, destructive)

| Trigger | Effect |
|---|---|
| `?vatan_seed_events=1` | **Wipes events**, switches currency to GBP, seeds demo data |
| `?vatan_seed_content=1` | Seeds homepage value-props + 10 blog posts |
| `?vatan_seed_static=1` | Populates About/Contact pages |
| `?vatan_seed_media=1` (`&force=1` to re-run) | Downloads Pexels images (needs VPN from Iran) |
| `?vatan_seed_city_photos=1` | Pulls Wikipedia city photos into Media Library |
| `?vatan_clone_translations=1` | Clones all FA content to EN as Polylang pairs |
| `?vatan_tax_audit=1` etc. | Taxonomy dedup/fix tools |

All gate on `current_user_can('manage_options')`. Treat as scaffolding — delete or keep dormant once production data exists.

## Key non-obvious patterns

- **Seat holds** (`inc/seat-holds.php`): race-safe UNIQUE table, 15-min TTL, token resolution via `user:{ID}` or `cookie:{token}`. Filter: `vatan_seat_hold_ttl`.
- **Check-in** (`inc/checkin.php`): REST `POST /vatan/v1/checkin`, QR payload format `VATAN:{order_id}:{item_id}:{hash}`.
- **REST namespace**: `vatan/v1`. Front-end JS uses `vatanData.restUrl` + `vatanData.restNonce`.
- **Page builder**: layouts stored in option `vatan_page_layouts` (JSON, keyed by page slug). Admin UI in `inc/admin/page-builder.php`, renderer in `front-page.php`.
- **Theme Settings**: option `vatan_theme_settings`, read via `vatan_get_setting( $key, $default )`.
- **Music player**: on-demand, gated by `music_player_app_enabled` / `music_player_web_enabled` toggles. App detected via `VatanTicketApp` User-Agent token. Preview with `?vatan_app_preview=1` (admins only).

## Capacitor app (separate project)

The mobile app lives at `C:\Users\sutso\Projects\vatan-ticket-app` — **not** inside this webroot.

- **Architecture**: thin native shell that loads `https://vatantiket.com` over HTTPS. All UI/routing/checkout lives on the server; no SPA bundling.
- **App ID**: `com.vatantiket.app`. User-Agent token: `VatanTicketApp/1.0`.
- **App-only features**: the WP side detects the UA token and enables music player + app-only nav items. Controlled by `music_player_app_enabled` toggle in Theme Settings.
- **Admin panel in app**: the app includes a frontend `/admin/` link for admin/shop-manager users.
- **Build**: `npx cap sync` to copy web assets, then build via Android Studio / Xcode.

When editing music player code (`inc/music/player.php`, `assets/js/music-player.js`), remember the player renders in both web (gated off by default) and app contexts.

## Custom DB tables

- `{prefix}vatan_seat_holds` — seat reservations (created on theme activation)
- `{prefix}vatan_newsletter_*` — newsletter subscribers

Use the helpers in `inc/seat-holds.php` / `inc/newsletter.php` — don't raw `$wpdb`.

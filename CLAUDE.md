# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This directory is the **WordPress webroot** (`app/public/`) of a site managed by **Local** (by Flywheel / LocalWP). It is not a traditional source repository — it contains the full WordPress core checkout (`wp-admin/`, `wp-includes/`, `wp-*.php`) plus `wp-content/`. There is no git repo, no root `package.json`, no `composer.json`, and no build pipeline.

Custom project code lives only under:
- `wp-content/themes/vatan-event/` — the **custom standalone theme** (not a child theme)
- `wp-content/mu-plugins/` — site-glue + one-shot seed/admin tools
- `wp-content/plugins/` — third-party plugins only; no custom plugins yet

Do **not** edit files in `wp-admin/`, `wp-includes/`, or the top-level `wp-*.php` core files — those are WordPress core and get overwritten on upgrade.

## Site state

The site is **"Vatan Ticket / Vatan Event"** — a bilingual (Persian primary, English secondary) event ticketing platform built on WordPress + WooCommerce.

- **URL**: `http://vatan-ticket.local` (Local). Production target domain is `vatantiket.com` / `vatanevent.com`.
- **Active theme**: `vatan-event` (custom — not the default block theme). Other `twentytwenty*` directories are stock WP and unused.
- **Active plugins**:
  - `polylang` + `polylang-pro` + `polylang-wc` — **multilingual layer (Polylang, NOT WPML)**. This is a real divergence — older docs and seed comments sometimes say "WPML"; the codebase actually integrates with Polylang (`pll_set_post_language`, `pll_save_post_translations`, `-fa` slug suffix handling).
  - `advanced-custom-fields` + `advanced-custom-fields-pro` (ACF Pro is installed; field groups are defined **programmatically in `inc/acf-fields.php`** — there is no `acf-json/` sync directory).
  - `woocommerce` — tickets are WC products; cart + checkout + payments go through WC. Currency is configurable; the events seeder forces it to GBP, so don't assume USD.
  - `custom-post-type-ui` is installed but **inactive** — CPTs/taxonomies are registered in code (`inc/custom-post-types.php`), not via CPTUI.
- **Localization**: Farsi (`fa_IR`) is the primary locale. Polylang manages FA/EN content pairs. UI strings live in the `vatan-event` text domain.

## High-level architecture

### Theme bootstrap

`wp-content/themes/vatan-event/functions.php` is the top of the world — it `require_once`s ~20 modules under `inc/`. Each module owns one concern; load order matters (helpers → i18n → CPTs → ACF → seat-holds → WC → REST → AJAX → ...). Read `functions.php` first when orienting; it has inline notes on why each include exists.

### Custom post types & taxonomies (`inc/custom-post-types.php`)

- **`event`** CPT — the unit of ticketed content. Public, archived at `/events/`, REST-enabled, supports title/editor/thumbnail/excerpt.
- **`organizer`** CPT — entity producing events. Linked to events via ACF `event_organizer` post_object field. Has `single-organizer.php` template.
- **`event_category`** taxonomy — hierarchical genre (concert / theater / standup / etc.). Has per-term `vatan_emoji` meta, auto-populated from a slug-to-emoji map covering English + Persian names. Backfill via `?vatan_emoji_backfill=1`.
- **`event_city`** taxonomy — hierarchical Country > City. Top-level (country) terms get a `vatan_country_flag` emoji (auto-derived from ISO alpha-2 via slug/name map) and a `vatan_city_image_id` cover image.
- Auto-flag/emoji hooks fire at priority **50** specifically so Polylang's term-clone hooks (priority 10) run first — the `-fa` clones then also get their meta filled on save.

### Ticketing pipeline (the load-bearing part)

`inc/seat-holds.php` implements **race-safe temporary seat reservations** bridging Add-to-cart → Order:
- Custom table `{prefix}vatan_seat_holds` with `UNIQUE(event_id, seat_key)` to prevent double-booking under concurrent requests.
- Holds last **15 minutes** (filter: `vatan_seat_hold_ttl`).
- Token resolution: logged-in users → `user:{ID}`; guests → `cookie:{token}` (cookie `vatan_hold_token`, 1-day expiry).
- `inc/woocommerce.php` hooks the WC cart/order flow against this table so a stale hold can't be checked out and a real order converts the hold into ownership.

QR-based check-in (`inc/checkin.php`) closes the loop: REST `POST /vatan/v1/checkin` (requires `manage_woocommerce`) verifies a `VATAN:{order_id}:{item_id}:{hash}` payload, stamps `_vatan_checked_in_at` order item meta, and rejects already-used tickets. A "Door Scanner" admin page lives under the Vatan Event menu.

### REST API (`inc/rest-api.php`)

Namespace: **`vatan/v1`**. Routes cover events search (with q / city / country / category / date / lang filters), seat-map fetch, add-to-cart with seat holding, newsletter signup, and check-in. Front-end JS hits these via `vatanData.restUrl` + `vatanData.restNonce` (localized in `functions.php`).

### Custom auth + wp-admin lockout

`inc/auth.php` replaces `wp-login.php` with **branded `/login/` and `/signup/` pages** (rendered by `page-login.php` / `page-signup.php`). Form processing runs on `init` priority 1 for early redirect; nonces are `vatan_login_nonce` / `vatan_signup_nonce`.

`inc/admin-dashboard.php` provides a front-end `/admin/` dashboard and **locks non-allow-listed users out of `wp-admin/`**:
- Allow-list is the constant `VATAN_WP_ADMIN_USER_IDS` (comma-separated user IDs) defined in `wp-config.php`. If undefined, only user ID 1 keeps wp-admin access.
- Multisite super-admins bypass; AJAX/REST/cron requests are exempt.

### Page builder + theme settings

`inc/page-builder.php` is a homepage component registry. Layouts are stored in the option **`vatan_page_layouts`** as JSON (keyed by page slug). The admin UI lives in `inc/admin/page-builder.php`; the front-end renderer is called from `front-page.php`.

Other admin sub-pages under the "Vatan Event" menu, registered from `inc/admin-panel.php`:
- **Theme Settings** (`inc/admin/theme-settings.php`) — option `vatan_theme_settings` holds hero slides, social links, color scheme, etc. Read with `vatan_get_setting( $key, $default )`.
- **Seat Manager** (`inc/admin/seat-manager.php`) — per-event seat-map editor.
- **Sales Analytics** (`inc/admin/sales-analytics.php`).
- **Door Scanner** — see check-in above.

### Asset enqueue pattern

Defined in `functions.php::vatan_enqueue_assets`:
- Always: `assets/css/main.css` + `components.css` (+ `rtl.css` when `is_rtl()`), `assets/js/main.js` + `search.js` + `animations.js`.
- On `is_singular('event')`: `assets/js/seat-map.js`.
- On the `create-event` page: `assets/js/create-event.js` + the admin seat-editor (`assets/admin/js/seat-editor.js`) + the planner bridge (`assets/js/seat-planner-create.js`), plus `assets/admin/css/admin.css`. The create-event form intentionally reuses the wp-admin Seat Manager UI.
- Localized JS payload `vatanData` carries REST URL, nonces, locale/lang, currency symbol+position+decimals, tax rate (filter `vatan_tax_rate`, default 0.09), max selectable seats, and i18n strings.

### mu-plugins (`wp-content/mu-plugins/`)

A mix of **always-on glue** and **one-shot seeders/admin tools**:

| File | Purpose |
|---|---|
| `vatan-translation-clone.php` | One-shot: `?vatan_clone_translations=1`. Clones every FA event/page/post/organizer to EN and links them as Polylang pairs. |
| `vatan-taxonomy-cleanup.php` | Admin tools: `?vatan_tax_audit=1`, `?vatan_tax_merge=1`, `?vatan_tax_set_lang=1`, `?vatan_tax_set_flags=1`, `?vatan_tax_debug_search=1` — for deduping/fixing Polylang term pairs. |
| `vatan-events-seed.php` | One-shot: `?vatan_seed_events=1`. **Destructive** — wipes events, switches currency to GBP, seeds the demo tour. |
| `vatan-content-seed.php` | One-shot: `?vatan_seed_content=1`. Seeds homepage value-props block + 10 blog posts. |
| `vatan-static-pages-seed.php` | One-shot: `?vatan_seed_static=1`. Populates About/Contact. |
| `vatan-media-seed.php` | One-shot: `?vatan_seed_media=1` (`&force=1` to re-run). Downloads Pexels images. Outbound network — requires VPN if running from Iran. |
| `vatan-city-photos-seed.php` | One-shot: `?vatan_seed_city_photos=1`. Pulls Wikipedia city landmark photos into Media Library. |

All one-shot tools self-gate on `current_user_can('manage_options')`. Treat them as scaffolding — they should be deleted (or kept dormant) once the production data is real.

### Music player + Capacitor integration

The site ships a small on-demand music player (mini-bar + slide-up panel) backed by three CPTs (`track`, `album`, `artist`) and the `music_genre` taxonomy. Module lives at `inc/music/` (post-types, ACF, REST under `vatan/v1/music/*`, render gate + asset enqueue in `player.php`). The engine is one vanilla-JS file at `assets/js/music-player.js`; styles at `assets/css/music-player.css`.

**Page-builder block**: a `music_hero` component is registered via the `vatan_page_builder_components` filter (in `inc/music/player.php`). It renders an elegant featured-album card on the homepage — clicking the play button starts the album in the mini-bar, clicking the card opens the music panel into the album view. Wired via document-level click delegation on `[data-vatan-music-action]` attributes. Required a `post_select` prop type, added to `inc/page-builder.php` alongside the existing `taxonomy_select`.

**Visibility model**: two independent toggles on Theme Settings → **Music** tab:
- `music_player_app_enabled` — show in the Capacitor app (default ON)
- `music_player_web_enabled` — show on the public website (default OFF)

App detection uses `vatan_is_app_request()` in `inc/music/player.php`, which checks the User-Agent for the token **`VatanTicketApp`** that the Capacitor shell appends. The whole render pipeline (markup, CSS, JS, REST hits) is skipped server-side when the gate is closed — web visitors with the player off receive zero bytes related to it.

**Admin preview**: visit any front-end URL with `?vatan_app_preview=1` (admins only) to render the app-context view from a desktop browser without spoofing UA. Useful while the web toggle is off.

**Live radio streams**: live-stream tracks are first-class catalog items — set the `track_is_live_stream` ACF field to true on a Track post and put the stream URL in `track_external_url`. There is no separate "radio" plugin or `VATAN_RADIO_STREAM_URL` constant; the previous `vatan-app-radio.php` mu-plugin was removed and its job folded into the music module.

## Custom database tables

Created on theme activation (and idempotently checked on `plugins_loaded`):
- `{prefix}vatan_seat_holds` — temporary seat reservations (see Ticketing pipeline).
- `{prefix}vatan_newsletter_*` — newsletter subscribers (schema version in option `vatan_newsletter_schema_version`).

When working with seat or newsletter logic, prefer the existing helpers in `inc/seat-holds.php` / `inc/newsletter.php` over raw `$wpdb` calls so the UNIQUE constraints and TTL semantics are preserved.

## wp-config.php constants the project reads

Add between the "stop editing" line and the database settings:
- `VATAN_WP_ADMIN_USER_IDS` — comma-separated list of user IDs allowed into `wp-admin/`. Required if you want anyone other than user 1 to reach the admin.

## Local environment

This site is run by **Local** — there is nothing to "build" or "start" from the CLI. Use the Local app (or `wp` via Local's site shell) to start/stop services and open the site.

- DB connection (`wp-config.php`): `DB_NAME=local`, `DB_USER=root`, `DB_PASSWORD=root`, `DB_HOST=localhost`. Local defaults — don't change.
- `WP_ENVIRONMENT_TYPE` is `local`. `WP_DEBUG` is off by default but guarded by `if ( ! defined( ... ) )`, so it can be flipped via mu-plugin or by editing `wp-config.php` directly.
- Server configs (nginx / php / mysql) are managed by Local under `../../conf/` (sibling of `public/`); don't hand-edit — change them through Local's UI so they survive restarts.
- A SQL snapshot lives at `../sql/local.sql` (sibling of `public/`). It's Local's export, not a migration script — read-only context, don't apply by hand.

## Working with WordPress from the CLI

There is no project-defined build / lint / test command. For WP-level operations, use **WP-CLI** from Local's "Open site shell". Useful in this codebase specifically:

- `wp plugin list` / `wp theme list` — confirm active stack matches the doc.
- `wp option get vatan_page_layouts --format=json` — inspect homepage layout.
- `wp option get vatan_theme_settings --format=json` — inspect Theme Settings.
- `wp post list --post_type=event` / `wp post list --post_type=organizer` — content inspection.
- `wp pll lang list` (Polylang WP-CLI) — list languages.
- `wp eval 'echo wp_count_posts("event")->publish;'` — quick counts.
- `wp db export` / `wp db import` — DB snapshots (prefer over editing `../sql/local.sql` by hand).

If WP-CLI isn't available, fall back to wp-admin at `http://vatan-ticket.local/wp-admin/`.

## Conventions for new code

- **Stay inside the `vatan-event` theme** unless you're adding cross-cutting glue that doesn't belong to the theme (then use `wp-content/mu-plugins/`). There's no expectation of theme-swappability — this theme is the product.
- New `inc/` modules: drop in, `require_once` from `functions.php` in dependency order, prefix functions `vatan_`, use the `vatan-event` text domain on every user-facing string. Match the existing inline-doc style (file-level docblock explaining the *why*, brief docblocks above each function).
- **Polylang-aware everything**: when registering taxonomies or saving terms/posts, expect a `-fa` slug suffix on the Persian clone and run any custom term meta hook at priority ≥ 50 so it fires after Polylang's clone. When linking translations programmatically, use Polylang's `pll_set_post_language` / `pll_save_post_translations`.
- **WooCommerce-first for money**: do not invent cart/checkout/payment flows — hook WC. The seat-hold table exists because WC's stock model doesn't fit per-seat allocation; reuse it rather than building a parallel reservation system.
- **ACF is programmatic** (`inc/acf-fields.php`). Don't add field groups via wp-admin and leave them DB-only — either extend that file or, if you want Local JSON sync, create `wp-content/themes/vatan-event/acf-json/` and enable sync in ACF settings.
- **Front-end auth pages own login/signup**; don't link users to `wp-login.php`. The branded pages are `/login/` and `/signup/`.
- **Admin one-shot tools** follow the established pattern: `add_action('admin_init', ...)` → gate on a `$_GET['vatan_*']` flag → `current_user_can('manage_options')` → run → `wp_die()` with an HTML report. Mirror this when adding new dev/admin utilities.

# Master Prompt — Vatan Event Platform

You are about to build **Vatan Event**, a professional bilingual (Persian/English) event ticketing platform built on WordPress + WooCommerce. This is the project briefing. Do not write any code yet — just read, confirm your understanding, and wait for the step-by-step prompts.

---

## Project Overview

**Vatan Event** is an Iranian event ticketing platform (think Eventbrite for Persian-speaking audiences worldwide). Users can browse events, select seats on an interactive seat map, purchase tickets, and manage their bookings — all in Persian (RTL) or English (LTR).

---

## Tech Stack

| Layer | Technology |
|---|---|
| CMS | WordPress 6.x |
| E-commerce | WooCommerce |
| Custom Fields | Advanced Custom Fields (ACF) Free |
| Multilingual | Polylang |
| Local Dev | LocalWP (or XAMPP) |
| Frontend | Vanilla JS + Custom CSS (no page builder) |
| Admin Panel | Custom WordPress Admin Pages (Settings API) |
| SEO | Yoast SEO plugin |

---

## What You Will Build

### 1. Custom WordPress Theme (`vatan-event`)
A fully custom theme — no Elementor, no Gutenberg blocks, pure PHP templates + CSS + JS. The theme must:
- Be RTL-first (Persian default), with LTR support for English
- Use CSS custom properties (design tokens) for all colors, spacing, typography
- Be fully responsive (mobile-first)
- Load Vazirmatn font (Persian) + Inter (English)

### 2. Custom Post Type: `event`
A dedicated CPT for events with rich ACF meta fields:
- Event date, time, venue, duration, age limit
- Ticket types (repeater): name, price, color, capacity
- Interactive seat map configuration (JSON-based)
- Gallery, status (upcoming/ongoing/finished/cancelled)

### 3. Pages to Build

| Page | Template File |
|---|---|
| Homepage | `front-page.php` |
| Event Listing | `archive-event.php` |
| Single Event | `single-event.php` |
| Checkout | `page-checkout.php` |
| My Account / My Tickets | WooCommerce override |
| 404 | `404.php` |

### 4. Key UI Components
- **Hero Section**: Full-screen dark background (concert photo), gradient overlay, headline with highlighted keywords, CTA buttons, floating search bar
- **Search Bar**: AJAX-powered, filters by city/date/category, debounced (300ms)
- **Event Card**: Image with category badge + days-left badge + bookmark button, venue/date info, price, buy button
- **Category Icons Row**: Horizontal scroll, emoji/icon + label for each category
- **Seat Map**: Interactive grid — economy / special (CIP) / VIP zones, click-to-select, reserved seats blocked, real-time price summary panel
- **Ticket Sidebar**: Sticky sidebar on event page — ticket types with color dots, seat selector, refund guarantee notice
- **Newsletter Section**: Email subscription with background gradient
- **Footer**: 4-column, logo + about, quick links, support links, app download buttons (App Store / Google Play), social icons

### 5. Custom Admin Panel
A full custom admin area accessible from the WordPress dashboard sidebar under **"وطن ایونت"**, with these sections:

- **Dashboard**: Sales overview, recent orders, top events
- **Theme Settings**: Logo, primary color, hero content, footer content, social links — everything editable without touching code
- **Seat Manager**: Per-event seat map editor, block/unblock seats, capacity stats
- **Sales Analytics**: Chart.js charts — daily revenue, tickets sold, comparison with previous period, CSV export

### 6. WooCommerce Integration
- Custom product type: `event_ticket`
- Each ticket purchase stores: event ID, ticket type, seat numbers, event date
- Checkout additions: national ID field (for Iranian events)
- Post-purchase: email confirmation with ticket details + QR code
- "My Tickets" tab in WooCommerce My Account

### 7. Bilingual Support (Persian / English)
- All theme strings wrapped in `__()` / `_e()`
- Language files: `fa_IR.po/.mo` + `en_US.po/.mo`
- RTL/LTR auto-switch based on `html[lang]`
- ACF fields duplicated for both languages: `_fa` / `_en` suffix
- Language switcher in header with flag icons

### 8. REST API Endpoints
```
GET  /wp-json/vatan/v1/events           List & filter events
GET  /wp-json/vatan/v1/events/{id}      Single event details
GET  /wp-json/vatan/v1/seats/{id}       Seat availability
POST /wp-json/vatan/v1/add-ticket       Add ticket to WooCommerce cart
```

---

## Visual Design Reference

### Color Palette
```css
--color-primary:     #FF2D78   /* Hot pink — CTAs, highlights */
--color-secondary:   #7C3AED   /* Purple — accents */
--color-dark:        #0D0D1A   /* Near-black — main background */
--color-dark-card:   #1A1A2E   /* Card backgrounds */
--color-dark-border: #2A2A40   /* Borders, dividers */
--color-text-primary:   #FFFFFF
--color-text-secondary: #A0A0B8
--color-seat-economy:   #06B6D4   /* Cyan */
--color-seat-special:   #8B5CF6   /* Purple */
--color-seat-vip:       #F59E0B   /* Amber/Gold */
--color-seat-reserved:  #374151   /* Gray */
--color-seat-selected:  #FF2D78   /* Pink */
```

### Typography
- Persian: **Vazirmatn** (Google Fonts)
- English: **Inter** (Google Fonts)
- Hero title: ~56px bold, right-aligned (RTL)
- Card title: ~18px semibold

### General Style
- Dark theme throughout
- Rounded cards (`border-radius: 12px`)
- Subtle card hover: `translateY(-4px)` + deeper shadow
- Pink glow effects on featured elements
- Backdrop blur on sticky header

---

## File Structure
```
wp-content/themes/vatan-event/
├── style.css
├── functions.php
├── index.php
├── front-page.php
├── single-event.php
├── archive-event.php
├── page-checkout.php
├── header.php
├── footer.php
├── 404.php
├── search.php
├── /assets/
│   ├── /css/
│   │   ├── main.css
│   │   ├── rtl.css
│   │   └── components.css
│   ├── /js/
│   │   ├── main.js
│   │   ├── seat-map.js
│   │   └── search.js
│   └── /fonts/
├── /template-parts/
│   ├── hero.php
│   ├── event-card.php
│   ├── search-bar.php
│   ├── seat-map.php
│   └── newsletter.php
├── /inc/
│   ├── custom-post-types.php
│   ├── acf-fields.php
│   ├── woocommerce.php
│   ├── admin-panel.php
│   ├── rest-api.php
│   ├── ajax-handlers.php
│   └── helpers.php
├── /languages/
│   ├── fa_IR.po
│   ├── fa_IR.mo
│   ├── en_US.po
│   └── en_US.mo
└── /woocommerce/        (WooCommerce template overrides)
```

---

## Coding Standards

- **PHP**: Follow WordPress Coding Standards. Use `vatan_` prefix for all functions, hooks, and options. Sanitize all inputs, escape all outputs.
- **CSS**: BEM naming convention. Use CSS custom properties — never hardcode colors or font sizes.
- **JavaScript**: Vanilla JS only (no jQuery beyond what WP loads). Use `const`/`let`, arrow functions, async/await for AJAX.
- **Security**: Nonces on all AJAX/REST calls. Capability checks on all admin actions.
- **Performance**: Enqueue scripts with proper dependencies and versioning. Use `wp_localize_script` to pass PHP data to JS.

---

## Plugins — Already Installed (assume these exist)
1. WooCommerce
2. Advanced Custom Fields (ACF) — Free
3. Polylang
4. Yoast SEO
5. Custom Post Type UI *(optional — we'll register CPTs in code)*

---

## What to Do Now

**Nothing yet.** This was the project briefing.

Reply with:
1. A summary of what you understood
2. Any clarifying questions you have
3. Confirm you're ready for **Prompt 1**

The step-by-step build prompts will follow one by one.

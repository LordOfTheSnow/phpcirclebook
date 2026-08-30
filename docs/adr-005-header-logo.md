# ADR-005: Optional Header Logo

**Status:** Accepted  
**Date:** 2026-08-30  
**Context:** Operators want to brand their instance with a logo next to the app name in the header. Like the other visitor-facing customisations (`APP_DESCRIPTION`, `APP_FOOTER`, the sidebar), it must be optional and configured without code changes, and it must degrade cleanly when unset.

---

## Summary

Add a logo to the left of the app name in the main page header, configured via `APP_LOGO` in `.env`. An empty value shows no logo; any value is used as the logo. The shipped `.env.example` defaults `APP_LOGO` to `favicon.svg`, so a fresh install shows the bundled favicon out of the box while operators can blank it to hide the logo or point it at their own image. The logo auto-scales to the header height, may exceed it by at most 40px, and when it is taller than the title the header row grows while its contents stay vertically centred.

---

## Decisions

### 1. Configuration: `APP_LOGO` in `.env`, Favicon Default via `.env.example`

A single optional `APP_LOGO` variable, consistent with the other per-instance display settings and excluded from the dotenv `required()` assertions. Its resolution is deliberately simple, with just two cases:

- **empty / unset** — render no logo.
- **any value** — treat as the logo (see Decision 2).

The "default to the favicon" behaviour lives in configuration rather than code: the shipped `.env.example` sets `APP_LOGO="favicon.svg"`, so a fresh install shows the bundled favicon, while an operator can blank the value to hide the logo. Keeping the fallback out of `logoSrc()` avoids a magic hidden default and a special opt-out token; the helper does exactly what the value says.

### 2. Value: Filename Served From `public/`, or an Absolute URL

A custom value is resolved by the `logoSrc()` helper: an `http(s)://` value is used verbatim (e.g. a CDN-hosted logo); anything else is treated as a static asset served from the web root with leading slashes trimmed (e.g. `logo.png`, dropped next to `favicon.svg`). Trimming leading slashes keeps the reference document-root-relative so it resolves under both supported docroot layouts (docroot = `public/`, or docroot = project root with the `.htaccess` fallback). For a custom raster/vector logo the operator supplies their own file; the built-in default reuses the favicon that already ships.

### 3. Sizing: Fit the Header, Cap at Header + 40px, Preserve Aspect Ratio

The header defines a baseline height as a CSS custom property (`--app-header-height`). The logo uses `height: auto; width: auto; max-height: calc(var(--app-header-height) + 40px)` so it:

- scales down large images to at most the header height plus 40px,
- shows smaller images at their natural size (no forced upscaling),
- always preserves aspect ratio,
- never overflows horizontally (`max-width: 100%`).

### 4. Vertical Alignment: Row Grows to the Logo, Contents Stay Centred

The header is a flexbox row with `align-items: center`. When the logo is taller than the title, the row height grows to the logo and the title (and the right-hand attribution) remain vertically centred against it — satisfying "the header should centre vertically to the logo size" with no extra JavaScript or measurement.

### 5. Markup: `.app-brand` Group

The logo and the `<h1>` app name are wrapped in an `.app-brand` flex group on the left, so the existing right-aligned "powered by" attribution is unaffected and the space-between layout still holds. The logo's `alt` text is the app name.

### 6. Scope: Main Page Only

The logo appears on the visitor-facing main page header, not the admin page. This matches ADR-003's treatment of the admin tool as a minimal single-operator surface; branding is a visitor concern.

---

## Consequences

- **Fresh installs get the favicon by default** via `.env.example`; an empty `APP_LOGO` hides the logo, and any value overrides it. The code path is a straight "empty means none, value means that value" with no hidden default or special tokens.
- **No new dependency, no build step:** a plain `<img>` plus a few CSS rules; the operator drops a file in `public/`, points at a URL, or relies on the bundled favicon default.
- **New optional `.env` variable `APP_LOGO`**, documented in `.env.example` and the README, and excluded from `required()`.
- **Responsive:** the logo shrinks with the viewport (`max-width: 100%`) and the header still stacks on narrow screens via the existing mobile rules.
- **Limitations:** the app does not resize or optimise the source image; an operator supplying a very large file serves that file as-is (only the displayed size is capped). No logo is provided by default.

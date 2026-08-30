# ADR-004: Configurable Sidebar with Events and Links Cards

**Status:** Accepted  
**Date:** 2026-08-30  
**Context:** Operators want to show supplementary information alongside the main registration form: a list of upcoming events and a set of relevant links. This content is per-instance, changes over time, and must be easy to edit without touching code. It should be optional — an instance that configures neither card should look exactly as it does today.

---

## Summary

Introduce an optional sidebar on the **main form page only**. The sidebar can hold two cards — **Upcoming Events** and **Links** — each rendered from an operator-authored Markdown file. A card appears only when its content file exists and is non-empty; if neither is configured, no sidebar renders and the form spans the full width. The sidebar side (left or right) is configured in `.env`.

Content is authored in Markdown and rendered to HTML with `league/commonmark` in a hardened, HTML-stripping configuration. Rendering is centralised in a small `App\SidebarContent` class.

---

## Decisions

### 1. Content Source: Dedicated Files (for now)

Card content lives in files under a new `content/` directory: `content/events.md` and `content/links.md`. This fits multi-line, multi-item content far better than `.env` (which suits single scalar values like `APP_NAME`), and the "show only if configured" rule maps naturally to "the file exists and is non-empty".

**Future extension (not implemented):** the same content could later be stored in the database and edited through the admin tool (see ADR-003), giving operators a web UI instead of file editing. The `App\SidebarContent` renderer is deliberately decoupled from the file source so a future DB-backed path can reuse the same safe renderer. This is recorded in the README as a possible enhancement.

### 2. Content Format: Markdown

Content files are Markdown, not plain text or raw HTML. Plain text cannot express the clickable links a Links card needs; raw HTML would be flexible but is an XSS hazard the moment content might arrive from a less-trusted source (e.g. the future admin-editable path). Markdown is operator-friendly (`- [Label](url)`) and expresses everything both cards need.

### 3. Markdown Parser: `league/commonmark`

`league/commonmark` (`^2.10`) is the de-facto standard PHP Markdown library: actively maintained, CommonMark-spec compliant, PSR-4/Composer native, and it ships a documented safe-mode configuration. Alternatives were rejected: `erusev/parsedown` is effectively maintenance-only and needs extra care for XSS; `michelf/php-markdown` is less active and not spec-compliant. `^2.10` is chosen deliberately to include the fix for CVE-2025-46734 (an XSS in the optional Attributes extension, which this app does not enable, but pinning forward avoids shipping a flagged version).

### 4. Safe Mode: Strip Raw HTML, Disallow Unsafe Links

The converter is configured with `html_input: 'strip'` and `allow_unsafe_links: false`. Although content is operator-authored today (the same trust level as `.env`), the events/links use cases are fully expressible in pure Markdown, so allowing raw HTML buys little and adds risk. Locking to safe mode now means the eventual DB/admin-editable path (Decision 1) inherits a safe renderer for free, rather than becoming a stored-XSS vector.

### 4a. Links Open in a New Tab

All links in the sidebar cards (both Events and Links) open in a new browser tab. This is done with CommonMark's bundled `ExternalLinkExtension` (`open_in_new_window: true`), not by asking operators to write raw HTML — which safe mode strips anyway. The extension emits `target="_blank"` and, because its `noopener`/`noreferrer` options default to "apply to external links", also `rel="noopener noreferrer"`, closing the reverse-tabnabbing hole that a bare `target="_blank"` would open. The behaviour is deliberately uniform across both cards for consistency; operators simply write normal Markdown links. Implementing this required moving `SidebarContent` from the convenience `CommonMarkConverter` to an explicit `Environment` + `MarkdownConverter` so the extension can be registered alongside the core extension while keeping the same safe-mode config.

### 5. Content Location and Naming: `content/events.md`, `content/links.md`

A new top-level `content/` directory holds the files under fixed names. Fixed names keep the code and docs simple ("drop a file here to enable the card"). Starter templates `content/events.md.example` and `content/links.md.example` ship in the repo, mirroring `.env.example`. The real `content/*.md` files are git-ignored so operator content is not committed. Like `data/` and `.env`, `content/` must never be reachable over HTTP — the root `.htaccess` fallback blocks it, and the recommended `public/`-as-docroot layout keeps it outside the web root anyway.

### 6. Sidebar Side: `SIDEBAR_SIDE` in `.env`, Default `right`

A single `SIDEBAR_SIDE` variable takes `left` or `right`. Unset or invalid values fall back to `right`. A right-hand sidebar keeps the primary form first in source order and visually on the left, which reads naturally and stacks cleanly on mobile. There is **no** dedicated on/off switch: presence of content governs visibility (Decision 7). `SIDEBAR_SIDE` is optional and therefore excluded from the dotenv `required()` assertion list.

### 7. Visibility: Governed by Content Presence

Each card renders only if its content file exists and is non-empty after trimming. The sidebar column renders only if at least one card is present. If neither card is configured, no sidebar column exists and the form spans the full width — an instance that configures nothing is visually unchanged from before this feature.

### 8. Layout: CSS Grid, Form-First DOM, Stacks on Mobile

The two-column layout uses CSS Grid in `layout.php`'s existing inline `<style>` block, consistent with how the project already hand-tunes Pico CSS rather than pulling in grid frameworks. The main content is always first in the DOM (good for accessibility and mobile source order); a wrapper class (`sidebar-left` / `sidebar-right`) places the sidebar visually via CSS. Below a `768px` breakpoint the layout stacks to a single column with the sidebar **below** the form, so the primary action stays at the top on small screens regardless of the desktop side.

### 9. Scope: Main Form Page Only

The sidebar appears only on the landing/form view. Message pages (thank-you, list-sent, errors) and the unsubscribe confirmation stay single-column and focused. The layout renders a sidebar slot only when the form view provides one; other render paths pass nothing.

### 10. Card Titles: Translatable i18n Keys

Card titles come from translation keys `sidebar.events_title` and `sidebar.links_title` (added to `lang/en.php` and `lang/de.php`), consistent with ADR-002's rule that visitor-facing chrome is translatable. The Markdown files provide only the card body, so the operator writes content without managing headings, and titles stay consistent and translatable.

### 11. Rendering Architecture: `App\SidebarContent` Class

A dedicated `App\SidebarContent` class constructs the configured CommonMark converter once and exposes `renderFile(string $path): string`, returning the rendered HTML or `''` when the file is absent, empty, or fails to read/parse. This centralises the safe-mode configuration in one testable place (important given the strict-escaping choice and the likely future DB path) and reuses a single converter instance rather than reconfiguring per call. It fits alongside the existing `src/*.php` classes under PSR-4.

### 12. No Caching

The rendered HTML is not cached; both files are parsed on each render of the form page. The content is tiny and CommonMark parsing of a few hundred bytes is negligible next to the work the form page already does (DB init, occasional rate-limit cleanup). Caching would add a writable directory and invalidation logic for no meaningful gain. If profiling ever shows it matters, caching can be added behind `renderFile` without changing callers.

### 13. Fail-Safe Rendering

If a content file exists but cannot be read or parsed, or renders to empty, the card renders as nothing (identical to "not configured") rather than surfacing an error or breaking the page. The form is the core function; a decorative sidebar card must never take down the public landing page. Failures are optionally logged via `error_log()` for the operator. This matches the app's existing graceful-degradation posture (e.g. the admin tool's friendly 503 when unconfigured).

---

## Consequences

- **First runtime Composer dependency beyond phpdotenv:** `league/commonmark` is added. It is well-maintained and pinned to `^2.10` to include the latest security fixes. The `intl` requirement from ADR-002 is unchanged.
- **New optional directory `content/`** with git-ignored `*.md` files and committed `*.md.example` starters. It must be blocked from HTTP access, handled by the root `.htaccess` fallback.
- **New optional `.env` variable `SIDEBAR_SIDE`** (`left`/`right`, default `right`), excluded from the `required()` assertions so unconfigured instances still boot.
- **Visitor-facing surface grows** by translatable card titles in every shipped language file.
- **Zero visible change for instances that configure nothing:** no sidebar renders, the form is full-width, and no new required configuration is introduced.
- **Future-friendly:** the safe renderer and content-presence model make a later admin-editable / DB-backed content source (ADR-003 territory) a straightforward extension rather than a rewrite.
- **Limitations:** content is edited by hand as files (no web UI yet); no caching (acceptable given tiny content); titles are translatable but card *content* is operator data and not translated (consistent with ADR-002/ADR-003).

# nimbuscms/blog — design

**Status:** design (pre-build). An **official NimbusCMS plugin** that turns a plain
collection of posts into a real blog: per-post SEO head, an RSS/Atom feed, and tag
archives. First consumer: **danmat.dev** (theme "Orchestra", three skins). Built to
be reusable by any Nimbus site, not shaped to danmat.

Requirements brief: `danmat.dev-site/drafts/blog-plugin-requirements.md`.

## Architecture — what lives where

The guiding split (which also matches the brief): **core content + the site's theme
render the posts; the plugin adds the cross-cutting things a plain collection can't
do.**

- **Content — a `blog` collection (core, seeded over MCP).** Fields: `summary`
  (textarea), `body` (textarea/markdown), `cover` (image), `tags` (text,
  comma-separated), `canonical_url` (text, optional) — plus the reserved
  `title`/`slug`/`published_at`. The plugin **never touches core tables**; the site's
  seed declares the collection and the posts.
  - **Why the handle is `blog`, not `posts`:** a browsable collection is served by
    core at `/{handle}` + `/{handle}/{slug}` — so handle `blog` gives the brief's
    `/blog` + `/blog/{slug}` **for free**, with core doing pagination, the sitemap,
    and (crucially) a full entry `PageContext` for the SEO head. A `posts` handle
    would serve `/posts/...` and a page-section overlay would then duplicate URLs and
    hand the head contributor no entry data. Handle `blog` is the clean mapping the
    brief leaves to us.
- **List + detail rendering — the site's theme.** Core resolves `collection-blog` /
  `entry-blog` from the active theme (its existing `entry-{handle}` convention), so
  the danmat "Orchestra" theme renders `/blog` and `/blog/{slug}` across its three
  skins. The plugin ships **no** list/detail templates — rendering is the theme's job
  (brief §27), and each site styles posts in its own identity.
- **SEO head — the plugin (`HeadContributor`, ADR 0004).** This is the reason it's a
  plugin. On the `blog` collection's entry page it emits: canonical (the post's
  `canonical_url` if set, else self), Open Graph `article` (+ `article:published_time`,
  image from `cover`), Twitter card, and JSON-LD `Article`; on the index it emits the
  blog-level website OG; on every blog page, the `<link rel="alternate"
  type="application/rss+xml">` feed link. It reads the post from the `PageContext`
  entry on the entry page (no URL parsing), and via `content()` where needed.
- **Feed — a plugin route `/ext/blog/feed.xml` (ADR 0017).** Page sections only
  return themed HTML, so the feed is a plugin route returning raw XML
  (`application/rss+xml`) of the latest published posts.
- **Tag archives — a plugin page section `tag` → `/tag/{name}` (ADR 0023).** Reads
  the `blog` collection via `content()`, filters by tag, returns a `PageView` the
  theme renders (plugin ships a default template; the theme overrides per skin).
- **Nav — "Writing" → `/blog`** added to the site's `config/menus.php`.

### The canonical / double-head question (decided)

The theme's `layout.php` already emits a self-canonical + basic OG from core's
`$meta` on every page. If the plugin also emits canonical, a cross-posted post
(`canonical_url` set) would get **two** canonicals. Resolution for v1 (no core
change): **the plugin owns the head on blog pages, and the consuming theme defers its
generic canonical/OG to `$head` on those pages** — for danmat, a one-line conditional
in `layout.php` (the theme is ours; "let a plugin own SEO on its own pages" is a
legitimate theme responsibility). Self-canonical posts (the common case, incl. the
first post) are unaffected either way.

> **Reusability note / future core capability:** requiring each theme to defer is
> mild coordination. A small, broadly-useful core capability — *an entry may declare
> its own canonical URL, and core's `meta()` honours it* — would remove the theme
> conditional and serve syndication for any collection, not just blogs. Flagged as a
> follow-up ADR (the same "app drives a reusable core capability" pattern as ADR
> 0029/0030); **not required for v1** and out of scope of the first ship.

### Inline SVG in the post body

The brief needs diagrams **inline in the body**, but the theme's `_prose` renderer
**escapes all HTML** (so a raw `<svg>` becomes text). The theme already trusts author
SVG elsewhere (`entry-projects`) via a strict regex allow-guard. So the danmat
`entry-blog` template renders the body with a small **SVG-aware prose pass**: split
the body on `<svg>…</svg>` blocks; each block is admitted **only** if it matches the
existing guard (starts `<svg`, ends `</svg>`, and contains no `<script`, no `on*=`,
no `javascript:`, no `<foreignObject>`) and is echoed raw; every text segment between
blocks goes through `_prose` (escape-first). This is a **theme** concern (it needs
`_prose` + `$e`), so it lives in the danmat theme; the plugin's reusable contribution
is the SEO/feed/tags, not body rendering.

## Security review (Attacker / Defender / QA)

Public read surfaces only (SEO head, a feed, tag pages, a public blog). No writes,
no auth, no core tables.

**🔴 Attacker → ⚪ Defender (control · severity)**

1. **Head injection via post fields** — a `title`/`summary`/`tags`/`canonical_url`
   with `"`/`<`/`</script>` breaks out of a meta attribute, the JSON-LD `<script>`,
   or `<link href>`. → Every value emitted into an attribute is `View::e()`-escaped;
   the JSON-LD block is `json_encode(…, JSON_HEX_TAG|APOS|QUOT|AMP|UNESCAPED_SLASHES)`
   so `</script>` and quotes can't break out; `canonical_url` and the cover URL go
   through the site's safe-href/URL gate before use (reject non-http(s), `javascript:`,
   `data:`, etc.) and are `$e`-escaped in the attribute. **Severity: High if unescaped
   → mitigated.**
2. **Inline SVG XSS in the body** — a post body carries `<svg onload=…>` /
   `<script>` / `<foreignObject>`. → The SVG guard is an allow-list: admit only a bare
   `<svg>…</svg>` with no `<script`, no `on*=`, no `javascript:`, no `<foreignObject>`;
   anything else is escaped as text by `_prose`. Post bodies are authored by the site
   owner over MCP (not visitor input), so this is defence-in-depth, but it holds under
   the site's strict `default-src 'self'` nonce-only CSP (no external/script vectors).
   **Severity: Medium (author-trusted) → mitigated.**
3. **Feed injection** — post fields break the RSS/Atom XML. → Titles/summaries are
   emitted inside `<![CDATA[…]]>` with the `]]>` sequence split, or XML-escaped;
   dates are formatted server-side; links are the gated canonical/self URLs. Content
   length is bounded ("a handful, not a feed" — the latest N posts). **Severity:
   Medium → mitigated.**
4. **Tag-page input** — `/tag/{name}` with a hostile `{name}`. → `{name}` is only
   used to filter (compared against parsed tags) and echoed `$e`-escaped as the page
   heading; the collection read is by handle (bound), never building SQL from `{name}`.
   A missing tag → the themed 404 (non-enumerating). **Severity: Low → mitigated.**
5. **Feed/DoS** — the feed or tag scan is unbounded. → Both read a bounded page of
   published entries via `ContentReader` (published-only; drafts never leak); the feed
   caps at N (e.g. 20) newest. **Severity: Low.**

**🟢 QA / permanence** — regression tests: head contributor escapes a hostile
title/summary and emits a correct canonical for both self and `canonical_url`-set
posts; JSON-LD is valid, escaped JSON; the SVG guard admits a clean diagram and
rejects `<script>`/`on*`/`<foreignObject>`; the feed is well-formed and escapes a
hostile title; a missing tag 404s. **Merge bar:** no Critical/High open. Security-green
to build.

## Platform review (three hats)

**🧑‍💼 Product** — A blog/SEO/feed capability is wanted by *many* unrelated Nimbus
sites (portfolios, docs, product sites), independent of danmat. Drift-guard #4: yes,
I'd recommend an official blog plugin regardless of danmat. ✅

**🏗️ Architect** — Classification: **official plugin**. Uses only existing hinges
(head ADR 0004, routes ADR 0017, sections ADR 0023, content-read ADR 0029). No core
change for v1. The one design choice — a browsable `blog` collection rather than a
`posts` collection + section overlay — is the smallest mapping that gives clean URLs,
a correct sitemap, and a real entry context for SEO. Reusable: rendering stays the
theme's; the plugin is content-model-light (a configurable collection handle). ✅

**👷 Principal engineer** — Escape-on-emit everywhere; `json_encode` for JSON-LD;
gated URLs; bounded reads; published-only via `ContentReader`. Testable units: the
head contributor (`PageContext → string`), the feed builder (`posts → XML`), the tag
resolver. **Mobile**: the blog list/detail are theme templates verified at 375px in
all three skins. **MCP**: reading/authoring posts is already agent-drivable (core
content over MCP); the plugin adds public read surfaces, so no new management action
to expose — the MCP check is satisfied by core content tooling. ✅

## Build slices (each CI-green)

1. **SEO head + the live blog** — plugin `HeadContributor` (canonical/OG-article/
   Twitter/JSON-LD/reading-time + rss-alternate) over the `blog` collection; the
   `blog` collection in danmat's seed; danmat theme `collection-blog`/`entry-blog`
   (3 skins) + SVG-aware body + the layout head-defer conditional; "Writing" nav.
2. **Feed** — `/ext/blog/feed.xml` (RSS 2.0) + discovery link.
3. **Tag archives** — `tag` page section → `/tag/{name}` + theme template (3 skins).
4. **Niceties** — reading-time surfaced in the UI, related-by-tag, sitemap note.

Then: seed the **Numistoria** post + its two inline-SVG diagrams, deploy (danmat
image rebuild + `--force-recreate danmat` + reseed), verify all three skins live at
375px, and point Dev.to's canonical at the new URL.

## Definition of done (v1)

`/blog` lists published posts newest-first in all three skins; `/blog/{slug}` renders
a full post incl. inline SVG; each post emits canonical + OG + JSON-LD (canonical
honours `canonical_url`); a working `/ext/blog/feed.xml`; `/tag/{name}` works;
"Writing" nav present; the Numistoria post renders with its two diagrams; plugin CI
green (PHPStan L6 + cs-fixer + tests); theme verified at 375px.

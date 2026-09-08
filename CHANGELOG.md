# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims to
follow semantic versioning once it reaches 1.0.

## [Unreleased]

### Added

- **Syndication** — cross-post a published post to external dev platforms from both
  the admin and MCP, through one `Syndicator`, one capability, and one audit trail.
  - Capability `nimbuscms.blog:syndicate` (wildcard-immune, ADR 0015/0030): a content
    `*:write` grant can never reach it; the explicit grant is the authority to publish
    externally.
  - First plugin table `blog_syndication` (ADR 0005): re-posting updates the external
    copy rather than duplicating it (keyed by entry + target); stores the external
    id/url/status only, no content and no secrets.
  - **Dev.to** target (`DEVTO_API_KEY`) and **Hashnode** target (`HASHNODE_TOKEN` +
    `HASHNODE_PUBLICATION_ID`; API writes require Hashnode Pro). Canonical set back to
    this site automatically.
  - Admin **Syndication** page (a Post/Update button per post per target, ADR 0020)
    and MCP tools `blog_syndicate_post` / `blog_syndication_status` (ADR 0016).
  - **Share links** (Hacker News, Reddit): prefilled submit links a human clicks (no
    auto-post), on the admin page and in the status tool.
- **Inline SVG diagrams in cross-posts** — Dev.to and Hashnode reject raw inline SVG,
  so the outgoing body has each `<svg>` rendered to a hosted PNG (via `rsvg-convert`)
  and swapped for a markdown image; the stored post keeps its inline SVG. Rendered onto
  a solid light background with a dark ink for `currentColor`. Content-addressed hosting
  (the file is the cache, no table). Untrusted SVG is guarded (scripts, `foreignObject`,
  DOCTYPE/entities, and external references are refused) and any failure degrades to a
  pointer to the canonical, never a failed cross-post.
- Agent guide (ADR 0013) served over MCP, covering the model and syndication.

### Fixed

- **`og:image` from a text cover** — the SEO head now emits `og:image` / `twitter:image`
  / JSON-LD `image` from a `cover` that is a plain image URL, not only a resolved media
  object. Unsafe URL values are ignored.
- **Outbound HTTP User-Agent** — the HTTP client now sends a `User-Agent`; without one,
  some platform edges (e.g. Dev.to's Varnish) answered a no-UA request with an
  empty-bodied HTTP 403 before it reached the API.
- Syndication errors now carry a slice of the platform's raw response, and a 403 no
  longer asserts the post body as the cause.

### Initial release

- Turns a plain `blog` collection into a blog: per-post SEO `<head>` (canonical honouring
  a post's `canonical_url`, Open Graph `article`, Twitter card, JSON-LD `Article`), an RSS
  feed at `/ext/blog/feed.xml`, and tag archives at `/tag/{tag}`. Touches no core tables;
  the site's theme renders the list and detail pages.

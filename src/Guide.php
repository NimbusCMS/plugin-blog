<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * The agent-facing guide (ADR 0013), served as an MCP resource. Reference
 * documentation, not instructions — it teaches an agent how the blog is modelled so
 * it authors posts and reasons about the public pages correctly.
 */
final class Guide
{
    public static function text(): string
    {
        return <<<'MD'
            # Blog

            The blog is an ordinary content collection named **`blog`** — the plugin
            adds SEO, a feed, and tag pages *around* it, but the posts are plain core
            content you create and edit over the normal content tools/MCP. The plugin
            has **no tables and no capability**; everything it serves is public read.

            ## Model — the `blog` collection

            One entry per post. Fields:

            - `title`, `slug`, `published_at` — the reserved attributes. A post is
              public only when it is **published**; drafts never appear on the site,
              in the feed, or on tag pages.
            - `summary` — one or two sentences; used for the list, the meta
              description, and the OG/Twitter/JSON-LD description. Write one.
            - `body` — the post, in markdown. The theme renders it; inline `<svg>…</svg>`
              diagrams are allowed **in the body** (bare SVG only — no `<script>`,
              no `on*=` handlers, no `<foreignObject>`; anything else is shown as text).
            - `cover` — a same-origin image (a media id); used as the OG/Twitter image.
            - `tags` — comma-separated; each becomes a `/tag/{tag}` archive.
            - `canonical_url` — set this **only when the post first appeared somewhere
              else** (e.g. cross-posted to Dev.to) and this site is not the original;
              the post's canonical link then points there. Leave it **empty** for a
              post that is native here (the common case) — the canonical is then the
              post's own `/blog/{slug}` URL, which is what other copies should point at.

            ## Public surface

            - `/blog` — the index (published posts, newest first).
            - `/blog/{slug}` — a post. Emits canonical + Open Graph `article` +
              Twitter card + JSON-LD `Article`.
            - `/tag/{tag}` — posts with that tag.
            - `/ext/blog/feed.xml` — an RSS feed of the latest published posts.

            ## Sharp edges

            - Set `summary` on every post — several head tags and the list fall back
              to it. Without it they are simply omitted.
            - `canonical_url` is for syndication origin, not a redirect. Empty = "this
              is the canonical home".
            - A post is invisible everywhere until `published_at` is set and in the
              past and its status is published.
            MD;
    }
}

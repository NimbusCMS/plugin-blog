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
            content you create and edit over the normal content tools/MCP. The public
            reading surfaces need **no capability**; the one guarded thing is
            **syndication** (see below), which owns a small table and its own capability.

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

            ## Syndication — cross-post a published post

            Push a **published** post to an external dev platform, or get a link to
            share it to an aggregator. The canonical is always set back to this site
            (or the post's `canonical_url` if it originated elsewhere), and re-pushing
            **updates** the existing external copy rather than making a second one.

            This is the plugin's one guarded action: the capability
            **`nimbuscms.blog:syndicate`**. It is wildcard-immune — a content
            `*:write` token can **not** reach it; a token must be granted this exact
            scope. That grant *is* the authority to publish externally, so nothing
            posts to an external platform on a generic content grant.

            **MCP tools** (both require the `syndicate` scope; a token without it does
            not even see them):

            - `blog_syndicate_post { slug, target }` — cross-post (or update) the
              post to one auto-post target. `target` is `"devto"` or `"hashnode"`.
              Returns the external id and URL.
            - `blog_syndication_status { slug }` — where the post has been syndicated
              and to what URLs, **plus** `share` links (Hacker News, Reddit) a human
              can open to submit it.

            **Auto-post targets** publish through their API using an operator key held
            server-side (never in content, the tool call, or the audit log). A target
            with no key configured is reported as unavailable and refuses cleanly.

            - `devto` — Dev.to. Needs `DEVTO_API_KEY`.
            - `hashnode` — Hashnode. Needs `HASHNODE_TOKEN` + `HASHNODE_PUBLICATION_ID`;
              **API writes require a Hashnode Pro account** (without Pro the push
              fails with a clear "requires Hashnode Pro" message).

            **Share links** (Hacker News, Reddit) are **not** auto-posted — they are
            link-submission communities. The status tool returns a prefilled submit
            URL for each; a **human** opens it and submits. You can surface these to a
            person, but you cannot post them yourself. (Hacker News has no submit API
            at all; Reddit API auto-submit is intentionally not offered here.)

            ## Sharp edges

            - Set `summary` on every post — several head tags and the list fall back
              to it. Without it they are simply omitted.
            - `canonical_url` is for syndication origin, not a redirect. Empty = "this
              is the canonical home".
            - A post is invisible everywhere until `published_at` is set and in the
              past and its status is published.
            - Only a **published** post can be syndicated; there is nothing to
              cross-post for a draft.
            - Syndication needs the `nimbuscms.blog:syndicate` scope and the target's
              key configured — otherwise the tools are absent or the target refuses.
            MD;
    }
}

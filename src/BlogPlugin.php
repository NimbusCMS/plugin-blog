<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

use Nimbus\Plugin\Plugin;
use Nimbus\Plugin\PluginContext;

/**
 * The official Blog plugin — turns a plain `blog` collection into a real blog:
 * per-post SEO `<head>` (canonical/OG/Twitter/JSON-LD, ADR 0004), an RSS feed
 * (ADR 0017), and tag archives (ADR 0023). It adds the cross-cutting things a plain
 * collection can't do; the **posts themselves are ordinary core content** (the
 * `blog` collection, seeded over MCP) and the **site's theme renders** the list and
 * detail pages via its own `collection-blog` / `entry-blog` templates. The plugin
 * touches no core tables and needs no capability — every surface is public read.
 *
 * The collection handle is `blog` by design: a browsable collection is served by
 * core at `/blog` + `/blog/{slug}` (clean URLs, correct sitemap, and a full entry
 * context for the SEO head) with no page-section overlay.
 */
final class BlogPlugin implements Plugin
{
    /** Matches extra.nimbus.id in composer.json. */
    public const ID = 'nimbuscms.blog';

    /** The content collection the blog reads (its handle is also its URL base). */
    public const COLLECTION = 'blog';

    public function register(PluginContext $context): void
    {
        // Per-post SEO head — the reason this is a plugin. Emits nothing off blog pages.
        $context->head()->register(new BlogHead(self::COLLECTION));

        // Agent-facing reference (ADR 0013).
        $context->skills()->register('Blog', Guide::text());
    }
}

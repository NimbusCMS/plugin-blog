<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

use Nimbus\Http\Request;
use Nimbus\Http\Response;
use Nimbus\Plugin\Plugin;
use Nimbus\Plugin\PluginContext;
use Nimbus\Site\PageView;
use Nimbus\Support\Config;

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

    /** How many newest posts the RSS feed carries. */
    public const FEED_ITEMS = 20;

    public function register(PluginContext $context): void
    {
        // Syndication storage (ADR 0005) and its capability (ADR 0015 wildcard-immune,
        // ADR 0030 fine-grained): pushing a post to an external platform is a real
        // publish, so it gets its own action `nimbuscms.blog:syndicate` that a content
        // wildcard can never reach. The admin action and MCP tool that use it land in
        // slice 2; the table and grantable capability are set up here.
        $context->migrations()->register('001_syndication', Schema::all());
        $context->capabilities()->declare('Blog', ['syndicate']);

        // Per-post SEO head — the reason this is a plugin. Emits nothing off blog pages.
        $context->head()->register(new BlogHead(self::COLLECTION));

        // A reader over the (published-only) blog collection, shared by the feed and
        // the tag archive. Resolved lazily so register() runs no query.
        $posts = static fn (): Posts => new Posts(
            static fn (int $limit): array => $context->content()->entries(self::COLLECTION, $limit),
        );

        // RSS feed of the latest published posts (ADR 0017) — raw XML, so a route
        // rather than a themed page. Advertised by BlogHead as /ext/blog/feed.xml.
        $context->routes()->get('blog', '/feed.xml', static function (Request $request) use ($posts): Response {
            $xml = Feed::rss($posts()->published(self::FEED_ITEMS), Config::appUrl(), Config::appName(), '/' . self::COLLECTION);
            return Response::file($xml, 'application/rss+xml; charset=UTF-8');
        });

        // Tag archives (ADR 0023): /tag/{name}. The section catches /tag and
        // /tag/{path*}; the resolver reads the tag from the (already-decoded) path,
        // filters the collection, and returns a themed page (or the themed 404 for a
        // missing/empty tag, so it never enumerates which tags exist).
        $context->pages()->register('tag', static function (Request $request) use ($posts): ?PageView {
            $name = trim(substr($request->path, strlen('/tag')), '/');
            if ($name === '' || str_contains($name, '/')) {
                return null;
            }
            $matched = $posts()->byTag($name);
            if ($matched === []) {
                return null;
            }
            return new PageView('blog-tag', [
                'tag'   => $name,
                'posts' => $matched,
                'base'  => '/' . self::COLLECTION,
            ], ['title' => 'Tagged: ' . $name]);
        }, __DIR__ . '/../templates');

        // Agent-facing reference (ADR 0013).
        $context->skills()->register('Blog', Guide::text());
    }
}

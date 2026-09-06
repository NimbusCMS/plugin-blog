<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Builds an RSS 2.0 feed from mapped posts (see {@see Posts}). Pure: posts + site
 * info in, a well-formed XML string out — so it is unit-testable and side-effect
 * free. Every value is XML-escaped for its text/attribute sink; each item links to
 * the post's own canonical home on this site.
 */
final class Feed
{
    /**
     * @param list<array{title:string,slug:string,summary:string,published_at:string,tags:list<string>,canonical_url:string}> $posts
     * @param string $basePath the blog's URL base, e.g. "/blog"
     */
    public static function rss(array $posts, string $siteUrl, string $siteName, string $basePath = '/blog'): string
    {
        $site = rtrim($siteUrl, '/');
        $base = $site . '/' . trim($basePath, '/');
        $self = $site . '/ext/blog/feed.xml';

        $items = '';
        foreach ($posts as $post) {
            $link = $base . '/' . self::x($post['slug']);
            $pub  = self::rfc822($post['published_at']);
            $cats = '';
            foreach ($post['tags'] as $tag) {
                $cats .= '<category>' . self::x($tag) . '</category>';
            }
            $items .= '<item>'
                . '<title>' . self::x($post['title']) . '</title>'
                . '<link>' . $link . '</link>'
                . '<guid isPermaLink="true">' . $link . '</guid>'
                . ($pub !== '' ? '<pubDate>' . $pub . '</pubDate>' : '')
                . ($post['summary'] !== '' ? '<description>' . self::x($post['summary']) . '</description>' : '')
                . $cats
                . '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            . '<title>' . self::x($siteName) . '</title>'
            . '<link>' . self::x($base) . '</link>'
            . '<description>' . self::x($siteName . ' — blog') . '</description>'
            . '<atom:link href="' . self::x($self) . '" rel="self" type="application/rss+xml"/>'
            . $items
            . '</channel></rss>';
    }

    /** XML-escape text and attribute values (&, <, >, ", '). */
    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** A stored datetime as an RFC-822 date (RSS), or '' if unparseable. */
    private static function rfc822(string $datetime): string
    {
        $ts = strtotime($datetime);
        return $ts !== false ? date(DATE_RSS, $ts) : '';
    }
}

<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

use Nimbus\Site\HeadContributor;
use Nimbus\Site\PageContext;
use Nimbus\Support\Config;
use Nimbus\View\View;

/**
 * The blog's per-page SEO `<head>` (ADR 0004) — the reason this is a plugin. On a
 * post (an entry in the blog collection) it emits canonical (the post's
 * `canonical_url` when set, else self), Open Graph `article`, a Twitter card, and a
 * JSON-LD `Article`; on the blog index it emits a website OG; and on every blog page
 * it advertises the RSS feed. It reads the post straight from the {@see PageContext}
 * (the entry is present on an entry page — no URL parsing), and emits **nothing** on
 * non-blog pages, so the rest of the site is untouched.
 *
 * SECURITY: every value is escaped for its sink — attributes via {@see View::e},
 * the JSON-LD via json_encode with the HEX flags so `</script>`/quotes can't break
 * out — and `canonical_url` / the cover URL are validated as http(s)/site-relative
 * before use. Post fields are author-supplied (over MCP), but they are treated as
 * untrusted here regardless.
 */
final class BlogHead implements HeadContributor
{
    public function __construct(private string $collection = 'blog')
    {
    }

    public function head(PageContext $page): string
    {
        $handle  = $page->collection['handle'] ?? '';
        $isEntry = $page->kind === 'entry' && $handle === $this->collection;
        $isIndex = $page->kind === 'collection' && $handle === $this->collection;
        if (!$isEntry && !$isIndex) {
            return '';
        }

        $siteUrl = rtrim(Config::appUrl(), '/');
        $out     = [];
        // Advertise the feed on every blog page.
        $out[] = '<link rel="alternate" type="application/rss+xml" title="'
            . View::e($page->siteName . ' — Blog') . '" href="'
            . View::e($siteUrl . '/ext/' . $this->collection . '/feed.xml') . '">';

        if ($isIndex) {
            $out[] = $this->openGraph('website', $page->title, '', $page->canonical, '', $page->siteName, '');
            return implode("\n", $out) . "\n";
        }

        // --- a post ---------------------------------------------------------
        $entry     = is_array($page->entry) ? $page->entry : [];
        $fields    = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
        $title     = (string) ($entry['title'] ?? $page->title);
        $summary   = trim((string) ($fields['summary'] ?? ''));
        $published = (string) ($entry['published_at'] ?? '');
        $cover     = $this->coverUrl($fields, $siteUrl);
        $declared  = $this->safeUrl((string) ($fields['canonical_url'] ?? ''));
        $canonical = $declared ?? $page->canonical; // self unless the post declares one

        if ($summary !== '') {
            $out[] = '<meta name="description" content="' . View::e($summary) . '">';
        }
        $out[] = '<link rel="canonical" href="' . View::e($canonical) . '">';
        $out[] = $this->openGraph('article', $title, $summary, $page->canonical, $cover, $page->siteName, $published);

        $card  = $cover !== '' ? 'summary_large_image' : 'summary';
        $out[] = '<meta name="twitter:card" content="' . $card . '">';
        $out[] = '<meta name="twitter:title" content="' . View::e($title) . '">';
        if ($summary !== '') {
            $out[] = '<meta name="twitter:description" content="' . View::e($summary) . '">';
        }
        if ($cover !== '') {
            $out[] = '<meta name="twitter:image" content="' . View::e($cover) . '">';
        }

        $out[] = $this->jsonLd($title, $summary, $page->canonical, $cover, $published, $page->siteName, $page->cspNonce);

        return implode("\n", $out) . "\n";
    }

    /** Build the Open Graph block (+ article:published_time for a post). */
    private function openGraph(string $type, string $title, string $desc, string $url, string $image, string $siteName, string $published): string
    {
        $tags = [
            'og:type'        => $type,
            'og:title'       => $title,
            'og:url'         => $url,
            'og:site_name'   => $siteName,
        ];
        if ($desc !== '') {
            $tags['og:description'] = $desc;
        }
        if ($image !== '') {
            $tags['og:image'] = $image;
        }
        if ($type === 'article' && $published !== '') {
            $tags['article:published_time'] = $this->iso8601($published);
        }
        $html = [];
        foreach ($tags as $property => $content) {
            if ($content !== '') {
                $html[] = '<meta property="' . View::e($property) . '" content="' . View::e($content) . '">';
            }
        }
        return implode("\n", $html);
    }

    /** A JSON-LD Article block, encoded so nothing can break out of the script. */
    private function jsonLd(string $title, string $summary, string $url, string $image, string $published, string $siteName, string $nonce): string
    {
        $ld = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => $title,
            'mainEntityOfPage' => $url,
            'url'              => $url,
        ];
        if ($summary !== '') {
            $ld['description'] = $summary;
        }
        if ($published !== '') {
            $ld['datePublished'] = $this->iso8601($published);
        }
        if ($image !== '') {
            $ld['image'] = $image;
        }
        if ($siteName !== '') {
            $ld['author']    = ['@type' => 'Person', 'name' => $siteName];
            $ld['publisher'] = ['@type' => 'Organization', 'name' => $siteName];
        }
        $json = json_encode($ld, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '<script type="application/ld+json" nonce="' . View::e($nonce) . '">' . ($json !== false ? $json : '{}') . '</script>';
    }

    /**
     * The post's cover image URL for OG/Twitter/JSON-LD, from either shape a `cover`
     * field can take: a **media**-typed field (core resolves it to `{url, ...}`) or a
     * **text**-typed field holding an image URL. In both cases the URL must be an
     * http(s) absolute or a site-relative `/path`; anything else (a `javascript:` or
     * `data:` string, a protocol-relative `//host`, a bare id) yields no image, so a
     * mis-modelled cover degrades to a plain summary card rather than a bad tag.
     *
     * @param array<string,mixed> $fields
     */
    private function coverUrl(array $fields, string $siteUrl): string
    {
        $cover = $fields['cover'] ?? null;
        if (is_array($cover)) {
            $cover = (string) ($cover['url'] ?? '');
        }
        if (!is_string($cover)) {
            return '';
        }
        $cover = trim($cover);
        if (preg_match('#^https?://[^\s"<>]+$#i', $cover) === 1) {
            return $cover;
        }
        if ($cover !== '' && $cover[0] === '/' && ($cover[1] ?? '') !== '/') {
            return $siteUrl . $cover;
        }
        return '';
    }

    /** An author-declared canonical is used only if it is a plain http(s) absolute URL. */
    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        return $url !== '' && preg_match('#^https?://[^\s"<>]+$#i', $url) === 1 ? $url : null;
    }

    /** A stored `Y-m-d H:i:s` (or any parseable) datetime as ISO 8601, else the raw value. */
    private function iso8601(string $datetime): string
    {
        $ts = strtotime($datetime);
        return $ts !== false ? date('c', $ts) : $datetime;
    }
}

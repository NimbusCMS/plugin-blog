<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use Nimbus\Site\PageContext;
use NimbusCMS\Blog\BlogHead;
use PHPUnit\Framework\TestCase;

/**
 * The blog SEO head contributor: it fires only on blog pages, emits canonical +
 * OG article + Twitter + JSON-LD on a post, honours a declared canonical_url, and
 * escapes every value for its sink. Pure PageContext -> string; no DB.
 */
final class BlogHeadTest extends TestCase
{
    private BlogHead $head;

    protected function setUp(): void
    {
        $this->head = new BlogHead('blog');
    }

    /** @param array<string,mixed> $fields */
    private function post(string $canonicalUrl, array $fields, string $selfUrl = 'https://ex.test/blog/hello'): PageContext
    {
        $entry = [
            'id'           => 1,
            'slug'         => 'hello',
            'title'        => (string) ($fields['__title'] ?? 'Hello World'),
            'published_at' => (string) ($fields['__pub'] ?? '2026-01-02 09:00:00'),
            'fields'       => array_merge(['summary' => '', 'body' => '', 'cover' => null, 'tags' => '', 'canonical_url' => $canonicalUrl], $fields),
        ];
        return new PageContext('entry', $selfUrl, (string) $entry['title'], 'danmat.dev', 'NONCE123', $entry, ['handle' => 'blog', 'name' => 'Writing']);
    }

    public function test_it_emits_nothing_off_blog_pages(): void
    {
        $ctx = new PageContext('entry', 'https://ex.test/projects/x', 'X', 'S', 'n', ['fields' => []], ['handle' => 'projects', 'name' => 'Projects']);
        self::assertSame('', $this->head->head($ctx));
    }

    public function test_the_index_emits_website_og_and_the_feed_link(): void
    {
        $ctx  = new PageContext('collection', 'https://ex.test/blog', 'Writing', 'danmat.dev', 'n', null, ['handle' => 'blog', 'name' => 'Writing']);
        $html = $this->head->head($ctx);
        self::assertStringContainsString('og:type" content="website"', $html);
        self::assertStringContainsString('/ext/blog/feed.xml', $html);
        self::assertStringNotContainsString('og:type" content="article"', $html);
    }

    public function test_a_post_emits_canonical_self_og_article_twitter_and_jsonld(): void
    {
        $html = $this->head->head($this->post('', ['summary' => 'A short summary.']));

        self::assertStringContainsString('<link rel="canonical" href="https://ex.test/blog/hello">', $html);
        self::assertStringContainsString('og:type" content="article"', $html);
        self::assertStringContainsString('article:published_time" content="2026-01-02T', $html);
        self::assertStringContainsString('name="description" content="A short summary."', $html);
        self::assertStringContainsString('name="twitter:card" content="summary"', $html);
        self::assertStringContainsString('application/ld+json" nonce="NONCE123"', $html);
        self::assertStringContainsString('"@type":"Article"', $html);
        self::assertStringContainsString('"datePublished":"2026-01-02T', $html);
    }

    public function test_a_cover_gives_a_large_image_card(): void
    {
        $html = $this->head->head($this->post('', ['summary' => 's', 'cover' => ['url' => '/uploads/c.jpg', 'alt' => 'c']]));
        self::assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        self::assertStringContainsString('og:image', $html);
        self::assertStringContainsString('/uploads/c.jpg', $html);
    }

    public function test_a_declared_canonical_url_points_away_from_self(): void
    {
        $html = $this->head->head($this->post('https://dev.to/dan/hello', ['summary' => 's']));
        self::assertStringContainsString('<link rel="canonical" href="https://dev.to/dan/hello">', $html);
        self::assertStringNotContainsString('rel="canonical" href="https://ex.test/blog/hello"', $html);
    }

    public function test_a_malformed_canonical_url_falls_back_to_self(): void
    {
        $html = $this->head->head($this->post('javascript:alert(1)', ['summary' => 's']));
        self::assertStringContainsString('<link rel="canonical" href="https://ex.test/blog/hello">', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function test_it_escapes_hostile_fields_in_attributes_and_jsonld(): void
    {
        $html = $this->head->head($this->post('', [
            '__title' => 'Ada & the "Analytical" </script> Engine',
            'summary' => 'break "out" <b>now</b>',
        ]));
        // Attributes: quotes and angle brackets escaped.
        self::assertStringNotContainsString('content="Ada & the "Analytical"', $html);
        self::assertStringContainsString('&quot;', $html);
        // JSON-LD: </script> can't break out (HEX_TAG escapes '<').
        self::assertStringNotContainsString('</script> Engine', $html);
        self::assertStringContainsString('<', $html);
    }
}

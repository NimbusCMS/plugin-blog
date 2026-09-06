<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\Feed;
use PHPUnit\Framework\TestCase;

/**
 * The RSS builder: well-formed XML, per-post links to this site, dates, categories,
 * and XML-escaping of hostile values. Pure — no DB.
 */
final class FeedTest extends TestCase
{
    /** @return list<array{title:string,slug:string,summary:string,published_at:string,tags:list<string>,canonical_url:string}> */
    private function samplePosts(): array
    {
        return [
            ['title' => 'First Post', 'slug' => 'first', 'summary' => 'Hello there.', 'published_at' => '2026-01-02 09:00:00', 'tags' => ['PHP', 'Nimbus'], 'canonical_url' => ''],
            ['title' => 'Second', 'slug' => 'second', 'summary' => '', 'published_at' => '2026-01-03 10:00:00', 'tags' => [], 'canonical_url' => ''],
        ];
    }

    public function test_it_is_well_formed_rss_with_a_self_link(): void
    {
        $xml = Feed::rss($this->samplePosts(), 'https://danmat.dev', 'danmat.dev', '/blog');

        $doc = simplexml_load_string($xml);
        self::assertNotFalse($doc, 'the feed is well-formed XML');
        self::assertSame('2.0', (string) $doc['version']);
        self::assertCount(2, $doc->channel->item);
        self::assertSame('danmat.dev', (string) $doc->channel->title);
    }

    public function test_items_link_to_this_site_with_dates_and_categories(): void
    {
        $xml = Feed::rss($this->samplePosts(), 'https://danmat.dev/', 'danmat.dev', '/blog');

        self::assertStringContainsString('<link>https://danmat.dev/blog/first</link>', $xml);
        self::assertStringContainsString('<guid isPermaLink="true">https://danmat.dev/blog/first</guid>', $xml);
        self::assertStringContainsString('<category>PHP</category>', $xml);
        self::assertStringContainsString('<pubDate>', $xml);
        // A post with no summary omits <description>.
        self::assertSame(1, substr_count($xml, '<description>Hello there.</description>'));
    }

    public function test_it_escapes_hostile_values(): void
    {
        $posts = [[
            'title'         => 'Break <out> & "quote" </title>',
            'slug'          => 'x',
            'summary'       => 'a <b>bold</b> & risky',
            'published_at'  => '2026-01-02 09:00:00',
            'tags'          => ['<evil>'],
            'canonical_url' => '',
        ]];
        $xml = Feed::rss($posts, 'https://danmat.dev', 'danmat.dev', '/blog');

        self::assertNotFalse(simplexml_load_string($xml), 'still well-formed with hostile input');
        self::assertStringNotContainsString('<out>', $xml, 'the raw tag must be escaped');
        self::assertStringContainsString('&lt;out&gt;', $xml);
        self::assertStringContainsString('&amp;', $xml);
    }
}

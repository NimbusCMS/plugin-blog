<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\Posts;
use PHPUnit\Framework\TestCase;

/**
 * Posts maps blog entries to the flat shape the feed/tag pages use, and filters by
 * tag case-insensitively. Fed a canned entry list (the reader is faked), so no DB.
 */
final class PostsTest extends TestCase
{
    /** @param list<array<string,mixed>> $entries */
    private function posts(array $entries): Posts
    {
        return new Posts(static fn (int $limit): array => array_slice($entries, 0, $limit));
    }

    /** @return array<string,mixed> */
    private function entry(string $title, string $slug, string $tags, string $summary = ''): array
    {
        return [
            'title'        => $title,
            'slug'         => $slug,
            'published_at' => '2026-01-02 09:00:00',
            'fields'       => ['summary' => $summary, 'tags' => $tags, 'canonical_url' => ''],
        ];
    }

    public function test_published_maps_fields_and_splits_tags(): void
    {
        $out = $this->posts([$this->entry('Hello', 'hello', 'PHP, Web Dev , ,Nimbus', 'A summary.')])->published();

        self::assertCount(1, $out);
        self::assertSame('Hello', $out[0]['title']);
        self::assertSame('hello', $out[0]['slug']);
        self::assertSame('A summary.', $out[0]['summary']);
        self::assertSame(['PHP', 'Web Dev', 'Nimbus'], $out[0]['tags'], 'tags trimmed, empties dropped');
    }

    public function test_by_tag_matches_case_insensitively_and_exactly(): void
    {
        $posts = $this->posts([
            $this->entry('A', 'a', 'PHP, Nimbus'),
            $this->entry('B', 'b', 'javascript'),
            $this->entry('C', 'c', 'php'),
        ]);

        $php = $posts->byTag('php');
        self::assertCount(2, $php, 'PHP and php both match');
        self::assertSame(['a', 'c'], array_map(static fn (array $p): string => $p['slug'], $php));

        self::assertSame([], $posts->byTag('py'), 'no partial matches');
        self::assertSame([], $posts->byTag('  '), 'blank tag matches nothing');
    }

    public function test_the_fetch_limit_is_passed_through(): void
    {
        $entries = [];
        for ($i = 0; $i < 5; $i++) {
            $entries[] = $this->entry("P$i", "p$i", 'x');
        }
        self::assertCount(3, $this->posts($entries)->published(3));
    }
}

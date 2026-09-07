<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\ShareLinks;
use PHPUnit\Framework\TestCase;

/**
 * The aggregator share-link builder: the two prefilled submit URLs, with the post URL
 * and title percent-encoded into each community's own parameter names.
 */
final class ShareLinksTest extends TestCase
{
    public function test_it_builds_hn_and_reddit_prefilled_submit_urls(): void
    {
        $links = ShareLinks::for('Hello & Goodbye', 'https://danmat.dev/blog/hello');
        $by    = [];
        foreach ($links as $l) {
            $by[$l['id']] = $l;
        }

        self::assertSame(['hn', 'reddit'], array_column($links, 'id'));
        self::assertSame('Hacker News', $by['hn']['label']);
        self::assertSame(
            'https://news.ycombinator.com/submitlink?u=https%3A%2F%2Fdanmat.dev%2Fblog%2Fhello&t=Hello%20%26%20Goodbye',
            $by['hn']['url'],
            'HN uses u/t; both values are encoded',
        );
        self::assertSame(
            'https://www.reddit.com/submit?url=https%3A%2F%2Fdanmat.dev%2Fblog%2Fhello&title=Hello%20%26%20Goodbye',
            $by['reddit']['url'],
            'Reddit uses url/title; both values are encoded',
        );
    }
}

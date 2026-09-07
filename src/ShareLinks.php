<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Prefilled submit links for the link-aggregator communities — Hacker News and
 * Reddit. These are not auto-post targets: each is a URL that opens the aggregator's
 * own submit form with the post's URL and title already filled in, so the logged-in
 * human reviews and submits. No credential, no stored state, no outbound call.
 *
 * (Hacker News has no submit API at all; Reddit's is OAuth-gated and spam-sensitive.
 * Auto-submit is a separate, opt-in conversation — see docs/DESIGN-syndication.md.)
 */
final class ShareLinks
{
    /**
     * @return list<array{id:string,label:string,url:string}>
     */
    public static function for(string $title, string $url): array
    {
        $u = rawurlencode($url);
        $t = rawurlencode($title);

        return [
            ['id' => 'hn', 'label' => 'Hacker News', 'url' => 'https://news.ycombinator.com/submitlink?u=' . $u . '&t=' . $t],
            ['id' => 'reddit', 'label' => 'Reddit', 'url' => 'https://www.reddit.com/submit?url=' . $u . '&title=' . $t],
        ];
    }
}

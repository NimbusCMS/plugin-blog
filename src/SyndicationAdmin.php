<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * The "Syndication" admin page (ADR 0020): published posts, each with a button per
 * target to post or update it on that platform, the resulting link, and the last
 * status. Gated on nimbuscms.blog:syndicate; the buttons post to the page's own
 * CSRF-checked action. Renders raw HTML inside the admin shell; every value is
 * escaped, and the only inline style is a nonce'd block (admin CSP drops inline
 * style= attributes).
 */
final class SyndicationAdmin
{
    private const ACTION = '/admin/blog-syndication/push';

    public function __construct(private Syndicator $syndicator, private Posts $posts)
    {
    }

    public function render(string $csrf, ?string $notice, string $nonce): string
    {
        $targets = $this->syndicator->targets();
        $posts   = $this->posts->published(200);

        $html = $this->styles($nonce)
            . '<div class="rz-head"><h1>Syndication</h1></div>'
            . '<p class="nb-muted rz-intro">Cross-post a published post to an external platform. The canonical is set back to this site automatically, and re-posting updates the existing copy rather than duplicating it. Credentials are set on the server; a target with no key configured is shown as unavailable.</p>';

        if ($notice !== null && $notice !== '') {
            $html .= '<div class="nb-notice">' . self::e($notice) . '</div>';
        }

        if ($targets === []) {
            return $html . '<p class="nb-muted">No syndication targets are available.</p>';
        }
        if ($posts === []) {
            return $html . '<p class="nb-muted">No published posts yet.</p>';
        }

        $html .= '<table class="rz-table"><thead><tr><th>Post</th>';
        foreach ($targets as $target) {
            $html .= '<th>' . self::e($target->label()) . '</th>';
        }
        $html .= '<th>Share</th></tr></thead><tbody>';

        foreach ($posts as $post) {
            $slug    = (string) $post['slug'];
            $status  = $this->syndicator->statusFor($slug);
            $records = [];
            foreach ($status['records'] ?? [] as $r) {
                $records[$r['target']] = $r;
            }
            $html .= '<tr><td>' . self::e((string) $post['title']) . '</td>';
            foreach ($targets as $target) {
                $html .= '<td>' . $this->cell($target, $slug, $records[$target->id()] ?? null, $csrf) . '</td>';
            }
            $html .= '<td>' . $this->shareCell($slug) . '</td>';
            $html .= '</tr>';
        }

        return $html . '</tbody></table>'
            . '<p class="nb-muted rz-foot">Auto-post targets publish through their API and set the canonical back here. <b>Share</b> opens the community\'s own submit form with the link and title pre-filled — you pick where it goes and post it yourself (nothing is stored). If a link was already submitted, Hacker News opens the existing thread rather than making a duplicate; that\'s expected.</p>';
    }

    /**
     * The aggregator share links for a post — each opens a prefilled submit form in a
     * new tab for the human to review and post. Not an auto-post, so no button/action.
     */
    private function shareCell(string $slug): string
    {
        $out = '';
        foreach ($this->syndicator->shareLinks($slug) as $link) {
            $out .= '<a class="rz-syn-link" href="' . self::e($link['url']) . '" target="_blank" rel="noopener nofollow">' . self::e($link['label']) . '</a>';
        }
        return $out;
    }

    /**
     * @param array{external_url:?string,status:string,synced_at:string}|null $record
     */
    private function cell(SyndicationTarget $target, string $slug, ?array $record, string $csrf): string
    {
        if (!$target->isConfigured()) {
            return '<span class="nb-muted">Not configured</span>';
        }
        $synced = $record !== null;
        $verb   = $synced ? 'Update' : 'Post';
        $out    = '<form method="post" action="' . self::e(self::ACTION) . '" class="rz-syn-form">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
            . '<input type="hidden" name="slug" value="' . self::e($slug) . '">'
            . '<input type="hidden" name="target" value="' . self::e($target->id()) . '">'
            . '<button type="submit" class="nb-btn">' . self::e($verb) . '</button></form>';

        if ($record !== null && ($record['external_url'] ?? '') !== '') {
            $out .= ' <a class="rz-syn-link" href="' . self::e((string) $record['external_url']) . '" target="_blank" rel="noopener">view</a>';
        }
        if ($record !== null && $record['status'] === 'error') {
            $out .= ' <span class="rz-syn-err">last attempt failed</span>';
        }
        return $out;
    }

    private function styles(string $nonce): string
    {
        return '<style nonce="' . self::e($nonce) . '">'
            . '.rz-intro{max-width:60ch}'
            . '.rz-table{width:100%;border-collapse:collapse;margin-top:1rem}'
            . '.rz-table th,.rz-table td{text-align:left;padding:.55rem .6rem;border-bottom:1px solid rgba(128,128,128,.2);vertical-align:middle}'
            . '.rz-syn-form{display:inline}'
            . '.rz-syn-link{margin-left:.5rem;font-size:.85rem}'
            . '.rz-syn-err{margin-left:.5rem;font-size:.8rem;color:#c0392b}'
            . '.rz-table td:last-child .rz-syn-link:first-child{margin-left:0}'
            . '.rz-table td:last-child{white-space:nowrap}'
            . '.rz-foot{max-width:70ch;margin-top:1rem;font-size:.85rem}'
            . '</style>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

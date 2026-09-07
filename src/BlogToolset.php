<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

use Nimbus\Api\EntryOpContext;
use Nimbus\Api\TokenPrincipal;
use Nimbus\Mcp\PluginTool;
use Nimbus\Mcp\PluginToolset;
use Nimbus\Mcp\ToolResult;

/**
 * The agent surface for syndication (ADR 0016). Both tools gate on the plugin's
 * `syndicate` action, so only a token explicitly scoped `nimbuscms.blog:syndicate`
 * can reach them; a content token cannot even enumerate them. They call the same
 * {@see Syndicator} the admin button uses, so the two surfaces behave identically.
 */
final class BlogToolset extends PluginToolset
{
    public function __construct(private Syndicator $syndicator)
    {
    }

    public function namespace(): string
    {
        return 'blog';
    }

    protected function tools(): array
    {
        $slug = ['type' => 'string', 'description' => 'The blog post slug.'];

        return [
            new PluginTool(
                'syndicate_post',
                'syndicate',
                'Cross-post a published blog post to an external platform (e.g. Dev.to), setting the canonical back to this site. Re-running updates the existing copy rather than duplicating it.',
                [
                    'type'       => 'object',
                    'required'   => ['slug', 'target'],
                    'properties' => [
                        'slug'   => $slug,
                        'target' => ['type' => 'string', 'description' => 'The target platform id, e.g. "devto".'],
                    ],
                ],
                $this->syndicatePost(...),
            ),
            new PluginTool(
                'syndication_status',
                'syndicate',
                'Where a published blog post has been syndicated and to what URLs, plus prefilled "share" links (Hacker News, Reddit) a human can open to submit it (these are not auto-posted).',
                [
                    'type'       => 'object',
                    'required'   => ['slug'],
                    'properties' => ['slug' => $slug],
                ],
                $this->syndicationStatus(...),
            ),
        ];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function syndicatePost(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        try {
            $result = $this->syndicator->syndicate($this->str($a, 'slug'), $this->str($a, 'target'), date('Y-m-d H:i:s'));
        } catch (SyndicationError $e) {
            return ToolResult::error($e->getMessage(), 'syndication_failed');
        }
        return ToolResult::ok($result);
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function syndicationStatus(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $slug   = $this->str($a, 'slug');
        $status = $this->syndicator->statusFor($slug);
        if ($status === null) {
            return ToolResult::error('No published post with that slug.', 'not_found');
        }
        $status['share'] = $this->syndicator->shareLinks($slug);
        return ToolResult::ok($status);
    }

    /** @param array<string,mixed> $a */
    private function str(array $a, string $key): string
    {
        $v = $a[$key] ?? '';
        return is_scalar($v) ? trim((string) $v) : '';
    }
}

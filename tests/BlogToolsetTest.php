<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use Nimbus\Api\EntryOpContext;
use Nimbus\Api\TokenPrincipal;
use Nimbus\Mcp\McpError;
use NimbusCMS\Blog\BlogToolset;
use NimbusCMS\Blog\Syndicator;
use PHPUnit\Framework\TestCase;

/**
 * The MCP surface: the tools exist and delegate to the Syndicator, and both gate on
 * nimbuscms.blog:syndicate, so a token without that exact scope can neither see nor
 * call them (the wildcard-immune management gate the base enforces). No DB, no network.
 */
final class BlogToolsetTest extends TestCase
{
    private BlogToolset $toolset;
    private FakeTarget $target;
    private EntryOpContext $ctx;

    protected function setUp(): void
    {
        $this->target = new FakeTarget('devto');
        $post         = ['id' => 7, 'slug' => 'hello', 'title' => 'Hello', 'fields' => ['body' => '# Hi', 'tags' => 'php', 'canonical_url' => '']];
        $syndicator   = new Syndicator(
            ['devto' => $this->target],
            new FakeStore(),
            static fn (string $s): ?array => $s === 'hello' ? $post : null,
            'https://danmat.dev',
            '/blog',
        );
        $this->toolset = new BlogToolset($syndicator);
        $this->toolset->bindTo('nimbuscms.blog'); // the registrar does this in prod
        $this->ctx = new EntryOpContext('127.0.0.1', '/api/v1/mcp');
    }

    private function principal(string ...$scopes): TokenPrincipal
    {
        return new TokenPrincipal(1, 'blog-bot', array_values($scopes));
    }

    public function test_a_syndicate_token_sees_both_tools(): void
    {
        $names = array_column($this->toolset->definitions($this->principal('nimbuscms.blog:syndicate')), 'name');
        self::assertContains('blog_syndicate_post', $names);
        self::assertContains('blog_syndication_status', $names);
    }

    public function test_a_content_wildcard_sees_nothing(): void
    {
        self::assertSame([], $this->toolset->definitions($this->principal('*:write')), 'syndicate is wildcard-immune');
        self::assertSame([], $this->toolset->definitions($this->principal('posts:write')));
    }

    public function test_syndicate_post_delegates_when_scoped(): void
    {
        $out = $this->toolset->call('blog_syndicate_post', ['slug' => 'hello', 'target' => 'devto'], $this->principal('nimbuscms.blog:syndicate'), $this->ctx);

        self::assertNotNull($out, 'a scoped token gets a result');
        self::assertNotNull($this->target->lastCall, 'the tool reached the Syndicator');
    }

    public function test_syndicate_post_is_refused_without_the_scope(): void
    {
        // A denied call reports as an unknown tool (non-enumerating) and never runs.
        try {
            $this->toolset->call('blog_syndicate_post', ['slug' => 'hello', 'target' => 'devto'], $this->principal('*:write'), $this->ctx);
            self::fail('expected the call to be refused');
        } catch (McpError) {
            self::assertNull($this->target->lastCall, 'it never reaches the Syndicator');
        }
    }

    public function test_status_returns_a_result_when_scoped(): void
    {
        $out = $this->toolset->call('blog_syndication_status', ['slug' => 'hello'], $this->principal('nimbuscms.blog:syndicate'), $this->ctx);
        self::assertNotNull($out);
    }
}

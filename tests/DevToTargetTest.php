<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\DevToTarget;
use NimbusCMS\Blog\SyndicationError;
use PHPUnit\Framework\TestCase;

/**
 * The Dev.to adapter: it builds the right request to create and to update, sends the
 * canonical and sanitised tags, and turns a non-2xx into a clear error. The fake HTTP
 * client captures the request, so nothing touches the network.
 */
final class DevToTargetTest extends TestCase
{
    /** @return array{title:string,body:string,tags:list<string>,canonical:string} */
    private function post(): array
    {
        return ['title' => 'Hello', 'body' => '# Hi', 'tags' => ['PHP', 'Web Dev', 'a-b', 'x', 'y', 'z'], 'canonical' => 'https://danmat.dev/blog/hello'];
    }

    public function test_create_posts_to_the_collection_with_key_canonical_and_tags(): void
    {
        $http   = new FakeHttpClient(201, '{"id":42,"url":"https://dev.to/dan/hello-42"}');
        $result = (new DevToTarget($http, 'secret-key'))->push($this->post(), null);

        self::assertSame('POST', $http->last['method']);
        self::assertSame('https://dev.to/api/articles', $http->last['url']);
        self::assertSame('secret-key', $http->last['headers']['api-key']);
        $sent = json_decode((string) $http->last['body'], true);
        self::assertTrue($sent['article']['published']);
        self::assertSame('https://danmat.dev/blog/hello', $sent['article']['canonical_url']);
        self::assertSame(['php', 'webdev', 'ab', 'x'], $sent['article']['tags'], 'lowercase alphanumeric, at most four');
        self::assertSame('42', $result['external_id']);
        self::assertSame('https://dev.to/dan/hello-42', $result['external_url']);
    }

    public function test_update_puts_to_the_article_id(): void
    {
        $http = new FakeHttpClient(200, '{"id":42,"url":"https://dev.to/dan/hello-42"}');
        (new DevToTarget($http, 'secret-key'))->push($this->post(), '42');

        self::assertSame('PUT', $http->last['method']);
        self::assertSame('https://dev.to/api/articles/42', $http->last['url']);
    }

    public function test_a_non_2xx_becomes_a_clear_error(): void
    {
        $this->expectException(SyndicationError::class);
        (new DevToTarget(new FakeHttpClient(422, '{"error":"nope"}'), 'k'))->push($this->post(), null);
    }

    public function test_a_403_message_is_open_about_the_cause_and_surfaces_the_response(): void
    {
        // A 403 is not necessarily the body: it may be a key/permissions issue. The
        // message must say so, and carry the raw platform response so it is diagnosable.
        try {
            (new DevToTarget(new FakeHttpClient(403, '{"error":"you are not allowed"}'), 'k'))->push($this->post(), null);
            self::fail('expected a SyndicationError');
        } catch (SyndicationError $e) {
            self::assertStringContainsString('HTTP 403', $e->getMessage());
            self::assertStringContainsString('key/permissions', $e->getMessage());
            self::assertStringContainsString('you are not allowed', $e->getMessage(), 'raw response is surfaced');
        }
    }

    public function test_unconfigured_reports_and_refuses(): void
    {
        $target = new DevToTarget(new FakeHttpClient(200, '{}'), null);
        self::assertFalse($target->isConfigured());
        $this->expectException(SyndicationError::class);
        $target->push($this->post(), null);
    }
}

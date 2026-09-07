<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\HashnodeTarget;
use NimbusCMS\Blog\SyndicationError;
use PHPUnit\Framework\TestCase;

/**
 * The Hashnode adapter: it builds the publishPost / updatePost GraphQL mutations,
 * sends the canonical as originalArticleURL and tags as {name, slug} objects, and
 * fails on both a non-2xx and the GraphQL 200-with-errors reply. The fake HTTP
 * client captures the request, so nothing touches the network.
 */
final class HashnodeTargetTest extends TestCase
{
    /** @return array{title:string,body:string,tags:list<string>,canonical:string} */
    private function post(): array
    {
        return ['title' => 'Hello', 'body' => '# Hi', 'tags' => ['PHP', 'Web Dev', 'a b', 'x', 'y', 'z'], 'canonical' => 'https://danmat.dev/blog/hello'];
    }

    private function target(FakeHttpClient $http): HashnodeTarget
    {
        return new HashnodeTarget($http, 'tok', 'pub-1');
    }

    public function test_create_publishes_with_publication_canonical_and_tag_objects(): void
    {
        $http   = new FakeHttpClient(200, '{"data":{"publishPost":{"post":{"id":"p42","url":"https://x.hashnode.dev/hello"}}}}');
        $result = $this->target($http)->push($this->post(), null);

        self::assertSame('POST', $http->last['method']);
        self::assertSame('https://gql.hashnode.com', $http->last['url']);
        self::assertSame('tok', $http->last['headers']['Authorization']);

        $sent = json_decode((string) $http->last['body'], true);
        self::assertStringContainsString('publishPost', $sent['query']);
        $input = $sent['variables']['input'];
        self::assertSame('pub-1', $input['publicationId']);
        self::assertSame('https://danmat.dev/blog/hello', $input['originalArticleURL']);
        self::assertSame(
            [['name' => 'PHP', 'slug' => 'php'], ['name' => 'Web Dev', 'slug' => 'web-dev'], ['name' => 'a b', 'slug' => 'a-b'], ['name' => 'x', 'slug' => 'x'], ['name' => 'y', 'slug' => 'y']],
            $input['tags'],
            'tag objects, slug lowercase-hyphenated, at most five',
        );
        self::assertSame('p42', $result['external_id']);
        self::assertSame('https://x.hashnode.dev/hello', $result['external_url']);
    }

    public function test_update_uses_update_mutation_with_the_stored_id_and_no_publication(): void
    {
        $http = new FakeHttpClient(200, '{"data":{"updatePost":{"post":{"id":"p42","url":"https://x.hashnode.dev/hello"}}}}');
        $this->target($http)->push($this->post(), 'p42');

        $sent  = json_decode((string) $http->last['body'], true);
        $input = $sent['variables']['input'];
        self::assertStringContainsString('updatePost', $sent['query']);
        self::assertSame('p42', $input['id'], 'a stored id makes it an update');
        self::assertArrayNotHasKey('publicationId', $input, 'update targets an existing post');
    }

    public function test_a_graphql_errors_payload_on_a_200_becomes_a_clear_error(): void
    {
        $http = new FakeHttpClient(200, '{"errors":[{"message":"This action requires Hashnode Pro"}]}');
        $this->expectException(SyndicationError::class);
        $this->expectExceptionMessage('Hashnode Pro');
        $this->target($http)->push($this->post(), null);
    }

    public function test_a_non_2xx_becomes_a_clear_error(): void
    {
        $this->expectException(SyndicationError::class);
        $this->target(new FakeHttpClient(500, ''))->push($this->post(), null);
    }

    public function test_a_403_message_is_open_about_the_cause_and_surfaces_the_response(): void
    {
        try {
            $this->target(new FakeHttpClient(403, '{"message":"forbidden detail"}'))->push($this->post(), null);
            self::fail('expected a SyndicationError');
        } catch (SyndicationError $e) {
            self::assertStringContainsString('HTTP 403', $e->getMessage());
            self::assertStringContainsString('forbidden detail', $e->getMessage(), 'raw response is surfaced');
        }
    }

    public function test_unconfigured_without_a_publication_reports_and_refuses(): void
    {
        $target = new HashnodeTarget(new FakeHttpClient(200, '{}'), 'tok', null);
        self::assertFalse($target->isConfigured());
        $this->expectException(SyndicationError::class);
        $target->push($this->post(), null);
    }
}

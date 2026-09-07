<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Hashnode syndication target. Publishes with the `publishPost` GraphQL mutation and
 * updates with `updatePost`, against the single endpoint `https://gql.hashnode.com`,
 * authenticated by the operator's personal access token. The canonical is sent as
 * `originalArticleURL` so Hashnode credits the original, and tags are sent as the
 * `{name, slug}` objects Hashnode requires.
 *
 * GraphQL replies 200 even on failure, carrying an `errors` array, so this adapter
 * treats a non-2xx **or** an `errors` payload as a failure. Writing over the API
 * requires a Hashnode **Pro** account and a publication id — both operator settings
 * (`HASHNODE_TOKEN`, `HASHNODE_PUBLICATION_ID`); with either missing the target is
 * "not configured" and never called.
 */
final class HashnodeTarget implements SyndicationTarget
{
    private const ENDPOINT = 'https://gql.hashnode.com';

    private const PUBLISH = <<<'GQL'
        mutation Publish($input: PublishPostInput!) {
          publishPost(input: $input) { post { id url } }
        }
        GQL;

    private const UPDATE = <<<'GQL'
        mutation Update($input: UpdatePostInput!) {
          updatePost(input: $input) { post { id url } }
        }
        GQL;

    public function __construct(
        private HttpClient $http,
        private ?string $token,
        private ?string $publicationId,
    ) {
    }

    public function id(): string
    {
        return 'hashnode';
    }

    public function label(): string
    {
        return 'Hashnode';
    }

    public function isConfigured(): bool
    {
        return $this->token !== null && $this->token !== ''
            && $this->publicationId !== null && $this->publicationId !== '';
    }

    public function push(array $post, ?string $externalId): array
    {
        if (!$this->isConfigured()) {
            throw new SyndicationError('Hashnode is not configured (set HASHNODE_TOKEN and HASHNODE_PUBLICATION_ID).');
        }

        $create = $externalId === null || $externalId === '';
        if ($create) {
            $query = self::PUBLISH;
            $input = [
                'title'              => $post['title'],
                'contentMarkdown'    => $post['body'],
                'publicationId'      => $this->publicationId,
                'originalArticleURL' => $post['canonical'],
                'tags'               => $this->tags($post['tags']),
            ];
        } else {
            $query = self::UPDATE;
            $input = [
                'id'                 => $externalId,
                'title'              => $post['title'],
                'contentMarkdown'    => $post['body'],
                'originalArticleURL' => $post['canonical'],
                'tags'               => $this->tags($post['tags']),
            ];
        }

        $body = json_encode(['query' => $query, 'variables' => ['input' => $input]], JSON_THROW_ON_ERROR);
        $resp = $this->http->send('POST', self::ENDPOINT, [
            'Authorization' => (string) $this->token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $body);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new SyndicationError('Hashnode returned HTTP ' . $resp['status'] . '.');
        }

        $data = json_decode($resp['body'], true);
        $data = is_array($data) ? $data : [];
        if (isset($data['errors'][0]['message']) && is_string($data['errors'][0]['message'])) {
            throw new SyndicationError('Hashnode: ' . $data['errors'][0]['message']);
        }

        $key  = $create ? 'publishPost' : 'updatePost';
        $node = $data['data'][$key]['post'] ?? null;
        if (!is_array($node)) {
            throw new SyndicationError('Hashnode returned no post.');
        }

        return [
            'external_id'  => (string) ($node['id'] ?? $externalId ?? ''),
            'external_url' => (string) ($node['url'] ?? ''),
        ];
    }

    /**
     * Hashnode wants tags as {name, slug} objects: the slug is lowercase, hyphenated,
     * alphanumeric; the name keeps the author's wording. It recommends at most five.
     *
     * @param list<string> $tags
     * @return list<array{name:string,slug:string}>
     */
    private function tags(array $tags): array
    {
        $clean = [];
        $seen  = [];
        foreach ($tags as $tag) {
            $name = trim($tag);
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
            if ($name === '' || $slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $clean[]     = ['name' => $name, 'slug' => $slug];
            if (count($clean) === 5) {
                break;
            }
        }
        return $clean;
    }
}

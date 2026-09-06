<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Dev.to (Forem) syndication target. Creates an article with `POST /api/articles`
 * and updates it with `PUT /api/articles/{id}`, authenticated by the operator's
 * `api-key`. The canonical URL is sent so Dev.to credits the original, and tags are
 * sanitised to Dev.to's rules (lowercase alphanumeric, at most four).
 */
final class DevToTarget implements SyndicationTarget
{
    private const ENDPOINT = 'https://dev.to/api/articles';

    public function __construct(private HttpClient $http, private ?string $apiKey)
    {
    }

    public function id(): string
    {
        return 'devto';
    }

    public function label(): string
    {
        return 'Dev.to';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function push(array $post, ?string $externalId): array
    {
        if (!$this->isConfigured()) {
            throw new SyndicationError('Dev.to is not configured (set DEVTO_API_KEY).');
        }

        $payload = ['article' => [
            'title'         => $post['title'],
            'body_markdown' => $post['body'],
            'published'     => true,
            'canonical_url' => $post['canonical'],
            'tags'          => $this->tags($post['tags']),
        ]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $create = $externalId === null || $externalId === '';
        $resp   = $this->http->send(
            $create ? 'POST' : 'PUT',
            $create ? self::ENDPOINT : self::ENDPOINT . '/' . rawurlencode($externalId),
            [
                'api-key'      => (string) $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/vnd.forem.api-v1+json',
            ],
            $body,
        );

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new SyndicationError('Dev.to returned HTTP ' . $resp['status'] . '.');
        }
        $data = json_decode($resp['body'], true);
        $data = is_array($data) ? $data : [];

        return [
            'external_id'  => (string) ($data['id'] ?? $externalId ?? ''),
            'external_url' => (string) ($data['url'] ?? ''),
        ];
    }

    /**
     * Dev.to tags must be lowercase alphanumeric and it accepts at most four.
     *
     * @param list<string> $tags
     * @return list<string>
     */
    private function tags(array $tags): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $tag));
            if ($slug !== '' && !in_array($slug, $clean, true)) {
                $clean[] = $slug;
            }
            if (count($clean) === 4) {
                break;
            }
        }
        return $clean;
    }
}

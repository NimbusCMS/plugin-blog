<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * The one place syndication happens, called by both the admin action and the MCP
 * tool so the two surfaces share behaviour, authority, and audit. Given a post slug
 * and a target id it reads the published post, computes the canonical, calls the
 * target adapter (create, or update when a prior external id is stored), and records
 * the result so a repeat push updates rather than duplicates.
 *
 * The post is read through a fetch closure wired to the plugin's published-only
 * content reader (ADR 0029), so this service is unit-testable without a database and
 * can never syndicate a draft.
 */
final class Syndicator
{
    /**
     * @param array<string,SyndicationTarget> $targets    keyed by target id
     * @param \Closure(string):(array<string,mixed>|null) $fetchBySlug published post view-model, or null
     */
    public function __construct(
        private array $targets,
        private SyndicationStore $store,
        private \Closure $fetchBySlug,
        private string $siteUrl,
        private string $basePath = '/blog',
    ) {
    }

    /** @return list<SyndicationTarget> */
    public function targets(): array
    {
        return array_values($this->targets);
    }

    /**
     * Push a published post to one target. Records the outcome either way.
     *
     * @return array{target:string,external_id:string,external_url:string}
     */
    public function syndicate(string $slug, string $targetId, string $now): array
    {
        $target = $this->targets[$targetId] ?? null;
        if ($target === null) {
            throw new SyndicationError('Unknown syndication target: ' . $targetId);
        }
        $post = ($this->fetchBySlug)($slug);
        if ($post === null) {
            throw new SyndicationError('No published post with slug: ' . $slug);
        }
        $entryId  = (int) ($post['id'] ?? 0);
        $existing = $this->store->get($entryId, $targetId);
        $payload  = $this->payload($post);

        try {
            $result = $target->push($payload, $existing['external_id'] ?? null);
        } catch (\Throwable $e) {
            // Keep any prior mapping, mark the attempt failed, and surface the error.
            $this->store->record($entryId, $targetId, $existing['external_id'] ?? null, $existing['external_url'] ?? '', 'error', $now);
            throw $e instanceof SyndicationError ? $e : new SyndicationError($e->getMessage());
        }

        $this->store->record($entryId, $targetId, $result['external_id'], $result['external_url'], 'ok', $now);

        return ['target' => $targetId, 'external_id' => $result['external_id'], 'external_url' => $result['external_url']];
    }

    /**
     * The syndication state of a post across targets (for the admin page / status tool),
     * or null if there is no such published post.
     *
     * @return array{slug:string,entry_id:int,records:list<array{target:string,external_id:?string,external_url:?string,status:string,synced_at:string}>}|null
     */
    public function statusFor(string $slug): ?array
    {
        $post = ($this->fetchBySlug)($slug);
        if ($post === null) {
            return null;
        }
        $entryId = (int) ($post['id'] ?? 0);
        return ['slug' => $slug, 'entry_id' => $entryId, 'records' => $this->store->forEntry($entryId)];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{title:string,body:string,tags:list<string>,canonical:string}
     */
    private function payload(array $post): array
    {
        $fields = is_array($post['fields'] ?? null) ? $post['fields'] : [];
        return [
            'title'     => (string) ($post['title'] ?? ''),
            'body'      => (string) ($fields['body'] ?? ''),
            'tags'      => $this->tags((string) ($fields['tags'] ?? '')),
            'canonical' => $this->canonical($post, $fields),
        ];
    }

    /**
     * The true original: the post's declared canonical_url if it is itself a copy,
     * otherwise this site's own /blog/{slug} URL.
     *
     * @param array<string,mixed> $post
     * @param array<string,mixed> $fields
     */
    private function canonical(array $post, array $fields): string
    {
        $declared = trim((string) ($fields['canonical_url'] ?? ''));
        if ($declared !== '' && preg_match('#^https?://#i', $declared) === 1) {
            return $declared;
        }
        return rtrim($this->siteUrl, '/') . '/' . trim($this->basePath, '/') . '/' . (string) ($post['slug'] ?? '');
    }

    /** @return list<string> */
    private function tags(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $out[] = $tag;
            }
        }
        return $out;
    }
}

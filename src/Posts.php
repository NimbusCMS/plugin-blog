<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Reads published posts from the blog collection and maps each to the flat shape
 * the feed and the tag archive need. Depends on a fetch closure (wired to
 * `ContentReader::entries` at runtime) rather than the reader directly, so it is
 * unit-testable with a canned list and never sees drafts (the reader is
 * published-only, ADR 0029).
 */
final class Posts
{
    /** @param \Closure(int):list<array<string,mixed>> $fetch published entries (view-models), newest first */
    public function __construct(private \Closure $fetch)
    {
    }

    /**
     * The latest published posts, mapped.
     *
     * @return list<array{title:string,slug:string,summary:string,published_at:string,tags:list<string>,canonical_url:string}>
     */
    public function published(int $limit = 50): array
    {
        $out = [];
        foreach (($this->fetch)($limit) as $entry) {
            $out[] = $this->map($entry);
        }
        return $out;
    }

    /**
     * Published posts carrying the given tag (case-insensitive, exact tag match).
     *
     * @return list<array{title:string,slug:string,summary:string,published_at:string,tags:list<string>,canonical_url:string}>
     */
    public function byTag(string $tag, int $limit = 200): array
    {
        $needle = mb_strtolower(trim($tag));
        if ($needle === '') {
            return [];
        }
        $out = [];
        foreach ($this->published($limit) as $post) {
            foreach ($post['tags'] as $t) {
                if (mb_strtolower($t) === $needle) {
                    $out[] = $post;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $entry
     * @return array{title:string,slug:string,summary:string,published_at:string,tags:list<string>,canonical_url:string}
     */
    private function map(array $entry): array
    {
        $fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
        return [
            'title'         => (string) ($entry['title'] ?? ''),
            'slug'          => (string) ($entry['slug'] ?? ''),
            'summary'       => trim((string) ($fields['summary'] ?? '')),
            'published_at'  => (string) ($entry['published_at'] ?? ''),
            'tags'          => $this->splitTags((string) ($fields['tags'] ?? '')),
            'canonical_url' => trim((string) ($fields['canonical_url'] ?? '')),
        ];
    }

    /** @return list<string> */
    private function splitTags(string $csv): array
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

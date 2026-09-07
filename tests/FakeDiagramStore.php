<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\DiagramStore;

/** In-memory content-addressed store; `$failStore` simulates an upload failure. */
final class FakeDiagramStore implements DiagramStore
{
    /** @var array<string,string> hash => png */
    public array $saved = [];

    public function __construct(public bool $failStore = false)
    {
    }

    public function has(string $hash): bool
    {
        return isset($this->saved[$hash]);
    }

    public function url(string $hash): string
    {
        return 'https://danmat.dev/uploads/nimbuscms.blog/diagrams/' . substr($hash, 0, 2) . '/' . $hash . '.png';
    }

    public function store(string $hash, string $png): bool
    {
        if ($this->failStore) {
            return false;
        }
        $this->saved[$hash] = $png;
        return true;
    }
}

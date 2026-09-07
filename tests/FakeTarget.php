<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\SyndicationError;
use NimbusCMS\Blog\SyndicationTarget;

/** A fake syndication target that records its last call and can be told to fail. */
final class FakeTarget implements SyndicationTarget
{
    /** @var array{post:array<string,mixed>,externalId:?string}|null */
    public ?array $lastCall = null;
    public bool $throw = false;

    public function __construct(private string $id = 'devto', private bool $configured = true)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return ucfirst($this->id);
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function push(array $post, ?string $externalId): array
    {
        $this->lastCall = ['post' => $post, 'externalId' => $externalId];
        if ($this->throw) {
            throw new SyndicationError('boom');
        }
        return ['external_id' => '42', 'external_url' => 'https://dev.to/dan/hello-42'];
    }
}

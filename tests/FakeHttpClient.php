<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\HttpClient;

/** A fake HTTP client that records the last request and returns a canned response. */
final class FakeHttpClient implements HttpClient
{
    /** @var array<string,mixed> */
    public array $last = [];

    public function __construct(private int $status, private string $body)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->last = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        return ['status' => $this->status, 'body' => $this->body];
    }
}

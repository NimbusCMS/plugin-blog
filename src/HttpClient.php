<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * The narrow outbound-HTTP seam a syndication target uses. Kept as an interface so
 * an adapter can be unit-tested with a canned client and never touches the network
 * in tests. The real implementation is {@see CurlHttpClient}.
 */
interface HttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}

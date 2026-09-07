<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * A content-addressed host for rendered diagram PNGs: the content hash is the identity,
 * so a diagram is rendered and stored once and every later cross-post reuses the same
 * public URL. The stored file is the cache, so there is no cache table (and no
 * migration to run on deploy).
 */
interface DiagramStore
{
    /** Is a PNG for this content hash already hosted? */
    public function has(string $hash): bool;

    /** The absolute public URL a PNG for this hash has (or would have). */
    public function url(string $hash): string;

    /** Host the PNG bytes under this hash. False on any failure (the caller falls back). */
    public function store(string $hash, string $png): bool;
}

<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Turns SVG markup into PNG bytes, or null if it cannot (unsupported markup, no
 * rasterizer available, a render failure). Null is a normal outcome the caller
 * handles by falling back, never an exception.
 */
interface Rasterizer
{
    public function toPng(string $svg): ?string;
}

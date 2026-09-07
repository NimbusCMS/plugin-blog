<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\Rasterizer;

/** A rasterizer that returns fixed PNG bytes, or null to simulate a render failure. */
final class FakeRasterizer implements Rasterizer
{
    public ?string $lastSvg = null;

    public function __construct(private ?string $png = 'PNGBYTES')
    {
    }

    public function toPng(string $svg): ?string
    {
        $this->lastSvg = $svg;
        return $this->png;
    }
}

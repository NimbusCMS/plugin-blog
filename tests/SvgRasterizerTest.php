<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\SvgRasterizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rasterizer's safety guard and process handling. A stub binary stands in for
 * rsvg-convert (it writes bytes to the -o path), so a *safe* SVG renders while every
 * dangerous or malformed SVG is refused before anything is executed. The refusals are
 * the security-critical behaviour: an unsafe SVG never reaches the renderer, and the
 * caller gets null (and falls back).
 */
final class SvgRasterizerTest extends TestCase
{
    private string $stub;

    protected function setUp(): void
    {
        // A stand-in "rasterizer": find -o <path> and write PNG bytes there, exit 0.
        $this->stub = sys_get_temp_dir() . '/rz_stub_' . bin2hex(random_bytes(4)) . '.sh';
        file_put_contents($this->stub, "#!/bin/sh\nout=\nwhile [ \$# -gt 0 ]; do\n  if [ \"\$1\" = \"-o\" ]; then out=\"\$2\"; fi\n  shift\ndone\nprintf 'PNGDATA' > \"\$out\"\n");
        chmod($this->stub, 0o700);
    }

    protected function tearDown(): void
    {
        @unlink($this->stub);
    }

    private function raster(): SvgRasterizer
    {
        return new SvgRasterizer($this->stub);
    }

    public function test_a_safe_svg_is_rendered(): void
    {
        $png = $this->raster()->toPng('<svg viewBox="0 0 10 10"><rect x="1" y="1" width="8" height="8" stroke="currentColor"/></svg>');
        self::assertSame('PNGDATA', $png, 'a safe svg reaches the renderer');
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function unsafeSvgs(): iterable
    {
        yield 'script'          => ['<svg><script>alert(1)</script></svg>'];
        yield 'event handler'   => ['<svg onload="x()"><rect/></svg>'];
        yield 'foreignObject'   => ['<svg><foreignObject><b>x</b></foreignObject></svg>'];
        yield 'doctype/entity'  => ['<!DOCTYPE svg [<!ENTITY x "y">]><svg><rect/></svg>'];
        yield 'external href'   => ['<svg><image href="file:///etc/passwd"/></svg>'];
        yield 'external xlink'  => ['<svg><image xlink:href="https://evil.example/x.png"/></svg>'];
        yield 'javascript href' => ['<svg><a href="javascript:alert(1)"><rect/></a></svg>'];
        yield 'not an svg'      => ['<div>not svg</div>'];
        yield 'empty'           => [''];
    }

    #[DataProvider('unsafeSvgs')]
    public function test_an_unsafe_or_malformed_svg_is_refused(string $svg): void
    {
        self::assertNull($this->raster()->toPng($svg), 'unsafe svg must not be rendered');
    }

    public function test_an_oversized_svg_is_refused(): void
    {
        $huge = '<svg>' . str_repeat('<rect/>', 100000) . '</svg>';
        self::assertNull($this->raster()->toPng($huge));
    }

    public function test_a_fragment_href_is_allowed(): void
    {
        // An in-document reference (#id) is safe and must not be treated as external.
        $png = $this->raster()->toPng('<svg><use href="#icon"/><g id="icon"><rect/></g></svg>');
        self::assertSame('PNGDATA', $png);
    }

    public function test_a_missing_binary_degrades_to_null(): void
    {
        $png = (new SvgRasterizer('rsvg-convert-does-not-exist-xyz'))->toPng('<svg><rect/></svg>');
        self::assertNull($png, 'no rasterizer installed → null, not an error');
    }
}

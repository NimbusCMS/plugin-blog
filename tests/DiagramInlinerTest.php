<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\DiagramInliner;
use PHPUnit\Framework\TestCase;

/**
 * The body transform for external targets: inline SVG becomes a hosted markdown image,
 * prose and fenced code are untouched, a diagram is rendered/hosted once, and any
 * render or host failure degrades to a canonical pointer rather than leaving raw SVG.
 */
final class DiagramInlinerTest extends TestCase
{
    private const CANON = 'https://danmat.dev/blog/hello';

    public function test_it_replaces_inline_svg_with_a_hosted_markdown_image(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(), new FakeDiagramStore());
        $body    = "Intro prose.\n\n<svg viewBox=\"0 0 10 10\" aria-label=\"Flow of data\"><rect/></svg>\n\nMore prose.";

        $out = $inliner->inline($body, self::CANON);

        self::assertStringNotContainsString('<svg', $out, 'no raw svg goes to the target');
        self::assertMatchesRegularExpression('/!\[Flow of data\]\(https:\/\/danmat\.dev\/uploads\/[^\s)]+\.png\)/', $out);
        self::assertStringContainsString('Intro prose.', $out);
        self::assertStringContainsString('More prose.', $out);
    }

    public function test_a_svg_without_aria_label_gets_a_plain_alt(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(), new FakeDiagramStore());
        $out     = $inliner->inline('<svg><rect/></svg>', self::CANON);
        self::assertStringContainsString('![Diagram](https://danmat.dev/uploads/', $out);
    }

    public function test_it_leaves_svg_inside_a_fenced_code_block_untouched(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(), new FakeDiagramStore());
        $body    = "Before.\n\n```html\n<svg><rect/></svg>\n```\n\nAfter.";

        $out = $inliner->inline($body, self::CANON);

        self::assertStringContainsString("```html\n<svg><rect/></svg>\n```", $out, 'the fenced example is preserved verbatim');
        self::assertStringNotContainsString('![Diagram]', $out);
    }

    public function test_it_transforms_prose_svg_but_not_the_fenced_one(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(), new FakeDiagramStore());
        $body    = "<svg aria-label=\"real\"><rect/></svg>\n\n```\n<svg><rect/></svg>\n```";

        $out = $inliner->inline($body, self::CANON);

        self::assertStringContainsString('![real](https://danmat.dev/uploads/', $out);
        self::assertStringContainsString("```\n<svg><rect/></svg>\n```", $out);
    }

    public function test_it_renders_and_hosts_each_distinct_diagram_once(): void
    {
        $raster  = new FakeRasterizer();
        $store   = new FakeDiagramStore();
        $inliner = new DiagramInliner($raster, $store);
        // Same svg twice + a different one → two stored images.
        $svg     = '<svg aria-label="a"><rect/></svg>';
        $inliner->inline($svg . "\n\n" . $svg . "\n\n" . '<svg aria-label="b"><circle/></svg>', self::CANON);
        self::assertCount(2, $store->saved);
    }

    public function test_a_render_failure_falls_back_to_the_canonical_pointer(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(null), new FakeDiagramStore());
        $out     = $inliner->inline('Prose.\n\n<svg><rect/></svg>', self::CANON);

        self::assertStringNotContainsString('<svg', $out);
        self::assertStringNotContainsString('![', $out, 'no image when nothing was hosted');
        self::assertStringContainsString('Diagram omitted in this cross-post', $out);
        self::assertStringContainsString(self::CANON, $out);
    }

    public function test_a_host_failure_also_falls_back(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(), new FakeDiagramStore(failStore: true));
        $out     = $inliner->inline('<svg><rect/></svg>', self::CANON);
        self::assertStringContainsString('Diagram omitted in this cross-post', $out);
        self::assertStringNotContainsString('![', $out);
    }

    public function test_a_body_with_no_svg_is_returned_unchanged(): void
    {
        $inliner = new DiagramInliner(new FakeRasterizer(), new FakeDiagramStore());
        $body    = "# Title\n\nJust prose and `inline code`.";
        self::assertSame($body, $inliner->inline($body, self::CANON));
    }
}

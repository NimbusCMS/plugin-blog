<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Rewrites a post body for an external platform that rejects raw inline SVG (Dev.to's
 * Forem and Hashnode both do, with a 403 / sanitised drop). Each inline `<svg>…</svg>`
 * becomes a normal markdown image pointing at a rendered, hosted PNG, so the diagram
 * actually shows on the cross-post. The stored body on this site is never changed;
 * only the copy handed to a target is transformed.
 *
 * A diagram is rendered and hosted once (content-addressed), so re-syndication reuses
 * the same image. If a diagram cannot be rendered or hosted, it degrades to a short
 * pointer to the canonical original rather than failing the whole cross-post. Fenced
 * code blocks and surrounding prose are left exactly as they are.
 */
final class DiagramInliner
{
    public function __construct(private Rasterizer $rasterizer, private DiagramStore $store)
    {
    }

    public function inline(string $body, string $canonical): string
    {
        if (stripos($body, '<svg') === false) {
            return $body;
        }

        // Split out fenced code blocks (``` or ~~~) and transform only the prose between
        // them, so an <svg> shown as example code in a fence is left untouched.
        $parts = preg_split('/(```[\s\S]*?```|~~~[\s\S]*?~~~)/', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $body;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue; // a captured fenced block
            }
            $parts[$i] = $this->replaceSvg($part, $canonical);
        }
        return implode('', $parts);
    }

    private function replaceSvg(string $text, string $canonical): string
    {
        $out = preg_replace_callback('/<svg[\s\S]*?<\/svg>/i', function (array $m) use ($canonical): string {
            $svg = $m[0];
            $hash = hash('sha256', $svg);
            if (!$this->store->has($hash)) {
                $png = $this->rasterizer->toPng($svg);
                if ($png === null || !$this->store->store($hash, $png)) {
                    return $this->fallback($canonical);
                }
            }
            return '![' . $this->alt($svg) . '](' . $this->store->url($hash) . ')';
        }, $text);

        return $out ?? $text;
    }

    /** A pointer used when a diagram cannot be rendered, so the post still works. */
    private function fallback(string $canonical): string
    {
        return "\n\n> Diagram omitted in this cross-post. See the original for the full figure:\n> " . $canonical . "\n\n";
    }

    /** Alt text from the SVG's aria-label if it has one, else a plain label. */
    private function alt(string $svg): string
    {
        if (preg_match('/aria-label\s*=\s*"([^"]*)"/i', $svg, $m) === 1
            || preg_match("/aria-label\s*=\s*'([^']*)'/i", $svg, $m) === 1) {
            $label = trim(str_replace(["\n", "\r", '[', ']'], ' ', $m[1]));
            if ($label !== '') {
                return $label;
            }
        }
        return 'Diagram';
    }
}

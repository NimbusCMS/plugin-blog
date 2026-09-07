<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Rasterizes SVG to PNG with `rsvg-convert` (librsvg). It renders onto a solid light
 * background with a concrete dark ink for `currentColor`, so a diagram that relies on
 * `currentColor` (invisible when rendered standalone on transparency) reads correctly
 * on any external platform's light or dark theme; declared colours (an accent hex)
 * are left untouched.
 *
 * SECURITY: the SVG is author-supplied and treated as untrusted. Before rendering it
 * is checked for the dangerous constructs an SVG can carry — scripts, event handlers,
 * `foreignObject`, a DOCTYPE/entity (XXE), and any external `href` (a `file:`/remote
 * reference is an SSRF / local-file-read vector, since the rendered pixels are then
 * hosted publicly). Anything matching is refused (null → the caller falls back). The
 * SVG is written into a fresh private temp dir and rendered with an argv array (no
 * shell, so no command injection) under a wall-clock timeout; librsvg itself runs no
 * scripts and fetches no network resources.
 */
final class SvgRasterizer implements Rasterizer
{
    public function __construct(
        private string $binary = 'rsvg-convert',
        private int $width = 1200,
        private string $background = '#fbfaf7',
        private string $ink = '#1f2933',
        private int $timeoutSeconds = 10,
        private int $maxBytes = 512000,
    ) {
    }

    public function toPng(string $svg): ?string
    {
        if (!$this->isSafe($svg)) {
            return null;
        }

        $dir = sys_get_temp_dir() . '/blogsvg_' . bin2hex(random_bytes(6));
        if (!@mkdir($dir, 0o700, true)) {
            return null;
        }
        try {
            $in  = $dir . '/in.svg';
            $css = $dir . '/ink.css';
            $out = $dir . '/out.png';
            if (@file_put_contents($in, $svg) === false || @file_put_contents($css, 'svg{color:' . $this->ink . '}') === false) {
                return null;
            }
            $ok = $this->run([
                $this->binary,
                '-w', (string) $this->width,
                '--keep-aspect-ratio',
                '-b', $this->background,
                '-s', $css,
                '-f', 'png',
                '-o', $out,
                $in,
            ]);
            if (!$ok || !is_file($out)) {
                return null;
            }
            $png = @file_get_contents($out);
            return is_string($png) && $png !== '' ? $png : null;
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    /** Reject the SVG constructs that make rasterizing untrusted markup dangerous. */
    private function isSafe(string $svg): bool
    {
        if ($svg === '' || strlen($svg) > $this->maxBytes) {
            return false;
        }
        if (preg_match('/^\s*<svg[\s>]/i', $svg) !== 1) {
            return false;
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY|<script|<foreignObject|\son[a-z]+\s*=|javascript:/i', $svg) === 1) {
            return false;
        }
        // Any href/xlink:href that is not an in-document fragment (#id) is external.
        if (preg_match('/(?:xlink:)?href\s*=\s*["\'](?!#)/i', $svg) === 1) {
            return false;
        }
        return true;
    }

    /**
     * Run an argv array with no shell, under a timeout. True only on a clean exit 0.
     *
     * @param list<string> $argv
     */
    private function run(array $argv): bool
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc        = @proc_open($argv, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return false;
        }
        fclose($pipes[0]);
        $deadline = microtime(true) + $this->timeoutSeconds;
        $code     = -1;
        while (true) {
            $status = proc_get_status($proc);
            if ($status['running'] === false) {
                $code = $status['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                break;
            }
            usleep(50000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        return $code === 0;
    }
}

<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * The default {@see HttpClient}, a thin wrapper over curl. Outbound only, to the
 * fixed platform endpoints a target names (never a user-supplied URL), with a
 * bounded timeout. No global state; a test uses a fake client instead of this.
 */
final class CurlHttpClient implements HttpClient
{
    public function __construct(private int $timeoutSeconds = 15)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new SyndicationError('Could not initialise an HTTP request.');
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new SyndicationError('HTTP request failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => is_string($response) ? $response : ''];
    }
}

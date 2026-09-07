<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\SyndicationStore;

/** An in-memory SyndicationStore. */
final class FakeStore implements SyndicationStore
{
    /** @var array<string,array{external_id:?string,external_url:?string,status:string}> */
    public array $rows = [];

    public function get(int $entryId, string $target): ?array
    {
        return $this->rows[$entryId . ':' . $target] ?? null;
    }

    public function record(int $entryId, string $target, ?string $externalId, string $externalUrl, string $status, string $now): void
    {
        $this->rows[$entryId . ':' . $target] = ['external_id' => $externalId, 'external_url' => $externalUrl, 'status' => $status];
    }

    public function forEntry(int $entryId): array
    {
        $out = [];
        foreach ($this->rows as $key => $row) {
            [$e, $t] = explode(':', $key, 2);
            if ((int) $e === $entryId) {
                $out[] = ['target' => $t, 'external_id' => $row['external_id'], 'external_url' => $row['external_url'], 'status' => $row['status'], 'synced_at' => '2026-01-01 00:00:00'];
            }
        }
        return $out;
    }
}

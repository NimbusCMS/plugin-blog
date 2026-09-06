<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

use Nimbus\Plugin\PluginStorage;

/**
 * The database-backed {@see SyndicationStore}, over the plugin's own
 * {@see Schema::SYNDICATION} table (ADR 0005). Storage is resolved lazily so
 * constructing this runs no query. Every statement is parameter-bound.
 */
final class SyndicationRepository implements SyndicationStore
{
    /** @param \Closure():PluginStorage $storage */
    public function __construct(private \Closure $storage)
    {
    }

    public function get(int $entryId, string $target): ?array
    {
        $row = $this->storage()->selectOne(
            'SELECT external_id, external_url, status FROM ' . Schema::SYNDICATION . ' WHERE entry_id = :e AND target = :t',
            ['e' => $entryId, 't' => $target],
        );
        if ($row === null) {
            return null;
        }
        return [
            'external_id'  => ($row['external_id'] ?? null) === null ? null : (string) $row['external_id'],
            'external_url' => ($row['external_url'] ?? null) === null ? null : (string) $row['external_url'],
            'status'       => (string) $row['status'],
        ];
    }

    public function record(int $entryId, string $target, ?string $externalId, string $externalUrl, string $status, string $now): void
    {
        // Upsert via the MySQL 8 row-alias form (no reused named placeholder).
        $this->storage()->execute(
            'INSERT INTO ' . Schema::SYNDICATION . ' (entry_id, target, external_id, external_url, status, synced_at)
             VALUES (:e, :t, :xid, :url, :st, :now) AS new
             ON DUPLICATE KEY UPDATE external_id = new.external_id, external_url = new.external_url, status = new.status, synced_at = new.synced_at',
            ['e' => $entryId, 't' => $target, 'xid' => $externalId, 'url' => $externalUrl, 'st' => $status, 'now' => $now],
        );
    }

    public function forEntry(int $entryId): array
    {
        $rows = $this->storage()->select(
            'SELECT target, external_id, external_url, status, synced_at FROM ' . Schema::SYNDICATION . ' WHERE entry_id = :e ORDER BY target',
            ['e' => $entryId],
        );
        return array_map(static fn (array $r): array => [
            'target'       => (string) $r['target'],
            'external_id'  => ($r['external_id'] ?? null) === null ? null : (string) $r['external_id'],
            'external_url' => ($r['external_url'] ?? null) === null ? null : (string) $r['external_url'],
            'status'       => (string) $r['status'],
            'synced_at'    => (string) $r['synced_at'],
        ], $rows);
    }

    private function storage(): PluginStorage
    {
        return ($this->storage)();
    }
}

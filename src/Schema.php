<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * The blog plugin's own storage (ADR 0005). Until syndication the plugin was
 * table-less; this is its first and only table. It maps a blog entry to the copy
 * it created on an external platform, so a repeat push updates that copy instead
 * of creating a duplicate. It holds no post content and no secrets, only the
 * external mapping, and touches no core data.
 */
final class Schema
{
    public const SYNDICATION = 'blog_syndication';

    /** @return list<string> each statement individually idempotent */
    public static function all(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS ' . self::SYNDICATION . ' (
                entry_id     BIGINT UNSIGNED NOT NULL,
                target       VARCHAR(32) NOT NULL,
                external_id  VARCHAR(191) NULL,
                external_url VARCHAR(512) NULL,
                status       VARCHAR(16) NOT NULL DEFAULT "ok",
                synced_at    DATETIME NOT NULL,
                PRIMARY KEY (entry_id, target)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];
    }
}

<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use NimbusCMS\Blog\Schema;
use NimbusCMS\Blog\SyndicationRepository;
use PHPUnit\Framework\TestCase;

/**
 * The database-backed store: a first push inserts, a second for the same
 * (entry, target) updates in place (never a duplicate), and reads come back shaped.
 * Backed by the plugin's own table.
 */
final class SyndicationRepositoryTest extends TestCase
{
    private SyndicationRepository $repo;
    private PluginStorage $storage;

    protected function setUp(): void
    {
        $db = new Connection([
            'host' => getenv('TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => getenv('TEST_DB_NAME') ?: 'nimbus_test',
            'user' => getenv('TEST_DB_USER') ?: 'root',
            'pass' => ($p = getenv('TEST_DB_PASS')) !== false ? $p : 'root',
        ]);
        foreach (Schema::all() as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::SYNDICATION);
        $this->storage = new PluginStorage($db);
        $this->repo    = new SyndicationRepository(fn (): PluginStorage => $this->storage);
    }

    public function test_record_inserts_then_updates_in_place(): void
    {
        self::assertNull($this->repo->get(7, 'devto'));

        $this->repo->record(7, 'devto', '42', 'https://dev.to/dan/hello-42', 'ok', '2026-01-01 09:00:00');
        $first = $this->repo->get(7, 'devto');
        self::assertSame('42', $first['external_id']);
        self::assertSame('ok', $first['status']);

        // Same (entry, target) again: update, not a second row.
        $this->repo->record(7, 'devto', '42', 'https://dev.to/dan/hello-42', 'error', '2026-01-02 10:00:00');
        self::assertSame('error', $this->repo->get(7, 'devto')['status']);
        self::assertCount(1, $this->repo->forEntry(7), 'still one row for the pair');
    }

    public function test_for_entry_lists_each_target(): void
    {
        $this->repo->record(7, 'devto', '42', 'https://dev.to/x', 'ok', '2026-01-01 09:00:00');
        $this->repo->record(7, 'hashnode', 'abc', 'https://hn.example/x', 'ok', '2026-01-01 09:00:00');
        $this->repo->record(8, 'devto', '99', 'https://dev.to/y', 'ok', '2026-01-01 09:00:00');

        $rows = $this->repo->forEntry(7);
        self::assertCount(2, $rows);
        self::assertSame(['devto', 'hashnode'], array_map(static fn (array $r): string => $r['target'], $rows));
    }
}

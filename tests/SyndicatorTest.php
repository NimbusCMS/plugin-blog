<?php

declare(strict_types=1);

namespace NimbusCMS\Blog\Tests;

use NimbusCMS\Blog\SyndicationError;
use NimbusCMS\Blog\SyndicationStore;
use NimbusCMS\Blog\SyndicationTarget;
use NimbusCMS\Blog\Syndicator;
use PHPUnit\Framework\TestCase;

/** A fake target that records its last call and can be told to fail. */
final class FakeTarget implements SyndicationTarget
{
    /** @var array{post:array<string,mixed>,externalId:?string}|null */
    public ?array $lastCall = null;
    public bool $throw = false;

    public function id(): string
    {
        return 'devto';
    }

    public function label(): string
    {
        return 'Dev.to';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function push(array $post, ?string $externalId): array
    {
        $this->lastCall = ['post' => $post, 'externalId' => $externalId];
        if ($this->throw) {
            throw new SyndicationError('boom');
        }
        return ['external_id' => '42', 'external_url' => 'https://dev.to/dan/hello-42'];
    }
}

/** An in-memory {@see SyndicationStore}. */
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

/**
 * The Syndicator: canonical computation (self vs a declared original), create vs
 * update from the stored external id, recording success and failure, and the
 * unknown-target / missing-post guards. Fully faked, no DB and no network.
 */
final class SyndicatorTest extends TestCase
{
    /** @param array<string,mixed> $fields */
    private function make(FakeTarget $target, FakeStore $store, array $fields = [], string $slug = 'hello'): Syndicator
    {
        $post = ['id' => 7, 'slug' => $slug, 'title' => 'Hello', 'fields' => array_merge(['body' => '# Hi', 'tags' => 'php, web', 'canonical_url' => ''], $fields)];
        return new Syndicator(['devto' => $target], $store, static fn (string $s): ?array => $s === $slug ? $post : null, 'https://danmat.dev', '/blog');
    }

    public function test_it_creates_when_nothing_is_stored_and_records_ok(): void
    {
        $t = new FakeTarget();
        $s = new FakeStore();
        $out = $this->make($t, $s)->syndicate('hello', 'devto', '2026-01-02 09:00:00');

        self::assertNotNull($t->lastCall);
        self::assertNull($t->lastCall['externalId'], 'a create passes no external id');
        self::assertSame('https://danmat.dev/blog/hello', $t->lastCall['post']['canonical'], 'self canonical');
        self::assertSame('42', $out['external_id']);
        self::assertSame('ok', $s->get(7, 'devto')['status']);
        self::assertSame('42', $s->get(7, 'devto')['external_id']);
    }

    public function test_it_updates_when_an_external_id_is_stored(): void
    {
        $t = new FakeTarget();
        $s = new FakeStore();
        $s->record(7, 'devto', '42', 'https://dev.to/dan/hello-42', 'ok', '2026-01-01 00:00:00');

        $this->make($t, $s)->syndicate('hello', 'devto', '2026-01-02 09:00:00');
        self::assertNotNull($t->lastCall);
        self::assertSame('42', $t->lastCall['externalId'], 'a stored id makes it an update');
    }

    public function test_a_declared_canonical_url_wins_over_self(): void
    {
        $t = new FakeTarget();
        $this->make($t, new FakeStore(), ['canonical_url' => 'https://dev.to/original'])->syndicate('hello', 'devto', 'now');
        self::assertNotNull($t->lastCall);
        self::assertSame('https://dev.to/original', $t->lastCall['post']['canonical']);
    }

    public function test_a_malformed_declared_canonical_falls_back_to_self(): void
    {
        $t = new FakeTarget();
        $this->make($t, new FakeStore(), ['canonical_url' => 'javascript:alert(1)'])->syndicate('hello', 'devto', 'now');
        self::assertNotNull($t->lastCall);
        self::assertSame('https://danmat.dev/blog/hello', $t->lastCall['post']['canonical']);
    }

    public function test_an_unknown_target_is_rejected(): void
    {
        $this->expectException(SyndicationError::class);
        $this->make(new FakeTarget(), new FakeStore())->syndicate('hello', 'nope', 'now');
    }

    public function test_a_missing_post_is_rejected(): void
    {
        $this->expectException(SyndicationError::class);
        $this->make(new FakeTarget(), new FakeStore())->syndicate('does-not-exist', 'devto', 'now');
    }

    public function test_a_target_failure_records_error_and_rethrows(): void
    {
        $t = new FakeTarget();
        $t->throw = true;
        $s = new FakeStore();
        try {
            $this->make($t, $s)->syndicate('hello', 'devto', 'now');
            self::fail('expected a SyndicationError');
        } catch (SyndicationError) {
            self::assertSame('error', $s->get(7, 'devto')['status']);
        }
    }

    public function test_status_for_lists_records(): void
    {
        $s = new FakeStore();
        $s->record(7, 'devto', '42', 'https://dev.to/dan/hello-42', 'ok', '2026-01-01 00:00:00');
        $status = $this->make(new FakeTarget(), $s)->statusFor('hello');
        self::assertNotNull($status);
        self::assertSame(7, $status['entry_id']);
        self::assertCount(1, $status['records']);
        self::assertSame('devto', $status['records'][0]['target']);
    }
}

<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

/**
 * Persistence of the entry-to-external-copy mapping, as the {@see Syndicator} sees
 * it. An interface so the service is unit-testable with an in-memory fake;
 * {@see SyndicationRepository} is the database implementation.
 */
interface SyndicationStore
{
    /** @return array{external_id:?string,external_url:?string,status:string}|null */
    public function get(int $entryId, string $target): ?array;

    public function record(int $entryId, string $target, ?string $externalId, string $externalUrl, string $status, string $now): void;

    /** @return list<array{target:string,external_id:?string,external_url:?string,status:string,synced_at:string}> */
    public function forEntry(int $entryId): array;
}

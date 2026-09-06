<?php

declare(strict_types=1);

namespace NimbusCMS\Blog;

use RuntimeException;

/** A syndication failure (not configured, a non-2xx from the platform, a transport error). */
final class SyndicationError extends RuntimeException
{
}

/**
 * One external platform a post can be pushed to (Dev.to, Hashnode, ...). Each target
 * is an adapter, so adding a platform is a new implementation, not a change to the
 * {@see Syndicator}. A target owns how it talks to its API and where the canonical
 * goes; it never touches the plugin's storage or the site's content.
 */
interface SyndicationTarget
{
    /** A stable id used in URLs, storage rows, and the MCP tool argument (e.g. "devto"). */
    public function id(): string;

    /** A human label for the admin (e.g. "Dev.to"). */
    public function label(): string;

    /** Whether the operator has configured this target's credentials. */
    public function isConfigured(): bool;

    /**
     * Create the post on the platform, or update it when $externalId is given.
     *
     * @param array{title:string,body:string,tags:list<string>,canonical:string} $post
     * @return array{external_id:string,external_url:string}
     */
    public function push(array $post, ?string $externalId): array;
}

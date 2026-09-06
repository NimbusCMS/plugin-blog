# nimbuscms/blog — design: syndication (cross-post to dev platforms)

**Status:** design (pre-build). A feature of the **blog plugin** (it depends on the
blog: the post model, the slug, the canonical). Adds a way to push a published post
out to external dev blogging platforms, from **both** the admin UI and MCP, with the
external copy's canonical pointed back at danmat.dev automatically. First consumer:
danmat.dev.

## Goal

From a published post, syndicate it to an external platform in one deliberate act,
and never have to hand-set the canonical again. The whole point of the blog's
`canonical_url` field was that danmat.dev is the original and the copies point back;
this closes that loop instead of doing it by hand.

## Targets, and the honest state of each

Cross-posting the **content** (with a canonical) is only clean where the platform
has a real write API that supports a canonical URL:

| Target | Content cross-post | Mechanism | Notes |
|--------|--------------------|-----------|-------|
| **Dev.to** | ✅ | Forem REST: `POST /api/articles`, header `api-key`; `PUT /api/articles/{id}` to update | `canonical_url`, `body_markdown`, `tags`, `series`, `published`. Free. |
| **Hashnode** | ✅ | GraphQL `gql.hashnode.com`: `publishPost` / `updatePost` | canonical is `originalArticleURL`; needs a personal token + publication id. **API writes require Hashnode Pro** (documented for the operator). |
| **Medium** | ❌ | — | Medium stopped issuing API integration tokens in Jan 2025. Excluded; documented so nobody wonders why. |
| **Hacker News, Reddit, daily.dev** | ❌ (not blogs) | link submission | Aggregators, not cross-post targets. Handled as a **share link** (see the aggregators section, pending research), never an auto-post. |

**Extensible by design:** each target is an adapter behind one interface, so adding
Ghost, write.as, or another platform later is a new adapter, not a redesign.

## Architecture: one action, two front doors

The core principle: a person in the admin and an agent over MCP both drive the same
action through the same service and the same capability.

- **`Syndicator`** (service) does the real work: given a post slug and a target, it
  reads the post (via the plugin's published-only content reader), computes the
  canonical, calls the target adapter, and records the result. One method:
  `syndicate(slug, target): SyndicationResult`.
- **`SyndicationTarget`** (interface): `id()`, `label()`, `push(PostPayload $post,
  string $canonical, ?string $externalId): {externalId, externalUrl}`. Implemented by
  `DevToTarget` and `HashnodeTarget`. Adding a platform = a new implementation.
- **Admin UI**: a capability-gated "Syndication" admin page (ADR 0020) lists published
  posts, each with per-target buttons (Post to Dev.to / Update on Dev.to, same for
  Hashnode), the resulting external links, and the aggregator share links. The button
  is a CSRF-protected admin action that calls `Syndicator`.
- **MCP**: a `syndicate_post {slug, target}` tool (ADR 0016) gated by the **same**
  capability, calling the **same** `Syndicator`. Plus a read-only `syndication_status
  {slug}` tool. Non-enumerating, audited.

Neither surface reimplements anything. Same authority, same audit, same idempotency.

## The capability: `nimbuscms.blog:syndicate`

Until now the blog plugin declared no capability (everything it did was public read).
Syndication is a real external publish, so it gets its own fine-grained action
(possible because of ADR 0030): the plugin declares `Blog` with the action
`syndicate`, i.e. `nimbuscms.blog:syndicate`. It is wildcard-immune (ADR 0015): a
content `*:write` grant can never reach it.

- In the **UI**, only a role granted `blog:syndicate` sees the buttons.
- Over **MCP**, only a token explicitly scoped `blog:syndicate` can call the tool. A
  normal content-write token cannot syndicate. The operator granting that scope **is**
  the authorization for external publishing, so an agent can never quietly post to
  Dev.to unless it was handed that exact scope.

This is how "MCP-first-class" coexists with "no surprise external posts."

## Secrets stay server-side

Each target's credential is an **operator setting** (env, e.g. `DEVTO_API_KEY`,
`HASHNODE_TOKEN`, `HASHNODE_PUBLICATION_ID`), read by the adapter inside the server.
Never in content, never in the DB rows below, never over MCP, never in the audit log.
The MCP tool call carries only a slug and a target name; the server holds the keys and
makes the outbound call. A target with no configured credential is shown as
unavailable in the UI and returns a clean "not configured" over MCP.

## Idempotency: update, never duplicate (the plugin's first table)

Cross-posting the same post twice must update the existing external article, not
create a second one. So the plugin gains its **first table** (ADR 0005 plugin storage;
it has been table-less until now), `blog_syndication`:

```
entry_id      the blog entry's core id
target        'devto' | 'hashnode'
external_id   the id the platform returned
external_url  the live URL on that platform
status        'ok' | 'error'
synced_at     datetime
(primary key: entry_id + target)
```

On syndicate: look up `(entry_id, target)`; if a row exists, the adapter updates by
`external_id`; else it creates and stores the new id. This table holds no post
content and no secrets, only the external mapping. It touches no core data.

## Canonical, computed server-side

The external canonical is always the post's true original:

- if the post's `canonical_url` field is set (this post is itself a copy of something
  elsewhere), use that;
- otherwise use the self URL, `<appUrl>/blog/<slug>`.

The operator never types a canonical into the external platform again.

## Aggregators (Hacker News, Reddit): share, do not auto-post

Researched against current (2025/2026) docs. Both are link-submission communities,
not blogs, and the honest answer for both is a **prefilled share link the human
clicks**, not an auto-post. Zero stored secrets, rules-safe, human in the loop.

- **Hacker News.** There is **no submit API at all** (the official Firebase API is
  read-only), so an auto-post is not even possible. The Syndication page shows a
  "Submit to HN" link that opens `https://news.ycombinator.com/submitlink?u=<url>&t=<title>`
  in a new tab; the logged-in human reviews and submits. Gotcha to handle in the copy:
  if the URL was already posted, HN takes the user to the **existing thread** rather
  than creating a duplicate, which is expected, not an error.
- **Reddit.** A `submit` API does exist (OAuth, `submit` scope), but auto-posting is a
  bad default: self-service app registration closed in late 2025 (manual approval,
  slow and opaque), it needs OAuth secret storage plus a **forced target subreddit**,
  and automated self-links are the textbook trigger for spam filtering and invisible
  shadowbans, on top of the 90/10 self-promotion norm and per-subreddit rules. So the
  default is a "Share to Reddit" link, `https://www.reddit.com/submit?url=<url>&title=<title>`,
  where the human picks the subreddit and submits. API auto-submit is out of scope for
  v1; it would only ever be an advanced, opt-in, single-user, non-commercial mode with
  the operator accepting all of that risk.

Both prefilled-submit URLs still work in 2025/2026. Neither aggregator stores a
credential or appears in `blog_syndication` (there is no external id to track for a
link the human submits).

## Security review (Attacker / Defender / QA)

- **Outbound target, SSRF?** The endpoints are fixed, known hosts (Dev.to, Hashnode),
  never a user-supplied URL, so there is no server-side request forgery surface. The
  only user input is a slug (resolved against the blog collection) and a target name
  (validated against the adapter registry).
- **Secret exposure?** Credentials live in operator env, read server-side, never
  logged, never returned by a tool, never stored in `blog_syndication`. A misconfigured
  target fails closed as "not configured."
- **Escalation?** `blog:syndicate` is wildcard-immune (ADR 0015) and fine-grained (ADR
  0030); only an explicit grant or admin reaches it, on both surfaces. Over MCP the
  tool is non-enumerating and every push is audited (who, which post, which target,
  resulting URL, not the secret).
- **Spam / double-post?** The `(entry_id, target)` key makes a repeat call an update,
  not a duplicate, so an agent re-running "syndicate my latest" cannot flood a platform.
- **Publishing on the user's behalf.** The admin button is the human's deliberate act;
  the MCP tool requires an explicitly syndicate-scoped token. Neither surface publishes
  externally on a generic content grant.
- **QA:** unit tests for the canonical computation (self vs declared), the adapter
  request shape (Dev.to body, Hashnode mutation) with a faked HTTP client, the
  create-vs-update decision from the stored id, and the capability gate on both the
  admin action and the MCP tool (a non-syndicate role/token is refused). Security-green
  target: no Critical/High.

## Platform review (three hats)

- **Product:** syndication is wanted by any dev-blog author; it belongs with the blog
  plugin because it is blog-dependent (post model, slug, canonical). Not core, not a
  separate plugin (it has no life without the blog).
- **Architect:** classification is an **official-plugin feature**. Reuses ADR 0005
  (storage), 0015/0030 (capability), 0016 (MCP), 0020 (admin pages). No core change.
  The adapter interface keeps the target list open without touching the service.
- **Engineer:** one shared service behind two surfaces; secrets server-side; idempotent
  by external id; outbound calls to fixed hosts; testable with a faked HTTP client.
  Mobile: the Syndication admin page verified at 375px.

## Build slices

1. `Syndicator` + `SyndicationTarget` interface + `DevToTarget` (create/update) +
   `blog_syndication` table + the `blog:syndicate` capability + canonical logic.
   Unit-tested with a faked HTTP client.
2. The Syndication admin page (per-post per-target buttons, status, links) + the
   `syndicate_post` / `syndication_status` MCP tools, both gated `blog:syndicate`.
3. `HashnodeTarget` (GraphQL) + the operator-config docs (which env vars, the Hashnode
   Pro caveat).
4. The aggregator share links (HN, Reddit) per the research pass.

## Definition of done

A published post can be pushed to Dev.to and Hashnode from the admin and from MCP,
gated on `blog:syndicate`, with the canonical auto-set and a repeat call updating not
duplicating; credentials are server-side only; the Syndication page shows status +
links + aggregator share links; plugin CI green (PHPStan L6 + cs-fixer + tests); the
page verified at 375px.

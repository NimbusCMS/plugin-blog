# nimbuscms/blog

An official [NimbusCMS](https://github.com/NimbusCMS/nimbus) plugin that turns a
plain content collection into a blog — the cross-cutting pieces a bare collection
can't do:

- **Per-post SEO `<head>`** — canonical (honouring a post's `canonical_url` for
  cross-posts, else self), Open Graph `article`, Twitter card, and JSON-LD `Article`.
- **RSS feed** of the latest published posts.
- **Tag archives** at `/tag/{tag}`.
- **Syndication** — cross-post a published post to Dev.to and Hashnode from the admin
  or over MCP, canonical set back to your site, re-posting updates rather than
  duplicating. See [Syndication](#syndication).

The posts themselves are ordinary core content (a `blog` collection, authored over
the normal content tools/MCP) and **your theme renders** the list and detail pages
via its own `collection-blog` / `entry-blog` templates. The public reading surfaces
touch no core tables and need no capability; **syndication** is the one guarded
action — it owns a small `blog_syndication` table and the wildcard-immune
`nimbuscms.blog:syndicate` capability.

## How it works

A **browsable `blog` collection** is served by core at `/blog` and `/blog/{slug}`
(clean URLs, correct sitemap, full entry context for the SEO head). This plugin
registers a head contributor over that collection, plus the feed route and tag
pages. See [`docs/DESIGN.md`](docs/DESIGN.md) for the architecture and the security
review.

## The `blog` collection

Create it as normal content (e.g. over MCP) with these fields:

| field | type | purpose |
|-------|------|---------|
| `summary` | textarea | list blurb + meta/OG/JSON-LD description |
| `body` | textarea (markdown) | the post; the theme may allow inline `<svg>` diagrams |
| `cover` | media | OG/Twitter image (same-origin) |
| `tags` | text | comma-separated; each becomes a `/tag/{tag}` archive |
| `canonical_url` | text | set only for a cross-post that originated elsewhere; empty = self-canonical |

(`title`, `slug`, `published_at` are the reserved attributes.)

## Syndication

Cross-post a published post to an external dev platform. The same action drives two
front doors — a capability-gated **Syndication** admin page (a Post / Update button
per post per target) and an **MCP** tool (`blog_syndicate_post`) — through one
service, one capability, and one audit trail. The canonical is always set back to
this site (or the post's own `canonical_url` if it already originated elsewhere), and
re-posting **updates** the existing external copy rather than creating a second one
(a `(post, target)` row remembers the external id).

Syndication is off until an operator grants the **`nimbuscms.blog:syndicate`**
capability. It is wildcard-immune: a content `*:write` role or token can never reach
it — the explicit grant *is* the authorization to publish externally.

### Targets and their credentials

Credentials are **operator env** only — read server-side, never in content, the DB,
the audit log, or over MCP. A target with a missing credential shows as *Not
configured* in the admin and returns a clean "not configured" over MCP.

| Target | Env | Notes |
|--------|-----|-------|
| **Dev.to** | `DEVTO_API_KEY` | A personal API key (Settings → Extensions → DEV API Keys). |
| **Hashnode** | `HASHNODE_TOKEN`, `HASHNODE_PUBLICATION_ID` | A personal access token (gql.hashnode.com) **and** the publication id to post into. **Writing over the Hashnode API requires a Hashnode Pro account** — without Pro the call fails with a clear "requires Hashnode Pro" error. |

Set the env for whichever targets you want, grant a role or token
`nimbuscms.blog:syndicate`, and the target appears on the Syndication page.
Hacker News and Reddit are aggregators, not blogs — they arrive in a later slice as
prefilled **share links** (a human clicks), never an auto-post.

## Install

```bash
composer require nimbuscms/blog
```

Enable it in `config/plugins.php`, add a **Writing → `/blog`** nav item, and give
your theme `collection-blog` / `entry-blog` templates. Discovered via
`installed.json` (`type: nimbuscms-plugin`).

## Develop

```bash
composer install
composer check   # phpstan (level 6) + phpunit
composer format  # php-cs-fixer
```

MIT.

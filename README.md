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
| `cover` | media *or* text | OG/Twitter/JSON-LD image — a `media` reference (resolved to its URL) **or** a `text` field holding an image URL (absolute `https://…` or site-relative `/…`). |
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

### Share links (Hacker News, Reddit)

Hacker News and Reddit are link-submission communities, not blogs, so the plugin
does **not** auto-post to them — the Syndication page shows a **Share** column with a
prefilled link per post that opens the community's own submit form (URL + title
filled in) in a new tab, for you to review and submit. No credential, no stored
state, no outbound call — just a shortcut past copy-pasting. (Hacker News has no
submit API at all; if a link was already submitted it opens the existing thread
rather than duplicating.) The same links are returned by the `blog_syndication_status`
MCP tool, so an agent can hand them to a human. API auto-submit for Reddit is a
separate, opt-in conversation (see `docs/DESIGN-syndication.md`).

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

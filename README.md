# nimbuscms/blog

An official [NimbusCMS](https://github.com/NimbusCMS/nimbus) plugin that turns a
plain content collection into a blog — the cross-cutting pieces a bare collection
can't do:

- **Per-post SEO `<head>`** — canonical (honouring a post's `canonical_url` for
  cross-posts, else self), Open Graph `article`, Twitter card, and JSON-LD `Article`.
- **RSS feed** of the latest published posts.
- **Tag archives** at `/tag/{tag}`.

The posts themselves are ordinary core content (a `blog` collection, authored over
the normal content tools/MCP) and **your theme renders** the list and detail pages
via its own `collection-blog` / `entry-blog` templates. The plugin touches no core
tables and declares no capability — every surface is public read.

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

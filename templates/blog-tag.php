<?php

/**
 * Default tag-archive template (ADR 0023) — a plain, safe fallback rendered inside
 * the active theme. A theme overrides it (e.g. `blog-tag.php`) to match its own
 * look; this generic version just lists the tagged posts, escaping everything.
 *
 * @var string   $tag   the tag being shown
 * @var string   $base  the blog URL base, e.g. "/blog"
 * @var list<array{title:string,slug:string,summary:string,published_at:string}> $posts
 * @var callable $e     escape a value for output
 */
?>
<section class="blog-tag">
  <header>
    <p class="eyebrow">Tag</p>
    <h1><?= $e($tag) ?></h1>
  </header>
  <ul class="blog-tag-list">
    <?php foreach ($posts as $post): ?>
      <li>
        <a href="<?= $e($base . '/' . $post['slug']) ?>"><?= $e($post['title']) ?></a>
        <?php if (($post['summary'] ?? '') !== ''): ?>
          <p class="blog-tag-summary"><?= $e($post['summary']) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

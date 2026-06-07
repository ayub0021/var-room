<?php
// ============================================================
//  VAR ROOM — Homepage
//  index.php
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

// ── Query params ───────────────────────────────────────────
$sort        = in_array($_GET['sort'] ?? '', ['trending','latest']) ? $_GET['sort'] : 'trending';
$competition = trim($_GET['competition'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * REVIEWS_PER_PAGE;

// ── Build WHERE clause ─────────────────────────────────────
$where  = "WHERE mr.status = 'approved'";
$params = [];

if ($competition) {
    $where   .= ' AND mr.competition = ?';
    $params[] = $competition;
}

// ── Order ──────────────────────────────────────────────────
$order = $sort === 'trending'
    ? 'ORDER BY total_votes DESC, mr.created_at DESC'
    : 'ORDER BY mr.created_at DESC';

// ── Main query ─────────────────────────────────────────────
$sql = "
    SELECT
        mr.*,
        u.username                           AS author,
        u.avatar                             AS author_avatar,
        COUNT(DISTINCT v.id)                 AS total_votes,
        COALESCE(SUM(v.vote_type='correct'),0) AS correct_votes,
        COALESCE(SUM(v.vote_type='wrong'),0)   AS wrong_votes,
        COUNT(DISTINCT c.id)                 AS comment_count
    FROM match_reviews mr
    JOIN  users    u ON mr.user_id = u.id
    LEFT JOIN votes    v ON mr.id  = v.review_id
    LEFT JOIN comments c ON mr.id  = c.review_id AND c.is_deleted = 0
    $where
    GROUP BY mr.id
    $order
    LIMIT ? OFFSET ?
";

$params[] = REVIEWS_PER_PAGE;
$params[] = $offset;
$reviews  = db_fetch_all($sql, $params);

// ── Total count for pagination ─────────────────────────────
$count_params = $competition ? [$competition] : [];
$total_rows   = (int) db_fetch_one(
    "SELECT COUNT(*) AS n FROM match_reviews mr $where",
    $count_params
)['n'];
$total_pages  = (int) ceil($total_rows / REVIEWS_PER_PAGE);

// ── Trending ticker (top 8 approved by votes) ──────────────
$ticker_items = db_fetch_all(
    "SELECT mr.title, mr.match_name, COUNT(v.id) AS total_votes
     FROM match_reviews mr
     LEFT JOIN votes v ON mr.id = v.review_id
     WHERE mr.status = 'approved'
     GROUP BY mr.id
     ORDER BY total_votes DESC
     LIMIT 8"
);

// ── Distinct competitions for filter bar ───────────────────
$competitions_used = db_fetch_all(
    "SELECT DISTINCT competition FROM match_reviews
     WHERE status = 'approved'
     ORDER BY competition ASC"
);

// ── Site stats ─────────────────────────────────────────────
$site_stats = db_fetch_one(
    "SELECT
        (SELECT COUNT(*) FROM match_reviews WHERE status='approved') AS total_reviews,
        (SELECT COUNT(*) FROM votes)   AS total_votes,
        (SELECT COUNT(*) FROM users)   AS total_users,
        (SELECT COUNT(*) FROM match_reviews WHERE status='pending') AS pending"
);

// ── Current user's votes (to highlight voted cards) ───────
$user_votes = [];
if (is_logged_in()) {
    $uid = current_user_id();
    $rows = db_fetch_all(
        "SELECT review_id, vote_type FROM votes WHERE user_id = ?", [$uid]
    );
    foreach ($rows as $r) {
        $user_votes[$r['review_id']] = $r['vote_type'];
    }
}

$page_title  = 'VAR Room — Community Verdict';
$active_page = $sort === 'trending' ? 'trending' : 'latest';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Ticker ───────────────────────────────────────────── -->
<?php if (!empty($ticker_items)): ?>
<div class="ticker" aria-label="Trending controversies">
  <div class="ticker__label">🔥 TRENDING</div>
  <div class="ticker__track">
    <div class="ticker__items" id="ticker-items">
      <?php foreach ($ticker_items as $t): ?>
        <span class="ticker__item"><?= e($t['title']) ?> — <?= e($t['match_name']) ?></span>
      <?php endforeach; ?>
      <?php /* Duplicated by JS for seamless loop */ ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Hero ─────────────────────────────────────────────── -->
<section class="hero" aria-label="Site introduction">
  <div class="container hero__inner">

    <div class="hero__eyebrow">
      <span>⚽</span> Community Verdict Platform
    </div>

    <h1 class="hero__title">
      The <em>VAR</em><br>Room
    </h1>

    <p class="hero__sub">
      Vote on football's most controversial referee and VAR decisions.
      Was the call right or wrong? Your verdict matters.
    </p>

    <div style="display:flex;gap:var(--space-md);flex-wrap:wrap;margin-bottom:var(--space-xl);">
      <?php if (is_logged_in()): ?>
        <a href="<?= SITE_URL ?>/upload.php" class="btn btn--primary">
          + Submit Controversy
        </a>
        <a href="#controversies" class="btn btn--ghost">Browse All</a>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn--primary">
          Join Free & Vote
        </a>
        <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn--ghost">Sign In</a>
      <?php endif; ?>
    </div>

    <!-- Stats strip -->
    <div class="hero__stats">
      <div>
        <div class="hero__stat-value"><?= number_format((int)$site_stats['total_reviews']) ?></div>
        <div class="hero__stat-label">Controversies</div>
      </div>
      <div class="hero__stat-sep"></div>
      <div>
        <div class="hero__stat-value"><?= number_format((int)$site_stats['total_votes']) ?></div>
        <div class="hero__stat-label">Votes Cast</div>
      </div>
      <div class="hero__stat-sep"></div>
      <div>
        <div class="hero__stat-value"><?= number_format((int)$site_stats['total_users']) ?></div>
        <div class="hero__stat-label">Members</div>
      </div>
    </div>
  </div>
</section>

<!-- ── Filter + Sort bar ─────────────────────────────────── -->
<div class="container" style="padding-top:var(--space-lg);" id="controversies">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-md);margin-bottom:var(--space-md);">

    <!-- Competition filter chips -->
    <div class="filter-bar" style="padding:0;flex:1;" role="group" aria-label="Filter by competition">
      <button class="filter-chip <?= !$competition ? 'active' : '' ?>" data-filter="">All</button>
      <?php foreach ($competitions_used as $c): ?>
        <button
          class="filter-chip <?= $competition === $c['competition'] ? 'active' : '' ?>"
          data-filter="<?= e($c['competition']) ?>">
          <?= e($c['competition']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Sort toggle -->
    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
      <a href="?sort=trending<?= $competition ? '&competition='.urlencode($competition) : '' ?>"
         class="btn btn--sm <?= $sort==='trending' ? 'btn--primary' : 'btn--ghost' ?>">
        🔥 Trending
      </a>
      <a href="?sort=latest<?= $competition ? '&competition='.urlencode($competition) : '' ?>"
         class="btn btn--sm <?= $sort==='latest' ? 'btn--primary' : 'btn--ghost' ?>">
        🕐 Latest
      </a>
    </div>
  </div>

  <!-- Section header -->
  <div class="section-header">
    <div>
      <div class="section-header__eyebrow">
        <?= $sort === 'trending' ? 'Most Voted This Week' : 'Recently Submitted' ?>
      </div>
      <h2 class="section-header__title">
        <?= $competition ? e($competition) : ($sort === 'trending' ? 'Trending' : 'Latest') ?>
        <?= $competition ? ' Controversies' : ' Debates' ?>
      </h2>
    </div>
    <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--text-muted);">
      <?= number_format($total_rows) ?> total
    </div>
  </div>

  <!-- ── Card Grid ──────────────────────────────────────── -->
  <?php if (empty($reviews)): ?>
    <div class="empty-state">
      <span class="empty-state__icon">⚽</span>
      <div class="empty-state__title">No controversies yet</div>
      <p>
        <?php if ($competition): ?>
          No approved submissions in this competition yet.
          <a href="?" style="color:var(--neon-green);">Clear filter</a>
        <?php elseif (is_logged_in()): ?>
          Be the first to <a href="<?= SITE_URL ?>/upload.php" style="color:var(--neon-green);">submit a controversy</a>!
        <?php else: ?>
          <a href="<?= SITE_URL ?>/auth/register.php" style="color:var(--neon-green);">Join</a> to submit the first one.
        <?php endif; ?>
      </p>
    </div>

  <?php else: ?>
    <div class="grid-3">
      <?php foreach ($reviews as $i => $r):
        $pct   = calc_vote_pct((int)$r['correct_votes'], (int)$r['wrong_votes']);
        $voted = $user_votes[$r['id']] ?? null;
      ?>
      <article class="controversy-card fade-in stagger-<?= min($i + 1, 6) ?>"
               onclick="window.location='<?= SITE_URL ?>/review.php?id=<?= (int)$r['id'] ?>'"
               role="link"
               tabindex="0"
               aria-label="<?= e($r['title']) ?>"
               onkeydown="if(event.key==='Enter') window.location='<?= SITE_URL ?>/review.php?id=<?= (int)$r['id'] ?>'">

        <!-- Image -->
        <div class="controversy-card__image">
          <img
            src="<?= UPLOAD_URL . e($r['image_path']) ?>"
            alt="<?= e($r['title']) ?>"
            loading="lazy"
            onerror="this.style.display='none'"
          >
          <div class="controversy-card__gradient"></div>

          <span class="controversy-card__badge controversy-card__badge--competition">
            <?= e($r['competition']) ?>
          </span>

          <span class="controversy-card__minute">
            <?= (int)$r['incident_min'] ?>'
          </span>

          <?php if ($voted): ?>
            <span style="position:absolute;bottom:10px;right:10px;font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 8px;border-radius:var(--radius-pill);background:<?= $voted==='correct' ? 'rgba(0,232,122,.9)' : 'rgba(255,45,85,.9)' ?>;color:#fff;">
              ✓ Voted
            </span>
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="controversy-card__body">
          <div class="controversy-card__match"><?= e($r['match_name']) ?></div>
          <h3 class="controversy-card__title"><?= e($r['title']) ?></h3>
          <p class="controversy-card__desc"><?= e(truncate($r['description'], 110)) ?></p>

          <!-- Vote bar -->
          <div class="vote-bar" data-vote-bar="<?= (int)$r['id'] ?>">
            <div class="vote-bar__track">
              <div
                class="vote-bar__fill"
                data-width="<?= $pct['correct_pct'] ?>"
                style="width:0%">
              </div>
            </div>
            <div class="vote-bar__labels">
              <span class="vote-bar__label vote-bar__label--correct">
                ✓ Correct
                <span class="vote-bar__pct"><?= $pct['correct_pct'] ?>%</span>
              </span>
              <span style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);">
                <span data-vote-total="<?= (int)$r['id'] ?>"><?= number_format($pct['total']) ?></span> votes
              </span>
              <span class="vote-bar__label vote-bar__label--wrong">
                <span class="vote-bar__pct"><?= $pct['wrong_pct'] ?>%</span>
                Wrong ✗
              </span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="controversy-card__footer">
          <div class="controversy-card__meta">
            <span title="Views">👁 <?= number_format((int)$r['views']) ?></span>
            <span title="Comments">💬 <span data-comment-count="<?= (int)$r['id'] ?>"><?= (int)$r['comment_count'] ?></span></span>
            <span title="Submitted by">by <?= e($r['author']) ?></span>
          </div>
          <span style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
            <?= time_ago($r['created_at']) ?>
          </span>
        </div>

      </article>
      <?php endforeach; ?>
    </div>

    <!-- ── Pagination ────────────────────────────────────── -->
    <?php if ($total_pages > 1): ?>
    <nav class="pagination" aria-label="Page navigation">
      <?php if ($page > 1): ?>
        <a href="?sort=<?= $sort ?>&page=<?= $page-1 ?><?= $competition ? '&competition='.urlencode($competition) : '' ?>"
           class="pagination__btn" aria-label="Previous page">←</a>
      <?php endif; ?>

      <?php
      $range = range(max(1, $page - 2), min($total_pages, $page + 2));
      foreach ($range as $p):
      ?>
        <a href="?sort=<?= $sort ?>&page=<?= $p ?><?= $competition ? '&competition='.urlencode($competition) : '' ?>"
           class="pagination__btn <?= $p === $page ? 'active' : '' ?>"
           <?= $p === $page ? 'aria-current="page"' : '' ?>>
          <?= $p ?>
        </a>
      <?php endforeach; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?sort=<?= $sort ?>&page=<?= $page+1 ?><?= $competition ? '&competition='.urlencode($competition) : '' ?>"
           class="pagination__btn" aria-label="Next page">→</a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- ── CTA Banner (shown to guests) ─────────────────────── -->
<?php if (!is_logged_in()): ?>
<section style="margin:var(--space-2xl) 0 0;background:var(--bg-surface);border-top:1px solid var(--border-subtle);border-bottom:1px solid var(--border-subtle);padding:var(--space-xl) 0;">
  <div class="container" style="text-align:center;">
    <div class="section-header__eyebrow" style="justify-content:center;">Make Your Voice Heard</div>
    <h2 style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:var(--space-md);">Join 
      <?= number_format((int)$site_stats['total_users']) ?> Fans Debating
    </h2>
    <p style="margin-bottom:var(--space-lg);max-width:480px;margin-left:auto;margin-right:auto;">
      Create a free account to vote on decisions, submit controversies, and join the debate.
    </p>
    <div style="display:flex;gap:var(--space-md);justify-content:center;flex-wrap:wrap;">
      <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn--primary" style="padding:14px 32px;font-size:1rem;">
        Join Free
      </a>
      <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn--ghost" style="padding:14px 32px;font-size:1rem;">
        Sign In
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// ============================================================
//  VAR ROOM — Single Review / Controversy Detail Page
//  review.php?id=123
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

// ── Validate ID ────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    set_flash('error', 'Invalid review ID.');
    redirect(SITE_URL . '/index.php');
}

// ── Fetch review with author + vote totals ─────────────────
$review = db_fetch_one(
    "SELECT
        mr.*,
        u.username                             AS author,
        u.avatar                               AS author_avatar,
        u.created_at                           AS author_joined,
        COUNT(DISTINCT v.id)                   AS total_votes,
        COALESCE(SUM(v.vote_type='correct'),0) AS correct_votes,
        COALESCE(SUM(v.vote_type='wrong'),0)   AS wrong_votes,
        COUNT(DISTINCT c.id)                   AS comment_count
     FROM match_reviews mr
     JOIN  users    u ON mr.user_id = u.id
     LEFT JOIN votes    v ON mr.id  = v.review_id
     LEFT JOIN comments c ON mr.id  = c.review_id AND c.is_deleted = 0
     WHERE mr.id = ? AND mr.status = 'approved'
     GROUP BY mr.id",
    [$id]
);

if (!$review) {
    set_flash('error', 'Controversy not found or not yet approved.');
    redirect(SITE_URL . '/index.php');
}

// ── Increment view count (simple, no dedup) ────────────────
db_execute('UPDATE match_reviews SET views = views + 1 WHERE id = ?', [$id]);

// ── Current user's vote ────────────────────────────────────
$user_vote = null;
if (is_logged_in()) {
    $v = db_fetch_one(
        'SELECT vote_type FROM votes WHERE review_id = ? AND user_id = ?',
        [$id, current_user_id()]
    );
    $user_vote = $v ? $v['vote_type'] : null;
}

// ── Percentages ────────────────────────────────────────────
$pct = calc_vote_pct((int)$review['correct_votes'], (int)$review['wrong_votes']);

// ── Comments (most recent first, paginated) ────────────────
$comment_page   = max(1, (int)($_GET['cpage'] ?? 1));
$comment_offset = ($comment_page - 1) * COMMENTS_PER_PAGE;

$comments = db_fetch_all(
    "SELECT c.*, u.username, u.avatar
     FROM comments c
     JOIN users u ON c.user_id = u.id
     WHERE c.review_id = ? AND c.is_deleted = 0
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?",
    [$id, COMMENTS_PER_PAGE, $comment_offset]
);

$total_comments = (int) db_fetch_one(
    'SELECT COUNT(*) AS n FROM comments WHERE review_id = ? AND is_deleted = 0',
    [$id]
)['n'];

$comment_pages = (int) ceil($total_comments / COMMENTS_PER_PAGE);

// ── Related controversies (same competition, different ID) ─
$related = db_fetch_all(
    "SELECT mr.id, mr.title, mr.match_name, mr.image_path, mr.incident_min,
            COUNT(v.id) AS total_votes
     FROM match_reviews mr
     LEFT JOIN votes v ON mr.id = v.review_id
     WHERE mr.competition = ? AND mr.id != ? AND mr.status = 'approved'
     GROUP BY mr.id
     ORDER BY total_votes DESC
     LIMIT 3",
    [$review['competition'], $id]
);

$page_title  = e($review['title']);
$active_page = '';
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:var(--space-xl) 0 var(--space-2xl);">
<div class="container">

  <!-- ── Breadcrumb ──────────────────────────────────────── -->
  <nav aria-label="Breadcrumb" style="margin-bottom:var(--space-lg);">
    <div style="display:flex;align-items:center;gap:8px;font-family:var(--font-condensed);font-size:.82rem;color:var(--text-muted);">
      <a href="<?= SITE_URL ?>/index.php" style="color:var(--text-muted);transition:color .15s;" onmouseover="this.style.color='var(--neon-green)'" onmouseout="this.style.color='var(--text-muted)'">Home</a>
      <span>›</span>
      <a href="<?= SITE_URL ?>/index.php?competition=<?= urlencode($review['competition']) ?>"
         style="color:var(--text-muted);transition:color .15s;"
         onmouseover="this.style.color='var(--neon-green)'"
         onmouseout="this.style.color='var(--text-muted)'"><?= e($review['competition']) ?></a>
      <span>›</span>
      <span style="color:var(--text-secondary);"><?= e(truncate($review['title'], 50)) ?></span>
    </div>
  </nav>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:var(--space-xl);align-items:start;">

    <!-- ══ LEFT COLUMN ══════════════════════════════════════ -->
    <div>

      <!-- Header -->
      <header style="margin-bottom:var(--space-xl);">

        <!-- Meta chips -->
        <div style="display:flex;align-items:center;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-md);">
          <span class="badge badge--amber"><?= e($review['competition']) ?></span>
          <span class="badge badge--gray" title="Minute of incident">
            ⏱ <?= (int)$review['incident_min'] ?>'
          </span>
          <span class="badge badge--gray">
            👁 <?= number_format((int)$review['views'] + 1) ?> views
          </span>
          <span class="badge badge--gray">
            💬 <span id="badge-comment-count"><?= $total_comments ?></span> comments
          </span>
          <?php if ($user_vote): ?>
            <span class="badge <?= $user_vote === 'correct' ? 'badge--green' : 'badge--red' ?>">
              <?= $user_vote === 'correct' ? '✓ You voted Correct' : '✗ You voted Wrong' ?>
            </span>
          <?php endif; ?>
        </div>

        <h1 style="font-size:clamp(1.8rem,4vw,3rem);letter-spacing:.03em;line-height:1.05;margin-bottom:var(--space-md);">
          <?= e($review['title']) ?>
        </h1>

        <!-- Match info strip -->
        <div style="display:flex;align-items:center;gap:var(--space-lg);padding:var(--space-md) var(--space-lg);background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);flex-wrap:wrap;">
          <div>
            <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px;">Match</div>
            <div style="font-family:var(--font-condensed);font-size:1rem;font-weight:700;color:var(--text-primary);"><?= e($review['match_name']) ?></div>
          </div>
          <div style="width:1px;height:36px;background:var(--border-default);"></div>
          <div>
            <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px;">Competition</div>
            <div style="font-family:var(--font-condensed);font-size:1rem;font-weight:700;color:var(--text-primary);"><?= e($review['competition']) ?></div>
          </div>
          <div style="width:1px;height:36px;background:var(--border-default);"></div>
          <div>
            <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px;">Minute</div>
            <div style="font-family:var(--font-display);font-size:1.3rem;color:var(--neon-amber);"><?= (int)$review['incident_min'] ?>'</div>
          </div>
          <div style="width:1px;height:36px;background:var(--border-default);"></div>
          <div>
            <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px;">Submitted</div>
            <div style="font-family:var(--font-condensed);font-size:.9rem;color:var(--text-secondary);"><?= time_ago($review['created_at']) ?></div>
          </div>
        </div>
      </header>

      <!-- Incident image -->
      <div style="border-radius:var(--radius-lg);overflow:hidden;margin-bottom:var(--space-xl);position:relative;aspect-ratio:16/9;background:var(--bg-surface);">
        <img
          src="<?= UPLOAD_URL . e($review['image_path']) ?>"
          alt="<?= e($review['title']) ?>"
          style="width:100%;height:100%;object-fit:cover;"
          onerror="this.parentElement.style.display='flex';this.parentElement.style.alignItems='center';this.parentElement.style.justifyContent='center';this.remove();"
        >
        <!-- HUD overlay corner -->
        <div style="position:absolute;top:0;left:0;right:0;padding:var(--space-md);background:linear-gradient(to bottom,rgba(7,8,15,.7),transparent);display:flex;align-items:center;justify-content:space-between;">
          <span style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.6);">INCIDENT FOOTAGE</span>
          <span style="font-family:var(--font-display);font-size:1rem;color:var(--neon-amber);letter-spacing:.05em;"><?= (int)$review['incident_min'] ?>'</span>
        </div>
        <div style="position:absolute;bottom:0;left:0;right:0;padding:var(--space-md);background:linear-gradient(to top,rgba(7,8,15,.8),transparent);">
          <div style="font-family:var(--font-display);font-size:1.1rem;letter-spacing:.04em;color:rgba(255,255,255,.9);"><?= e($review['match_name']) ?></div>
        </div>
      </div>

      <!-- Description -->
      <div style="margin-bottom:var(--space-xl);">
        <div class="section-header__eyebrow" style="margin-bottom:var(--space-sm);">What Happened</div>
        <div style="font-size:1rem;color:var(--text-secondary);line-height:1.8;white-space:pre-line;"><?= e($review['description']) ?></div>
      </div>

      <!-- Author card -->
      <div style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-md) var(--space-lg);background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);margin-bottom:var(--space-xl);">
        <div style="width:44px;height:44px;border-radius:50%;border:2px solid var(--border-default);overflow:hidden;flex-shrink:0;">
          <img src="<?= SITE_URL ?>/uploads/avatars/<?= e($review['author_avatar']) ?>"
               alt="<?= e($review['author']) ?>"
               style="width:100%;height:100%;object-fit:cover;"
               onerror="this.src='<?= SITE_URL ?>/assets/images/default_avatar.svg'">
        </div>
        <div>
          <div style="font-family:var(--font-condensed);font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);">Submitted by</div>
          <div style="font-family:var(--font-condensed);font-size:1rem;font-weight:700;color:var(--text-primary);"><?= e($review['author']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted);">Member since <?= date('M Y', strtotime($review['author_joined'])) ?></div>
        </div>
      </div>

      <!-- ── Comments Section ──────────────────────────── -->
      <section id="comments" aria-label="Discussion">
        <div class="section-header" style="margin-bottom:var(--space-lg);">
          <div>
            <div class="section-header__eyebrow">Community Discussion</div>
            <h2 class="section-header__title">
              Comments
              <span style="font-family:var(--font-mono);font-size:1rem;color:var(--text-muted);" id="comment-count-display">(<?= $total_comments ?>)</span>
            </h2>
          </div>
        </div>

        <!-- Comment form -->
        <?php if (is_logged_in()): ?>
          <div style="margin-bottom:var(--space-xl);">
            <div style="display:flex;gap:var(--space-md);align-items:flex-start;">
              <div style="width:36px;height:36px;border-radius:50%;border:1px solid var(--border-default);overflow:hidden;flex-shrink:0;">
                <img src="<?= SITE_URL ?>/uploads/avatars/<?= e($_SESSION['avatar'] ?? 'default_avatar.svg') ?>"
                     alt="You"
                     style="width:100%;height:100%;object-fit:cover;"
                     onerror="this.src='<?= SITE_URL ?>/assets/images/default_avatar.svg'">
              </div>
              <div style="flex:1;">
                <form id="comment-form" data-review-id="<?= $id ?>">
                  <textarea
                    name="body"
                    class="form-control"
                    placeholder="Share your take on this decision..."
                    maxlength="1000"
                    style="min-height:90px;margin-bottom:var(--space-sm);"
                    required
                  ></textarea>
                  <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn--primary btn--sm">Post Comment</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div style="padding:var(--space-lg);background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);text-align:center;margin-bottom:var(--space-xl);">
            <p style="margin-bottom:var(--space-md);">Join the discussion — <strong style="color:var(--text-primary);">sign in to comment</strong></p>
            <div style="display:flex;gap:var(--space-sm);justify-content:center;">
              <a href="<?= SITE_URL ?>/auth/login.php?redirect=<?= urlencode(SITE_URL . '/review.php?id=' . $id) ?>" class="btn btn--primary btn--sm">Sign In</a>
              <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn--ghost btn--sm">Join Free</a>
            </div>
          </div>
        <?php endif; ?>

        <!-- Comment list -->
        <div id="comment-list">
          <?php if (empty($comments)): ?>
            <div class="empty-state" style="padding:var(--space-xl) 0;">
              <span class="empty-state__icon">💬</span>
              <div class="empty-state__title">No comments yet</div>
              <p>Be the first to share your verdict on this decision.</p>
            </div>
          <?php else: ?>
            <?php foreach ($comments as $c): ?>
              <div class="comment" id="comment-<?= (int)$c['id'] ?>">
                <div class="comment__avatar">
                  <img src="<?= SITE_URL ?>/uploads/avatars/<?= e($c['avatar']) ?>"
                       alt="<?= e($c['username']) ?>"
                       onerror="this.src='<?= SITE_URL ?>/assets/images/default_avatar.svg'">
                </div>
                <div class="comment__body">
                  <div class="comment__header">
                    <span class="comment__name"><?= e($c['username']) ?></span>
                    <span class="comment__time"><?= time_ago($c['created_at']) ?></span>
                    <?php if (is_moderator() || (is_logged_in() && current_user_id() == $c['user_id'])): ?>
                      <button
                        class="btn btn--sm"
                        style="margin-left:auto;padding:2px 8px;font-size:.7rem;color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                        onclick="deleteComment(<?= (int)$c['id'] ?>, this)"
                        data-confirm="Delete this comment?">
                        Delete
                      </button>
                    <?php endif; ?>
                  </div>
                  <p class="comment__text"><?= nl2br(e($c['body'])) ?></p>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- Comment pagination -->
            <?php if ($comment_pages > 1): ?>
              <nav class="pagination" style="margin-top:var(--space-lg);">
                <?php for ($p = 1; $p <= $comment_pages; $p++): ?>
                  <a href="?id=<?= $id ?>&cpage=<?= $p ?>#comments"
                     class="pagination__btn <?= $p === $comment_page ? 'active' : '' ?>">
                    <?= $p ?>
                  </a>
                <?php endfor; ?>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>

    </div><!-- /left column -->

    <!-- ══ RIGHT SIDEBAR ════════════════════════════════════ -->
    <aside style="position:sticky;top:calc(var(--nav-height) + var(--space-lg));">

      <!-- ── VERDICT PANEL ─────────────────────────────── -->
      <div style="background:var(--bg-card);border:1px solid var(--border-default);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:var(--space-lg);">

        <!-- Panel header -->
        <div style="padding:var(--space-md) var(--space-lg);background:var(--bg-surface);border-bottom:1px solid var(--border-subtle);display:flex;align-items:center;gap:var(--space-sm);">
          <div style="width:8px;height:8px;border-radius:50%;background:var(--neon-green);animation:pulse-live 1.5s infinite;"></div>
          <span style="font-family:var(--font-mono);font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--neon-green);">Live Verdict</span>
        </div>

        <div style="padding:var(--space-lg);">

          <!-- Big percentage display -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm);margin-bottom:var(--space-lg);" id="pct-display">
            <!-- Correct -->
            <div style="text-align:center;padding:var(--space-md);background:var(--neon-green-glow);border:1px solid rgba(0,232,122,.2);border-radius:var(--radius-md);">
              <div style="font-family:var(--font-display);font-size:2.4rem;color:var(--neon-green);line-height:1;" id="correct-pct-big"><?= $pct['correct_pct'] ?>%</div>
              <div style="font-family:var(--font-condensed);font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--neon-green);margin-top:4px;">✓ Correct</div>
              <div style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);margin-top:2px;" id="correct-count"><?= number_format((int)$review['correct_votes']) ?> votes</div>
            </div>
            <!-- Wrong -->
            <div style="text-align:center;padding:var(--space-md);background:var(--neon-red-glow);border:1px solid rgba(255,45,85,.2);border-radius:var(--radius-md);">
              <div style="font-family:var(--font-display);font-size:2.4rem;color:var(--neon-red);line-height:1;" id="wrong-pct-big"><?= $pct['wrong_pct'] ?>%</div>
              <div style="font-family:var(--font-condensed);font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--neon-red);margin-top:4px;">✗ Wrong</div>
              <div style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);margin-top:2px;" id="wrong-count"><?= number_format((int)$review['wrong_votes']) ?> votes</div>
            </div>
          </div>

          <!-- Dual vote bar -->
          <div style="margin-bottom:var(--space-lg);">
            <div style="height:10px;border-radius:var(--radius-pill);background:var(--bg-surface);overflow:hidden;position:relative;">
              <div id="vote-bar-correct"
                   style="position:absolute;left:0;top:0;height:100%;border-radius:var(--radius-pill) 0 0 var(--radius-pill);background:var(--neon-green);transition:width .8s cubic-bezier(.22,1,.36,1);width:<?= $pct['correct_pct'] ?>%;">
              </div>
              <div id="vote-bar-wrong"
                   style="position:absolute;right:0;top:0;height:100%;border-radius:0 var(--radius-pill) var(--radius-pill) 0;background:var(--neon-red);transition:width .8s cubic-bezier(.22,1,.36,1);width:<?= $pct['wrong_pct'] ?>%;">
              </div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:6px;font-family:var(--font-mono);font-size:.72rem;">
              <span style="color:var(--neon-green);">CORRECT</span>
              <span style="color:var(--text-muted);" id="total-votes-display">
                <?= number_format($pct['total']) ?> total votes
              </span>
              <span style="color:var(--neon-red);">WRONG</span>
            </div>
          </div>

          <!-- Majority verdict label -->
          <?php if ($pct['total'] > 0): ?>
            <div style="text-align:center;margin-bottom:var(--space-lg);padding:var(--space-sm);background:var(--bg-surface);border-radius:var(--radius-sm);">
              <?php
              $verdict      = $pct['correct_pct'] >= 50 ? 'correct' : 'wrong';
              $verdict_pct  = $pct['correct_pct'] >= 50 ? $pct['correct_pct'] : $pct['wrong_pct'];
              $verdict_color = $verdict === 'correct' ? 'var(--neon-green)' : 'var(--neon-red)';
              $verdict_icon  = $verdict === 'correct' ? '✓' : '✗';
              ?>
              <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px;">Community Verdict</div>
              <div style="font-family:var(--font-display);font-size:1.4rem;color:<?= $verdict_color ?>;">
                <?= $verdict_icon ?> <?= strtoupper($verdict) ?> DECISION
              </div>
              <div style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
                <?= $verdict_pct ?>% of <?= number_format($pct['total']) ?> voters agree
              </div>
            </div>
          <?php endif; ?>

          <!-- ── VOTE BUTTONS ──────────────────────────── -->
          <?php if (is_logged_in()): ?>
            <div class="vote-buttons" id="vote-buttons">
              <button
                class="vote-btn vote-btn--correct <?= $user_vote === 'correct' ? 'voted' : '' ?>"
                data-review-id="<?= $id ?>"
                data-vote-type="correct"
                <?= $user_vote ? 'disabled title="You already voted"' : '' ?>
                aria-label="Vote correct decision"
                aria-pressed="<?= $user_vote === 'correct' ? 'true' : 'false' ?>"
              >
                <span class="vote-btn__icon">✓</span>
                <span style="font-size:1rem;">Correct Decision</span>
                <span class="vote-btn__count" id="sidebar-correct-count"><?= number_format((int)$review['correct_votes']) ?></span>
              </button>
              <button
                class="vote-btn vote-btn--wrong <?= $user_vote === 'wrong' ? 'voted' : '' ?>"
                data-review-id="<?= $id ?>"
                data-vote-type="wrong"
                <?= $user_vote ? 'disabled title="You already voted"' : '' ?>
                aria-label="Vote wrong decision"
                aria-pressed="<?= $user_vote === 'wrong' ? 'true' : 'false' ?>"
              >
                <span class="vote-btn__icon">✗</span>
                <span style="font-size:1rem;">Wrong Decision</span>
                <span class="vote-btn__count" id="sidebar-wrong-count"><?= number_format((int)$review['wrong_votes']) ?></span>
              </button>
            </div>

            <?php if ($user_vote): ?>
              <p style="text-align:center;font-size:.8rem;color:var(--text-muted);margin-top:var(--space-sm);">
                You already voted — one vote per controversy.
              </p>
            <?php endif; ?>

          <?php else: ?>
            <div style="text-align:center;padding:var(--space-md);background:var(--bg-surface);border-radius:var(--radius-md);">
              <p style="font-size:.9rem;margin-bottom:var(--space-md);">
                <strong style="color:var(--text-primary);">Sign in to cast your vote</strong>
              </p>
              <a href="<?= SITE_URL ?>/auth/login.php?redirect=<?= urlencode(SITE_URL . '/review.php?id=' . $id) ?>"
                 class="btn btn--primary" style="width:100%;justify-content:center;">
                Sign In & Vote
              </a>
            </div>
          <?php endif; ?>

        </div>
      </div><!-- /verdict panel -->

      <!-- ── STATS PANEL ───────────────────────────────── -->
      <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);padding:var(--space-lg);margin-bottom:var(--space-lg);">
        <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:var(--space-md);">Match Stats</div>
        <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
          <?php
          $stats_rows = [
            ['Total Votes',     number_format($pct['total'])],
            ['Correct Votes',   number_format((int)$review['correct_votes'])],
            ['Wrong Votes',     number_format((int)$review['wrong_votes'])],
            ['Total Views',     number_format((int)$review['views'] + 1)],
            ['Comments',        $total_comments],
            ['Incident Minute', $review['incident_min'] . "'"],
          ];
          foreach ($stats_rows as [$label, $val]): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border-subtle);">
            <span style="font-family:var(--font-condensed);font-size:.82rem;font-weight:600;color:var(--text-muted);"><?= e($label) ?></span>
            <span style="font-family:var(--font-mono);font-size:.82rem;font-weight:700;color:var(--text-primary);"><?= e($val) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── RELATED ───────────────────────────────────── -->
      <?php if (!empty($related)): ?>
      <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);padding:var(--space-lg);">
        <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:var(--space-md);">Related Controversies</div>
        <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
          <?php foreach ($related as $rel): ?>
          <a href="<?= SITE_URL ?>/review.php?id=<?= (int)$rel['id'] ?>"
             style="display:flex;gap:var(--space-sm);padding:var(--space-sm);border-radius:var(--radius-sm);transition:background .15s;"
             onmouseover="this.style.background='var(--border-subtle)'"
             onmouseout="this.style.background='transparent'">
            <div style="width:60px;height:40px;border-radius:var(--radius-sm);overflow:hidden;flex-shrink:0;background:var(--bg-surface);">
              <img src="<?= UPLOAD_URL . e($rel['image_path']) ?>"
                   alt=""
                   style="width:100%;height:100%;object-fit:cover;"
                   onerror="this.style.display='none'">
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-family:var(--font-condensed);font-size:.85rem;font-weight:700;color:var(--text-primary);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= e($rel['title']) ?>
              </div>
              <div style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-muted);margin-top:2px;">
                <?= number_format((int)$rel['total_votes']) ?> votes · <?= (int)$rel['incident_min'] ?>'
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </aside><!-- /sidebar -->
  </div><!-- /grid -->
</div><!-- /container -->
</main>

<script>
/* ── Override updateVoteUI for the review page ──────────── */
document.addEventListener('DOMContentLoaded', function () {

  /* Animate dual vote bar on load */
  const correct = parseInt('<?= $pct['correct_pct'] ?>');
  const wrong   = parseInt('<?= $pct['wrong_pct'] ?>');
  setTimeout(() => {
    const bc = document.getElementById('vote-bar-correct');
    const bw = document.getElementById('vote-bar-wrong');
    if (bc) bc.style.width = correct + '%';
    if (bw) bw.style.width = wrong + '%';
  }, 300);
});

/* Override the global updateVoteUI to also update sidebar elements */
function updateVoteUI(reviewId, data) {
  /* Update sidebar big percentages */
  const cp = document.getElementById('correct-pct-big');
  const wp = document.getElementById('wrong-pct-big');
  const cc = document.getElementById('correct-count');
  const wc = document.getElementById('wrong-count');
  const bc = document.getElementById('vote-bar-correct');
  const bw = document.getElementById('vote-bar-wrong');
  const tv = document.getElementById('total-votes-display');
  const sc = document.getElementById('sidebar-correct-count');
  const sw = document.getElementById('sidebar-wrong-count');

  if (cp) cp.textContent = data.correct_pct + '%';
  if (wp) wp.textContent = data.wrong_pct   + '%';
  if (cc) cc.textContent = data.correct_votes.toLocaleString() + ' votes';
  if (wc) wc.textContent = data.wrong_votes.toLocaleString()   + ' votes';
  if (bc) bc.style.width = data.correct_pct + '%';
  if (bw) bw.style.width = data.wrong_pct   + '%';
  if (tv) tv.textContent = data.total.toLocaleString() + ' total votes';
  if (sc) sc.textContent = data.correct_votes.toLocaleString();
  if (sw) sw.textContent = data.wrong_votes.toLocaleString();

  /* Disable both buttons after voting */
  document.querySelectorAll('.vote-btn').forEach(btn => {
    btn.disabled = true;
    btn.classList.toggle('voted', btn.dataset.voteType === data.user_vote);
  });

  /* Add "you voted" badge dynamically */
  const badgeArea = document.querySelector('[aria-label="Breadcrumb"]')
                    ?.nextElementSibling?.querySelector('.badge:last-of-type');

  showToast('Your vote is locked in!', 'success');
}

/* ── Delete comment via AJAX ────────────────────────────── */
function deleteComment(commentId, btn) {
  if (!confirm('Delete this comment?')) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  fetch('<?= SITE_URL ?>/ajax/comment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete', comment_id: commentId, csrf_token: csrf }),
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const el = document.getElementById('comment-' + commentId);
      if (el) {
        el.style.opacity = '0';
        el.style.transition = 'opacity .3s';
        setTimeout(() => el.remove(), 300);
      }
      /* Decrement count display */
      const countEl = document.getElementById('badge-comment-count');
      if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
      showToast('Comment deleted.', 'info');
    } else {
      showToast(data.error || 'Could not delete comment.', 'error');
    }
  })
  .catch(() => showToast('Network error.', 'error'));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

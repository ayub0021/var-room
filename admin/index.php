<?php
// ============================================================
//  VAR ROOM — Admin Dashboard
//  admin/index.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// ── Overview stats ─────────────────────────────────────────
$stats = db_fetch_one("
    SELECT
        (SELECT COUNT(*) FROM users)                                  AS total_users,
        (SELECT COUNT(*) FROM users  WHERE is_banned = 1)            AS banned_users,
        (SELECT COUNT(*) FROM match_reviews)                          AS total_reviews,
        (SELECT COUNT(*) FROM match_reviews WHERE status='pending')   AS pending_reviews,
        (SELECT COUNT(*) FROM match_reviews WHERE status='approved')  AS approved_reviews,
        (SELECT COUNT(*) FROM match_reviews WHERE status='rejected')  AS rejected_reviews,
        (SELECT COUNT(*) FROM votes)                                  AS total_votes,
        (SELECT COUNT(*) FROM comments WHERE is_deleted=0)           AS total_comments,
        (SELECT COUNT(*) FROM users  WHERE DATE(created_at)=CURDATE()) AS new_users_today,
        (SELECT COUNT(*) FROM votes  WHERE DATE(created_at)=CURDATE()) AS votes_today
");

// ── Recent pending reviews ─────────────────────────────────
$pending = db_fetch_all("
    SELECT mr.*, u.username AS author
    FROM match_reviews mr
    JOIN users u ON mr.user_id = u.id
    WHERE mr.status = 'pending'
    ORDER BY mr.created_at DESC
    LIMIT 8
");

// ── Recent users ───────────────────────────────────────────
$recent_users = db_fetch_all("
    SELECT id, username, email, role, is_banned, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 8
");

// ── Top controversies by votes ─────────────────────────────
$top_reviews = db_fetch_all("
    SELECT mr.id, mr.title, mr.match_name, mr.competition,
           COUNT(v.id) AS total_votes,
           SUM(v.vote_type='correct') AS correct_votes,
           SUM(v.vote_type='wrong')   AS wrong_votes
    FROM match_reviews mr
    LEFT JOIN votes v ON mr.id = v.review_id
    WHERE mr.status = 'approved'
    GROUP BY mr.id
    ORDER BY total_votes DESC
    LIMIT 5
");

// ── Activity over last 7 days ──────────────────────────────
$activity = db_fetch_all("
    SELECT
        DATE(created_at)  AS day,
        COUNT(*)          AS vote_count
    FROM votes
    WHERE created_at >= NOW() - INTERVAL 7 DAY
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

$page_title  = 'Admin Dashboard';
$active_page = 'admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">

  <!-- ── Sidebar ─────────────────────────────────────────── -->
  <aside class="admin-sidebar">
    <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:var(--space-lg);padding-left:14px;">
      Admin Panel
    </div>

    <nav>
      <a href="<?= SITE_URL ?>/admin/index.php"    class="admin-nav-item active">📊 Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/moderate.php" class="admin-nav-item">
        ⏳ Review Queue
        <?php if ((int)$stats['pending_reviews'] > 0): ?>
          <span style="margin-left:auto;font-family:var(--font-mono);font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:99px;background:var(--neon-amber);color:var(--text-inverse);">
            <?= (int)$stats['pending_reviews'] ?>
          </span>
        <?php endif; ?>
      </a>
      <a href="<?= SITE_URL ?>/admin/users.php"    class="admin-nav-item">👥 Users</a>
      <a href="<?= SITE_URL ?>/admin/reviews.php"  class="admin-nav-item">📋 All Reviews</a>
      <a href="<?= SITE_URL ?>/admin/comments.php" class="admin-nav-item">💬 Comments</a>

      <div style="height:1px;background:var(--border-subtle);margin:var(--space-md) 0;"></div>

      <a href="<?= SITE_URL ?>/index.php"          class="admin-nav-item">🏠 View Site</a>
      <a href="<?= SITE_URL ?>/auth/logout.php"    class="admin-nav-item" style="color:var(--neon-red);">🚪 Logout</a>
    </nav>
  </aside>

  <!-- ── Main ────────────────────────────────────────────── -->
  <main class="admin-main">

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-xl);">
      <div>
        <div class="section-header__eyebrow">Control Centre</div>
        <h1 style="font-size:2.4rem;">Dashboard</h1>
      </div>
      <div style="font-family:var(--font-mono);font-size:.78rem;color:var(--text-muted);">
        <?= date('D, d M Y — H:i') ?>
      </div>
    </div>

    <!-- ── Stat Cards Row ───────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:var(--space-md);margin-bottom:var(--space-xl);">
      <?php
      $stat_cards = [
        ['Total Users',     $stats['total_users'],     'var(--neon-green)',  '👥'],
        ['Approved',        $stats['approved_reviews'],'var(--neon-green)',  '✅'],
        ['Pending',         $stats['pending_reviews'], 'var(--neon-amber)',  '⏳'],
        ['Total Votes',     $stats['total_votes'],     'var(--neon-blue)',   '🗳'],
        ['Comments',        $stats['total_comments'],  'var(--text-muted)',  '💬'],
      ];
      foreach ($stat_cards as [$label, $value, $color, $icon]):
      ?>
      <div class="stat-card">
        <div class="stat-card__accent" style="background:<?= $color ?>;"></div>
        <div style="font-size:1.4rem;margin-bottom:var(--space-sm);"><?= $icon ?></div>
        <div class="stat-card__value" style="color:<?= $color ?>;"><?= number_format((int)$value) ?></div>
        <div class="stat-card__label"><?= e($label) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Today strip ──────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:var(--space-md);margin-bottom:var(--space-xl);">
      <div style="padding:var(--space-md) var(--space-lg);background:var(--bg-card);border:1px solid var(--border-subtle);border-left:3px solid var(--neon-green);border-radius:var(--radius-md);display:flex;align-items:center;gap:var(--space-md);">
        <div style="font-size:1.8rem;">🆕</div>
        <div>
          <div style="font-family:var(--font-display);font-size:1.8rem;color:var(--neon-green);"><?= (int)$stats['new_users_today'] ?></div>
          <div style="font-family:var(--font-condensed);font-size:.78rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);">New Users Today</div>
        </div>
      </div>
      <div style="padding:var(--space-md) var(--space-lg);background:var(--bg-card);border:1px solid var(--border-subtle);border-left:3px solid var(--neon-amber);border-radius:var(--radius-md);display:flex;align-items:center;gap:var(--space-md);">
        <div style="font-size:1.8rem;">🗳</div>
        <div>
          <div style="font-family:var(--font-display);font-size:1.8rem;color:var(--neon-amber);"><?= (int)$stats['votes_today'] ?></div>
          <div style="font-family:var(--font-condensed);font-size:.78rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);">Votes Cast Today</div>
        </div>
      </div>
    </div>

    <!-- ── Two-col grid ──────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-xl);margin-bottom:var(--space-xl);">

      <!-- Pending queue preview -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-md);">
          <div>
            <div class="section-header__eyebrow">Needs Attention</div>
            <h3 style="font-size:1.5rem;">Pending Queue</h3>
          </div>
          <a href="<?= SITE_URL ?>/admin/moderate.php" class="btn btn--sm btn--ghost">View All →</a>
        </div>

        <?php if (empty($pending)): ?>
          <div style="padding:var(--space-xl);text-align:center;background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);">
            <div style="font-size:2rem;margin-bottom:var(--space-sm);">✅</div>
            <div style="font-family:var(--font-condensed);font-weight:700;color:var(--text-secondary);">Queue is clear!</div>
          </div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
            <?php foreach ($pending as $p): ?>
            <div style="display:flex;align-items:center;gap:var(--space-md);padding:var(--space-md);background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);">
              <div style="flex:1;min-width:0;">
                <div style="font-family:var(--font-condensed);font-size:.9rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= e($p['title']) ?>
                </div>
                <div style="font-size:.78rem;color:var(--text-muted);">
                  by <?= e($p['author']) ?> · <?= time_ago($p['created_at']) ?>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0;">
                <a href="<?= SITE_URL ?>/admin/moderate.php?action=approve&id=<?= (int)$p['id'] ?>&token=<?= csrf_token() ?>"
                   class="btn btn--sm"
                   style="background:var(--neon-green-glow);color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                   onclick="return confirm('Approve this submission?')">✓</a>
                <a href="<?= SITE_URL ?>/admin/moderate.php?action=reject&id=<?= (int)$p['id'] ?>&token=<?= csrf_token() ?>"
                   class="btn btn--sm"
                   style="background:var(--neon-red-glow);color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                   onclick="return confirm('Reject this submission?')">✗</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Top controversies -->
      <div>
        <div style="margin-bottom:var(--space-md);">
          <div class="section-header__eyebrow">Most Engaged</div>
          <h3 style="font-size:1.5rem;">Top Controversies</h3>
        </div>

        <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);overflow:hidden;">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Title</th>
                <th>Competition</th>
                <th>Votes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($top_reviews as $i => $tr):
                $pct = calc_vote_pct((int)$tr['correct_votes'], (int)$tr['wrong_votes']);
              ?>
              <tr>
                <td style="font-family:var(--font-mono);font-size:.8rem;color:var(--text-muted);"><?= $i+1 ?></td>
                <td>
                  <a href="<?= SITE_URL ?>/review.php?id=<?= (int)$tr['id'] ?>"
                     style="font-family:var(--font-condensed);font-size:.85rem;font-weight:700;color:var(--text-primary);transition:color .15s;"
                     onmouseover="this.style.color='var(--neon-green)'"
                     onmouseout="this.style.color='var(--text-primary)'">
                    <?= e(truncate($tr['title'], 38)) ?>
                  </a>
                </td>
                <td><span class="badge badge--gray" style="font-size:.6rem;"><?= e($tr['competition']) ?></span></td>
                <td>
                  <span style="font-family:var(--font-mono);font-size:.8rem;font-weight:700;color:var(--text-primary);"><?= number_format((int)$tr['total_votes']) ?></span>
                  <div style="width:60px;height:3px;background:var(--bg-surface);border-radius:99px;margin-top:3px;overflow:hidden;">
                    <div style="height:100%;background:var(--neon-green);width:<?= $pct['correct_pct'] ?>%;border-radius:99px;"></div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($top_reviews)): ?>
              <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:var(--space-lg);">No approved reviews yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Recent Users table ────────────────────────────── -->
    <div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-md);">
        <div>
          <div class="section-header__eyebrow">Community</div>
          <h3 style="font-size:1.5rem;">Recent Members</h3>
        </div>
        <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn--sm btn--ghost">Manage All →</a>
      </div>

      <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);overflow:hidden;">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_users as $u): ?>
            <tr>
              <td>
                <div style="font-family:var(--font-condensed);font-size:.9rem;font-weight:700;color:var(--text-primary);">
                  <?= e($u['username']) ?>
                </div>
              </td>
              <td style="font-size:.82rem;"><?= e($u['email']) ?></td>
              <td>
                <?php
                $role_colors = ['admin'=>'badge--amber','moderator'=>'badge--green','user'=>'badge--gray'];
                $rc = $role_colors[$u['role']] ?? 'badge--gray';
                ?>
                <span class="badge <?= $rc ?>"><?= e(strtoupper($u['role'])) ?></span>
              </td>
              <td style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);">
                <?= time_ago($u['created_at']) ?>
              </td>
              <td>
                <?php if ((int)$u['is_banned']): ?>
                  <span class="badge badge--red">BANNED</span>
                <?php else: ?>
                  <span class="badge badge--green">ACTIVE</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u['username'] !== 'admin'): ?>
                <div style="display:flex;gap:4px;">
                  <?php if (!(int)$u['is_banned']): ?>
                  <a href="<?= SITE_URL ?>/admin/users.php?action=ban&id=<?= (int)$u['id'] ?>&token=<?= csrf_token() ?>"
                     class="btn btn--sm"
                     style="font-size:.68rem;padding:3px 8px;color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                     onclick="return confirm('Ban user <?= e($u['username']) ?>?')">Ban</a>
                  <?php else: ?>
                  <a href="<?= SITE_URL ?>/admin/users.php?action=unban&id=<?= (int)$u['id'] ?>&token=<?= csrf_token() ?>"
                     class="btn btn--sm"
                     style="font-size:.68rem;padding:3px 8px;color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                     onclick="return confirm('Unban user <?= e($u['username']) ?>?')">Unban</a>
                  <?php endif; ?>
                </div>
                <?php else: ?>
                  <span style="font-size:.72rem;color:var(--text-muted);">Protected</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

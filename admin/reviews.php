<?php
// ============================================================
//  VAR ROOM — Admin: All Reviews
//  admin/reviews.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// ── Handle delete ──────────────────────────────────────────
if ($_GET['action'] ?? '' === 'delete') {
    $item_id = (int)($_GET['id'] ?? 0);
    $token   = $_GET['token'] ?? '';
    if ($item_id > 0 && hash_equals(csrf_token(), $token)) {
        // Fetch image path to delete from disk
        $rev = db_fetch_one('SELECT image_path FROM match_reviews WHERE id = ?', [$item_id]);
        if ($rev) {
            $img_file = UPLOAD_DIR . $rev['image_path'];
            if (is_file($img_file)) @unlink($img_file);
        }
        db_execute('DELETE FROM match_reviews WHERE id = ?', [$item_id]);
        set_flash('success', "Review #$item_id permanently deleted.");
    } else {
        set_flash('error', 'Invalid request.');
    }
    redirect(SITE_URL . '/admin/reviews.php');
}

// ── Filters & pagination ───────────────────────────────────
$search      = trim($_GET['q']           ?? '');
$comp_filter = trim($_GET['competition'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 15;
$offset      = ($page - 1) * $limit;

$where  = 'WHERE 1=1';
$params = [];

if ($search) {
    $where   .= ' AND (mr.title LIKE ? OR mr.match_name LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($comp_filter) {
    $where   .= ' AND mr.competition = ?';
    $params[] = $comp_filter;
}

$total = (int) db_fetch_one(
    "SELECT COUNT(*) AS n FROM match_reviews mr $where", $params
)['n'];
$total_pages = (int) ceil($total / $limit);

$reviews = db_fetch_all(
    "SELECT mr.*, u.username AS author,
            COUNT(DISTINCT v.id)  AS total_votes,
            COUNT(DISTINCT c.id)  AS comment_count
     FROM match_reviews mr
     JOIN  users u ON mr.user_id = u.id
     LEFT JOIN votes    v ON mr.id = v.review_id
     LEFT JOIN comments c ON mr.id = c.review_id AND c.is_deleted = 0
     $where
     GROUP BY mr.id
     ORDER BY mr.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$page_title  = 'All Reviews';
$active_page = 'admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:var(--space-lg);padding-left:14px;">Admin Panel</div>
    <nav>
      <a href="<?= SITE_URL ?>/admin/index.php"    class="admin-nav-item">📊 Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/moderate.php" class="admin-nav-item">⏳ Review Queue</a>
      <a href="<?= SITE_URL ?>/admin/users.php"    class="admin-nav-item">👥 Users</a>
      <a href="<?= SITE_URL ?>/admin/reviews.php"  class="admin-nav-item active">📋 All Reviews</a>
      <a href="<?= SITE_URL ?>/admin/comments.php" class="admin-nav-item">💬 Comments</a>
      <div style="height:1px;background:var(--border-subtle);margin:var(--space-md) 0;"></div>
      <a href="<?= SITE_URL ?>/index.php"          class="admin-nav-item">🏠 View Site</a>
      <a href="<?= SITE_URL ?>/auth/logout.php"    class="admin-nav-item" style="color:var(--neon-red);">🚪 Logout</a>
    </nav>
  </aside>

  <main class="admin-main">

    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-xl);flex-wrap:wrap;gap:var(--space-md);">
      <div>
        <div class="section-header__eyebrow">Content</div>
        <h1 style="font-size:2.4rem;">All Reviews</h1>
      </div>
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="text" name="q" value="<?= e($search) ?>"
               class="form-control" placeholder="Search title or match…" style="width:200px;">
        <select name="competition" class="form-control" style="width:160px;">
          <option value="">All Competitions</option>
          <?php foreach (COMPETITIONS as $c): ?>
            <option value="<?= e($c) ?>" <?= $comp_filter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn--primary btn--sm">Filter</button>
        <?php if ($search || $comp_filter): ?>
          <a href="?" class="btn btn--ghost btn--sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);overflow:hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Author</th>
            <th>Competition</th>
            <th>Min</th>
            <th>Votes</th>
            <th>Comments</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reviews as $r): ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);">#<?= (int)$r['id'] ?></td>
            <td style="max-width:220px;">
              <a href="<?= SITE_URL ?>/review.php?id=<?= (int)$r['id'] ?>"
                 target="_blank"
                 style="font-family:var(--font-condensed);font-size:.88rem;font-weight:700;color:var(--text-primary);transition:color .15s;"
                 onmouseover="this.style.color='var(--neon-green)'"
                 onmouseout="this.style.color='var(--text-primary)'">
                <?= e(truncate($r['title'], 45)) ?>
              </a>
              <div style="font-size:.75rem;color:var(--text-muted);"><?= e($r['match_name']) ?></div>
            </td>
            <td style="font-family:var(--font-condensed);font-size:.85rem;font-weight:600;color:var(--text-secondary);"><?= e($r['author']) ?></td>
            <td><span class="badge badge--gray" style="font-size:.6rem;"><?= e($r['competition']) ?></span></td>
            <td style="font-family:var(--font-display);font-size:.95rem;color:var(--neon-amber);text-align:center;"><?= (int)$r['incident_min'] ?>'</td>
            <td style="font-family:var(--font-mono);font-size:.82rem;text-align:center;"><?= number_format((int)$r['total_votes']) ?></td>
            <td style="font-family:var(--font-mono);font-size:.82rem;text-align:center;"><?= (int)$r['comment_count'] ?></td>
            <td>
              <?php
              $sb = ['pending'=>'badge--amber','approved'=>'badge--green','rejected'=>'badge--red'];
              echo '<span class="badge '.($sb[$r['status']]??'badge--gray').'">'.strtoupper($r['status']).'</span>';
              ?>
            </td>
            <td style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);white-space:nowrap;">
              <?= date('d M Y', strtotime($r['created_at'])) ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;">
                <a href="<?= SITE_URL ?>/admin/moderate.php?action=<?= $r['status']==='approved'?'reject':'approve' ?>&id=<?= (int)$r['id'] ?>&token=<?= csrf_token() ?>"
                   class="btn btn--sm"
                   style="font-size:.68rem;padding:3px 8px;<?= $r['status']==='approved' ? 'color:var(--neon-red);border-color:rgba(255,45,85,.3);' : 'color:var(--neon-green);border-color:rgba(0,232,122,.3);' ?>"
                   onclick="return confirm('Change status of this review?')">
                  <?= $r['status']==='approved' ? 'Unpublish' : 'Approve' ?>
                </a>
                <a href="?action=delete&id=<?= (int)$r['id'] ?>&token=<?= csrf_token() ?>"
                   class="btn btn--sm btn--danger"
                   style="font-size:.68rem;padding:3px 8px;"
                   onclick="return confirm('PERMANENTLY delete this review and its image?')">
                  🗑
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($reviews)): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:var(--space-xl);">No reviews found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="pagination" style="margin-top:var(--space-xl);">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?q=<?= urlencode($search) ?>&competition=<?= urlencode($comp_filter) ?>&page=<?= $p ?>"
           class="pagination__btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

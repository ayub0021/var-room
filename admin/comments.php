<?php
// ============================================================
//  VAR ROOM — Admin: Comment Management
//  admin/comments.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// ── Handle delete / restore ────────────────────────────────
$action  = $_GET['action'] ?? '';
$item_id = (int)($_GET['id']    ?? 0);
$token   = $_GET['token'] ?? '';

if (in_array($action, ['delete','restore']) && $item_id > 0) {
    if (!hash_equals(csrf_token(), $token)) {
        set_flash('error', 'Security token invalid.');
        redirect(SITE_URL . '/admin/comments.php');
    }
    $val = $action === 'delete' ? 1 : 0;
    db_execute('UPDATE comments SET is_deleted = ? WHERE id = ?', [$val, $item_id]);
    set_flash('success', 'Comment ' . ($action === 'delete' ? 'deleted.' : 'restored.'));
    redirect(SITE_URL . '/admin/comments.php');
}

// ── Filters ────────────────────────────────────────────────
$show_deleted = !empty($_GET['deleted']);
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 20;
$offset       = ($page - 1) * $limit;

$where  = $show_deleted ? 'WHERE c.is_deleted = 1' : 'WHERE c.is_deleted = 0';
$params = [];

if ($search) {
    $where   .= ' AND (c.body LIKE ? OR u.username LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$total = (int) db_fetch_one(
    "SELECT COUNT(*) AS n FROM comments c JOIN users u ON c.user_id = u.id $where",
    $params
)['n'];
$total_pages = (int) ceil($total / $limit);

$comments = db_fetch_all(
    "SELECT c.*, u.username,
            mr.title AS review_title, mr.id AS review_id
     FROM comments c
     JOIN users         u  ON c.user_id  = u.id
     JOIN match_reviews mr ON c.review_id = mr.id
     $where
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$page_title  = 'Comment Management';
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
      <a href="<?= SITE_URL ?>/admin/reviews.php"  class="admin-nav-item">📋 All Reviews</a>
      <a href="<?= SITE_URL ?>/admin/comments.php" class="admin-nav-item active">💬 Comments</a>
      <div style="height:1px;background:var(--border-subtle);margin:var(--space-md) 0;"></div>
      <a href="<?= SITE_URL ?>/index.php"          class="admin-nav-item">🏠 View Site</a>
      <a href="<?= SITE_URL ?>/auth/logout.php"    class="admin-nav-item" style="color:var(--neon-red);">🚪 Logout</a>
    </nav>
  </aside>

  <main class="admin-main">

    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-xl);flex-wrap:wrap;gap:var(--space-md);">
      <div>
        <div class="section-header__eyebrow">Moderation</div>
        <h1 style="font-size:2.4rem;">Comments</h1>
      </div>
      <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;">
          <input type="hidden" name="deleted" value="<?= $show_deleted ? '1' : '' ?>">
          <input type="text" name="q" value="<?= e($search) ?>"
                 class="form-control" placeholder="Search comments or users…" style="width:220px;">
          <button type="submit" class="btn btn--primary btn--sm">Search</button>
        </form>
        <a href="?<?= $show_deleted ? '' : 'deleted=1' ?>"
           class="btn btn--sm <?= $show_deleted ? 'btn--danger' : 'btn--ghost' ?>">
          <?= $show_deleted ? '🗑 Deleted Comments' : '👁 View Deleted' ?>
        </a>
      </div>
    </div>

    <?php if ($show_deleted): ?>
      <div class="flash flash--error" style="margin-bottom:var(--space-md);">
        <span>⚠ Showing deleted (soft-deleted) comments. These are hidden from public view.</span>
      </div>
    <?php endif; ?>

    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);overflow:hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Comment</th>
            <th>Review</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($comments as $c): ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);">#<?= (int)$c['id'] ?></td>
            <td style="font-family:var(--font-condensed);font-size:.88rem;font-weight:700;color:var(--text-primary);white-space:nowrap;"><?= e($c['username']) ?></td>
            <td style="max-width:320px;">
              <p style="font-size:.85rem;color:var(--text-secondary);line-height:1.5;<?= $c['is_deleted'] ? 'text-decoration:line-through;opacity:.5;' : '' ?>">
                <?= e(truncate($c['body'], 120)) ?>
              </p>
            </td>
            <td>
              <a href="<?= SITE_URL ?>/review.php?id=<?= (int)$c['review_id'] ?>#comments"
                 target="_blank"
                 style="font-size:.8rem;color:var(--neon-green);"><?= e(truncate($c['review_title'], 40)) ?></a>
            </td>
            <td style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);white-space:nowrap;">
              <?= time_ago($c['created_at']) ?>
            </td>
            <td>
              <?php if (!(int)$c['is_deleted']): ?>
                <a href="?action=delete&id=<?= (int)$c['id'] ?>&token=<?= csrf_token() ?><?= $show_deleted ? '&deleted=1' : '' ?>"
                   class="btn btn--sm"
                   style="font-size:.68rem;padding:3px 8px;color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                   onclick="return confirm('Delete this comment?')">Delete</a>
              <?php else: ?>
                <a href="?action=restore&id=<?= (int)$c['id'] ?>&token=<?= csrf_token() ?>&deleted=1"
                   class="btn btn--sm"
                   style="font-size:.68rem;padding:3px 8px;color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                   onclick="return confirm('Restore this comment?')">Restore</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($comments)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:var(--space-xl);">No comments found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="pagination" style="margin-top:var(--space-xl);">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?q=<?= urlencode($search) ?>&deleted=<?= $show_deleted?'1':'' ?>&page=<?= $p ?>"
           class="pagination__btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

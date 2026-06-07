<?php
// ============================================================
//  VAR ROOM — Admin: Review Queue / Moderation
//  admin/moderate.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// ── Handle approve / reject via GET (quick action) ─────────
$action  = $_GET['action'] ?? '';
$item_id = (int)($_GET['id']    ?? 0);
$token   = $_GET['token'] ?? '';

if (in_array($action, ['approve','reject']) && $item_id > 0) {
    if (!hash_equals(csrf_token(), $token)) {
        set_flash('error', 'Security token invalid. Try again.');
        redirect(SITE_URL . '/admin/moderate.php');
    }

    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    $rows = db_execute(
        "UPDATE match_reviews SET status = ? WHERE id = ? AND status = 'pending'",
        [$new_status, $item_id]
    );

    if ($rows > 0) {
        set_flash('success', "Review #$item_id has been {$new_status}.");
    } else {
        set_flash('error', 'Review not found or already moderated.');
    }
    redirect(SITE_URL . '/admin/moderate.php');
}

// ── Handle bulk action via POST ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $bulk_ids    = array_map('intval', $_POST['bulk_ids'] ?? []);
    $bulk_action = $_POST['bulk_action'] ?? '';

    if (!empty($bulk_ids) && in_array($bulk_action, ['approve','reject'])) {
        $new_status = $bulk_action === 'approve' ? 'approved' : 'rejected';
        $placeholders = implode(',', array_fill(0, count($bulk_ids), '?'));
        $params = array_merge([$new_status], $bulk_ids);
        $rows = db_execute(
            "UPDATE match_reviews SET status = ? WHERE id IN ($placeholders) AND status = 'pending'",
            $params
        );
        set_flash('success', "$rows review(s) marked as {$new_status}.");
    } else {
        set_flash('error', 'No reviews selected or invalid action.');
    }
    redirect(SITE_URL . '/admin/moderate.php');
}

// ── Pagination & filter ────────────────────────────────────
$filter  = in_array($_GET['filter'] ?? '', ['pending','approved','rejected']) ? $_GET['filter'] : 'pending';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 12;
$offset  = ($page - 1) * $limit;

$where  = "WHERE mr.status = ?";
$params = [$filter];

$total = (int) db_fetch_one(
    "SELECT COUNT(*) AS n FROM match_reviews mr $where", $params
)['n'];
$total_pages = (int) ceil($total / $limit);

$reviews = db_fetch_all(
    "SELECT mr.*, u.username AS author,
            COUNT(DISTINCT v.id) AS total_votes,
            COUNT(DISTINCT c.id) AS comment_count
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

// ── Counts per status ──────────────────────────────────────
$counts = db_fetch_one("
    SELECT
        SUM(status='pending')  AS pending,
        SUM(status='approved') AS approved,
        SUM(status='rejected') AS rejected
    FROM match_reviews
");

$page_title  = 'Review Queue';
$active_page = 'admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">

  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:var(--space-lg);padding-left:14px;">Admin Panel</div>
    <nav>
      <a href="<?= SITE_URL ?>/admin/index.php"    class="admin-nav-item">📊 Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/moderate.php" class="admin-nav-item active">
        ⏳ Review Queue
        <?php if ((int)$counts['pending'] > 0): ?>
          <span style="margin-left:auto;font-family:var(--font-mono);font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:99px;background:var(--neon-amber);color:var(--text-inverse);">
            <?= (int)$counts['pending'] ?>
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

  <!-- Main -->
  <main class="admin-main">

    <!-- Header -->
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-xl);flex-wrap:wrap;gap:var(--space-md);">
      <div>
        <div class="section-header__eyebrow">Moderation</div>
        <h1 style="font-size:2.4rem;">Review Queue</h1>
      </div>
      <div style="display:flex;gap:var(--space-sm);">
        <?php
        $filter_tabs = [
          'pending'  => ['label'=>'Pending',  'count'=>$counts['pending'],  'color'=>'var(--neon-amber)'],
          'approved' => ['label'=>'Approved', 'count'=>$counts['approved'], 'color'=>'var(--neon-green)'],
          'rejected' => ['label'=>'Rejected', 'count'=>$counts['rejected'], 'color'=>'var(--neon-red)'],
        ];
        foreach ($filter_tabs as $key => $tab):
        ?>
          <a href="?filter=<?= $key ?>"
             class="btn btn--sm <?= $filter === $key ? 'btn--primary' : 'btn--ghost' ?>"
             style="<?= $filter === $key ? "background:{$tab['color']};border-color:{$tab['color']};" : '' ?>">
            <?= e($tab['label']) ?>
            <span style="font-family:var(--font-mono);font-size:.65rem;<?= $filter !== $key ? "color:var(--text-muted);" : '' ?>">
              (<?= (int)$tab['count'] ?>)
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Bulk action form -->
    <form method="POST" id="bulk-form">
      <?= csrf_field() ?>

      <!-- Bulk toolbar -->
      <?php if ($filter === 'pending' && !empty($reviews)): ?>
      <div style="display:flex;align-items:center;gap:var(--space-sm);padding:var(--space-md);background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);margin-bottom:var(--space-md);">
        <input type="checkbox" id="select-all" style="accent-color:var(--neon-green);width:15px;height:15px;cursor:pointer;">
        <label for="select-all" style="font-family:var(--font-condensed);font-size:.85rem;font-weight:700;color:var(--text-secondary);cursor:pointer;">Select All</label>
        <div style="margin-left:auto;display:flex;gap:6px;">
          <button type="submit" name="bulk_action" value="approve"
                  class="btn btn--sm"
                  style="background:var(--neon-green-glow);color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                  onclick="return confirm('Approve selected reviews?')">
            ✓ Approve Selected
          </button>
          <button type="submit" name="bulk_action" value="reject"
                  class="btn btn--sm"
                  style="background:var(--neon-red-glow);color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                  onclick="return confirm('Reject selected reviews?')">
            ✗ Reject Selected
          </button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Review cards -->
      <?php if (empty($reviews)): ?>
        <div class="empty-state">
          <span class="empty-state__icon"><?= $filter==='pending' ? '✅' : '📭' ?></span>
          <div class="empty-state__title">
            <?= $filter==='pending' ? 'Queue is clear!' : 'No '.e($filter).' reviews.' ?>
          </div>
          <p><?= $filter==='pending' ? 'No submissions waiting for review.' : 'Nothing here yet.' ?></p>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:var(--space-md);">
          <?php foreach ($reviews as $r): ?>
          <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);overflow:hidden;transition:border-color .2s;"
               onmouseover="this.style.borderColor='var(--border-default)'"
               onmouseout="this.style.borderColor='var(--border-subtle)'">
            <div style="display:grid;grid-template-columns:220px 1fr auto;gap:0;">

              <!-- Thumbnail -->
              <div style="position:relative;overflow:hidden;background:var(--bg-surface);">
                <img src="<?= UPLOAD_URL . e($r['image_path']) ?>"
                     alt="<?= e($r['title']) ?>"
                     style="width:100%;height:100%;object-fit:cover;min-height:140px;"
                     onerror="this.style.display='none'">
                <div style="position:absolute;top:8px;left:8px;">
                  <?php if ($filter === 'pending'): ?>
                  <input type="checkbox" name="bulk_ids[]" value="<?= (int)$r['id'] ?>"
                         class="bulk-checkbox"
                         style="accent-color:var(--neon-green);width:16px;height:16px;cursor:pointer;">
                  <?php endif; ?>
                </div>
                <div style="position:absolute;bottom:8px;right:8px;">
                  <span style="font-family:var(--font-display);font-size:.85rem;padding:2px 8px;background:var(--neon-amber);color:var(--text-inverse);border-radius:99px;">
                    <?= (int)$r['incident_min'] ?>'
                  </span>
                </div>
              </div>

              <!-- Content -->
              <div style="padding:var(--space-lg);">
                <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-sm);flex-wrap:wrap;">
                  <span class="badge badge--amber"><?= e($r['competition']) ?></span>
                  <span class="badge badge--gray">ID #<?= (int)$r['id'] ?></span>
                  <?php
                  $status_badge = ['pending'=>'badge--amber','approved'=>'badge--green','rejected'=>'badge--red'];
                  ?>
                  <span class="badge <?= $status_badge[$r['status']] ?? 'badge--gray' ?>">
                    <?= strtoupper($r['status']) ?>
                  </span>
                </div>

                <h3 style="font-family:var(--font-display);font-size:1.3rem;letter-spacing:.03em;color:var(--text-primary);margin-bottom:6px;line-height:1.1;">
                  <?= e($r['title']) ?>
                </h3>
                <div style="font-family:var(--font-condensed);font-size:.85rem;color:var(--text-secondary);font-weight:600;margin-bottom:var(--space-sm);">
                  <?= e($r['match_name']) ?>
                </div>
                <p style="font-size:.875rem;color:var(--text-muted);line-height:1.6;margin-bottom:var(--space-sm);">
                  <?= e(truncate($r['description'], 160)) ?>
                </p>
                <div style="display:flex;align-items:center;gap:var(--space-md);font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
                  <span>by <strong style="color:var(--text-secondary);"><?= e($r['author']) ?></strong></span>
                  <span>🕐 <?= time_ago($r['created_at']) ?></span>
                  <span>🗳 <?= number_format((int)$r['total_votes']) ?> votes</span>
                  <span>💬 <?= (int)$r['comment_count'] ?> comments</span>
                </div>
              </div>

              <!-- Action buttons -->
              <div style="padding:var(--space-lg);display:flex;flex-direction:column;gap:var(--space-sm);justify-content:center;border-left:1px solid var(--border-subtle);min-width:150px;">
                <a href="<?= SITE_URL ?>/review.php?id=<?= (int)$r['id'] ?>"
                   class="btn btn--ghost btn--sm" target="_blank">
                  👁 Preview
                </a>

                <?php if ($r['status'] === 'pending'): ?>
                  <a href="?action=approve&id=<?= (int)$r['id'] ?>&token=<?= csrf_token() ?>&filter=<?= $filter ?>"
                     class="btn btn--sm"
                     style="background:var(--neon-green-glow);color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                     onclick="return confirm('Approve: \'<?= addslashes(truncate($r['title'],40)) ?>\'?')">
                    ✓ Approve
                  </a>
                  <a href="?action=reject&id=<?= (int)$r['id'] ?>&token=<?= csrf_token() ?>&filter=<?= $filter ?>"
                     class="btn btn--sm"
                     style="background:var(--neon-red-glow);color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                     onclick="return confirm('Reject: \'<?= addslashes(truncate($r['title'],40)) ?>\'?')">
                    ✗ Reject
                  </a>
                <?php elseif ($r['status'] === 'approved'): ?>
                  <a href="?action=reject&id=<?= (int)$r['id'] ?>&token=<?= csrf_token() ?>&filter=<?= $filter ?>"
                     class="btn btn--sm"
                     style="background:var(--neon-red-glow);color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                     onclick="return confirm('Un-publish this review?')">
                    ✗ Unpublish
                  </a>
                <?php elseif ($r['status'] === 'rejected'): ?>
                  <a href="?action=approve&id=<?= (int)$r['id'] ?>&token=<?= csrf_token() ?>&filter=<?= $filter ?>"
                     class="btn btn--sm"
                     style="background:var(--neon-green-glow);color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                     onclick="return confirm('Re-approve this review?')">
                    ✓ Re-approve
                  </a>
                <?php endif; ?>
              </div>

            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="pagination" style="margin-top:var(--space-xl);">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?filter=<?= $filter ?>&page=<?= $p ?>"
           class="pagination__btn <?= $p === $page ? 'active' : '' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>

  </main>
</div>

<script>
/* Select all checkboxes */
document.getElementById('select-all')?.addEventListener('change', function() {
  document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = this.checked);
});
document.querySelectorAll('.bulk-checkbox').forEach(cb => {
  cb.addEventListener('change', () => {
    const all  = document.querySelectorAll('.bulk-checkbox');
    const chkd = document.querySelectorAll('.bulk-checkbox:checked');
    const sa   = document.getElementById('select-all');
    if (sa) sa.indeterminate = chkd.length > 0 && chkd.length < all.length;
    if (sa) sa.checked = chkd.length === all.length;
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

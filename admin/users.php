<?php
// ============================================================
//  VAR ROOM — Admin: User Management
//  admin/users.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// ── Handle ban / unban / promote ──────────────────────────
$action  = $_GET['action'] ?? '';
$item_id = (int)($_GET['id']    ?? 0);
$token   = $_GET['token'] ?? '';

if (in_array($action, ['ban','unban','make_mod','make_user']) && $item_id > 0) {
    if (!hash_equals(csrf_token(), $token)) {
        set_flash('error', 'Security token invalid.');
        redirect(SITE_URL . '/admin/users.php');
    }

    // Protect the master admin account (id=1)
    if ($item_id === 1) {
        set_flash('error', 'The primary admin account cannot be modified.');
        redirect(SITE_URL . '/admin/users.php');
    }

    switch ($action) {
        case 'ban':
            db_execute('UPDATE users SET is_banned = 1 WHERE id = ?', [$item_id]);
            set_flash('success', 'User has been banned.');
            break;
        case 'unban':
            db_execute('UPDATE users SET is_banned = 0 WHERE id = ?', [$item_id]);
            set_flash('success', 'User has been unbanned.');
            break;
        case 'make_mod':
            db_execute("UPDATE users SET role = 'moderator' WHERE id = ? AND role = 'user'", [$item_id]);
            set_flash('success', 'User promoted to Moderator.');
            break;
        case 'make_user':
            db_execute("UPDATE users SET role = 'user' WHERE id = ? AND role = 'moderator'", [$item_id]);
            set_flash('success', 'Moderator demoted to User.');
            break;
    }
    redirect(SITE_URL . '/admin/users.php');
}

// ── Search & paginate ──────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = 'WHERE 1=1';
$params = [];

if ($search) {
    $where   .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$total = (int) db_fetch_one(
    "SELECT COUNT(*) AS n FROM users $where", $params
)['n'];
$total_pages = (int) ceil($total / $limit);

$users = db_fetch_all(
    "SELECT u.*,
            COUNT(DISTINCT mr.id) AS review_count,
            COUNT(DISTINCT v.id)  AS vote_count
     FROM users u
     LEFT JOIN match_reviews mr ON u.id = mr.user_id
     LEFT JOIN votes v          ON u.id = v.user_id
     $where
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$page_title  = 'User Management';
$active_page = 'admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div style="font-family:var(--font-mono);font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:var(--space-lg);padding-left:14px;">Admin Panel</div>
    <nav>
      <a href="<?= SITE_URL ?>/admin/index.php"    class="admin-nav-item">📊 Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/moderate.php" class="admin-nav-item">⏳ Review Queue</a>
      <a href="<?= SITE_URL ?>/admin/users.php"    class="admin-nav-item active">👥 Users</a>
      <a href="<?= SITE_URL ?>/admin/reviews.php"  class="admin-nav-item">📋 All Reviews</a>
      <a href="<?= SITE_URL ?>/admin/comments.php" class="admin-nav-item">💬 Comments</a>
      <div style="height:1px;background:var(--border-subtle);margin:var(--space-md) 0;"></div>
      <a href="<?= SITE_URL ?>/index.php"          class="admin-nav-item">🏠 View Site</a>
      <a href="<?= SITE_URL ?>/auth/logout.php"    class="admin-nav-item" style="color:var(--neon-red);">🚪 Logout</a>
    </nav>
  </aside>

  <main class="admin-main">

    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-xl);flex-wrap:wrap;gap:var(--space-md);">
      <div>
        <div class="section-header__eyebrow">Community</div>
        <h1 style="font-size:2.4rem;">User Management</h1>
      </div>
      <!-- Search -->
      <form method="GET" style="display:flex;gap:8px;">
        <input type="text" name="q" value="<?= e($search) ?>"
               class="form-control" placeholder="Search username or email…"
               style="width:260px;">
        <button type="submit" class="btn btn--primary btn--sm">Search</button>
        <?php if ($search): ?>
          <a href="?" class="btn btn--ghost btn--sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($search): ?>
      <p style="margin-bottom:var(--space-md);font-size:.875rem;color:var(--text-muted);">
        Showing <?= $total ?> result(s) for "<strong style="color:var(--text-primary);"><?= e($search) ?></strong>"
      </p>
    <?php endif; ?>

    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-lg);overflow:hidden;">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Reviews</th>
            <th>Votes</th>
            <th>Joined</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:.75rem;color:var(--text-muted);"><?= (int)$u['id'] ?></td>
            <td>
              <div style="font-family:var(--font-condensed);font-size:.9rem;font-weight:700;color:var(--text-primary);">
                <?= e($u['username']) ?>
              </div>
            </td>
            <td style="font-size:.82rem;color:var(--text-secondary);"><?= e($u['email']) ?></td>
            <td>
              <?php $rc = ['admin'=>'badge--amber','moderator'=>'badge--green','user'=>'badge--gray'][$u['role']] ?? 'badge--gray'; ?>
              <span class="badge <?= $rc ?>"><?= strtoupper($u['role']) ?></span>
            </td>
            <td style="font-family:var(--font-mono);font-size:.82rem;text-align:center;"><?= (int)$u['review_count'] ?></td>
            <td style="font-family:var(--font-mono);font-size:.82rem;text-align:center;"><?= (int)$u['vote_count'] ?></td>
            <td style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
              <?= date('d M Y', strtotime($u['created_at'])) ?>
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
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <?php if (!(int)$u['is_banned']): ?>
                  <a href="?action=ban&id=<?= (int)$u['id'] ?>&token=<?= csrf_token() ?>"
                     class="btn btn--sm" style="font-size:.68rem;padding:3px 8px;color:var(--neon-red);border-color:rgba(255,45,85,.3);"
                     onclick="return confirm('Ban <?= e($u['username']) ?>?')">Ban</a>
                <?php else: ?>
                  <a href="?action=unban&id=<?= (int)$u['id'] ?>&token=<?= csrf_token() ?>"
                     class="btn btn--sm" style="font-size:.68rem;padding:3px 8px;color:var(--neon-green);border-color:rgba(0,232,122,.3);"
                     onclick="return confirm('Unban <?= e($u['username']) ?>?')">Unban</a>
                <?php endif; ?>

                <?php if ($u['role'] === 'user'): ?>
                  <a href="?action=make_mod&id=<?= (int)$u['id'] ?>&token=<?= csrf_token() ?>"
                     class="btn btn--sm" style="font-size:.68rem;padding:3px 8px;color:var(--neon-amber);border-color:rgba(255,184,0,.3);"
                     onclick="return confirm('Promote <?= e($u['username']) ?> to Moderator?')">+Mod</a>
                <?php elseif ($u['role'] === 'moderator'): ?>
                  <a href="?action=make_user&id=<?= (int)$u['id'] ?>&token=<?= csrf_token() ?>"
                     class="btn btn--sm" style="font-size:.68rem;padding:3px 8px;color:var(--text-muted);border-color:var(--border-default);"
                     onclick="return confirm('Demote <?= e($u['username']) ?> to User?')">−Mod</a>
                <?php endif; ?>
              </div>
              <?php else: ?>
                <span style="font-size:.72rem;color:var(--text-muted);">Protected</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:var(--space-xl);">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="pagination" style="margin-top:var(--space-xl);">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>"
           class="pagination__btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

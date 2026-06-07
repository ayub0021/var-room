<?php
// ============================================================
//  VAR ROOM — Forgot Password (stub)
//  auth/forgot.php
//
//  Full email-reset flow requires a mail server (e.g. SMTP).
//  This stub gives users a clear message and a path forward.
//  To fully implement: use PHPMailer + a password_resets table.
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) redirect(SITE_URL . '/index.php');

$sent   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        // Always show "sent" — prevents email enumeration
        $sent = true;
        // TODO: generate token, store in DB, email the reset link
    }
}

$page_title  = 'Forgot Password';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';
?>

<main style="min-height:calc(100vh - var(--nav-height));display:flex;align-items:center;justify-content:center;padding:var(--space-xl) var(--space-md);">
  <div style="width:100%;max-width:420px;">

    <div style="text-align:center;margin-bottom:var(--space-xl);">
      <div style="font-family:var(--font-mono);font-size:.72rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--neon-amber);margin-bottom:var(--space-sm);display:flex;align-items:center;justify-content:center;gap:6px;">
        <span style="width:20px;height:2px;background:var(--neon-amber);display:inline-block;border-radius:1px;"></span>
        Password Reset
        <span style="width:20px;height:2px;background:var(--neon-amber);display:inline-block;border-radius:1px;"></span>
      </div>
      <h1 style="font-size:2.2rem;margin-bottom:var(--space-sm);">Forgot Password?</h1>
      <p>Enter your email and we'll send reset instructions.</p>
    </div>

    <div style="background:var(--bg-card);border:1px solid var(--border-default);border-radius:var(--radius-lg);padding:var(--space-xl);">
      <?php if ($sent): ?>
        <div class="flash flash--success">
          <span>✓ If that email is registered, a reset link is on its way.</span>
        </div>
        <p style="text-align:center;margin-top:var(--space-lg);">
          <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn--ghost">Back to Login</a>
        </p>
      <?php else: ?>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" autofocus required>
            <?php if (isset($errors['email'])): ?>
              <div class="form-error">⚠ <?= e($errors['email']) ?></div>
            <?php endif; ?>
          </div>
          <button type="submit" class="btn btn--primary" style="width:100%;padding:14px;">Send Reset Link</button>
        </form>
        <p style="text-align:center;margin-top:var(--space-lg);font-size:.875rem;">
          <a href="<?= SITE_URL ?>/auth/login.php" style="color:var(--text-muted);">← Back to Login</a>
        </p>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

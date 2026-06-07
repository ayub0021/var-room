<?php
// ============================================================
//  VAR ROOM — Registration Page
//  auth/register.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in → go home
if (is_logged_in()) redirect(SITE_URL . '/index.php');

$errors = [];
$values = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username  = trim($_POST['username']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password']  ?? '';
    $confirm   = $_POST['confirm']   ?? '';

    $values['username'] = $username;
    $values['email']    = $email;

    // ── Validate username ──────────────────────────────────
    if (empty($username)) {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors['username'] = '3–30 chars. Letters, numbers, underscores only.';
    } else {
        $exists = db_fetch_one('SELECT id FROM users WHERE username = ?', [$username]);
        if ($exists) $errors['username'] = 'That username is already taken.';
    }

    // ── Validate email ─────────────────────────────────────
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $exists = db_fetch_one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($exists) $errors['email'] = 'An account with this email already exists.';
    }

    // ── Validate password ──────────────────────────────────
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Include at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Include at least one number.';
    }

    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    // ── Create account ─────────────────────────────────────
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = db_insert(
            'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
            [$username, $email, $hash]
        );

        // Log them in immediately
        $user = db_fetch_one('SELECT * FROM users WHERE id = ?', [$userId]);
        login_user($user);

        set_flash('success', "Welcome to VAR Room, {$username}! Start debating.");
        redirect(SITE_URL . '/index.php');
    }
}

$page_title  = 'Create Account';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';
?>

<main style="min-height:calc(100vh - var(--nav-height));display:flex;align-items:center;justify-content:center;padding:var(--space-xl) var(--space-md);">
  <div style="width:100%;max-width:440px;">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:var(--space-xl);">
      <div style="font-family:var(--font-mono);font-size:.72rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--neon-green);margin-bottom:var(--space-sm);display:flex;align-items:center;justify-content:center;gap:6px;">
        <span style="width:20px;height:2px;background:var(--neon-green);display:inline-block;border-radius:1px;"></span>
        New Account
        <span style="width:20px;height:2px;background:var(--neon-green);display:inline-block;border-radius:1px;"></span>
      </div>
      <h1 style="font-size:2.6rem;margin-bottom:var(--space-sm);">Join the Debate</h1>
      <p>Create your VAR Room account and start voting on football's most controversial decisions.</p>
    </div>

    <!-- Form Card -->
    <div style="background:var(--bg-card);border:1px solid var(--border-default);border-radius:var(--radius-lg);padding:var(--space-xl);">

      <form method="POST" action="" novalidate>
        <?= csrf_field() ?>

        <!-- Username -->
        <div class="form-group">
          <label class="form-label" for="username">
            Username <span class="required">*</span>
          </label>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control <?= isset($errors['username']) ? 'error' : '' ?>"
            value="<?= e($values['username']) ?>"
            placeholder="e.g. FootballFan99"
            maxlength="30"
            autocomplete="username"
            required
          >
          <?php if (isset($errors['username'])): ?>
            <div class="form-error">⚠ <?= e($errors['username']) ?></div>
          <?php else: ?>
            <div class="form-help">Letters, numbers, underscores. 3–30 characters.</div>
          <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email">
            Email Address <span class="required">*</span>
          </label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
            value="<?= e($values['email']) ?>"
            placeholder="you@example.com"
            autocomplete="email"
            required
          >
          <?php if (isset($errors['email'])): ?>
            <div class="form-error">⚠ <?= e($errors['email']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label" for="password">
            Password <span class="required">*</span>
          </label>
          <div style="position:relative;">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control <?= isset($errors['password']) ? 'error' : '' ?>"
              placeholder="Min. 8 chars, 1 uppercase, 1 number"
              autocomplete="new-password"
              required
            >
            <button type="button" class="btn btn--icon" id="toggle-password"
              style="position:absolute;right:8px;top:50%;transform:translateY(-50%);color:var(--text-muted);"
              aria-label="Toggle password visibility">
              👁
            </button>
          </div>

          <!-- Strength indicator -->
          <div id="strength-bar" style="margin-top:8px;height:3px;border-radius:99px;background:var(--bg-surface);overflow:hidden;">
            <div id="strength-fill" style="height:100%;width:0%;transition:width .3s,background .3s;border-radius:99px;"></div>
          </div>
          <div id="strength-label" style="font-family:var(--font-mono);font-size:.7rem;color:var(--text-muted);margin-top:4px;"></div>

          <?php if (isset($errors['password'])): ?>
            <div class="form-error">⚠ <?= e($errors['password']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Confirm -->
        <div class="form-group">
          <label class="form-label" for="confirm">
            Confirm Password <span class="required">*</span>
          </label>
          <input
            type="password"
            id="confirm"
            name="confirm"
            class="form-control <?= isset($errors['confirm']) ? 'error' : '' ?>"
            placeholder="Repeat your password"
            autocomplete="new-password"
            required
          >
          <?php if (isset($errors['confirm'])): ?>
            <div class="form-error">⚠ <?= e($errors['confirm']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Terms -->
        <div class="form-group" style="display:flex;align-items:flex-start;gap:10px;">
          <input type="checkbox" id="terms" name="terms" value="1"
            style="margin-top:3px;accent-color:var(--neon-green);width:16px;height:16px;flex-shrink:0;" required>
          <label for="terms" style="font-size:.85rem;color:var(--text-secondary);cursor:pointer;">
            I understand VAR Room is for community discussion only and agree to keep debates respectful.
          </label>
        </div>

        <button type="submit" class="btn btn--primary" style="width:100%;padding:14px;font-size:1rem;">
          Create Account
        </button>
      </form>

      <p style="text-align:center;margin-top:var(--space-lg);font-size:.875rem;color:var(--text-muted);">
        Already have an account?
        <a href="<?= SITE_URL ?>/auth/login.php" style="color:var(--neon-green);font-weight:600;">Sign In</a>
      </p>
    </div>

  </div>
</main>

<script>
/* Password show/hide */
document.getElementById('toggle-password').addEventListener('click', function() {
  const pw = document.getElementById('password');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  this.textContent = pw.type === 'password' ? '👁' : '🙈';
});

/* Password strength meter */
document.getElementById('password').addEventListener('input', function() {
  const val   = this.value;
  const fill  = document.getElementById('strength-fill');
  const label = document.getElementById('strength-label');

  let score = 0;
  if (val.length >= 8)           score++;
  if (/[A-Z]/.test(val))        score++;
  if (/[0-9]/.test(val))        score++;
  if (/[^a-zA-Z0-9]/.test(val)) score++;
  if (val.length >= 16)          score++;

  const levels = [
    { pct: 0,   color: 'transparent', text: '' },
    { pct: 20,  color: 'var(--neon-red)',   text: 'Weak' },
    { pct: 40,  color: '#ff6b00',           text: 'Fair' },
    { pct: 60,  color: 'var(--neon-amber)', text: 'Good' },
    { pct: 80,  color: 'var(--neon-green)', text: 'Strong' },
    { pct: 100, color: 'var(--neon-green)', text: 'Very Strong' },
  ];

  const lvl = levels[score] || levels[0];
  fill.style.width      = lvl.pct + '%';
  fill.style.background = lvl.color;
  label.textContent     = lvl.text;
  label.style.color     = lvl.color;
});

/* Match-confirm highlight */
document.getElementById('confirm').addEventListener('input', function() {
  const pw = document.getElementById('password').value;
  this.style.borderColor = this.value && this.value === pw
    ? 'var(--neon-green)' : '';
});

/* Auto-focus username */
document.getElementById('username').focus();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

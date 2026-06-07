<?php
// ============================================================
//  VAR ROOM — Login Page
//  auth/login.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) redirect(SITE_URL . '/index.php');

$errors = [];
$email  = '';

// ── Brute-force throttle (simple session-based) ────────────
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last']     = time();
}

// Reset counter after 15 minutes
if (time() - ($_SESSION['login_last'] ?? 0) > 900) {
    $_SESSION['login_attempts'] = 0;
}

$is_throttled = ($_SESSION['login_attempts'] ?? 0) >= 5;

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_throttled) {
    csrf_verify();

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    // Basic field checks
    if (empty($email) || empty($password)) {
        $errors['general'] = 'Please fill in both fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['general'] = 'Enter a valid email address.';
    } else {
        $user = db_fetch_one('SELECT * FROM users WHERE email = ?', [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts']++;
            $_SESSION['login_last'] = time();
            $remaining = 5 - ($_SESSION['login_attempts'] ?? 0);
            $errors['general'] = $remaining > 0
                ? "Invalid email or password. {$remaining} attempt(s) left."
                : 'Too many failed attempts. Try again in 15 minutes.';
        } elseif ((int)$user['is_banned'] === 1) {
            $errors['general'] = 'Your account has been suspended. Contact support.';
        } else {
            // Success — log in
            $_SESSION['login_attempts'] = 0;
            login_user($user);

            // Update last_login timestamp
            db_execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);

            // Remember me: store a long-lived cookie token (basic implementation)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('var_room_remember', $token, [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                // NOTE: In production, store token hash in DB linked to user.
                // For simplicity here we store user_id in the cookie value.
                // Replace with a proper token table in production.
                setcookie('var_room_uid', (string)$user['id'], [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            $redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');
            set_flash('success', "Welcome back, {$user['username']}!");
            redirect($redirect);
        }
    }
}

$page_title  = 'Sign In';
$active_page = '';
require_once __DIR__ . '/../includes/header.php';
?>

<main style="min-height:calc(100vh - var(--nav-height));display:flex;align-items:center;justify-content:center;padding:var(--space-xl) var(--space-md);">
  <div style="width:100%;max-width:420px;">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:var(--space-xl);">
      <div style="font-family:var(--font-mono);font-size:.72rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--neon-green);margin-bottom:var(--space-sm);display:flex;align-items:center;justify-content:center;gap:6px;">
        <span style="width:20px;height:2px;background:var(--neon-green);display:inline-block;border-radius:1px;"></span>
        Sign In
        <span style="width:20px;height:2px;background:var(--neon-green);display:inline-block;border-radius:1px;"></span>
      </div>
      <h1 style="font-size:2.6rem;margin-bottom:var(--space-sm);">Welcome Back</h1>
      <p>Your verdict is needed on the pitch.</p>
    </div>

    <!-- Throttle warning -->
    <?php if ($is_throttled): ?>
      <div class="flash flash--error" style="margin-bottom:var(--space-lg);">
        <span>⛔ Too many failed attempts. Please wait 15 minutes before trying again.</span>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div style="background:var(--bg-card);border:1px solid var(--border-default);border-radius:var(--radius-lg);padding:var(--space-xl);">

      <?php if (isset($errors['general'])): ?>
        <div class="flash flash--error" style="margin-bottom:var(--space-lg);">
          <span>⚠ <?= e($errors['general']) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate <?= $is_throttled ? 'style="pointer-events:none;opacity:.5;"' : '' ?>>
        <?= csrf_field() ?>

        <?php
        // Pass redirect URL through the form
        if (!empty($_GET['redirect'])) {
            echo '<input type="hidden" name="redirect" value="' . e($_GET['redirect']) . '">';
        }
        ?>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            value="<?= e($email) ?>"
            placeholder="you@example.com"
            autocomplete="email"
            required
            autofocus
          >
        </div>

        <!-- Password -->
        <div class="form-group">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <label class="form-label" for="password" style="margin:0;">Password</label>
            <a href="<?= SITE_URL ?>/auth/forgot.php"
               style="font-size:.8rem;color:var(--text-muted);transition:color .15s;"
               onmouseover="this.style.color='var(--neon-green)'"
               onmouseout="this.style.color='var(--text-muted)'">
              Forgot password?
            </a>
          </div>
          <div style="position:relative;">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Your password"
              autocomplete="current-password"
              required
            >
            <button type="button" id="toggle-pw"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1rem;padding:4px;"
              aria-label="Toggle password visibility">👁</button>
          </div>
        </div>

        <!-- Remember me -->
        <div class="form-group" style="display:flex;align-items:center;gap:10px;">
          <input type="checkbox" id="remember" name="remember" value="1"
            style="accent-color:var(--neon-green);width:16px;height:16px;cursor:pointer;">
          <label for="remember" style="font-size:.875rem;color:var(--text-secondary);cursor:pointer;margin:0;">
            Keep me signed in for 30 days
          </label>
        </div>

        <button type="submit" class="btn btn--primary" style="width:100%;padding:14px;font-size:1rem;"
          <?= $is_throttled ? 'disabled' : '' ?>>
          Sign In
        </button>
      </form>

      <!-- Divider -->
      <div style="display:flex;align-items:center;gap:12px;margin:var(--space-lg) 0;">
        <div style="flex:1;height:1px;background:var(--border-subtle);"></div>
        <span style="font-size:.78rem;color:var(--text-muted);font-family:var(--font-mono);">OR</span>
        <div style="flex:1;height:1px;background:var(--border-subtle);"></div>
      </div>

      <p style="text-align:center;font-size:.875rem;color:var(--text-muted);">
        Don't have an account?
        <a href="<?= SITE_URL ?>/auth/register.php" style="color:var(--neon-green);font-weight:600;">
          Join VAR Room Free
        </a>
      </p>
    </div>

    <!-- Admin hint (only shown in development) -->
    <?php if (($_SERVER['SERVER_NAME'] ?? '') === 'localhost'): ?>
    <div style="margin-top:var(--space-lg);padding:var(--space-md);background:var(--bg-surface);border:1px dashed var(--border-default);border-radius:var(--radius-sm);">
      <p style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);margin-bottom:4px;">DEV — Default admin login:</p>
      <p style="font-family:var(--font-mono);font-size:.78rem;color:var(--neon-amber);">admin@varroom.com / Admin@123</p>
    </div>
    <?php endif; ?>

  </div>
</main>

<script>
/* Toggle password visibility */
document.getElementById('toggle-pw').addEventListener('click', function () {
  const pw = document.getElementById('password');
  const isHidden = pw.type === 'password';
  pw.type = isHidden ? 'text' : 'password';
  this.textContent = isHidden ? '🙈' : '👁';
});

/* Highlight email field if it came from a failed attempt */
<?php if (!empty($email)): ?>
document.getElementById('password').focus();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

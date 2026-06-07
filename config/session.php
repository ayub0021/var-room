<?php
// ============================================================
//  VAR ROOM — Session Management
//  config/session.php
// ============================================================

// Harden PHP session cookies
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
// ini_set('session.cookie_secure', 1); // Uncomment on HTTPS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Auth helpers ────────────────────────────────────────── */

/** Check if the current visitor is logged in */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/** Redirect to login page if not authenticated */
function require_login(string $redirect = '/var-room/auth/login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/** Check if logged-in user is an admin */
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** Check if logged-in user is admin or moderator */
function is_moderator(): bool {
    return is_logged_in() && in_array($_SESSION['role'] ?? '', ['admin', 'moderator']);
}

/** Redirect admins-only pages */
function require_admin(): void {
    if (!is_admin()) {
        header('Location: /var-room/index.php?error=unauthorized');
        exit;
    }
}

/** Log a user in — call after verifying credentials */
function login_user(array $user): void {
    session_regenerate_id(true);   // prevent session fixation
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['avatar']   = $user['avatar'];
}

/** Destroy session and log out */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Return current user's ID or null */
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/** Return current username or 'Guest' */
function current_username(): string {
    return $_SESSION['username'] ?? 'Guest';
}

/* ── CSRF helpers ────────────────────────────────────────── */

/** Generate (or return existing) CSRF token */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Output a hidden CSRF input field */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Validate a submitted CSRF token; die on failure */
function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'CSRF token mismatch.']));
    }
}

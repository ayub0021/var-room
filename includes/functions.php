<?php
// ============================================================
//  VAR ROOM — Global Utility Functions
//  includes/functions.php
// ============================================================

/**
 * Safely echo HTML-escaped output.
 */
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect and exit.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Handle image upload.
 * Returns ['success'=>true, 'path'=>'filename.ext'] or ['success'=>false, 'error'=>'msg']
 */
function handle_image_upload(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['success' => false, 'error' => 'File exceeds ' . UPLOAD_MAX_MB . 'MB limit.'];
    }

    // Validate MIME type using finfo (not $_FILES['type'], which can be spoofed)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES, true)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF.'];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTS, true)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    // Generate a unique, safe filename
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Failed to save file. Check folder permissions.'];
    }

    return ['success' => true, 'path' => $filename];
}

/**
 * Calculate vote percentages.
 * Returns ['correct_pct'=>int, 'wrong_pct'=>int, 'total'=>int]
 */
function calc_vote_pct(int $correct, int $wrong): array {
    $total = $correct + $wrong;
    if ($total === 0) {
        return ['correct_pct' => 0, 'wrong_pct' => 0, 'total' => 0];
    }
    $c = (int) round(($correct / $total) * 100);
    return [
        'correct_pct' => $c,
        'wrong_pct'   => 100 - $c,
        'total'       => $total,
    ];
}

/**
 * Time-ago string (e.g. "3 hours ago").
 */
function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    return match(true) {
        $diff < 60       => 'just now',
        $diff < 3600     => (int)($diff / 60)   . ' min ago',
        $diff < 86400    => (int)($diff / 3600)  . ' hr ago',
        $diff < 604800   => (int)($diff / 86400) . ' days ago',
        default          => date('M j, Y', strtotime($datetime)),
    };
}

/**
 * Truncate text to a given length.
 */
function truncate(string $text, int $limit = 120): string {
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '…';
}

/**
 * Flash message system.
 * set_flash('success', 'Saved!') — then get_flash('success')
 */
function set_flash(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function get_flash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/**
 * Output flash messages as dismissible HTML alerts.
 */
function render_flash(): void {
    foreach (['success', 'error', 'info'] as $type) {
        $msg = get_flash($type);
        if ($msg) {
            echo '<div class="flash flash--' . $type . '">';
            echo '<span>' . e($msg) . '</span>';
            echo '<button class="flash__close" onclick="this.parentElement.remove()">&#x2715;</button>';
            echo '</div>';
        }
    }
}

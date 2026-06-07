<?php
// ============================================================
//  VAR ROOM — AJAX Comment Handler
//  ajax/comment.php
//
//  POST JSON actions:
//    post:   { action:"post",   review_id, body, csrf_token }
//    delete: { action:"delete", comment_id, csrf_token }
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed.']));
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid JSON.']));
}

// CSRF
if (!hash_equals(csrf_token(), $body['csrf_token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Security token mismatch.']));
}

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Sign in to comment.']));
}

$action = $body['action'] ?? 'post';

// ══════════════════════════════════════════════════════════
//  ACTION: POST COMMENT
// ══════════════════════════════════════════════════════════
if ($action === 'post') {

    $review_id  = (int)($body['review_id'] ?? 0);
    $comment_body = trim($body['body'] ?? '');

    if ($review_id < 1) {
        die(json_encode(['success' => false, 'error' => 'Invalid review.']));
    }

    if (mb_strlen($comment_body) < 1) {
        die(json_encode(['success' => false, 'error' => 'Comment cannot be empty.']));
    }

    if (mb_strlen($comment_body) > 1000) {
        die(json_encode(['success' => false, 'error' => 'Comment is too long (max 1000 chars).']));
    }

    // Confirm review is approved
    $review = db_fetch_one(
        "SELECT id FROM match_reviews WHERE id = ? AND status = 'approved'",
        [$review_id]
    );
    if (!$review) {
        die(json_encode(['success' => false, 'error' => 'Review not found.']));
    }

    // Simple rate limit: max 5 comments per minute per user
    $recent = db_fetch_one(
        "SELECT COUNT(*) AS n FROM comments
         WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 MINUTE",
        [current_user_id()]
    );
    if ((int)$recent['n'] >= 5) {
        die(json_encode(['success' => false, 'error' => 'Slow down! Max 5 comments per minute.']));
    }

    $comment_id = db_insert(
        'INSERT INTO comments (review_id, user_id, body) VALUES (?, ?, ?)',
        [$review_id, current_user_id(), $comment_body]
    );

    // Return comment data so JS can prepend it
    echo json_encode([
        'success' => true,
        'comment' => [
            'id'       => (int)$comment_id,
            'body'     => $comment_body,
            'username' => current_username(),
            'avatar'   => $_SESSION['avatar'] ?? 'default_avatar.svg',
        ],
    ]);

// ══════════════════════════════════════════════════════════
//  ACTION: DELETE COMMENT
// ══════════════════════════════════════════════════════════
} elseif ($action === 'delete') {

    $comment_id = (int)($body['comment_id'] ?? 0);
    if ($comment_id < 1) {
        die(json_encode(['success' => false, 'error' => 'Invalid comment ID.']));
    }

    $comment = db_fetch_one(
        'SELECT id, user_id FROM comments WHERE id = ? AND is_deleted = 0',
        [$comment_id]
    );

    if (!$comment) {
        die(json_encode(['success' => false, 'error' => 'Comment not found.']));
    }

    // Only owner, moderator, or admin can delete
    $can_delete = is_moderator() || ((int)$comment['user_id'] === current_user_id());
    if (!$can_delete) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Permission denied.']));
    }

    db_execute('UPDATE comments SET is_deleted = 1 WHERE id = ?', [$comment_id]);

    echo json_encode(['success' => true]);

} else {
    die(json_encode(['success' => false, 'error' => 'Unknown action.']));
}

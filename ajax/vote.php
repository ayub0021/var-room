<?php
// ============================================================
//  VAR ROOM — AJAX Vote Handler
//  ajax/vote.php
//
//  Expects JSON POST:
//    { review_id: int, vote_type: "correct"|"wrong", csrf_token: string }
//
//  Returns JSON:
//    { success: bool, correct_pct, wrong_pct, correct_votes,
//      wrong_votes, total, user_vote, error? }
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ── Only accept POST ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed.']));
}

// ── Parse JSON body ────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid JSON.']));
}

// ── Verify CSRF ────────────────────────────────────────────
if (!hash_equals(csrf_token(), $body['csrf_token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Security token mismatch. Refresh the page.']));
}

// ── Must be logged in ──────────────────────────────────────
if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'You must be signed in to vote.']));
}

// ── Validate inputs ────────────────────────────────────────
$review_id = (int)($body['review_id'] ?? 0);
$vote_type = $body['vote_type'] ?? '';

if ($review_id < 1) {
    die(json_encode(['success' => false, 'error' => 'Invalid review ID.']));
}

if (!in_array($vote_type, ['correct', 'wrong'], true)) {
    die(json_encode(['success' => false, 'error' => 'Vote type must be "correct" or "wrong".']));
}

// ── Verify review exists and is approved ───────────────────
$review = db_fetch_one(
    "SELECT id FROM match_reviews WHERE id = ? AND status = 'approved'",
    [$review_id]
);

if (!$review) {
    die(json_encode(['success' => false, 'error' => 'Review not found or not approved.']));
}

// ── Check for existing vote ────────────────────────────────
$user_id      = current_user_id();
$existing     = db_fetch_one(
    'SELECT id, vote_type FROM votes WHERE review_id = ? AND user_id = ?',
    [$review_id, $user_id]
);

if ($existing) {
    // Already voted — return current counts with a clear message
    $counts = _get_vote_counts($review_id);
    $pct    = calc_vote_pct($counts['correct'], $counts['wrong']);
    die(json_encode([
        'success'       => false,
        'error'         => 'You have already voted on this controversy.',
        'correct_pct'   => $pct['correct_pct'],
        'wrong_pct'     => $pct['wrong_pct'],
        'correct_votes' => $counts['correct'],
        'wrong_votes'   => $counts['wrong'],
        'total'         => $pct['total'],
        'user_vote'     => $existing['vote_type'],
    ]));
}

// ── Record the vote ────────────────────────────────────────
try {
    db_insert(
        'INSERT INTO votes (review_id, user_id, vote_type) VALUES (?, ?, ?)',
        [$review_id, $user_id, $vote_type]
    );
} catch (PDOException $e) {
    // UNIQUE constraint violation — race condition, already voted
    error_log('[VAR ROOM vote] ' . $e->getMessage());
    die(json_encode(['success' => false, 'error' => 'Vote could not be recorded. You may have already voted.']));
}

// ── Return fresh counts ────────────────────────────────────
$counts = _get_vote_counts($review_id);
$pct    = calc_vote_pct($counts['correct'], $counts['wrong']);

echo json_encode([
    'success'       => true,
    'correct_pct'   => $pct['correct_pct'],
    'wrong_pct'     => $pct['wrong_pct'],
    'correct_votes' => $counts['correct'],
    'wrong_votes'   => $counts['wrong'],
    'total'         => $pct['total'],
    'user_vote'     => $vote_type,
]);

// ── Helper: fetch current vote counts ─────────────────────
function _get_vote_counts(int $review_id): array {
    $row = db_fetch_one(
        "SELECT
            COALESCE(SUM(vote_type = 'correct'), 0) AS correct,
            COALESCE(SUM(vote_type = 'wrong'),   0) AS wrong
         FROM votes
         WHERE review_id = ?",
        [$review_id]
    );
    return [
        'correct' => (int)($row['correct'] ?? 0),
        'wrong'   => (int)($row['wrong']   ?? 0),
    ];
}

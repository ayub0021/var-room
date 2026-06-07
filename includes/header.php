<?php
// ============================================================
//  VAR ROOM — Shared Header / Navigation
//  includes/header.php
//
//  Usage: require_once __DIR__ . '/../includes/header.php';
//  Before including, optionally set:
//    $page_title  = 'My Page';
//    $active_page = 'home';   // home | upload | trending | admin
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title  = $page_title  ?? SITE_NAME;
$active_page = $active_page ?? 'home';

// Fetch site-wide stats for the hero (cached in session for 60 s)
if (!isset($_SESSION['site_stats']) || time() - ($_SESSION['site_stats_time'] ?? 0) > 60) {
    $_SESSION['site_stats'] = db_fetch_one(
        "SELECT
            (SELECT COUNT(*) FROM match_reviews WHERE status='approved') AS total_reviews,
            (SELECT COUNT(*) FROM votes)    AS total_votes,
            (SELECT COUNT(*) FROM users)    AS total_users"
    );
    $_SESSION['site_stats_time'] = time();
}
$stats = $_SESSION['site_stats'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="VAR Room — The community platform for debating football's most controversial referee and VAR decisions.">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title><?= e($page_title) ?> | VAR Room</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">

  <!-- Stylesheet -->
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ── Navigation ──────────────────────────────────────────── -->
<nav class="nav" role="navigation" aria-label="Main navigation">
  <div class="nav__inner">

    <!-- Logo -->
    <a href="<?= SITE_URL ?>/index.php" class="nav__logo" aria-label="VAR Room home">
      <div class="nav__logo-icon" aria-hidden="true">VR</div>
      <span class="nav__logo-text">VAR <span>Room</span></span>
    </a>

    <!-- Desktop links -->
    <ul class="nav__links" role="list">
      <li>
        <a href="<?= SITE_URL ?>/index.php"
           class="nav__link <?= $active_page === 'home'     ? 'active' : '' ?>">
          Home
        </a>
      </li>
      <li>
        <a href="<?= SITE_URL ?>/index.php?sort=trending"
           class="nav__link <?= $active_page === 'trending' ? 'active' : '' ?>">
          Trending
        </a>
      </li>
      <li>
        <a href="<?= SITE_URL ?>/index.php?sort=latest"
           class="nav__link <?= $active_page === 'latest'   ? 'active' : '' ?>">
          Latest
        </a>
      </li>
      <?php if (is_logged_in()): ?>
      <li>
        <a href="<?= SITE_URL ?>/upload.php"
           class="nav__link <?= $active_page === 'upload'   ? 'active' : '' ?>">
          Submit
        </a>
      </li>
      <?php endif; ?>
      <?php if (is_admin()): ?>
      <li>
        <a href="<?= SITE_URL ?>/admin/index.php"
           class="nav__link <?= $active_page === 'admin'    ? 'active' : '' ?>">
          Admin
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <!-- Right side -->
    <div class="nav__right">

      <!-- Live indicator -->
      <div class="nav__live" aria-label="Live voting active">
        <span class="nav__live-dot" aria-hidden="true"></span>
        LIVE
      </div>

      <?php if (is_logged_in()): ?>
        <!-- User menu -->
        <span class="nav__user-name"><?= e(current_username()) ?></span>
        <a href="<?= SITE_URL ?>/auth/logout.php"
           class="btn btn--ghost btn--sm">Logout</a>

      <?php else: ?>
        <a href="<?= SITE_URL ?>/auth/login.php"    class="btn btn--ghost btn--sm">Login</a>
        <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn--primary btn--sm">Join</a>
      <?php endif; ?>
    </div>

    <!-- Hamburger (mobile) -->
    <button class="nav__hamburger" id="nav-hamburger"
            aria-controls="nav-mobile" aria-expanded="false" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ── Mobile nav ───────────────────────────────────────────── -->
<div class="nav__mobile" id="nav-mobile" role="dialog" aria-label="Mobile menu">
  <a href="<?= SITE_URL ?>/index.php"
     class="nav__link <?= $active_page === 'home'     ? 'active' : '' ?>">Home</a>
  <a href="<?= SITE_URL ?>/index.php?sort=trending"
     class="nav__link <?= $active_page === 'trending' ? 'active' : '' ?>">Trending</a>
  <a href="<?= SITE_URL ?>/index.php?sort=latest"
     class="nav__link <?= $active_page === 'latest'   ? 'active' : '' ?>">Latest</a>

  <?php if (is_logged_in()): ?>
    <a href="<?= SITE_URL ?>/upload.php" class="nav__link">Submit Controversy</a>
    <a href="<?= SITE_URL ?>/auth/logout.php" class="nav__link text-red">Logout</a>
  <?php else: ?>
    <a href="<?= SITE_URL ?>/auth/login.php"    class="nav__link">Login</a>
    <a href="<?= SITE_URL ?>/auth/register.php" class="nav__link text-green">Join Free</a>
  <?php endif; ?>

  <?php if (is_admin()): ?>
    <a href="<?= SITE_URL ?>/admin/index.php" class="nav__link text-amber">Admin Panel</a>
  <?php endif; ?>
</div>

<!-- ── Flash Messages ───────────────────────────────────────── -->
<div class="container" style="padding-top:12px;">
  <?php render_flash(); ?>
</div>

<!-- ── Page Content Start ───────────────────────────────────── -->

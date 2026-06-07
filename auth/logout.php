<?php
// ============================================================
//  VAR ROOM — Logout Handler
//  auth/logout.php
// ============================================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Clear remember-me cookies
setcookie('var_room_remember', '', time() - 3600, '/', '', false, true);
setcookie('var_room_uid',      '', time() - 3600, '/', '', false, true);

logout_user();

set_flash('success', 'You have been signed out.');
redirect(SITE_URL . '/index.php');

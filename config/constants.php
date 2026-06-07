<?php
// ============================================================
//  VAR ROOM — App-wide Constants
//  config/constants.php
// ============================================================

// Site identity
define('SITE_NAME',    'VAR Room');
define('SITE_URL',     'http://localhost/var-room');
define('SITE_VERSION', '1.0.0');

// File upload limits
define('UPLOAD_DIR',      __DIR__ . '/../uploads/controversies/');
define('UPLOAD_URL',      SITE_URL . '/uploads/controversies/');
define('UPLOAD_MAX_MB',   5);                                      // megabytes
define('UPLOAD_MAX_BYTES', UPLOAD_MAX_MB * 1024 * 1024);
define('ALLOWED_TYPES',   ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_EXTS',    ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Pagination
define('REVIEWS_PER_PAGE',   9);
define('COMMENTS_PER_PAGE', 20);

// Competition list (used in upload form & filters)
define('COMPETITIONS', [
    'Premier League',
    'La Liga',
    'Serie A',
    'Bundesliga',
    'Ligue 1',
    'UEFA Champions League',
    'UEFA Europa League',
    'UEFA Conference League',
    'FIFA World Cup',
    'Copa America',
    'EURO',
    'FA Cup',
    'Carabao Cup',
    'Copa del Rey',
    'DFB Pokal',
    'Other',
]);

// Incident minutes (90 min + ET)
define('MAX_INCIDENT_MIN', 120);

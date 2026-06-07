-- ============================================================
--  VAR ROOM — Database Schema
--  Run this entire file in phpMyAdmin > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS var_room
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE var_room;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar        VARCHAR(255) DEFAULT 'default_avatar.png',
    role          ENUM('user','moderator','admin') DEFAULT 'user',
    is_banned     TINYINT(1)   DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    last_login    TIMESTAMP    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: match_reviews
-- ============================================================
CREATE TABLE IF NOT EXISTS match_reviews (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         NOT NULL,
    match_name    VARCHAR(200) NOT NULL,
    competition   VARCHAR(100) NOT NULL,
    incident_min  TINYINT UNSIGNED NOT NULL,
    image_path    VARCHAR(255) NOT NULL,
    status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    views         INT UNSIGNED DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status      (status),
    INDEX idx_created     (created_at),
    INDEX idx_competition (competition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: votes
-- ============================================================
CREATE TABLE IF NOT EXISTS votes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    vote_type  ENUM('correct','wrong') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (review_id, user_id),
    FOREIGN KEY (review_id) REFERENCES match_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)         ON DELETE CASCADE,
    INDEX idx_review (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: comments
-- ============================================================
CREATE TABLE IF NOT EXISTS comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT         NOT NULL,
    is_deleted TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES match_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)         ON DELETE CASCADE,
    INDEX idx_review (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED: default admin account  (password: Admin@123)
-- ============================================================
INSERT INTO users (username, email, password_hash, role) VALUES (
    'admin',
    'admin@varroom.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);

-- ============================================================
-- VIEW: trending_reviews
-- ============================================================
CREATE OR REPLACE VIEW trending_reviews AS
SELECT
    mr.*,
    u.username                                  AS author,
    u.avatar                                    AS author_avatar,
    COUNT(DISTINCT v.id)                        AS total_votes,
    SUM(v.vote_type = 'correct')                AS correct_votes,
    SUM(v.vote_type = 'wrong')                  AS wrong_votes,
    COUNT(DISTINCT c.id)                        AS comment_count
FROM match_reviews mr
JOIN  users    u ON mr.user_id  = u.id
LEFT JOIN votes    v ON mr.id   = v.review_id
                     AND v.created_at >= NOW() - INTERVAL 7 DAY
LEFT JOIN comments c ON mr.id   = c.review_id AND c.is_deleted = 0
WHERE mr.status = 'approved'
GROUP BY mr.id
ORDER BY total_votes DESC, mr.created_at DESC;

-- ============================================================
-- VIEW: latest_reviews
-- ============================================================
CREATE OR REPLACE VIEW latest_reviews AS
SELECT
    mr.*,
    u.username                               AS author,
    u.avatar                                 AS author_avatar,
    COUNT(DISTINCT v.id)                     AS total_votes,
    SUM(v.vote_type = 'correct')             AS correct_votes,
    SUM(v.vote_type = 'wrong')               AS wrong_votes,
    COUNT(DISTINCT c.id)                     AS comment_count
FROM match_reviews mr
JOIN  users    u ON mr.user_id = u.id
LEFT JOIN votes    v ON mr.id  = v.review_id
LEFT JOIN comments c ON mr.id  = c.review_id AND c.is_deleted = 0
WHERE mr.status = 'approved'
GROUP BY mr.id
ORDER BY mr.created_at DESC;

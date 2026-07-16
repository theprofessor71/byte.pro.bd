-- Upgrade an EXISTING CyberBlogs database to the v2 feature pack.
-- Fresh installs don't need this — install.php reads schema.sql which
-- already includes everything. Run in phpMyAdmin → SQL tab.

-- posts: series / difficulty / language / draft-preview token
ALTER TABLE posts
    ADD COLUMN series VARCHAR(120) DEFAULT '' AFTER reading_time,
    ADD COLUMN series_part INT DEFAULT 0 AFTER series,
    ADD COLUMN difficulty VARCHAR(10) DEFAULT '' AFTER series_part,
    ADD COLUMN lang CHAR(2) DEFAULT 'en' AFTER difficulty,
    ADD COLUMN preview_token CHAR(32) DEFAULT '' AFTER lang;

-- admins: TOTP replay protection
ALTER TABLE admins
    ADD COLUMN totp_last_slice BIGINT NOT NULL DEFAULT 0 AFTER totp_secret;

-- helpful indexes (skip any that already exist)
ALTER TABLE posts
    ADD INDEX idx_status_created (status, created_at),
    ADD INDEX idx_category (category);
ALTER TABLE comments
    ADD FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE;

-- comment upvotes
CREATE TABLE IF NOT EXISTS comment_votes (
    comment_id INT NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, ip_hash),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- newsletter signups
CREATE TABLE IF NOT EXISTS subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- post revision history
CREATE TABLE IF NOT EXISTS post_revisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post_time (post_id, saved_at),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- give existing posts a preview token (MD5(...) = 32 hex chars, portable
-- across MySQL/MariaDB versions unlike RANDOM_BYTES)
UPDATE posts SET preview_token = MD5(CONCAT(id, RAND(), NOW())) WHERE preview_token = '';

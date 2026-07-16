-- CyberBlogs (byte.pro.bd) — Database Schema
-- MySQL/MariaDB InnoDB, utf8mb4

CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    totp_secret VARCHAR(255) DEFAULT NULL,          -- AES-256-GCM encrypted, base64
    totp_last_slice BIGINT NOT NULL DEFAULT 0,      -- last accepted TOTP time-slice (RFC 6238 replay protection)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    category VARCHAR(100) DEFAULT 'General',
    tags VARCHAR(500) DEFAULT '',
    thumbnail VARCHAR(255) DEFAULT '',
    status ENUM('draft','published') DEFAULT 'draft',
    views INT DEFAULT 0,
    reading_time INT DEFAULT 0,
    series VARCHAR(120) DEFAULT '',                 -- multi-part write-up series name
    series_part INT DEFAULT 0,                      -- 1-based part number within series
    difficulty VARCHAR(10) DEFAULT '',              -- '', easy, medium, hard, insane (CTF badge)
    lang CHAR(2) DEFAULT 'en',                      -- ISO 639-1 (en/bn)
    preview_token CHAR(32) DEFAULT '',              -- share-a-draft secret link token
    author_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES admins(id) ON DELETE SET NULL,
    FULLTEXT INDEX ft_search (title, content, tags),
    INDEX idx_status_created (status, created_at),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    parent_id INT DEFAULT NULL,                     -- threaded replies (1 level)
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(255) DEFAULT NULL,
    content TEXT NOT NULL,
    status TINYINT DEFAULT 0,                       -- 0=Pending, 1=Approved
    ip_address VARCHAR(45) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_post_status (post_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_hash VARCHAR(64) UNIQUE NOT NULL,
    real_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,                -- relative to protected_uploads/
    mime_type VARCHAR(100) DEFAULT 'application/octet-stream',
    file_size INT DEFAULT 0,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, created_at),
    INDEX idx_action_time (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_name VARCHAR(100) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily view tracking — powers the 30-day dashboard chart
CREATE TABLE IF NOT EXISTS post_views_daily (
    post_id INT NOT NULL,
    view_date DATE NOT NULL,
    views INT DEFAULT 1,
    PRIMARY KEY (post_id, view_date),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comment upvotes — same privacy model as reactions (ip_hash, no raw IP)
CREATE TABLE IF NOT EXISTS comment_votes (
    comment_id INT NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, ip_hash),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter signups (collect-only; export CSV from admin and send via any service)
CREATE TABLE IF NOT EXISTS subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post revision history — previous version saved on every edit
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

-- Post reactions (Lucide icons) — ip_hash = SHA-256(ip + secret), no raw IP stored
CREATE TABLE IF NOT EXISTS post_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    reaction VARCHAR(20) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_react (post_id, reaction, ip_hash),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

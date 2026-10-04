-- Budget Harian - skema MySQL (jalankan sekali di phpMyAdmin)
-- Semua tabel memakai prefiks bg_ agar tidak bentrok dengan aplikasi lain.

CREATE TABLE IF NOT EXISTS bg_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'viewer',
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_pw TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bg_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_auth_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bg_auth_selector (selector),
  KEY idx_bg_auth_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(50) NOT NULL,
  attempted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_bg_login_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_settings (
  k VARCHAR(50) NOT NULL,
  v TEXT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_divisions (
  code INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_uploads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  days INT NOT NULL,
  total BIGINT NOT NULL,
  replaced_days INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_bg_uploads_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_days (
  budget_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  upload_id INT UNSIGNED NULL,
  PRIMARY KEY (budget_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_budget (
  budget_date DATE NOT NULL,
  div_code INT NOT NULL,
  amount BIGINT NOT NULL,
  PRIMARY KEY (budget_date, div_code),
  KEY idx_bg_budget_div (div_code, budget_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal
INSERT IGNORE INTO bg_settings (k, v) VALUES ('store_title', 'Aeon Store MCT - 1008');

INSERT IGNORE INTO bg_divisions (code, name, sort_order, active) VALUES
(16, 'Innerware', 1, 1),
(21, 'Grocery', 2, 1),
(23, 'DND', 3, 1),
(24, 'Fish', 4, 1),
(25, 'Meat', 5, 1),
(26, 'Produce', 6, 1),
(27, 'Delica-Sushi', 7, 1),
(28, 'Bakery', 8, 1),
(31, 'Home Fashion', 9, 1),
(32, 'Dining', 10, 1),
(33, 'Electrical', 11, 1),
(35, 'Stationary', 12, 1),
(37, 'DIY', 13, 1),
(72, 'HBC-NF', 14, 1),
(81, 'Baby', 15, 1),
(83, 'Toys', 16, 1);

-- Admin awal: username admin / password admin123 (wajib ganti saat login pertama)
INSERT IGNORE INTO bg_users (username, name, password_hash, role, active, must_change_pw, created_at) VALUES
('admin', 'Administrator', '$2y$10$2O4Q9Rbb7d8ALMMDHJY6suDQ6mFOXxyDKFp5kHqjPYu31QfB8mSbK', 'admin', 1, 1, NOW());

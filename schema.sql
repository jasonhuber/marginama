-- Marginama schema (MySQL 8 / MariaDB 10.4+)
-- Run once per environment:
--   mysql -h $DB_HOST -u $DB_USER -p $DB_NAME < schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            CHAR(26)     NOT NULL PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_tokens (
  id           CHAR(26)     NOT NULL PRIMARY KEY,
  user_id      CHAR(26)     NOT NULL,
  name         VARCHAR(255) NOT NULL,
  token_hash   CHAR(64)     NOT NULL UNIQUE,
  last_used_at DATETIME     DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_tokens_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS video_reviews (
  id           CHAR(26)     NOT NULL PRIMARY KEY,
  user_id      CHAR(26)     NOT NULL,
  video_url    VARCHAR(2048) NOT NULL,
  video_title  VARCHAR(500) DEFAULT NULL,
  provider     VARCHAR(32)  NOT NULL,
  share_token  CHAR(40)     DEFAULT NULL UNIQUE,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_reviews_user_url (user_id, video_url(512))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS video_critiques (
  id            CHAR(26)     NOT NULL PRIMARY KEY,
  review_id     CHAR(26)     NOT NULL,
  timestamp_sec INT          NOT NULL,
  note          TEXT         NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_crit_review FOREIGN KEY (review_id) REFERENCES video_reviews(id) ON DELETE CASCADE,
  INDEX idx_crit_review_ts (review_id, timestamp_sec)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

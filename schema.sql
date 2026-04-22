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

CREATE TABLE IF NOT EXISTS events (
  id         CHAR(26)      NOT NULL PRIMARY KEY,
  user_id    CHAR(26)      DEFAULT NULL,
  type       VARCHAR(64)   NOT NULL,
  path       VARCHAR(512)  DEFAULT NULL,
  meta       TEXT          DEFAULT NULL,
  ip_trunc   VARCHAR(64)   DEFAULT NULL,
  ua         VARCHAR(500)  DEFAULT NULL,
  session_h  CHAR(16)      DEFAULT NULL,
  referer    VARCHAR(1024) DEFAULT NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ev_created (created_at),
  INDEX idx_ev_type (type),
  INDEX idx_ev_user_time (user_id, created_at),
  INDEX idx_ev_session (session_h)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suggestions (
  id         CHAR(26)      NOT NULL PRIMARY KEY,
  user_id    CHAR(26)      DEFAULT NULL,
  kind       VARCHAR(32)   NOT NULL DEFAULT 'other',
  body       TEXT          NOT NULL,
  page_url   VARCHAR(2048) DEFAULT NULL,
  user_agent VARCHAR(500)  DEFAULT NULL,
  status     VARCHAR(24)   NOT NULL DEFAULT 'new',
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sugg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_sugg_created (created_at),
  INDEX idx_sugg_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tags are per-user. `name` is freeform; users can encode kind as a prefix
-- (e.g. "person:alice", "topic:discovery") — no schema-level kind column.
CREATE TABLE IF NOT EXISTS tags (
  id         CHAR(26)    NOT NULL PRIMARY KEY,
  user_id    CHAR(26)    NOT NULL,
  name       VARCHAR(64) NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_tags_user_name (user_id, name),
  INDEX idx_tags_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_tags (
  review_id CHAR(26) NOT NULL,
  tag_id    CHAR(26) NOT NULL,
  PRIMARY KEY (review_id, tag_id),
  CONSTRAINT fk_rt_review FOREIGN KEY (review_id) REFERENCES video_reviews(id) ON DELETE CASCADE,
  CONSTRAINT fk_rt_tag    FOREIGN KEY (tag_id)    REFERENCES tags(id)          ON DELETE CASCADE,
  INDEX idx_rt_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Embeddings stored as JSON arrays of floats. No vector DB — for a single
-- user's corpus (hundreds of notes) cosine similarity in PHP is trivial.
-- `model` lets us migrate model versions later by clearing rows with the old
-- label and re-embedding.
CREATE TABLE IF NOT EXISTS review_embeddings (
  review_id   CHAR(26)    NOT NULL PRIMARY KEY,
  model       VARCHAR(64) NOT NULL,
  embedding   JSON        NOT NULL,
  source_hash CHAR(64)    NOT NULL,
  embedded_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rem_review FOREIGN KEY (review_id) REFERENCES video_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS critique_embeddings (
  critique_id CHAR(26)    NOT NULL PRIMARY KEY,
  review_id   CHAR(26)    NOT NULL,
  user_id     CHAR(26)    NOT NULL,
  model       VARCHAR(64) NOT NULL,
  embedding   JSON        NOT NULL,
  source_hash CHAR(64)    NOT NULL,
  embedded_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cem_critique FOREIGN KEY (critique_id) REFERENCES video_critiques(id) ON DELETE CASCADE,
  CONSTRAINT fk_cem_review   FOREIGN KEY (review_id)   REFERENCES video_reviews(id)   ON DELETE CASCADE,
  CONSTRAINT fk_cem_user     FOREIGN KEY (user_id)     REFERENCES users(id)           ON DELETE CASCADE,
  INDEX idx_cem_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

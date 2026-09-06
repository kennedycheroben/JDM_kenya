-- JDM Kenya OAuth 2.0 provider schema. Apply once through an authorized deployment process.
-- Existing email addresses remain unverified until JDM has evidence of mailbox control.

ALTER TABLE users
  ADD COLUMN oauth_subject CHAR(36) NULL AFTER id,
  ADD COLUMN email_verified_at DATETIME NULL AFTER email,
  ADD COLUMN account_status ENUM('active','disabled','suspended','deleted') NOT NULL DEFAULT 'active' AFTER is_approved,
  ADD UNIQUE KEY uq_users_oauth_subject (oauth_subject),
  ADD KEY idx_users_oauth_eligibility (account_status, email_verified_at);

UPDATE users SET oauth_subject = UUID() WHERE oauth_subject IS NULL;

CREATE TABLE oauth_clients (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  secret_hash VARCHAR(255) NULL,
  redirect_uri VARCHAR(2048) NOT NULL,
  allowed_scopes VARCHAR(255) NOT NULL DEFAULT 'profile email',
  is_confidential TINYINT(1) NOT NULL DEFAULT 1,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_oauth_clients_active (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_auth_codes (
  identifier_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_subject CHAR(36) NOT NULL,
  client_id VARCHAR(64) NOT NULL,
  scopes TEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_oauth_codes_expiry (expires_at, revoked_at),
  CONSTRAINT fk_oauth_codes_user FOREIGN KEY (user_subject) REFERENCES users(oauth_subject),
  CONSTRAINT fk_oauth_codes_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_access_tokens (
  identifier_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_subject CHAR(36) NOT NULL,
  client_id VARCHAR(64) NOT NULL,
  scopes TEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_oauth_tokens_expiry (expires_at, revoked_at),
  CONSTRAINT fk_oauth_tokens_user FOREIGN KEY (user_subject) REFERENCES users(oauth_subject),
  CONSTRAINT fk_oauth_tokens_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(80) NOT NULL,
  client_id VARCHAR(64) NULL,
  user_subject CHAR(36) NULL,
  ip_hash CHAR(64) NOT NULL,
  metadata JSON NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_oauth_audit_event_time (event_type, created_at),
  KEY idx_oauth_audit_client_time (client_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_rate_limits (
  bucket_key CHAR(64) NOT NULL PRIMARY KEY,
  window_started_at DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

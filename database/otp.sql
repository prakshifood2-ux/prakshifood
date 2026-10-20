CREATE TABLE IF NOT EXISTS email_otps (
 kind ENUM('customer','admin') NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 challenge_id CHAR(64) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL,
 code_hash CHAR(64) NOT NULL,
 credential_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 consumed_at DATETIME NULL,
 sent_at DATETIME NOT NULL,
 window_start DATETIME NOT NULL,
 send_count INT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY(kind,account_id)
);

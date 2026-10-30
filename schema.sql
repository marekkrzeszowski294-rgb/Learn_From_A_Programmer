CREATE DATABASE IF NOT EXISTS learn_from_programmer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE learn_from_programmer;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student', 'mentor') NOT NULL,
  terms_accepted_at DATETIME NOT NULL,
  completed_lessons TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  topic VARCHAR(100) NOT NULL,
  mentor_level ENUM('undecided', 'junior', 'mid', 'senior', 'tech-lead') NOT NULL DEFAULT 'undecided',
  status ENUM('new', 'contacted', 'closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_booking_requests_user_id (user_id),
  KEY idx_booking_requests_created_at (created_at),
  CONSTRAINT fk_booking_requests_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

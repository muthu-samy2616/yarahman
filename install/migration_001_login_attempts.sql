-- migration_001_login_attempts.sql
-- Purpose: Rate limiting table for login brute-force protection
-- Run ONCE against u777110831_briyani_shop BEFORE deploying new code
-- Compatible: MariaDB 10.x / 11.x
-- ----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45)     NOT NULL,
    `username`   VARCHAR(100)    NOT NULL DEFAULT '',
    `success`    TINYINT(1)      NOT NULL DEFAULT 0,
    `attempted_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Housekeeping event: auto-purge attempts older than 24 hours
-- (Only enable if your hosting plan supports the Event Scheduler)
-- DELIMITER $$
-- CREATE EVENT IF NOT EXISTS `purge_old_login_attempts`
--   ON SCHEDULE EVERY 1 HOUR
--   DO
--     DELETE FROM `login_attempts` WHERE `attempted_at` < NOW() - INTERVAL 24 HOUR;
-- $$
-- DELIMITER ;

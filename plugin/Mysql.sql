CREATE TABLE IF NOT EXISTS `typecho_moments` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uid` INT NOT NULL DEFAULT 0,
  `content` TEXT NOT NULL,
  `created` INT NULL DEFAULT 0,
  `updated` INT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `uuid` varchar(36) NOT NULL UNIQUE,
  `ip` varchar(50) DEFAULT NULL,
  `agent` TEXT DEFAULT NULL,
  `pinned` TINYINT NOT NULL DEFAULT 0,
  `source` VARCHAR(255) DEFAULT NULL,
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `created` (`created`),
  KEY `pinned` (`pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `typecho_moments_hashtags` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `count` INT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 关系表
CREATE TABLE IF NOT EXISTS `typecho_moments_relation` (
  `moment_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`moment_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

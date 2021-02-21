/* v1.0.0 */

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

CREATE TABLE `link` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `url` varchar(500) NOT NULL,
  `disable` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `comment` tinytext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `disable` (`disable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `link` (`id`, `created`, `code`, `url`, `disable`, `comment`) VALUES (1,	current_timestamp,	'default',	'https://github.com/Devenet/Shortener',	1,	'');

CREATE TABLE `view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `link_id` smallint(5) unsigned NOT NULL,
  `ip_hash` varchar(32) DEFAULT NULL,
  `referer` varchar(300) DEFAULT NULL,
  `referer_host` varchar(100) DEFAULT NULL,
  `user_agent` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_view_link_id` (`link_id`),
  CONSTRAINT `fk_view_link_id` FOREIGN KEY (`link_id`) REFERENCES `link` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

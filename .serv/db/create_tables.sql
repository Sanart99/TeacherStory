/*!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.8-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: teacherstory
-- ------------------------------------------------------
-- Server version	10.11.8-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `connections`
--

DROP TABLE IF EXISTS `connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `connections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `app_id` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_activity_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events_to_send`
--

DROP TABLE IF EXISTS `events_to_send`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events_to_send` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `date` datetime NOT NULL,
  `expiration_date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invite_codes`
--

DROP TABLE IF EXISTS `invite_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invite_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` bigint(20) unsigned NOT NULL,
  `code` varchar(200) NOT NULL,
  `max_referree_count` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite_codes__code` (`code`),
  KEY `fk_invite_codes__users` (`referrer_id`) USING BTREE,
  CONSTRAINT `fk_invite_codes__users__userId` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invite_queues`
--

DROP TABLE IF EXISTS `invite_queues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invite_queues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(200) NOT NULL,
  `date` datetime NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `resulting_user_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_unique_session_id` (`code`,`session_id`),
  KEY `fk_invite_queues__users__userId` (`resulting_user_id`),
  CONSTRAINT `fk_invite_queues__users__userId` FOREIGN KEY (`resulting_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `log_browser_errors`
--

DROP TABLE IF EXISTS `log_browser_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_browser_errors` (
  `user_id` bigint(20) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `msg` varchar(500) NOT NULL,
  `url` varchar(2000) NOT NULL,
  `location` varchar(100) NOT NULL,
  KEY `fk_log_browser_errors__userId` (`user_id`),
  CONSTRAINT `fk_log_browser_errors__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_access_tokens`
--

DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_access_tokens` (
  `client_id` varchar(100) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `granted_scope` varchar(1000) NOT NULL,
  `scope` varchar(1000) NOT NULL,
  `token_type` varchar(50) NOT NULL,
  `refresh_token` varchar(100) NOT NULL,
  `access_token` varchar(100) NOT NULL,
  `expiration_date` datetime NOT NULL,
  `associated_code` varchar(200) NOT NULL,
  PRIMARY KEY (`client_id`,`user_id`),
  UNIQUE KEY `uq_access_token` (`access_token`),
  UNIQUE KEY `uq_refresh_token` (`refresh_token`),
  KEY `fk_oauth_access_tokens__userId` (`user_id`),
  CONSTRAINT `fk_oauth_access_tokens__clientId` FOREIGN KEY (`client_id`) REFERENCES `oauth_clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_access_tokens__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_clients`
--

DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_clients` (
  `user_id` bigint(20) unsigned NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_type` enum('confidential','public') NOT NULL,
  `client_secret` varchar(150) NOT NULL,
  `website` varchar(200) DEFAULT NULL,
  `description` varchar(750) DEFAULT NULL,
  `logo` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`client_id`),
  UNIQUE KEY `uq_client_id` (`client_id`) USING BTREE,
  CONSTRAINT `fk_oauth_clients__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_clients_redirect_uris`
--

DROP TABLE IF EXISTS `oauth_clients_redirect_uris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oauth_clients_redirect_uris` (
  `user_id` bigint(20) unsigned NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `number` int(11) unsigned NOT NULL,
  `redirect_uri` varchar(2000) NOT NULL,
  PRIMARY KEY (`client_id`,`number`,`user_id`) USING BTREE,
  KEY `fk_oauth_clients_redirect_uris__id` (`user_id`,`client_id`),
  CONSTRAINT `fk_oauth_clients_redirect_uris__id` FOREIGN KEY (`user_id`, `client_id`) REFERENCES `oauth_clients` (`user_id`, `client_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `url_check` CHECK (`redirect_uri` regexp '^https://(?:[a-zA-Z0-9-]+.)+[a-zA-Z]{2,}(?:/[^s]*)?$' = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `user_id` bigint(20) unsigned NOT NULL,
  `remote_public_key` varchar(500) NOT NULL,
  `date` datetime NOT NULL,
  `endpoint` varchar(8000) NOT NULL,
  `auth_token` varchar(200) NOT NULL,
  `expiration_time` double DEFAULT NULL,
  `user_visible_only` tinyint(1) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`remote_public_key`,`date`) USING BTREE,
  CONSTRAINT `fk_push_subscriptions__id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_connection_attempts`
--

DROP TABLE IF EXISTS `sec_connection_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_connection_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `app_id` varchar(500) DEFAULT NULL,
  `remote_address` varchar(45) NOT NULL,
  `date` datetime NOT NULL,
  `successful` tinyint(1) unsigned NOT NULL,
  `error_type` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_connections__userId` (`user_id`),
  CONSTRAINT `fk_connections__userId` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_ip_bans`
--

DROP TABLE IF EXISTS `sec_ip_bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_ip_bans` (
  `remote_address` varchar(75) NOT NULL,
  `date` datetime NOT NULL,
  `reason` varchar(75) NOT NULL,
  KEY `idx_remote_address` (`reason`,`date`,`remote_address`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_query_complexity_usage`
--

DROP TABLE IF EXISTS `sec_query_complexity_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_query_complexity_usage` (
  `remote_address` varchar(75) NOT NULL,
  `complexity_used` int(10) unsigned NOT NULL,
  PRIMARY KEY (`remote_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_sus_ip`
--

DROP TABLE IF EXISTS `sec_sus_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_sus_ip` (
  `remote_address` varchar(75) NOT NULL,
  `date` datetime NOT NULL,
  `points` tinyint(255) unsigned NOT NULL,
  `reason` varchar(500) NOT NULL,
  KEY `idx_sec_sus_ip` (`remote_address`,`date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_total_requests`
--

DROP TABLE IF EXISTS `sec_total_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_total_requests` (
  `remote_address` varchar(75) NOT NULL,
  `count` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`remote_address`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_users_bans`
--

DROP TABLE IF EXISTS `sec_users_bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_users_bans` (
  `user_id` bigint(20) unsigned NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `reason` mediumtext NOT NULL,
  PRIMARY KEY (`user_id`,`start_date`,`end_date`),
  KEY `fk_user_bans__id` (`user_id`),
  CONSTRAINT `fk_user_bans__id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_users_query_complexity_usage`
--

DROP TABLE IF EXISTS `sec_users_query_complexity_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_users_query_complexity_usage` (
  `user_id` bigint(20) unsigned NOT NULL,
  `complexity_used` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  CONSTRAINT `fk_sec_users_query_complexity_usage_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_users_total_requests`
--

DROP TABLE IF EXISTS `sec_users_total_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_users_total_requests` (
  `user_id` bigint(20) unsigned NOT NULL,
  `count` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  CONSTRAINT `fk_sec_users_total_requests__id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_wrong_sids`
--

DROP TABLE IF EXISTS `sec_wrong_sids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sec_wrong_sids` (
  `remote_address` varchar(75) NOT NULL,
  `date` datetime NOT NULL,
  `session_id` text NOT NULL,
  UNIQUE KEY `uq_sid` (`remote_address`,`session_id`(100)),
  KEY `idx_remote_address` (`session_id`(100),`date`,`remote_address`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trig_sec_wrong_sids_autoban_one` AFTER INSERT ON `sec_wrong_sids` FOR EACH ROW BEGIN
	DECLARE sReason VARCHAR(100) DEFAULT 'too many wrong sids';
	DECLARE res INT;
	SET res = (SELECT COUNT(*) FROM sec_wrong_sids WHERE remote_address=NEW.remote_address);
	
	IF (res >= 10 AND (SELECT COUNT(*) FROM sec_ip_bans WHERE remote_address=NEW.remote_address AND reason=sReason)=0) THEN BEGIN
		INSERT INTO sec_ip_bans (remote_address,date,reason) VALUES(NEW.remote_address,NOW(),sReason);
	END;
	END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `simpleKV`
--

DROP TABLE IF EXISTS `simpleKV`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `simpleKV` (
  `key` varchar(500) NOT NULL,
  `value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `roles` set('Administrator') NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL,
  `password` varchar(80) NOT NULL,
  `registration_date` datetime NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_object(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_name` (`name`),
  CONSTRAINT `settings` CHECK (json_valid(`settings`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'teacherstory'
--
/*!50106 SET @save_time_zone= @@TIME_ZONE */ ;
/*!50106 DROP EVENT IF EXISTS `ev_daily_cleaning` */;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = '+00:00' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`root`@`localhost`*/ /*!50106 EVENT `ev_daily_cleaning` ON SCHEDULE EVERY 1 DAY STARTS '2024-06-21 00:00:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
	DELETE FROM connections WHERE last_activity_at<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM events_to_send WHERE expiration_date<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM push_subscriptions WHERE date<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM log_browser_errors WHERE date<DATE_SUB(NOW(), INTERVAL 6 MONTH);
	DELETE FROM sec_connection_attempts WHERE date<DATE_SUB(NOW(), INTERVAL 2 MONTH);
	DELETE FROM sec_query_complexity_usage;
	DELETE FROM sec_users_query_complexity_usage;
	DELETE FROM sec_wrong_sids WHERE date<DATE_SUB(NOW(), INTERVAL 7 DAY);
END */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
/*!50106 DROP EVENT IF EXISTS `ev_hourly_cleaning` */;;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = '+00:00' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`root`@`localhost`*/ /*!50106 EVENT `ev_hourly_cleaning` ON SCHEDULE EVERY 1 HOUR STARTS '2024-06-21 00:00:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
	DELETE FROM sec_total_requests;
	DELETE FROM sec_users_total_requests;
END */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
DELIMITER ;
/*!50106 SET TIME_ZONE= @save_time_zone */ ;

--
-- Dumping routines for database 'teacherstory'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `proc_sec_wrong_sids_autobans_fullcheck` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `proc_sec_wrong_sids_autobans_fullcheck`()
BEGIN
	DECLARE sReason VARCHAR(100) DEFAULT 'too many wrong sids';
	DECLARE res CURSOR FOR (
		SELECT * FROM sec_wrong_sids AS t GROUP BY remote_address
		HAVING COUNT(*) >= 10 AND (SELECT COUNT(*) FROM sec_ip_bans WHERE remote_address=t.remote_address AND reason=sReason)=0
	);
		
	FOR row IN res DO BEGIN
		INSERT INTO sec_ip_bans (remote_address,date,reason) VALUES(row.remote_address,NOW(),sReason);
	END;
	END FOR;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-01-05  8:03:47

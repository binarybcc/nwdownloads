/*M!999999\- enable the sandbox mode */ 

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
DROP TABLE IF EXISTS `daily_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_snapshots` (
  `snapshot_date` date NOT NULL,
  `week_num` int(11) DEFAULT NULL COMMENT 'ISO week number (1-53)',
  `year` int(11) DEFAULT NULL COMMENT 'Year for the week',
  `paper_code` varchar(10) NOT NULL,
  `paper_name` varchar(100) DEFAULT NULL,
  `business_unit` varchar(50) DEFAULT NULL,
  `total_active` int(11) NOT NULL DEFAULT 0,
  `on_vacation` int(11) NOT NULL DEFAULT 0,
  `deliverable` int(11) NOT NULL DEFAULT 0,
  `mail_delivery` int(11) NOT NULL DEFAULT 0,
  `carrier_delivery` int(11) NOT NULL DEFAULT 0,
  `digital_only` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_filename` varchar(255) DEFAULT NULL COMMENT 'Original CSV filename',
  `source_date` date DEFAULT NULL COMMENT 'Date from filename',
  `is_backfilled` tinyint(1) DEFAULT 0 COMMENT '1 if backfilled',
  `backfill_weeks` int(11) DEFAULT NULL COMMENT 'Weeks backfilled',
  PRIMARY KEY (`snapshot_date`,`paper_code`),
  UNIQUE KEY `unique_daily_snapshot` (`snapshot_date`,`paper_code`),
  KEY `idx_date` (`snapshot_date`),
  KEY `idx_paper` (`paper_code`),
  KEY `idx_business_unit` (`business_unit`),
  KEY `idx_week` (`week_num`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dashboard_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboard_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'viewer',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_date` timestamp NULL DEFAULT current_timestamp(),
  `file_type` varchar(50) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `records_processed` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`import_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `publication_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `publication_schedule` (
  `paper_code` varchar(10) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
  `has_print` tinyint(1) DEFAULT 0 COMMENT 'True if print edition publishes this day',
  `has_digital` tinyint(1) DEFAULT 0 COMMENT 'True if digital content updates this day',
  PRIMARY KEY (`paper_code`,`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Publication schedule for all papers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rate_distribution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_distribution` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_date` date NOT NULL,
  `paper_code` varchar(10) NOT NULL,
  `rate_id` int(11) NOT NULL,
  `rate_description` text DEFAULT NULL,
  `subscriber_count` int(11) NOT NULL DEFAULT 0,
  `percentage` decimal(5,2) DEFAULT NULL,
  `rank_position` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date_paper` (`snapshot_date`,`paper_code`),
  KEY `idx_rate` (`rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rate_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paper_code` varchar(10) NOT NULL,
  `zone` varchar(50) NOT NULL,
  `rate_name` varchar(255) NOT NULL,
  `subscription_length` varchar(20) NOT NULL,
  `rate_amount` decimal(10,2) NOT NULL,
  `is_legacy` tinyint(1) DEFAULT 0 COMMENT 'User or auto-marked as legacy',
  `is_ignored` tinyint(1) DEFAULT 0 COMMENT 'User marked to ignore in calculations',
  `is_special` tinyint(1) DEFAULT 0 COMMENT 'Special rates excluded from opportunity calculations',
  `auto_detected_legacy` tinyint(1) DEFAULT 0 COMMENT 'Was auto-detected as below market rate',
  `notes` text DEFAULT NULL COMMENT 'Optional user notes about why rate is flagged',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_zone_rate` (`paper_code`,`zone`,`subscription_length`,`rate_amount`),
  KEY `idx_paper_zone` (`paper_code`,`zone`),
  KEY `idx_flags` (`is_legacy`,`is_ignored`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User-controlled rate classification for legacy detection';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rate_structure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_structure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paper_code` varchar(10) NOT NULL,
  `subscription_length` varchar(20) NOT NULL,
  `market_rate` decimal(10,2) NOT NULL,
  `rate_name` varchar(255) DEFAULT NULL,
  `annualized_rate` decimal(10,2) NOT NULL COMMENT 'Rate normalized to annual for comparison',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_paper_length` (`paper_code`,`subscription_length`),
  KEY `idx_paper_code` (`paper_code`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Market rate lookup table for legacy rate gap analysis';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `raw_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `raw_uploads` (
  `upload_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `upload_timestamp` timestamp NULL DEFAULT current_timestamp(),
  `filename` varchar(255) NOT NULL COMMENT 'Original CSV filename',
  `file_size` int(11) NOT NULL COMMENT 'File size in bytes',
  `file_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash for duplicate detection',
  `snapshot_date` date NOT NULL COMMENT 'Snapshot date extracted from data',
  `row_count` int(11) NOT NULL COMMENT 'Total rows in CSV',
  `subscriber_count` int(11) NOT NULL COMMENT 'Actual subscriber records processed',
  `raw_csv_data` longtext NOT NULL COMMENT 'Complete CSV file content',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'When data was processed into subscriber_snapshots',
  `processing_status` enum('pending','completed','failed','reprocessing') DEFAULT 'completed',
  `processing_errors` text DEFAULT NULL COMMENT 'Any errors during processing',
  `uploaded_by` varchar(100) DEFAULT 'web_interface' COMMENT 'Upload method',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Uploader IP address',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent',
  PRIMARY KEY (`upload_id`),
  UNIQUE KEY `unique_file_hash` (`file_hash`),
  KEY `idx_upload_date` (`upload_timestamp`),
  KEY `idx_snapshot_date` (`snapshot_date`),
  KEY `idx_filename` (`filename`),
  KEY `idx_file_hash` (`file_hash`),
  KEY `idx_processing_status` (`processing_status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Immutable source of truth for all uploaded CSV files';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriber_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriber_snapshots` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `upload_id` bigint(20) DEFAULT NULL COMMENT 'Reference to raw_uploads table',
  `snapshot_date` date NOT NULL,
  `week_num` int(11) DEFAULT NULL COMMENT 'ISO week number (1-53)',
  `year` int(11) DEFAULT NULL COMMENT 'Year for the week',
  `import_timestamp` timestamp NULL DEFAULT current_timestamp(),
  `sub_num` varchar(50) NOT NULL,
  `paper_code` varchar(10) NOT NULL,
  `paper_name` varchar(100) NOT NULL,
  `business_unit` varchar(50) NOT NULL,
  `name` varchar(200) DEFAULT NULL,
  `route` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city_state_postal` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rate_name` varchar(50) DEFAULT NULL,
  `subscription_length` varchar(20) DEFAULT NULL,
  `delivery_type` varchar(20) DEFAULT NULL,
  `payment_status` varchar(10) DEFAULT NULL,
  `begin_date` date DEFAULT NULL,
  `paid_thru` date DEFAULT NULL,
  `daily_rate` decimal(10,5) DEFAULT NULL,
  `last_payment_amount` decimal(10,2) DEFAULT NULL,
  `on_vacation` tinyint(1) DEFAULT 0,
  `vacation_start` date DEFAULT NULL COMMENT 'Vacation start date from Newzware',
  `vacation_end` date DEFAULT NULL COMMENT 'Vacation end/return date from Newzware',
  `vacation_weeks` decimal(5,1) DEFAULT NULL COMMENT 'Calculated weeks on vacation (calendar weeks)',
  `abc` varchar(10) DEFAULT NULL,
  `issue_code` varchar(10) DEFAULT NULL,
  `login_id` varchar(50) DEFAULT NULL,
  `last_login` date DEFAULT NULL,
  `source_filename` varchar(255) DEFAULT NULL COMMENT 'Original CSV filename',
  `source_date` date DEFAULT NULL COMMENT 'Date from filename',
  `is_backfilled` tinyint(1) DEFAULT 0 COMMENT '1 if backfilled',
  `backfill_weeks` int(11) DEFAULT NULL COMMENT 'Weeks backfilled',
  PRIMARY KEY (`id`,`snapshot_date`),
  UNIQUE KEY `unique_snapshot_subscriber` (`snapshot_date`,`sub_num`,`paper_code`),
  KEY `idx_upload_id` (`upload_id`),
  KEY `idx_snapshot_date` (`snapshot_date`),
  KEY `idx_snapshot_sub` (`snapshot_date`,`sub_num`),
  KEY `idx_sub_num` (`sub_num`),
  KEY `idx_paper_code` (`paper_code`),
  KEY `idx_business_unit` (`business_unit`),
  KEY `idx_rate_name` (`rate_name`),
  KEY `idx_paid_thru` (`paid_thru`),
  KEY `idx_last_payment` (`last_payment_amount`),
  KEY `idx_delivery_type` (`delivery_type`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_week` (`week_num`,`year`),
  KEY `idx_vacation_dates` (`vacation_start`,`vacation_end`),
  KEY `idx_vacation_weeks` (`vacation_weeks`),
  KEY `idx_revenue_query` (`snapshot_date`,`paid_thru`,`last_payment_amount`,`delivery_type`)
) ENGINE=InnoDB AUTO_INCREMENT=25377 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Partitioned subscriber snapshots - optimized for temporal queries'
 PARTITION BY RANGE (to_days(`snapshot_date`))
(PARTITION `p2025_11` VALUES LESS THAN (739951) COMMENT = 'November 2025' ENGINE = InnoDB,
 PARTITION `p2025_12` VALUES LESS THAN (739982) COMMENT = 'December 2025' ENGINE = InnoDB,
 PARTITION `p2026_01` VALUES LESS THAN (740013) COMMENT = 'January 2026' ENGINE = InnoDB,
 PARTITION `p2026_02` VALUES LESS THAN (740041) COMMENT = 'February 2026' ENGINE = InnoDB,
 PARTITION `p2026_03` VALUES LESS THAN (740072) COMMENT = 'March 2026' ENGINE = InnoDB,
 PARTITION `p2026_04` VALUES LESS THAN (740102) COMMENT = 'April 2026' ENGINE = InnoDB,
 PARTITION `p2026_05` VALUES LESS THAN (740133) COMMENT = 'May 2026' ENGINE = InnoDB,
 PARTITION `p2026_06` VALUES LESS THAN (740163) COMMENT = 'June 2026' ENGINE = InnoDB,
 PARTITION `p2026_07` VALUES LESS THAN (740194) COMMENT = 'July 2026' ENGINE = InnoDB,
 PARTITION `p2026_08` VALUES LESS THAN (740225) COMMENT = 'August 2026' ENGINE = InnoDB,
 PARTITION `p2026_09` VALUES LESS THAN (740255) COMMENT = 'September 2026' ENGINE = InnoDB,
 PARTITION `p2026_10` VALUES LESS THAN (740286) COMMENT = 'October 2026' ENGINE = InnoDB,
 PARTITION `p2026_11` VALUES LESS THAN (740316) COMMENT = 'November 2026' ENGINE = InnoDB,
 PARTITION `p2026_12` VALUES LESS THAN (740347) COMMENT = 'December 2026' ENGINE = InnoDB,
 PARTITION `p_future` VALUES LESS THAN MAXVALUE COMMENT = 'Future data' ENGINE = InnoDB);
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_date` date NOT NULL,
  `paper_code` varchar(10) NOT NULL,
  `active_vacations` int(11) NOT NULL DEFAULT 0,
  `scheduled_next_7days` int(11) NOT NULL DEFAULT 0,
  `scheduled_next_30days` int(11) NOT NULL DEFAULT 0,
  `returning_this_week` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vac_snapshot` (`snapshot_date`,`paper_code`),
  KEY `idx_date` (`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `weekly_summary`;
/*!50001 DROP VIEW IF EXISTS `weekly_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `weekly_summary` AS SELECT
 1 AS `week_start_date`,
  1 AS `week_label`,
  1 AS `paper_code`,
  1 AS `paper_name`,
  1 AS `business_unit`,
  1 AS `print_days_reported`,
  1 AS `avg_total_active`,
  1 AS `avg_deliverable`,
  1 AS `max_total_active`,
  1 AS `min_total_active`,
  1 AS `weekly_variation`,
  1 AS `avg_mail`,
  1 AS `avg_carrier`,
  1 AS `avg_digital`,
  1 AS `avg_vacation`,
  1 AS `latest_snapshot_in_week`,
  1 AS `expected_print_days`,
  1 AS `is_week_complete` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `weekly_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`circ_dash`@`172.18.0.%` SQL SECURITY DEFINER */
/*!50001 VIEW `weekly_summary` AS select `ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day AS `week_start_date`,concat(date_format(`ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day,'%b %d'),' - ',date_format(`ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day + interval 6 day,'%b %d, %Y')) AS `week_label`,`ds`.`paper_code` AS `paper_code`,`ds`.`paper_name` AS `paper_name`,`ds`.`business_unit` AS `business_unit`,count(distinct `ds`.`snapshot_date`) AS `print_days_reported`,round(avg(`ds`.`total_active`),0) AS `avg_total_active`,round(avg(`ds`.`deliverable`),0) AS `avg_deliverable`,max(`ds`.`total_active`) AS `max_total_active`,min(`ds`.`total_active`) AS `min_total_active`,max(`ds`.`total_active`) - min(`ds`.`total_active`) AS `weekly_variation`,round(avg(`ds`.`mail_delivery`),0) AS `avg_mail`,round(avg(`ds`.`carrier_delivery`),0) AS `avg_carrier`,round(avg(`ds`.`digital_only`),0) AS `avg_digital`,round(avg(`ds`.`on_vacation`),0) AS `avg_vacation`,max(`ds`.`snapshot_date`) AS `latest_snapshot_in_week`,(select count(0) from `publication_schedule` `ps2` where `ps2`.`paper_code` = `ds`.`paper_code` and `ps2`.`has_print` = 1) AS `expected_print_days`,count(distinct `ds`.`snapshot_date`) >= (select count(0) from `publication_schedule` `ps3` where `ps3`.`paper_code` = `ds`.`paper_code` and `ps3`.`has_print` = 1) AS `is_week_complete` from (`daily_snapshots` `ds` join `publication_schedule` `ps` on(`ds`.`paper_code` = `ps`.`paper_code` and dayofweek(`ds`.`snapshot_date`) - 1 = `ps`.`day_of_week` and `ps`.`has_print` = 1)) where `ds`.`snapshot_date` >= curdate() - interval 90 day group by `ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day,concat(date_format(`ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day,'%b %d'),' - ',date_format(`ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day + interval 6 day,'%b %d, %Y')),`ds`.`paper_code`,`ds`.`paper_name`,`ds`.`business_unit` order by `ds`.`snapshot_date` - interval weekday(`ds`.`snapshot_date`) day desc,`ds`.`business_unit`,`ds`.`paper_name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


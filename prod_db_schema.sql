/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: mauserdb
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0+deb12u2

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
-- Table structure for table `alert_settings`
--

DROP TABLE IF EXISTS `alert_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alert_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('oee_low','stop_long','scrap_high') NOT NULL,
  `threshold_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL COMMENT 'user_id som senast ändrade',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=1132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('oee_low','stop_long','scrap_high') NOT NULL,
  `message` varchar(500) NOT NULL,
  `value` decimal(10,2) DEFAULT NULL COMMENT 'Det uppmätta värdet som triggade alerten',
  `threshold` decimal(10,2) DEFAULT NULL COMMENT 'Tröskelvärdets gränsvärde',
  `severity` enum('warning','critical') NOT NULL DEFAULT 'warning',
  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_by` int(10) unsigned DEFAULT NULL COMMENT 'user_id som kvitterade',
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_acknowledged` (`acknowledged`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `user` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_user` (`user`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `avvikelselarm`
--

DROP TABLE IF EXISTS `avvikelselarm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `avvikelselarm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` enum('oee','kassation','produktionstakt','maskinstopp','produktionsmal') NOT NULL,
  `allvarlighetsgrad` enum('kritisk','varning','info') NOT NULL DEFAULT 'varning',
  `meddelande` varchar(500) NOT NULL,
  `varde_aktuellt` decimal(10,2) DEFAULT NULL COMMENT 'Aktuellt uppmatt varde',
  `varde_grans` decimal(10,2) DEFAULT NULL COMMENT 'Gransvarde som overskreds',
  `tidsstampel` datetime NOT NULL DEFAULT current_timestamp(),
  `kvitterad` tinyint(1) NOT NULL DEFAULT 0,
  `kvitterad_av` varchar(100) DEFAULT NULL,
  `kvitterad_datum` datetime DEFAULT NULL,
  `kvitterings_kommentar` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_allvarlighetsgrad` (`allvarlighetsgrad`),
  KEY `idx_kvitterad` (`kvitterad`),
  KEY `idx_tidsstampel` (`tidsstampel`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Automatiska avvikelselarm for produktionen';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batch_ibc`
--

DROP TABLE IF EXISTS `batch_ibc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `batch_ibc` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) unsigned NOT NULL,
  `ibc_nummer` varchar(100) DEFAULT NULL,
  `operator_id` int(10) unsigned DEFAULT NULL,
  `startad` datetime NOT NULL DEFAULT current_timestamp(),
  `klar` datetime DEFAULT NULL,
  `kasserad` tinyint(1) NOT NULL DEFAULT 0,
  `cykeltid_sekunder` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_batch_ibc_batch` (`batch_id`),
  KEY `idx_batch_ibc_operator` (`operator_id`),
  CONSTRAINT `fk_batch_ibc_batch` FOREIGN KEY (`batch_id`) REFERENCES `batch_order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `batch_order`
--

DROP TABLE IF EXISTS `batch_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `batch_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `batch_nummer` varchar(100) NOT NULL,
  `planerat_antal` int(10) unsigned NOT NULL DEFAULT 0,
  `kommentar` text DEFAULT NULL,
  `status` enum('pagaende','klar','pausad') NOT NULL DEFAULT 'pagaende',
  `skapad_av` int(10) unsigned DEFAULT NULL,
  `skapad_datum` datetime NOT NULL DEFAULT current_timestamp(),
  `avslutad_datum` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_batch_status` (`status`),
  KEY `idx_batch_skapad` (`skapad_datum`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_audit_log`
--

DROP TABLE IF EXISTS `bonus_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `user` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_config`
--

DROP TABLE IF EXISTS `bonus_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `weights_foodgrade` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weights_foodgrade`)),
  `weights_nonun` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weights_nonun`)),
  `weights_tvattade` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weights_tvattade`)),
  `productivity_target_foodgrade` decimal(5,2) DEFAULT 12.00,
  `productivity_target_nonun` decimal(5,2) DEFAULT 20.00,
  `productivity_target_tvattade` decimal(5,2) DEFAULT 15.00,
  `tier_multipliers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tier_multipliers`)),
  `max_bonus` int(11) DEFAULT 200,
  `team_bonus_enabled` tinyint(1) DEFAULT 0,
  `safety_bonus_enabled` tinyint(1) DEFAULT 0,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `weekly_bonus_goal` decimal(6,2) NOT NULL DEFAULT 80.00 COMMENT 'Målpoäng per vecka för bonusberäkning',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_konfiguration`
--

DROP TABLE IF EXISTS `bonus_konfiguration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_konfiguration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faktor` enum('ibc_per_timme','kvalitet','narvaro','team_bonus') NOT NULL,
  `vikt` decimal(5,2) NOT NULL COMMENT 'Procentandel av total bonus (summa=100)',
  `mal_varde` decimal(10,2) NOT NULL COMMENT 'Malvarde for 100% bonus',
  `max_bonus_kr` decimal(10,2) NOT NULL COMMENT 'Max bonus i kronor for denna faktor',
  `beskrivning` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faktor` (`faktor`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_level_amounts`
--

DROP TABLE IF EXISTS `bonus_level_amounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_level_amounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) NOT NULL,
  `amount_sek` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level_name` (`level_name`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_payouts`
--

DROP TABLE IF EXISTS `bonus_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_payouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `op_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_label` varchar(50) NOT NULL DEFAULT '',
  `bonus_level` enum('none','bronze','silver','gold','platinum') NOT NULL DEFAULT 'none',
  `status` enum('pending','approved','paid') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `amount_sek` decimal(10,2) NOT NULL,
  `ibc_count` int(11) DEFAULT 0,
  `avg_ibc_per_h` decimal(6,2) DEFAULT 0.00,
  `avg_quality_pct` decimal(5,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_op_id` (`op_id`),
  KEY `idx_period` (`period_start`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bonus_utbetalning`
--

DROP TABLE IF EXISTS `bonus_utbetalning`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_utbetalning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operator_id` int(11) NOT NULL,
  `operator_namn` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_slut` date NOT NULL,
  `ibc_per_timme_snitt` decimal(10,2) DEFAULT 0.00,
  `kvalitet_procent` decimal(5,2) DEFAULT 0.00,
  `narvaro_procent` decimal(5,2) DEFAULT 0.00,
  `team_mal_procent` decimal(5,2) DEFAULT 0.00,
  `bonus_ibc` decimal(10,2) DEFAULT 0.00,
  `bonus_kvalitet` decimal(10,2) DEFAULT 0.00,
  `bonus_narvaro` decimal(10,2) DEFAULT 0.00,
  `bonus_team` decimal(10,2) DEFAULT 0.00,
  `total_bonus` decimal(10,2) DEFAULT 0.00,
  `skapad_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_operator_id` (`operator_id`),
  KEY `idx_period` (`period_start`,`period_slut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dashboard_layouts`
--

DROP TABLE IF EXISTS `dashboard_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboard_layouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `layout_json` text NOT NULL COMMENT 'JSON med widget-ordning och synlighet',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feature_flags`
--

DROP TABLE IF EXISTS `feature_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feature_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feature_key` varchar(100) NOT NULL,
  `label` varchar(200) NOT NULL,
  `category` varchar(50) DEFAULT 'rebotling',
  `min_role` enum('public','user','admin','developer') NOT NULL DEFAULT 'developer',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_key` (`feature_key`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gamification_badges`
--

DROP TABLE IF EXISTS `gamification_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamification_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` varchar(50) NOT NULL COMMENT 'centurion, perfektionist, maratonlopare, stoppjagare, teamspelare',
  `tilldelad_datum` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_badge_date` (`user_id`,`badge_id`,`tilldelad_datum`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_badge_id` (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sparade badges/utmarkelser for gamification-systemet';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gamification_milstolpar`
--

DROP TABLE IF EXISTS `gamification_milstolpar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamification_milstolpar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `milstolpe_namn` varchar(50) NOT NULL COMMENT 'Nyborjare, Erfaren, Expert, Master, Legend, Mytisk',
  `uppnadd_datum` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_milstolpe` (`user_id`,`milstolpe_namn`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Uppnadda milstolpar for operatorer';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kapacitet_config`
--

DROP TABLE IF EXISTS `kapacitet_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kapacitet_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `station_id` varchar(30) NOT NULL,
  `station_namn` varchar(60) NOT NULL,
  `teoretisk_kapacitet_per_timme` decimal(8,2) NOT NULL DEFAULT 30.00,
  `mal_utnyttjandegrad_pct` decimal(5,2) NOT NULL DEFAULT 85.00,
  `ibc_per_operator_timme` decimal(8,2) NOT NULL DEFAULT 15.00,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_station_id` (`station_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kassationsorsak_typer`
--

DROP TABLE IF EXISTS `kassationsorsak_typer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kassationsorsak_typer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(100) NOT NULL,
  `beskrivning` text DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `sortorder` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kassationsregistrering`
--

DROP TABLE IF EXISTS `kassationsregistrering`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kassationsregistrering` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `skiftraknare` int(11) DEFAULT NULL,
  `skift_typ` enum('dag','kväll','natt') DEFAULT NULL,
  `orsak_id` int(11) NOT NULL,
  `antal` int(11) NOT NULL DEFAULT 1,
  `kommentar` varchar(500) DEFAULT NULL,
  `registrerad_av` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_orsak_datum` (`orsak_id`,`datum`),
  KEY `idx_registrerad_av` (`registrerad_av`),
  KEY `idx_skift_typ` (`skift_typ`),
  CONSTRAINT `kassationsregistrering_ibfk_1` FOREIGN KEY (`orsak_id`) REFERENCES `kassationsorsak_typer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `klassificeringslinje_settings`
--

DROP TABLE IF EXISTS `klassificeringslinje_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `klassificeringslinje_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting` varchar(100) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting` (`setting`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `klassificeringslinje_skiftrapport`
--

DROP TABLE IF EXISTS `klassificeringslinje_skiftrapport`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `klassificeringslinje_skiftrapport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `antal_ok` int(11) NOT NULL DEFAULT 0,
  `antal_ej_ok` int(11) NOT NULL DEFAULT 0,
  `totalt` int(11) NOT NULL DEFAULT 0,
  `kommentar` text DEFAULT NULL,
  `inlagd` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `klassificeringslinje_weekday_goals`
--

DROP TABLE IF EXISTS `klassificeringslinje_weekday_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `klassificeringslinje_weekday_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `weekday` tinyint(4) NOT NULL,
  `mal` int(11) NOT NULL DEFAULT 120,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekday` (`weekday`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kundordrar`
--

DROP TABLE IF EXISTS `kundordrar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kundordrar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kundnamn` varchar(255) NOT NULL,
  `antal_ibc` int(11) NOT NULL DEFAULT 0,
  `bestallningsdatum` date NOT NULL,
  `onskat_leveransdatum` date NOT NULL,
  `beraknat_leveransdatum` date DEFAULT NULL,
  `status` enum('planerad','i_produktion','levererad','forsenad') NOT NULL DEFAULT 'planerad',
  `prioritet` int(11) NOT NULL DEFAULT 5,
  `notering` text DEFAULT NULL,
  `skapad_datum` datetime NOT NULL DEFAULT current_timestamp(),
  `uppdaterad_datum` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kvalitetscertifikat`
--

DROP TABLE IF EXISTS `kvalitetscertifikat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kvalitetscertifikat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `batch_nummer` varchar(50) NOT NULL,
  `datum` date NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `operator_namn` varchar(100) NOT NULL DEFAULT '',
  `antal_ibc` int(11) NOT NULL DEFAULT 0,
  `kassation_procent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cykeltid_snitt` decimal(8,2) NOT NULL DEFAULT 0.00,
  `kvalitetspoang` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` enum('godkand','underkand','ej_bedomd') NOT NULL DEFAULT 'ej_bedomd',
  `kommentar` text DEFAULT NULL,
  `bedomd_av` varchar(100) DEFAULT NULL,
  `bedomd_datum` datetime DEFAULT NULL,
  `skapad_datum` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kc_batch` (`batch_nummer`),
  KEY `idx_kc_datum` (`datum`),
  KEY `idx_kc_status` (`status`),
  KEY `idx_kc_operator` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kvalitetskriterier`
--

DROP TABLE IF EXISTS `kvalitetskriterier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kvalitetskriterier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(100) NOT NULL,
  `beskrivning` text DEFAULT NULL,
  `min_varde` decimal(10,2) DEFAULT NULL,
  `max_varde` decimal(10,2) DEFAULT NULL,
  `vikt` decimal(5,2) NOT NULL DEFAULT 1.00,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `skapad_datum` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `larmregler`
--

DROP TABLE IF EXISTS `larmregler`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `larmregler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` enum('oee','kassation','produktionstakt','maskinstopp','produktionsmal') NOT NULL,
  `allvarlighetsgrad` enum('kritisk','varning','info') NOT NULL DEFAULT 'varning',
  `grans_varde` decimal(10,2) NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `beskrivning` varchar(300) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_typ` (`typ`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Konfigurerbara larmregler med troeskelvarden';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_created` (`ip_address`,`created_at`),
  KEY `idx_created` (`created_at`),
  KEY `idx_username_created` (`username`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maintenance_equipment`
--

DROP TABLE IF EXISTS `maintenance_equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(100) NOT NULL,
  `kategori` enum('maskin','transport','verktyg','infrastruktur','övrigt') DEFAULT 'maskin',
  `linje` varchar(50) DEFAULT 'rebotling',
  `aktiv` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maintenance_log`
--

DROP TABLE IF EXISTS `maintenance_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `line` enum('rebotling','tvattlinje','saglinje','klassificeringslinje','allmant') NOT NULL DEFAULT 'rebotling',
  `maintenance_type` enum('planerat','akut','inspektion','kalibrering','rengoring','ovrigt') NOT NULL DEFAULT 'ovrigt',
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `cost_sek` decimal(10,2) DEFAULT NULL,
  `status` enum('planerat','pagaende','klart','avbokat') NOT NULL DEFAULT 'klart',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `equipment` varchar(100) DEFAULT NULL,
  `downtime_minutes` int(11) DEFAULT 0,
  `resolved` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_line_date` (`line`,`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maskin_oee_config`
--

DROP TABLE IF EXISTS `maskin_oee_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maskin_oee_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maskin_id` int(11) NOT NULL,
  `planerad_tid_min` decimal(8,2) NOT NULL DEFAULT 480.00 COMMENT 'Planerad drifttid per dag (min), standard 8h',
  `ideal_cykeltid_sek` decimal(8,2) NOT NULL DEFAULT 120.00 COMMENT 'Ideal cykeltid per IBC (sekunder)',
  `oee_mal_pct` decimal(5,2) NOT NULL DEFAULT 85.00 COMMENT 'OEE-mal i procent',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_maskin_id` (`maskin_id`),
  KEY `idx_maskin_id` (`maskin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maskin_oee_daglig`
--

DROP TABLE IF EXISTS `maskin_oee_daglig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maskin_oee_daglig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maskin_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `planerad_tid_min` decimal(8,2) NOT NULL DEFAULT 0.00,
  `drifttid_min` decimal(8,2) NOT NULL DEFAULT 0.00,
  `stopptid_min` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_output` int(11) NOT NULL DEFAULT 0,
  `ok_output` int(11) NOT NULL DEFAULT 0,
  `kassation` int(11) NOT NULL DEFAULT 0,
  `ideal_cykeltid_sek` decimal(8,2) NOT NULL DEFAULT 120.00,
  `tillganglighet_pct` decimal(5,2) DEFAULT NULL,
  `prestanda_pct` decimal(5,2) DEFAULT NULL,
  `kvalitet_pct` decimal(5,2) DEFAULT NULL,
  `oee_pct` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_maskin_datum` (`maskin_id`,`datum`),
  KEY `idx_datum` (`datum`),
  KEY `idx_maskin_id` (`maskin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=256 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maskin_register`
--

DROP TABLE IF EXISTS `maskin_register`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maskin_register` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(100) NOT NULL,
  `beskrivning` text DEFAULT NULL,
  `service_intervall_dagar` int(11) NOT NULL DEFAULT 90,
  `aktiv` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maskin_service_logg`
--

DROP TABLE IF EXISTS `maskin_service_logg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maskin_service_logg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maskin_id` int(11) NOT NULL,
  `service_datum` date NOT NULL,
  `service_typ` enum('planerat','akut','inspektion') NOT NULL DEFAULT 'planerat',
  `beskrivning` text DEFAULT NULL,
  `utfort_av` varchar(100) DEFAULT NULL,
  `nasta_planerad_datum` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maskin_id` (`maskin_id`),
  KEY `idx_service_datum` (`service_datum`),
  CONSTRAINT `maskin_service_logg_ibfk_1` FOREIGN KEY (`maskin_id`) REFERENCES `maskin_register` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maskin_stopptid`
--

DROP TABLE IF EXISTS `maskin_stopptid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maskin_stopptid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maskin_id` int(11) NOT NULL COMMENT 'Referens till maskin_register.id',
  `maskin_namn` varchar(100) NOT NULL COMMENT 'Denormaliserat maskinnamn (cache)',
  `startad_at` datetime NOT NULL,
  `avslutad_at` datetime DEFAULT NULL,
  `duration_min` decimal(8,2) DEFAULT NULL COMMENT 'Varaktighet i minuter (beräknas vid avslut)',
  `orsak` varchar(200) DEFAULT NULL COMMENT 'Fri text eller kategorinamn',
  `orsak_kategori` enum('maskin','material','operatör','planerat','övrigt') NOT NULL DEFAULT 'övrigt',
  `operator_id` int(11) DEFAULT NULL COMMENT 'users.id för den som registrerade',
  `operator_namn` varchar(100) DEFAULT NULL COMMENT 'Denormaliserat operatörnamn',
  `kommentar` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maskin_id` (`maskin_id`),
  KEY `idx_startad_at` (`startad_at`),
  KEY `idx_avslutad_at` (`avslutad_at`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maskin-specifika stopptider för rebotling-linjen';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `category` enum('produktion','bonus','system','info','viktig','rekord','hog_oee','certifiering','urgent') NOT NULL DEFAULT 'info',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 1,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `arkiveras_efter_dagar` int(11) DEFAULT NULL,
  `arkiverad` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_pinned` (`pinned`),
  KEY `idx_priority` (`priority`),
  KEY `idx_published` (`published`),
  KEY `idx_arkiverad` (`arkiverad`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operator_certifications`
--

DROP TABLE IF EXISTS `operator_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `op_number` int(11) NOT NULL,
  `line` varchar(50) NOT NULL,
  `certified_by` int(11) DEFAULT NULL,
  `certified_date` date NOT NULL,
  `expires_date` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_op` (`op_number`),
  KEY `idx_line` (`line`),
  KEY `idx_expires` (`expires_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operator_feedback`
--

DROP TABLE IF EXISTS `operator_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operator_id` int(11) NOT NULL,
  `skiftraknare` int(11) DEFAULT NULL,
  `datum` date NOT NULL,
  `stämning` tinyint(4) NOT NULL COMMENT '1=Dålig 2=Ok 3=Bra 4=Utmärkt',
  `kommentar` varchar(280) DEFAULT NULL,
  `skapad_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_operator` (`operator_id`),
  KEY `idx_datum_operator` (`datum`,`operator_id`),
  KEY `idx_skapad_at` (`skapad_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operators`
--

DROP TABLE IF EXISTS `operators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `operators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `number` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operators_number` (`number`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_events`
--

DROP TABLE IF EXISTS `production_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_date` date NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `event_type` enum('underhall','ny_operator','mal_andring','rekord','ovrigt') NOT NULL DEFAULT 'ovrigt',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produktions_mal`
--

DROP TABLE IF EXISTS `produktions_mal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produktions_mal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mal_typ` enum('dagligt','veckovist') NOT NULL DEFAULT 'dagligt',
  `target_ibc` int(10) unsigned NOT NULL DEFAULT 80,
  `target_kassation_pct` decimal(5,2) NOT NULL DEFAULT 5.00,
  `giltig_from` date NOT NULL,
  `giltig_tom` date DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mal_typ` (`mal_typ`),
  KEY `idx_mal_giltig` (`giltig_from`,`giltig_tom`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produktionskapacitet_config`
--

DROP TABLE IF EXISTS `produktionskapacitet_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produktionskapacitet_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kapacitet_per_dag` int(11) NOT NULL DEFAULT 80,
  `planerade_underhallsdagar` text DEFAULT NULL COMMENT 'JSON-array med datum',
  `buffer_procent` int(11) NOT NULL DEFAULT 10,
  `uppdaterad_datum` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produktionskostnad_config`
--

DROP TABLE IF EXISTS `produktionskostnad_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produktionskostnad_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `faktor` enum('energi','bemanning','material','kassation','overhead') NOT NULL,
  `varde` decimal(10,2) NOT NULL DEFAULT 0.00,
  `enhet` varchar(20) NOT NULL DEFAULT 'kr',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_faktor` (`faktor`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produktionsmal_undantag`
--

DROP TABLE IF EXISTS `produktionsmal_undantag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produktionsmal_undantag` (
  `datum` date NOT NULL,
  `justerat_mal` int(11) NOT NULL,
  `orsak` varchar(255) DEFAULT NULL,
  `skapad_av` int(11) DEFAULT NULL,
  `skapad_at` timestamp NULL DEFAULT current_timestamp(),
  `uppdaterad_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produktionstakt_target`
--

DROP TABLE IF EXISTS `produktionstakt_target`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produktionstakt_target` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_ibc_per_hour` decimal(6,1) NOT NULL,
  `set_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_annotations`
--

DROP TABLE IF EXISTS `rebotling_annotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_annotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `typ` enum('driftstopp','helgdag','handelse','ovrigt') NOT NULL DEFAULT 'ovrigt',
  `titel` varchar(120) NOT NULL,
  `beskrivning` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_typ` (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_driftstopp`
--

DROP TABLE IF EXISTS `rebotling_driftstopp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_driftstopp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `driftstopp_status` tinyint(1) NOT NULL DEFAULT 0,
  `skiftraknare` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_skiftraknare` (`skiftraknare`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_goal_history`
--

DROP TABLE IF EXISTS `rebotling_goal_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_goal_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goal_type` varchar(50) NOT NULL DEFAULT 'dagmal',
  `value` int(11) NOT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type_time` (`goal_type`,`changed_at`),
  KEY `idx_changed_at` (`changed_at`),
  KEY `idx_changed_by` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_ibc`
--

DROP TABLE IF EXISTS `rebotling_ibc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_ibc` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `s_count` bigint(20) NOT NULL,
  `ibc_count` int(11) NOT NULL,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `other` varchar(200) NOT NULL DEFAULT '',
  `skiftraknare` int(11) DEFAULT NULL,
  `produktion_procent` int(11) NOT NULL,
  `ibc_ok` int(11) DEFAULT NULL,
  `ibc_ej_ok` int(11) DEFAULT NULL,
  `bur_ej_ok` int(11) DEFAULT NULL,
  `runtime_plc` int(11) DEFAULT NULL,
  `rasttime` int(11) DEFAULT NULL,
  `op1` int(11) DEFAULT NULL,
  `op2` int(11) DEFAULT NULL,
  `op3` int(11) DEFAULT NULL,
  `effektivitet` decimal(5,2) DEFAULT NULL,
  `produktivitet` decimal(5,2) DEFAULT NULL,
  `kvalitet` decimal(5,2) DEFAULT NULL,
  `bonus_poang` decimal(5,2) DEFAULT NULL,
  `bonus_approved` tinyint(1) DEFAULT 0,
  `bonus_approved_by` varchar(100) DEFAULT NULL,
  `bonus_approved_at` datetime DEFAULT NULL,
  `produkt` int(11) DEFAULT NULL,
  `lopnummer` int(11) DEFAULT NULL,
  `runtime` int(11) DEFAULT NULL,
  `nyttlopnummer` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_skiftraknare_ibc` (`skiftraknare`),
  KEY `idx_rebotling_ibc_op1` (`op1`),
  KEY `idx_rebotling_ibc_op2` (`op2`),
  KEY `idx_rebotling_ibc_op3` (`op3`),
  KEY `idx_rebotling_ibc_bonus` (`bonus_poang`),
  KEY `idx_rebotling_ibc_datum` (`datum`),
  KEY `idx_datum` (`datum`)
) ENGINE=InnoDB AUTO_INCREMENT=4920 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_kassationsalarminst`
--

DROP TABLE IF EXISTS `rebotling_kassationsalarminst`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_kassationsalarminst` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `varning_procent` decimal(5,2) NOT NULL DEFAULT 3.00 COMMENT 'Gul varning (%)',
  `alarm_procent` decimal(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Rod alarm (%)',
  `skapad_av` int(10) unsigned DEFAULT NULL,
  `skapad_datum` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_skapad_datum` (`skapad_datum`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_lopnummer_current`
--

DROP TABLE IF EXISTS `rebotling_lopnummer_current`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_lopnummer_current` (
  `id` tinyint(3) unsigned NOT NULL,
  `lopnummer` int(11) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_onoff`
--

DROP TABLE IF EXISTS `rebotling_onoff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_onoff` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `s_count_h` bigint(20) NOT NULL,
  `s_count_l` bigint(20) NOT NULL,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `running` tinyint(1) NOT NULL,
  `runtime_today` int(11) NOT NULL,
  `program` smallint(6) NOT NULL DEFAULT 0,
  `op1` smallint(6) NOT NULL DEFAULT 0,
  `op2` smallint(6) NOT NULL DEFAULT 0,
  `op3` smallint(6) NOT NULL DEFAULT 0,
  `produkt` smallint(6) NOT NULL DEFAULT 0,
  `antal` smallint(6) NOT NULL DEFAULT 0,
  `runtime_plc` smallint(6) NOT NULL DEFAULT 0,
  `skiftraknare` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_skiftraknare_onoff` (`skiftraknare`)
) ENGINE=InnoDB AUTO_INCREMENT=1099 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_production_goals`
--

DROP TABLE IF EXISTS `rebotling_production_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_production_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_type` enum('daily','weekly') NOT NULL DEFAULT 'daily',
  `target_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_period_type` (`period_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_products`
--

DROP TABLE IF EXISTS `rebotling_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `cycle_time_minutes` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_produktionsmal`
--

DROP TABLE IF EXISTS `rebotling_produktionsmal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_produktionsmal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` enum('dag','vecka','manad') NOT NULL DEFAULT 'vecka',
  `mal_antal` int(10) unsigned NOT NULL,
  `start_datum` date NOT NULL,
  `slut_datum` date NOT NULL,
  `skapad_av` int(10) unsigned DEFAULT NULL,
  `skapad_datum` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_datum` (`start_datum`,`slut_datum`),
  KEY `idx_aktiv` (`typ`,`slut_datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_rast`
--

DROP TABLE IF EXISTS `rebotling_rast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_rast` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `rast_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = arbetar, 1 = på rast',
  `rast_today` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total rasttid idag i minuter',
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_runtime`
--

DROP TABLE IF EXISTS `rebotling_runtime`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_runtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `rast_status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_settings`
--

DROP TABLE IF EXISTS `rebotling_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `rebotling_target` int(11) NOT NULL DEFAULT 1000,
  `hourly_target` int(11) NOT NULL DEFAULT 50,
  `auto_start` tinyint(1) NOT NULL DEFAULT 0,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT 0,
  `alert_threshold` int(11) NOT NULL DEFAULT 80,
  `shift_hours` decimal(4,1) NOT NULL DEFAULT 8.0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `min_operators` int(11) DEFAULT 2,
  `notification_emails` text DEFAULT NULL,
  `alert_thresholds` text DEFAULT NULL,
  `notification_config` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_kv_settings`
--

DROP TABLE IF EXISTS `rebotling_kv_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_kv_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_shift_times`
--

DROP TABLE IF EXISTS `rebotling_shift_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_shift_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(50) NOT NULL,
  `start_time` time NOT NULL DEFAULT '06:00:00',
  `end_time` time NOT NULL DEFAULT '14:00:00',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_name` (`shift_name`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_skift_kommentar`
--

DROP TABLE IF EXISTS `rebotling_skift_kommentar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_skift_kommentar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `skift_nr` int(11) NOT NULL,
  `kommentar` text DEFAULT NULL,
  `skapad_av` varchar(100) DEFAULT NULL,
  `skapad_tid` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_skift` (`datum`,`skift_nr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_skiftoverlamning`
--

DROP TABLE IF EXISTS `rebotling_skiftoverlamning`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_skiftoverlamning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skift_datum` date NOT NULL,
  `skift_typ` varchar(20) NOT NULL COMMENT 'dag, kvall, natt',
  `operator_id` int(11) DEFAULT NULL,
  `produktion_antal` int(11) DEFAULT 0,
  `oee_procent` decimal(5,2) DEFAULT 0.00,
  `stopp_antal` int(11) DEFAULT 0,
  `stopp_minuter` int(11) DEFAULT 0,
  `kassation_procent` decimal(5,2) DEFAULT 0.00,
  `checklista_rengoring` tinyint(1) DEFAULT 0,
  `checklista_verktyg` tinyint(1) DEFAULT 0,
  `checklista_kemikalier` tinyint(1) DEFAULT 0,
  `checklista_avvikelser` tinyint(1) DEFAULT 0,
  `checklista_sakerhet` tinyint(1) DEFAULT 0,
  `checklista_material` tinyint(1) DEFAULT 0,
  `kommentar_hande` text DEFAULT NULL,
  `kommentar_atgarda` text DEFAULT NULL,
  `kommentar_ovrigt` text DEFAULT NULL,
  `skapad` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`skift_datum`),
  KEY `idx_skift_typ` (`skift_typ`),
  KEY `idx_operator` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_skiftrapport`
--

DROP TABLE IF EXISTS `rebotling_skiftrapport`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_skiftrapport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `ibc_ok` int(11) NOT NULL DEFAULT 0,
  `bur_ej_ok` int(11) NOT NULL DEFAULT 0,
  `ibc_ej_ok` int(11) NOT NULL DEFAULT 0,
  `totalt` int(11) NOT NULL DEFAULT 0,
  `inlagd` tinyint(1) NOT NULL DEFAULT 0,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `skiftraknare` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `drifttid` int(11) NOT NULL DEFAULT 0,
  `op1` int(11) DEFAULT NULL COMMENT 'D4000 - Operatör Tvättplats (PLC operator_id)',
  `op2` int(11) DEFAULT NULL COMMENT 'D4001 - Operatör Kontrollstation (PLC operator_id)',
  `op3` int(11) DEFAULT NULL COMMENT 'D4002 - Operatör Truckförare (PLC operator_id)',
  `rasttime` int(11) DEFAULT NULL COMMENT 'D4008 - Rasttid PLC (minuter)',
  `driftstopptime` int(11) DEFAULT NULL,
  `lopnummer` int(11) DEFAULT NULL COMMENT 'D4009 - Högsta löpnummer i skiftet',
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_skiftraknare` (`skiftraknare`)
) ENGINE=MyISAM AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_underhallslogg`
--

DROP TABLE IF EXISTS `rebotling_underhallslogg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_underhallslogg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station_id` int(11) NOT NULL DEFAULT 0,
  `typ` enum('planerat','oplanerat') NOT NULL,
  `beskrivning` text DEFAULT NULL,
  `varaktighet_min` int(11) NOT NULL DEFAULT 0,
  `stopporsak` varchar(255) DEFAULT NULL,
  `utford_av` varchar(100) DEFAULT NULL,
  `datum` datetime NOT NULL,
  `skapad` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_station_id` (`station_id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_datum` (`datum`),
  KEY `idx_skapad` (`skapad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rebotling_weekday_goals`
--

DROP TABLE IF EXISTS `rebotling_weekday_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebotling_weekday_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `weekday` tinyint(4) NOT NULL COMMENT '1=Måndag ... 7=Söndag (ISO)',
  `daily_goal` int(11) NOT NULL DEFAULT 1000,
  `label` varchar(20) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_weekday` (`weekday`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `saglinje_settings`
--

DROP TABLE IF EXISTS `saglinje_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saglinje_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting` varchar(100) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting` (`setting`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `saglinje_skiftrapport`
--

DROP TABLE IF EXISTS `saglinje_skiftrapport`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saglinje_skiftrapport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `antal_ok` int(11) NOT NULL DEFAULT 0,
  `antal_ej_ok` int(11) NOT NULL DEFAULT 0,
  `totalt` int(11) NOT NULL DEFAULT 0,
  `kommentar` text DEFAULT NULL,
  `inlagd` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `saglinje_weekday_goals`
--

DROP TABLE IF EXISTS `saglinje_weekday_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saglinje_weekday_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `weekday` tinyint(4) NOT NULL,
  `mal` int(11) NOT NULL DEFAULT 50,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekday` (`weekday`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_intervals`
--

DROP TABLE IF EXISTS `service_intervals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_intervals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maskin_namn` varchar(100) NOT NULL,
  `intervall_ibc` int(11) NOT NULL DEFAULT 5000,
  `senaste_service_datum` datetime DEFAULT NULL,
  `senaste_service_ibc` int(11) NOT NULL DEFAULT 0,
  `skapad` datetime NOT NULL DEFAULT current_timestamp(),
  `uppdaterad` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shift_handover`
--

DROP TABLE IF EXISTS `shift_handover`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shift_handover` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `skift_nr` int(11) NOT NULL,
  `note` text NOT NULL,
  `priority` enum('normal','important','urgent') DEFAULT 'normal',
  `op_number` int(11) DEFAULT NULL,
  `op_name` varchar(100) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `acknowledged_by` int(11) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `audience` enum('alla','ansvarig','teknik') DEFAULT 'alla',
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_skift` (`datum`,`skift_nr`),
  KEY `idx_ack` (`acknowledged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shift_plan`
--

DROP TABLE IF EXISTS `shift_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shift_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `skift_nr` int(11) NOT NULL,
  `op_number` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_op` (`datum`,`skift_nr`,`op_number`),
  KEY `idx_datum` (`datum`),
  KEY `idx_op` (`op_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skift_konfiguration`
--

DROP TABLE IF EXISTS `skift_konfiguration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skift_konfiguration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skift_typ` varchar(20) NOT NULL,
  `start_tid` time NOT NULL,
  `slut_tid` time NOT NULL,
  `min_bemanning` int(11) NOT NULL DEFAULT 2,
  `max_bemanning` int(11) NOT NULL DEFAULT 6,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `skift_typ` (`skift_typ`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skift_schema`
--

DROP TABLE IF EXISTS `skift_schema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skift_schema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operator_id` int(11) NOT NULL,
  `skift_typ` varchar(20) NOT NULL,
  `datum` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operator_datum` (`operator_id`,`datum`),
  KEY `idx_datum_skift` (`datum`,`skift_typ`),
  KEY `fk_skift_typ` (`skift_typ`),
  CONSTRAINT `fk_skift_typ` FOREIGN KEY (`skift_typ`) REFERENCES `skift_konfiguration` (`skift_typ`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skiftoverlamning_logg`
--

DROP TABLE IF EXISTS `skiftoverlamning_logg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skiftoverlamning_logg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operator_id` int(11) NOT NULL COMMENT 'Avgående operatör (user_id)',
  `operator_namn` varchar(100) DEFAULT NULL COMMENT 'Cachat operatörsnamn vid skapande',
  `skift_typ` enum('dag','kvall','natt') NOT NULL DEFAULT 'dag' COMMENT 'dag=06-14, kvall=14-22, natt=22-06',
  `datum` date NOT NULL COMMENT 'Skiftets datum',
  `ibc_totalt` int(11) NOT NULL DEFAULT 0 COMMENT 'Totalt antal IBC detta skift',
  `ibc_per_h` decimal(6,1) NOT NULL DEFAULT 0.0 COMMENT 'IBC per timme',
  `stopptid_min` int(11) NOT NULL DEFAULT 0 COMMENT 'Total stopptid i minuter',
  `kassationer` int(11) NOT NULL DEFAULT 0 COMMENT 'Antal kasserade IBC',
  `problem_text` text DEFAULT NULL COMMENT 'Problem/incidenter under skiftet',
  `pagaende_arbete` text DEFAULT NULL COMMENT 'Pågående arbete att ta vid',
  `instruktioner` text DEFAULT NULL COMMENT 'Specialinstruktioner till nästa skift',
  `kommentar` text DEFAULT NULL COMMENT 'Generell kommentar',
  `checklista_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Checklista-status som JSON-array [{key, label, checked}]' CHECK (json_valid(`checklista_json`)),
  `mal_nasta_skift` text DEFAULT NULL COMMENT 'Produktionsmål och fokusområden för nästa skift',
  `har_pagaende_problem` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 om pågående problem flaggats',
  `allvarlighetsgrad` enum('lag','medel','hog','kritisk') DEFAULT 'medel' COMMENT 'Allvarlighetsgrad för pågående problem',
  `skapad` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_operator` (`operator_id`),
  KEY `idx_skift_typ` (`skift_typ`),
  KEY `idx_pagaende` (`har_pagaende_problem`),
  KEY `idx_skapad` (`skapad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skiftoverlamning_notes`
--

DROP TABLE IF EXISTS `skiftoverlamning_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skiftoverlamning_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skiftraknare` int(11) NOT NULL COMMENT 'PLC-skiftraknare (unikt per skift)',
  `linje` varchar(50) NOT NULL DEFAULT 'rebotling',
  `note_text` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_skift_linje` (`skiftraknare`,`linje`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stoppage_log`
--

DROP TABLE IF EXISTS `stoppage_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stoppage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `line` varchar(50) NOT NULL DEFAULT 'rebotling',
  `reason_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_line` (`line`),
  KEY `idx_reason` (`reason_id`),
  KEY `idx_start` (`start_time`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stoppage_reasons`
--

DROP TABLE IF EXISTS `stoppage_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stoppage_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('planned','unplanned') NOT NULL DEFAULT 'unplanned',
  `color` varchar(7) DEFAULT '#6b7280',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stopporsak_kategorier`
--

DROP TABLE IF EXISTS `stopporsak_kategorier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stopporsak_kategorier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(100) NOT NULL,
  `ikon` varchar(10) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stopporsak_registreringar`
--

DROP TABLE IF EXISTS `stopporsak_registreringar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stopporsak_registreringar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) NOT NULL,
  `linje` varchar(50) NOT NULL DEFAULT 'rebotling',
  `kommentar` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kategori` (`kategori_id`),
  KEY `idx_linje` (`linje`),
  KEY `idx_user` (`user_id`),
  KEY `idx_start` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tvattlinje_ibc`
--

DROP TABLE IF EXISTS `tvattlinje_ibc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tvattlinje_ibc` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `s_count` bigint(20) NOT NULL,
  `ibc_count` int(11) NOT NULL,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `other` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6862 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tvattlinje_onoff`
--

DROP TABLE IF EXISTS `tvattlinje_onoff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tvattlinje_onoff` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `s_count_h` bigint(20) NOT NULL,
  `s_count_l` bigint(20) NOT NULL,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `running` tinyint(1) NOT NULL DEFAULT 0,
  `runtime_today` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=278 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tvattlinje_rast`
--

DROP TABLE IF EXISTS `tvattlinje_rast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tvattlinje_rast` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `rast_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = arbetar, 1 = på rast',
  `rast_today` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total rasttid idag i minuter',
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tvattlinje_settings`
--

DROP TABLE IF EXISTS `tvattlinje_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tvattlinje_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `antal_per_dag` int(11) NOT NULL DEFAULT 150,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `timtakt` int(11) NOT NULL DEFAULT 20,
  `skiftlangd` decimal(4,1) NOT NULL DEFAULT 8.0,
  `setting` varchar(100) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tvattlinje_skiftrapport`
--

DROP TABLE IF EXISTS `tvattlinje_skiftrapport`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tvattlinje_skiftrapport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `antal_ok` int(11) NOT NULL DEFAULT 0,
  `antal_ej_ok` int(11) NOT NULL DEFAULT 0,
  `totalt` int(11) NOT NULL DEFAULT 0,
  `kommentar` text DEFAULT NULL,
  `inlagd` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tvattlinje_weekday_goals`
--

DROP TABLE IF EXISTS `tvattlinje_weekday_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tvattlinje_weekday_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `weekday` tinyint(4) NOT NULL,
  `mal` int(11) NOT NULL DEFAULT 80,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekday` (`weekday`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `underhall_kategorier`
--

DROP TABLE IF EXISTS `underhall_kategorier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `underhall_kategorier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(50) NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `underhall_komponenter`
--

DROP TABLE IF EXISTS `underhall_komponenter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `underhall_komponenter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namn` varchar(100) NOT NULL,
  `maskin` varchar(100) NOT NULL DEFAULT 'Rebotling',
  `kategori` varchar(50) NOT NULL DEFAULT 'Mekaniskt',
  `beskrivning` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `skapad` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_maskin` (`maskin`),
  KEY `idx_aktiv` (`aktiv`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `underhall_scheman`
--

DROP TABLE IF EXISTS `underhall_scheman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `underhall_scheman` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `komponent_id` int(11) NOT NULL,
  `intervall_dagar` int(11) NOT NULL DEFAULT 30,
  `senaste_underhall` datetime DEFAULT NULL,
  `nasta_planerat` datetime DEFAULT NULL,
  `ansvarig` varchar(100) DEFAULT NULL,
  `noteringar` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `skapad` datetime NOT NULL DEFAULT current_timestamp(),
  `uppdaterad` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_komponent_id` (`komponent_id`),
  KEY `idx_senaste_underhall` (`senaste_underhall`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `underhallslogg`
--

DROP TABLE IF EXISTS `underhallslogg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `underhallslogg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `typ` enum('planerat','oplanerat') NOT NULL,
  `varaktighet_min` int(11) NOT NULL,
  `kommentar` text DEFAULT NULL,
  `maskin` varchar(100) NOT NULL DEFAULT 'Rebotling',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_kategori` (`kategori`),
  KEY `idx_typ` (`typ`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_favoriter`
--

DROP TABLE IF EXISTS `user_favoriter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_favoriter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `route` varchar(255) NOT NULL,
  `label` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fas fa-star',
  `color` varchar(20) NOT NULL DEFAULT '#4299e1',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_route` (`user_id`,`route`),
  KEY `idx_user_favoriter_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `code` varchar(30) DEFAULT NULL,
  `admin` tinyint(1) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1,
  `operator_id` int(11) DEFAULT NULL COMMENT 'Kopplar användaren till ett operatörsnummer i rebotling_ibc',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_operator_id` (`operator_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vader_data`
--

DROP TABLE IF EXISTS `vader_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vader_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utetemperatur` decimal(5,2) NOT NULL,
  `datum` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`)
) ENGINE=InnoDB AUTO_INCREMENT=3143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-25 19:30:07

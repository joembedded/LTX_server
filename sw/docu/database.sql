-- SQL statments for initialising login.php and device database
-- 14.02.2024 JoEmbedded.de (New: -- x removed (only 1 ROLE/TOKEN used, Rest: spare!);
-- LTX Table Structure 

-- for Tabelle `users`:
-- (rem: initial remember-flag, confirmed: increments for each mail sent)

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `confirmed`  smallint unsigned DEFAULT 0,
  `rem`  tinyint unsigned,
  `last_seen` timestamp NULL DEFAULT NULL,
  `loggedin`  tinyint DEFAULT 0,
  `remark` varchar(255) COLLATE utf8_unicode_ci,
  `user_role` int unsigned DEFAULT 65535,
  `ticket` varchar(16) COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`), INDEX(`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Start with ID 1001 !!! Run only once! (Check SELECT MAX(id) FROM users)
ALTER TABLE `users` AUTO_INCREMENT = 1001;


-- Table for the available Devices units/vals: composed lines, flags: e.g. alarms
-- counters for transfers,lines,warnings,alarms,error
-- role and email(could be SMS as well) reserved, t.b.d
CREATE TABLE IF NOT EXISTS `devices` (
  `id` int unsigned AUTO_INCREMENT,
  `mac` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `first_seen` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NULL DEFAULT NULL,
  `last_change` timestamp DEFAULT CURRENT_TIMESTAMP,

  `name` varchar(255) COLLATE utf8_unicode_ci,
  `units` varchar(8191) COLLATE utf8_unicode_ci,
  `vals` varchar(8191) COLLATE utf8_unicode_ci,
  `cookie` int unsigned DEFAULT NULL,

  `utc_offset` int DEFAULT 3600,
  
  `lat` float,
  `lng` float,
  `rad` float,
  `last_gps` timestamp NULL DEFAULT NULL,
  `posflags` tinyint unsigned DEFAULT 0, 
  
    
  `transfer_cnt` int unsigned DEFAULT 0,
  `lines_cnt` int unsigned DEFAULT 0,

  `warnings_cnt` int unsigned DEFAULT 0,
  `alarms_cnt` int unsigned DEFAULT 0,
  `err_cnt` int unsigned DEFAULT 0,
  `anz_lines` int unsigned DEFAULT 0,  
  `flags`  smallint unsigned DEFAULT 0, 

  `owner_id` int unsigned DEFAULT NULL,
  `fw_key` varchar(32) COLLATE utf8_unicode_ci,
  `ow_role` int unsigned DEFAULT 65535,
  
  `quota_cnt` int unsigned DEFAULT 0,
  `quota_flags` int unsigned DEFAULT 0,
  `timeout_warn` int unsigned DEFAULT 0,
  `timeout_alarm` int unsigned DEFAULT 0,
  
  `vbat0` float DEFAULT 0,
  `vbat100` float DEFAULT 0,
  `cbat` float DEFAULT 0,
  
  `role0` int unsigned DEFAULT 0,
  `token0` varchar(16) COLLATE utf8_unicode_ci,
-- x   `role1` int unsigned DEFAULT 0,
-- x   `token1` varchar(16) COLLATE utf8_unicode_ci,
-- x   `role2` int unsigned DEFAULT 0,
-- x   `token2` varchar(16) COLLATE utf8_unicode_ci,
-- x   `role3` int unsigned DEFAULT 0,
-- x   `token3` varchar(16) COLLATE utf8_unicode_ci,
  
  `email0` varchar(255) COLLATE utf8_unicode_ci,
  `cond0` varchar(255) COLLATE utf8_unicode_ci,
  `em_date0` timestamp NULL DEFAULT NULL,
  `em_cnt0` int unsigned DEFAULT 0,

-- x   `email1` varchar(255) COLLATE utf8_unicode_ci,
-- x   `cond1` varchar(255) COLLATE utf8_unicode_ci,
-- x   `em_date1` timestamp NULL DEFAULT NULL,
-- x   `em_cnt1` int unsigned DEFAULT 0,

-- x   `email2` varchar(255) COLLATE utf8_unicode_ci,
-- x   `cond2` varchar(255) COLLATE utf8_unicode_ci,
-- x   `em_date2` timestamp NULL DEFAULT NULL,
-- x   `em_cnt2` int unsigned DEFAULT 0,

-- x   `email3` varchar(255) COLLATE utf8_unicode_ci,
-- x   `cond3` varchar(255) COLLATE utf8_unicode_ci,
-- x   `em_date3` timestamp NULL DEFAULT NULL,
-- x   `em_cnt3` int unsigned DEFAULT 0,

  PRIMARY KEY (`id`), UNIQUE (`mac`), INDEX(`mac`), INDEX(`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Guest-Devices --
CREATE TABLE IF NOT EXISTS `guest_devices` (
  `id` int unsigned AUTO_INCREMENT,
  `guest_id` int unsigned DEFAULT 0,
  `mac` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `token` varchar(16) COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`), INDEX(`guest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

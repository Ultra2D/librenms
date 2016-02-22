CREATE TABLE IF NOT EXISTS `ports_fdb` ( `port_id` int(11) NOT NULL, `mac_address` varchar(32) CHARACTER SET utf8 collate utf8_general_ci NOT NULL, `first_discovered` timestamp NULL DEFAULT NULL, `last_discovered` timestamp NULL DEFAULT NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `ports_fdb` ADD INDEX ( `mac_address` );

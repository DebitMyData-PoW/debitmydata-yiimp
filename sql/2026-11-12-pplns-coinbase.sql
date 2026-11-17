-- Coinbase payouts: PPLNS and solo paid in the block itself (stratum/pplns.h).
-- Run once, before installing the new stratum and website:
--   mysql yiimpfrontend < 2026-11-12-pplns-coinbase.sql

-- shares mined for a solo coinbase are kept out of the PPLNS window
ALTER TABLE `shares` ADD `solo` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `shares` ADD INDEX `pplns1` (`coinid`, `valid`, `solo`, `id`);

-- where each coin stands in the switch from PROP
CREATE TABLE IF NOT EXISTS `pplns_state` (
  `coinid` INT(11) NOT NULL,
  `prop_paid_id` BIGINT(30) NULL DEFAULT NULL COMMENT 'website: last share id paid by a PROP block',
  `switch_time` INT(11) NULL DEFAULT NULL COMMENT 'stratum: when coinbase payouts started',
  `floor_id` BIGINT(30) NULL DEFAULT NULL COMMENT 'stratum: shares up to this id are paid',
  `closed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'stratum: PROP round closed, PPLNS from now on',
  `updated` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`coinid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- who each block pays, recorded by the stratum when it finds the block
CREATE TABLE IF NOT EXISTS `coinbase_splits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `coinid` INT(11) NOT NULL,
  `height` INT(11) NOT NULL,
  `blockhash` VARCHAR(128) NOT NULL,
  `mode` TINYINT(1) NOT NULL COMMENT '0 PROP by the website, 1 closes the PROP round, 2 PPLNS, 3 solo',
  `userid` INT(11) NOT NULL COMMENT '0: the pool (fees and rounding)',
  `amount` DOUBLE NOT NULL,
  `in_coinbase` TINYINT(1) NOT NULL COMMENT '1: paid in the block itself, 0: paid the normal way',
  `share_from` BIGINT(30) NULL DEFAULT NULL,
  `share_to` BIGINT(30) NULL DEFAULT NULL,
  `time` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `block` (`coinid`, `blockhash`),
  KEY `user` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- PROP pays each share once from now on. Shares already paid by the website's last block, per coin:
-- those written before that block was credited (accounts.last_earning is set when a block is credited).
-- Install the new website files BEFORE running this file, so no block is credited the old way after it.
INSERT IGNORE INTO `pplns_state` (`coinid`, `prop_paid_id`, `updated`)
SELECT c.id,
  (SELECT MAX(s.id) FROM `shares` s WHERE s.coinid = c.id AND s.time < t.last_credit),
  UNIX_TIMESTAMP()
FROM `coins` c
JOIN (SELECT coinid, MAX(last_earning) AS last_credit FROM `accounts` WHERE coinid IS NOT NULL AND last_earning IS NOT NULL GROUP BY coinid) t
  ON t.coinid = c.id
WHERE c.enable = 1;

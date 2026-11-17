-- Notifications of the pool app (web push): the devices that asked for them, and where each one
-- is in the lists of earnings, payouts and blocks. Run once: sudo mysql yiimpfrontend < this file.
-- Nothing else in the database changes, and the pool runs as before until the keys are set up.

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,                       -- accounts.id: the address this device follows
  `endpoint` varchar(1024) NOT NULL,               -- where the browser's push service takes the notices
  `endpoint_hash` char(64) NOT NULL,               -- sha256 of endpoint (unique: one row per device)
  `p256dh` varchar(128) NOT NULL,                  -- the browser's public key (base64url)
  `auth` varchar(64) NOT NULL,                     -- the browser's secret (base64url)
  `wants` tinyint(3) unsigned NOT NULL DEFAULT 3,  -- 1 blocks that pay me, 2 payouts, 4 every block the pool finds
  `created` int(10) unsigned NOT NULL,
  `earn_cursor` int(10) unsigned NOT NULL DEFAULT 0,    -- last earnings.id told
  `payout_cursor` int(10) unsigned NOT NULL DEFAULT 0,  -- last payouts.id told
  `block_cursor` int(10) unsigned NOT NULL DEFAULT 0,   -- last blocks.id told (every block)
  `earn_sent` int(10) unsigned NOT NULL DEFAULT 0,      -- when each kind was last sent (one notice per 15 minutes)
  `payout_sent` int(10) unsigned NOT NULL DEFAULT 0,
  `block_sent` int(10) unsigned NOT NULL DEFAULT 0,
  `failures` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
  KEY `userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

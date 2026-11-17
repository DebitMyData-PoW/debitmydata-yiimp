-- "Send a test notice" on the wallet page: when a device asked for one (the blocks job sends it
-- and clears it). Run once: sudo mysql yiimpfrontend < this file
ALTER TABLE `push_subscriptions` ADD COLUMN `test_at` int(10) unsigned NOT NULL DEFAULT 0;

# Installing the DebitMyData mining pool

## With the installer (recommended)

The pool runs on **Ubuntu 24.04 with PHP 8.3** (Ubuntu's own), MariaDB 10.11 and nginx, in the
multipool installer's layout (`/home/crypto-data/yiimp/site`). The
[DebitMyData pool installer](https://github.com/DebitMyData-PoW/Multi-Pool-Installer) sets it all
up on a fresh server, from this repository's `main` branch: the website, the database, the stratum
with the DMD port, and the notifications. Follow its
[INSTALL.md](https://github.com/DebitMyData-PoW/Multi-Pool-Installer/blob/master/INSTALL.md).

The website runs on Yii 1.1.32 (`web/framework`), with YiiMP's few changes to it.

## The database changes

On a pool installed some other way, or to bring an older YiiMP database up to date, DebitMyData's
database changes in `sql/` run once each, in this order (the second one makes the table that the
third one changes), after YiiMP's own:

```
sudo mysql yiimpfrontend < sql/2026-11-12-pplns-coinbase.sql
sudo mysql yiimpfrontend < sql/2026-11-13-push.sql
sudo mysql yiimpfrontend < sql/2026-11-12-push-test.sql
```

## Not in this repository

This repository is public. These stay on the server only:

- **Settings and secrets**: the installer's `configuration/` folder next to `web/`
  (`serverconfig.php`, and `push.php` with the path of the web push private key), `keys.php`,
  and the stratum's `config/*.conf` (database password). The `.gitignore` keeps them out; the
  installer writes them.
- **Licensed fonts and icons**: Font Awesome Pro (`web/yaamp/ui/css/all.css`,
  `web/yaamp/ui/js/all.js`, `web/yaamp/ui/webfonts/`) and the commercial display fonts in
  `web/yaamp/ui/fonts/`. Their licenses don't allow publishing them: bring your own licensed
  copies, or replace them. Mada, under the SIL Open Font License, is included.

## The pool app on Google Play (optional)

The website installs as an app from the browser. To also publish it on Google Play (a Bubblewrap
app), list its package name in `web/manifest.json` (`related_applications`; the installer asks for
it), and put the app's signing key in `web/.well-known/assetlinks.json` (the package name and the
SHA-256 fingerprint of the Play app signing key, from Play Console, App integrity), so that it
opens without the browser's bar.

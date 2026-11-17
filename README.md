# DebitMyData mining pool (YiiMP)

The code of DebitMyData's official mining pool, **[pool.debitmydata.io](https://pool.debitmydata.io)**:
mining DMD, the native coin of the DebitMyData PoW chain (SHA-256), with PPLNS payouts
paid in the block itself.

It is based on [YiiMP](https://github.com/tpruvot/yiimp) by tpruvot, as continued by
cryptopool.builders and [le2Ks](https://github.com/le2Ks/yiimp). Their full history is kept in
this repository.

## What DebitMyData added

- **PPLNS paid in the coinbase**, with an optional solo mode (password `m=solo`):
  `stratum/pplns.cpp`, `stratum/pplns.h`, `sql/2026-11-12-pplns-coinbase.sql`.
- **My wallet** (`/mywallet/<address>`): a miner's workers, earnings, payouts and blocks, with
  notifications on the phone or the computer (web push): `web/yaamp/modules/mywallet/`,
  `web/yaamp/core/backend/push.php`, `sql/2026-11-13-push.sql`, `sql/2026-11-12-push-test.sql`.
- **An installable app** (manifest and service worker): `web/manifest.json`, `web/sw.js`,
  `web/images/app/`.
- **DebitMyData's design**, in light and dark: `web/yaamp/ui/css/dmd-pool.css`,
  `web/yaamp/ui/dmd-app.php`, `web/yaamp/ui/main.php`.
- Pages the pool doesn't use (renting, trading, benchmarks, stats and a few others) were
  removed.

## Running it

It installs on **Ubuntu 24.04 with PHP 7.4**, with the multipool installer's layout
(`/home/crypto-data/yiimp/site`). PHP 8.x support is being worked on in the `ubuntu-24.04`
branch.

The database changes in `sql/` run once each, in date order: `sudo mysql yiimpfrontend < file`.

## Not in this repository

This repository is public. These stay on the server only:

- **Settings and secrets**: the installer's `configuration/` folder next to `web/`
  (`serverconfig.php`, and `push.php` with the path of the web push private key), `keys.php`,
  and the stratum's `config/*.conf` (database password). The `.gitignore` keeps them out.
- **Licensed fonts and icons**: Font Awesome Pro (`web/yaamp/ui/css/all.css`,
  `web/yaamp/ui/js/all.js`, `web/yaamp/ui/webfonts/`) and the commercial display fonts in
  `web/yaamp/ui/fonts/`. Their licenses don't allow publishing them. Mada, under the SIL Open
  Font License, is included.

## Security

To report a security problem, write to info@debitmydata.com with the subject line
`Security: DebitMyData pool`. Please don't open a public issue for it.

## Credits

- [tpruvot](https://github.com/tpruvot/yiimp), for YiiMP
- cryptopool.builders and Original-Tasty, for their fork and installer
- [le2Ks](https://github.com/le2Ks/yiimp), for the version this pool was installed from

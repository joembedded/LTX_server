# LTX Microcloud Server

**Server version**

LTX can be installed in two modes:

- **LTX_Server**: with database support.
- **LTX_Legacy**: without database support.

In **LTX_Legacy** mode, all data is written to directories. Each device's new data is appended to `.../out_total/total.edt`. This file is plain text in `EDT` format and can become quite large over time. The input script `sw/ltu_trigger.php` adds the data.

Note: using `CS_VIEW.PHP` for graphs requires PHP's `gdlib` extension to be enabled.

In **LTX_Server** mode, all new data is written to the database. The quota limit is configured in `./sw/conf/api_key.inc.php` via `DB_QUOTA` (default: `"90\n1000"`). For each new logger, a `quota_days.dat` file with two or three lines is created automatically:

1. Number of days to keep, for example `90`.
2. Maximum number of database lines per device, for example `1000`.
3. Optional URL for PUSH notifications on new data. This is used only by `LTX_Server`.

The input script `sw/ltu_trigger.php` automatically removes older data. For example, set `DB_QUOTA` to `"365\n100000"` to keep only the last 365 days or a maximum of 100,000 lines per device. `quota_days.dat` can be changed per logger at any time.

LTX Microcloud adapts the maximum upload size for files with Autosync, such as logger data, to the network speed. `2G/LTE-M` is faster than `LTE-NB`. Configure the two `define()` values `MAXM_2GM` and `MAXM_NB`; the defaults are `20k` and `5k` bytes.

For rare transmission intervals with high logging intervals, increase these limits to ensure that all data is transferred. SSL encryption over slow connections such as `LTE-NB` might work, but is not recommended.

New in V2.23: by default, all devices use the same `D_API_KEY`. This is acceptable for small or closed systems. Larger systems can optionally use individual keys, attached to the MAC and checked via an external API.

![LTX Gdraw tool](./docs_raw2edit/G-Draw.jpg "LTX Gdraw tool")

## Documentation

- Details: [Installation of LTX Database](./sw/docu/installation_LTX_database.md "Details")
- Live demo: [LTX demo](https://joembedded.de/ltx/sw/login.php) (`demo` / `123456`)

More documentation is available in the media browser:

- LTX Cloud Overview: [LTX Overview](./docs_raw2edit/LTX_Cloud_V1.pdf "LTX Overview")
- LTX Alarme (only DE): [LTX Alarme (DE)](./docs_raw2edit/LTX_AlarmeDE_V1.pdf "LTX Alarme (DE)")
- LTX API (only DE): [LTX PushPull-API (DE)](./docs_raw2edit/LTX_PushPull.pdf "LTX PushPull-API (DE)")

---

## Third-party Software

- PHP QR Code: <https://sourceforge.net/projects/phpqrcode> (LGPL)
- jQuery: <https://jquery.org/license/> (MIT)
- Font Awesome: <https://fontawesome.com/license/free> (MIT, SIL OFL, CC BY 4.0)
- FileSaver: <https://github.com/eligrey/FileSaver.js/blob/master/LICENSE.md> (MIT)

---

## Changelog

- V1.00 04.12.2020 Initial
- V1.01 06.12.2020 Checked for PHP 8 compatibility
- V1.02 08.12.2020 Docs added
- V1.10 09.01.2021 More docs added
- V1.50 08.12.2022 SWARM packet driver added
- V1.52 20.01.2023 ASTROCAST packet driver added
- V1.60 21.01.2023 Push URL first draft
- V1.70 11.01.2023 PushPull pre-release in PHP
- V1.71 06.02.2023 Database UTC
- V1.72 11.02.2023 GPS_View.html cosmetics
- V1.73 21.02.2023 Push/Pull `w_pcp.php` V1.05 released
- V1.74 28.03.2023 Fixed `undefined` in `gps_view.js`
- V1.75 20.04.2023 `w_pcp.php` V1.07
- V1.76 06.06.2023 Access Legacy for admin users
- V1.77 28.06.2023 Added `sw/js/xtract_demo.html`: demo to access BLE_API data in IndexDB
- V1.78 15.08.2023 Device timeouts in `service.php`
- V1.79 05.10.2023 Added `CommandConfig` as new parameter in `iparam.lxp`
- V2.00 15.10.2023 Direct FTP/FTPSSL push via `CommandConfig`
- V2.01 18.10.2023 Cosmetics and FTP push (only `LTX_Server`)
- V2.10 19.10.2023 Decoding of compressed lines, starting with `$` + Base64, added
- V2.20 02.11.2023 Legacy CSView UTF-8 cosmetics and removed database fields `token`, `mail`, `cond..1-3` (see `...sql_docu.txt`)
- V2.21 04.11.2023 Added network details (2G/4G/...)
- V2.22 05.11.2023 Max. upload limit depending on network; set `MAXM_xx` in `api_key.inc.php`
- V2.23 25.11.2023 If `DAPIKEY_SERVER` is defined: individual external `D_API_KEY` check for each new device (only once)
- V2.24 28.11.2023 Additional FTP export formats (`sw/vpnf/ipush.php`)
- V2.25 11.12.2023 Added `quotaget`/`quotachange` in `sw/php/w_rad.php`
- V2.26 20.01.2024 Added onboarding/remove commands in `sw/php/w_rad.php` (more commands: todo; see `LTX_PushPull.pdf`, V1.11)
- V2.27 22.01.2024 Optimised onboarding/remove commands
- V2.28 14.03.2024 Error fix (`lxu_trigger.php`)
- V2.29 06.04.2024 Cosmetics
- V2.30 17.04.2024 Cosmetics
- V2.31 13.05.2024 Drivers for SWARM (product shut down) and ASTROCAST removed
- V2.32 24.05.2024 Added drivers from ORBCOMM IGWS2 (INMARSAT)
- V2.33 09.09.2024 Selectable sec/min/hr and started internationalisation in UI
- V2.50 11.10.2024 Added German/English translations for `intern_main.html`
- V2.51 18.11.2024 Added EcoWitt/Wunderground data upload for weather stations
- V2.52 05.12.2024 Added optional `MXGET_MEM` (extends `MAXM_xx` from V2.22)
- V2.53 25.06.2025 Added LoRaWAN support for ChirpStack V4 and TTN V3 (`lxu_ltxlora_v1.php`)

# LTX Microcloud **SERVER** #
**Server Version**

LTX can be installed WITH (named as "LTX_Server") Database and WITHOUT (named as "LTX_Legacy").

In case of "LTX_Legacy" all data will be sent to directories and ALL device's new data will
be added to a file '.../out_total/total.edt' for the device. 
This file is simple text ('EDT'-Format) and might become quite large over time ;-)
The input script 'sw\ltu_trigger.php' will add the data.
Note: using "CS_VIEW.PHP' for graphs requires PHP's gdlib extension enabled.

In case of "LTX_Server" all new data will be written to the database. There is a quota limit in
'./sw/conf/api_key.inc.php' ("DB_QUOTA" with default "90\n1000"). A file 'quota_days.dat' with 2-3 lines
will automatically be written for each new logger, 1.st line are days (here 90), 2.nd line is lines (in the database, so even a small DB can hold thousands of devices).
The optional 3.rd line is an URL where to send a PUSH notification on new data (only used for LTX_Server).
The input script 'sw\ltu_trigger.php' will automatically remove older data.
Change e.g. to "365\n100000" to allow only the last 365 days or max. 100000 lines per device.
The file 'quota_days.dat' my be set to individual values per logger at any time.

LTX Microcloud adapts maximum upload size for files with Autosync (e.g. logger data) to Network speed (2G/LTE-M is faster than LTE-NB). Set the 2 defines() for "MAXM_2GM"/"MAXM_NB". Default 20k/5k Bytes.
For are transmission intervals at high logging intervals it should be increased to get always all data. 
Please note: using SSL encryption if slow connections are enabled (LTE-NB) might work, but is not recommended.

New in V.23: By default all devices use the same D_API_KEY. This OK for small or closed systems. Optionally new devices can use individual keys (attached to MAC and checked via external API) for larger systems.


![LTX Gdraw tool](./docs_raw2edit/G-Draw.jpg "LTX Gdraw tool")

Details, see: ['sw\docu\installation_LTX_database.md'](./sw/docu/installation_LTX_database.md "Details...")

Live demo LTX: ['https://joembedded.de...' (User: 'demo', Password: '123456')](https://joembedded.de/ltx/sw/login.php)

More docus in the Media-Browser:

LTX Cloud Overview: ['LTX Overview'](./docs_raw2edit/LTX_Cloud_V1.pdf "LTX Overview")

LTX Alarme (only DE): ['LTX Alarme (DE)'](./docs_raw2edit/LTX_AlarmeDE_V1.pdf "LTX Alarme (DE)")

LTX API (only DE): ['LTX PushPull-API (DE)'](./docs_raw2edit/LTX_PushPull.pdf "LTX PushPull-API (DE)")

---

## 3.rd Party Software ##
- PHP QR Code https://sourceforge.net/projects/phpqrcode License: LGPL
- jQUERY https://jquery.org/license/  License: MIT
- FontAwesome https://fontawesome.com/license/free License: MIT, SIL-OFL, CC-BY-4.0
- FileSaver https://github.com/eligrey/FileSaver.js/blob/master/LICENSE.md License: MIT

---

## Changelog ##
- V1.00 04.12.2020 Initial
- V1.01 06.12.2020 Checked for PHP8 compatibility
- V1.02 08.12.2020 Docs added
- V1.10 09.01.2021 More Docs added
- V1.50 08.12.2022 SWARM Packet driver added
- V1.52 20.01.2023 ASTOROCAST Packet driver added
- V1.60 21.01.2023 Push-URL first draft
- V1.70 11.01.2023 PushPull Pre-Release in PHP
- V1.71 06.02.2023 Database UTC
- V1.72 11.02.2023 GPS_View.html cosmetics
- V1.73 21.02.2023 Push/Pull w_pcp.php V1.05 released
- V1.74 28.03.2023 Fixed 'undefined' in gps_view.js
- V1.75 20.04.2023 w_pcp.php V1.07
- V1.76 06.06.2023 Access Legacy for Admin Users
- V1.77 28.06.2023 Added sw/js/xtract_demo.html: demo to access BLE_API data in IndexDB
- V1.78 15.08.2023 Device Timouts in service.php
- V1.79 05.10.2023 Added CommandConfig as new Parameter in 'iparam.lxp'
- V2.00 15.10.2023 Direct FTP/FTPSSL-Push via CommandConfig
- V2.01 18.10.2023 Cosmetics and FTP-push (only 'LTX_Server')
- V2.10 19.10.2023 Decoding of compressed lines (starting with '$'+Base64) added
- V2.20	02.11.2023 Legacy CSView UTF-8 cosmetics and removed in Database 'token/mail/cond..1-3' (see ...sql_docu.txt)
- V2.21	04.11.2023 Added Network Details (2G/4G/..) 
- V2.22 05.11.2023 Max. upload limit depending on Network, set defines(MAXM_xx) in 'api_key.inc.php!
- V2.23 25.11.2023 If DAPIKEY_SERVER defined: indivdual external D_API_KEY check for each NEW device  (only once)
- V2.24 28.11.2023 Additional FTP-Export formats (sw/vpnf/ipush.php)
- V2.25 11.12.2023 Added 'quotaget/quotachange' in sw/php/w_rad.php 
- V2.26 20.01.2024 Added onboarding/remove commands in sw/php/w_rad.php (more cmd: *todo*) (see LTX_PushPull.pdf, V1.11)
- V2.27 22.01.2024 Optim. onboarding/remove commands
- V2.28 14.03.2024 ErrorFix (lxu_trigger.php)


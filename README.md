# LTX Microcloud **SERVER** #
**Server Version**

LTX can be installed WITH (named as "LTX_Server") Database and WITHOUT (named as "LTX_Legacy").

In case of "LTX_Legacy" all data will be sent to directories and ALL device's new data will
be added to a file '.../out_total/total.edt' for the device. 
This file is simple text ('EDT'-Format) and might become quite large over time ;-)
The input script 'sw\ltu_trigger.php' will add the data.

In case of "LTX_Server" all new data will be written to the database. There is a quota limit in
'./sw/conf/api_key.inc.php' ("DB_QUOTA" with default "3650\n100000000"). A file 'quota_days.dat' with 2-3 lines
will automatically be written for each new logger, 1.st line are days (here 3650), 2.nd line is lines (in the database).
The optional 3.rd line is an URL where to send a PUSH notification on new data (only used for LTX_Server).
The input script 'sw\ltu_trigger.php' will automatically remove older data.
Change e.g. to "90\n1000" to allow only the last 90 days or max. 1000 lines per device (so even a small DB can hold thousands of devices).
The file 'quota_days.dat' my be set to individual values per logger at any time.


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


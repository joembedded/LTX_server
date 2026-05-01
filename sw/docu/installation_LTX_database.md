# Installation of LTX Database

**File:** `./sw/docu/installation_LTX_database`

**21.05.2025 Jo**

LTX can be installed in two modes:

- **LTX_Server**: with database support.
- **LTX_Legacy**: without database support.

## System Requirements

The system requirements are very low for both `LTX_Server` and `LTX_Legacy`:

- Standard LAMP or WAMP stack, as included with many web hosting packages.
- Linux or Windows.
- Apache or any other web server.
- PHP, version 7 or newer recommended.

Additional requirements for `LTX_Server`:

- One MySQL, MariaDB, or compatible SQL database.
- HTTPS for user access. Device data may be uploaded via HTTP, which requires less energy.

## Data Storage

In **LTX_Legacy** mode, all data is written to directories. Each device's new data is appended to `.../out_total/total.edt`. Each device is identified by an 8-byte MAC. This file is plain text in `EDT` format and can become quite large over time.

The input script `sw/ltu_trigger.php` adds the data.

In **LTX_Server** mode, all new data is written to the database. The quota limit is configured in `../sw/conf/api_key.inc.php` via `DB_QUOTA` (default: `"3650\n100000000"`). For each new logger or device, a `quota_days.dat` file with two or three lines is created automatically:

1. Number of days to keep, for example `3650`.
2. Maximum number of database lines per device, for example `100000000`.
3. Optional URL for PUSH notifications on new data.

The input script `sw/ltu_trigger.php` automatically removes older data. For example, set `DB_QUOTA` to `"90\n1000"` to keep only the last 90 days or a maximum of 1,000 lines per device. Even a small database can then hold thousands of devices.

`quota_days.dat` can be edited per logger at any time.

## Important

This repository (`LTX_Server`) is automatically generated and maintained by scripts. No feedback to issues, requests, or comments.

## Installation

1. Create or use a standard empty SQL/MySQL/MariaDB database. Note all credentials.

2. Copy all files to your server. The server normally runs HTTP only, by default on port 80.

   Activate HTTPS/SSL for the domain so that it supports both HTTP and HTTPS. Devices access the server via HTTP. HTTPS as device access protocol will follow with the next release. The frontend supports only HTTPS, so it is recommended to make the server reachable by HTTP and HTTPS under the same name.

3. Modify `./sw/conf/api_key.inc.php` as described in its comments. Most settings can be changed later. The most important values are:

   - Set `S_DATA` to your own directory, for example `../xxx_secret_dir`.
   - Set `L_KEY` to your own login key for the legacy part of the software.

4. Set the access parameters in `./sw/conf/config.inc.php` as described in its comments:

   - The first entry, `192.168..`/`localhost`, is for local use, for example with an XAMPP development kit.
   - The second entry, `xyz.com`, is for use on your server. Replace all `xyz.com` values with your domain. `DB_HOST` is the database host from step 1.

5. Run `./sw/setup.php`. This script can be run only once and installs an administrator.

   The script runs only if the database is completely empty. Optionally clear an existing database, for example with phpMyAdmin.

   Remark: by default, the database username and password are used for the admin account.

6. Set the server name and path in the `sys_param.lxp` file on the device.

7. Make a test transmission.

8. Periodically, for example once per day, call `./sw/service/service.php` to clean and check the database. A mail is sent to the admin with a short summary.

   Note: for CRON, `$_SERVER['SERVER_NAME']`, `$_SERVER['REMOTE_ADDR']`, and similar values may need to be set in `service.php`. See the comments at the beginning of `service.php`.

   Hints:

   - CRON command example: `/bin/php ./JoEmbedded_Run/ltx/sw/service/service.php`
   - Manual call/tests for `service.php`: `./sw/service/index.php`
   - URL example: `http(s)://myltx.com/ltx/sw/service/service.php`

9. Only for ORBCOMM: periodically, for example once per minute, call `./sw/lxs_obc_v1.php?k=S_API_KEY` to poll for satellite messages.

   Use the same scheme as step 8. `S_API_KEY` is configured in `api_key.inc.php`.

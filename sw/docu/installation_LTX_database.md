### Installation of LTX Database (File: "\sw\docu\installation_LTX_database") ###

### 07.12.2020 Jo ###

LTX can be installed WITH (named as "LTX_server") Database and WITHOUT (named as "LTX_legacy").

In case of "LTX_legacy" all data will be sent to directories and ALL device's new data will
be added to a file ".../out_total/total.edt" for the device. 
This file is simple text ('EDT'-Format) and might become quite large oer time ;-)
The input script 'sw\ltu_trigger.php' will add the data.

In case of "LTX_server" all new data will be written to the database. There is a quota limit in 
'sw\conf\api_key.inc.php' ("DB_QUOTA" with default "90\n1000"). A file 'quota_days.dat' with 2 lines
will automatically be written for each new logger, 1.st line are days (here 90), 2.nd line is lines (in the database).
The input script 'sw\ltu_trigger.php' will automatically remove older data.

 1. Create or use a standard SQL/MYSQL Database, as included with many Hosting Packages
 2. Set Access Parameters in "sw\conf\config.inc.php"
 3. Run "sw\setup_db.php"- This script can be run ONLY once and will install an Administrator
    This Script will ONLY run, if the database is COMPLETELY empty!
	_Remark: By default: the Database's Username and Password will be used for ADMIN_ 
	
***


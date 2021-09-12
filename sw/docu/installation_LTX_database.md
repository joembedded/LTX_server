### Installation of LTX Database (File: './sw/docu/installation_LTX_database') ###

### 09.09.2021 Jo ###

LTX can be installed WITH (named as "LTX_server") Database and WITHOUT (named as "LTX_legacy").

In case of "LTX_legacy" all data will be sent to directories and ALL device's new data will
be added to a file '.../out_total/total.edt' for the device. 
This file is simple text ('EDT'-Format) and might become quite large over time ;-)
The input script 'sw\ltu_trigger.php' will add the data.

In case of "LTX_server" all new data will be written to the database. There is a quota limit in
'../sw/conf/api_key.inc.php' ("DB_QUOTA" with default "3650\n100000000"). A file 'quota_days.dat' with 2 lines
will automatically be written for each new logger, 1.st line are days (here 3650), 2.nd line is lines (in the database).
The input script 'sw\ltu_trigger.php' will automatically remove older data.
Change e.g. to "90\n1000" to allow only the last 90 days or max. 1000 lines per device (so even a small DB can hold thousands of devices).
The file 'quota_days.dat' my be set to individual values per logger at any time.

***Installation:*** 

 1. Create or use a standard SQL/MYSQL Database, as included with many Hosting Packages

 2. Simply copy all to your server, Server must run HTTP (by default port 80) and additional HTTPS is recommended.
... Devices access the Server via HTTP (HTTPS as access protocol for the devices will follow with the next release),
... The Frontend supports only HTTPS, so it is a good idea to make the Server reachable by HTTP and HTTPS with the same name.

 3. Modify './sw/conf/api_key.inc.php' as in comments

 4. Set Access Parameters in './sw/conf/config.inc.php' as in comments

 5. Run './sw/setup.php'- This script can be run ONLY once and will install an Administrator
    This Script will ONLY run, if the database is COMPLETELY empty! 
	(optionally clear existing database w.g. with PHPAdmin)
... _Remark: By default: the Database's Username and Password will be used for ADMIN_ 

 6. Set your Server name and path in the 'sys_param.lxp' file on the device. 

 7. Make a test transmition
 
 8. Peridocally (e.g. each day) call './sw/service/service.php?cmd=service' to clean/check up Database!
    A Mail will be sent to the (Admin) with a short summary.
	
***


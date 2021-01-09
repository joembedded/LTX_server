<?php

// Edit your SQL Server Setup here:
if($_SERVER['SERVER_NAME'] =="192.168.1.252" || $_SERVER['SERVER_NAME'] =="localhost"){
	// Test-Server (local, e.g. XAMPP)
	define ("DB_HOST","localhost");
	define ("DB_NAME","ltx_local");
	define ("DB_USER","root");
	define ("DB_PASSWORD","");
	define ("HTTPS_SERVER",null); // null only for localhost
	define ("AUTOMAIL","no-reply@localhost.com");
	define ("SERVICEMAIL","me@localhost.com");
}else if($_SERVER['SERVER_NAME'] =="xyz.com"){
	// Working Server (by Provider)
	define ("DB_HOST","rdbms.xyz.com");
	define ("DB_NAME","DBxxxyyyy");
	define ("DB_USER","Uxxxxyyyy");
	define ("DB_PASSWORD","xxxxxxx");
	define ("HTTPS_SERVER","xyz.com"); // HTTPS name of Server
	define ("AUTOMAIL","no-reply@xyz.com");
	define ("SERVICEMAIL","me@xyz.com");
}

define ("STR_CRYPT_KEY","1234ABCDxxxxAAAA"); // exactly 16 Chars for encrypting Strings

?>
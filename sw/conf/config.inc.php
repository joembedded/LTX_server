<?php

// Edit your SQL Server Setup here:
if($_SERVER['SERVER_NAME'] =="192.168.1.252" || $_SERVER['SERVER_NAME'] =="localhost"){
	// Test-Server (local, e.g. XAMPP)
	define ("DB_HOST","localhost");
	define ("DB_NAME","ltx_local");
	define ("DB_USER","root");
	define ("DB_PASSWORD","");
	define ("HTTPS_SERVER",null);
	define ("AUTOMAIL","no-reply@localhost.com");
	define ("SERVICEMAIL","me@localhost.com");
}else if($_SERVER['SERVER_NAME'] =="xxxx.com"){
	// Working Server (by Provider)
	define ("DB_HOST","rdbms.xxxx.com");
	define ("DB_NAME","DBxxxyyyy");
	define ("DB_USER","Uxxxxyyyy");
	define ("DB_PASSWORD","xxxxxxx");
	define ("HTTPS_SERVER","xxxxxxx");
	define ("AUTOMAIL","no-reply@jxxxx.com");
	define ("SERVICEMAIL","me@xxxx.com");
}

define ("STR_CRYPT_KEY","1234ABCDxxxxAAAA"); // exactly 16 Chars for encrypting Strings

?>
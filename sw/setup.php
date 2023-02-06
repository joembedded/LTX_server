<?php
// --- setup.php - Database Setup 05.02.2023 (C) joembedded.de ---

error_reporting(E_ALL);

include("conf/api_key.inc.php");
include("conf/config.inc.php");	// DB Access Param
include("inc/db_funcs.inc.php");	// DB Access Param
include("lxu_loglib.php");


// --------- MAIN ------------
echo "================================<br>";
echo "LTX Database Setup...<br>";
echo "================================<br>";

$now = time();						// one timestamp for complete run
$xlog = "";
try {
	check_dirs();
	db_init();

	// Check DB Time
	$statement = $pdo->prepare("SELECT NOW() as now;");
	$statement->execute();
	$dnow = $statement->fetch(); 
	echo "Database Time (should be UTC): NOW: '".$dnow['now']."'<br>";

	$statement = $pdo->prepare("SHOW TABLES");
	$qres = $statement->execute();
	if ($qres !== false) {	// !false: Result of Operation OK
		$cnt = 0;
		for (;;) {
			$table = $statement->fetch(); // If !false: Result available
			if ($table === false) break;
			$cnt++;
			//echo "Table: '".$table."'<br>";
		}
		if ($cnt) {
			echo "================================<br>";
			echo "FATAL ERROR: Database NOT Empty ($cnt Tables)!<br>";
			echo "================================<br>";
			$xlog = "(FATAL ERROR: Database NOT Empty!)";
			echo "<a href='login.php'>Login to LTX...</a><br>";
			add_logfile();
			exit();
		}
	}

	echo "Connection to Database 'mysql:host='" . DB_HOST . "';dbname='" . DB_NAME . "';charset=utf8' OK<br>";

	/* Just as INFO
	echo "Database User: '".DB_USER."'<br>";
	echo "Database Password: '".DB_PASSWORD."'<br>";
	*/

	echo "Loading init-statement ('./docu/database.sql')<br>";
	$init_sql = file_get_contents("docu/database.sql");

	$statement = $pdo->prepare($init_sql);
	$qres = $statement->execute();
	if ($qres !== false) {
		$xlog .= "(Create Initial Tables OK)";
		echo "Create Initial Tables OK<br>";

		// Create ADMIN : Same User/PW as Database!
		$admin_un = DB_USER;
		$admin_pw = DB_PASSWORD;
		while (strlen($admin_un) <= 6) $admin_un .= $admin_un;
		if (strlen($admin_pw) <= 6) $admin_pw = $admin_un;

		echo "<br>Create ADMIN:<br>";
		echo "ADMIN User    : '" . $admin_un . "'<br>";
		echo "ADMIN Password: '" . $admin_pw . "'<br>";
		echo "ADMIN Servicemail: '" . SERVICEMAIL . "'<br><br>";
		echo "<i>(Legacy Login and Data-Directory see './conf/api_key.inc.php',<br>";
		echo "DB Credentials: './conf/config.inc.php')</i><br>";

		$statement = $pdo->prepare("INSERT INTO users (name, email, password, confirmed, rem, ticket, user_role) VALUES ( ? , ?, ? , ?, ?, ?, ?)");
		$psw_enc = simple_crypt($admin_pw, 0);	// use encrypted PW ind DB
		$qres = $statement->execute(array($admin_un, SERVICEMAIL, $psw_enc, 1, 1, "(ADMIN)", 65535 + 65536));	// Full ROLE (2e16)
		$anz = $statement->rowCount(); // No of matches
		if ($anz != 1) {
			$xlog .= "(ERROR: Failed to Create ADMIN)";
			echo "ERROR: Failed to Create ADMIN!!!<br>";
		} else {
			if(!file_exists("./conf_backup")){
				echo "<br>Backup Credential Files to './conf_backup'<br>";
				mkdir("./conf_backup");
				$af = file_get_contents("./conf/api_key.inc.php");
				file_put_contents("./conf_backup/api_key.inc.php", $af);
				$cf = file_get_contents("./conf/config.inc.php");
				file_put_contents("./conf_backup/config.inc.php", $cf);
			}
			echo "======================<br>";
			echo "LTX Database Setup OK!<br>";
			echo "======================<br>";
		}
	} else {
		$xlog .= "(ERROR: Failed to Create Initial Tables)";
		echo "ERROR: Failed to Create Initial Tables!!!<br>";
	}
} catch (Exception $e) {
	$errm = "#ERROR: '" . $e->getMessage() . "'";
	exit("$errm\n");
	$xlog .= "($errm)";
}
echo "<a href='login.php'>Login to LTX...</a><br>";
add_logfile();

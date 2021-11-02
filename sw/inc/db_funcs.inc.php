<?php
/*****************************************************
 * Database Toolbox (C)JoEmbedded.de 
 * Last modified: 25.10.2021
 * ****************************************************/

// ------------- Functions --------------
// Init Database. Might be called multiple times
function db_init()
{
	global $pdo;
	if (isset($pdo)) return;

	try { // Nothing will work without the DB
		$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		exit("ERROR: '" . $e->getMessage() . "'<br>");
	}
}

// Simple and fast String de/en-cryption 
function simple_crypt($string, $ed)
{ // direction 0: encrypt, 1: decrypt
	$method = "AES-128-CBC";
	if (!$ed) {
		$output = base64_encode(openssl_encrypt($string, $method, STR_CRYPT_KEY, 0, STR_CRYPT_KEY));
	} else {
		$output = openssl_decrypt(base64_decode($string), $method, STR_CRYPT_KEY, 0, STR_CRYPT_KEY);
	}
	return $output;
}

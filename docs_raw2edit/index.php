<!DOCTYPE HTML>
<html>
<head><title>LTX1 - Media-Browser</title></head>
	<meta charset="UTF-8">
	<meta name="description" content="Ultra-Low-Power IoT">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="../sw/css/w3.css">
	<link rel="stylesheet" href="../sw/css/theme_jo.css">
	<link rel="stylesheet" href="../sw/fontawesome/css/all.min.css">
	<script src="../sw/jquery/jquery-3.3.1.min.js"></script>
	<script>
		if (typeof $ === 'undefined') {
			alert("ERROR: Missing Scripts!");
		}
	</script>
<body>


<div class="w3-container">
<?php
	// A small file browser for a given subdirectory
	// (C)2020 JoEmbedded.de - save as "index.php" (see '$me')
	error_reporting(E_ALL);

	$me="index.php";
	
	// --- Convert to Timestring
	function  secs2period($secs){
		if($secs>=86400) return floor($secs/3600)."h";
		return gmdate('H\h\r i\m\i\n s\s\e\c',$secs);
	}

	// --- Write a Logfile ---
	function addlog($xlog){
        $logpath="./";
		if(@filesize($logpath."log.log")>1000000){	// Main LOG
				unlink($logpath."_log_old.log");
				rename($logpath."log.log",$logpath."_log_old.log");
				$xlog.=" ('log.log' -> '_log_old.log')";
		}

		$log=@fopen($logpath."log.log",'a');
		if($log){                
			while(!flock($log,LOCK_EX)) usleep(10000);  // Lock File - Is a MUST
			fputs($log,gmdate("d.m.y H:i:s ",time()).$_SERVER['REMOTE_ADDR']);        // Write file
			fputs($log," $xlog\n");        // evt. add extras
			flock($log,LOCK_UN);
			fclose($log);
        }
	}
	
	//---- MAIN -----
	$now=time();
	$dir=@$_GET['dir'];

	if(!strlen($dir) || strlen($dir)>128 ) $dir=".";

	addlog($dir);	// What is displayed?
	
	echo "<div class='w3-panel w3-dark-grey'><h3><b>LTX1 - Media-Browser - Directory '$dir'</b></h3></div>";
	
	echo "<ul class='w3-ul w3-leftbar w3-border-green w3-hoverable w3-light-gray'>";
	echo "<li><a href=\"..\sw\index.php\">Home / Login</a><br></li>";
	echo "</ul><br>";
	
	// --- Test if in allowed range, never higher than script itself! ---
	$minpath=realpath(".");
	$actpath=@realpath($dir);
	//echo "minpath: '$minpath', actpath: '$actpath'<br>";
	if(strncmp($actpath,$minpath,strlen($minpath))){
			echo "<p><b>Invalid path!</b></p>";
			$dir="";	// -> Gen. Error: Directory not found
	}
	// --- Test End ---

	$anz=0;
	if(file_exists($dir)){

		$list=scandir($dir);
		if(count($list)){
			echo "<ul class='w3-ul w3-leftbar w3-border-orange  w3-hoverable w3-light-gray'>";
			// Directories first
			$dircnt=0;
			foreach($list as $file){
				if($file=='.') continue;
				if($file=='..'){
					if(strlen($dir)){
						$p=strrpos($dir,'/');
						if($p>0){
							$up=substr($dir,0,$p);
							echo "<li><a href=\"$me?dir=$up\"><big>&nwarr;</big> <small>(Directory up)</small> </a></li>";
						}
					}
					continue;
				}
				if(is_dir("$dir/$file")){
						echo "<li><a href=\"$me?dir=$dir/$file\">/$file <big>&searr;</big></a></li>";
						$dircnt++;
				}
			}
			if($dircnt) echo "&nbsp;<small>($dircnt Directories)</small><br></ul><br>";
			else echo "</ul>";
		}

		echo "<ul class='w3-ul w3-leftbar w3-border-blue w3-hoverable w3-light-gray'>";
		$dstot=0;
		// Then files
		foreach($list as $file){
			if($file=='.'||$file=='..') continue;

			// don't show PHP and HTML and CSS
			if(stripos($file,'.php')) continue;
			if(stripos($file,'.html')) continue;
			if(stripos($file,'.css')) continue;
			if(stripos($file,'.log')) continue;
			if(stripos($file,'.js')) continue;

			if(is_dir("$dir/$file")) continue;

			$ds=filesize("$dir/$file");
			$dstot+=$ds;
			$fa=secs2period($now-filemtime("$dir/$file"));
			echo "<li><a href=\"$dir/$file\">$file</a> <small><i> &nbsp;&nbsp;&nbsp;($ds Bytes, Age: $fa)</i></small></li>";
			$anz++;
		}
		if($anz) echo "&nbsp;<small>($anz Files, total: $dstot Bytes)</small><br>";
		else echo "&nbsp;<small>(No files)</small><br>";
		echo "</ul><br>";


	}else{
		echo "<p><b>ERROR: Directory not found!</b></p>";
	}
?>
</div>
</body>
</html>

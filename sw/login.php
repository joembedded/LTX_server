<?php
  /*****************************************************
  * Jo's Micro Cloud Login Script (C)JoEmbedded.de 
  * Last modified: 05.12.2020
  *
  * LOGIN.PHP
  * see database-SQL description for use. 
  *
  * Actions:
  * autologin (or nothing):  if cookie is present auto-forward to internal site
  * login:    Query for user/password
  * logout:   Disable session and set db-entry to not-active
  * confirm:  Activate user 
  * register: Register first time and send conformation mail
  * forgot:   Resend-Login-Long Mail to registered user
  *
  * Adapt for own use: 
  * - Generate a Database with the SQL statement in /docu
  * - Change Database access in config.inc.php
  * - Set header where to jump (see '$page_intern') or uncomment for tests
  *****************************************************/
  
  // ------------ Global PHP Setup ------------------
  error_reporting(E_ALL);
  require_once("conf/config.inc.php");	// DB Access 
  require_once("inc/db_funcs.inc.php"); // Init DB
  
  check_https();
  db_init();
  session_start();	// NO Output allowed before this!
  
  $page_intern="intern_main.html";	// Jump to HERE (uncomment for TESTS)
  
  // --------------- PHP Functions --------------------
  
  // Optionally redirect to secure site with same script
  function check_https(){
  	$script=$_SERVER['PHP_SELF'];	// /xxx.php
  	if(!isset($_SERVER['HTTPS']) && HTTPS_SERVER) { // ppt. Redirect
  		$url="https://".HTTPS_SERVER;	// HTTPS on Std. Port
  		$url.=$script;
  		header("Location: $url");
  		echo "Redirect to '$url'...";
  		exit();
  	}
  }
  
  // Send Confirmation Mail and return true on success
  function send_confirmation_mail($mail,$uname,$subj){
  	$script=$_SERVER['PHP_SELF'];
  	$host=$_SERVER['SERVER_NAME'];
  	if(@$_SERVER['HTTPS']) {
  		$url="https://$host";	// HTTPS
  		if($_SERVER['SERVER_PORT']!=443) $url.=":".$_SERVER['SERVER_PORT'];
  	}else{
  		$url="http://$host";	// HTTP
  		if($_SERVER['SERVER_PORT']!=80) $url.=":".$_SERVER['SERVER_PORT'];
  	}
  	$url.="$script?a=confirm&mail=$mail";	// Theoretically plus a secret.. <t.b.d>
  
  	$mail_text="Hello '$uname' (Username),\n\n";
  	$mail_text.="Please follow the link to complete or renew your registration on '$host':\n\n";
	$mail_text.="$url\n\n(This Email was sent automatically. If received unintentionally, please ignore it. Contact Service: ".SERVICEMAIL.")";
  
	$header = "From: Automailer <".AUTOMAIL.">\r\n" .
  	// 'Reply-To: webmaster@example.com' . "\r\n" .
  	'X-Mailer: PHP/' . phpversion();

  	
/* Test
	$smail="---Send Mail---\n";
  	$smail.="To: '$mail'\n";
  	$smail.="$header\n";
  	$smail.="Subject: '$subj'\n\n";
  	$smail.="$mail_text\n";
  	$smail.="--------------------------\n";
	file_put_contents("dbg_log.txt",$smail, FILE_APPEND);
	return true;	// OK
*/	
  		

  	$res=@mail($mail,$subj,$mail_text, $header);
  	return $res;
  }
  
  // Set/Disable Remember-Cookies
  function set_remember_cookie($remember,$uname,$psw){
  	if($remember){
  		setcookie("user",$uname,time()+(86400*365));	// Valid for another Year
  		setcookie("psw",simple_crypt($psw,0),time()+(86400*365));	 // Write encrypted
  	}else{
  		setcookie("user","",time()-1000);	// Kill Cookie
  		setcookie("psw","",time()-1000);	
  	}
  }
  
  //---------------------------------- MAIN PHP ----------------------------------
  // Autocomplete what is known
  $uname=@$_COOKIE['user'];
  if(strlen(@$_POST['uname']))$uname=$_POST['uname'];
  $uname=trim($uname);	// No Spaces
  $len=strlen($uname);
  if($len<4 || $len>100) unset($uname);
  
  $psw=@$_COOKIE['psw'];
  if(strlen($psw)) $psw=simple_crypt($psw,1);	// Decrypt from cookie
  
  if(strlen(@$_POST['psw'])) $psw=@$_POST['psw'];
  $psw=trim($psw);
  $len=strlen($psw);
  if($len<6 || $len>100) unset($psw);
  
  $mail=@$_GET['mail'];	// Mail comes always as GET or POST
  if(strlen(@$_POST['mail'])) $mail=$_POST['mail'];
  $len=trim(strlen($mail));
  if($len<6 || $len>100) unset($mail);
  
  $uid=@$_SESSION['user_id'];
  
  $remember=strlen(@$_POST['rem'])?true:false;
  
  $msg="";	// Start with empty Text
  $action=@$_GET['a'];
  if(!strlen($action) && strlen(@$uname) && strlen(@$psw)){
  	$action="autologin";
  	$remember=1;	// logical state without action and user/password set
  }
  if(strlen($action)){
  	unset($_SESSION['uname']);
  	unset($_SESSION['user_id']);
  	
  	switch($action){	// User and Mail must be Text (might be displayed)
  	case "logout":
  		set_remember_cookie(0,"","");	// Kill Cookie
  		if(strlen($uid)){
  			$statement = $pdo->prepare("UPDATE users SET loggedin = 0 WHERE  id = ?");
  			$statement->execute(array($uid));
  		}
  		$msg="<span class=\"ok\">OK: Logged out!</span>";
  		break;
  
  	case "confirm":
  		if(!isset($mail)) break;
  		$statement = $pdo->prepare("UPDATE users SET confirmed = confirmed + 1 WHERE email = ?");
  		$qres=$statement->execute(array($mail));
  		$anz=$statement->rowCount(); // No of matches
  		if($anz<1){
  			$msg="<span class=\"err\">ERROR: Unknown Email!</span>";
  			break;
  		}else if($anz>1){
  			$msg="<span class=\"ok\">INFO: Multiple Users for this Emails!</span>";
  			break;
  		}
  		$statement = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  		$qres=$statement->execute(array($mail));
  		$user_row = $statement->fetch();
  		$psw=simple_crypt($user_row['password'],1);	// decrypt PW from Database
  		$uname=$user_row['name'];
  		$remember=$user_row['rem'];
  		// fall through..
  		
  	case "autologin":
  		if(!isset($uname) || !isset($psw)) break;	
  		// Autologin only if user still logged in
  		$statement = $pdo->prepare("SELECT loggedin FROM users WHERE name = ? AND password = ?");
  		$psw_enc=simple_crypt($psw,0);	// use encrypted PW ind DB
  		$qres=$statement->execute(array($uname,$psw_enc));
  		$anz=$statement->rowCount(); // No of matches
  		if($anz!=1) break;
  		$user_row = $statement->fetch();
  		if(@$user_row['loggedin']!=1) break;	// Display form if not still logged in
  		
  	case "login":
  		if(!isset($uname) || !isset($psw)) break;	
		$statement = $pdo->prepare("SELECT * FROM users WHERE name = ? AND password = ?");
  		$psw_enc=simple_crypt($psw,0);	// use encrypted PW ind DB
  		$qres=$statement->execute(array($uname,$psw_enc));
  		$anz=$statement->rowCount(); // No of matches
  		if($anz!=1){
  			usleep(100000);	// 0.1 secs 
  			$msg="<span class=\"err\">ERROR: Login failed!</span>";
  			break;
  		}
  		set_remember_cookie($remember,$uname,$psw);
  		$user_row = $statement->fetch();
  		if(!$user_row['confirmed']){	// Only confirmed users may register
  			$msg="<span class=\"err\">ERROR: User Email not confirmed!</span>";
  			break;
  		}
  
  		$_SESSION['uname']=$uname;
  		$_SESSION['user_id']=$user_row['id'];
  
  		$statement = $pdo->prepare("UPDATE users SET loggedin = 1, last_seen=NOW() WHERE  id = ?");
  		$statement->execute(array($user_row['id']));
  		
  		$msg="<span class=\"ok\">OK: Logged in as '$uname'!</span>";
  
  		if(strlen(@$page_intern)){	// else: TEST
  			$nloc = "Location: $page_intern";
  			header($nloc); exit();
  		}
  		break;
  
  	case "register":
		$ticket=@$_POST['uticket'];
		if(strlen($ticket)!=16){
  			$msg="<span class=\"err\">ERROR: Invalid Ticket!</span>";
			break;
		}
		
		// Process Ticket as in ticket_help.php
		$tmp=pack("H*", $ticket); // Make String from $ticket
		$xc=ord($tmp[7]);
		$res=""; for($i=0;$i<6;$i++) $res.=chr(ord($tmp[$i])^(($i*15+$xc)&255));
		$crc=((~crc32($res))&0xFFFF);
		if((($crc>>8)&255)==ord($tmp[6]) && $xc==($crc&255)){
			$plain_ticket=trim($res);
		} else{
  			$msg="<span class=\"err\">ERROR: Invalid Ticket!</span>";
			break;
		}
		if(!isset($uname) || !isset($psw) || !isset($mail)) break;	
  		$statement = $pdo->prepare("SELECT * FROM users WHERE name = ? ");
  		$qres=$statement->execute(array($uname));
  		$anz=$statement->rowCount(); // No of matches
  		$user_row = $statement->fetch();
  		if($anz){
  			usleep(100000);	// 0.1 secs 
  			$msg="<span class=\"err\">ERROR: User already exists!</span>";
  			break;
  		}

  		$statement = $pdo->prepare("SELECT * FROM users WHERE email = ? ");
  		$qres=$statement->execute(array($mail));
  		$anz=$statement->rowCount(); // No of matches
  		$user_row = $statement->fetch();
  		if($anz){
  			usleep(100000);	// 0.1 secs 
			if(isset($_POST['no_mail'])){
				$msg="<span class=\"err\">WARNING: Mail already in use!</span>";
			}else{
				$msg="<span class=\"err\">ERROR: Mail already in use! Select another one!</span>";
				break;
			}
  		}
  
  		$statement = $pdo->prepare("INSERT INTO users (name, email, password, confirmed, rem, ticket ) VALUES ( ? , ?, ? , ?, ?, ?)");
  		$psw_enc=simple_crypt($psw,0);	// use encrypted PW ind DB
  		$qres=$statement->execute(array($uname,$mail,$psw_enc, (isset($_POST['no_mail'])?1:0), $remember, $plain_ticket));
  		$anz=$statement->rowCount(); // No of matches
  		if($anz!=1){
			if(!isset($_POST['no_mail'])){
				$msg="<span class=\"err\">ERROR: Write to Database!</span>";
				break;
			}
  		}
  		$new_id = $pdo->lastInsertId();
  		set_remember_cookie($remember,$uname,$psw);
  
  		// Send Verification Mail to new user
  		$subj="Welcome new user '$uname'! (ID:$new_id)";
  		if(send_confirmation_mail($mail, $uname,$subj)!=true){
  			$msg = "<span class=\"err\">ERROR: Failed to send mail!</span>";
  		}else {
			if(isset($_POST['no_mail'])){
				$msg="<span class=\"ok\">OK: Please confirm the Email!</span>";
			}else{
				$msg="<span class=\"ok\">OK: An Email was sent!</span>";
			}
		}
  		break;
  
  	case "forgot":
  		if(!isset($mail)) break;	
  		$statement = $pdo->prepare("SELECT * FROM users WHERE email = ? ");
  		$qres=$statement->execute(array($mail));
  		$anz=$statement->rowCount(); // No of matches
  		if($anz<1){
  			usleep(100000);	// 0.1 secs 
  			$msg="<span class=\"err\">ERROR: Unknown Mail!</span>";
  			break;
  		}
		$anzt=$anz;
		while($anz){
			$user_row = $statement->fetch();
			$uname=$user_row['name']; // Retrieve User
			$user_id=$user_row['id'];
			$subj="Welcome back '$uname'! (ID:$user_id)";
			if($anzt>1) $subj.=", Account: $anz of $anzt";
			$anz--;
			if(send_confirmation_mail($mail, $uname,$subj)!=true){
				$msg = "<span class=\"err\">ERROR: Failed to send mail!</span>";
			}else{
				if($anzt==1) $msg="<span class=\"ok\">OK: Email was sent!</span>";
				else $msg="<span class=\"ok\">OK: $anzt Emails sent!</span>";
			}
		}
  		$uname=""; // Prevent Showing username in input-field
  		break;
  
  	default:
  		$msg="<span class=\"err\">ERROR: action='$action'?</span>";
  		break;
  	}
  	// Check uncatched DB Errors as String (**DEBUG-INFO**)
  	if(@$qres===false){
  		$msg.="<br><small>(".$statement->errorInfo()[2].")</small>";
  	}
  }
?>
<!-- ----------------- HTML ------------------------------------ -->
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Welcome</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="MIC1 Login">
	<meta name="keywords" content="MIC1, Micro Cloud">
	
    <link rel="stylesheet" type="text/css" media="screen" href="css/login.css">
    <link rel="stylesheet" href="fontawesome/css/all.min.css">
    <link rel="stylesheet" type="text/css" media="screen" href="css/clouds.css">
  </head>
  <body>
    <div class="form-container animate" style="z-index: 1">
      <!-- ***Login*** -->
      <form id="loginForm" action="login.php?a=login" method="post" autocomplete="on">
        <div class="form-item">
          <h2>Login</h2>
        </div>
        <div class="form-item">
          <label for="uname_l">Username</label><br>
          <input type="text" placeholder="Enter Username" name="uname" id="uname_l" 
            required minlength="4" <?php if(strlen(@$uname)) echo "value=\"$uname\""; ?> >
        </div>
        <div class="form-item">
          <label for="inputPswLogin">Password</label><br>
          <input type="password" placeholder="Enter Password" name="psw" id="inputPswLogin" required 
            minlength="6" <?php if(strlen(@$psw)) echo "value=\"$psw\""; ?> ><br>
          <input type="checkbox" id="checkPswLogin" name="show_pw" onclick="setPasswordVisibility()"><label for="checkPswLogin">Show Password</label>
        </div>
        <div class="form-item">
          <input type="checkbox" checked="checked" name="rem" id="rem_l"> 
          <label for="rem_l">Remember me (using Cookie)</label><br>
          <button type="submit">Login <i class="fas fa-sign-in-alt"></i></button> &nbsp; &nbsp; 
          <button type="button" onclick="gotoRegister()">Register User</button> 
          <button type="button" onclick="gotoForgot()">Forgot Password?</button>  
        </div>
      </form>
      <!-- ***REGISTER USER*** -->
      <form id="registerForm" action="login.php?a=register" method="post" autocomplete="on">
        <div class="form-item">
          <h2>Register User</h2>
        </div>

        <div class="form-item">
          <label for="uticket">Enter Registration Ticket</label><br>
          <input type="text" placeholder="16 Characters" name="uticket" id="uticket"
            required minlength="16" maxlength="16" >
        </div>

        <div class="form-item">
          <label for="uname_r">Select a Username</label><br>
          <input type="text" placeholder="Minimum 4 Characters" name="uname" id="uname_r"
            required minlength="4" >
        </div>
        <div class="form-item">
          <label for="mail_r">Email</label><br>
          <input type="email" placeholder="Contact Email" name="mail" required  id="mail_r">
          <input type="checkbox" id="noMailRegister" name="no_mail" onclick="setMailVisibility()"><label for="noMailRegister">Do not register with Email Validation</label>
        </div>
        <div class="form-item">
          <label for="inputPswRegister">Select a Password</label><br>
          <input type="password" placeholder="Minimum 6 Characters" name="psw" id="inputPswRegister" required minlength="6"><br>
          <input type="checkbox" id="checkPswRegister" name="show_pw" onclick="setPasswordVisibility()"><label for="checkPswRegister">Show Password</label>
        </div>
        <div class="form-item">
          <input type="checkbox" checked="checked" name="rem" id="rem_r"> 
          <label for="rem_r">Remember me (using Cookie)</i></label><br>
          <button type="button" onclick="gotoLogin()">Login</button>  &nbsp; &nbsp; 
          <button type="submit">Register User <i class="fas fa-user-plus"></i></button> &nbsp; &nbsp; 
          <button type="button" onclick="gotoForgot()">Forgot Password?</button>  
        </div>
      </form>
      <!-- ***FORGOT PASSWORD*** -->
      <form id="forgotForm" action="login.php?a=forgot" method="post" autocomplete="on">
        <div class="form-item">
          <h2>Forgot Password</h2>
        </div>
        <div class="form-item">
          <label for="mail_f">Email</label><br>
          <input type="email" placeholder="Enter registered Email" id="mail_f" name="mail" required>
        </div>
        <div class="form-item">
          <button type="button" onclick="gotoLogin()">Login</button>
          <button type="button" onclick="gotoRegister()">Register User</button> &nbsp; &nbsp; 
          <button type="submit">Forgot Password? <i class="fas fa-envelope"></i></button>  
        </div>
      </form>
      <!--- *** Opt User-Text *** --->
      <div id="msgText"><?php if(strlen($msg)>0) echo $msg; ?></div>
    </div>
    <!-- <div class="header"></div> -->
    <div id="clouds">
      <div class="cloud x1"></div>
      <div class="cloud x2"></div>
      <div class="cloud x3"></div>
      <div class="cloud x4"></div>
      <div class="cloud x5"></div>
      <!-- ***FOOTER*** -->
      <div class="footer">
        <a href="/">[Visit our Homepage]</a> &nbsp; <a href="../docs/">[Media-Browser]</a> 
      </div>
    </div>
    <script>
      function gotoLogin(event){
      	document.getElementById("registerForm").style.display="none";
      	document.getElementById("forgotForm").style.display="none";
      	document.getElementById("loginForm").style.display="block";
      }
      function gotoRegister(event){
      	document.getElementById("loginForm").style.display="none";
      	document.getElementById("forgotForm").style.display="none";
      	document.getElementById("registerForm").style.display="block";
      }
      function gotoForgot(event){
      	document.getElementById("loginForm").style.display="none";
      	document.getElementById("registerForm").style.display="none";
      	document.getElementById("forgotForm").style.display="block";
      }
      function setPasswordVisibility(){
      	var pswIsChecked = document.getElementById("checkPswLogin").checked;
        document.getElementById("inputPswLogin").type=pswIsChecked?"text":"password";
      	pswIsChecked = document.getElementById("checkPswRegister").checked;
        document.getElementById("inputPswRegister").type=pswIsChecked?"text":"password";
      }
      function setMailVisibility(){
      	var mailIsChecked = document.getElementById("noMailRegister").checked;
        //document.getElementById("mail_r").type=mailIsChecked?"hidden":"email";
      }
      function initScripts(){
      	gotoLogin();
      	setPasswordVisibility();
      }
      window.addEventListener('load', initScripts);
        
    </script>
  </body>
</html>

<!DOCTYPE HTML>
<html>
  <head>
    <title>LTX Service direct</title>
  </head>
  <body>
         <form name="serviceform" action="service.php?v=1" method="get" >
			<b>Password ('L_KEY'): </b>

<?PHP 
error_reporting(E_ALL);
include("../conf/api_key.inc.php");

session_start();
if (isset($_REQUEST['k'])) {
	$api_key = $_REQUEST['k'];
	$_SESSION['key'] = L_KEY;
} else {
	$api_key = @$_SESSION['key'];
}

if(isset($api_key)){
	echo "<input type='password' name='k' value='$api_key'>"; // Given PW
}else{
	echo '<!-- User (Legacy): --><input placeholder="Enter User" type="input" name="user" value="legacy" hidden>';
	echo '<input type="password" name="k" placeholder="Enter Password">'; // Plain Text field
}
?>
			<br>
			Command (default: 'service' or optional): <input type="text" name="cmd"><br>
			Days: (older data removed, default:'' or >= 2): <input type="text" name="d"><br>
			<input type="hidden" name="v" value="1">
           <button type="submit">OK</button>
         </form>
  </body>
</html>
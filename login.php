<?php
include_once ("include/include.php");

$loginfailed = "";
if(!empty($_POST{"userid"})) {
	$user = new User();
	if(!$user -> loginUser($_POST{"userid"}, $_POST{"password"})) {
		$loginfailed = "Login attempt failed";
	}
}

if(!empty($_SESSION{"userid"})) {
	header("Location: chat.php");
	exit ;
}

$title = "Login";
include_once ("include/header.php");
?>
<div id="content">
	<h2>Login to MemChat</h2>
	<?php echo $loginfailed
	?><br>
	<form method="post" action="login.php" id="loginform" style="width:40%">
		<fieldset>
			<label for="userid">
				User id:
			</label>
			<input type="text" name="userid" length=20>
			<label for="password">
				Password:
			</label>
			<input type="password" name="password" id="password" length=20>
			<input type="submit" name="login" id="registration">
		</fieldset>
	</form>
	Register
	<a href="register.php">here</a> if you don't have an account.
</div>
<?php
include_once ("include/footer.php");
?>

<?php
	include_once("include/include.php");
	$title="Register User";
	include_once("include/header.php");
	$result = false;
    //opening the register page logs you out
	$user=new User();
    $user->logoutUser();
	if(!empty($_POST{"userid"})){
		$userid=$_POST{"userid"};
		$result = $user->createUserAccount($_POST{"userid"},$_POST{"password"});
		if($result == true){
			echo 	"<div id='error'>" .
					"User id '<i>$userid</i>' successfully created. <br> " .
					"<a href='login.php'>Login here</a>" .
					"</div>";
		}else{
			echo "<div id='error'>" . $user->getLastErrorString() . "</div>";
		}
	}
	if($result != true){
?>
<div id="content">
	<h2>Register to use WebChat</h2>
	<form method="post" action="register.php" id="registrationform" style="width:40%">
		<fieldset>
			<label for="userid">
				User id:
			</label>
			<input type="text" name="userid" id="userid">
			<label for="password">
				Password:
			</label>
			<input type="password" name="password" id="password">
			<input type="submit" name="registration" id="registration">
		</fieldset>
	</form>
	<small>
		<i>
		User id must have between 4 and 10 characters.
		<br>
		Only letters, numbers and underscore characters are permitted
		</i>
	</small>
</div>
<?php
}
include_once("include/footer.php");
?>

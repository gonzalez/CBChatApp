<?php
	include_once("include/include.php");
	$userid=$_SESSION{"userid"};
	$title="Logout";
	include_once("include/header.php");
	$user = new User();
	$user->logoutUser();
	echo("$userid logged out");
	include_once("include/footer.php");
?>

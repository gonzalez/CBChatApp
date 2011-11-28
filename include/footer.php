<div id="footer">
	<?php
	if(empty($_SESSION{"userid"})){
		echo("Not logged in.<br>");
?>
	<a href="login.php">Login here</a>
	<?php
}else{
echo("Logged in as user <i>" . $_SESSION{"userid"} . "</i>");
echo("<br>");
echo("<a href='logout.php'>Logout</a>");
}?>
</div>
</div>
</body>
</html>

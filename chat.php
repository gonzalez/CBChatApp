<?php
include_once ("include/include.php");
if(empty($_SESSION{"userid"})) {
	header("Location: login.php");
}
$title = "Chat";
include_once ("include/header.php");
?>
<form method="post" action="chat.php" id="chat">
	<div>
		<input type="text" name="comment" id="comment" maxlength="50" size="50"/>
		<input type="submit" name="submitcomment" id="submitcomment" value="Submit comment" />
	</div>
</form>
<br>

<?php
$comments = new Comments();
if(!empty($_POST{"comment"})) {
	$comments -> addComment($_POST{"comment"});
}
if(!empty($_POST{"delete"})) {
	$comments -> deleteComment($_POST{"commentnum"});
}
$comment_list = $comments -> getLastComments(10);
$keys = array_keys($comment_list);
?>
<table>
	<tr>
		<th width="10%" align="center">Comment #</th>
		<th>Action</th>
		<th>User ID</th>
		<th width="60%">Comment</th>
	</tr>
	<?php
	foreach($keys as $key) {
		echo "<tr>\n";
		$result = explode(KEY_DELIM, $comment_list{$key});
		$userid = array_shift($result);
		$message = implode(KEY_DELIM, $result);
		$commentnum = array_pop(explode("#", $key));
		$actionlink = "";
		if(empty($userid)) {
			$userid = "?";
			$message = "<i>deleted</i>";
		}
		if($_SESSION{"userid"} == $userid) {
			$actionlink = "<form method='post' action='chat.php' id='message" . $commentnum . "'>";
			$actionlink .= "<input type='hidden' value='$commentnum' name='commentnum'>";
			$actionlink .= '<input type="submit" value="delete" name="delete" style="background: none; border: none; font-style: italic;" >';
			$actionlink .= "</form>";
		}
		echo("<td>$commentnum</td><td>$actionlink</td><td>$userid</td><td>$message</td>\n");
		echo "</tr>\n";
	}
	?>
</table>
<?php
include_once ("include/footer.php");
?>

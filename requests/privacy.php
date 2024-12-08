<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if($_POST['value'] == 0 || $_POST['value'] == 1) {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
		
		$result = $feed->changePrivacy($_POST['track'], $_POST['value'], $_POST['type']);
		if($result) {
			echo 1;
		} else {
			echo 0;
		}
	}
}

mysqli_close($db);
?>
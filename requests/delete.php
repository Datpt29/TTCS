<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(in_array($_POST['type'], array('0', '1', '2', '3'))) {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
		
		$result = $feed->delete($_POST['id'], $_POST['type']);
		if($result) {
			echo 1;
		} else {
			echo 0;
		}
	}
}

mysqli_close($db);
?>
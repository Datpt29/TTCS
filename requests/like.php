<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_POST['id']) && isset($_POST['type'])) {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->title = $settings['title'];
		$feed->email = $CONF['email'];
		$feed->id = $user['idu'];
		$feed->username = $user['username'];
		$feed->user_email = $user['email'];
		$feed->l_per_post = $settings['lperpost'];
		$feed->email_like = $settings['email_like'];
		
		$result = $feed->getActions($_POST['id'], $_POST['type']);
		echo $result;
	}
}

mysqli_close($db);
?>
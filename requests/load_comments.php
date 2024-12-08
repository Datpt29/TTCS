<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(!empty($_POST['id']) && !empty($_POST['start']) && !empty($_POST['cid'])) {
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	$feed->time = $settings['time'];
	// Verify if it's logged in, then send the username to the class property to determine if any buttons is shown
	if(is_array($user) && $user['username']) {
		$feed->username = $user['username'];
	}
	$feed->c_per_page = $settings['cperpage'];
	echo $feed->getComments($_POST['id'], $_POST['cid'], $_POST['start']);
}

mysqli_close($db);
?>
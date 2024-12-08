<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(!empty($_POST['uid'])) {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
		$feed->m_per_page = $settings['mperpage'];
		$feed->time = $settings['time'];
		
		// Type 1: Check for new messages.
		if(!empty($_POST['type'])) {
			$feed->updateStatus($user['offline']);
			echo $feed->checkChat($_POST['uid']);
		} else {
			echo $feed->getChatMessages($_POST['uid'], $_POST['cid'], $_POST['start']);
		}
	}
}

mysqli_close($db);
?>
<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_POST['start']) && isset($_POST['q']) && ctype_digit($_POST['start'])) {
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	if(is_array($user) && $user['username']) {
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
		$feed->online_time = $settings['conline'];
		
		if(!empty($_POST['list'])) {
			echo $feed->onlineUsers(2, $_POST['q']);
			return;
		}
	}
	$feed->per_page = $settings['perpage'];
	$feed->profile = $_POST['profile'] ?? null;
	$feed->profile_data = $feed->profileData(null, $_POST['id'] ?? null);
	$feed->s_per_page = $settings['sperpage'];
	$feed->subsList = $feed->getSubs($feed->profile_data['idu'] ?? null, $_POST['type'] ?? null, $_POST['start']);
	
	if(isset($_POST['live']) && $_POST['live']) {
		echo $feed->getSearch(0, 3, $_POST['q'], null, 1);
	} else {
		echo $feed->getSearch($_POST['start'], $settings['sperpage'], $_POST['q'], $_POST['filter']);
	}
}

mysqli_close($db);
?>
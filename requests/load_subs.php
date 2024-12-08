<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_POST['type']) && isset($_POST['start']) && isset($_POST['id'])) {
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	if(is_array($user) && $user['username']) {
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
	}
	$feed->per_page = $settings['perpage'];
	$feed->c_per_page = $settings['cperpage'];
	$feed->c_start = 0;
	$feed->profile = $_POST['profile'] ?? null;
	$feed->profile_data = $feed->profileData(null, $_POST['id']);
	$feed->s_per_page = $settings['sperpage'];
	$feed->subsList = $feed->getSubs($feed->profile_data['idu'], $_POST['type'], $_POST['start']);
		
	echo $feed->listSubs($_POST['type']);
}

mysqli_close($db);
?>
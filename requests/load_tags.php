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
		$feed->user = $user;
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
	}
	
	$feed->per_page = $settings['perpage'];
	$feed->c_per_page = $settings['cperpage'];
	$feed->c_start = 0;
	$feed->profile = $_POST['profile'] ?? null;
	$feed->profile_data = $feed->profileData(null, $_POST['id'] ?? null);
	$feed->s_per_page = $settings['sperpage'];
	$feed->l_per_post = $settings['lperpost'];
	$feed->subsList = $feed->getSubs($feed->profile_data['idu'] ?? null, $_POST['type'] ?? null, $_POST['start']);
	
	if($_POST['live']) {
		echo $feed->getHashtags(str_replace('#', '', $_POST['q']), 10);
	}
}

mysqli_close($db);
?>
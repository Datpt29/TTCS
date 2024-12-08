<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_POST['start']) && ctype_digit($_POST['start'])) {
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
	$feed->time = $settings['time'];
	$feed->c_start = 0;
	$feed->profile = $_POST['profile'] ?? null;
	$feed->profile_data = $feed->profileData($_POST['profile'] ?? null);
	$feed->s_per_page = $settings['sperpage'];
	$feed->l_per_post = $settings['lperpost'];
		
	if($_POST['type'] == 1) {
		$feed->categories = $feed->getCategories();
		$likes = $feed->getLikes($_POST['start'], 1);
		$getLikes = $likes[0];
	} else {
        $feed->per_page = $settings['sperpage'];
		$getLikes = $feed->getLikes($_POST['start'], 2, $_POST['query']);
	}
	echo $getLikes;
}

mysqli_close($db);
?>
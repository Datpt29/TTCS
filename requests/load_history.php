<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(!empty($_POST['start'])) {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->user = $user;
		$feed->id = $user['idu'];
		$feed->username = $user['username'];
		$feed->per_page = $settings['perpage'];
		$feed->categories = $feed->getCategories();
		$feed->c_per_page = $settings['cperpage'];
		$feed->c_start = 0;
		$feed->l_per_post = $settings['lperpost'];
		$feed->time = $settings['time'];
		if(empty($_POST['filter'])) {
			$_POST['filter'] = '';
		}
		
		$stream = $feed->history($_POST['start'], $_POST['filter']);
		echo $stream[0];
	}
}

mysqli_close($db);
?>
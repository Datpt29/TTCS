<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(is_array($user) && $user['username']) {
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	$feed->username = $user['username'];
	$feed->id = $user['idu'];
	$feed->time = $settings['time'];
	$feed->per_page = $settings['perpage'];
	$feed->c_per_page = $settings['cperpage'];
	$feed->c_start = 0;
	if(isset($_POST['for']) && $_POST['for'] == 1) {
		echo $feed->checkNewNotifications($settings['nperwidget'], $_POST['type'] ?? null, $_POST['for'] ?? null, $user['notificationl'], $user['notificationc'], $user['notificationf'], $user['notificationd']);
	} else {
		echo $feed->checkNewNotifications($settings['nperwidget'], $_POST['type'] ?? null, $_POST['for'] ?? null, $user['notificationl'], $user['notificationc'], $user['notificationf'], $user['notificationd']);
	}
}

mysqli_close($db);
?>
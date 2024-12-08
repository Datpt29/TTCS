<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

// If message is not empty
if(!empty($_POST)) {
	if(isset($_POST['error'])) {
		if($_POST['desc']) {
			$err[] = array(6, 5000);
		}
		if($_POST['buy']) {
			$err[] = array(7);
		}
		if($_POST['tag_max']) {
			$err[] = array(8, 30);
		}
		if($_POST['tag_min']) {
			$err[] = array(9, 1);
		}
		if($_POST['ttl_min']) {
			$err[] = array(10);
		}
		if($_POST['ttl_max']) {
			$err[] = array(11, 100);
		}

		$message = '';
		foreach($err as $error) {
			$message .= notificationBox('error', sprintf($LNG["{$error[0]}_upload_err"], ((isset($error[1])) ? $error[1] : ''), ((isset($error[2])) ? $error[2] : '')));
		}
		
		$update = array($message);
	} else {
		if(is_array($user) && $user['username']) {
			$feed = new feed();
			$feed->db = $db;
			$feed->url = $CONF['url'];
			$feed->user = $user;
			$feed->id = $user['idu'];
			$feed->username = $user['username'];
			$feed->per_page = $settings['perpage'];
			$feed->art_size = $settings['artsize'];
			$feed->art_format = $settings['artformat'];
			$feed->paypalapp = $settings['paypalapp'];
			$feed->track_size_total = ($feed->getProStatus($feed->id, 1) ? $settings['protracktotal'] : $settings['tracksizetotal']);
			$feed->track_size = ($feed->getProStatus($feed->id, 1) ? $settings['protracksize'] : $settings['tracksize']);
			$feed->track_format = $settings['trackformat'];
			$feed->time = $settings['time'];
			
			$update = $feed->updateTrack($_POST, 1);
		}
	}
}

echo json_encode(array("result" => (strpos($update[0], 'notification-box-error') > 0 ? 0 : 1), "message" => $update[0]));

mysqli_close($db);
?>
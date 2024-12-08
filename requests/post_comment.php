<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

// Remove any extra white spaces, new lines
$_POST['comment'] = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $_POST['comment']);

if(!empty($_POST['id']) && !empty($_POST['comment'])) {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->title = $settings['title'];
		$feed->email = $CONF['email'];
		$feed->id = $user['idu'];
		$feed->username = $user['username'];
		$feed->user_email = $user['email'];
		$feed->time = $settings['time'];
		$feed->email_comment = $settings['email_comment'];

		$rand = rand();
		// If the message is not too long
		if(strlen($_POST['comment']) < $settings['mlimit']) {
			$result = $feed->addComment($_POST['id'], $_POST['comment']);
			if($result) {
				echo $feed->getComments(null, null, null, 1);
			} else {
				echo '<div class="message-reply-container" id="post_comment_'.$rand.'"><div class="message-reported">'.$LNG['comment_error'].' <a onclick="deleteNotification(1, \''.$rand.'\')" title="Delete notification"><div class="delete_btn"></div></a></div></div>';
			}
		} else {
			echo '<div class="message-reply-container" id="post_comment_'.$rand.'"><div class="message-reported">'.sprintf($LNG['comment_too_long'], $settings['mlimit']).' <a onclick="deleteNotification(1, \''.$rand.'\')" title="Delete notification"><div class="delete_btn"></div></a></div></div>';
		}
	}
}

mysqli_close($db);
?>
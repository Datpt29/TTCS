<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

// Remove any extra white spaces, new lines
$_POST['message'] = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $_POST['message']);

// If message is not empty
if(!empty($_POST['message']) && $_POST['message'] !== ' ') {
	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->username = $user['username'];
		$feed->time = $settings['time'];
		$feed->id = $user['idu'];
		$feed->chat_length = $settings['mlimit'];
		
		// Set the $x to output the value via JS
		echo $feed->postChat($_POST['message'], $_POST['id']);
	}
}

mysqli_close($db);
?>
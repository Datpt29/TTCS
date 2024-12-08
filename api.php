<?php
header('Content-Type: text/plain; charset=utf-8;');
session_start();
require_once('./includes/config.php');
$db = new mysqli($CONF['host'], $CONF['user'], $CONF['pass'], $CONF['name']);
if ($db->connect_errno) {
    echo "Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
}
$db->set_charset("utf8");
$apiVersion = 1.1;

// Allowed $_GET['t'] values 
$types = array('u', 't');

echo '{"apiVersion":"'.$apiVersion.'", "data": ';
if(in_array($_GET['t'], $types)) {
	if($_GET['t'] == 'u') {
		$result = getUser($_GET['q']);
		
		// Output the result
		if(!empty($result['id'])) {
			$result['image'] = permalink($CONF['url'].'/image.php?t=a&w=112&h=112&src='.$result['image']);
			$result['cover'] = permalink($CONF['url'].'/image.php?t=c&w=900&h=200&src='.$result['cover']);
			echo json_encode($result);
		} else {
			echo json_encode(array('error' => 'No data available for the selected user.'));
		}
	} elseif($_GET['t'] == 't') {
		$user = getUser($_GET['q']);
		$result = $db->query(sprintf("SELECT `id`, `uid` as `by`, `title`, `description`, `art`, `tag`, `buy`, `record`, `release`, `license`, `time`, `likes`, `views` FROM `tracks` WHERE `uid` = '%s' AND `public` = '1' AND `as3_track` = '0' ORDER BY `id` DESC LIMIT 0, 20", $db->real_escape_string($user['id'])));

		$rows = array();
		// Store the result
		while($row = $result->fetch_assoc()) {
			$rows[]	= $row; 
		}
		
		foreach($rows as &$value) {
			$value['art'] = permalink($CONF['url'].'/image.php?t=m&w=112&h=112&src='.$value['art']);
		}
		
		// Output the result
		if(!empty($rows)) {
			echo json_encode($rows);
		} else {
			echo json_encode(array('error' => 'No tracks available'));
		}
	}
} else {
	echo json_encode(array('error' => 'You need to specify a valid \'t\' parameter'));
}
echo '}';

function getUser($username) {
	global $db;
	$sql = $db->query(sprintf("SELECT `idu` as `id`, `username`, `first_name`, `last_name`, `website`, `country`, `city`, `image`, `cover`, `private` FROM `users` WHERE `username` = '%s'", $db->real_escape_string($username)));
	$user = $sql->fetch_assoc();
	// Return if the user is public
	if(!$user['private']) {
		unset($user['private']);
		return $user;
	}
}
?>
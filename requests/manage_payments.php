<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_SESSION['is_admin'])) {
	$managePayments = new managePayments();
	$managePayments->db = $db;
	$managePayments->url = $CONF['url'];
	$managePayments->per_page = $settings['rperpage'];
	
	if(isset($_POST['start'])) {
		echo $managePayments->getPayments($_POST['start']);
	}
}

mysqli_close($db);
?>
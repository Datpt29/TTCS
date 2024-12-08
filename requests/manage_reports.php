<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_SESSION['is_admin']) && isset($_SESSION['passwordAdmin'])) {
	$manageReports = new manageReports();
	$manageReports->db = $db;
	$manageReports->url = $CONF['url'];
	$manageReports->per_page = $settings['rperpage'];
	
	if(isset($_POST['start'])) {
		echo $manageReports->getReports($_POST['start']);
	}
}

mysqli_close($db);
?>
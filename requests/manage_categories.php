<?php
require_once(__DIR__ .'/../includes/autoload.php');
if(isset($_SESSION['token_id']) == false || $_POST['token_id'] != $_SESSION['token_id']) {
    return false;
}

if(isset($_SESSION['is_admin'])) {
	$manageCategories = new manageCategories();
	$manageCategories->db = $db;
	$manageCategories->url = $CONF['url'];
	
	if($_POST['type'] == 1 && !empty($_POST['id'])) {
		echo $manageCategories->addCategory($_POST['id']);
	} elseif($_POST['type'] == 0 && !empty($_POST['id'])) {
		echo $manageCategories->deleteCategory($_POST['id']);
	}
}

mysqli_close($db);
?>
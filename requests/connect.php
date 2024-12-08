<?php
require_once(__DIR__ .'/../includes/autoload.php');

if(isset($_GET['facebook']) && $settings['fbappid']) {
	$reg = new register();
	$reg->db = $db;
	$reg->url = $CONF['url'];
	$reg->email_register = $settings['mail'];
	$reg->email_like = $settings['email_like'];
	$reg->email_comment = $settings['email_comment'];
	$reg->email_new_friend = $settings['email_new_friend'];
	$reg->fbapp = $settings['fbapp'];
	$reg->fbappid = $settings['fbappid'];
	$reg->fbappsecret = $settings['fbappsecret'];
	$reg->fbcode = $_GET['code'];
	$reg->fbstate = $_GET['state'];
	$process = $reg->facebook();
	
	if($process == 1) {
		if($settings['mail']) {
			sendMail($reg->email, sprintf($LNG['welcome_mail'], $settings['title']), sprintf($LNG['user_created'], $settings['title'], $reg->username, $CONF['url'], $settings['title'], $CONF['url'], $settings['title']), $CONF['email']);
		}
		header("Location: ".$CONF['url']);
	}
}
if(isset($_POST['register'])) {
	// Register usage
	$reg = new register();
	$reg->db = $db;
	$reg->url = $CONF['url'];
	$reg->site_email = $CONF['email'];
	$reg->title = $settings['title'];
	$reg->username = $_POST['username'];
	$reg->password = $_POST['password'];
	$reg->email = $_POST['email'];
	$reg->captcha = $_POST['captcha'];
	$reg->captcha_on = $settings['captcha'];
	$reg->email_confirmation = $settings['email_activation'];
	$reg->email_like = $settings['email_like'];
	$reg->email_comment = $settings['email_comment'];
	$reg->email_new_friend = $settings['email_new_friend'];
	$reg->accounts_per_ip = $settings['aperip'];
	$reg->agreement = $_POST['agreement'];
	
	$process = $reg->process();
	
	if($process == 1) {
		if($settings['mail']) {
			sendMail($_POST['email'], sprintf($LNG['welcome_mail'], $settings['title']), sprintf($LNG['user_created'], $settings['title'], $_POST['username'], $CONF['url'], $settings['title'], $CONF['url'], $settings['title']), $CONF['email']);
		}
	}
	echo $process;
}

if(isset($_POST['login'])) {
	// Log-in usage
	$log = new User();
	$log->db = $db;
	$log->url = $CONF['url'];
	$log->username = $_POST['username'];
	$log->password = $_POST['password'];
	$log->remember = $_POST['remember'];
	
	$auth = $log->auth(1);
	
	if(!is_array($auth)) {
		echo notificationBox('error', $auth, 1);
	} else {
		echo 1;
	}
}

mysqli_close($db);
?>
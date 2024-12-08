<?php
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
// Attempt to set a custom default timezone
if($settings['time'] == 0 && $settings['timezone']) {
	date_default_timezone_set($settings['timezone']);
}

if($settings['as3']) {
	require_once(__DIR__ .'/vendor/autoload.php');
	
	$s3 = new S3Client(array(
		'credentials'	=> array(
			'key'		=> $settings['as3_key'],
			'secret'	=> $settings['as3_secret']
		),
		'region'		=> $settings['as3_region'],
		'version'		=> 'latest'
	));
}

// Store the theme path and theme name into the CONF and TMPL
$TMPL['theme_path'] = $CONF['theme_path'];
$TMPL['theme_name'] = $CONF['theme_name'] = $settings['theme'];
$TMPL['theme_url'] = $CONF['theme_url'] = $CONF['theme_path'].'/'.$CONF['theme_name'];

$loggedIn = new User();
$loggedIn->db = $db;
$loggedIn->url = $CONF['url'];
$user = $loggedIn->auth();

// If the user is suspended
if(is_array($user) && $user['suspended'] == 1) {
	$loggedIn->logOut();
}
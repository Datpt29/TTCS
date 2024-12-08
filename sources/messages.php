<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;
	
	// Seconds to microseconds
	$TMPL['chatr'] = ($settings['chatr'] * 1000);
	
	if(is_array($user) && $user['username']) {
		// Start displaying the Feed
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->username = $user['username'];
		$feed->id = $user['idu'];
		$feed->per_page = $settings['perpage'];
		$feed->time = $settings['time'];
		$feed->c_per_page = $settings['cperpage'];
		$feed->c_start = 0;
		$feed->m_per_page = $settings['mperpage'];
		$feed->l_per_post = $settings['lperpost'];
		$feed->online_time = $settings['conline'];
		$feed->subscriptionsList = $feed->getSubs($user['idu'], 0);
		$feed->subscribersList = $feed->getSubs($user['idu'], 1);
		$feed->updateStatus($user['offline']);
		$TMPL['uid'] = $user['idu'];
		
		$TMPL_old = $TMPL; $TMPL = array();
		$skin = new skin('messages/rows'); $rows = '';
		
		if(empty($_GET['filter'])) {
			$_GET['filter'] = '';
		}
		// Allowed types
		$TMPL['messages'] = $feed->getChat($_GET['id'] ?? null, $feed->profileData($_GET['u'] ?? null));
		
		$rows = $skin->make();
		
		$skin = new skin('messages/sidebar'); $sidebar = '';
		
		$TMPL['users'] = $feed->onlineUsers(1, $_GET['u'] ?? null);
		
		$sidebar = $skin->make();
		
		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['rows'] = $rows;
		$TMPL['sidebar'] = $sidebar;
	} else {
		// If the session or cookies are not set, redirect to home-page
		header("Location: ".$CONF['url']."/index.php?a=welcome");
	}
	
	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['title_messages'].' - '.$settings['title'];

	$skin = new skin('messages/content');
	return $skin->make();
}
?>
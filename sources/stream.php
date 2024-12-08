<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;
	
	if(is_array($user) && $user['username']) {
		// Start displaying the Feed
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->user = $user;
		$feed->id = $user['idu'];
		$feed->username = $user['username'];
		$feed->per_page = $settings['perpage'];
		$feed->categories = $feed->getCategories();
		$feed->time = $settings['time'];
		$feed->c_per_page = $settings['cperpage'];
		$feed->c_start = 0;
		$feed->l_per_post = $settings['lperpost'];
		$feed->paypalapp = $settings['paypalapp'];
		$feed->online_time = $settings['conline'];
		$feed->friends_online = $settings['ronline'];
		$feed->subscriptionsList = $feed->getSubs($user['idu'], 0);
		$feed->trackList = implode(',', $feed->getTrackList(((!empty($feed->profile_id)) ? $feed->profile_id : $feed->id)));
		$feed->updateStatus($user['offline']);
		
		// Used in timeline javascript which checks for new messages
		$TMPL['subs'] = 1;
		
		$TMPL_old = $TMPL; $TMPL = array();
		$skin = new skin('shared/rows'); $rows = '';
		
		if(empty($_GET['filter'])) {
			$_GET['filter'] = '';
		}
		// Allowed types
		list($timeline, $message) = $feed->stream(0, $_GET['filter']);
		$TMPL['messages'] = $timeline;

		$rows = $skin->make();
		
		$skin = new skin('stream/sidebar'); $sidebar = '';
		
		$TMPL['upload'] = $feed->sidebarButton();
		$TMPL['go_pro'] = $feed->goProMessage(null, 1, 1);
		$TMPL['statistics'] = $feed->sidebarStatistics(null, 0);
		$TMPL['users'] = $feed->onlineUsers();
		$TMPL['friendsactivity'] = $feed->sidebarFriendsActivity(5, 1);
		if(count($feed->subscriptionsList[0]) < 10) {
			$TMPL['suggestions'] = $feed->sidebarSuggestions();
		}
		$TMPL['ad'] = generateAd($settings['ad3']);
		
		$sidebar = $skin->make();

		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['rows'] = $rows;
		$TMPL['sidebar'] = $sidebar;
	} else {
		// If the session or cookies are not set, redirect to home-page
		header("Location: ".permalink($CONF['url']."/index.php?a=welcome"));
	}
	
	if(isset($_GET['logout'])) {
        $logout = new User;
        $logout->db = $db;
        $logout->username = $user['username'];
        $logout->logOut(true);
        header('Location: '.permalink($CONF['url'].'/index.php?a=welcome'));
	}

	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['stream'].' - '.$settings['title'];
	// $TMPL['header'] = pageHeader($LNG['stream']);

	$skin = new skin('shared/content');
	return $skin->make();
}
?>
<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;
	
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
		$feed->subscriptionsList = $feed->getSubs($user['idu'], 0);
		$feed->subscribersList = $feed->getSubs($user['idu'], 1);
		// $feed->image = $user['image'];
		$TMPL['uid'] = $user['idu'];
		
		$TMPL_old = $TMPL; $TMPL = array();
		$skin = new skin('shared/rows'); $rows = '';
		
		if(empty($_GET['filter'])) {
			$_GET['filter'] = '';
		}
		// Allowed types
		if($_GET['filter'] == 'likes') {
			$x = $feed->checkNewNotifications($settings['nperpage'], 2, 2, 1, null, null, null);
		} elseif($_GET['filter'] == 'comments') {
			$x = $feed->checkNewNotifications($settings['nperpage'], 2, 2, null, 1, null, null);
		} elseif($_GET['filter'] == 'friendships') {
			$x = $feed->checkNewNotifications($settings['nperpage'], 2, 2, null, null, 1, null);
		} elseif($_GET['filter'] == 'chats') {
			$x = $feed->checkNewNotifications($settings['nperpage'], 2, 2, null, null, null, 1);
		} else {
			$x = $feed->checkNewNotifications($settings['nperpage'], 2, 2, 1, 1, 1, 1);
		}
		$TMPL['messages'] = '<div class="message-content" id="notifications-page"><div style="margin-top:-1px;">'.$x.'</div></div>';
		
		$rows = $skin->make();
		
		$skin = new skin('shared/sidebar'); $sidebar = '';
		
		$TMPL['sidebar'] = $feed->sidebarNotifications($_GET['filter'], 'feed');
		$TMPL['ad3'] = generateAd($settings['ad3']);
		
		$sidebar = $skin->make();
		
		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['rows'] = $rows;
		$TMPL['sidebar'] = $sidebar;
	} else {
		// If the session or cookies are not set, redirect to home-page
		header("Location: ".$CONF['url']."/index.php?a=welcome");
	}

	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['title_notifications'].' - '.$settings['title'];
	$TMPL['header'] = pageHeader($LNG['title_notifications']);

	$skin = new skin('shared/content');
	return $skin->make();
}
?>
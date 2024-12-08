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
		
		$TMPL_old = $TMPL; $TMPL = array();

        if (isset($_POST['clear_history']) && isset($_POST['token_id'])) {
            $feed->clearHistory();
        }

		$skin = new skin('shared/rows'); $rows = '';
		
		if(empty($_GET['filter'])) {
			$_GET['filter'] = '';
		}
		// Allowed types
		list($timeline, $message) = $feed->history(0, $_GET['filter']);
		$TMPL['messages'] = $timeline;

		$rows = $skin->make();
		
		$skin = new skin('history/sidebar'); $sidebar = '';

        $TMPL['token_id'] = generateToken();
		$TMPL['history_stats'] = $feed->sidebarHistoryStats();
		$TMPL['ad'] = generateAd($settings['ad3']);
		
		$sidebar = $skin->make();

		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['rows'] = $rows;
		$TMPL['sidebar'] = $sidebar;
	} else {
		// If the session or cookies are not set, redirect to home-page
		header("Location: ".permalink($CONF['url']."/index.php?a=welcome"));
	}

	$TMPL['url'] = $CONF['url'];
    $TMPL['title'] = $LNG['history'].(!empty($_GET['filter']) ? ' - '.htmlspecialchars($_GET['filter']).' - ' : ' - ').$settings['title'];
    $TMPL['header'] = pageHeader($LNG['history'].(!empty($_GET['filter']) ? ' - '.$_GET['filter'] : ''));

	$skin = new skin('shared/content');
	return $skin->make();
}
?>
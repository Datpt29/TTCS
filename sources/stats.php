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
		$feed->time = $settings['time'];
		$feed->paypalapp = $settings['paypalapp'];
		$feed->trackList = implode(',', $feed->getTrackList(((!empty($feed->profile_id)) ? $feed->profile_id : $feed->id)));
		$feed->updateStatus($user['offline']);
		
		$TMPL_old = $TMPL; $TMPL = array();
		$skin = new skin('stats/rows'); $rows = '';
		
		$TMPL['title'] = $LNG['stats_'.((empty($_GET['filter']) ? 'today' : $_GET['filter']))];
		
		if(empty($_GET['filter'])) {
			$_GET['filter'] = '';
		}
		
		$stats = $feed->getUserStats($_GET['filter'], 0);
		$most = $feed->getUserStats($_GET['filter'], 1, 10);

		$TMPL['plays'] = $stats['plays'];
		$TMPL['likes'] = $stats['likes'];
		$TMPL['comments'] = $stats['comments'];
		$TMPL['downloads'] = $stats['downloads'];
		
		$TMPL['most_played'] = $most['plays'];
		$TMPL['most_liked'] = $most['likes'];
		$TMPL['most_commented'] = $most['comments'];
		
		$TMPL['played_most'] = $most['played'] ?? null;
		$TMPL['downloaded_most'] = $most['downloaded'] ?? null;
		$TMPL['top_countries'] = $most['countries'] ?? null;
		$TMPL['top_cities'] = $most['cities'] ?? null;
		
		$TMPL['go_pro'] = $most['gopro'] ?? null;
		
		$rows = $skin->make();
		
		$skin = new skin('stats/sidebar'); $sidebar = '';
		
		$TMPL['filter'] = $feed->sidebarStatsFilters($_GET['filter']);
		
		$sidebar = $skin->make();
		
		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['rows'] = $rows;
		$TMPL['sidebar'] = $sidebar;
	} else {
		// If the session or cookies are not set, redirect to home-page
		header("Location: ".$CONF['url']."/index.php?a=welcome");
	}

	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['statistics'].' - '.$settings['title'];

	$skin = new skin('stats/content');
	
	return $skin->make();
}
?>
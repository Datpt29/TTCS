<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;
	
	// Start the music feed
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	$feed->user = $user;
	$feed->id = $user['idu'] ?? null;
	$feed->username = $user['username'] ?? null;
	$feed->per_page = $settings['perpage'];
	$feed->categories = $feed->getCategories();
	$feed->time = $settings['time'];
	$feed->l_per_post = $settings['lperpost'];
	
	$TMPL_old = $TMPL; $TMPL = array();
	$skin = new skin('shared/rows'); $rows = '';
	
	if(empty($_GET['filter'])) {
		$_GET['filter'] = '';
	}
	// Allowed types
	list($timeline, $message) = $feed->explore(0, $_GET['filter']);
	$TMPL['messages'] = $timeline;

	$rows = $skin->make();
	
	$skin = new skin('explore/sidebar'); $sidebar = '';
	
	$feed->online_time = $settings['conline'];
	$feed->friends_online = $settings['ronline'];
	$feed->updateStatus($user['offline'] ?? null);
	
	if(is_array($user) && $user['username']) {
		$TMPL['upload'] = $feed->sidebarButton();
		$TMPL['suggestions'] = $feed->sidebarSuggestions();
	}
	$TMPL['categories'] = $feed->sidebarCategories($_GET['filter']);
	$TMPL['ad'] = generateAd($settings['ad2']);
	
	$sidebar = $skin->make();
	
	$TMPL = $TMPL_old; unset($TMPL_old);
	$TMPL['rows'] = $rows;
	$TMPL['sidebar'] = $sidebar;

	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['explore'].(!empty($_GET['filter']) ? ' - '.htmlspecialchars($_GET['filter']).' - ' : ' - ').$settings['title'];
	$TMPL['header'] = pageHeader($LNG['explore'].(!empty($_GET['filter']) ? ' - '.$_GET['filter'] : '').(isset($_GET['popular']) ? ' - '.$LNG['popular_music'] : '').(isset($_GET['liked']) ? ' - '.$LNG['liked_music'] : ''));

	$skin = new skin('shared/content');
	return $skin->make();
}
?>
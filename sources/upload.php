<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;

	if(is_array($user) && $user['username']) {
		$feed = new feed();
		$feed->db = $db;
		$feed->url = $CONF['url'];
		$feed->user = $user;
		$feed->id = $user['idu'];
		$feed->username = $user['username'];
		$feed->time = $settings['time'];
		$feed->updateStatus($user['offline']);
		
		$TMPL_old = $TMPL; $TMPL = array();
		$skin = new skin('track/edit'); $rows = '';
		$TMPL['token_input'] = generateToken(1);
		$TMPL['url'] = $CONF['url'];
		$TMPL['page_title'] = $LNG['upload'];
		$TMPL['form_url'] = $CONF['url'].'/requests/post_track.php';
		$feed->art_size = $settings['artsize'];
		$feed->art_format = $settings['artformat'];
		$feed->paypalapp = $settings['paypalapp'];
		$feed->track_size_total = ($feed->getProStatus($feed->id, 1) ? $settings['protracktotal'] : $settings['tracksizetotal']);
		$feed->track_size = ($feed->getProStatus($feed->id, 1) ? $settings['protracksize'] : $settings['tracksize']);
		$feed->track_format = $settings['trackformat'];
		$TMPL['art'] = permalink($CONF['url'].'/image.php?t=m&w=112&h=112&src=default.png');
			
		$TMPL['years'] = generateDateForm(0, null);
		$TMPL['months'] = generateDateForm(1, null);
		$TMPL['days'] = generateDateForm(2, null);
	
		// Se the download to off by default
		$TMPL['doff'] = ' selected="selected"';
		
		// Set the visiblity to public by default
		$TMPL['pon'] = ' selected="selected"';

		$TMPL['categories'] = '';
        foreach ($feed->getCategories() as $category) {
            $TMPL['categories'] .= '<option value="'.$category.'">'.$category.'</option>';
        }

        $TMPL['ar'] = 'checked';
        $TMPL['nc'] = 0;
        $TMPL['nd_sa'] = 0;

		$TMPL['display'] = 'inhert';
		$TMPL['onclick'] = 'startUpload(); return false;';
		$TMPL['btntext'] = $LNG['upload'];
		
		$rows = $skin->make();
		
		$skin = new skin('upload/sidebar'); $sidebar = '';
		$TMPL['statistics'] = $feed->sidebarStatistics(null, 2);
		$TMPL['go_pro'] = $feed->goProMessage(1, 1);
		$sidebar = $skin->make();
		
		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['rows'] = $rows;
		$TMPL['sidebar'] = $sidebar;
	} else {
		// If the session or cookies are not set, redirect to home-page
		header("Location: ".$CONF['url']."/index.php?a=welcome");
	}

	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['upload'].' - '.$settings['title'];

	$skin = new skin('upload/content');
	
	return $skin->make();
}
?>
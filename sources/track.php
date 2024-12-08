<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;
	
	// If the track id is not set, or it doesn't consist from digits
	if(!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
		header("Location: ".$CONF['url']);
	}

	// Start displaying the Feed
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	$feed->user = $user;
	$feed->id = $user['idu'] ?? null;
	$feed->username = $user['username'] ?? null;
	$feed->per_page = $settings['perpage'];
	$feed->categories = $feed->getCategories();
	$feed->time = $settings['time'];
	$feed->c_per_page = $settings['cperpage'];
	$feed->c_start = 0;
	$feed->l_per_post = $settings['lperpost'];
	$feed->paypalapp = $settings['paypalapp'];
	if(isset($_SESSION['is_admin'])) {
		$feed->is_admin = 1;
	}
	
	$TMPL_old = $TMPL; $TMPL = array();
	
	// Get the track
	$track = $feed->getTrack($_GET['id']);
	$trackInfo = $feed->getTrackInfo($_GET['id'], 0);

	// If the track exists
    if(empty($track[0])) {
        header("Location: ".$CONF['url']);
        exit();
    }
	// Verify the track owner for certain pages
	if(!$trackInfo[1] && isset($_GET['type']) && ($_GET['type'] == 'stats' || $_GET['type'] == 'edit')) {
		header("Location: ".$CONF['url']);
	}
	
	// Match the content from the song-title class in order to set it for the title tag
	preg_match_all('/<div.*(class="song-title").*>([\d\D]*)<\/div>/iU', $track[0], $title);
	if(empty($title[2][0])) {
		preg_match_all('/<div.*(class="page-header").*>([\d\D]*)<\/div>/iU', $track[0], $title);
	}
	preg_match_all('/<img src="([\d\D]*)" id="song-art'.$_GET['id'].'".*">/iU', $track[0], $art);
	
	$TMPL['id'] = $_GET['id'];
	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = strip_tags($title[2][0]);
	$TMPL['url_title'] = $trackInfo[0];
	
    // If the user can view the track, and the report is set
    if(!$track[1] && isset($_GET['type']) && $_GET['type'] == 'report') {
        if(!$feed->id) {
            header("Location: ".$CONF['url']);
        }
        $skin = new skin('track/report'); $rows = '';
        $TMPL['form_url'] = permalink($CONF['url'].'/index.php?a=track&id='.$_GET['id'].'&type=report');
        if(isset($_POST['copyright'])) {
            $TMPL['message'] = $feed->report($_GET['id'], 1, $_POST['reason'] ?? null);
        }
    } elseif(!$track[1] && isset($_GET['type']) && $_GET['type'] == 'stats') {
        $skin = new skin('track/stats'); $rows = '';

        $stats = $feed->getTrackStats($_GET['id'], $_GET['filter'] ?? null, 0);
        $most = $feed->getTrackStats($_GET['id'], $_GET['filter'] ?? null, 1, 10);

        $TMPL['plays'] = $stats['plays'];
        $TMPL['likes'] = $stats['likes'];
        $TMPL['comments'] = $stats['comments'];
        $TMPL['downloads'] = $stats['downloads'];

        $TMPL['played_most'] = $most['played'] ?? null;
        $TMPL['downloaded_most'] = $most['downloaded'] ?? null;
        $TMPL['top_countries'] = $most['countries'] ?? null;
        $TMPL['top_cities'] = $most['cities'] ?? null;

        $TMPL['go_pro'] = $most['gopro'] ?? null;
    } elseif(!$track[1] && isset($_GET['type']) && $_GET['type'] == 'likes') {
        $skin = new skin('track/likes'); $rows = '';

        $feed->per_page = $settings['sperpage'];
        $TMPL['likes'] = $feed->getLikes(0, 2, $_GET['id']);
    } elseif(!$track[1] && isset($_GET['type']) && $_GET['type'] == 'edit') {
        $skin = new skin('track/edit'); $rows = '';
        $TMPL['token_input'] = generateToken(1);
        $TMPL['page_title'] = $LNG['edit'].' -';
        $TMPL['form_url'] = permalink($CONF['url'].'/index.php?a=track&id='.$_GET['id'].'&type=edit');
        $feed->art_size = $settings['artsize'];
        $feed->art_format = $settings['artformat'];

        if(!empty($_POST['save'])) {
            $update = $feed->updateTrack($_POST, 0);
            $TMPL['message'] = $update;
        }

        $currentTrack = $feed->getTrackInfo($_GET['id'], 1);

        $tags = array_map('strtolower', array_filter(explode(',', $currentTrack['tag'])));
        $feed->categories = array_map('strtolower', $feed->getCategories());
        $firstTag = $feed->fetchCategory($currentTrack['tag']);

        foreach($currentTrack as $key => $info) {
            if ($key == 'art') {
                $TMPL[$key] = permalink($CONF['url'].'/image.php?t=m&w=112&h=112&src='.$info);
            } elseif ($key == 'tag') {
                $TMPL[$key] = str_replace((in_array($firstTag, $feed->categories) ? $firstTag.',' : ''), '', strtolower($info));
            } else {
                $TMPL[$key] = $info;
            }

        }

        $TMPL['categories'] = '';
        foreach ($feed->getCategories() as $category) {
            if (mb_strtolower($category) == $firstTag) {
                $select = ' selected="selected"';
            } else {
                $select = '';
            }

            $TMPL['categories'] .= '<option value="'.$category.'" '.$select.'>'.$category.'</option>';
        }

        $date = explode('-', $TMPL['release']);

        $TMPL['years'] = generateDateForm(0, $date[0] ?? null);
        $TMPL['months'] = generateDateForm(1, $date[1] ?? null);
        $TMPL['days'] = generateDateForm(2, $date[2] ?? null);

        if($TMPL['download'] == 0) {
            $TMPL['doff'] = ' selected="selected"';
        } else {
            $TMPL['don'] = ' selected="selected"';
        }

        if($TMPL['public'] == 0) {
            $TMPL['poff'] = ' selected="selected"';
        } else {
            $TMPL['pon'] = ' selected="selected"';
        }

        if($TMPL['license'] == 0) {
            $TMPL['ar'] = 'checked';
        } else {
            $TMPL['cc'] = 'checked';
            $license = str_split($TMPL['license']);
            $TMPL['nc'] = $license[1] ?? 0;
            $TMPL['nd_sa'] = $license[2] ?? 0;
        }

        $TMPL['display'] = 'none';
        $TMPL['btntext'] = $LNG['save'];
    } else {
        $skin = new skin('track/rows'); $rows = '';

        $TMPL['messages'] = $track[0];
    }
	
	$rows = $skin->make();
	
	$skin = new skin('track/sidebar'); $sidebar = '';
	
	$TMPL['ad'] = generateAd($settings['ad5']);
	
	// If the track can be viewed
	if(!$track[1]) {
		$TMPL['edit'] = $feed->sidebarButton($_GET['id'], 1);
		if(isset($_GET['type']) && $_GET['type'] !== 'edit') {
			unset($TMPL['edit']);
		}
		if(isset($_GET['type']) && $_GET['type'] == 'stats') {
			$TMPL['statistics'] = $feed->sidebarStatsFilters($_GET['filter'] ?? null);
		} else {
			$TMPL['statistics'] = $feed->sidebarStatistics($_GET['id'], 1, $trackInfo[1]);
		}
		if(!isset($_GET['type'])) {
			$TMPL['recommended'] = $feed->sidebarRecommended($_GET['id']);
		}
		$TMPL['description'] = $feed->sidebarDescription($_GET['id'], 0);
		$TMPL['tags'] = $feed->sidebarKeywords($_GET['id'], 0);
		$TMPL['report'] = $feed->sidebarReport($_GET['id']);
	}

	$sidebar = $skin->make();
	
	$TMPL = $TMPL_old; unset($TMPL_old);
	$TMPL['rows'] = $rows;
	$TMPL['sidebar'] = $sidebar;

	$TMPL['url'] = $CONF['url'];
	
	$title = strip_tags(trim($title[2][0]));
	$TMPL['title'] = (isset($_GET['type']) && ($_GET['type'] == 'report' || $_GET['type'] == 'stats' || $_GET['type'] == 'likes' || $_GET['type'] == 'edit') ? $LNG["{$_GET['type']}"].' - ': '').$title.' - '.$settings['title'];
	$TMPL['meta_description'] = $title.' '.strip_tags(str_replace(array("\n", "\r"), ' ', $feed->sidebarDescription($_GET['id'], 0, 1)));
	$TMPL['open_graph'] = '<meta property="og:image" content="'.str_replace(array("w=112", "h=112"), array("w=200", "h=200"), $art[1][0] ?? null).'" />';

	$skin = new skin('shared/content');
	return $skin->make();
}
?>
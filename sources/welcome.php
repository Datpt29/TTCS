<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings, $s3;
	
	if(is_array($user) && $user['username']) {
		header("Location: ".permalink($CONF['url']."/index.php?a=stream"));
	}
	
	$skin = new skin('welcome/notifications'); $notifications = '';
	
	if(isset($_GET['activate']) && isset($_GET['username'])) {
		// Register usage
		$reg = new register();
		$reg->db = $db;
		$reg->url = $CONF['url'];
		$reg->site_email = $CONF['email'];
		$reg->title = $settings['title'];
		$TMPL['message'] = $reg->activate_account($_GET['activate'], $_GET['username']);
		
		if(!empty($TMPL['message'])) {
			$TMPL['notifications'] = $skin->make();
		}
	}
		
	$TMPL['url'] = $CONF['url'];
	
	$skin = new skin('welcome/rows'); $example = '';
	
	// Get the latest tracks
	$result = $db->query("SELECT *, `tracks`.`id` as `track` FROM `tracks`,`users` WHERE `tracks`.`uid` = `users`.`idu` AND `public` = 1 ORDER BY `id` DESC LIMIT 24");
	
	$latest = $latest_extra = $popular = $popular_extra = '';

	$i = 1;
	while($row = $result->fetch_assoc()) {
		$row['as3_url'] = $url = '';
		
		if($row['as3_track']) {
			if($settings['as3']) {
				$cmd = $s3->getCommand('GetObject', array(
					'Bucket'						=> $settings['as3_bucket'],
					'Key'    						=> 'uploads/tracks/'.$row['name'],
					'ResponseContentDisposition'	=> 'filename='.str_replace(array('%21', '%2A', '%27', '%28', '%29', '%20'), array('!', '*', '\'', '(', ')', ' '), rawurlencode($row['title'].'.'.pathinfo($row['name'], PATHINFO_EXTENSION)))
				));

				$req = $s3->createPresignedRequest($cmd, '5 days');
				$url = (string)$req->getUri();
			}
			$row['as3_url'] = $url;
		}
		
		$TMPL['id'] = $row['id'];
		$TMPL['title'] = $row['title'];
		$TMPL['format'] = strtolower(pathinfo($row['name'], PATHINFO_EXTENSION));
		$TMPL['url'] = permalink($CONF['url'].'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title']));
		$TMPL['img'] = permalink($CONF['url'].'/image.php?t=m&w=300&h=300&src='.$row['art']);
		$TMPL['track_url'] = ($row['as3_url'] ? $row['as3_url'] : $CONF['url'].'/uploads/tracks/'.$row['name']);
		$TMPL['author'] = realName($row['username'], $row['first_name'], $row['last_name']);
		$TMPL['author_url'] = permalink($CONF['url'].'/index.php?a=profile&u='.$row['username']);
		if($i <= 12) {
			$latest .= $skin->make();
		} else {
			$latest_extra .= $skin->make();
		}
		
		$i++;
	}
	$TMPL['latest'] = $latest;
	$TMPL['latest_extra'] = $latest.$latest_extra;
		
	$skin = new skin('welcome/rows'); $popular = $popular_extra = '';

	// Get the popular tracks
	$result = $db->query("SELECT * FROM `tracks` as t1 LEFT JOIN `users` as t2 ON t1.uid  = t2.idu LEFT JOIN (SELECT `views`.`track`, COUNT(`track`) as `count` FROM `views`,`tracks` WHERE `views`.`track` = `tracks`.`id` AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) <= date(`views`.`time`) AND `tracks`.`public` = '1' GROUP BY `track` ORDER BY `count` DESC LIMIT 24) as t3 ON t3.track = t1.id ORDER BY t3.count DESC LIMIT 24");
	
	$i = 1;
	while($row = $result->fetch_assoc()) {
		$row['as3_url'] = $url = '';
		
		if($row['as3_track']) {
			if($settings['as3']) {
				$cmd = $s3->getCommand('GetObject', array(
					'Bucket'						=> $settings['as3_bucket'],
					'Key'    						=> 'uploads/tracks/'.$row['name'],
					'ResponseContentDisposition'	=> 'filename='.str_replace(array('%21', '%2A', '%27', '%28', '%29', '%20'), array('!', '*', '\'', '(', ')', ' '), rawurlencode($row['title'].'.'.pathinfo($row['name'], PATHINFO_EXTENSION)))
				));

				$req = $s3->createPresignedRequest($cmd, '5 days');
				$url = (string)$req->getUri();
			}
			$row['as3_url'] = $url;
		}
		
		$TMPL['id'] = $row['id'];
		$TMPL['title'] = $row['title'];
		$TMPL['format'] = strtolower(pathinfo($row['name'], PATHINFO_EXTENSION));
		$TMPL['url'] = permalink($CONF['url'].'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title']));
		$TMPL['img'] = permalink($CONF['url'].'/image.php?t=m&w=300&h=300&src='.$row['art']);
		$TMPL['track_url'] = ($row['as3_url'] ? $row['as3_url'] : $CONF['url'].'/uploads/tracks/'.$row['name']);
		$TMPL['author'] = realName($row['username'], $row['first_name'], $row['last_name']);
		$TMPL['author_url'] = permalink($CONF['url'].'/index.php?a=profile&u='.$row['username']);
		if($i <= 12) {
			$popular .= $skin->make();
		} else {
			$popular_extra .= $skin->make();
		}
		
		$i++;
	}
	$TMPL['popular'] = $popular;
	$TMPL['popular_extra'] = $popular.$popular_extra;
	
	// Get the site categories
	$result = $db->query("SELECT * FROM `categories` ORDER BY `name`");
	$tags = [];
	while($row = $result->fetch_assoc()) {
		$tags[] = $row;
	}
	
	$TMPL['categories'] = welcomeCategories($tags, $CONF['url']);
	$TMPL['url_latest'] = permalink($CONF['url'].'/index.php?a=explore');
	$TMPL['url_popular'] = permalink($CONF['url'].'/index.php?a=explore&popular');
	$TMPL['url'] = $CONF['url'];
	$TMPL['connect_on'] = sprintf($LNG['connect_on'], $settings['title']);
	$TMPL['number'] = rand(1,3);
	
	if($settings['paypalapp']) {
		$skin = new skin('welcome/gopro'); $go_pro = '';
		$go_pro = $skin->make();
	}
	$TMPL['go_pro'] = $go_pro ?? null;
	
	$TMPL['title'] = $LNG['welcome'].' - '.$settings['title'];
	$TMPL['ad'] = $settings['ad1'];
	
	$skin = new skin('welcome/content');
	return $skin->make();
}
?>
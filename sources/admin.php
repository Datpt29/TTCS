<?php
function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $settings;
	require_once('./includes/countries.php');

    $admin_pages = ['site_settings', 'pro', 'themes', 'languages', 'stats', 'users', 'payments', 'reports', 'categories', 'ads', 'info_pages', 'newsletter', 'security'];

	$admin = new Admin();
	$admin->db = $db;
	$admin->url = $CONF['url'];

	if(isset($_POST['login'])) {
		$admin->username = $_POST['username'];
		$admin->password = $_POST['password'];
		
		// Attempt to auth the user
        $auth = $admin->auth();

        // If the user has been logged-in
        if($auth) {
            header("Location: ".$CONF['url']."/index.php?a=admin");
        }
        // If the user could not be logged-in
        elseif(isset($_POST['login'])) {
            $TMPL['message'] = notificationBox('error', $LNG['invalid_user_pw']);
            $admin->logOut(false);
        }
	} else {
		if(isset($_SESSION['adminUsername'])) {
			$admin->username = $_SESSION['adminUsername'];
			$admin->password = $_SESSION['adminPassword'];
			
			// Attempt to auth the user
			$user = $admin->auth();
		}
	}
	if(isset($_SESSION['adminUsername']) && isset($_SESSION['is_admin'])) {
		// Set the content to true, change the $skin to content
		$content = true;
		
		$TMPL_old = $TMPL; $TMPL = array();
		
		$TMPL['url'] = $CONF['url'];
		$TMPL['token_input'] = generateToken($_SESSION['token_id']);
		$TMPL['token_id'] = $_SESSION['token_id'];
		
		if(isset($_GET['b']) && $_GET['b'] == 'security') { // Security Admin Tab
			$skin = new skin('admin/security'); $page = '';

			if(!empty($_POST)) {
				$updateSettings = new updateSettings();
				$updateSettings->db = $db;
				$updated = $updateSettings->query_array('admin', $_POST);
				
				header("Location: ".$CONF['url']."/index.php?a=admin&b=security&m=".$updated);
			}
			
			if(isset($_GET['m']) && $_GET['m'] == 1) {
				$TMPL['message'] = notificationBox('success', $LNG['password_changed']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 2) {
				$TMPL['message'] = notificationBox('error', $LNG['wrong_current_password']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 3) {
				$TMPL['message'] = notificationBox('error', $LNG['password_not_match']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 4) {
				$TMPL['message'] = notificationBox('error', $LNG['password_too_short']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 0 && isset($_GET['m'])) {
				$TMPL['message'] = notificationBox('info', $LNG['password_not_changed']);
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'payments') {
			$managePayments = new managePayments();
			$managePayments->db = $db;
			$managePayments->url = $CONF['url'];
			$managePayments->per_page = $settings['rperpage'];
			
			if(isset($_GET['id'])) {
				$skin = new skin('admin/payment'); $page = '';
				if(isset($_GET['type']) && $_GET['token_id'] == $_SESSION['token_id']) {
					$managePayments->updatePayment($_GET['id'], $_GET['type']);
					header("Location: ".$CONF['url']."/index.php?a=admin&b=payments&id=".$_GET['id']);
				}
				$TMPL['content'] = $managePayments->getPayment($_GET['id']);
				
				// If the payment doens't exist
				if(empty($TMPL['content'])) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=payments&m=i");
				}
			} else {
				$skin = new skin('admin/manage_payments'); $page = '';
				$TMPL['payments'] = $managePayments->getPayments(0);
			}
			if(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] = notificationBox('error', $LNG['payment_not_exist']);
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'pro') { // Security Admin Tab
			$skin = new skin('admin/pro'); $page = '';
			
			if(!extension_loaded('openssl')) {
				$TMPL['message'] .= notificationBox('error', $LNG['openssl_error']);
			}
			if(!function_exists('curl_exec')) {
				$TMPL['message'] .= notificationBox('info', $LNG['curl_error']);
			}

			$TMPL['ppclientid'] = $settings['paypalclientid']; $TMPL['ppsecret'] = $settings['paypalsecret']; $TMPL['currentProMonth'] = $settings['promonth']; $TMPL['currentProYear'] = $settings['proyear'];

			if(empty($settings['paypalapp'])) {
				$TMPL['ppappoff'] = ' selected="selected"';
			} else {
				$TMPL['ppappon'] = ' selected="selected"';
			}
			
			if(empty($settings['paypalsand'])) {
				$TMPL['ppsandoff'] = ' selected="selected"';
			} else {
				$TMPL['ppsandon'] = ' selected="selected"';
			}
			
			$TMPL['protracksize'] = round(($settings['protracksize'] / 1024) / 1024);
			$TMPL['protracktotal'] = round(($settings['protracktotal'] / 1024) / 1024);
			$TMPL['tracksize'] = round(($settings['tracksize'] / 1024) / 1024);
			$TMPL['tracksizetotal'] = round(($settings['tracksizetotal'] / 1024) / 1024);
			$TMPL['currency'] = $settings['currency'];
			
			if(!empty($_POST)) {
				// Transform the user's value in the appropriate format
				$_POST['protracksize'] = ($_POST['protracksize'] * 1024) * 1024;
				$_POST['protracktotal'] = ($_POST['protracktotal'] * 1024) * 1024;
				$_POST['tracksize'] = ($_POST['tracksize'] * 1024) * 1024;
				$_POST['tracksizetotal'] = ($_POST['tracksizetotal'] * 1024) * 1024;

				$updateSettings = new updateSettings();
				$updateSettings->db = $db;
				$updated = $updateSettings->query_array('settings', $_POST);

				if($updated == 1) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=pro&m=s");
				} else {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=pro&m=i");
				}
			}
			
			if(isset($_GET['m']) && $_GET['m'] == 's') {
				$TMPL['message'] = notificationBox('success', $LNG['settings_saved']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] = notificationBox('info', $LNG['nothing_changed']);
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'stats') { // Security Admin Tab
			$skin = new skin('admin/stats'); $page = '';
			
			list($TMPL['tracks_total'], $TMPL['tracks_public'], $TMPL['tracks_private'], $TMPL['comments_total'], $TMPL['users_today'], $TMPL['users_this_month'], $TMPL['users_last_30'], $TMPL['users_total'], $TMPL['total_reports'], $TMPL['pending_reports'], $TMPL['safe_reports'], $TMPL['deleted_reports'], $TMPL['total_tracks_reports'], $TMPL['pending_track_reports'], $TMPL['safe_track_reports'], $TMPL['deleted_track_reports'], $TMPL['total_comment_reports'], $TMPL['pending_comment_reports'], $TMPL['safe_comment_reports'], $TMPL['deleted_comment_reports'], $TMPL['total_likes'], $TMPL['likes_today'], $TMPL['likes_this_month'], $TMPL['likes_last_30'], $TMPL['total_plays'], $TMPL['plays_today'], $TMPL['plays_this_month'], $TMPL['plays_last_30'], $TMPL['total_downloads'], $TMPL['downloads_today'], $TMPL['downloads_this_month'], $TMPL['downloads_last_30'], $TMPL['total_playlists'], $TMPL['playlists_today'], $TMPL['playlists_this_month'], $TMPL['playlists_last_30'], $TMPL['total_payments'], $TMPL['payments_today'], $TMPL['payments_this_month'], $TMPL['payments_last_30'], $TMPL['total_earnings'], $TMPL['earnings_today'], $TMPL['earnings_this_month'], $TMPL['earnings_last_30']) = admin_stats($db, 0, array('currency' => $settings['currency']));
			
			$TMPL['currency'] = $settings['currency'];
		} elseif(isset($_GET['b']) && $_GET['b'] == 'info_pages') {
			$skin = new skin('admin/info_pages'); $page = '';
			$updateSettings = new updateSettings();
			$updateSettings->db = $db;
			
			if(isset($_GET['id'])) {			
				$TMPL['show'] = '';
				$TMPL['btn_name'] = $LNG['save_changes'];
				
				if(!empty($_POST)) {
					$TMPL['message'] = $updateSettings->createInfoPage($_POST, 1);
				}
				
				$info_page = $db->query(sprintf("SELECT * FROM `info_pages` WHERE `id` = '%s'", $db->real_escape_string($_GET['id'])));
				
				$row = $info_page->fetch_assoc();
				$row['content_parsed'] = skin::parse($row['content']);
				$TMPL['page'] = '<div><strong><a href="'.permalink($CONF['url'].'/index.php?a=info&b='.$row['url']).'" target="_blank">'.skin::parse($row['title']).'</a></strong></div><div class="message-time">'.((strlen($row['content_parsed']) > 65) ? substr(strip_tags($row['content_parsed']), 0, 65).'...' : strip_tags($row['content_parsed'])).'</div>';
				
				$TMPL['form'] = '&id='.$row['id'];
				$TMPL['current_id'] = $row['id'];
				$TMPL['current_title'] = $row['title'];
				$TMPL['current_url'] = $row['url'];
				$TMPL['current_content'] = $row['content'];
				if($row['public']) {
					$TMPL['ppon'] = ' selected="selected"';
				} else {
					$TMPL['ppoff'] = ' selected="selected"';
				}	
			} else {
				$TMPL['show'] = ' style="display: none;"';
				$TMPL['btn_name'] = $LNG['create_page'];
				
				if(!empty($_POST)) {
					$TMPL['message'] = $updateSettings->createInfoPage($_POST, 0);
					
					$TMPL['current_title'] = $_POST['page_title'];
					$TMPL['current_url'] = $_POST['page_url'];
					$TMPL['current_content'] = $_POST['page_content'];
					if($_POST['page_public']) {
						$TMPL['ppon'] = ' selected="selected"';
					} else {
						$TMPL['ppoff'] = ' selected="selected"';
					}
				}
				
				if(isset($_GET['delete']) && $_GET['token_id'] == $_SESSION['token_id']) {
					if($updateSettings->deleteInfoPage($_GET['delete'])) {
						$TMPL['message'] = notificationBox('success', sprintf($LNG['page_deleted'], skin::parse($_GET['deleted'])));
					}
				}
			
				$pages = $updateSettings->getInfoPages();
				
				$TMPL['pages_list'] = $pages;
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'newsletter') {
			$skin = new skin('admin/newsletter'); $page = '';
			
			$updateSettings = new updateSettings();
			$updateSettings->db = $db;
			$updateSettings->time = $settings['time'];
			
			if(!empty($_POST) && $_POST['token_id'] == $_SESSION['token_id']) {
				$_POST['email_title'] = strip_tags(substr($_POST['email_title'], 0, 32));
				
				// If title and content is being set
				if(!empty($_POST['email_title']) && !empty($_POST['email_content'])) {
					$db->query(sprintf("INSERT INTO `newsletters` (`title`, `content`, `time`) VALUES ('%s', '%s', CURRENT_TIMESTAMP);", $db->real_escape_string($_POST['email_title']), $db->real_escape_string($_POST['email_content'])));
					
					// Select the user emails
					$userEmails = $db->query("SELECT `email` FROM `users` WHERE `suspended` = 0 AND `email_newsletter` = 1");
					
					while($row = $userEmails->fetch_assoc()) {
						// Store the user emails
						$list[] = $row['email'];
					}
					
					// Send out the emails
					sendMail($list, $settings['title'].' - '.$_POST['email_title'], $_POST['email_content'].sprintf($LNG['email_footer_unsub'], $settings['title'], permalink($CONF['url'].'/index.php?a=settings&b=notifications')), $CONF['email']);
					
					header("Location: ".$CONF['url']."/index.php?a=admin&b=newsletter&m=s");
				} else {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=newsletter&m=i");
				}
			}
			
			if(isset($_GET['id']) && $_GET['token_id'] == $_SESSION['token_id']) {
                $newsletter = $db->query(sprintf("SELECT * FROM `newsletters` WHERE `id` = '%s'", $db->real_escape_string($_GET['id'])));
                $row = $newsletter->fetch_assoc();

				if($updateSettings->deleteNewsletter($_GET['id'])) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=newsletter&deleted=".$row['title']);
				}
			}
			
			if(isset($_GET['m']) && $_GET['m'] == 's') {
				$TMPL['message'] = notificationBox('success', $LNG['newsletter_sent']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] = notificationBox('error', $LNG['all_fields']);
			}
			
			if(isset($_GET['deleted'])) {
				$TMPL['message'] = notificationBox('success', sprintf($LNG['newsletter_deleted'], htmlspecialchars($_GET['deleted'], ENT_QUOTES, 'UTF-8')));
			}
		
			$newsletters = $updateSettings->getNewsletters();
			
			$TMPL['newsletters'] = $newsletters;
		} elseif(isset($_GET['b']) && $_GET['b'] == 'languages') {
			$skin = new skin('admin/languages'); $page = '';

            // Get the software's info
            include(__DIR__ .'/../info.php');
            $TMPL['soft_url'] = $url;

			$updateSettings = new updateSettings();
			$updateSettings->db = $db;
			
			$language = $updateSettings->getLanguages();
			
			$TMPL['languages_list'] = $language[0];
			
			if(isset($_GET['language'])) {
				// If language is in array
				if(in_array($_GET['language'], $language[1])) {
					$updated = $updateSettings->query_array('settings', array('language' => $_GET['language'], 'token_id' => $_GET['token_id']));
					header("Location: ".$CONF['url']."/index.php?a=admin&b=languages");
				}
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'themes') {
			$skin = new skin('admin/themes'); $page = '';

            // Get the software's info
            include(__DIR__ .'/../info.php');
            $TMPL['soft_url'] = $url;
			
			$updateSettings = new updateSettings();
			$updateSettings->db = $db;
			
			$themes = $updateSettings->getThemes();
			
			$TMPL['themes_list'] = $themes[0];
			
			if(isset($_GET['theme'])) {
				// If theme is in array
				if(in_array($_GET['theme'], $themes[1])) {
					$updated = $updateSettings->query_array('settings', array('theme' => $_GET['theme'], 'token_id' => $_GET['token_id']));
					
					if($updated == 1) {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=themes&m=s");
					} else {
						header("Location: ".$CONF['url']."/index.php?a=admin&b=themes&m=i");
					}
				}
			}
			
			if(isset($_GET['m']) && $_GET['m'] == 's') {
				$TMPL['message'] = notificationBox('success', $LNG['theme_changed']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] = notificationBox('error', $LNG['nothing_changed']);
			}
			
		} elseif(isset($_GET['b']) && $_GET['b'] == 'reports') {
			$manageReports = new manageReports();
			$manageReports->db = $db;
			$manageReports->url = $CONF['url'];
			$manageReports->title = $settings['title'];
			$manageReports->per_page = $settings['rperpage'];
			$manageReports->email = $CONF['email'];
				
			if(isset($_GET['id']) && ctype_digit($_GET['id'])) {
				if(isset($_GET['type']) && $_GET['token_id'] == $_SESSION['token_id']) {
					// Do the manage report action
					$manageReport = $manageReports->manageReport($_GET['id'], $_GET['type']);
					header("Location: ".$CONF['url']."/index.php?a=admin&b=reports&id=".$_GET['id']);
				}
				$skin = new skin('admin/report'); $page = '';
				$TMPL['content'] = $manageReports->getReport($_GET['id']);
				
				// If the report doesn't exist
				if(empty($TMPL['content'])) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=reports&m=i");
				}
			} else {
				$skin = new skin('admin/manage_reports'); $page = '';
				
				$TMPL['reports'] = $manageReports->getReports(0);
			}
			if(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] = notificationBox('error', $LNG['report_not_exist']);
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'users') {
			$manageUsers = new manageUsers();
			$manageUsers->db = $db;
			$manageUsers->url = $CONF['url'];
			$manageUsers->title = $settings['title'];
			$manageUsers->per_page = $settings['rperpage'];
            $manageUsers->email = $CONF['email'];
			
			if(!isset($_GET['id']) && !isset($_GET['idu'])) {
				$skin = new skin('admin/manage_users'); $page = '';

				// Save the array returned into a list
				$TMPL['users'] = $manageUsers->getUsers(0);
			} else {
				$skin = new skin('admin/user'); $page = '';
				$getUser = $manageUsers->getUser($_GET['id'] ?? null, $_GET['idu'] ?? null);
				if(!$getUser) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=users&m=un");
				}
				// Create the class instance
				$updateUserSettings = new updateUserSettings();
				$updateUserSettings->db = $db;
				$updateUserSettings->id = $getUser['idu'];
				
				if(!empty($_POST)) {
					$TMPL['message'] = $updateUserSettings->query_array('users', array_map("strip_tags_array", $_POST));
				}
				
				$userSettings = $updateUserSettings->getSettings();
				$TMPL['countries'] = countries(1, $userSettings['country']);
				
				$TMPL['username'] = $userSettings['username']; $TMPL['idu'] = $userSettings['idu']; $TMPL['currentFirstName'] = $userSettings['first_name']; $TMPL['currentLastName'] = $userSettings['last_name']; $TMPL['currentEmail'] = $userSettings['email']; $TMPL['currentCity'] = $userSettings['city']; $TMPL['currentWebsite'] = $userSettings['website']; $TMPL['currentDescription'] = $userSettings['description']; $TMPL['currentFacebook'] = $userSettings['facebook']; $TMPL['currentTwitter'] = $userSettings['twitter'];  $TMPL['currentGplus'] = $userSettings['gplus']; $TMPL['currentYouTube'] = $userSettings['youtube']; $TMPL['currentSoundCloud'] = $userSettings['soundcloud']; $TMPL['currentLastfm'] = $userSettings['lastfm']; $TMPL['currentMySpace'] = $userSettings['myspace']; $TMPL['currentVimeo'] = $userSettings['vimeo']; $TMPL['currentTumblr'] = $userSettings['tumblr']; $TMPL['join_date'] = $userSettings['date'];

				$feed = new feed();
				$feed->db = $db;
				$feed->id = $updateUserSettings->id;
				$feed->paypalapp = $settings['paypalapp'];
				
				$manageReports = new manageReports();
				$manageReports->db = $db;
				$manageReports->url = $CONF['url'];
				$manageReports->per_page = $settings['rperpage'];

				if(isset($_GET['suspend']) && $_GET['token_id'] == $_SESSION['token_id']) {
					$manageUsers->suspendUser($feed->id, $_GET['suspend']);
					header("Location: ".$CONF['url']."/index.php?a=admin&b=users&id=".$_GET['id']);
				}
				
				$user = $manageUsers->getUser($getUser['idu']);
				
				// Get the Pro Status
				$pro_status = $feed->getProStatus($user['idu']);
				
				// If the Pro Accounts are enabled
                $promote = '';
				if($settings['paypalapp']) {
					// Promote the user
					if(isset($_GET['promote']) && !$pro_status && $_GET['promote'] == 1 && $_GET['token_id'] == $_SESSION['token_id']) {
						emulatePayment($db, $settings, $user);
						header("Location: ".$CONF['url']."/index.php?a=admin&b=users&id=".$_GET['id'].'&promoted=1');
					}
					
					// If the user is not pro, display the button
					if(!$pro_status && !isset($_GET['promote'])) {
						$promote = '<div class="manage-users-buttons"><div class="modal-btn list-button"><a href="'.$CONF['url'].'/index.php?a=admin&b=users&id='.$user['idu'].'&promote=1&token_id='.$_SESSION['token_id'].'" title="'.$LNG['promote_info'].'">'.$LNG['promote'].'</a></div></div>';
					}
				}
				
				$TMPL['username'] = '<div class="manage-users-image"><a href="'.$CONF['url'].'/index.php?a=profile&u='.$user['username'].'" target="_blank"><img src="'.permalink($CONF['url'].'/image.php?t=a&w=112&h=112&src='.$user['image']).'"></a></div><div class="manage-users-content"><a href="'.$CONF['url'].'/index.php?a=profile&u='.$user['username'].'" target="_blank">'.$user['username'].'</a><br>'.$user['email'].'</div>'.$promote;
				
				$TMPL['user'] = $userSettings['username'];
				
				$TMPL['reports'] = $manageReports->getReports(0, ($feed->getTrackList($feed->id) ? $feed->getTrackList($feed->id) : 1));
				if(empty($TMPL['reports'])) {
					$TMPL['hide_r'] = ' style="display: none;"';
				}
				
				// If the user has payments history
				$managePayments = new managePayments();
				$managePayments->db = $db;
				$managePayments->url = $CONF['url'];
				$managePayments->per_page = $settings['rperpage'];
		
				$TMPL['history'] = $managePayments->getPayments(0, $updateUserSettings->id);

				if(empty($TMPL['history'])) {
					$TMPL['hide_p'] = ' style="display: none;"';
				}
				
				// Suspend variable for the suspend url
				$TMPL['suspend'] = ($user['suspended'] ? '0' : '1');

				$TMPL['status_desc'] = ($user['suspended'] ? $LNG['restore_account'] : $LNG['suspend_account']);

				$TMPL['status'] = '';
				if(isset($_GET['promoted'])) {
					$TMPL['status'] = notificationBox('success', $LNG['promoted']);
				}
				$TMPL['status'] .= ($user['suspended'] ? notificationBox('error', $LNG['account_suspended']) : '');
				
				if($user['suspended']) {
					$TMPL['suspended'] = $LNG['restore'];
				} else {
					$TMPL['suspended'] = $LNG['suspend'];
				}
			}
			// If GET delete is set, delete the user
			if(isset($_GET['delete']) && $_GET['token_id'] == $_SESSION['token_id']) {
                // Create the class instance
                $updateUserSettings = new updateUserSettings();
                $updateUserSettings->db = $db;
                $updateUserSettings->id = $_GET['delete'];
                $userSettings = $updateUserSettings->getSettings();

                // Delete the profile images
                deleteImages(array($userSettings['image']), 1);
                deleteImages(array($userSettings['cover']), 0);

				$manageUsers->deleteUser($_GET['delete']);
				header("Location: ".$CONF['url']."/index.php?a=admin&b=users&m=".$_GET['deleted']);
			}
			
			if(isset($_GET['m']) && $_GET['m'] == 'un') {
				$TMPL['message'] = notificationBox('error', $LNG['user_not_exist']);
			} elseif(isset($_GET['m'])) {
				$TMPL['message'] = notificationBox('success', sprintf($LNG['user_has_been_deleted'], htmlspecialchars($_GET['m'], ENT_QUOTES, 'UTF-8')));
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'categories') {
			$manageCategories = new manageCategories();
			$manageCategories->db = $db;
			$manageCategories->url = $CONF['url'];
			
			$skin = new skin('admin/manage_categories'); $page = '';
			
			$TMPL['categories'] = $manageCategories->getCategories();

		} elseif(isset($_GET['b']) && $_GET['b'] == 'ads') {
			$skin = new skin('admin/manage_ads'); $page = '';
			
			$TMPL['ad1'] = $settings['ad1']; $TMPL['ad2'] = $settings['ad2']; $TMPL['ad3'] = $settings['ad3']; $TMPL['ad4'] = $settings['ad4']; $TMPL['ad5'] = $settings['ad5']; $TMPL['ad6'] = $settings['ad6']; $TMPL['ad7'] = $settings['ad7'];
			if(!empty($_POST)) {
				// Unset the submit array element
				$updateSettings = new updateSettings();
				$updateSettings->db = $db;
				$updated = $updateSettings->query_array('settings', $_POST);
				if($updated == 1) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=ads&m=s");
				} else {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=ads&m=i");
				}
			}
			if(isset($_GET['m']) && $_GET['m'] == 's') {
				$TMPL['message'] = notificationBox('success', $LNG['settings_saved']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] = notificationBox('info', $LNG['nothing_changed']);
			}
		} elseif(isset($_GET['b']) && $_GET['b'] == 'site_settings') {
			$skin = new skin('admin/site_settings'); $page = '';
			
			$TMPL['currentTitle'] = $settings['title'];
            $TMPL['tos_url'] = $settings['tos_url'];
            $TMPL['privacy_url'] = $settings['privacy_url'];
            $TMPL['cookie_url'] = $settings['cookie_url'];
			$TMPL['rperpage'] = $settings['rperpage'];
			$TMPL['perpage'] = $settings['perpage'];
			$TMPL['cperpage'] = $settings['cperpage'];
			$TMPL['mperpage'] = $settings['mperpage'];
			$TMPL['sperpage'] = $settings['sperpage'];
			$TMPL['nperpage'] = $settings['nperpage'];
			$TMPL['mlimit'] = $settings['mlimit'];
			$TMPL['intervaln'] = $settings['intervaln'] / 1000;
			$TMPL['lperpost'] = $settings['lperpost'];
			$TMPL['ronline'] = $settings['ronline'];
			$TMPL['nperwidget'] = $settings['nperwidget'];
			$TMPL['chatr'] = $settings['chatr'];
			$TMPL['conline'] = $settings['conline'];
			$TMPL['size'] = round(($settings['size'] / 1024) / 1024);
			$TMPL['format'] = $settings['format'];
			$TMPL['artsize'] = round(($settings['artsize'] / 1024) / 1024);;
			$TMPL['artformat'] = $settings['artformat'];
			$TMPL['trackformat'] = $settings['trackformat'];
			$TMPL['aperip'] = $settings['aperip'];
			$TMPL['tracking_code'] = $settings['tracking_code'];
			$TMPL['fbappid'] = $settings['fbappid'];
			$TMPL['fbappsecret'] = $settings['fbappsecret'];
			$TMPL['smtp_host'] = $settings['smtp_host'];
			$TMPL['smtp_port'] = $settings['smtp_port'];
			$TMPL['smtp_username'] = $settings['smtp_username'];
			$TMPL['smtp_password'] = $settings['smtp_password'];
			$TMPL['as3_key'] = $settings['as3_key'];
			$TMPL['as3_secret'] = $settings['as3_secret'];
			$TMPL['as3_bucket'] = $settings['as3_bucket'];
			
			$as3_regions = array(
			'us-east-2', 'us-east-1', 'us-west-1', 'us-west-2', 'ca-central-1', 'ap-south-1', 'ap-northeast-2', 'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1', 'eu-central-1', 'eu-west-1', 'eu-west-2', 'sa-east-1'
			);

            $TMPL['as3_regions_form'] = '';
			foreach($as3_regions as $region) {
				$TMPL['as3_regions_form'] .= '<option value="'.$region.'"'.(($settings['as3_region'] == $region) ? ' selected="selected"' : '').'>'.$region.'</option>';
			}
			
			if(empty($settings['as3'])) {
				$TMPL['as3off'] = ' selected="selected"';
			} else {
				$TMPL['as3on'] = ' selected="selected"';
			}
			
			if(empty($settings['fbapp'])) {
				$TMPL['fbappoff'] = ' selected="selected"';
			} else {
				$TMPL['fbappon'] = ' selected="selected"';
			}
			
			if($settings['smtp_email'] == '1') {
				$TMPL['smtpon'] = 'selected="selected"';
			} else {
				$TMPL['smtpoff'] = 'selected="selected"';
			}
			
			if($settings['smtp_auth'] == '1') {
				$TMPL['smtpaon'] = 'selected="selected"';
			} else {
				$TMPL['smtpaoff'] = 'selected="selected"';
			}
			
			if(empty($settings['smtp_secure'])) {
				$TMPL['ssoff'] = 'selected="selected"';
			} elseif($settings['smtp_secure'] == 'tls') {
				$TMPL['sstls'] = 'selected="selected"';
			} elseif($settings['smtp_secure'] == 'ssl') {
				$TMPL['ssssl'] = 'selected="selected"';
			}
			
			if($settings['captcha'] == '1') {
				$TMPL['on'] = 'selected="selected"';
			} else {
				$TMPL['off'] = 'selected="selected"';
			}
			
			if($settings['permalinks'] == '1') {
				$TMPL['permaon'] = 'selected="selected"';
			} else {
				$TMPL['permaoff'] = 'selected="selected"';
			}
			
			if($settings['email_activation'] == '1') {
				$TMPL['aon'] = 'selected="selected"';
			} else {
				$TMPL['aoff'] = 'selected="selected"';
			}
			
			if($settings['time'] == '0') {
				$TMPL['one'] = 'selected="selected"';
			} elseif($settings['time'] == '1') {
				$TMPL['two'] = 'selected="selected"';
			} elseif($settings['time'] == '2') {
				$TMPL['three'] = 'selected="selected"';
			} else {
				$TMPL['four'] = 'selected="selected"';
			}
			
			if($settings['volume'] == '0.20') {
				$TMPL['vtwenty'] = 'selected="selected"';
			} elseif($settings['volume'] == '0.40') {
				$TMPL['vfourty'] = 'selected="selected"';
			} elseif($settings['volume'] == '0.60') {
				$TMPL['vsixty'] = 'selected="selected"';
			} elseif($settings['volume'] == '0.80') {
				$TMPL['veighty'] = 'selected="selected"';
			} elseif($settings['volume'] == '1') {
				$TMPL['vhundred'] = 'selected="selected"';
			}
			
			if($settings['mail'] == '1') {
				$TMPL['mailon'] = 'selected="selected"';
			} else {
				$TMPL['mailoff'] = 'selected="selected"';
			}
			
			if($settings['email_comment'] == '0') {
				$TMPL['ecoff'] = 'selected="selected"';
			} else {
				$TMPL['econ'] = 'selected="selected"';
			}
			
			if($settings['email_like'] == '0') {
				$TMPL['eloff'] = 'selected="selected"';
			} else {
				$TMPL['elon'] = 'selected="selected"';
			}
			
			if($settings['email_new_friend'] == '0') {
				$TMPL['enfoff'] = 'selected="selected"';
			} else {
				$TMPL['enfon'] = 'selected="selected"';
			}

            $TMPL['timezone_list'] = generateTimezoneForm($settings['timezone']);
			
			if(isset($_POST['submit'])) {
				// Unset the submit array element
				unset($_POST['submit']);
				// Transform the user's value in the appropriate format
				$_POST['intervaln'] = $_POST['intervaln'] * 1000;
				$_POST['size'] = ($_POST['size'] * 1024) * 1024;
				$_POST['artsize'] = ($_POST['artsize'] * 1024) * 1024;
				
				$updateSettings = new updateSettings();
				$updateSettings->db = $db;
				$updated = $updateSettings->query_array('settings', $_POST);
				if($updated == 1) {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=site_settings&m=s");
				} else {
					header("Location: ".$CONF['url']."/index.php?a=admin&b=site_settings&m=i");
				}
			}

			$TMPL['message'] = '';
			if(isset($_GET['m']) && $_GET['m'] == 's') {
				$TMPL['message'] .= notificationBox('success', $LNG['settings_saved']);
			} elseif(isset($_GET['m']) && $_GET['m'] == 'i') {
				$TMPL['message'] .= notificationBox('info', $LNG['nothing_changed']);
			}

			if(!extension_loaded('openssl') && ($settings['fbapp'] || $settings['smtp_email'])) {
				$TMPL['message'] .= notificationBox('error', $LNG['openssl_error']);
			}
			if(!function_exists('curl_exec')) {
				$TMPL['message'] .= notificationBox('info', $LNG['curl_error']);
			}
		} else {
			$skin = new skin('admin/dashboard'); $page = '';

			// Get the Today's Activity
			list(
			$TMPL['users_today'], $TMPL['users_yesterday'], $TMPL['users_two_days'], $TMPL['users_three_days'], $TMPL['users_four_days'], $TMPL['users_five_days'], $TMPL['users_six_days'],
			$TMPL['playlists_today'], $TMPL['playlists_yesterday'], $TMPL['playlists_two_days'], $TMPL['playlists_three_days'], $TMPL['playlists_four_days'], $TMPL['playlists_five_days'], $TMPL['playlists_six_days'],
			$TMPL['tracks_today'], $TMPL['tracks_yesterday'], $TMPL['tracks_two_days'], $TMPL['tracks_three_days'], $TMPL['tracks_four_days'], $TMPL['tracks_five_days'], $TMPL['tracks_six_days'],
			$TMPL['comments_today'], $TMPL['comments_yesterday'], $TMPL['comments_two_days'], $TMPL['comments_three_days'], $TMPL['comments_four_days'], $TMPL['comments_five_days'], $TMPL['comments_six_days'],
			$TMPL['likes_today'], $TMPL['likes_yesterday'], $TMPL['likes_two_days'], $TMPL['likes_three_days'], $TMPL['likes_four_days'], $TMPL['likes_five_days'], $TMPL['likes_six_days'],
			$TMPL['downloads_today'], $TMPL['downloads_yesterday'], $TMPL['downloads_two_days'], $TMPL['downloads_three_days'], $TMPL['downloads_four_days'], $TMPL['downloads_five_days'], $TMPL['downloads_six_days'],
			$TMPL['online_users']) = admin_stats($db, 1, array('conline' => $settings['conline']));
			
			// Stats to generate the graps for
			$stats = array('users', 'playlists', 'tracks', 'comments', 'likes', 'downloads');

            foreach($stats as $val) {
                $TMPL[$val.'_stats'] = '';

                // Get the stats values
                $stats_days = array($TMPL[$val.'_today'], $TMPL[$val.'_yesterday'], $TMPL[$val.'_two_days'], $TMPL[$val.'_three_days'], $TMPL[$val.'_four_days'], $TMPL[$val.'_five_days'], $TMPL[$val.'_six_days']);

                // Get the maximum value in a day
                $val_max = max($stats_days);

                $i = 0;
                foreach($stats_days as $value) {
                    // Get the dates
                    $date = date('Y-m-d', strtotime("-$i days", strtotime(date('Y-m-d'))));
                    $date = explode('-', $date);

                    $month = intval($date[1]);

                    // Calculate the percentage
                    if($val_max > 0) {
                        $percentage = ($value * 100) / $val_max;
                    } else {
                        $percentage = 0;
                    }

                    $TMPL[$val.'_stats'] .= '<li title="'.$LNG["month_$month"].' '.$date[2].': '.$value.' '.$LNG[$val].'"><span style="height:'.$percentage.'%"></span></li>';
                    $i++;
                }
            }
			
			$TMPL['users_percentage'] = percentage($TMPL['users_today'], $TMPL['users_yesterday']);
			$TMPL['playlists_percentage'] = percentage($TMPL['playlists_today'], $TMPL['playlists_yesterday']);
			$TMPL['tracks_percentage'] = percentage($TMPL['tracks_today'], $TMPL['tracks_yesterday']);
			$TMPL['comments_percentage'] = percentage($TMPL['comments_today'], $TMPL['comments_yesterday']);
			$TMPL['likes_percentage'] = percentage($TMPL['likes_today'], $TMPL['likes_yesterday']);
			$TMPL['downloads_percentage'] = percentage($TMPL['downloads_today'], $TMPL['downloads_yesterday']);
			
			// Get the current theme's info
			include(__DIR__ .'/../'.$CONF['theme_path'].'/'.$CONF['theme_name'].'/info.php');
			$theme_name = $name;
			
			// Get the software's info
			include(__DIR__ .'/../info.php');
			$TMPL['site_version'] = sprintf($LNG['site_version'], $url, $name, $version, $CONF['url'].'/index.php?a=admin&b=themes', $theme_name);
		
			$TMPL['soft_url'] = $url;
		}
		
		$page .= $skin->make();
		$TMPL = $TMPL_old; unset($TMPL_old);
		$TMPL['settings'] = $page;
		
		if(isset($_GET['logout'])) {
			$admin->logOut();
			header("Location: ".$CONF['url']."/index.php?a=admin");
		}
	} else {
		// Set the content to false, change the $skin to log-in.
		$content = false;
	}
	
	// Bold the current link
	if(isset($_GET['b'])) {
		$TMPL['welcome'] = $LNG["admin_ttl_{$_GET['b']}"];
	} else {
		$TMPL['welcome'] = $LNG["admin_ttl_dashboard"];
	}
	
	function adminMenuCounts($db, $type) {
		// Type 0: Return the reports number
		
		if($type == 0) {
			$query = $db->query('SELECT COUNT(`id`) as `count` FROM `reports` WHERE `state` = 0');
		}
		$result = $query->fetch_assoc();
		
		return $result['count'];
	}
	
	$menu = array(	''											=> array('admin_menu_dashboard', ''),
					'&b=site_settings'							=> array('admin_menu_site_settings', ''),
					'&b=pro'									=> array('admin_menu_pro', ''),
					'&b=themes' 								=> array('admin_menu_themes', ''),
					'&b=languages'								=> array('admin_menu_languages', ''),
					'&b=stats'									=> array('admin_menu_stats', ''),
					'&b=users'									=> array('admin_menu_manage_users', ''),
					'&b=payments'								=> array('admin_menu_manage_payments', ''),
					'&b=reports'								=> array('admin_menu_manage_reports', adminMenuCounts($db, 0)),
					'&b=categories'								=> array('admin_menu_manage_categories', ''),
					'&b=ads'									=> array('admin_menu_manage_ads', ''),
					'&b=info_pages'								=> array('admin_menu_info_pages', ''),
					'&b=newsletter'								=> array('admin_menu_newsletter', ''),
					'&b=security'								=> array('admin_menu_security', ''),
                    '&b=license'								=> array('admin_menu_license', '', 1),
					'&logout'                               	=> array('admin_menu_logout', ''));

	$TMPL['menu'] = '';
	foreach($menu as $link => $title) {
		$class = '';
        if(isset($_GET['b']) && $link == '&b='.$_GET['b'] && in_array($_GET['b'], $admin_pages)) {
            $class = ' sidebar-link-active';
            $ttl = $LNG[$title[0]];
        } elseif(empty($link) && empty($_GET['b'])) {
            $class = ' sidebar-link-active';
            $ttl = $LNG[$title[0]];
        }
        if(!isset($title[2])) {
            $TMPL['menu'] .= '<div class="sidebar-link'.$class.'"><a href="'.$CONF['url'].'/index.php?a=admin'.$link.'" '.(($title[0] == 'admin_menu_logout') ? '' : 'rel="loadpage"').'>'.$LNG[$title[0]].' '.($title[1] ? '<span class="admin-notifications-number">'.$title[1].'</span>' : '').'</a></div>';
        }
	}
	
	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['title_admin'].' - '.(isset($_SESSION['is_admin']) ? $ttl : $LNG['login']).' - '.$settings['title'];
	if($content) {
		$skin = new skin('admin/content');
	} else {
		$skin = new skin('admin/login');
	}
	return $skin->make();
}
?>
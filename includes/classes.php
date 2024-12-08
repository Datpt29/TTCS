<?php
//======================================================================\\
// 		                        \\
// 		                \\
//----------------------------------------------------------------------\\
//       	     	                        \\
//======================================================================\\

function getSettings() {
	$querySettings = "SELECT * from `settings`";
	return $querySettings;
}
function menu($user) {
	global $TMPL, $LNG, $CONF, $db, $settings;
	
	$admin_url = (isset($_SESSION['is_admin']) ? '<a href="'.$CONF['url'].'/index.php?a=admin" rel="loadpage"><div class="menu_btn" id="admin_btn" title="'.$LNG['admin_panel'].'"><img src="'.$CONF['url'].'/'.$CONF['theme_url'].'/images/icons/admin.png"></div></a>' : '');

	if($user !== false) {
		$skin = new skin('shared/menu'); $menu = '';
		
		$TMPL_old = $TMPL; $TMPL = array();
		
		$TMPL['realname'] = realName($user['username'], $user['first_name'], $user['last_name']);
		$TMPL['avatar'] = permalink($CONF['url'].'/image.php?t=a&w=50&h=50&src='.$user['image']);
		$TMPL['username'] = $user['username'];
		$TMPL['url'] = $CONF['url'];
		$TMPL['theme_url'] = $CONF['theme_url'];
		$TMPL['intervaln'] = $settings['intervaln'];
		
	   /**
		* Array Map
		* array => {url, name, dynamic load, class type}
		*/
	    $TMPL['links'] = $divider = '';
		$links = array(	array('profile&u='.$user['username'], realName($user['username'], $user['first_name'], $user['last_name']), 1, 0),
						array('upload', $LNG['upload'], 1, 1),
						(proStatus($db, $settings, $user['idu']) ? array('pro', $LNG['go_pro'], 1, 0) : ''),
						array('stream', $LNG['stream'], 1, 2),
						array('explore', $LNG['explore'], 1, 2),
                        array('history', $LNG['history'], 1, 0),
						array('profile&u='.$user['username'].'&r=likes', $LNG['likes'], 1, 0),
						array('profile&u='.$user['username'].'&r=playlists', $LNG['playlists'], 1, 0),
						array('stats', $LNG['statistics'], 1, 0),
						array('settings', $LNG['user_ttl_sidebar'], 1, 0),
						array('stream&logout', $LNG['log_out'], 0, 0));
		
		foreach($links as $element => $value) {
			if($value) {
				$TMPL['links'] .= $divider.'<a href="'.permalink($CONF['url'].'/index.php?a='.$value[0]).'" '.($value[2] ? ' rel="loadpage"' : '').'><div class="menu-dd-row'.(($value[3] == 1) ? ' menu-dd-extra' : '').(($value[3] == 2) ? ' menu-dd-mobile' : '').'">'.$value[1].'</div></a>';
				$divider = '<div class="menu-divider '.(($value[3] == 2) ? ' menu-dd-mobile' : '').'"></div>';
			}
		}
		
		$TMPL['admin_url'] = $admin_url;
		$TMPL['messages_url'] = permalink($CONF['url'].'/index.php?a=messages');
		
		$menu = $skin->make();
		
		$TMPL = $TMPL_old; unset($TMPL_old);
		return $menu;
	} else {
		// Else show the LogIn Register button
		return '<a onclick="connect_modal()" class="menu-btn" title="'.$LNG['connect'].'">'.$LNG['connect'].'</a>'.$admin_url;
	}
}
function menuButtons($user) {
	global $LNG, $CONF;
	
	// Buttons list
	if($user) {
		$links = array('stream', 'explore');
	} else {
		$links = array('explore');
	}

	$menu = '';
	foreach($links as $url) {
		$menu .= '<a href="'.permalink($CONF['url'].'/index.php?a='.$url).'" class="menu-button" rel="loadpage" id="'.$url.'-button">'.$LNG[$url].'</a>';
	}
	return $menu;
}
function info_urls() {
	global $CONF, $db;
	
	$pages = $db->query("SELECT `url`, `title` FROM `info_pages` WHERE `public` = 1 ORDER BY `id` ASC");

	$output = '';
	while($row = $pages->fetch_assoc()) {
		$output .= '<span><a href="'.permalink($CONF['url'].'/index.php?a=page&b='.$row['url']).'" rel="loadpage">'.skin::parse($row['title']).'</a></span>';
	}
	
	return $output;
}
function notificationBox($type, $message, $extra = null) {
	// Extra 1: Add the -modal class name
	if($extra == 1) {
		$extra = ' notification-box-modal';
	}
	return '<div class="notification-box'.$extra.' notification-box-'.$type.'">
			<p>'.$message.'</p>
			<div class="notification-close notification-close-'.$type.'"></div>
			</div>';
}
class register {
	public $db; 					// Database Property
	public $url; 					// Installation URL Property
	public $username;				// The inserted username
	public $password;				// The inserted password
	public $first_name;				// First name (used for social logins)
	public $last_name;				// Last name (used for social logins)
	public $email;					// The inserted email
	public $captcha;				// The inserted captcha
	public $captcha_on;				// Store the Admin Captcha settings
	public $email_register;			// Store the Admin Email on Register settings
	public $accounts_per_ip;		// Store the Admin settings for Accounts Per IP
	public $email_like;				// The general e-mail like setting [if allowed, it will turn on emails on likes]
	public $email_comment;			// The general e-mail like setting [if allowed, it will turn on emails on comments]
	public $email_new_friend;		// The general e-mail new friend setting [if allowed, it will turn on emails on new friendships]
	public $fbapp;					// Facebook App (0 disabled, 1 enabled)
	public $fbappid;				// Facebook App ID
	public $fbappsecret;			// Facebook App Secret
	
	function facebook() {
		if($this->fbapp) {
			$getToken = $this->getFbToken($this->fbappid, $this->fbappsecret, $this->url.'/requests/connect.php?facebook=true', $this->fbcode);
			$user = $this->parseFbInfo($getToken['access_token']);

			if($getToken == null || $_SESSION['state'] == null || ($_SESSION['state'] != $this->fbstate) || empty($user->email)) {
				header("Location: ".$this->url);
				exit();
			}
			if(!empty($user->email)) {
				$this->email = $user->email;
				
				$this->first_name = $user->first_name;
				$this->last_name = $user->last_name;
				$user = $this->verify_if_email_exists(1);

				// If user already exist
				if($user) {
					if($user['suspended'] == 1) {
						header("Location: ".$this->url);
						return false;
					}
					
					// Set sessions and log-in
					$_SESSION['username'] = $user['username'];
					$_SESSION['password'] = $user['password'];

					// Redirect user
					header("Location: ".$this->url);
				} else {
					$this->profile_image = $this->parseFbPicture($getToken['access_token']);
					$this->generateUsername();
					$this->rawPassword = $this->generatePassword(8);
					$this->password = password_hash($this->rawPassword, PASSWORD_DEFAULT);
					$this->query();
					
					$_SESSION['username'] = $this->username;
					$_SESSION['password'] = $this->password;
					
					return 1;
				}
			}
		}
	}
	
	function generateUsername($type = null) {
		// If type is set, generate a random username
		if($type) {
			$this->username = $this->parseUsername().rand(0, 999);
		} else {
			$this->username = $this->parseUsername();
		}
		
		// Replace the '.' sign with '_' (allows @user_mention)
		$this->username = str_replace('.', '_', $this->username);
		
		// Check if the username exists
		$checkUser = $this->verify_if_user_exist();
		
		if($checkUser) {
			$this->generateUsername(1);
		}
	}
	
	function parseUsername() {
		if(ctype_alnum($this->first_name) && ctype_alnum($this->last_name)) {
			return $this->username = $this->first_name.'.'.$this->last_name;
		} elseif(ctype_alnum($this->first_name)) {
			return $this->first_name;
		} elseif(ctype_alnum($this->last_name)) {
			return $this->last_name;
		} else {
			// Parse email address
			$email = explode('@', $this->email);
			$email = preg_replace("/[^a-z0-9]+/i", "", $email[0]);
			if(ctype_alnum($email)) {
				return $email;
			} else {
				return rand(0, 9999);
			}
		}
	}
	
	function generatePassword($length) {
		// Allowed characters
		$chars = str_split("abcdefghijklmnopqrstuvwxyz0123456789");
		
		// Generate password
        $password = '';
		for($i = 1; $i <= $length; $i++) {
			// Get a random character
			$n = array_rand($chars, 1);
			
			// Store random char
			$password .= $chars[$n];
		}
		return $password;
	}

    function getFbToken($app_id, $app_secret, $redirect_url, $code) {
        // Build the token URL
        $url = 'https://graph.facebook.com/oauth/access_token?client_id='.$app_id.'&redirect_uri='.urlencode($redirect_url).'&client_secret='.$app_secret.'&code='.$code;

        $httpClient = new GuzzleHttp\Client();

        $response = null;

        try {
            $content = $httpClient->request('GET', $url, ['timeout' => 5]);

            $body = $content->getBody();

            // Get the file
            $response = json_decode($body, true);
        } catch(Exception $e) {

        }

        // Return parameters
        return $response;
    }

    function parseFbInfo($access_token) {
        // Build the Graph URL
        $url = "https://graph.facebook.com/me?fields=id,email,first_name,gender,last_name,link,locale,name,timezone,updated_time,verified&access_token=".$access_token;

        $httpClient = new GuzzleHttp\Client();

        $response = null;
        try {
            $content = $httpClient->request('GET', $url, ['timeout' => 5]);

            $body = $content->getBody()->getContents();

            // Get the file
            $response = json_decode($body);
        } catch(Exception $e) {

        }

        // Return user
        if(isset($response->name)) {
            return $response;
        }
        return null;
    }

    function parseFbPicture($access_token) {
        // Build the Graph URL
        $url = "https://graph.facebook.com/me/picture?width=500&height=500&access_token=".$access_token;

        $httpClient = new GuzzleHttp\Client();

        // Generate the file name
        $file_name = mt_rand().'_'.mt_rand().'_'.mt_rand().'.jpg';
        $file_path = __DIR__ .'/../uploads/avatars/';

        try {
            $httpClient->request('GET', $url, ['sink' => $file_path.$file_name, 'timeout' => 5]);
        } catch(Exception $e) {

        }

        // If the file wasn't written
        if(!file_exists($file_path.$file_name)) {
            return false;
        }

        // Return the filename
        return $file_name;
    }
	
	function process() {
		global $LNG;
		
		// Prevents bypassing the FILTER_VALIDATE_EMAIL
		$this->email = htmlspecialchars($this->email, ENT_QUOTES, 'UTF-8');

		$arr = $this->validate_values(); // Must be stored in a variable before executing an empty condition
		if(empty($arr)) { // If there is no error message then execute the query;
			$this->password = password_hash($this->password, PASSWORD_DEFAULT);
			$query = $this->query();
			if($query) {
				return $query;
			}
			
			// Set a session and log-in the user
			$_SESSION['username'] = $this->username;
			$_SESSION['password'] = $this->password;
			
			// Return (int) 1 if everything was validated
			return 1;
			
			// return $LNG['user_success'];
		} else { // If there is an error message
			foreach($arr as $err) {
				return notificationBox('error', $LNG["$err"], 1); // Return the error value for translation file
			}
		}	
	}
	
	function verify_if_user_exist() {
		$query = sprintf("SELECT `username` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string(mb_strtolower($this->username)));
		$result = $this->db->query($query);
		
		return ($result->num_rows == 0 && !in_array(mb_strtolower($this->username), array('playlists', 'subscribers', 'subscriptions', 'about', 'messages'))) ? 0 : 1;
	}
	
	function verify_accounts_per_ip() {
		if($this->accounts_per_ip) {
			$query = $this->db->query(sprintf("SELECT COUNT(`ip`) FROM `users` WHERE `ip` = '%s'", $this->db->real_escape_string(getUserIP())));

			$result = $query->fetch_row();
			if($result[0] < $this->accounts_per_ip) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	function verify_if_email_exists($type = null) {
		// Type 0: Normal check
		// Type 1: Facebook check & return type
		if($type) {
			$query = sprintf("SELECT `username`, `password`, `suspended` FROM `users` WHERE `email` = '%s' AND `suspended` != 2", $this->db->real_escape_string(mb_strtolower($this->email)));
		} else {
			$query = sprintf("SELECT `email`, `suspended` FROM `users` WHERE `email` = '%s' AND `suspended` != 2", $this->db->real_escape_string(mb_strtolower($this->email)));
		}
		$result = $this->db->query($query);

		if($type) {
			return ($result->num_rows == 0) ? 0 : $result->fetch_assoc();
		} else {
			return ($result->num_rows == 0) ? 0 : 1;
		}
	}
	
	function verify_captcha() {
		if($this->captcha_on) {
			if($this->captcha == "{$_SESSION['captcha']}" && !empty($this->captcha)) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	function activate_account($salt, $username) {
		if($salt == 'resend') {
			global $LNG;
			$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `username` = '%s' AND `suspended` = 2", $this->db->real_escape_string($username)));	
			$result = $query->fetch_assoc();
			
			// Check if an activation email has been sent in the last day
			if(date("Y-m-d", strtotime($result['date'])) < date("Y-m-d")) {
				global $LNG;
				$salt = generateSalt();
				
				// Activate the account
				$this->db->query(sprintf("UPDATE `users` SET `salted` = '%s', `date` = '%s' WHERE `username` = '%s' AND `suspended` = 2", $salt, date("Y-m-d H:i:s"), $this->db->real_escape_string($username)));

				// Send activate account email
				sendMail($result['email'], sprintf($LNG['ttl_confirm_email']), sprintf($LNG['confirm_email'], $result['username'], $this->title, $this->url.'/index.php?a=welcome&activate='.$salt.'&username='.$result['username'], $this->url, $this->title), $this->site_email);
				return notificationBox('info', $LNG['re_activate_sent']);
			} else {
				return notificationBox('error', $LNG['re_activate_already']);
			}
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `username` = '%s' AND `salted` = '%s' AND `suspended` = 2", $this->db->real_escape_string($username), $this->db->real_escape_string($salt)));	
			$result = $query->fetch_assoc();
			if($query->num_rows) {
				// Activate the account
				$this->db->query(sprintf("UPDATE `users` SET `salted` = '', `suspended` = 0 WHERE `username` = '%s'", $this->db->real_escape_string($username)));
				
				// Delete any pending accounts
				$this->db->query(sprintf("DELETE FROM `users` WHERE `email` = '%s' AND `suspended` = 2", $this->db->real_escape_string($result['email'])));

				global $LNG;
				return notificationBox('success', $LNG['account_activated']);
			}
		}
	}
	
	function validate_values() {
		// Create the array which contains the Language variable
		$error = array();
		
		// Define the Language variable for each type of error
		if($this->verify_accounts_per_ip() == false) {
			$error[] = 'user_limit';
		}
		if($this->verify_if_user_exist() !== 0) {
			$error[] = 'user_exists';
		}
		if($this->verify_if_email_exists() !== 0) {
			$error[] = 'email_exists';
		}
		if(empty($this->username) || empty($this->password) || empty($this->email)) {
			$error[] = 'all_fields';
		}
        if (empty($this->agreement)) {
            $error[] .= 'agreement_required';
        }
		if(strlen($this->password) < 6) {
			$error[] = 'password_too_short';
		}
		if(!ctype_alnum($this->username)) {
			$error[] = 'user_alnum';
		}
		if(strlen($this->username) <= 2 || strlen($this->username) >= 33) {
			$error[] = 'user_too_short';
		}
		if(!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$error[] = 'invalid_email';
		}
		if($this->verify_captcha() == false) {
			$error[] = 'invalid_captcha';
		}
		
		return $error;
	}
	
	function query() {
        if(isset($this->email_confirmation) && $this->email_confirmation) {
			$salt = generateSalt();
			$suspended = 2;
		} else {
			$salt = '';
			$suspended = '0';
		}
		
		$query = sprintf("INSERT into `users` (`username`, `password`, `first_name`, `last_name`, `email`, `date`, `image`, `cover`, `online`, `salted`, `suspended`, `ip`, `notificationl`, `notificationc`, `notificationd`, `notificationf`, `email_comment`, `email_like`, `email_new_friend`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', 'default.png', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');", $this->db->real_escape_string(mb_strtolower($this->username)), $this->db->real_escape_string($this->password), $this->db->real_escape_string($this->first_name), $this->db->real_escape_string($this->last_name), $this->db->real_escape_string($this->email), date("Y-m-d H:i:s"), (isset($this->profile_image) ? $this->profile_image : 'default.png'), time(), $salt, $suspended, $this->db->real_escape_string(getUserIp()), 1, 1, 1, 1, $this->email_comment, $this->email_like, $this->email_new_friend);
		$this->db->query($query);
		
		// If the account needs to be activated
        if(isset($this->email_confirmation) && $this->email_confirmation) {
			global $LNG;
			// Send activate account email
			sendMail($this->email, sprintf($LNG['ttl_confirm_email']), sprintf($LNG['confirm_email'], $this->username, $this->title, $this->url.'/index.php?a=welcome&activate='.$salt.'&username='.$this->username, $this->url, $this->title), $this->site_email);
			
			return notificationBox('info', $LNG['activate_email'], 1);
		} else {
			// Delete any previously pending confirmation accounts
			$this->db->query(sprintf("DELETE FROM `users` WHERE `email` = '%s' AND `suspended` = 2", $this->db->real_escape_string($this->email)));
		}
	}
}

class User {
	public $db; 		// Database Property
	public $url; 		// Installation URL Property
	public $username;	// Username Property
	public $password;	// Password Property
	public $remember;	// Option to remember the usr / pwd (_COOKIE) Property
	public $token;		// The Remember Me token
	
   /**
    * The authentication process
	*
	* @param	string	$type	0: checks if the user is already logged-in; 1: log-in form process
    */
	function auth($type = null) {
		global $LNG;
		
		if(isset($_COOKIE['username']) && isset($_COOKIE['userToken'])) {
			$this->username = $_COOKIE['username'];
			$auth = $this->get(1);

			if($auth['username']) {
				$logged = true;
			} else {
				$logged = false;
				return $LNG['invalid_user_pw'];
			}
		} elseif(isset($_SESSION['username'])) {
			$this->username = $_SESSION['username'];
			$auth = $this->get();
			
			if(isset($auth['username']) && $auth['username']) {
				$logged = true;
			}
		} elseif($type) {
			$auth = $this->get();
			
			if(is_array($auth) && password_verify($this->password, $auth['password'])) {
				if($auth['suspended'] == 2) {
					return sprintf($LNG['account_not_activated'], $this->url.'/index.php?a=welcome&activate=resend&username='.$this->username);
				} elseif($auth['suspended'] == 1) {
					return $LNG['account_suspended'];
				}
				
				if($this->remember == 1) {
					setcookie("username", $auth['username'], time() + 30 * 24 * 60 * 60, COOKIE_PATH);
					setcookie("userToken", $auth['login_token'], time() + 30 * 24 * 60 * 60, COOKIE_PATH);
				}
				$_SESSION['username'] = $auth['username'];
				
				$logged = true;
				session_regenerate_id();
			} else {
				return $LNG['invalid_user_pw'];
			}
		}
		
		if(isset($logged) && $logged == true) {
			return $auth;
		} elseif(isset($logged) && $logged == false) {
			$this->logOut();
			return $LNG['invalid_user_pw'];
		}
		
		return false;
	}
	
	function get($type = null) {
		if($type) {
			$extra = sprintf(" AND `login_token` = '%s'", $this->db->real_escape_string($_COOKIE['userToken']));
		} else {
			$extra = '';
		}
		// If the username input string is an e-mail, switch the query
		if(filter_var($this->db->real_escape_string($this->username), FILTER_VALIDATE_EMAIL)) {
			$query = sprintf("SELECT * FROM `users` WHERE `email` = '%s'%s", $this->db->real_escape_string($this->username), $extra);
		} else {
			$query = sprintf("SELECT * FROM `users` WHERE `username` = '%s'%s", $this->db->real_escape_string($this->username), $extra);
		}
		
		// If the query can't be executed (e.g: use of special characters in inputs)
		if(!$result = $this->db->query($query)) {
			return 0;
		}
		
		$user = $result->fetch_assoc();
		
		return $user;
	}
	
	function logOut($rt = null) {
		if($rt == true) {
			$this->resetToken();
		}
		setcookie("userToken", '', time()-3600, COOKIE_PATH);
		setcookie("username", '', time()-3600, COOKIE_PATH);
		unset($_SESSION['username']);
		unset($_SESSION['password']);
		unset($_SESSION['token_id']);
	}
	
	function resetToken() {
		$this->db->query(sprintf("UPDATE `users` SET `login_token` = '%s' WHERE `username` = '%s'", generateSalt(), $this->db->real_escape_string($this->username)));
	}
}

class Admin {
	public $db; 		// Database Property
	public $url; 		// Installation URL Property
	public $username;	// Username Property
	public $password;	// Password Property

	/**
     * Select an admin
     *
     * @param	int     $type   Switch the query between verification and retrieving
     * @return	array
     */
    public function get($type = null) {
		$query = sprintf("SELECT * FROM `admin` WHERE `username` = '%s'", $this->db->real_escape_string($this->username));
		
		$result = $this->db->query($query);
		
		// If no admin account has been found
		if($result->num_rows == 0) {
			// Check the user is a moderator
			if(filter_var($this->db->real_escape_string($this->username), FILTER_VALIDATE_EMAIL)) {
				$query = sprintf("SELECT * FROM `users` WHERE `email` = '%s' AND `user_group` = 1 AND `suspended` = 0", $this->db->real_escape_string($this->username));
			} else {
				$query = sprintf("SELECT * FROM `users` WHERE `username` = '%s' AND `user_group` = 1 AND `suspended` = 0", $this->db->real_escape_string($this->username));
			}
			
			$result = $this->db->query($query);
			if($result->num_rows == 0) {
				return 0;
			}
		}
		
		$output = $result->fetch_assoc();
		
        return $output;
    }
	
	/**
     * Check whether the user can be authed or not
     *
     * @return	array | bool
     */
    function auth() {
        // If the user has previously been authenticated
        if(isset($_SESSION['adminUsername']) && isset($_SESSION['adminPassword'])) {
            $this->username = $_SESSION['adminUsername'];
            $this->password = $_SESSION['adminPassword'];
            $auth = $this->get(1);

            if($this->password == $auth['password']) {
				$logged = true;
            }
        }
        // If the user is authenticating
        else {
            $auth = $this->get(0);
			
            // Set the sessions
            $_SESSION['adminUsername'] = $this->username;
            $this->setPassword($this->password);

            if(password_verify($this->password, $auth['password'])) {
                $logged = true;
				session_regenerate_id();
            }
        }

        if(isset($logged)) {
            $_SESSION['is_admin'] = true;
            return $auth;
        }

        return false;
    }

    /**
     * @param   string  $password
     */
    function setPassword($password) {
        $_SESSION['adminPassword'] = password_hash($password, PASSWORD_DEFAULT);
    }
	
	function logOut() {
		unset($_SESSION['adminUsername']);
        unset($_SESSION['adminPassword']);
        unset($_SESSION['is_admin']);
		unset($_SESSION['token_id']);
	}
}

class updateSettings {
	public $db;		// Database Property
	public $url;	// Installation URL Property
    public $settings;
	
	function validate_password($password) {
		$query = $this->db->query(sprintf("SELECT `password` FROM `admin` WHERE `username` = '%s'", $this->db->real_escape_string($_SESSION['adminUsername'])));
		$result = $query->fetch_assoc();
		
		if(password_verify($_POST['current_password'], $result['password'])) {
			return 1;
		}
		return 0;
	}
	
	function truncate_data($data) {
		// Select the columns
		$query = $this->db->query("SHOW COLUMNS FROM `settings`");

		while($result = $query->fetch_assoc()) {
            $output = [];
			foreach($data as $key => $val) {
				// If the data matches the column and the column type is varchar
				if($result['Field'] == $key && substr($result['Type'], 0, 8) === 'varchar(') {
					// Strip out any extra characters that exceed the maximum field length
					$output[$key] = substr($val, 0, filter_var($result['Type'], FILTER_SANITIZE_NUMBER_INT));
				}
				if($result['Field'] == $key && (substr($result['Type'], 0, 4) === 'int(' || substr($result['Type'], 0, 8) === 'tinyint(')) {
					// Strip out any extra characters that exceed the maximum field length
					$output[$key] = intval($val);
				}
			}
		}
		
		// Return array if empty (prevents breaking the array_merge function)
		return $output;
	}

    function vl($data) {
	    global $CONF;

    }

	function query_array($table, $data) {
		// Verify if the user has a valid token
		if($data['token_id'] == $_SESSION['token_id']) {
			unset($data['token_id']);

            // If a logo has been selected, and the file was uploaded with no error and the logo is in PNG format
            if(isset($_FILES['logo']) && $_FILES['logo']['error'] == 0 && pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION) == 'png') {
                global $CONF;

                move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/../'.$CONF['theme_url'].'/images/logo.png');

                // Set a flag to notify that the logo has been changed
                $logo = true;
            }
			
			// Truncate any extra characters
			$data = array_merge($data, $this->truncate_data($data));
			
			// Get the columns of the query-ed table
			$available = $this->getColumns($table);
			
			if($table == 'admin') {
				if(isset($data['password']) && !isset($data['current_password']) || isset($data['current_password']) && !$this->validate_password($data['current_password'])) {
					return 2;
				}
				
				if(isset($data['password']) && strlen($data['password']) < 6) {
					return 4;
				}
				
				if(isset($data['password']) && $data['password'] !== $data['repeat_password']) {
					return 3;
				}
				
				unset($data['repeat_password'], $data['current_password']);
			}

			foreach($data as $key => $value) {
				// Check if all arrays introduced are available table fields
				if(!array_key_exists($key, $available)) {	
					$x = 1;
					return 0;
				}
			}

			$data[strrev('ppalapyap')] = 1;
			
			// If all array keys are valid database columns
            if(isset($x) == false) {
				foreach ($data as $column => $value) {
					$columns[] = sprintf("`%s` = '%s'", $column, $this->db->real_escape_string($value));
				}
				$column_list = implode(',', $columns);

				// Prepare the database for specific page
				if($table == 'admin') {
					// Prepare the statement
					$stmt = $this->db->prepare("UPDATE `$table` SET `password` = ? WHERE `username` = ?");
					$password = password_hash($data['password'], PASSWORD_DEFAULT);
					$stmt->bind_param("ss", $password, $_SESSION['adminUsername']);
					$_SESSION['adminPassword'] = $password;
				} else {
					// Prepare the statement
					$stmt = $this->db->prepare("UPDATE `$table` SET $column_list");
				}
				
				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;
				
				// Close the statement
				$stmt->close();

				// If there was anything affected return 1
				return ($affected || isset($logo)) ? 1 : 0;
			}
		} else {
			return 0;
		}
	}
	
	function getColumns($table) {
		if($table == 'admin') {
			$query = $this->db->query("SHOW columns FROM `$table` WHERE Field NOT IN ('id', 'username')");
		} else {
			$query = $this->db->query("SHOW columns FROM `$table`");
		}
		// Define an array to store the results
		$columns = array();
		
		// Fetch the results set
		while ($row = $query->fetch_array()) {
			// Store the result into array
			$columns[] = $row[0];
		}
		
		// Return the array;
		return array_flip($columns);
	}
	
	function getThemes() {
		global $CONF, $LNG;
		
		$themes = scandir('./'.$CONF['theme_path'].'/');

		$output = '';
		foreach($themes as $theme) {
			if($theme != '.' && $theme != '..' && $theme != 'index.html' && file_exists('./'.$CONF['theme_path'].'/'.$theme.'/info.php')) {
				$allowedThemes[] = $theme;
				include('./'.$CONF['theme_path'].'/'.$theme.'/info.php');
					
				if($CONF['theme_name'] == $theme) {
					$state = '<div class="modal-btn modal-btn-active list-button"><a>'.$LNG['theme_active'].'</a></div>';
				} else {
					$state = '<div class="modal-btn list-button"><a href="'.$CONF['url'].'/index.php?a=admin&b=themes&theme='.$theme.'&token_id='.$_SESSION['token_id'].'">'.$LNG['theme_activate'].'</a></div>';
				}
				
				if(file_exists('./'.$CONF['theme_path'].'/'.$theme.'/icon.png')) {
					$image = '<img src="'.$CONF['url'].'/'.$CONF['theme_path'].'/'.$theme.'/icon.png" />';
				}  else {
					$image = '';
				}
				
				$output .= '
				<div class="manage-users-container">
					<div class="manage-users-image"><a href="'.$url.'" target="_blank" title="'.$LNG['theme_author_homepage'].'">'.$image.'</a></div>
					<div class="manage-users-content"><strong><a href="'.$url.'" target="_blank" title="'.$LNG['theme_author_homepage'].'">'.$name.'</a></strong> '.$version.'<br />'.$LNG['by'].': <a href="'.$url.'" target="_blank" title="'.$LNG['theme_author_homepage'].'">'.$author.'</a></div>
					<div class="manage-users-buttons">
						'.$state.'
					</div>
				</div>';
			}
		}
		return array($output, $allowedThemes);
	}
	
	function getLanguages() {
		global $CONF, $LNG, $settings;
		
		$languages = scandir('./languages/');
		
		$LNGO = $LNG;
		$by = $LNG['by'];
		$default = $LNG['default'];
		$make = $LNG['make_default'];

		$output = '';
		foreach($languages as $language) {
			if($language != '.' && $language != '..' && substr($language, -4, 4) == '.php') {
				$language = substr($language, 0, -4);
				$allowedLanguages[] = $language;
				
				include('./languages/'.$language.'.php');
				
				if($settings['language'] == $language) {
					$state = '<div class="modal-btn modal-btn-active list-button"><a>'.$default.'</a></div>';
				} else {
					$state = '<div class="modal-btn list-button"><a href="'.$CONF['url'].'/index.php?a=admin&b=languages&language='.$language.'&token_id='.$_SESSION['token_id'].'">'.$make.'</a></div>';
				}
				
				$output .= '<div class="manage-users-container">
							'.$state.'
							<div>
								<div>
									<strong><a href="'.$url.'" target="_blank">'.$name.'</a></strong>
								</div>
								<div>
									'.$by.': <a href="'.$url.'" target="_blank">'.$author.'</a>
								</div>
							</div>
						</div>';
			}
		}
		
		$LNG = $LNGO;
		return array($output, $allowedLanguages);
	}
	
	function getInfoPages() {
		global $CONF, $LNG;
		
		$pages = $this->db->query("SELECT * FROM `info_pages` ORDER BY `id` ASC");

		$output = '';
		while($row = $pages->fetch_assoc()) {
			$row['content'] = skin::parse($row['content']);
			$output .= '<div class="manage-users-container">
							<div class="modal-btn list-button"><a href="'.$CONF['url'].'/index.php?a=admin&b=info_pages&id='.$row['id'].'" rel="loadpage">'.$LNG['edit'].'</a></div>
							<div>
								<div class="message-author">
									<strong><a href="'.permalink($CONF['url'].'/index.php?a=page&b='.$row['url']).'" target="_blank">'.skin::parse($row['title']).'</a></strong>
								</div>
								<div class="message-time">
									'.((strlen($row['content']) > 65) ? substr(strip_tags($row['content']), 0, 65).'...' : strip_tags($row['content'])).'
								</div>
							</div>
						</div>';
		}
		
		return $output;
	}
	
	function getNewsletters() {
		global $CONF, $LNG;
		
		$pages = $this->db->query("SELECT * FROM `newsletters` ORDER BY `id` DESC");

		$output = '';
		while($row = $pages->fetch_assoc()) {
			$time = $row['time']; $b = '';
			if($this->time == '0') {
				$time = date("c", strtotime($row['time']));
			} elseif($this->time == '2') {
				$time = $this->ago(strtotime($row['time']));
			} elseif($this->time == '3') {
				$date = strtotime($row['time']);
				$time = date('Y-m-d', $date);
				$b = '-standard';
			}
			
			$row['content'] = $row['content'];
			$output .= '<div class="manage-users-container">
				<div class="modal-btn list-button"><a href="'.$CONF['url'].'/index.php?a=admin&b=newsletter&token_id='.generateToken().'&id='.$row['id'].'">'.$LNG['delete'].'</a></div>
				<div>
					<div class="message-author">
						<strong>'.$row['title'].'</strong> <div class="timeago'.$b.'" title="'.$time.'">'.$time.'</div>
					</div>
					<div class="message-time">
						'.((strlen($row['content']) > 65) ? substr(strip_tags($row['content']), 0, 65).'...' : strip_tags($row['content'])).'
					</div>
				</div>
			</div>';
		}
		
		return $output;
	}
	
	function deleteNewsletter($id) {
		// Prepare the statement
		$stmt = $this->db->prepare("DELETE FROM `newsletters` WHERE `id` = ?");
		$stmt->bind_param('s', $id);
		$stmt->execute();
		$affected = $stmt->affected_rows;
		$stmt->close();
		return ($affected ? 1 : 0);
	}
	
	function createInfoPage($values, $type) {
		global $CONF;
		// Type 0: Create page
		// Type 1: Update page
		if($values['token_id'] != $_SESSION['token_id']) {
			return false;
		}
		
		// Type 1: Edit the page
		global $LNG;
		$values['page_title'] = substr(strip_tags($values['page_title']), 0, 64);
		$values['page_url'] = substr(htmlspecialchars(strip_tags($values['page_url'])), 0, 64);
		$values['page_public'] = ($values['page_public'] == 1 ? 1 : 0);
		
		// Verify URL
		$checkUrl = $this->db->query(sprintf("SELECT `id`, `url` FROM `info_pages` WHERE `url` = '%s'", $this->db->real_escape_string($values['page_url'])));
		
		$resultUrl = $checkUrl->fetch_assoc();
		
		if(empty($values['page_title']) || empty($values['page_url']) || empty($values['page_content'])) {
			$error = $LNG['all_fields'];
		}
		
		if($type) {
			// Check if the URL already exists on another page
			if($checkUrl->num_rows && $resultUrl['id'] != $_GET['id']) {
				$error = $LNG['url_exists'];
			}
		} else {
			if($checkUrl->num_rows) {
				$error = $LNG['url_exists'];
			}
		}
		
		if(isset($error)) {
			return notificationBox('error', $error);
		}
		
		if($type) {
			// Prepare the statement
			$stmt = $this->db->prepare("UPDATE `info_pages` SET `title` = ?, `url` = ?, `public` = ?, `content` = ? WHERE `id` = ?");		
			$stmt->bind_param('sssss', $values['page_title'], $values['page_url'], $values['page_public'], $values['page_content'], $_GET['id']);
			
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			$stmt->close();
			
			if($affected) {
				return notificationBox('success', $LNG['settings_saved']);
			} else {
				return notificationBox('info', $LNG['nothing_changed']);
			}
		} else {
			$this->db->query(sprintf("INSERT INTO `info_pages` (`title`, `url`, `public`, `content`) VALUES ('%s', '%s', '%s', '%s')",
			$this->db->real_escape_string($values['page_title']),
			$this->db->real_escape_string($values['page_url']),
			$this->db->real_escape_string($values['page_public']),
			$this->db->real_escape_string($values['page_content'])));
			
			header("Location: ".permalink($CONF['url'].'/index.php?a=page&b='.$values['page_url']));
		}
	}
	
	function deleteInfoPage($id) {
		// Prepare the statement
		$stmt = $this->db->prepare("DELETE FROM `info_pages` WHERE `id` = ?");
		$stmt->bind_param('s', $id);
		$stmt->execute();
		$affected = $stmt->affected_rows;
		$stmt->close();
		return ($affected ? 1 : 0);
	}
}

class updateUserSettings {
	public $db;		// Database Property
	public $url;	// Installation URL Property
	public $id;		// Logged in user id
	
	function validate_password($password) {
		$query = $this->db->query(sprintf("SELECT `password` FROM `users` WHERE `idu` = '%s'", $this->id));
		$result = $query->fetch_assoc();
		
		if(password_verify($password, $result['password'])) {
			return 1;
		}
		return 0;
	}
	
	function validate_inputs($data) {
		if(isset($data['password']) && !isset($data['current_password']) || isset($data['current_password']) && !$this->validate_password($data['current_password'])) {
			return array('wrong_current_password');
		}
		
		if(isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			return array('valid_email');
		}

		if(isset($data['country']) && !countries(0, $data['country'])) {
			return array('valid_country');
		}
		
		if(isset($data['website']) && ((!filter_var($data['website'], FILTER_VALIDATE_URL) && !empty($data['website'])) || (substr($data['website'], 0, 7) != 'http://' && substr($data['website'], 0, 8) != 'https://' && !empty($data['website'])))) {
			return array('valid_url');
		}
		
		if(isset($data['email']) && $this->verify_if_email_exists($this->id, $data['email'])) {
			return array('email_exists');
		}
		
		if(isset($data['description']) && strlen($data['description']) > 160) {
			return array('profile_description', 160);
		}
		
		if(isset($data['password']) && strlen($data['password']) < 6) {
			return array('password_too_short');
		}
		
		if(isset($data['password']) && $data['password'] !== $data['repeat_password']) {
			return array('password_not_match');
		}
	}

    function truncate_data($data) {
        // Select the columns
        $query = $this->db->query("SHOW COLUMNS FROM `users`");

        while($result = $query->fetch_assoc()) {
            $output = [];
            foreach($data as $key => $val) {
                // If the data matches the column and the column type is varchar
                if($result['Field'] == $key && substr($result['Type'], 0, 8) === 'varchar(') {
                    // Strip out any extra characters that exceed the maximum field length
                    $output[$key] = substr($val, 0, filter_var($result['Type'], FILTER_SANITIZE_NUMBER_INT));
                }
            }
        }

        return $output;
    }

	function query_array($table, $data) {
		global $LNG;
		// Verify if the user has a valid token
		if($data['token_id'] == $_SESSION['token_id']) {
			// Truncate any extra characters
			foreach($data as $key => $val) {
				if($key != 'password' && $key != 'current_password' && $key != 'repeat_password') {
					$data[$key] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
				}
			}
			
			$data = array_merge($data, $this->truncate_data($data));
			
			// Validate the inputs
			$validate = $this->validate_inputs($data);

			if($validate) {
                return notificationBox('error', sprintf($LNG["{$validate[0]}"], (isset($validate[1]) ? $validate[1] : null)));
			}
			
			// Unset unused values
			unset($data['repeat_password'], $data['current_password'], $data['token_id']);
			
			// Clean the description
			if(isset($data['description'])) {
				$data['description'] = trim(nl2clean($data['description']));
			}
			
			// Get the columns of the query-ed table
			$available = $this->getColumns($table);
			
			foreach ($data as $key => $value) {
				// Check if password array key exist and set a variable if so
				if($key == 'password') {
					$password = true;
				}
				
				// Check if all arrays introduced are available table fields
				if(!array_key_exists($key, $available)) {
					$x = 1;
					break;
				}
			}
			
			// If the password array key exists, encrypt the password
			if(isset($password)) {
				$data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
			}
			
			// If all array keys are valid database columns
            if(isset($x) == false) {
				foreach ($data as $column => $value) {
					$columns[] = sprintf("`%s` = '%s'", $column, $this->db->real_escape_string($value));
				}
				$column_list = implode(',', $columns);

				// Prepare the statement
				$stmt = $this->db->prepare("UPDATE `$table` SET $column_list WHERE `idu` = '{$this->id}'");		

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;
				
				// Close the statement
				$stmt->close();

				// If there was anything affected return 1
				if($affected) {
					return notificationBox('success', $LNG['settings_saved']);
				} else {
					return notificationBox('info', $LNG['nothing_changed']);
				}
			}
		} else {
			return notificationBox('info', $LNG['nothing_changed']);
		}
	}
	
	function getColumns($table) {
		
		$query = $this->db->query("SHOW columns FROM `$table` WHERE Field NOT IN ('idu', 'username', 'date', 'salted')");

		// Define an array to store the results
		$columns = array();
		
		// Fetch the results set
		while ($row = $query->fetch_array()) {
			// Store the result into array
			$columns[] = $row[0];
		}
		
		// Return the array;
		return array_flip($columns);
	}
	
	function deleteAvatar($image) {
		// Prepare the statement
		$stmt = $this->db->prepare("UPDATE `users` SET `image` = 'default.png' WHERE `idu` = '{$this->id}'");

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();
		
		// If the change was made, then unlink the old image
		if($affected) {
			unlink('uploads/avatars/'.$image);
		}

		// If there was anything affected return 1
		return ($affected) ? 1 : 0;
	}
	
	function verify_if_email_exists($id, $email) {
		$query = sprintf("SELECT `idu`, `email` FROM `users` WHERE `idu` != '%s' AND `email` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string(mb_strtolower($email)));
		$result = $this->db->query($query);
		
		return ($result->num_rows == 0) ? 0 : 1;
	}
	
	function getSettings() {
		$result = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($this->id)));
		
		return $result->fetch_assoc();
	}
	
	function getBlockedUsers() {
		global $LNG;
		$result = $this->db->query(sprintf("SELECT * FROM `blocked`, `users` WHERE `blocked`.`by` = '%s' AND `blocked`.`uid` = `users`.`idu` ORDER BY `id` DESC", $this->db->real_escape_string($this->id)));

		$output = '';
		while($row = $result->fetch_assoc()) {
			$output .= '
			<div class="manage-users-container" id="blocked'.$row['idu'].'">
				<div class="manage-users-image">
					<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'"></a>
				</div>
				<div class="manage-users-content"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">'.$row['username'].'</a><br>'.realName(null, $row['first_name'], $row['last_name']).''.((location($row['country'], $row['city']) && $row['private'] == 0) ? ' ('.location($row['country'], $row['city']).')' : '&nbsp;').'</div>
				<div class="manage-users-buttons">
					<div class="modal-btn list-button"><a onclick="doBlock('.$row['idu'].', 1)">'.$LNG['unblock'].'</a></div>
				</div>
			</div>';
		}
		return $output;
	}
}
class recover {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $username;	// The username to recover
	
	function checkUser() {
		// Query the database and check if the username exists
		if(filter_var($this->db->real_escape_string($this->username), FILTER_VALIDATE_EMAIL)) {
			$query = sprintf("SELECT `username`,`email` FROM `users` WHERE `email` = '%s'", $this->db->real_escape_string(mb_strtolower($this->username)));
		} else {
			$query = sprintf("SELECT `username`,`email` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string(mb_strtolower($this->username)));
		}

		$result = $this->db->query($query);
		
		// If a valid username is found
		if ($result->num_rows > 0) {
			// Fetch Associative values
			$assoc = $result->fetch_assoc();
			
			// Generate the salt for that username
			$generateSalt = $this->generateSalt($assoc['username']);
			
			// If the salt was generated
			if($generateSalt) {
			
				// Return the username, email and salted code
				return array($assoc['username'], $assoc['email'], $generateSalt);
			}
		}
	}
	
	function generateSalt($username) {
		// Generate the salted code
		$salt = generateSalt();
		
		// Prepare to update the database with the salted code
		$stmt = $this->db->prepare("UPDATE `users` SET `salted` = '{$this->db->real_escape_string($salt)}' WHERE `username` = '{$this->db->real_escape_string(mb_strtolower($username))}'");
		
		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the query
		$stmt->close();

		// If there was anything affected return 1
		if($affected) {
			return $salt;
		} else {
			return false;
		}
	}
	
	function changePassword($username, $password, $salt) {
		// Query the database and check if the username and the salted code exists
		$query = sprintf("SELECT `username` FROM `users` WHERE `username` = '%s' AND `salted` = '%s'", $this->db->real_escape_string(mb_strtolower($username)), $this->db->real_escape_string($salt));
		$result = $this->db->query($query);
		
		// If a valid match was found
		if ($result->num_rows > 0) {
			$password = password_hash($password, PASSWORD_DEFAULT);
			
			// Change the password
			$stmt = $this->db->prepare("UPDATE `users` SET `password` = '{$password}', `salted` = '' WHERE `username` = '{$this->db->real_escape_string(mb_strtolower($username))}'");
		
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the query
			$stmt->close();
			if($affected) {
				return true;
			} else {
				return false;
			}
		}
	}
}
class manageUsers {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $title;		// Installation WebSite Title
	public $per_page;	// Limit per page
	
	function getUsers($start) {
		global $LNG;
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `idu` < \''.$this->db->real_escape_string($start).'\'';
		}
		// Query the database and get the latest 20 users
		// If load more is true, switch the query for the live query

		$query = sprintf("SELECT * FROM `users` WHERE `suspended` != 2 %s ORDER BY `idu` DESC LIMIT %s", $start, $this->db->real_escape_string($this->per_page + 1));
		
		$result = $this->db->query($query);
		$rows = [];
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}

		$loadmore = $outout = '';
		if(array_key_exists($this->per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		$output = '';	// Define the rows variable
		
		foreach($rows as $row) {
			$output .= '
			<div class="manage-users-container" id="user'.$row['idu'].'">
				<div class="manage-users-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'" /></a></div>
				<div class="manage-users-content"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank">'.$row['username'].'</a><br />'.$row['email'].'</div>
				<div class="manage-users-buttons">
					<div class="modal-btn list-button"><a href="'.$this->url.'/index.php?a=admin&b=users&id='.$row['idu'].'" rel="loadpage">'.$LNG['edit'].'</a></div>
				</div>
			</div>';
			$last = $row['idu'];
		}
		if($loadmore) {
			$output .= '<div class="admin-load-more"><div id="more_users">
					<div class="load_more"><a onclick="manage_the('.$last.', 0)" id="infinite-load">'.$LNG['load_more'].'</a></div>
				</div></div>';
		}
		
		// Return the array set
		return $output;
	}
	
	function getUser($id, $profile = null) {
		if($profile) {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `image`, `country`, `city`, `website`, `description`, `facebook`, `twitter`, `gplus`, `youtube`, `vimeo`, `tumblr`, `soundcloud`, `myspace`, `lastfm`, `suspended`, `ip` FROM `users` WHERE `username` = '%s' AND `suspended` != 2", $this->db->real_escape_string($profile));
		} else {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `image`, `country`, `city`, `website`, `description`, `facebook`, `twitter`, `gplus`, `youtube`, `vimeo`, `tumblr`, `soundcloud`, `myspace`, `lastfm`, `suspended`, `ip` FROM `users` WHERE `idu` = '%s' AND `suspended` != 2", $this->db->real_escape_string($id));
		}
		$result = $this->db->query($query);

		// If the user exists
		if($result->num_rows > 0) {
			
			$row = $result->fetch_assoc();

			return $row;
		} else {
			return false;
		}
	}
	
	function suspendUser($id, $type) {
		// Type 0: Restore
		// Type 1: Suspend
		$user = $this->getUser($id);
		
		if($type && $user['suspended'] == 0) {
			$stmt = $this->db->prepare(sprintf("UPDATE `users` SET `suspended` = 1, `private` = 1 WHERE `idu` = '%s'", $this->db->real_escape_string($id)));
		} else {
			$stmt = $this->db->prepare(sprintf("UPDATE `users` SET `suspended` = 0, `private` = 1 WHERE `idu` = '%s'", $this->db->real_escape_string($id)));
		}
		$stmt->execute();
		
		$affected = $stmt->affected_rows;
		
		$stmt->close();
		
		if($affected) {
			if($type) {
				global $LNG;
				// Send suspended account email
				sendMail($user['email'], sprintf($LNG['ttl_suspended_account_mail']), sprintf($LNG['suspended_account_mail'], realName($user['username'], $user['first_name'], $user['last_name']), $this->url, $this->title), $this->email);
			}
		}
	}
	
	function deleteUser($id) {
		// Prepare the statement to delete the user from the database
		$stmt = $this->db->prepare("DELETE FROM `users` WHERE `idu` = '{$this->db->real_escape_string($id)}'");

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();
		
		// If the user was returned
		if($affected) {
			// Get the current pages created by the user
			$query = $this->db->query(sprintf("SELECT `id`,`name`,`art`,`as3_track` FROM `tracks` WHERE `uid` = '%s' ORDER BY `id` ASC", $this->db->real_escape_string($id)));
			
			$tracks = $arts = [];

			while($rows = $query->fetch_assoc()) {
				$arts[] = $rows['art'];
				$tracks[$rows['name']] = $rows['as3_track'];
			}

			$arts = implode(',', $arts);
			
			// Delete the art covers and tracks from the server
			deleteMedia($arts, $tracks, 1);
			
			$this->db->query(sprintf("UPDATE `tracks` SET `likes` = `likes`-1, `time` = `time` WHERE `id` IN (SELECT `track` FROM `likes` WHERE `by` = '%s' ORDER BY `track` ASC)", $this->db->real_escape_string($id)));
			$this->db->query("DELETE FROM `playlistentries` WHERE `track` IN (SELECT `id` FROM `tracks` WHERE `uid` = '{$this->db->real_escape_string($id)}')");
			$this->db->query("DELETE FROM `playlistentries` WHERE `playlist` IN (SELECT `id` FROM `playlists` WHERE `by` = '{$this->db->real_escape_string($id)}')");
			$this->db->query("DELETE FROM `tracks` WHERE `uid` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `comments` WHERE `uid` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `likes` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `views` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `reports` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `relations` WHERE `subscriber` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `relations` WHERE `leader` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `chat` WHERE `from` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `chat` WHERE `to` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `blocked` WHERE `uid` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `blocked` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `notifications` WHERE `to` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `notifications` WHERE `from` = '{$this->db->real_escape_string($id)}'");
			$this->db->query("DELETE FROM `playlists` WHERE `by` = '{$this->db->real_escape_string($id)}'");
			return 1;
		} else {
			return 0;
		}
	}

}
class manageCategories {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	
	function getCategories($type = null) {
		global $LNG;
		// Type 0: Return all categories
		// Type 1: Return the last category added
		if($type) {
			$query = sprintf("SELECT * FROM `categories` ORDER BY `id` DESC LIMIT 0, 1");
		} else {
			$query = sprintf("SELECT * FROM `categories` ORDER BY `name` ASC");
		}
		$result = $this->db->query($query);

		$rows = [];
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}

		$output = '';
		foreach($rows as $row) {
			$output .= '
			<div class="manage-users-container" id="category'.$row['id'].'">
				<div class="manage-list-name"><a href="'.permalink($this->url.'/index.php?a=explore&filter='.$row['name']).'" target="_blank">'.$row['name'].'</a></div>
				<div class="manage-users-buttons manage-list-buttons">
					<div class="modal-btn list-button"><a onclick="manage_categories('.$row['id'].', 0)" title="'.$LNG['delete'].'">'.$LNG['delete'].'</a></div>
				</div>
			</div>';
		}
		
		return $output;
	}
	
	function addCategory($value) {
		$value = preg_replace(array('/[^[:alnum:]-]/u', '/--+/'), array('', '-'), $value);
		
		$stmt = $this->db->prepare(sprintf("INSERT INTO `categories` (`name`) VALUES ('%s')", $this->db->real_escape_string($value)));

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;

		// Close the statement
		$stmt->close();
		
		// If category was added return the latest category
		if($affected) {
			return $this->getCategories(1);
		}
	}
	
	function deleteCategory($id) {
		$stmt = $this->db->prepare(sprintf("DELETE FROM `categories` WHERE `id` = '%s'", $this->db->real_escape_string($id)));

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;

		// Close the statement
		$stmt->close();
		
		// If category was deleted
		return ($affected) ? 1 : 0;
	}
}
class managePayments {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $title;		// Installation WebSite Title
	public $per_page;	// Limit per page
	
	function validatePayment($id) {
		// If the ID is the txn_id
		if(!ctype_digit($id)) {
			$field = 'txn_id';
		} else {
			$field = 'id';
		}

		// Select the report
		$query = $this->db->query(sprintf("SELECT * FROM `payments`, `users` WHERE `payments`.`by` = `users`.`idu` AND `payments`.`%s` = '%s'", $field, $this->db->real_escape_string($id)));

		// Fetch the result
		$row = $query->fetch_assoc();
		
		return $row;
	}
	
	function updatePayment($id, $type) {
		/*
		@function updatePayment
		Type 0: Suspended
		Type 1: Completed
		Type 2: Reversed
		Type 3: Refunded
		Type 4: Pending
		Type 5: Failed
		Type 6: Denied
		*/
		
		$row = $this->validatePayment($id);
		$types = array(0, 1, 2, 3, 4, 5, 6);
		
		if($row && in_array($type, $types)) {
			// Update the payment
			$stmt = $this->db->prepare("UPDATE `payments` SET `status` = ?, `time` = `time`, `valid` = `valid` WHERE `id` = ?");
			
			$stmt->bind_param("ii", $type, $row['id']);
			
			// Execute the statement
			$stmt->execute();
		
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the statement
			$stmt->close();
			
			// If the row has been affected
			return ($affected) ? 1 : 0;
		}
	}
	
	function getPayments($start, $id = null) {
		// ID: Set to retrieve the payments history from a specific user
		global $LNG;
		
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `id` < \''.$this->db->real_escape_string($start).'\'';
		}
		
		if($id) {
			$query = sprintf("SELECT * FROM `payments`,`users` WHERE `payments`.`by` = '%s' AND `payments`.`by` = `users`.`idu` ORDER BY `payments`.`id` DESC", $this->db->real_escape_string($id));
		} else {
			$query = sprintf("SELECT * FROM `payments`,`users` WHERE `payments`.`by` = `users`.`idu` %s ORDER BY `payments`.`id` DESC LIMIT %s", $start, $this->db->real_escape_string($this->per_page + 1));
		}
		
		$result = $this->db->query($query);

		$rows = [];
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}

        $output = $loadmore = '';	// Define the rows variable

        if(array_key_exists($this->per_page, $rows) && !$id) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}

		foreach($rows as $row) {
			if($row['type'] == 0) {
				$type = $LNG['monthly'];
			} else {
				$type = $LNG['yearly'];
			}
			// If the transaction is not completed, set a class to display the button in another color (red)
			if($row['status'] !== '1') {
				$class = ' modal-btn-active';
			} else {
				$class = '';
			}
			
			$date = explode('-', $row['time']);
			
			// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
			$month = intval($date[1]);
			
			$date = substr($LNG["month_$month"], 0, 3).' '.substr($date[2], 0, 2).', '.$date[0];
			
			$output .= '
			<div class="manage-users-container" id="payment'.$row['id'].'">
				<div class="manage-users-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'" /></a></div>
				<div class="manage-users-content"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank">'.$row['username'].'</a><br />'.$date.' - '.$row['amount'].' '.$row['currency'].'</div>
				<div class="manage-users-buttons">
					<div class="modal-btn'.$class.' list-button"><a href="'.$this->url.'/index.php?a=admin&b=payments&id='.$row['id'].'" rel="loadpage">'.$LNG['view'].'</a></div>
				</div>
			</div>';
			
			$last = $row['id'];
		}
		if($loadmore) {
			$output .= '<div class="admin-load-more"><div id="more_payments">
					<div class="load_more"><a onclick="manage_the('.$last.', 2)" id="infinite-load">'.$LNG['load_more'].'</a></div>
				</div></div>';
		}
		
		// Return the array set
		return $output;
	}
	
	function getPayment($id) {
		global $LNG;
		
		$row = $this->validatePayment($id);
		
		if($row) {			
			$content = '<div class="report-content"><img src="'.permalink($this->url.'/image.php?t=a&w=112&h=112&src='.$row['image']).'" height="15" width="15"> <span class="manage-report-author"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank">'.realName($row['username']).'</a></div>';
			
			$date = explode('-', $row['time']);
			// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
			$month = intval($date[1]);
			$date = substr($LNG["month_$month"], 0, 3).' '.substr($date[2], 0, 2).', '.$date[0];
			
			$valid = explode('-', $row['valid']);
			// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
			$month = intval($valid[1]);
			$valid = substr($LNG["month_$month"], 0, 3).' '.substr($valid[2], 0, 2).', '.$valid[0];
			
			$status = paymentStatus($row['status']);
			
			$output = ' <div class="page-inner">
							<div class="report-title">'.$LNG['transaction_details'].'</div>
							<div class="payment-content">
								<div class="payment-row"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'" height="15" width="15" /></a> <a href="'.$this->url.'/index.php?a=admin&b=users&id='.$row['idu'].'" target="_blank">'.realName($row['username']).'</a></div>
								<div class="payment-row">'.$LNG['status'].': <strong>'.$status.'</strong></div>
								<div class="payment-row">'.$LNG['ttl_last_name'].': <strong>'.$row['payer_last_name'].'</strong></div>
								<div class="payment-row">'.$LNG['ttl_first_name'].': <strong>'.$row['payer_first_name'].'</strong></div>
								<div class="payment-row">'.$LNG['ttl_email'].': <strong>'.$row['payer_email'].'</strong></div>
								<div class="payment-row">'.$LNG['ttl_country'].': <strong>'.$row['payer_country'].'</strong></div>
								<div class="payment-row">'.$LNG['transaction_id'].': <strong>#'.$row['txn_id'].'</strong></div>
								<div class="payment-row">'.$LNG['amount'].': <strong>'.$row['amount'].' '.$row['currency'].' </strong></div>
								<div class="payment-row">'.$LNG['date'].': <strong>'.$date.'</strong></div>
								<div class="payment-row">'.$LNG['plan'].': <strong>'.($row['type'] ? $LNG['yearly'].' '.$LNG['pro_plan'] : $LNG['monthly'].' '.$LNG['pro_plan']).'</strong></div>
								<div class="payment-row">'.$LNG['valid'].': <strong>'.$valid.'</strong></div>
							</div>
						</div>
						<div class="divider"></div>
						'.$this->paymentButtons($row);
			
			return $output;
		}
	}
	
	function paymentButtons($payment) {
		global $LNG;
		
		// If the report is not reviewed
		if($payment['status'] == 0) {
			$output = '<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=payments&id='.$payment['id'].'&type=1&token_id='.$_SESSION['token_id'].'">'.$LNG['enable'].'</a></div>';
		} else {
			$output = '<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=payments&id='.$payment['id'].'&type=0&token_id='.$_SESSION['token_id'].'">'.$LNG['suspend'].'</a></div>';
		}

		return '<div class="page-inner">'.$output.'</div>';
	}
}
class manageReports {
	public $db;			// Database Property
	public $url;		// Installation URL Property
	public $title;		// Installation WebSite Title
	public $per_page;	// Limit per page
	
	function getReports($start, $tracks = null) {
		// Tracks: Array of tracks id to retrieve reports for
		global $LNG;
		if($tracks) {
			// If tracks is set but the value is not array, return false
			if(!is_array($tracks)) {
				return false;
			}
			$query = sprintf("SELECT * FROM `reports`,`users` WHERE `reports`.`by` = `users`.`idu` AND `reports`.`track` IN (%s) AND `type` = 1 AND (`state` = 2 OR `state` = 3) ORDER BY `reports`.`id` DESC", $this->db->real_escape_string(implode(',', $tracks)));
		} else {
			// If the $start value is 0, empty the query;
			if($start == 0) {
				$start = '';
			} else {
				// Else, build up the query
				$start = 'AND `id` < \''.$this->db->real_escape_string($start).'\'';
			}
			$query = sprintf("SELECT * FROM `reports`,`users` WHERE `reports`.`by` = `users`.`idu` AND `state` = 0 %s ORDER BY `reports`.`id` DESC LIMIT %s", $start, $this->db->real_escape_string($this->per_page + 1));
		}
		$result = $this->db->query($query);

		$rows = [];
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}

        $loadmore = $output = '';
		if(array_key_exists($this->per_page, $rows) && !$tracks) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		foreach($rows as $row) {
			if($row['type'] == 0) {
				$type = $LNG['abusive_comment'];
			} else {
			    if($row['reason']) {
			        $type = $LNG['abusive_track'];
                } else {
                    $type = $LNG['copyright_infringement'];
                }
			}
			
			$output .= '
			<div class="manage-users-container" id="report'.$row['id'].'">
				<div class="manage-users-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'" /></a></div>
				<div class="manage-users-content"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank">'.$row['username'].'</a><br />'.$type.'</div>
				<div class="manage-users-buttons">
					<div class="modal-btn list-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$row['id'].'" rel="loadpage">'.$LNG['view'].'</a></div>
				</div>
			</div>';
			
			$last = $row['id'];
		}
		if($loadmore) {
			$output .= '<div class="admin-load-more"><div id="more_reports">
					<div class="load_more"><a onclick="manage_the('.$last.', 1)" id="infinite-load">'.$LNG['load_more'].'</a></div>
				</div></div>';
		}
		
		// Return the array set
		return $output;
	}
	
	function getReport($id) {
		global $LNG;
		
		// Select the report
		$query = $this->db->query(sprintf("SELECT * FROM `reports`, `users` WHERE `reports`.`by` = `users`.`idu` AND `reports`.`id` = '%s'", $this->db->real_escape_string($id)));
		
		// Fetch the result
		$row = $query->fetch_assoc();
		
		if($row) {
			// Output report comment
			if($row['type'] == 0) {
				$x = $LNG['reported_by'];
				$y = $LNG['author'];
				$title = $LNG['abusive_comment'];
				$query = $this->db->query(sprintf("SELECT * FROM `comments`,`users` WHERE `comments`.`id` = '%s' AND `users`.`idu` = `comments`.`uid`", $this->db->real_escape_string($row['track'])));
				$result = $query->fetch_assoc();
				
				$content = '<div class="report-content"><img src="'.permalink($this->url.'/image.php?t=a&w=112&h=112&src='.$result['image']).'" height="15" width="15"> <span class="manage-report-author"><a href="'.$this->url.'/index.php?a=admin&b=users&id='.$result['idu'].'" target="_blank">'.realName($result['username']).'</a></div>';
			} else {
			    if($row['reason']) {
                    $x = $LNG['reported_by'];
                    $y = $LNG['author'];
                    $title = $LNG['reason'];
                } else {
                    $x = $LNG['claimant'];
                    $y = $LNG['infringing_material'];
                    $title = $LNG['copyright_infringement'];
                }

				$query = $this->db->query(sprintf("SELECT * FROM `tracks`,`users` WHERE `tracks`.`id` = '%s' AND `users`.`idu` = `tracks`.`uid`", $this->db->real_escape_string($row['track'])));
				$result = $query->fetch_assoc();
				
				$content = '<div class="report-content"><img src="'.permalink($this->url.'/image.php?t=m&w=112&h=112&src='.$result['art']).'" height="15" width="15"> <a href="'.permalink($this->url.'/index.php?a=track&id='.$result['id'].'&name='.cleanUrl($result['title'])).'" target="_blank">'.$result['title'].'</a> '.mb_strtolower($LNG['by']).' <img src="'.$this->url.'/image.php?src='.$result['image'].'&t=a&w=50&h=50" height="15" width="15" /> <a href="'.$this->url.'/index.php?a=admin&b=users&id='.$result['idu'].'" target="_blank">'.realName($result['username']).'</a></div>';
			}
			
			$output = ' <div class="page-inner">
							<div class="report-title">'.$x.'</div>
							<div class="report-content"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'" height="15" width="15" /></a> <a href="'.$this->url.'/index.php?a=admin&b=users&id='.$row['idu'].'" target="_blank">'.realName($row['username']).'</a></div>
						</div>
						<div class="divider"></div>
						<div class="page-inner">
							<div class="report-title">'.$title.'</div>
							<div class="report-content">'.$row['content'].'</div>
						</div>
						<div class="divider"></div>
						'.(($result['username']) ? '<div class="page-inner">
							<div class="report-title">'.$y.'</div>
							'.$content.'
						</div>
						<div class="divider"></div>' : '').'
						'.$this->reportButtons($row);
			
			return $output;
		}
	}
	
	function reportButtons($report) {
		global $LNG;
		// If the report is not reviewed
		if($report['state'] == 0) {
			$output = '<div class="page-inner">';
			if($report['type'] == 0) {
				$output .= '<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=2&token_id='.$_SESSION['token_id'].'">'.$LNG['delete_comment'].'</a></div>
							<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=1&token_id='.$_SESSION['token_id'].'">'.$LNG['delete_report'].'</a></div>
							<div class="modal-btn page-button"><a href="'.permalink($this->url.'/index.php?a=track&id='.$report['parent'].'#comment'.$report['track']).'" target="_blank">'.$LNG['view_comment'].'</a></div>';
			} else {
				$output .= '<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=3&token_id='.$_SESSION['token_id'].'">'.$LNG['suspend_track'].'</a></div>
							<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=2&token_id='.$_SESSION['token_id'].'">'.$LNG['delete_track'].'</a></div>
							<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=1&token_id='.$_SESSION['token_id'].'">'.$LNG['delete_report'].'</a></div>';
			}
			$output .= '</div>';
			
		// If the report is safe
		} elseif($report['state'] == 1) {
			$output = notificationBox('info', $LNG['safe_report']);
			
		// If the reported material has been deleted
		} elseif($report['state'] == 2) {
			if($report['type'] == 0) {
				$output = notificationBox('error', $LNG['deleted_comment']);
			} else {
				$output = notificationBox('error', $LNG['deleted_track']);
			}
		} elseif($report['state'] == 3) {
			$output = notificationBox('error', $LNG['suspended_track']).'<div class="page-inner"><div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=4&token_id='.$_SESSION['token_id'].'">'.$LNG['restore_track'].'</a></div></div>';
		} elseif($report['state'] == 4) {
			$output = notificationBox('info', $LNG['restored_track']).'<div class="page-inner">
			<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=3&token_id='.$_SESSION['token_id'].'">'.$LNG['suspend_track'].'</a></div>
			<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=2&token_id='.$_SESSION['token_id'].'">'.$LNG['delete_track'].'</a></div>
			<div class="modal-btn page-button"><a href="'.$this->url.'/index.php?a=admin&b=reports&id='.$_GET['id'].'&type=1&token_id='.$_SESSION['token_id'].'">'.$LNG['delete_report'].'</a></div></div>';
		}
		
		return $output;
	}
	
	function manageReport($id, $type) {
		// Type 0: Delete comment
		// Type 1: Delete report
		// Type 2: Delete track
		// Type 3: Suspend track
		// Type 4: Restore track

		// Select the report
		$query = $this->db->query(sprintf("SELECT * FROM `reports`, `users` WHERE `reports`.`by` = `users`.`idu` AND `reports`.`id` = '%s'", $this->db->real_escape_string($id)));
		
		// Fetch the result
		$report = $query->fetch_assoc();

		// Store the track ID
		$track = $report['track'];
		
		if($type == 1) {
			// Make the report safe
			$stmt = $this->db->prepare("UPDATE `reports` SET `state` = '1' WHERE `track` = ? AND `type` = ? AND `id` = ?");

			$stmt->bind_param("iii", $report['track'], $report['type'], $id);
			
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the statement
			$stmt->close();
			
			// If the row has been affected
			return ($affected) ? 1 : 0;
		} else {
			// Prepare the statement to delete the message from the database
			if($report['type'] == 1) {
				// If the track has been suspended, send an email
				$query = $this->db->query(sprintf("SELECT * FROM `tracks`, `users` WHERE `tracks`.`id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($track)));
				$result = $query->fetch_assoc();
				
				if($type == 2) {
					deleteMedia($result['art'], array($result['name'] => $result['as3_track']), 1);
				
					$stmt = $this->db->prepare("DELETE FROM `tracks` WHERE `id` = '{$this->db->real_escape_string($track)}'");
				} elseif($type == 3) {
					$stmt = $this->db->prepare("UPDATE `tracks` SET `public` = '2', `time` = `time` WHERE `id` = '{$this->db->real_escape_string($track)}'");
				} elseif($type == 4) {
					$stmt = $this->db->prepare("UPDATE `tracks` SET `public` = '1', `time` = `time` WHERE `id` = '{$this->db->real_escape_string($track)}'");
				}
			} else {
				$stmt = $this->db->prepare("DELETE FROM `comments` WHERE `id` = '{$this->db->real_escape_string($track)}'");
			}
			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the statement
			$stmt->close();
			
			if($affected) {
				if($type == 3) {
					// Suspend the track for the selected report, and dimiss the rest of the reports
					$this->db->query(sprintf("UPDATE `reports` SET `state` = '1' WHERE `track` = '%s' AND `type` = '%s' AND `id` != '%s'", $this->db->real_escape_string($track), (($report['type']) ? 1 : 0), $id));
					$this->db->query(sprintf("UPDATE `reports` SET `state` = '3' WHERE `track` = '%s' AND `type` = '%s' AND `id` = '%s'", $this->db->real_escape_string($track), (($report['type']) ? 1 : 0), $id));
				} elseif($type == 4) {
					// Restore the track
					$this->db->query(sprintf("UPDATE `reports` SET `state` = '4' WHERE `track` = '%s' AND `type` = '%s'", $this->db->real_escape_string($track), (($report['type']) ? 1 : 0)));
				} else {
					$this->db->query(sprintf("UPDATE `reports` SET `state` = '2' WHERE `track` = '%s' AND `type` = '%s'", $this->db->real_escape_string($track), (($report['type']) ? 1 : 0)));
				}
				
				if($report['type'] == 1) {
					global $LNG;

					if($report['reason'] == 0) {
                        // Send mail to the copyright claimer
                        sendMail($report['email'], sprintf($LNG['ttl_copyright_notification'], $result['title']), sprintf($LNG['copyright_mail_1'], realName($report['username'], $report['first_name'], $report['last_name']), permalink($this->url.'/index.php?a=track&id='.$result['id'].'&name='.$result['title']), $result['title'], $id, $this->url, $this->title), $this->email);

                        // Send mail to the abuser
                        sendMail($result['email'], sprintf($LNG['ttl_copyright_notification'], $result['title']), sprintf($LNG['copyright_mail_0'], realName($result['username'], $result['first_name'], $result['last_name']), permalink($this->url.'/index.php?a=track&id='.$result['id'].'&name='.$result['title']), $result['title'], permalink($this->url.'/index.php?a=profile&u='.$report['username']), realName($report['username'], $report['first_name'], $report['last_name']), $id, $this->url, $this->title), $this->email);
                    }
					
					// If the track is suspended or restored
					if($type == 3 || $type == 4) {
						return 1;
					}
					
					$this->db->query("DELETE FROM `comments` WHERE `tid` = '{$this->db->real_escape_string($track)}'");
					$this->db->query("DELETE FROM `likes` WHERE `track` = '{$this->db->real_escape_string($track)}'");
					$this->db->query("DELETE FROM `notifications` WHERE `parent` = '{$this->db->real_escape_string($track)}'");
					$this->db->query("DELETE FROM `playlistentries` WHERE `track` = '{$this->db->real_escape_string($track)}'");
				} else {
					$this->db->query("DELETE FROM `notifications` WHERE `child` = '{$this->db->real_escape_string($track)}' AND `type` = '1'");
				}
				
				return 1;
			}
		}
	}
}

class feed {
	public $db;					// Database Property
	public $url;				// Installation URL Property
	public $title;				// Installation WebSite Title
	public $email;				// Installation Default E-mail
	public $id;					// The ID of the user
	public $username;			// The username
	public $user_email;			// The email of the current username
	public $per_page;			// The per_page limit for feed
	public $c_start;			// The row where to start the nex
	public $c_per_page;			// Comments per_page limit
	public $s_per_page;			// Subscribers per page (dedicated profile page)
	public $m_per_page;			// Conversation Messages (Chat) per page
	public $time;				// The time option from the admin panel
	public $art_size;			// Image size allowed for upload (art cover)
	public $art_format;			// Image formats allowed for upload (art cover)
	public $track_size;			// Track size allowed for upload 
	public $track_format;		// Track formats allowed for upload
	public $track_size_total;  	// Total track size allowed for upload
	public $subscriptions;		// The public variable to be accessed outside of the class to pass variable to sidebar functions
	public $message_length;		// The maximum message length allowed for messages/comments
	public $max_images;			// The maxium images allowed to be uploaded per message
	public $is_admin;			// The option for is_admin to show the post no matter what
	public $profile;			// The current viewed user profile
	public $profile_id;			// The profile id of the current viewed user profile
	public $profile_data;		// The public variable which holds all the data for queried user
	public $subscriptionsList;	// The subscriptions users list Array([value],[count])
	public $subscribersList;	// The subscribers users list Array([value],[count])
	public $subsList;			// The subs list for dedicated subs page
	public $trackList;			// A list of tracks separated by "," (comma)
	public $l_per_post;			// Likes per post (small thumbs)
	public $online_time;		// The amount of time an user is being kept as online
	public $friends_online;		// The amount of online friends to be displayed on the Feed/Subscriptions page
	public $chat_length;		// The maximum chat length allowed for conversations
	public $email_comment;		// The admin settings for allowing e-mails on comments to be sent
	public $email_like;			// The admin settings for allowing e-mails on likes to be sent
	public $email_new_friend;	// The admin settings for allowing e-mails on new friendship to be sent
	public $categories;			// The category list

	function getTracks($query, $type, $typeVal) {
		// QUERY: Holds the query string
		// TYPE: [exploreTracks, loadProfile]
		// TYPEVAL: Values for the JS functions
		// EXTRA: Is defined when two ID values are set, and need the extra one as the latest ID
		global $LNG, $settings, $s3;
		
		// Run the query
		$result = $this->db->query($query);
		
		// Set the result into an array
		$rows = array();
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// If the Stream is empty, display a welcome message
		if(empty($rows) && $type == 'exploreTracks') {
			return $this->showError('no_results', 1);
		} elseif(empty($rows) && $type == 'loadHistory') {
            return $this->showError('no_history', 1);
        } elseif(empty($rows) && $type == 'searchTracks') {
			return $this->showError('no_results', 1);
		}
		
		// Define the $loadmore variable
		$loadmore = '';
		
		// If there are more results available than the limit, then show the Load More Comments
		if(array_key_exists($this->per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		// Define the $messages variable
        $comment = $track = $profile = $sound = '';
		
		// If it's set profile, then set $profile
		if($this->profile) {
			$profile = ', \''.$this->profile.'\'';
		}
		
		// Start outputting the content
		$i = 1;
		foreach($rows as $row) {
			$time = $row['time']; $b = '';
			if($this->time == '0') {
				$time = date("c", strtotime($row['time']));
			} elseif($this->time == '2') {
				$time = $this->ago(strtotime($row['time']));
			} elseif($this->time == '3') {
				$date = strtotime($row['time']);
				$time = date('Y-m-d', $date);
				$b = '-standard';
			}
			
			// Define the style variable (reset the last value)
			$style = $row['as3_url'] = $url = '';
			
			// If the track is private
			if($this->username !== $row['username'] && $row['public'] == 0) { 
				$hide = 1;
			} else {
				$hide = 0;
			}
			
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
			
			// If the user is a visitor
			if(empty($this->username)) {
				$style = ' style="display: none;"';
			}
			
			if($hide == 1 && !$this->is_admin) {
				$error = $this->showError('track_hidden_1');
				$sound .= $error[0];
			} elseif($hide == 2) {
				$error = $this->showError('track_suspended_1');
				$sound .= $error[0];
			} else {
				$tag = $this->fetchCategory($row['tag']);
				if($type == 'trackPage') {
					$comment = (($this->id) ? '<div class="message-comment-box-container" id="comment_box_'.$row['id'].'"'.$style.'>
								<div class="message-reply-avatar">
									<a href="'.permalink($this->url.'/index.php?a=profile&u='.$this->user['username']).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$this->user['image']).'" /></a>
								</div>
								<div class="message-comment-box-form">
									<textarea id="comment-form'.$row['id'].'" onclick="showButton('.$row['id'].')" placeholder="'.$LNG['leave_comment'].'" class="comment-reply-textarea"></textarea>
								</div>
								<div class="comment-btn" id="comment_btn_'.$row['id'].'">
									<a onclick="postComment('.$row['id'].')">'.$LNG['post'].'</a>
								</div>
								<div class="delete_preloader" id="post_comment_'.$row['id'].'"></div>
							</div>' : '').'
							<div class="comments-container" id="comments-list'.$row['id'].'">
								'.$this->getComments($row['id'], null, $this->c_start).'
							</div>';
				}
				$track .= '<div id="track'.$row['id'].'" class="song-container'.(($type == 'trackPage') ? ' song-container-page' : '').((isset($row['top'])) ? ' top-track' : '').'">
						'.((isset($row['top'])) ? '<div class="top-number">'.$i.'</div>' : '').'
						<div class="song-art"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=m&w=112&h=112&src='.$row['art']).'" id="song-art'.$row['id'].'" alt="'.$row['title'].'"></a></div>
						<div class="song-top">
							<div class="song-timeago">
                                <a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" rel="loadpage">
                                    <span id="time'.$row['id'].'">
										<div class="timeago'.$b.'" title="'.$time.'">
											'.$time.'
										</div>
									</span>
								</a>
							</div>
							<div data-track-name="'.$row['name'].'" data-track-id="'.$row['id'].'" id="play'.$row['id'].'" data-track-url="'.($row['as3_url'] ? $row['as3_url'] : $this->url.'/uploads/tracks/'.$row['name']).'" data-track-format="'.strtolower(pathinfo($row['name'], PATHINFO_EXTENSION)).'" class="track song-play-btn">
							</div>
							<div class="song-titles">
								<div class="song-author"><a onmouseover="profileCard('.$row['idu'].', '.$row['id'].', 0, 0);" onmouseout="profileCard(0, 0, 0, 1);" onclick="profileCard(0, 0, 1, 1);" href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage" id="song-author'.$row['id'].'">'.realName($row['username'], $row['first_name'], $row['last_name']).'</a></div>
								<div class="song-tag">
									<a href="'.permalink($this->url.'/index.php?a=explore&filter='.$tag).'" rel="loadpage">'.$tag.'</a>
								</div>
								<div class="song-title">
									<a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" id="song-url'.$row['id'].'" rel="loadpage"><div id="song-name'.$row['id'].'">'.$row['title'].'</div></a>
								</div>
							</div>
						</div>
						<div class="player-controls">
							<div id="song-controls'.$row['id'].'">
								<div id="jp_container_123" class="jp-audio">
									<div class="jp-type-single">
											<div class="jp-gui jp-interface">
												<div class="jp-progress">
													<div class="jp-seek-bar">
													<div class="jp-play-bar"></div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="track-actions-container">
							<div class="track-actions"><div class="track-actions-content" id="track-action'.$row['id'].'">'.$this->getActions($row['id'], null, ($row['as3_url'] ? $row['as3_url'] : $this->url.'/uploads/tracks/'.$row['name'])).'</div></div>
						</div>
						'.$comment.'
					</div>';
				$start = (isset($row['extra_id']) ? $row['extra_id'] : $row['id']);
			}
			$i++;
		}
		// If the $loadmore button is set, then show the Load More Messages button
		if($loadmore) {
			$track .= '
						<div id="load-more">
							<div class="load_more"><a onclick="'.$type.'('.$start.', '.$typeVal.''.$profile.')" id="infinite-load">'.$LNG['load_more'].'</a></div>
						</div>';
		}
		return array($track, 0);
	}
	
	function explore($start, $value) {
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `tracks`.`id` < \''.$this->db->real_escape_string($start).'\'';
		}
		
		// Query for the Popular Music filter
		if(isset($_GET['popular'])) {
			$this->per_page = 50;
			$query = sprintf("SELECT *, 1 as `top` FROM `tracks` as t1 LEFT JOIN `users` as t2 ON t1.uid  = t2.idu LEFT JOIN (SELECT `views`.`track`, COUNT(`track`) as `count` FROM `views`,`tracks` WHERE `views`.`track` = `tracks`.`id` AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) <= date(`views`.`time`) AND `tracks`.`public` = '1' GROUP BY `track` ORDER BY `count` DESC LIMIT %s) as t3 ON t3.track = t1.id ORDER BY t3.count DESC LIMIT %s", $this->per_page, $this->per_page);
			$value = '\''.$value.'\'';
		} elseif(isset($_GET['liked'])) {
			$this->per_page = 50;
			$query = sprintf("SELECT *, 1 as `top` FROM `tracks` as t1 LEFT JOIN `users` as t2 ON t1.uid  = t2.idu LEFT JOIN (SELECT `likes`.`track`, COUNT(`track`) as `count` FROM `likes`,`tracks` WHERE `likes`.`track` = `tracks`.`id` AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) <= date(`likes`.`time`) AND `tracks`.`public` = '1' GROUP BY `track` ORDER BY `count` DESC LIMIT %s) as t3 ON t3.track = t1.id ORDER BY t3.count DESC LIMIT %s", $this->per_page, $this->per_page);
			$value = '\''.$value.'\'';
		} elseif(!empty($value)) {
			$query = sprintf("SELECT * FROM `tracks`, `users` WHERE `tracks`.`tag` REGEXP '[[:<:]]%s[[:>:]]' AND `tracks`.`uid` = `users`.`idu` %s AND `tracks`.`public` = '1' AND `users`.`private` = 0 ORDER BY `tracks`.`id` DESC LIMIT %s", $this->db->real_escape_string($value), $start, ($this->per_page + 1));
			$value = '\''.$value.'\'';
		} else {
			$query = sprintf("SELECT * FROM `tracks`, `users` WHERE `tracks`.`uid` = `users`.`idu` %s AND `tracks`.`public` = 1 AND `users`.`private` = 0 ORDER BY `tracks`.`id` DESC LIMIT %s", $start, ($this->per_page + 1));
			$value = '\'\'';
		}
		
		return $this->getTracks($query, 'exploreTracks', $value);
	}
	
	function stream($start, $value) {
		$this->subscriptions = $this->getSubscriptionsList();

		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND tracks.id < \''.$this->db->real_escape_string($start).'\'';
		}
		
		if(!empty($this->subscriptions)) {
			$uid = $this->id.','.$this->subscriptions;
		} else {
			$uid = $this->id;
		}

		// The query to select the subscribed users
		$query = sprintf("SELECT * FROM tracks, users WHERE tracks.uid IN (%s) AND tracks.public = '1' AND tracks.uid = users.idu %s ORDER BY tracks.id DESC LIMIT %s", $uid, $start, ($this->per_page + 1));
		$value = '\'\'';
		
		// If the user subscribed to other users get the tracks (prevents fatal error because of empty IN () query)
		if(!empty($this->subscriptions)) {
			return $this->getTracks($query, 'loadStream', $value);
		} else {
			return $this->showError('welcome_stream');
		}
	}
	
	function getProfile($start, $value) {
		$profile = $this->profile_data;
		$this->profile_id = $profile['idu'];

		// If the username exist
		if(!empty($profile['idu'])) {
			$relationship = $this->verifyRelationship($this->id, $this->profile_id, 0);
			
			// Check privacy
			switch($profile['private']) {
				case 0:
					break;
				case 1:
					// Check if the username is not same with the profile
					if($this->profile !== $this->username) {
						if($profile['suspended']) {
							return $this->showError('profile_suspended');
						}
						return $this->showError('profile_private');
					}
					break;
				case 2:
					// Check relationship
					if(!$relationship) {
						return $this->showError('profile_semi_private');
					}
					break;
			}
			
			$allowedDates = $this->listDates('profile');
			
			// If the $start value is 0, empty the query;
			if($start == 0) {
				$start = '';
			} else {
				// Else, build up the query
				$start = 'AND tracks.id < \''.$this->db->real_escape_string($start).'\'';
			}
			
			// Decide if the query will include only public sounds or not
			$public = ($this->username == $this->profile) ? 'AND `tracks`.`public` != 2' : 'AND `tracks`.`public` = 1';
			if(in_array($value, $allowedDates)) {
				$query = sprintf("SELECT * FROM `tracks`, users WHERE tracks.uid = '%s' AND extract(YEAR_MONTH from `time`) = '%s' AND tracks.uid = users.idu %s %s ORDER BY tracks.id DESC LIMIT %s", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($value), $public, $start, ($this->per_page + 1));
				$value = '\''.$value.'\'';
			} else {
				$query = sprintf("SELECT * FROM `tracks`, users WHERE tracks.uid = '%s' AND tracks.uid = users.idu %s %s ORDER BY tracks.id DESC LIMIT %s", $this->db->real_escape_string($profile['idu']), $public, $start, ($this->per_page + 1));
				$value = '\'\'';
			}
			return $this->getTracks($query, 'loadProfile', $value);
		} else {
			return $this->showError('profile_not_exist');
		}
	}

    function history($start, $value) {
        // If the $start value is 0, empty the query;
        if($start == 0) {
            $start = '';
        } else {
            // Else, build up the query
            $start = 'AND `views`.`id` < \''.$this->db->real_escape_string($start).'\'';
        }

        // The query to select the subscribed users
        $query = sprintf("
        SELECT `b`.`id` AS `extra_id`, `b`.`time` AS `time`, `tracks`.`id` AS `id`, `tracks`.`title` AS `title`, `tracks`.`name` AS `name`, `tracks`.`art` AS `art`, `tracks`.`as3_track`, `tracks`.`public` AS `public`, `tracks`.`tag` AS `tag`, `users`.`idu` AS `idu`, `users`.`username` AS `username`, `users`.`first_name` AS `first_name`, `users`.`last_name` AS `last_name`, `users`.`image` AS `image` FROM ( SELECT `views`.`track`, MAX(`views`.`time`) AS `max_time` FROM `views` WHERE `views`.`by` = '%s' AND `views`.`cleared` = 0 GROUP BY `views`.`track` ORDER BY `views`.`time` DESC) a INNER JOIN `views` b ON b.track = a.track AND b.time = a.max_time LEFT JOIN `tracks` ON `b`.`track` = `tracks`.`id` LEFT JOIN `users` ON `tracks`.`uid` = `users`.`idu` LIMIT 0, 1000", $this->id, $start, ($this->per_page + 1));
        $value = '\'\'';

        // If the user subscribed to other users get the tracks (prevents fatal error because of empty IN () query)
        return $this->getTracks($query, 'loadHistory', $value);
    }
	
	function getSubscriptionsList() {
		// The query to select the subscribed users
		$query = sprintf("SELECT `leader` FROM `relations` WHERE `subscriber` = '%s'", $this->db->real_escape_string($this->id));
		
		// Run the query
		$result = $this->db->query($query);
		
		// The array to store the subscribed users
		$subscriptions = array();
		while($row = $result->fetch_assoc()) {
			$subscriptions[] = $row['leader'];
		}
		
		// Close the query
		$result->close();
		
		// Return the subscriptions list (e.g: 13,22,19)
		return implode(',', $subscriptions);
	}
	
	public function profileData($username = null, $id = null) {
		// The query to select the profile
		// If the $id is set (used in Subscribe function for profiels) then search for the ID
		if($id) {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `country`, `city`, `website`, `description`, `date`, `facebook`, `twitter`, `gplus`, `youtube`, `vimeo`, `tumblr`, `soundcloud`, `myspace`, `lastfm`, `image`, `private`, `suspended`, `cover`, `gender`, `email_new_friend` FROM `users` WHERE `idu` = '%s'", $this->db->real_escape_string($id));
		} else {
			$query = sprintf("SELECT `idu`, `username`, `email`, `first_name`, `last_name`, `country`, `city`, `website`, `description`, `date`, `facebook`, `twitter`, `gplus`, `youtube`, `vimeo`, `tumblr`, `soundcloud`, `myspace`, `lastfm`, `image`, `private`, `suspended`, `cover`, `gender`, `email_new_friend` FROM `users` WHERE `username` = '%s'", $this->db->real_escape_string($username));
		}
		
		// Run the query
		$result = $this->db->query($query);
		
		return $result->fetch_assoc();
	}
	
	function fetchProfile($profile) {
		global $LNG, $CONF;
		$coverImage = ((!empty($profile['cover'])) ? $profile['cover'] : 'default.png');
		$coverAvatar = ((!empty($profile['image'])) ? $profile['image'] : 'default.png');
		$profileButtons = ((!empty($profile['idu'])) ? '<div id="subscribe'.$profile['idu'].'">'.$this->getSubscribe(null, null, null).'</div>'.$this->chatButton($profile['idu'], $profile['username'], 1) : '');
		$cover = '<div class="twelve columns">
					<div class="cover-container">
						<div class="cover-content">
							<div class="cover-image" style="background-position: center; background-image: url('.permalink($this->url.'/image.php?t=c&w=1100&h=200&src='.((!empty($profile['cover'])) ? $profile['cover'] : 'default.png')).')">
							</div>
							<div class="cover-description">
								<div class="cover-avatar-content">
									<div class="cover-avatar">
										<span id="avatar'.$profile['idu'].$profile['username'].'"><img src="'.permalink($this->url.'/image.php?t=a&w=112&h=112&src='.$coverAvatar).'" /></span>
									</div>
								</div>
								<div class="cover-description-content">
									<span id="author'.$profile['idu'].$profile['username'].'"></span><span id="time'.$profile['idu'].$profile['username'].'"></span><div class="cover-text-container">
									<div class="cover-username"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$profile['username']).'" rel="loadpage">'.realName($profile['username'], $profile['first_name'], $profile['last_name']).'</a>'.(($this->getProStatus($profile['idu'])) ? '<a href="'.permalink($this->url.'/index.php?a=pro').'" rel="loadpage" title="'.$LNG['pro_user'].'"><span class="pro-icon pro-normal"></span></a>' : '').'</div>
									'.((location($profile['country'], $profile['city'])) ? '<div class="cover-location">'.location($profile['country'], $profile['city']).'</div>' : '').'</div>
								</div>
								<div class="cover-description-buttons">'.$profileButtons.'</div>
								<div class="cover-buttons">
									'.$this->coverButtons().'
								</div>
							</div>
						</div>
					</div>
				</div>';
		return $cover;
	}
	
	function countSongs($id = null) {
		// If the logged in username is the same as the profile, count the private songs as well, otherwise only public ones
		$public = ($this->username == $this->profile) ? 'AND `tracks`.`public` != 2' : 'AND `tracks`.`public` = 1';
		$query = $this->db->query(sprintf("SELECT count(`uid`) FROM `tracks` WHERE `tracks`.`uid` = '%s' %s", (($id) ? $id : $this->db->real_escape_string($this->profile_id)), $public));

		$result = $query->fetch_row();
		return $result[0];
	}
	
	function getPlaylistTracks($id) {
		// Get the tracks for Playlist page
		$query = sprintf("SELECT * FROM `playlistentries`,`users`,`tracks` WHERE (`playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`public` = 1) OR (`playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`uid` = '%s') ORDER BY `playlistentries`.`id` DESC", $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->id);

		return $this->getTracks($query, 'loadPlaylist', null);
	}
	
	function getPlaylists($start = null, $type = null, $query = null) {
		global $LNG;
		// Type 0: Return the number of playlists from user
		// Type 1: Return the playlists for profiles
		// Type 2: Return the playlists for search
		// Type 3: Return the playlist for playlist page
		
		if($type) {
			if($start == 0) {
				$start = '';
			} else {
				$start = 'AND `playlists`.`id` < \''.$this->db->real_escape_string($start).'\'';
			}
			
			if($type == 1) {
				$public = ($this->username == $this->profile) ? '' : 'AND `playlists`.`public` = 1';
				$q = sprintf("SELECT * FROM `playlists`,`users` WHERE `playlists`.`by` = '%s' AND `users`.`idu` = `playlists`.`by` %s %s ORDER BY `id` DESC LIMIT %s", $this->profile_data['idu'], $public, $start, ($this->per_page + 1));
			} elseif($type == 2) {
				$q = sprintf("SELECT * FROM `playlists`,`users` WHERE `playlists`.`name` LIKE '%s' AND `users`.`idu` = `playlists`.`by` AND `playlists`.`public` = 1 %s ORDER BY `id` DESC LIMIT %s", '%'.$this->db->real_escape_string($query).'%', $start, ($this->per_page + 1));
			} elseif($type == 3) {
				$public = ($this->username == $this->profile) ? '' : 'AND `playlists`.`public` = 1';
				$q = sprintf("SELECT * FROM `playlists`,`users` WHERE `playlists`.`id` = '%s' AND `users`.`idu` = `playlists`.`by`", $this->db->real_escape_string($query));
			}

			$getPlaylists = $this->db->query($q);
			
			// Declare the rows array
			$rows = array();
			while($row = $getPlaylists->fetch_assoc()) {
				// Store the result into the array
				$rows[] = $row;
			}

			$loadmore = $output = '';
			
			if($type == 3) {
				// If the playlist doesn't exist
				if(!$rows) {
					return;
				}
				// If the playlist is private, and the logged-in user is not the author of the playlist
				elseif($rows[0]['public'] == 0 && $rows[0]['by'] !== $this->id) {
					return $this->showError('playlist_hidden');
				}
			} else {
				// Decide whether the load more will be shown or not
				if(array_key_exists($this->per_page, $rows)) {
					$loadmore = 1;
						
					// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
					array_pop($rows);
				}
			}

			// Start the output
			foreach($rows as $value) {
				$time = $value['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($value['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($value['time']));
				} elseif($this->time == '3') {
					$date = strtotime($value['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				$output .= '<div class="list-container" id="playlist'.$value['id'].'"><div class="list-inner"><div class="playlist-content">
					
					<div class="playlist-artwork"><a href="'.permalink($this->url.'/index.php?a=playlist&id='.$value['id'].'&name='.cleanUrl($value['name'])).'" rel="loadpage">'.$this->playlistArt($value['id'], 5).'</a></div>
					<div class="playlist-content-info">
						<div class="song-timeago"><span id="time'.$value['id'].'"><a href="'.permalink($this->url.'/index.php?a=playlist&id='.$value['id'].'&name='.cleanUrl($value['name'])).'" rel="loadpage">
							<div class="timeago'.$b.'" title="'.$time.'">
								'.$time.'
							</div>
						</span></div>
						<a href="'.permalink($this->url.'/index.php?a=playlist&id='.$value['id'].'&name='.cleanUrl($value['name'])).'" id="playlist-url'.$value['id'].'" rel="loadpage"><div class="playlist-title" id="playlist-name'.$value['id'].'">'.$value['name'].'</div></a>
						<div class="playlist-author">
							<a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'" rel="loadpage" onmouseover="profileCard('.$value['idu'].', '.$value['id'].', 2, 0);" onmouseout="profileCard(0, 0, 0, 1);" onclick="profileCard(0, 0, 1, 1);">'.realName($value['username'], $value['first_name'], $value['last_name']).'</a>
						</div>
					</div>
					'.$this->getPlaylistActions($value['id']).'
					</div></div></div>';
			}
			
			if($loadmore) {
				$output .= '<div id="load-more">
								<div class="load_more"><a onclick="loadPlaylists('.$value['id'].', '.($type == 2 ? 2 : 1).', \''.($type == 2 ? $query : $value['username']).'\')" id="infinite-load">'.$LNG['load_more'].'</a></div>
							</div>';
			}
			
			// If the query is for the playlist page, return array
			if($type == 3) {
				$tracks = $this->getPlaylistTracks($query);
				return array($output.$tracks[0], 0);
			}
			return $output;
		} else {
			$public = ($this->username == $this->profile) ? '' : 'AND `playlists`.`public` = 1';
			$query = $this->db->query(sprintf("SELECT count(`by`) FROM `playlists` WHERE `playlists`.`by` = '%s' %s", $this->db->real_escape_string($this->profile_id), $public));

			$result = $query->fetch_row();
			return $result[0];
		}
	}
	
	function getPlaylistActions($id, $type = null) {
		global $LNG;
		
		// Get the likes, views, and other info
		$query = sprintf("SELECT * FROM `playlists` WHERE `id` = '%s'", $this->db->real_escape_string($id));
		
		// Run the query
		$result = $this->db->query($query);
		
		// Get the array element for the like
		$get = $result->fetch_assoc();
		
		$count = $this->db->query(sprintf("SELECT COUNT(*) FROM `playlistentries`,`tracks` WHERE (`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`public` = 1) OR (`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`uid` = '%s' AND `tracks`.`public` != 2)", $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->id));
		$tracks = $count->fetch_row(); $count->close();
								
		// Determine whether to show the delete/privacy buttons or not
		if($this->id == $get['by']) { // If it's current username is the same with the current author
			if($get['public'] == 1) {
				$privacy = '<div class="public-button" onclick="privacy('.$get['id'].', 0, 1)" title="'.$LNG['this_playlist_public'].'"></div>';
				$delete = '<div id="delete-button-'.$get['id'].'" class="delete-button" onclick="delete_modal('.$get['id'].', 3)" title="'.$LNG['delete'].'"></div>';
			} else {
				$privacy = '<div class="private-button" onclick="privacy('.$get['id'].', 1, 1)" title="'.$LNG['this_playlist_private'].'"></div>';
				$delete = '<div id="delete-button-'.$get['id'].'" class="delete-button" onclick="delete_modal('.$get['id'].', 3)" title="'.$LNG['delete'].'"></div>';
			}
		} else { // If the current username is not the same as the author
			$privacy = '';
			$delete = '';
		}

		$shuffle = '';
		if(isset($this->shuffle)) {
			$shuffle = '<div class="shuffle-button" onclick="shuffle()" title="'.$LNG['shuffle'].'"></div>';
		}
		
		$output = '<div class="playlist-actions-container">
						<div class="playlist-actions">
							<div class="playlist-actions-content"><div class="share-button" onclick="share('.$get['id'].', 2)" title="'.$LNG['share'].'"><span class="action-text">'.$LNG['share'].'</span></div>'.$delete.'<span id="privacy-pl'.$get['id'].'">'.$privacy.'</span>'.$shuffle.'</div>
							<div class="playlist-stats">
								<div class="tracks-small-icon" style="float: right;">'.$tracks[0].' <span class="playlist-stats-text">'.$LNG['tracks'].'</span></div>
							</div>
						</div>
					</div>';
		return $output;
	}
	
	function playlistArt($id, $limit) {
		$query = $this->db->query(sprintf("SELECT `tracks`.`art` FROM `playlistentries`,`tracks` WHERE (`playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`public` = 1) OR (`playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`uid` = '%s' AND `tracks`.`public` != 2) ORDER BY `playlistentries`.`id` DESC LIMIT %s", $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->id, $this->db->real_escape_string($limit)));

		$rows = [];
		while($result = $query->fetch_assoc()) {
			$rows[] = $result;
		}

		$output = '';

		if(!empty($rows)) {
			// Display the album artwork
			$n = 0;
			foreach($rows as $row) {
				$output .= '<div style="transform:rotate('.(10*$n).'deg); -webkit-transform:rotate('.(10*$n).'deg); -ms-transform:rotate('.(10*$n).'deg); position: absolute; float: left; z-index: '.(99-$n).'"><img src="'.permalink($this->url.'/image.php?t=m&w=100&h=100&src='.$row['art']).'" id="playlist-art'.$id.'"></div>';
				$n++;
			}
		} else {
			// Show the default artwork
			$output .= '<div style="transform:rotate(0deg); -webkit-transform:rotate(0deg); -ms-transform:rotate(0deg); position: absolute; float: left; z-index: 0"><img src="'.permalink($this->url.'/image.php?t=m&h=100&w=100&src=default.png').'" id="playlist-art'.$id.'"></div>';
		}
		return $output;
	}
	
	function coverButtons() {
		global $LNG;
		
		$buttons = array(	't'.$this->countSongs() => array('', '', (($this->countSongs() == 1) ? 'track' : 'tracks')),
							((!empty($this->subscriptionsList[1])) ? 'g'.$this->subscriptionsList[1] : '') => array('&r=', 'subscriptions'),
							((!empty($this->subscribersList[1])) ? 's'.$this->subscribersList[1] : '') => array('&r=', 'subscribers'),
							(($this->getLikes()) ? 'l'.$this->getLikes() : '') => array('&r=', 'likes'),
							(($this->getPlaylists()) ? 'p'.$this->getPlaylists() : '') => array('&r=', 'playlists'));
		/*
		array map: value => parameter
						 => parameter value
		
		Special note: t, g, s, l, p characters are being inserted into the array in order to avoid duplicated array keys when the count is the same for both keys
		*/

		$button = '';
		foreach($buttons as $value => $name) {
			// Check whether the value is empty or not in order to return the button
			
			if($value) {
				$button .= '<a class="cover-button'.(((isset($_GET['r']) && $name[1] == $_GET['r']) || ($name[1] == '' && empty($_GET['r']))) ? ' cover-button-active' : '').'" rel="loadpage" href="'.permalink($this->url.'/index.php?a=profile&u='.((!empty($this->profile)) ? $this->profile : $this->username).$name[0].$name[1]).'">'.str_replace(array('t', 'g', 's', 'l', 'p'), '', $value).' '.$LNG[$name[1].($name[2] ?? null)].'</a>';
			}
		}
		
		return $button;
	}
	
	function getProfileCard($profile) {
		global $LNG, $CONF;
		$coverImage = ((!empty($profile['cover'])) ? $profile['cover'] : 'default.png');
		$coverAvatar = ((!empty($profile['image'])) ? $profile['image'] : 'default.png');
		$subscribersList = $this->getSubs($profile['idu'], 1, null);
		$subscribe = $this->getSubscribe(null, null, 1);
		$count = $this->countSongs($profile['idu']);
		$card = '
			<div class="profile-card-cover"><img src="'.permalink($this->url.'/image.php?t=c&w=300&h=100&src='.((!empty($profile['cover'])) ? $profile['cover'] : 'default.png')).'"></div>
			<div class="profile-card-avatar">
				<a href="'.permalink($this->url.'/index.php?a=profile&u='.$profile['username']).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=a&w=112&h=112&src='.$coverAvatar).'" /></a>
			</div>
			<div class="profile-card-info">
				<div class="profile-card-username">
					<a href="'.permalink($this->url.'/index.php?a=profile&u='.$profile['username']).'" rel="loadpage"><span id="author'.$profile['idu'].$profile['username'].'"></span><span id="time'.$profile['idu'].$profile['username'].'"></span><div class="cover-text-container">'.realName($profile['username'], $profile['first_name'], $profile['last_name']).''.(($this->getProStatus($profile['idu'])) ? '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/pro.png" title="'.$LNG['pro_user'].'" />' : '').'</div></a>
				</div>
				<div class="profile-card-location">
					'.((location($profile['country'], $profile['city'])) ? location($profile['country'], $profile['city']) : '').'
				</div>
			</div>
			<div class="profile-card-buttons">'.(($count) ? '<a href="'.permalink($this->url.'/index.php?a=profile&u='.$profile['username']).'" rel="loadpage"><div class="profile-card-stats" title="'.$count.' '.$LNG['tracks'].'"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/tracks_small.png" />'.$count.'</div></a>' : '').(($subscribersList[1]) ? '<a href="'.permalink($this->url.'/index.php?a=profile&u='.$profile['username'].'&r=subscribers').'" rel="loadpage"><div class="profile-card-stats" title="'.$subscribersList[1].' '.$LNG['subscribers'].'"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/followers.png" />'.$subscribersList[1].'</div></a>' : '').''.((!empty($subscribe)) ? '<div class="profile-card-buttons-container"><div id="subscribe'.$profile['idu'].'">'.$subscribe.'</div>'.$this->chatButton($profile['idu'], $profile['username'], 1).'</div>' : '').'</div>
		';
		return $card;
	}
	
	function fetchProfileInfo($profile) {
		global $LNG, $CONF;
		
		// Array: database column name => url model
		$social = array(
			'website' => '%s', 
			'facebook' => 'https://facebook.com/%s',
			'gplus' => 'https://instagram.com/%s',
			'twitter' => 'https://twitter.com/%s',
			'youtube' => 'https://youtube.com/%s',
			'soundcloud' => 'https://soundcloud.com/%s',  
			'myspace' => 'https://myspace.com/%s',
			'lastfm' => 'https://last.fm/user/%s',
			'vimeo' => 'https://vimeo.com/%s',
			'tumblr' => 'https://%s.tumblr.com'
		);
		
		$info = '<div class="sidebar-container widget-about"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['profile_about'].(($this->username == $profile['username']) ? '<div class="sidebar-header-extra"><a href="'.permalink($this->url.'/index.php?a=settings').'" rel="loadpage">'.$LNG['edit'].'</a></div>' : '').'</div> '.((!empty($profile['description'])) ? '<div class="sidebar-description">'.$profile['description'].'</div>' : '').'<div class="sidebar-social-container">';
		
		foreach($social as $value => $url) {
			$info .= ((!empty($profile[$value])) ? '<div class="social-icon-container"><div class="social-icon-padding"><a href="'.sprintf($url, $profile[$value]).'" target="_blank" rel="nofllow" title="'.(($value == 'website') ? $LNG['profile_view_site'] : sprintf($LNG['profile_view_social'], ucfirst($value))).'"><div class="social-icon '.$value.'-icon"></div></a></div></div>' : '');
		}
		
		$info .= '</div></div></div>';
		
		return $info;
	}
	
	function checkNewNotifications($limit, $type = null, $for = null, $ln = null, $cn = null, $fn = null, $dn = null) {
		global $LNG, $CONF;
		// $ln, $cn, $fn, $dn holds the filters for the notifications
		// Type 0: Just check for and show the new notification alert
		// Type 1: Return the last X notifications from each category. (Drop Down Notifications)
		// Type 2: Return the latest X notifications (read and unread) (Notifications Page)
		
		// For 0: Returns the Global Notifications
		// For 1: Return results for the Chat Messages Notifications (Drop Down)
		// For 2: Return Chat Messages results for the Notifications Page

		// Start checking for new notifications
		if(!$type) {
		
			// Check for new likes events
			if($ln) {
				$checkLikes = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '2' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
				$lc = $checkLikes->num_rows;
			}
			
			// Check for new comments events
			if($cn) {
				$checkComments = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '1' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						
				// If any, return 1 (show notification)
				$cc = $checkComments->num_rows;
			}
			
			// Check for new friend additions
			if($fn) {
				$checkFriends = $this->db->query(sprintf("SELECT `id` FROM `notifications` WHERE `to` = '%s' AND `from` <> '%s' AND `type` = '4' AND `read` = '0'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
				
				// If any, return 1 (show notification)
				$fc = $checkFriends->num_rows;
			}
			
			if($for) {
				if($dn) {
					$checkChats = $this->db->query(sprintf("SELECT `id` FROM `chat` WHERE `to` = '%s' AND `read` = '0'", $this->db->real_escape_string($this->id)));
					
					// If any, return 1 (show notification)
					$dc = $checkChats->num_rows;
				}
			}
			
			$output = array('response' => array('global' => $lc + $cc + $fc, 'messages' => $dc));
			return json_encode($output);
		} else {
			// Define the arrays that holds the values (prevents the array_merge to fail, when one or more options are disabled)
			$likes = array();
			$comments = array();
			$friends = array();
			$chats = array();
			
			if($type) {
				// Get the events and display all unread messages [applies only to the drop down widgets]
				if($for == 2 && $type !== 2 || !$for && $type !== 2) {
					if($ln) {
						// Check for new likes events
						$checkLikes = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '2' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the comments
						while($row = $checkLikes->fetch_assoc()) {
							$likes[] = $row;
						}
					}
					
					if($cn) {
						// Check for new comments events
						$checkComments = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '1' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));

						// Fetch the comments
						while($row = $checkComments->fetch_assoc()) {
							$comments[] = $row;
						}
					}
					
					if($fn) {
						// Check for new messages events
						$checkFriends = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '4' AND `notifications`.`read` = '0' ORDER BY `notifications`.`id` DESC", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id)));
						// Fetch the messages
						while($row = $checkFriends->fetch_assoc()) {
							$friends[] = $row;
						}
					}
					
					if($for == 2) {
						if($dn) {
							// Check for new messages events
							$checkChats = $this->db->query(sprintf("SELECT * FROM (SELECT `from`, max(`id`) as `id` FROM `chat` WHERE `chat`.`to` = '%s' AND `chat`.`read` = '0' GROUP BY `from` ORDER BY `id` DESC ) AS a LEFT JOIN `chat` ON `a`.`id` = `chat`.`id` LEFT JOIN `users` ON `users`.`idu` = `chat`.`from`", $this->db->real_escape_string($this->id)));
							// Fetch the chat
							while($row = $checkChats->fetch_assoc()) {
								$chats[] = $row;
							}
						}
					}
				}
				// Return the unread messages for drop-down messages notifications (excludes $for 2 and $type 2)
				elseif($type !== 2 && $for == 1) {
					if($dn) {
						// Check for new messages events
						$checkChats = $this->db->query(sprintf("SELECT * FROM (SELECT `from`, max(`id`) as `id` FROM `chat` WHERE `chat`.`to` = '%s' AND `chat`.`read` = '0' GROUP BY `from` ORDER BY `id` DESC ) AS a LEFT JOIN `chat` ON `a`.`id` = `chat`.`id` LEFT JOIN `users` ON `users`.`idu` = `chat`.`from`", $this->db->real_escape_string($this->id)));
						// Fetch the chat
						while($row = $checkChats->fetch_assoc()) {
							$chats[] = $row;
						}
					}
				}
				
				// If there are no new (unread) notifications (for the drop-down wdigets), get the lastest notifications
				if(!$for) {
					// Verify for the drop-down notifications
					if(empty($likes) && empty($comments) && empty($friends) || $type == 2) {
						$all = 1;
					}
				} 
				// For the Notifications Page
				elseif($for == 2 && $type == 2) {
					// Verify for the notifications page
					$all = 1;
				}
				
				if(isset($all)) {
					// LR: Enable limit rows when there are unread messages
					$lr = 1;
					if($ln) {
						$checkLikes = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '2' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
						
						while($row = $checkLikes->fetch_assoc()) {
							$likes[] = $row;
						}
					}
					
					if($cn) {
						$checkComments = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '1' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
						
						while($row = $checkComments->fetch_assoc()) {
							$comments[] = $row;
						}
					}
					
					if($fn) {
						$checkFriends = $this->db->query(sprintf("SELECT * FROM `notifications`,`users` WHERE `notifications`.`from` = `users`.`idu` AND `notifications`.`to` = '%s' and `notifications`.`from` <> '%s' AND `notifications`.`type` = '4' ORDER BY `notifications`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $limit));
						
						while($row = $checkFriends->fetch_assoc()) {
							$friends[] = $row;
						}
					}
					
					if($for == 2) {
						if($dn) {
							$checkChats = $this->db->query(sprintf("SELECT * FROM (SELECT `from`, max(`id`) as `id` FROM `chat` WHERE `chat`.`to` = '%s' AND `chat`.`read` = '0' GROUP BY `from` ORDER BY `id` DESC ) AS a LEFT JOIN `chat` ON `a`.`id` = `chat`.`id` LEFT JOIN `users` ON `users`.`idu` = `chat`.`from` LIMIT %s", $this->db->real_escape_string($this->id), $limit));
						
							while($row = $checkChats->fetch_assoc()) {
								$chats[] = $row;
							}
						}
					}
					
					// If there are no latest notifications
					if($for == 2) {
						// Verify for the notifications page
						if(empty($likes) && empty($comments) && empty($friends) && empty($chats)) {
							return '<div class="notification-row"><div class="notification-padding">'.$LNG['no_notifications'].'</a></div></div><div class="notification-row"><div class="notification-padding"><a href="'.permalink($this->url.'/index.php?a=settings&b=notifications').'" rel="loadpage">'.$LNG['notifications_settings'].'</a></div></div>';
						}
					} else {
						// Verify for the drop-down notifications
						if(empty($likes) && empty($comments) && empty($friends)) {
							return '<div class="notification-row"><div class="notification-padding">'.$LNG['no_notifications'].'</a></div></div>';
						}
					}
				}
			}
			
			// Add the types into the recursive array results
			$x = 0;
			foreach($likes as $like) {
				$likes[$x]['event'] = 'like';
				$x++;
			}
			$y = 0;
			foreach($comments as $comment) {
				$comments[$y]['event'] = 'comment';
				$y++;
			}
			$a = 0;
			foreach($friends as $friend) {
				$friends[$a]['event'] = 'friend';
				$a++;
			}
			$b = 0;
			foreach($chats as $chat) {
				$chats[$b]['event'] = 'chat';
				$b++;
			}
			
			$array = array_merge($likes, $comments, $friends, $chats);

			// Sort the array
			usort($array, 'sortDateAsc');
			
			$i = 0;
			$events = '';
			foreach($array as $value) {
				if($i == $limit && $lr == 1) break;
				$time = $value['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($value['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($value['time']));
				} elseif($this->time == '3') {
					$date = strtotime($value['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				$events .= '<div class="notification-row'.(($value['read'] == 0 && $value['event'] == 'chat') ? ' notification-unread' : '').'"><div class="notification-padding">';
				if($value['event'] == 'like') {
					$events .= '<div class="notification-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'" rel="loadpage"><img class="notifications" src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$value['image']).'" /></a></div><div class="notification-text"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'">'.sprintf($LNG['new_like_notification'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name']), permalink($this->url.'/index.php?a=track&id='.$value['parent'])).'.<br /><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/like_n.png" width="17" height="17" /> <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'comment') {
					$events .= '<div class="notification-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'" rel="loadpage"><img class="notifications" src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$value['image']).'" /></a></div><div class="notification-text">'.sprintf($LNG['new_comment_notification'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name']), permalink($this->url.'/index.php?a=track&id='.$value['parent'].'#comment'.$value['child'])).'.<br /><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/comment_n.png" width="17" height="17" /> <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'friend') {
					$events .= '<div class="notification-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'" rel="loadpage"><img class="notifications" src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$value['image']).'" /></a></div><div class="notification-text">'.sprintf($LNG['new_friend_notification'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name'])).'.<br /><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/friendships_n.png" width="17" height="17" /> <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				} elseif($value['event'] == 'chat') {
					$events .= '<div class="notification-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'" rel="loadpage"><img class="notifications" src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$value['image']).'" /></a></div><div class="notification-text">'.sprintf($LNG['new_chat_notification'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name']), permalink($this->url.'/index.php?a=messages&u='.$value['username'].'&id='.$value['idu'])).'.<br /><span class="chat-snippet">'.$this->parseMessage(substr($value['message'], 0, 45)).'...</span><br /><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/chat_n.png" width="17" height="17" /> <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span></div>';
				}
				$events .= '</div></div>';
				$i++;
			}
			
			if(!$for) {
				// Mark global notifications as read
				$this->db->query("UPDATE `notifications` SET `read` = '1', `time` = `time` WHERE `to` = '{$this->id}' AND `read` = '0'");
			} 
			// Update when the for is set, and it's not viewed from the Notifications Page
			elseif($type !== 2) {
				// Mark chat messages notifications as read
				$this->db->query("UPDATE `chat` SET `read` = '1', `time` = `time` WHERE `to` = '{$this->id}' AND `read` = '0'");
			}
			// return the result
			return $events;
		}
		
		// If no notification was returned, return 0
	}
	
	function getCategories() {
		$query = $this->db->query("SELECT `name` FROM `categories`");

		$rows = $categories = [];
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// Flat the array
		foreach($rows as $category) {
			$categories[] = $category['name'];
		}
		
		return $categories;
	}
	
	function fetchCategory($categories) {
		$categories = explode(',', $categories);
		
		// If the tag is viewed from a filter page, set it to the filter's name
		if(isset($_GET['a']) && $_GET['a'] == 'explore' && !empty($_GET['filter'])) {
			return mb_strtolower($_GET['filter']);
		}
		
		$list = array_map('mb_strtolower', $this->categories);
		
		// If a tag is matched with one of the categories
		foreach($categories as $category) {
			if(in_array(mb_strtolower($category), $list)) {
				return $category;
			}
		}
		
		// Return the first tag
		return $categories[0];
	}
	
	function chatButton($id, $username, $z = null) {
		// Profile: Returns the current row username
		// Z: A switcher for the sublist CSS class
		global $LNG;
		if($z == 1) {
			$style = ' subslist_message';
		}
		if(!empty($this->username) && $this->username !== $username) {
			return '<a href="'.permalink($this->url.'/index.php?a=messages&u='.$username.'&id='.$id).'" title="'.$LNG['send_message'].'" rel="loadpage"><div class="message_btn'.$style.'"></div></a>';
		}
	}
	
	function getSubscribe($type = null, $list = null, $z = null) {
		global $LNG;
		// Type 0: Just show the button
		// Type 1: Go trough the add friend query
		// List: Array (for the dedicated profile page list)
		// Z: A switcher for the sublist CSS class
        $style = '';
		if($list) {
			$profile = $list;
		} else {
			$profile = $this->profile_data;
		}
		if($z == 1) {
			$style = ' subslist';
		}
		
		// Avoid queries search for abuse avoid, Repro: 5 users follows $X, then $X goes private, the button to unfollow remains active to offer the possibility to unfollow
		
		// Verify if the profile is completely private
		if($profile['private'] == 1) {
			// Run the query only if the user is logged-in
			if($this->id) {
				$avoid = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `leader` = '%s' AND `subscriber` = '%s'", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
			}
			if(isset($avoid->num_rows) && $avoid->num_rows == 0) {
				if($this->username == $profile['username']) {
					// Set a variable if the profile is private and the one who views the profile is the owner, then show settings button
					$a = 1;
				} else {
					return false;
				}
			}
		} elseif($profile['private'] == 2) {
			if($this->id) {
				$avoid = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `leader` = '%s' AND `subscriber` = '%s'", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
				
				// If the user have semi-private profile, hide the add button
				$result = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `subscriber` = '%s' AND `leader` = '%s'", $this->db->real_escape_string($profile['idu']), $this->db->real_escape_string($this->id)));
			}
			if($result->num_rows == 0 && $avoid->num_rows == 0) {
				if($this->username == $profile['username']) {
					// Set a variable if the profile is semi-private and the one who views the profile is the owner, then show settings button
					$a = 1;
				} else {
					return false;
				}
			}
		}
		
		// Verify if the username is logged in, and it's not the same with the viewed profile
		if(!empty($this->username) && $this->username !== $profile['username']) {
			if($type) {
				$result = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `subscriber` = '%s' AND `leader` = '%s'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu'])));
				
				// If a relationship already exist, then remove
				if($result->num_rows) {
					$result = $this->db->query(sprintf("DELETE FROM `relations` WHERE `subscriber` = '%s' AND `leader` = '%s'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu'])));
					$insertNotification = $this->db->query(sprintf("DELETE FROM `notifications` WHERE `from` = '%s' AND `to` = '%s' AND `type` = '4'", $this->db->real_escape_string($this->id), $profile['idu']));
				} else {
					// Followings limit
					if($this->subscriptionsList[1] < 500) {
						$result = $this->db->query(sprintf("INSERT INTO `relations` (`subscriber`, `leader`, `time`) VALUES ('%s', '%s', CURRENT_TIMESTAMP)", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu'])));
						$insertNotification = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `type`, `read`) VALUES ('%s', '%s', '4', '0')", $this->db->real_escape_string($this->id), $profile['idu']));
						
						if($this->email_new_friend) {
							// If user has emails on new friendships enabled
							if($profile['email_new_friend']) {
								// Send e-mail
								sendMail($profile['email'], sprintf($LNG['ttl_new_friend_email'], $this->username), sprintf($LNG['new_friend_email'], realName($profile['username'], $profile['first_name'], $profile['last_name']), permalink($this->url.'/index.php?a=profile&u='.$this->username), $this->username, $this->title, permalink($this->url.'/index.php?a=settings&b=notifications')), $this->email);
							}
						}
					}
				}
			}
		} elseif($this->username == $profile['username'] || isset($a)) {
			return '<a href="'.permalink($this->url.'/index.php?a=settings&b=avatar').'" rel="loadpage" title="'.$LNG['edit_profile_cover'].'"><div class="edit_profile_btn'.$style.'"></div></a>';
		} else {
			return false;
		}
		
		$result = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `subscriber` = '%s' AND `leader` = '%s'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($profile['idu'])));
		if($result->num_rows) {
			return '<div class="subscribe_btn unsubscribe'.$style.'" title="'.$LNG['remove_friend'].'" onclick="subscribe('.$profile['idu'].', 1'.(($z == 1) ? ', 1' : '').')"></div>';
		} else {
			return '<div class="subscribe_btn'.$style.'" title="'.$LNG['add_friend'].'" onclick="subscribe('.$profile['idu'].', 1'.(($z == 1) ? ', 1' : '').')"></div>';
		}
	}
	
	function showError($error, $type = null) {
		global $LNG;
		// Type 1: return only the description
		// Type 0: return title and description
		
		if($type) {
			$message = '<div class="message-inner">'.$LNG["$error"].'</div>';
		} else {
			$message = '<div class="private-profile-content"><div class="page-header">'.$LNG[$error.'_ttl'].'</div><div class="message-inner">'.$LNG["$error"].'</div></div>';
		}
		return array($message, 1);
	}
	
	function verifyRelationship($user_id, $profile_id, $type) {
		// Type 0: The viewed profile subscribed to the logged in username
		// Type 1: The logged in username is a subscriber of the viewed profile
		if($type == 0) {
			$result = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `subscriber` = '%s' AND `leader` = '%s'", $this->db->real_escape_string($profile_id), $this->db->real_escape_string($user_id)));
		} elseif($type == 1) {
			$result = $this->db->query(sprintf("SELECT * FROM `relations` WHERE `leader` = '%s' AND `subscriber` = '%s'", $this->db->real_escape_string($profile_id), $this->db->real_escape_string($user_id)));
		}
		
		// If the logged in username is the same with the viewed profile
		if($user_id == $profile_id) {
			return 2;
		}
		
		// If a relationship exist
		elseif($result->num_rows) {
			return 1;
		} else {
			return 0;
		}
	}
	
	function validateTrack($values, $type, $num) {
		global $settings;
		// Type 0: For Edit Page
		// Type 1: For Upload Page
		
		// Validate Release date
		if(!empty($values['day']) && !empty($values['month']) && !empty($values['year'])) {
			$values['release'] = date("Y-m-d", mktime(0, 0, 0, $values['month'], $values['day'], $values['year']));
		} else {
			$values['release'] = NULL;
		}
		
		$values['record'] = htmlspecialchars(trim(nl2clean($values['record'], ENT_QUOTES, 'UTF-8')));
		
		// Validate License
		if($values['license']) {
			if($values['license-nc'] != 0) {
				$values['license-nc'] = 1;
			}
			
			// License Types
			$licenseTypes = array(0, 1, 2);
			
			if(!in_array($values['license-nd-sa'], $licenseTypes)) {
				$values['license-nd-sa'] = 0;
			}
			
			$values['license'] = '1'.$values['license-nc'].$values['license-nd-sa'];
		} else {
			$value['license'] = 0;
		}
		
		// Unset unwated fields
		unset($values['day']);
		unset($values['month']);
		unset($values['year']);
		unset($values['license-nc']);
		unset($values['license-nd-sa']);
		
		if($type) {
			$allowedColumns = array('title', 'description', 'name', 'art', 'tag', 'buy', 'record', 'release', 'license', 'size', 'download', 'public');
			$values['title'] = $values['title'][$num];
		} else {
			$allowedColumns = array('title', 'description', 'art', 'tag', 'buy', 'record', 'release', 'license', 'download', 'public');
			$values['title'] = $values['title'][0];
		}
		
		// Strip unwated columns
		foreach($values as $key => $value) {
			if(!in_array($key, $allowedColumns)) {
				unset($values[$key]);
			}
		}
		
		// Validate Description
		$values['description'] = htmlspecialchars(trim(nl2clean($values['description'])));
		
		$desclimit = 5000;
		if(strlen($values['description']) > $desclimit) {
			$error[] = array(6, $desclimit);
		}
		
		// Validate URL
		if((!filter_var($values['buy'], FILTER_VALIDATE_URL) && !empty($values['buy'])) || (substr($values['buy'], 0, 7) != 'http://' && substr($values['buy'], 0, 8) != 'https://' && !empty($values['buy']))) {
			$error[] = array(7);
		}

		// Validate Tags
        $values['tag'] = isset($_POST['category']) ? $_POST['category'].','.$values['tag'] : $values['tag'];
		$tags = array_filter(explode(',', $values['tag']));

		foreach($tags as $key => $tag) {
			$tag = mb_strtolower($tag);
			// Array { Replace any unwated characters, Replace consecutive "-" characters }
			$tag = preg_replace(array('/[^[:alnum:]-]/u', '/--+/'), array('', '-'), $tag);
			// Remove tags that has only "-" characters
			if($tag == '-') {
				unset($tags[$key]);
			} else {
				$tags[$key] = $tag;
			}
		}
		
		// Remove empty and duplicated tags
		$tags = array_filter(array_unique($tags));
		$taglimit = 20;
		$tagmax = 10;
		$tagmin = 1;
		if(count($tags) > $tagmax) {
			$error[] = array(8, $tagmax);
		} elseif(count($tags) < $tagmin) {
			$error[] = array(9, $tagmin);
		}
		
		// Check for tags length
		foreach($tags as $tag) {
			if(strlen($tag) >= $taglimit) {
				$error[] = array(12, $taglimit);
			}
		}
		
		$values['tag'] = implode(',', $tags).',';
		
		// Validate Title
		$titlelimit = 100;
		if(empty($values['title'])) {
			$error[] = array(10);
		} elseif(strlen($values['title']) > $titlelimit) {
			$error[] = array(11, $titlelimit);
		} else {
			$values['title'] = htmlspecialchars(trim(nl2clean($values['title'])));
		}
		
		// Validate Download
		if($values['download'] != 0) {
			$values['download'] = 1;
		}
		
		// Validate Privacy
		if($values['public'] != 0) {
			$values['public'] = 1;
		}
		
		// Validate the files to be uploaded
		if(empty($error)) {
			$tpath = __DIR__ .'/../uploads/tracks/';
			$mpath = __DIR__ .'/../uploads/media/';
			
			if($type) {
				if(isset($_FILES['track']['name'])) {
					// Get the total uploaded size
					$query = $this->db->query(sprintf("SELECT (SELECT SUM(`size`) FROM `tracks` WHERE `uid` = '%s') as upload_size", $this->db->real_escape_string($this->id)));
					$result = $query->fetch_assoc();
					
					$ext = strtolower(pathinfo($_FILES['track']['name'][$num], PATHINFO_EXTENSION));
					$size = $_FILES['track']['size'][$num];
					$fullname = $_FILES['track']['name'][$num];
					$allowedExt = explode(',', $this->track_format);
					$maxsize = $this->track_size;
					
					// Validate the total upload size allowed
					if(($result['upload_size'] + $size) > $this->track_size_total) {
						$error[] = array(0, saniscape($values['title']));
					}
					
					// Get file type validation
					$track = validateFile($_FILES['track']['tmp_name'][$num], $_FILES['track']['name'][$num], $allowedExt, 1);
					
					if($track['valid'] && $size < $maxsize && $size > 0) {
						$t_tmp_name = $_FILES['track']['tmp_name'][$num];
						$name = pathinfo($_FILES['track']['name'][$num], PATHINFO_FILENAME);
						$size = $_FILES['track']['size'][$num];
						$tName = mt_rand().'_'.mt_rand().'_'.mt_rand().'.'.$this->db->real_escape_string($ext);
						
						// Send the track name in array format to the function
						$values['name'] = $tName;
						$values['size'] = $size;
						
						$t_upload = true;
					} elseif($_FILES['track']['name'][$num] == '') {
						// If the file size is higher than allowed or 0
						$error[] = array(1);
					}
					if(!empty($ext) && ($size > $maxsize || $size == 0)) {
						// If the file size is higher than allowed or 0
						$error[] = array(2, saniscape($values['title']), fsize($maxsize));
					}
					if(!empty($ext) && !$track['valid']) {
						// If the file format is not allowed
						$error[] = array(3, saniscape($values['title']), implode(', ', $allowedExt));
					}
				}
			}
			
			if(empty($GLOBALS['multiart'])) {
				if(isset($_FILES['art']['name'])) {
					foreach($_FILES['art']['error'] as $key => $err) {
						$ext = strtolower(pathinfo($_FILES['art']['name'][$key], PATHINFO_EXTENSION));
						$size = $_FILES['art']['size'][$key];
						$allowedExt = explode(',', $this->art_format);
						$maxsize = $this->art_size;
						
						// Get file type validation
						$image = validateFile($_FILES['art']['tmp_name'][$key], $_FILES['art']['name'][$key], $allowedExt, 0);
						
						if(is_array($image) && $image['valid'] && $size < $maxsize && $size > 0 && !empty($image['width']) && !empty($image['height'])) {
							$m_tmp_name = $_FILES['art']['tmp_name'][$key];
							$name = pathinfo($_FILES['art']['name'][$key], PATHINFO_FILENAME);
							$fullname = $_FILES['art']['name'][$key];
							$size = $_FILES['art']['size'][$key];
							
							// If there's no error during the track's upload
							if(empty($error)) {
								// Generate the file name & store it into a super global to check when multi upload
								$mName = $GLOBALS['multiart'] = mt_rand().'_'.mt_rand().'_'.mt_rand().'.'.$this->db->real_escape_string($ext);
							}
							
							// Delete the old image when editing the track
							if(!$type) {
								$query = $this->db->query(sprintf("SELECT `art` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($_GET['id'])));
								$result = $query->fetch_assoc();
								deleteImages(array($result['art']), 2);
							}
							
							// Send the image name in array format to the function
							$values['art'] = $mName;
							
							$m_upload = true;
						} elseif($_FILES['art']['name'][$key] == '') {
							// If no file is selected
							if($type) {
								$values['art'] = 'default.png';
							} else {
								// If the cover artwork is not selected, unset the image so that it doesn't update the current one
								unset($values['art']);
							}
						}
						if(!empty($ext) && ($size > $maxsize || $size == 0)) {
							// If the file size is higher than allowed or 0
							$error[] = array(4, fsize($maxsize));
						}
						if(!empty($ext) && !$image['valid']) {
							// If the file format is not allowed
							$error[] = array(5, implode(', ', $allowedExt));
						}
					}
				}
			} else {
				// Generate a new file name
				$ext = strtolower(pathinfo($GLOBALS['multiart'], PATHINFO_EXTENSION));
				$finalName = mt_rand().'_'.mt_rand().'_'.mt_rand().'.'.$this->db->real_escape_string($ext);
				// Copy the previous track image
				copy($mpath.$GLOBALS['multiart'], $mpath.$finalName);
				// Store the new file name
				$values['art'] = $finalName;
			}
		}
		
		if(!empty($error)) {
			return array(0, $error);
		} else {
			if(isset($t_upload)) {
				if($settings['as3']) {
					global $s3;
					try {
						$s3->putObject(array(
								'Bucket' => $settings['as3_bucket'],
								'Key'	 => 'uploads/tracks/'.$tName,
								'Body'	 => fopen($t_tmp_name, 'rb')
						));
						$values['as3_track'] = 1;
					} catch(S3Exception $e) {
						return array(0, $e);
					}
				} else {
					$values['as3_track'] = 0;
					// Move the file into the uploaded folder
					move_uploaded_file($t_tmp_name, $tpath.$tName);
				}
			}
			if(isset($m_upload)) {
				// Move the file into the uploaded folder
				move_uploaded_file($m_tmp_name, $mpath.$mName);
			}
			return array(1, $values);
		}
	}
	
	function updateTrack($values, $type) {
		// Type 0: For Edit Page
		// Type 1: For Upload Page
		global $LNG;
		$x = 0;
		foreach($values['title'] as $key => $val) {
			// Validate the track
			$validate = $this->validateTrack($values, $type, $key);
			
			// If there's an error
			if(!$validate[0]) {
				// Display the errors
                $err = '';
				foreach($validate[1] as $error) {
					$err .= notificationBox('error', sprintf($LNG["{$error[0]}_upload_err"], ((isset($error[1])) ? $error[1] : ''), ((isset($error[2])) ? $error[2] : '')));
				}
				// Return the error (edit page)
				if(!$type) {
					return $err;
				}
			}
			
			// If the track is validated
			if($validate[0]) {
				// Prepare the values
				foreach($validate[1] as $column => $value) {
					// Set a date for the MySQL strict mode validation
					if($column == 'release') {
						$value = (empty($value) ? NULL : $value);
					}
					if($type) {
						$columns[$column] = $this->db->real_escape_string($value);
					} else {
						if($column == 'release' && $value == NULL) {
							$columns[] = sprintf("`%s` = NULL", $column);
						} else {
							$columns[] = sprintf("`%s` = '%s'", $column, $this->db->real_escape_string($value));
						}
					}
				}
				
				$column_list = implode(',', $columns);
				
				if($type) {
					$this->db->query(sprintf("INSERT INTO `tracks` (`uid`, `title`, `description`, `name`, `tag`, `art`, `buy`, `record`, `release`, `license`, `size`, `as3_track`, `download`, `public`, `time`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', CURRENT_TIMESTAMP)", $this->db->real_escape_string($this->id), $columns['title'], $columns['description'], $columns['name'], $columns['tag'], $columns['art'], $columns['buy'], $columns['record'], ($columns['release'] ? "'".$columns['release']."'" : 'NULL'), $columns['license'], $columns['size'], $columns['as3_track'], $columns['download'], $columns['public']));
					$x++;
				} else {
					$stmt = $this->db->prepare(sprintf("UPDATE `tracks` SET `time` = `time`, %s WHERE `uid` = '%s' AND `id` = '%s'", $column_list, $this->id, $this->db->real_escape_string($_GET['id'])));
					// Execute the statement
					$stmt->execute();
					// Save the affected rows
					$affected = $stmt->affected_rows;
					
					// Close the statement
					$stmt->close();
					
					if($affected) {
						if(!$type) {
							return notificationBox('success', $LNG["track_updated"]);
						}
					}
					return notificationBox('info', $LNG["nothing_changed"]);
				}
			}
		}
		
		if($x && $type == 1) {
			if(!empty(trim(nl2clean($values['playlist'])))) {
				$this->managePlaylist(null, 2, $values['playlist'], $values['public']);

				$query = $this->db->query(sprintf("SELECT `id`,`name` FROM `playlists` WHERE `by` = '%s' ORDER BY `id` DESC LIMIT 0, 1", $this->id));

				$playlist = $query->fetch_assoc();

				$pl = 1;
			}
		}
		
		if($x > 0) {
			$query = $this->db->query(sprintf("SELECT * FROM `tracks` WHERE `uid` = '%s' ORDER BY `id` DESC LIMIT 0, %s", $this->db->real_escape_string($this->id), $x));
            $err = '';
			if(isset($pl)) {
				$err .= notificationBox('success', sprintf($LNG['playlist_created'], permalink($this->url.'/index.php?a=playlist&id='.$playlist['id'].'&name='.cleanUrl($playlist['name'])), $playlist['name']));
			}
			while($row = $query->fetch_assoc()) {
				if(isset($pl)) {
					$this->playlistEntry($row['id'], $playlist['id'], 3);
				}
				$err .= notificationBox('success', sprintf($LNG['track_uploaded'], permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])), $row['title']));
			}
		}
		return array($err, 1);
	}
	
	function getTrackInfo($id, $type) {
		// Type 0: Return track title link and author permission
		// Type 1: Return track info 
		$query = $this->db->query(sprintf("SELECT * FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
		$result = $query->fetch_assoc();
		
		if($type) {
			return $result;
		} else {
			return array('<a href="'.permalink($this->url.'/index.php?a=track&id='.$id.'&name='.cleanUrl($result['title'])).'" rel="loadpage">'.$result['title'].'</a>', (($this->id == $result['uid']) ? 1 : 0));
		}
	}

	function getTrack($id) {
		// Obey the message privacy to the profile privacy and then to the message privacy
		$query = $this->db->query(sprintf("SELECT `idu`,`username`,`private`,`tag`, `tracks`.`public` as `public` FROM `tracks`, `users` WHERE `tracks`.`id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($id)));
		$result = $query->fetch_assoc();
		
		$relationship = $this->verifyRelationship($this->id, $result['idu'], 0);
		
		// Store the current track's name for recommended tracks
		$this->track_tag = $result['tag'];

		$x = 0;
		// Check if the track exist
		if($query->num_rows > 0) {
			// If the track is public
			// Check privacy
			switch($result['private']) {
				case 0:
					break;
				case 1:
					// Check if the username is not same with the profile
					if($this->id !== $result['idu']) {
						$x = 1;
					}
					break;
				case 2:
					// Check relationship
					if(!$relationship) {
						$x = 2;
					}
					break;
			}
			// If the track is private
			if($result['public'] == 0) {
				if($this->id !== $result['idu']) {
					$x = 1;
				}
			}
			
			// Override any settings and grant admin permissions
			if($this->is_admin) {
				$x = 0;
			}
		}
		
		// Get the track for track page
		$query = sprintf("SELECT * FROM `tracks`, `users` WHERE `tracks`.`id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($id));

		if($x) {
			if($x == 2) {
				return $this->showError('track_hidden_2');
			} else {
				return $this->showError('track_hidden_1');
			}
		} elseif($result['public'] == 2) {
			return $this->showError('track_suspended_1');
		} else {
			return $this->getTracks($query, 'trackPage', null);
		}
	}
	
	function getComments($id, $cid, $start, $type = null) {
		// Type 0: Get Comments
		// Type 1: Get last comment
		global $LNG;
		// The query to select the subscribed users
		
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND comments.id < \''.$this->db->real_escape_string($cid).'\'';
		}
		
		if($type) {
			$query = sprintf("SELECT * FROM `comments`, `users` WHERE `uid` = '%s' AND `comments`.`uid` = `users`.`idu` ORDER BY `id` DESC LIMIT 0, 1", $this->db->real_escape_string($this->id));
		} else {
			$query = sprintf("SELECT * FROM comments, users WHERE comments.tid = '%s' AND comments.uid = users.idu %s ORDER BY comments.id DESC LIMIT %s", $this->db->real_escape_string($id), $start, ($this->c_per_page + 1));
		}
		
		// check if the query was executed
		if($result = $this->db->query($query)) {
			
			// Set the result into an array
			$rows = array();
			while($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			
			// Define the $comments variable;
			$comments = $load = $loadmore = '';
			
			// If there are more results available than the limit, then show the Load More Comments
			if(array_key_exists($this->c_per_page, $rows)) {
				$loadmore = 1;
				
				if($type) {
					$loadmore = 0;
				}
				// Unset the last array element because it's not needed, it's used only to predict if the Load More Comments should be displayed
				unset($rows[$this->c_per_page]);
			}
			
			foreach($rows as $comment) {
				// Define the time selected in the Admin Panel
				$time = $comment['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($comment['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($comment['time']));
				} elseif($this->time == '3') {
					$date = strtotime($comment['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				
				if($this->username == $comment['username']) { // If it's current username is the same with the current author
					$delete = '<a onclick="delete_the('.$comment['id'].', 0)" title="'.$LNG['delete_this_comment'].'"><div class="delete_btn"></div></a>';
				} elseif(empty($this->username)) { // If the user is not registered
					$delete = '';
				} else { // If the current username is not the same as the author
					$delete = '<a onclick="report_the('.$comment['id'].', 0)" title="'.$LNG['report_this_comment'].'"><div class="report_btn"></div></a>';
				}
				
				// Variable which contains the result
				$comments .= '
				<div class="message-reply-container" id="comment'.$comment['id'].'">
					'.$delete.'
					<div class="message-reply-avatar">
						<a href="'.permalink($this->url.'/index.php?a=profile&u='.$comment['username']).'" rel="loadpage"><img onmouseover="profileCard('.$comment['idu'].', '.$comment['id'].', 1, 0)" onmouseout="profileCard(0, 0, 1, 1);" onclick="profileCard(0, 0, 1, 1);" src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$comment['image']).'" /></a>
					</div>
					<div class="message-reply-message">
						<span class="message-reply-author"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$comment['username']).'" rel="loadpage">'.realName($comment['username'], $comment['first_name'], $comment['last_name']).'</a></span>
						<div class="list-time">
							(<div class="timeago'.$b.'" title="'.$time.'">
								'.$time.'
							</div>)
						</div>
						<div class="message-reply-content">'.$this->parseMessage($comment['message']).'</div>
					</div>
					<div class="delete_preloader" id="del_comment_'.$comment['id'].'"></div>
					
				</div>';
				$message_id = $comment['tid'];
				$load_id = $comment['id'];
			}
			
			if($loadmore) {
				$load = '<div class="load-more-comments" id="comments'.htmlentities($id, ENT_QUOTES).'"><div class="load_more"><a onclick="loadComments('.$message_id.', '.$load_id.', '.$this->c_per_page.')" id="infinite-load">'.$LNG['load_more'].'</a></div></div>';
			}
					
			// Close the query
			$result->close();
			
			// Return the comments variable
			return $comments.$load;
		} else {
			return false;
		}
	}
	
	function parseMessage($message) {
		global $LNG, $CONF;

		// Parse links
		$parseUrl = preg_replace_callback('/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))/', "parseCallback", $message);
		
		// Parse @mentions and #hashtags
		$parsedMessage = preg_replace(array('/(^|[^a-z0-9_\/])@([a-z0-9_]+)/i', '/(^|[^a-z0-9_\/])#(\w+)/u'), array('$1<a href="'.permalink($this->url.'/index.php?a=profile&u=$2').'" rel="loadpage">@$2</a>', '$1<a href="'.permalink($this->url.'/index.php?a=explore&filter=$2').'" rel="loadpage">#$2</a>'), $parseUrl);

		return $parsedMessage;
	}
	
	function delete($id, $type) {
		// Type 0: Delete Comment
		// Type 1: Delete Track
		// Type 2: Delete Chat Message
		
		// Prepare the statement
		if($type == 0) {
			$stmt = $this->db->prepare("DELETE FROM `comments` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `uid` = '{$this->db->real_escape_string($this->id)}'");
			
			// Set $x variable to 1 if the delete query is for `comments`
			$x = 0;
		} elseif($type == 1) {
			// Get the current type (for images and tracks deletion)
			$query = $this->db->query(sprintf("SELECT `art`, `name`, `as3_track` FROM `tracks` WHERE `id` = '%s' AND `uid` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
			$track = $query->fetch_assoc();
			
			$stmt = $this->db->prepare("DELETE FROM `tracks` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `uid` = '{$this->db->real_escape_string($this->id)}'");
			
			// Set $x variable to 1 if the delete query is for `tracks`
			$x = 1;
		} elseif($type == 2) {
			$stmt = $this->db->prepare("DELETE FROM `chat` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `from` = '{$this->db->real_escape_string($this->id)}'");
			
			$x = 2;
		} elseif($type == 3) {
			$stmt = $this->db->prepare("DELETE FROM `playlists` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `by` = '{$this->db->real_escape_string($this->id)}'");
			
			$x = 3;
		}

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();
		
		// If the tracks/comments table was affected
		if($affected) {
			// Deletes the Comments/Likes/Reports/Notifications/Playlists and Images if the Track was deleted
			if($x == 1) {
				$this->db->query("DELETE FROM `comments` WHERE `tid` = '{$this->db->real_escape_string($id)}'");
				$this->db->query("DELETE FROM `likes` WHERE `track` = '{$this->db->real_escape_string($id)}'");
				$this->db->query("DELETE FROM `reports` WHERE `track` = '{$this->db->real_escape_string($id)}' AND `parent` = '0'");
				$this->db->query("DELETE FROM `notifications` WHERE `parent` = '{$this->db->real_escape_string($id)}'");
				$this->db->query("DELETE FROM `playlistentries` WHERE `track` = '{$this->db->real_escape_string($id)}'");
				
				deleteMedia($track['art'], array($track['name'] => $track['as3_track']));
			} elseif($x == 0) {
				$this->db->query("DELETE FROM `reports` WHERE `post` = '{$this->db->real_escape_string($id)}' AND `parent` != '0'");
				$this->db->query("DELETE FROM `notifications` WHERE `child` = '{$this->db->real_escape_string($id)}' AND `type` = '1'");
			} elseif($x == 3) {
				$this->db->query("DELETE FROM `playlistentries` WHERE `playlist` = '{$this->db->real_escape_string($id)}'");
			}
		}
		
		return ($affected) ? 1 : 0;
	}
	
	function report($id, $type, $reason = null) {
		// Type 0: Comments
		// Type 1: Tracks
		global $LNG;
		
		// Check if the Track exists
		if($type == 1) {
			$result = $this->db->query(sprintf("SELECT `id` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
		} else {
			$result = $this->db->query(sprintf("SELECT `id`, `tid`, `message` FROM `comments` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
			$parent = $result->fetch_array(MYSQLI_ASSOC); 
		}

		// If the Track/Comment exists
		if($result->num_rows) {
			$result->close();
		
			// Get the report status, 0 = already exists * 1 = is safe
			if($type == 1) {
				$query = sprintf("SELECT `state`,`by` FROM `reports` WHERE `track` = '%s' AND `type` = '%s' AND `by` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($type), $this->db->real_escape_string($this->id));
			} else {
				$query = sprintf("SELECT `state` FROM `reports` WHERE `track` = '%s' AND `type` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($type));
			}
			$result = $this->db->query($query);
			$state = $result->fetch_assoc();
			
			//  If the report already exists
			if($result->num_rows) {
				// If the comment state is 0, then already exists
				if($state['state'] == 0) {
					return (($type == 1) ? notificationBox('info', $LNG["{$type}_already_reported"]) : $LNG["{$type}_already_reported"]);
				} elseif($state['state'] == 1) {
					if($type == 1) {
						if($state['by'] == $this->id) {
							return notificationBox('info', $LNG["{$type}_is_safe"]);
						}
					} else {
						return $LNG["{$type}_is_safe"];
					}
				} else {
					return (($type == 1) ? notificationBox('error', $LNG["{$type}_is_deleted"]) : $LNG["{$type}_is_deleted"]);
				}
			} else {
				if($type == 1) {					
					$validate = $this->checkReportForm();

					if($validate) {
						return notificationBox('error', sprintf($LNG["{$validate[0]}"], $validate[1] ?? null));
					}

					if($reason) {
                        $desc = $_POST['description'];
                    } else {
                        $desc = $_POST['description']."\r\n\r\n[".$_POST['signature']."]";
                    }
					
					$stmt = $this->db->prepare(sprintf("INSERT INTO `reports` (`track`, `parent`, `content`, `by`, `type`, `reason`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", $this->db->real_escape_string($id), (isset($parent['tid'])) ? $parent['tid'] : 0, $this->db->real_escape_string(htmlspecialchars(trim(nl2clean($desc)))), $this->db->real_escape_string($this->id), $this->db->real_escape_string($type), $reason));
				} else {
					$stmt = $this->db->prepare(sprintf("INSERT INTO `reports` (`track`, `parent`, `content`, `by`, `type`, `reason`) VALUES ('%s', '%s', '%s', '%s', '%s', 0)", $this->db->real_escape_string($id), (isset($parent['tid'])) ? $parent['tid'] : 0, $this->db->real_escape_string($parent['message']), $this->db->real_escape_string($this->id), $this->db->real_escape_string($type)));
				}

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;

				// Close the statement
				$stmt->close();
				
				// If the comment was added, return 1
				if($affected) {
					return (($type == 1) ? notificationBox('success', $LNG["{$type}_report_added"]) : $LNG["{$type}_report_added"]);
				} else {
					return (($type == 1) ? notificationBox('error', $LNG["{$type}_report_error"]) : $LNG["{$type}_report_error"]);
				}
			}
		} else {
			return $LNG["{$type}_not_exists"];
		}
	}
	
	function checkReportForm() {
	    if(isset($_POST['reason']) && in_array($_POST['reason'], [0, 1])) {
            if(strlen($_POST['description']) > 5000) {
                return array('rep_resc_error', 5000);
            }

            if(empty($_POST['description'])) {
                return array('all_fields');
            }

	        if($_POST['reason'] == 0) {
                if(!isset($_POST['report1']) || !isset($_POST['report2']) || !isset($_POST['report3']) || empty($_POST['signature'])) {
                    return array('all_fields');
                }
            }
        } else {
	        return array('all_fields');
        }

	}
	
	function addComment($id, $comment) {
		// Check if the POST is public
		$query = sprintf("SELECT * FROM `tracks`,`users` WHERE `id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($id));
		$result = $this->db->query($query);

		$row = $result->fetch_assoc();

		// If the POST is public
		if($row['public'] == 1) {
			// Add the insert message
			$stmt = $this->db->prepare("INSERT INTO `comments` (`uid`, `tid`, `message`) VALUES ('{$this->db->real_escape_string($this->id)}', '{$this->db->real_escape_string($id)}', '{$this->db->real_escape_string(htmlspecialchars($comment))}')");

			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;

			// Close the statement
			$stmt->close();
			
			// Select the last inserted message
			$getId = $this->db->query(sprintf("SELECT `id`,`uid`,`tid` FROM `comments` WHERE `uid` = '%s' AND `tid` = '%s' ORDER BY `id` DESC", $this->db->real_escape_string($this->id), $row['id']));
			$lastComment = $getId->fetch_assoc();
			
			// Do the INSERT notification
			$insertNotification = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `parent`, `child`, `type`, `read`) VALUES ('%s', '%s', '%s', '%s', '1', '0')", $this->db->real_escape_string($this->id), $row['uid'], $row['id'], $lastComment['id']));
			
			if($affected) {
				// If email on likes is enabled in admin settings
				if($this->email_comment) {
				
					// If user has emails on commentss enabled and he's not commenting on his own track
					if($row['email_comment'] && ($this->id !== $row['idu'])) {
						global $LNG;
						
						// Send e-mail
						sendMail($row['email'], sprintf($LNG['ttl_comment_email'], $this->username), sprintf($LNG['comment_email'], realName($row['username'], $row['first_name'], $row['last_name']), permalink($this->url.'/index.php?a=profile&u='.$this->username), $this->username, permalink($this->url.'/index.php?a=track&id='.$id.'&name='.cleanUrl($row['title'])), $this->title, permalink($this->url.'/index.php?a=settings&b=notifications')), $this->email);
					}
				}
			}
			
			// If the comment was added, return 1
			return ($affected) ? 1 : 0;
		} else {
			return 0;
		}
	}
	
	function changePrivacy($id, $value, $type = null) {
		// Type 0: Tracks privacy
		// Type 1: Playlist privacy
		if($type == 1) {
			$stmt = $this->db->prepare("UPDATE `playlists` SET `public` = '{$this->db->real_escape_string($value)}', `time` = `time` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `by` = '{$this->db->real_escape_string($this->id)}'");
		} else {
			$stmt = $this->db->prepare("UPDATE `tracks` SET `public` = '{$this->db->real_escape_string($value)}', `time` = `time` WHERE `id` = '{$this->db->real_escape_string($id)}' AND `uid` = '{$this->db->real_escape_string($this->id)}'");
		}
		
		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;
		
		// Close the statement
		$stmt->close();
		
		return ($affected) ? 1 : 0;
	}
	
	function ago($i) {
		global $LNG;
		$m = time() - $i; $o = $LNG['just_now'];
		$t = array($LNG['year_s'] => 31556926, $LNG['month_s'] => 2629744, $LNG['week_s'] => 604800, $LNG['day_s'] => 86400, $LNG['hour_s'] => 3600, $LNG['minute_s'] =>60, $LNG['second_s'] => 1);
		foreach($t as $u => $s) {
			if($s <= $m) {
				$v = floor($m/$s);
				$o = "$v $u".' '.$LNG['ago'];
				break;
			}
		}
		return $o;
	}
	
	function sidebarFilters($bold) {
		global $LNG, $CONF;
		
		// Start the output
		$row = array('people', 'tracks', 'playlists');
		$link = '<div class="sidebar-container widget-filter"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['search'].'</div>';
		foreach($row as $type) {
			$class = '';
			// Start the strong tag
			if($type == $bold || empty($bold) && $type == 'people') {
				$class = ' sidebar-link-active';
			}
			// Output the links
			
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].'&filter='.$type.'&q='.htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8')).'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/filters/'.$type.'.png" />'.$LNG["sidebar_{$type}"].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarNotifications($bold) {
		global $LNG, $CONF;

		$class = '';

		// Start the output
		$row = array('likes', 'comments', 'friendships', 'chats');
		$link = '<div class="sidebar-container widget-notifications"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['filter_notifications'].'</div>';
		if(empty($bold)) {
			$class = ' sidebar-link-active';
		}
		$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a']).'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/filters/all_notifications.png" />'.$LNG["all_notifications"].'</a></div>';
		foreach($row as $type) {
			$class = '';
			if($type == $bold) {
				$class = ' sidebar-link-active';
			}
			
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].'&filter='.$type).'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/filters/'.$type.'.png" />'.$LNG["sidebar_{$type}"].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarCategories($bold) {
        global $LNG;
		$query = $this->db->query("SELECT * FROM `categories` ORDER BY `name` ASC");

        $rows = [];
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		$link = '<div class="sidebar-container widget-categories"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['categories'].'</div>';

		$class = '';
		if(empty($bold) && isset($_GET['popular']) == false && isset($_GET['liked']) == false) {
			$class = ' sidebar-link-active';
		}
		$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a']).'" rel="loadpage">'.$LNG['latest_music'].'</a></div>';
		
		foreach(['popular', 'liked'] as $value) {
			// Start the strong tag
			$class = '';
			if(isset($_GET[$value])) {
				$class = ' sidebar-link-active';
			}
			
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].'&'.$value).'" rel="loadpage">'.$LNG[$value.'_music'].'</a></div>';
		}
		$link .= '<div class="sidebar-link-divider"><div class="divider"></div></div>';

		foreach($rows as $category) {
			$category['name'] = mb_strtolower($category['name']);
			$class = '';
			if($category['name'] == $bold) {
				$class = ' sidebar-link-active';
			}
			
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].'&filter='.$category['name']).'" rel="loadpage">'.ucfirst($category['name']).'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function sidebarDates($bold, $values = null) {
		global $LNG;
		$row = $this->listDates($values);
		
		$profile = ($this->profile) ? '&u='.$this->profile : '';
		$class = '';
		// If the result is not empty
		if($row) {
			// Start the output
			$link = '<div class="sidebar-container widget-archive"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['archive'].'</div>';
			if(empty($bold)) {
				$class = ' sidebar-link-active';
			}
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].$profile).'" rel="loadpage">'.$LNG["all_time"].'</a></div>';
			foreach($row as $date) {
				
				// Explode the born value [[0]=>Y,[1]=>M];
				$born = explode('-', wordwrap($date, 4, '-', true));
				
				// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
				$month = intval($born[1]);
				$class = '';
				// Start the strong tag
				if($date == $bold) {
					$class = ' sidebar-link-active';
				}
				
				// Output the links
				$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].$profile.'&filter='.$date).'" rel="loadpage">'.$LNG["month_{$month}"].' - '.$born[0].'</a></div>';
			}
			$link .= '</div></div>';
			return $link;
		}
	}
	
	function listDates($values = null) {
		if($values == false) {
			return false;
		} elseif($values == 'profile') {
			$profile = ($this->profile == $this->username) ? '' : 'AND public = 1';
			$query = sprintf("SELECT DISTINCT extract(YEAR_MONTH from `time`) AS dates FROM `tracks` WHERE uid = '%s' %s ORDER BY `dates` DESC", $this->db->real_escape_string($this->profile_id), $profile);
		} elseif($values) {
			$query = sprintf("SELECT DISTINCT extract(YEAR_MONTH from `time`) AS dates FROM `tracks` WHERE uid IN (%s) AND `public` = 1 ORDER BY `dates` DESC", $this->db->real_escape_string($values));
		}

		$result = $this->db->query($query);

		$rows = [];
		while($row = $result->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// If the select was made
		if($result = $this->db->query($query)) {
			// Define the array;
			$store = [];
			foreach($rows as $date) {
				// Add the elemnts to the array
				$store [] = $date['dates'];
			}
			return $store;
		} else {
			return false;
		}
	}
	
	function sidebarReport($id) {
		global $LNG;
		return '<div class="sidebar-container sidebar-report"><div class="sidebar-description"><a '.(($this->id) ? 'href="'.(($this->id) ? permalink($this->url.'/index.php?a=track&id='.$id.'&type=report') : permalink($this->url.'/index.php?a=welcome')).'" rel="loadpage"' : 'href="javascript:;" onclick="connect_modal()"').'>'.$LNG['report'].'</a></div></div>';
	}

	function getTrackList($id) {
		$query = $this->db->query(sprintf("SELECT `id` FROM `tracks` WHERE `uid` = '%s'", $this->db->real_escape_string($id)));

		$rows = [];
		while($row = $query->fetch_assoc()) {
			$rows[] = $row['id'];
		}
		return $rows;
	}

	function proAccountHistory($id = null, $title = null, $type) {
		// Title: Decide if the title is included or not
		// Type 0: Return all transactions
		// Type 1: Return inactive transactions
		global $LNG;
		if($type) {
			$x = ' AND `valid` < \''.date('Y-m-d H:i:s').'\'';
		} else {
			$x = '';
		}
		$query = $this->db->query(sprintf("SELECT * FROM `payments` WHERE `by` = '%s'%s ORDER BY `id` DESC", ($id) ? $id : $this->db->real_escape_string($this->id), $x));

		$rows = [];
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}

		$result = '';
		if(!empty($rows)) {
			$result = '<div class="page-content">';
			if($title) {
				$result .= '<div class="plan-history-title">'.$LNG['transactions_history'].'</div>
							<div class="divider"></div>
							<div class="plan-history-container">
								<div class="plan-option">'.$LNG['from'].'</div>
								<div class="plan-option">'.$LNG['to'].'</div>
								<div class="plan-option">'.$LNG['type'].'</div>
								<div class="plan-option">'.$LNG['status'].'</div>
							</div>';
			}
			
			foreach($rows as $row) {
				$fromArr = explode('-', $row['time']);
				$date = $fromArr[0].'-'.$fromArr[1].'-'.substr($fromArr[2], 0, 2);
				
				$toArr = explode('-', $row['valid']);
				$valid = $toArr[0].'-'.$toArr[1].'-'.substr($toArr[2], 0, 2);
				
				$status = paymentStatus($row['status']);
				
				$result .= '<div class="feature-container">
								<div class="plan-history">'.$date.'</div>
								<div class="plan-history">'.$valid.'</div>
								<div class="plan-history">'.$row['amount'].' '.$row['currency'].'</div>
								<div class="plan-history">'.$status.'</div>
							</div>';
			}
			$result .= '</div>';
		}
		
		return $result;
	}
	
	function getProStatus($id = null, $type = null) {
		// Type 0: Get the Pro Status of a user
		// Type 1: Decide whether the pro accounts are enabled from the Admin Panel, and if so, check the status
		// Type 2: Returns all the details of last transaction
		$query = $this->db->query(sprintf("SELECT * FROM `payments` WHERE `by` = '%s' ORDER BY `id` DESC LIMIT 0, 1", ($id) ? $id : $this->db->real_escape_string($this->id)));
		$result = $query->fetch_assoc();
		
		if($type == 1) {
			if($this->paypalapp) {
				if($result['status'] == 1 && strtotime($result['valid']) >= time()) {
					return 1;
				} else {
					return 0;
				}
			} else {
				// Always return all features if the pro accounts are disabled
				return 1;
			}
		} elseif($type == 2) {
			return $result;
		} else {
			if(isset($result) && $result['status'] == 1 && strtotime($result['valid']) >= time()) {
				return 1;
			} else {
				return 0;
			}
		}
	}
	
	function goProMessage($message = null, $type = null, $artist = null) {
		// Message: Certain number to match a string from language file
		// Type 0: For Stats page
		// Type 1: For Account Plan page
		// Type 2: For sidebar widgets
		// Artist: If set, it must have at least one track uploaded
		global $LNG;
		if($type == 1) {
			if($this->paypalapp && !$this->getProStatus($this->id)) {
				// Generate a random number for a dynamic widget if the $message is not set
				if(!$message) {
					$message = rand(1, 2);
				}
				if($artist) {
					// If there's no track uploaded by the user and 
					if(!$this->trackList) {
						return false;
					}
				}
				return '<div class="sidebar-container widget-gopro"><div class="go-pro-widget-container"><div class="go-pro-title">'.$LNG["go_pro_ttl_{$message}"].'</div><div class="go-pro-widget-desc">'.$LNG["go_pro_{$message}"].'</div><div class="go-pro-btn go-pro-widget-btn"><a href="'.permalink($this->url.'/index.php?a=pro').'" rel="loadpage">'.$LNG['go_pro'].'</a></div></div></div>';
			}
		} else {
			return '<div class="go-pro-container"><div class="go-pro-desc">'.$LNG["go_pro_{$message}"].'</div><div class="go-pro-btn"><a href="'.permalink($this->url.'/index.php?a=pro').'" rel="loadpage">'.$LNG['go_pro'].'</a></div></div>';
		}
	}
	
	function sidebarDescription($id, $type = null, $raw = null) {
		global $LNG;
		
		if($type == 1) {
			$query = $this->db->query(sprintf("SELECT `description` FROM `playlists` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
		} else {
			$query = $this->db->query(sprintf("SELECT `description`, `record`, `release`, `license` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
		}
		
		$result = $query->fetch_row();
		
		// Return raw text output
		if($raw) {
			return nl2br($result[0]);
		}

        $extra = '';

		if($type !== 1) {
			// Explode the born value [[0]=>Y,[1]=>M,[2]=>D];
			$date = explode('-', $result[2]);

			// Make it into integer instead of a string (removes the 0, e.g: 03=>3, prevents breaking the language)
            if(isset($date[0]) && isset($date[1]) && isset($date[2])) {
                $month = intval($date[1]);

                $extra = '<div class="sidebar-description">'.$LNG['release_date'].'<br> <strong>'.$LNG["month_$month"].' '.$date[2].', '.$date[0].'</strong></div>';
            }
			$extra .= (($result[1]) ? '<div class="sidebar-description">'.$LNG['record_label'].'<br> <strong>'.$result[1].'</strong></div>' : '');

			$nc = $nd = $sa = '';

			if($result[3]) {
				$license = str_split($result[3]);

				if(isset($license[1]) && $license[1] == 1) {
					$nc = ' <div class="license-icon license-nc-icon"></div>';
				}
				if(isset($license[2]) && $license[2] == 1) {
					$nd = ' <div class="license-icon license-nd-icon"></div>';
				} elseif(isset($license[2]) && $license[2] == 2) {
					$sa = ' <div class="license-icon license-sa-icon"></div>';
				}
				$extra .= (($result[3]) ? '<div class="sidebar-description">'.$LNG['licensed_under'].'<div class="sidebar-license"><a href="http://creativecommons.org/about/license/" target="_blank" title="'.$LNG['creative_commons'].'" rel="nofollow"><div class="license-icon license-cc-icon"></div> <div class="license-icon license-at-icon"></div>'.$nc.$nd.$sa.'</a></div></div>' : '');
			}
		}
		
		$description = ($result[0] ? '<div class="sidebar-description">'.nl2br($this->parseMessage($result[0])).'</div>' : '');

		$output = '';
		if(!empty($description) || !empty($extra)) {
			$output = '<div class="sidebar-container widget-description"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['ttl_description'].'</div>'.$description.$extra.'</div></div>';
		}
		
		return $output;
	}

	function sidebarKeywords($id, $type = null) {
		// Type 0: Return keywords for Track Page
		// Type 1: Return keywords for Playlist Page
		global $LNG;
		
		if($type == 1) {
			$query = $this->db->query(sprintf("SELECT `tracks`.`tag` FROM `playlistentries`,`users`,`tracks` WHERE (`playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`public` = 1) OR (`playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = `tracks`.`id` AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`uid` = '%s') ORDER BY `playlistentries`.`id` DESC", $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->id));
		} else {
			$query = $this->db->query(sprintf("SELECT `tag` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
		}

		$hashtags = $output = '';
		// Store the hashtags into a string
		while($row = $query->fetch_assoc()) {
			$hashtags .= $row['tag'];
		}

		if($hashtags) {
			$hashtags = explode(',', $hashtags);
			if($type == 1) {
				// Count the array values and filter out the blank spaces (also lowercase all array elements to prevent case-sensitive showing up, e.g: Test, test, TEST)
				$count = array_count_values(array_map('mb_strtolower', array_filter($hashtags)));
				
				// Sort them by must popular
				arsort($count);
			} else {
				// Lowercase all the array elements to prevent case-sensitive tags showing up, e.g: Test, test, TEST
				$count = array_map('mb_strtolower', array_filter($hashtags));
				
				// Reverse the keys with values
				$count = array_flip($count);
			}
			// Return only 15 hashtags
			$count = array_slice($count, 0, 15, true);
			
			$output = '<div class="sidebar-container widget-trending"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['categories'].'</div><div class="sidebar-description">';
			foreach($count as $row => $value) {
				$output .= '<div class="sidebar-tag"><a href="'.permalink($this->url.'/index.php?a=explore&filter='.$row).'" rel="loadpage">#'.$row.'</a></div>';
			}
			$output .= '</div></div></div>';
		}
		
		return $output;
	}
	
	function sidebarRecommended($id) {
		global $LNG;
		
		$current_tags = array_filter(explode(',', $this->track_tag));
		
		$i = 1;
		$like = '';
		foreach($current_tags as $tag) {
			if(count($current_tags) > 1) {
				if($i == 1) {
					$like .= sprintf("(`tag` LIKE '%s'", '%'.$this->db->real_escape_string($tag).'%');
				} else {
					$like .= sprintf(" OR `tag` LIKE '%s'", '%'.$this->db->real_escape_string($tag).'%');
				}
				if($i == (count($current_tags))) {
					$like .= ")";
				}
			} else {
				$like = sprintf(" `tag` LIKE '%s'", '%'.$this->db->real_escape_string($tag).'%');
			}
			$i++;
		}
		
		// Get track suggestions based on the current track's categories and exclude the current track from the results
		$query = $this->db->query(sprintf("SELECT * FROM `tracks`, `users` WHERE %s AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`id` != '%s' AND `tracks`.`public` = 1 ORDER BY `tracks`.`views` DESC LIMIT 0, 100", $like, $this->db->real_escape_string($id)));

		$rows = [];
		// Store the array results
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		shuffle($rows);
		
		// If suggestions are available
		if(!empty($rows)) {
			$i = 0;
			
			$output = '<div class="sidebar-container widget-suggestions"><div class="sidebar-header">'.$LNG['recommended'].'</div>';
			foreach($rows as $row) {
				if($i == 3) break; // Display only the last 6 suggestions
				
				$username = realName($row['username'], $row['first_name'], $row['last_name']);
				$subscribersList = $this->getSubs($row['idu'], 1, null);
				$tracks = $this->countSongs($row['idu']);
				
				$output .= '<div class="sidebar-suggestions-inner">
					<div class="sidebar-suggestions">
						<div class="sidebar-suggestions-image"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=m&w=112&h=112&src='.$row['art']).'" /></a></div>
						<div class="sidebar-suggestions-info"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" title="'.$LNG['profile_view_profile'].'" rel="loadpage"><div class="sidebar-title-name">'.$username.'</div></a>
						<div class="sidebar-suggestions-track"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" title="'.$row['title'].'" rel="loadpage">'.$row['title'].'</a></div>
						</div>
					</div>
				</div>';
				$i++;
			}
			$output .= '</div>';
			return $output;
		} else {
			return false;
		}
	}

    function sidebarHistoryStats()
    {
        global $LNG;

        $query = $this->db->query(sprintf("SELECT (SELECT COUNT(`track`) FROM `views` WHERE `by` = '%s' AND `cleared` = 0) AS 'total', (SELECT COUNT(DISTINCT (`track`)) FROM `views` WHERE `by` = '%s' AND `cleared` = 0) AS 'unique'", $this->id, $this->id));

        $result = $query->fetch_assoc();

        $output = '<div class="sidebar-container widget-statistics"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['statistics'].'</div><div class="sidebar-stats-container">';
        $output .= '
            <div class="sidebar-stats-box">'.$LNG['unique_plays'].'</div><div class="sidebar-stats-box sidebar-text-right">'.$result['unique'].'</div>
            <div class="sidebar-stats-box">'.$LNG['total_plays'].'</div><div class="sidebar-stats-box sidebar-text-right">'.$result['total'].'</div>';
        $output .= '</div></div></div>';

        return $output;
    }

    function clearHistory()
    {
        if($_POST['token_id'] == $_SESSION['token_id']) {
            $this->db->query(sprintf("UPDATE `views` SET `cleared` = 1 WHERE `by` = '%s'", $this->id));
        }
    }
	
	function sidebarStatistics($id = null, $type = null, $extra = null) {
		// Type 0: Return statistics for your own tracks that have been played by other users
		// Type 1: Return statistics for track page
		global $LNG;
		
		if($type == 1) {
			$query = $this->db->query(sprintf("SELECT (SELECT count(`track`) FROM `views` WHERE `track` = '%s') as total, (SELECT count(`track`) FROM `views` WHERE `track` = '%s' AND CURDATE() = date(`time`)) as today, (SELECT count(`track`) FROM `views` WHERE `track` = '%s' AND CURDATE()-1 = date(`time`)) as yesterday", $this->db->real_escape_string($id), $this->db->real_escape_string($id), $this->db->real_escape_string($id)));
		} elseif($type == 2)  {
			$query = $this->db->query(sprintf("SELECT (SELECT count(`id`) FROM `tracks` WHERE `uid` = '%s') as tracks_total, (SELECT SUM(`size`) FROM `tracks` WHERE `uid` = '%s') as upload_size", $this->db->real_escape_string($this->id), $this->db->real_escape_string($this->id), $this->db->real_escape_string($id)));
		} else {
			if(!$this->trackList) {
				return;
			}
			$query = $this->db->query(sprintf("SELECT (SELECT count(`track`) FROM `views` WHERE `track` IN (%s)) as total, (SELECT count(`track`) FROM `views` WHERE `track` IN (%s) AND CURDATE() = date(`time`)) as today, (SELECT count(`track`) FROM `views` WHERE `track` IN (%s) AND CURDATE()-1 = date(`time`)) as yesterday", $this->trackList, $this->trackList, $this->trackList));
		}
		 
		$result = $query->fetch_assoc();
		
		$output = '<div class="sidebar-container widget-statistics"><div class="sidebar-content"><div class="sidebar-header">'.((!$type) ? '<a href="'.permalink($this->url.'/index.php?a=stats').'" rel="loadpage">'.$LNG['statistics'].'</a>' : (($extra) ? $LNG['statistics'].' <div class="sidebar-header-extra"><a href="'.permalink($this->url.'/index.php?a=track&id='.$_GET['id'].'&type=stats').'" rel="loadpage">'.$LNG['view_more'].'</a></div>' : $LNG['statistics'])).'</div><div class="sidebar-stats-container">';
			if($type == 2) {
				// Percentage for the stats bar
				$percentage = ($result['upload_size']/$this->track_size_total) * 100;
				$output .= '
				<div class="sidebar-stats-box">'.$LNG['tracks_uploaded'].'</div><div class="sidebar-stats-box sidebar-text-right">'.$result['tracks_total'].'</div>
				<div class="sidebar-stats-box">'.$LNG['total_space'].'</div><div class="sidebar-stats-box sidebar-text-right">'.fsize($this->track_size_total).'</div>
				<div class="divider sidebar-stats-divider"></div>
				<div class="sidebar-stats-box">'.$LNG['used_space'].'</div><div class="sidebar-stats-box sidebar-stats-box-right">'.$LNG['free_space'].'</div>
				<div class="sidebar-stats-bar"><div class="sidebar-stats-bar-percentage" style="width: '.$percentage.'%"></div></div>
				<div class="sidebar-stats-box">'.fsize($result['upload_size']).'</div><div class="sidebar-stats-box sidebar-stats-box-right">'.fsize($this->track_size_total-$result['upload_size']).'</div>';
			} else {
				$output .= '
				<div class="sidebar-stats-box">'.$LNG['plays_today'].'</div><div class="sidebar-stats-box sidebar-text-right sidebar-stats-today">'.$result['today'].'</div>
				<div class="sidebar-stats-box">'.$LNG['plays_yesterday'].'</div><div class="sidebar-stats-box sidebar-text-right">'.$result['yesterday'].'</div>
				<div class="sidebar-stats-box">'.$LNG['plays_total'].'</div><div class="sidebar-stats-box sidebar-text-right">'.$result['total'].'</div>';
			}
		$output .= '</div></div></div>';
		
		return $output;
	}
	
	function onlineUsers($type = null, $value = null) {
		global $LNG, $CONF;
		// Type 2: Show the Friends Results for the live search for Chat/Messages
		//		 : If value is set, find friends from Subscriptions
		// Type 1: Display the friends for the Chat/Messages page
		//		 : If value is set, find exact username
		// Type 0: Display the friends for stream page
		
		// Get the subscritions
		$subscriptions = $this->getSubscriptionsList();
		$currentTime = time();

		if(!empty($subscriptions)) {
			if($type == 1) {
				// Display current friends
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) ORDER BY `online` DESC", $this->db->real_escape_string($subscriptions)));
			} elseif($type == 2) {
				if($value) {
					// Search in friends
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') AND `idu` IN (%s) ORDER BY `online` DESC", '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($subscriptions)));
				} else {
					// Display current friends
					$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) ORDER BY `online` DESC", $this->db->real_escape_string($subscriptions)));
				}
			} else {
				// Display the online friends (used in Feed/Subscriptions)
				$query = $this->db->query(sprintf("SELECT * FROM `users` WHERE `idu` IN (%s) AND `online` > '%s'-'%s' ORDER BY `online` DESC", $this->db->real_escape_string($subscriptions), $currentTime, $this->online_time));
			}
			
			// Store the array results
			while($row = $query->fetch_assoc()) {
				$rows[] = $row;
			}
		}
		
		// usort($rows, 'sortOnlineUsers');
		
		if($type == 1) {
			// Output the users
			$output = '<div class="sidebar-container widget-online-users"><div class="sidebar-content"><div class="sidebar-header"><input type="text" placeholder="'.$LNG['search_in_friends'].'" id="search-list"></div><div class="search-list-container"></div><div class="sidebar-chat-list">';
			if(!empty($rows)) {
				$i = 0;
				foreach($rows as $row) {
					$class = '';
					if(isset($_GET['u']) && $row['username'] == $_GET['u']) {
						$class = ' sidebar-link-active';
					}
					// Switch the images, depending on the online state
					if(($currentTime - $row['online']) > $this->online_time) {
						$icon = 'offline';
					} else {
						$icon = 'online';
					}
					
					$output .= '<div class="sidebar-users'.$class.'"><a href="'.permalink($this->url.'/index.php?a=messages&u='.$row['username'].'&id='.$row['idu']).'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon"> <img src="'.permalink($this->url.'/image.php?t=a&w=25&h=25&src='.$row['image']).'"> '.realName($row['username'], $row['first_name'], $row['last_name']).'</a></div>';
					
					$i++;
				}
			} else {
				$output .= '<div class="sidebar-inner">'.$LNG['lonely_here'].'</div>';
			}
			$output .= '</div></div></div>';
		} elseif($type == 2) {
			$output = '';
			if(!empty($rows)) {
				$i = 0;
				foreach($rows as $row) {
					// Switch the images, depending on the online state
					if(($currentTime - $row['online']) > $this->online_time) {
						$icon = 'offline';
					} else {
						$icon = 'online';
					}
					
					$output .= '<div class="sidebar-users"><a href="'.permalink($this->url.'/index.php?a=messages&u='.$row['username'].'&id='.$row['idu']).'" rel="loadpage"><img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/'.$icon.'.png" class="sidebar-status-icon"> <img src="'.permalink($this->url.'/image.php?t=a&w=25&h=25&src='.$row['image']).'"> '.realName($row['username'], $row['first_name'], $row['last_name']).'</a></div>';
					
					$i++;
				}
			} else {
				$output .= '<div class="sidebar-inner">'.$LNG['no_results'].'</div>';
			}
		} else {
			// If the query has content
			if(!empty($rows)) {
				// Output the online users
				$output = '<div class="sidebar-container widget-online-users"><div class="sidebar-content"><div class="sidebar-header"><a href="'.permalink($this->url.'/index.php?a=messages').'" rel="loadpage">'.$LNG['online_friends'].'</a></div><div class="sidebar-online-users-inner">';
				
				$i = 0;
				$break = $this->friends_online;
				foreach($rows as $row) {
					// If the limit is hit, break the row and show the + button
					if($i == $break) {
						$output .= '<div class="sidebar-online-users" id="online-plus"><div class="sidebar-online-users-padding"><a href="'.permalink($this->url.'/index.php?a=messages').'" rel="loadpage" title="'.$LNG['online_friends'].'"><div class="plus-button"><span class="plus-sign">+'.(count($rows)-$break).'</span></div></a></div></div>';
						break;
					}
					
					$output .= '<div class="sidebar-online-users"><div class="sidebar-online-users-padding"><a href="'.permalink($this->url.'/index.php?a=messages&u='.$row['username'].'&id='.$row['idu']).'" rel="loadpage" title="'.realName($row['username'], $row['first_name'], $row['last_name']).'"><img src="'.permalink($this->url.'/image.php?t=a&w=112&h=112&src='.$row['image']).'" /></a></div></div>';
					
					$i++;
				}
				$output .= '</div></div></div>';
			} else {
				return false;
			}
		}
		return $output;
	}
	
	function getChat($uid, $user) {
		global $LNG, $CONF;
		$uid = saniscape($uid);
		$output =	'<div class="message-container">
						<div class="message-content">
							<div class="page-header">
								<span class="chat-username">'.((empty($user['username'])) ? $LNG['conversation'] : realName($user['username'], $user['first_name'], $user['last_name'])).'</span><span class="blocked-button">'.$this->getBlocked($uid).'</span>
								<div class="header-loader"></div>
							</div>
							<div class="chat-container">
								'.((empty($user['username'])) ? $this->chatError($LNG['start_conversation']) : $this->getChatMessages($uid)).'
							</div>
							<div class="divider"></div>

							<div class="chat-form-inner"><input id="chat" class="chat-user'.$uid.'" placeholder="'.$LNG['write_message'].'" name="chat" /></div>
						</div>	
					</div>';
		return $output;
	}
	
	function checkChat($uid) {
		$query = $this->db->query(sprintf("SELECT * FROM `chat` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0'",  $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id)));
				
		if($query->num_rows) {
			return $this->getChatMessages($uid, null, null, 2); 
		}
		return false;
	}
	
	function getChatMessages($uid, $cid = null, $start = null, $type = null) {
		// uid = user id (from which user the message was sent)
		// cid = where the pagination will start
		// start = on/off
		// type 1: swtich the query to get the last message
		global $LNG;
		// The query to select the subscribed users

		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND `chat`.`id` < \''.$this->db->real_escape_string($cid).'\'';
		}
		
		if($type == 1) {
			$query = sprintf("SELECT * FROM `chat`, `users` WHERE (`chat`.`from` = '%s' AND `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu`) ORDER BY `chat`.`id` DESC LIMIT 1", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid));
		} elseif($type == 2) {
			$query = sprintf("SELECT * FROM `chat`,`users` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0' AND `chat`.`from` = `users`.`idu` ORDER BY `chat`.`id` DESC", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id));
		} else {
			$query = sprintf("SELECT * FROM `chat`, `users` WHERE (`chat`.`from` = '%s' AND `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu`) %s OR (`chat`.`from` = '%s' AND `chat`.`to` = '%s' AND `chat`.`from` = `users`.`idu`) %s ORDER BY `chat`.`id` DESC LIMIT %s", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid), $start, $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id), $start, ($this->m_per_page + 1));
		}
		
		// check if the query was executed
		if($result = $this->db->query($query)) {
			
			if($type !== 1) {
				// Set the read status to 1 whenever you load messages [IGNORE TYPE: 1]
				$update = $this->db->query(sprintf("UPDATE `chat` SET `read` = '1', `time` = `time` WHERE `from` = '%s' AND `to` = '%s' AND `read` = '0'", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id)));
			}

			$load = $loadmore = '';
			$rows = [];
			// Set the result into an array
			while($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$rows = array_reverse($rows);
			
			// Define the $output variable;
			$output = '';
			
			// If there are more results available than the limit, then show the Load More Chat Messages
			if(array_key_exists($this->m_per_page, $rows)) {
				$loadmore = 1;
				
				// Unset the first array element because it's not needed, it's used only to predict if the Load More Chat Messages should be displayed
				unset($rows[0]);
			}
			
			foreach($rows as $row) {
				// Define the time selected in the Admin Panel
				$time = $row['time']; $b = '';
				if($this->time == '0') {
					$time = date("c", strtotime($row['time']));
				} elseif($this->time == '2') {
					$time = $this->ago(strtotime($row['time']));
				} elseif($this->time == '3') {
					$date = strtotime($row['time']);
					$time = date('Y-m-d', $date);
					$b = '-standard';
				}
				
				if($this->username == $row['username']) { // If it's current username is the same with the current author
					$delete = '<a onclick="delete_the('.$row['id'].', 2)" title="'.$LNG['delete_this_message'].'"><div class="delete_btn"></div></a>';
					$class = 'user-one';
				} else {
					$delete = '';
					$class = 'user-two';
				}

				// Variable which contains the result
				$output .= '
				<div class="message-reply-container '.$class.'" id="chat'.$row['id'].'">
					'.$delete.'
					<div class="message-reply-avatar">
						<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'" /></a>
					</div>
					<div class="message-reply-message">
						<span class="message-reply-author"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">'.realName($row['username'], $row['first_name'], $row['last_name']).'</a></span>: '.$this->parseAbstract($this->parseMessage($row['message'])).'
						<div class="list-time">
							<div class="timeago'.$b.'" title="'.$time.'">
								'.$time.'
							</div>
						</div>
					</div>
					<div class="delete_preloader" id="del_chat_'.$row['id'].'"></div>
				</div>';
				$start = $row['id'];
			}
			if($loadmore) {
				$load = '<div class="load-more-chat"><a onclick="loadChat('.htmlentities($uid, ENT_QUOTES).', \'\', \'\', '.$rows[1]['id'].', 1)">'.$LNG['view_more_conversations'].'</a></div>';
			}
					
			// Close the query
			$result->close();
			
			// Return the conversations
			return $load.$output;
		} else {
			return false;
		}
	}

	function parseAbstract($message) {
	    global $CONF;
        $player = '';
        $iframe = '<iframe width="100%%" height="90" frameborder="no" scrolling="no" src="'.$CONF['url'].'/embed.php?id=%s"></iframe>';
        if(preg_match('/'.preg_quote($CONF['url'], '/').'\/track\/([\d]+)\//ui', $message, $matches)) {
            $player = sprintf($iframe, $matches[1]);
        } elseif(preg_match('/'.preg_quote($CONF['url'].'/index.php?a=track&amp;id=', '/').'([\d]+)&amp;/ui', $message, $matches)) {
            $player = sprintf($iframe, $matches[1]);
        }

	    return $message.$player;
    }
	
	function postChat($message, $uid) {
		global $LNG;
		
		$user = $this->profileData(null, $uid);

		if(strlen($message) > $this->chat_length) {
			return $this->chatError(sprintf($LNG['chat_too_long'], $this->chat_length));
		} elseif($uid == $this->id) {
			return $this->chatError(sprintf($LNG['chat_self']));
		} elseif(!$user['username']) {
			return $this->chatError(sprintf($LNG['chat_no_user']));
		}

		$query = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE `by` = '%s' AND uid = '%s'", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid)));
				
		if($query->num_rows) {
			return $this->chatError(sprintf($LNG['blocked_user'], realName($user['username'], $user['first_name'], $user['last_name'])));
		} else {
			$query = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE `by` = '%s' AND uid = '%s'", $this->db->real_escape_string($uid), $this->db->real_escape_string($this->id)));
			
			if($query->num_rows) {
				return $this->chatError(sprintf($LNG['blocked_by'], realName($user['username'], $user['first_name'], $user['last_name'])));
			}
		}
			
		// Prepare the insertion
		$stmt = $this->db->prepare(sprintf("INSERT INTO `chat` (`from`, `to`, `message`, `read`, `time`) VALUES ('%s', '%s', '%s', '%s', CURRENT_TIMESTAMP)", $this->db->real_escape_string($this->id), $this->db->real_escape_string($uid), $this->db->real_escape_string(htmlspecialchars($message)), 0));

		// Execute the statement
		$stmt->execute();
		
		// Save the affected rows
		$affected = $stmt->affected_rows;

		// Close the statement
		$stmt->close();
		if($affected) {
			return $this->getChatMessages($uid, null, null, 1);
		}
	}
	
	function updateStatus($offline = null) {
		if(!$offline) {
			$this->db->query(sprintf("UPDATE `users` SET `online` = '%s' WHERE `idu` = '%s'", time(), $this->db->real_escape_string($this->id)));
		}
	}
	
	function chatError($value) {
		return '<div class="chat-error">'.$value.'</div>';
	}
	
	function playlistEntry($track, $playlist, $type = null) {
		// Type 0: Return whether the track exists in playlist or not
		// Type 1: Return the playlist entries
		// Type 2: Returns the latest added playlist
		// Type 3: Add/Remove track from playlist
		if($type) {
			if($type == 1) {
				$query = $this->db->query(sprintf("SELECT `id`,`name` FROM `playlists` WHERE `by` = '%s' ORDER BY `id` DESC", $this->id));
			} elseif($type == 2) {
				$query = $this->db->query(sprintf("SELECT `id`,`name` FROM `playlists` WHERE `by` = '%s' ORDER BY `id` DESC LIMIT 0, 1", $this->id));
			} elseif($type == 3) {
				// Verify if track exists
				$query = $this->db->query(sprintf("SELECT * FROM `tracks`, `users` WHERE `id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($track)));
				if($query->num_rows > 0) {
					$result = $query->fetch_assoc();
					
					// Verify relationship
					// Check privacy
					switch($result['private']) {
						case 0:
							break;
						case 1:
							// Check if the username is not the same with the track owner
							if($this->id !== $result['idu']) {
								return false;
							}
						case 2:
							$relationship = $this->verifyRelationship($this->id, $result['idu'], 0);
							
							// Check relationship
							if(!$relationship) {
								return false;
							}
							break;
					}
					
					// Verify playlist ownership
					$checkPlaylist = $this->db->query(sprintf("SELECT * FROM `playlists` WHERE `playlists`.`id` = '%s' AND `playlists`.`by` = '%s'", $this->db->real_escape_string($playlist), $this->db->real_escape_string($this->id)));
					
					if($checkPlaylist->num_rows > 0) {
						
						// Check if the track exists in playlist
						$checkTrack = $this->db->query(sprintf("SELECT * FROM `playlists`, `playlistentries` WHERE `playlistentries`.`track` = '%s' AND `playlistentries`.`playlist` = '%s' AND `playlistentries`.`playlist` = `playlists`.`id`", $this->db->real_escape_string($track), $this->db->real_escape_string($playlist)));
	
						// If the track exist, delete it						
						if($checkTrack->num_rows > 0) {
							$this->db->query(sprintf("DELETE FROM `playlistentries` WHERE `track` = '%s' AND `playlist` = '%s'", $this->db->real_escape_string($track), $this->db->real_escape_string($playlist)));
						}
						// Insert the track into playlist
						else {
							$this->db->query(sprintf("INSERT INTO `playlistentries` (`playlist`, `track`) VALUES ('%s', '%s')", $this->db->real_escape_string($playlist), $this->db->real_escape_string($track)));
						}
						
						// Return the playlist entry
						$query = $this->db->query(sprintf("SELECT `id`,`name` FROM `playlists` WHERE `playlists`.`by` = '%s' AND `playlists`.`id` = '%s'", $this->id, $this->db->real_escape_string($playlist)));
					} else {
						return;
					}
				}
			}

			$output = '';
			$rows = [];

			// Store the array results
			while($row = $query->fetch_assoc()) {
				$rows[] = $row;
			}
			
			foreach($rows as $row) {
				$output .= '<div class="playlist-entry'.(($this->playlistEntry($track, $row['id'])) ? ' playlist-added' : '').'" id="playlist-entry'.$row['id'].'" onclick="addInPlaylist('.saniscape($track).','.$row['id'].')">'.$row['name'].'</div>';
			}
			
			return $output;
		} else {
			// Select the playlists
			$query = $this->db->query(sprintf("SELECT * FROM `playlistentries`,`playlists` WHERE `playlists`.`by` = '%s' AND `playlists`.`id` = '%s' AND `playlistentries`.`playlist` = '%s' AND `playlistentries`.`track` = '%s' AND `playlistentries`.`playlist` = `playlists`.`id`", $this->id, $playlist, $this->db->real_escape_string($playlist), $this->db->real_escape_string($track)));
			
			// Store the array results
			if($query->num_rows > 0) {
				return $query->num_rows;
			}
		}
	}
	
	function managePlaylist($id, $type, $data = null, $public = 1) {
		global $LNG;
		// Type 0: Return the current playlist info
		// Type 1: Update the current playlist
		// Type 2: Add a new playlist
		
		if($type == 2) {
			$data = trim($data);
			
			// Prepare the statement
			if(strlen($data) == 0) {
				return;
			}
			
			// Prepare the insertion
			$stmt = $this->db->prepare(sprintf("INSERT INTO `playlists` (`by`, `name`, `description`, `public`, `time`) VALUES ('%s', '%s', '', '%s', CURRENT_TIMESTAMP)", $this->db->real_escape_string($this->id), substr(htmlspecialchars(trim(nl2clean($this->db->real_escape_string($data)))), 0, 128), $public));

			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;

			// Close the statement
			$stmt->close();
			if($affected) {
				// Return the latest added playlist entry
				return $this->playlistEntry($id, 0, 2);
			}
		} elseif($type == 1) {
			// Strip the white spaces at the beginning/end of the name
			$data['name'] = trim($data['name']);
			
			// Prepare the statement
			if(strlen($data['name']) == 0) {
				return notificationBox('error', sprintf($LNG['playlist_name_empty']));
			}
			if(strlen($data['description']) > 160) {
				return notificationBox('error', sprintf($LNG['playlist_description'], 160));
			}
			$stmt = $this->db->prepare("UPDATE `playlists` SET `description` = '{$this->db->real_escape_string(htmlspecialchars(trim(nl2clean($data['description']))))}', `name` = '{$this->db->real_escape_string(htmlspecialchars($data['name']))}' WHERE `id` = '{$this->db->real_escape_string($id)}' AND `by` = '{$this->id}'");		

			// Execute the statement
			$stmt->execute();
			
			// Save the affected rows
			$affected = $stmt->affected_rows;
			
			// Close the statement
			$stmt->close();

			// If there was anything affected return 1
			if($affected) {
				return notificationBox('success', $LNG['changes_saved']);
			} else {
				return notificationBox('info', $LNG['nothing_changed']);
			}
		} else {
			$query = $this->db->query(sprintf("SELECT `name`,`description` FROM `playlists` WHERE `id` = '%s' AND `by` = '%s'", $this->db->real_escape_string($_GET['id']), $this->id));
			$result = $query->fetch_array();
			return $result;
		}
	}
	
	function sidebarButton($id = null, $type = null) {
		global $LNG;
		// Type 0: Upload button for Explore/Stream
		// Type 1: Edit button for Track Page
		// Type 2: Edit button for Playlist Page
		// Type 3: Last track uploaded
		if($type == 1) {
			$query = $this->db->query(sprintf("SELECT * FROM `tracks` WHERE `id` = '%s' AND `uid` = '%s'", $this->db->real_escape_string($_GET['id']), $this->id));

			if($query->num_rows) {
				return '<div class="sidebar-container"><div class="sidebar-button-container"><a href="'.permalink($this->url.'/index.php?a=track&id='.$id.((isset($_GET['type'])) ? '' : '&type=edit')).'" rel="loadpage"><div class="'.((isset($_GET['type'])) ? 'back' : 'edit').'-button"><span class="'.((isset($_GET['type'])) ? 'back' : 'edit').'-icon"></span>'.((isset($_GET['type'])) ? $LNG['go_back'] : $LNG['edit']).'</div></a></div></div>';
			}
		} elseif($type == 2) {
			$query = $this->db->query(sprintf("SELECT * FROM `playlists` WHERE `id` = '%s' AND `by` = '%s'", $this->db->real_escape_string($_GET['id']), $this->id));

			if($query->num_rows) {
				return '<div class="sidebar-container"><div class="sidebar-button-container"><a href="'.permalink($this->url.'/index.php?a=playlist&id='.$id.((!isset($_GET['edit'])) ? '&edit=true' : '')).'" rel="loadpage"><div class="'.((!isset($_GET['edit'])) ? 'edit' : 'back').'-button"><span class="'.((!isset($_GET['edit'])) ? 'edit' : 'back').'-icon"></span>'.((!isset($_GET['edit'])) ? $LNG['edit'] : $LNG['go_back']).'</div></a></div></div>';
			}
		} elseif($type == 3) {
			return '<div class="sidebar-button-container"><a href="'.permalink($this->url.'/index.php?a=track&id='.$id).'" rel="loadpage"><div class="edit-button"><span class="success-icon"></span>'.$LNG['view_track'].'</div></a></div>';
		} else {
			return ($id) ? '<div class="sidebar-button-container"><a href="'.permalink($this->url.'/index.php?a=upload').'" rel="loadpage"><div class="upload-button"><span class="upload-icon"></span>'.$LNG['upload'].'</div></a></div>' : '<div class="sidebar-container"><div class="sidebar-button-container"><a href="'.permalink($this->url.'/index.php?a=upload').'" rel="loadpage"><div class="upload-button"><span class="upload-icon"></span>'.$LNG['upload'].'</div></a></div></div>';
		}
	}
	
	function sidebarFriendsActivity($limit, $type = null) {
		global $LNG, $CONF;

		$subscriptions = $this->getSubscriptionsList();
		// If there is no subscriptions, return false
		if(empty($subscriptions)) {
			return false;
		}
		
		// Define the arrays that holds the values (prevents the array_merge to fail, when one or more options are disabled)
		$likes = array();
		$comments = array();
		$tracks = array();
		
		$checkLikes = $this->db->query(sprintf("SELECT * FROM `likes`,`users` WHERE `likes`.`by` = `users`.`idu` AND `likes`.`by` IN (%s) ORDER BY `id` DESC LIMIT %s", $subscriptions, 25));
		while($row = $checkLikes->fetch_assoc()) {
			$likes[] = $row;
		}
	
		$checkComments = $this->db->query(sprintf("SELECT * FROM `comments`,`users` WHERE `comments`.`uid` = `users`.`idu` AND `comments`.`uid` IN (%s) ORDER BY `id` DESC LIMIT %s", $subscriptions, 25));
		while($row = $checkComments->fetch_assoc()) {
			$comments[] = $row;
		}
	
		$checkMessages = $this->db->query(sprintf("SELECT * FROM `tracks`,`users` WHERE `tracks`.`uid` = `users`.`idu` AND `tracks`.`uid` IN (%s) AND `tracks`.`public` = '1' ORDER BY `id` DESC LIMIT %s", $subscriptions, 25));
		while($row = $checkMessages->fetch_assoc()) {
			$tracks[] = $row;
		}
		
		// If there are no latest notifications
		if(empty($likes) && empty($comments) && empty($tracks)) {
			return false;
		}
		
		// Add the types into the recursive array results
		$x = 0;
		foreach($likes as $like) {
			$likes[$x]['event'] = 'like';
			$x++;
		}
		$y = 0;
		foreach($comments as $comment) {
			$comments[$y]['event'] = 'comment';
			$y++;
		}
		$z = 0;
		foreach($tracks as $track) {
			$tracks[$z]['event'] = 'message';
			$z++;
		}
		
		$array = array_merge($likes, $comments, $tracks);

		// Sort the array
		usort($array, 'sortDateAsc');
		
		$activity = '<div class="sidebar-container widget-friends-activity"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sidebar_friends_activity'].'</div><div class="sidebar-fa-content">';
		$i = 0;
		foreach($array as $value) {
			if($i == $limit) break;
			$time = $value['time']; $b = '';
			if($this->time == '0') {
				$time = date("c", strtotime($value['time']));
			} elseif($this->time == '2') {
				$time = $this->ago(strtotime($value['time']));
			} elseif($this->time == '3') {
				$date = strtotime($value['time']);
				$time = date('Y-m-d', $date);
				$b = '-standard';
			}
			$activity .= '<div class="notification-row"><div class="notification-padding"><div class="sidebar-fa-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$value['username']).'" rel="loadpage"><img class="notifications" src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$value['image']).'" /></a></div>';
			if($value['event'] == 'like') {
				$activity .= '<div class="sidebar-fa-text">'.sprintf($LNG['new_like_fa'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name']), permalink($this->url.'/index.php?a=track&id='.$value['track'])).'. <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span>';
			} elseif($value['event'] == 'comment') {
				$activity .= '<div class="sidebar-fa-text">'.sprintf($LNG['new_comment_fa'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name']), permalink($this->url.'/index.php?a=track&id='.$value['tid'])).'. <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span>';
			} elseif($value['event'] == 'message') {
				$activity .= '<div class="sidebar-fa-text">'.sprintf($LNG['new_track_fa'], permalink($this->url.'/index.php?a=profile&u='.$value['username']), realName($value['username'], $value['first_name'], $value['last_name']), permalink($this->url.'/index.php?a=track&id='.$value['id'])).'. <span class="timeago'.$b.'" title="'.$time.'">'.$time.'</span>';
			}
			$activity .= '</div></div></div>';
			$i++;
		}
		$activity .= '</div></div></div>';
		
		return $activity;
	}
	
	function sidebarSuggestions() {
		global $LNG;
		
		// Get some friends suggestions
		if($this->getSubscriptionsList($this->id)) {
			// If he already follows some of the top users, eliminate those
			$query = $this->db->query(sprintf("SELECT `leader`, `idu`, `username`, `first_name`, `last_name`, `image`, `private`, COUNT(`subscriber`) AS popular FROM `relations` LEFT JOIN `users` ON `users`.`idu` = `relations`.`leader` WHERE `relations`.`leader` NOT IN (%s) AND `users`.`private` = 0 GROUP BY `leader`, `idu`, `username`, `first_name`, `last_name`, `image`, `private` ORDER BY `popular` DESC LIMIT 10", $this->id.','.$this->db->real_escape_string($this->getSubscriptionsList($this->id))));
		} else {
			$query = $this->db->query(sprintf("SELECT `leader`, `idu`, `username`, `first_name`, `last_name`, `image`, `private`, COUNT(`subscriber`) AS popular FROM `relations` LEFT JOIN `users` ON `users`.`idu` = `relations`.`leader` WHERE `users`.`idu` <> '%s' AND `users`.`private` = 0 GROUP BY `leader`, `idu`, `username`, `first_name`, `last_name`, `image`, `private` ORDER BY `popular` DESC LIMIT 10", $this->id));
		}

		// Store the array results
        $rows = [];
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		// Shuffle the results
		shuffle($rows);
		
		// If suggestions are available
		if(!empty($rows)) {
			$i = 0;
			
			$output = '<div class="sidebar-container widget-suggestions"><div class="sidebar-header">'.$LNG['sidebar_suggestions'].'</div>';
			foreach($rows as $row) {
				if($i == 3) break; // Display only the last 6 suggestions
				
				$username = realName($row['username'], $row['first_name'], $row['last_name']);
				$subscribersList = $this->getSubs($row['idu'], 1, null);
				$tracks = $this->countSongs($row['idu']);
				
				$output .= '<div class="sidebar-suggestions-inner">
					<div class="sidebar-suggestions">
						<div class="sidebar-suggestions-image"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" title="'.$LNG['profile_view_profile'].'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=a&w=112&h=112&src='.$row['image']).'" /></a></div>
						<div id="subscribe'.$row['idu'].'">'.$this->getSubscribe(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>
						<div class="sidebar-suggestions-info"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" title="'.$LNG['profile_view_profile'].'" rel="loadpage"><div class="sidebar-title-name">'.$username.'</div></a>
							<div class="sidebar-suggestions-small">'.(($tracks) ? '<div class="sidebar-suggestions-tracks" title="'.$tracks.' '.$LNG['tracks'].'">'.$tracks.'</div>' : '').'
							'.(($subscribersList[1]) ? '<div class="sidebar-suggestions-followers" title="'.$subscribersList[1].' '.$LNG['subscribers'].'">'.$subscribersList[1].'</div>' : '').'</div>
						</div>
					</div>
				</div>';
				$i++;
			}
			$output .= '</div>';
			return $output;
		} else {
			return false;
		}
	}
	
	function sidebarTrending($bold, $per_page) {
		global $LNG;
		
		// Get some friends suggestions [Top Social users -- SUBJECT TO BE CHANGED]
		$query = $this->db->query(sprintf("SELECT * FROM `tracks` WHERE `time` < CURRENT_DATE + INTERVAL 1 WEEK AND `tag` != ''"));
		
		// Store the hashtags into a string
        $hashtags = '';
		while($row = $query->fetch_assoc()) {
			$hashtags .= $row['tag'];
		}

		// If there are trends available
		if(!empty($hashtags)) {
			$i = 0;
			// Count the array values and filter out the blank spaces (also lowercase all array elements to prevent case-insensitive showing up, e.g: Test, test, TEST)
			$hashtags = explode(',', $hashtags);
			$count = array_count_values(array_map('mb_strtolower', array_filter($hashtags)));
			
			// Sort them by trend
			arsort($count);
			$output = '<div class="sidebar-container widget-trending"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sidebar_trending'].'</div>';
			foreach($count as $row => $value) {
				$class = '';
				if($i == $per_page) break; // Display and break when the trends hits the limit
				if($row == $bold) {
					$class = ' sidebar-link-active';
				}
				$output .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a=explore&filter='.$row).'" rel="loadpage">#'.$row.'</a></div>';
				$i++;
			}
			$output .= '</div></div>';
			return $output;
		} else {
			return false;
		}
	}
	
	function sidebarStatsFilters($bold) {
		global $LNG, $CONF;
		
		// Start the output
		$row = array('today', 'last7', 'last30', 'last356', 'total');
		$link = '<div class="sidebar-container widget-filter"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['filter_stats'].'</div>';
		foreach($row as $type) {
			$class = '';
			if($type == $bold || empty($bold) && $type == 'today') {
				$class = ' sidebar-link-active';
			}
			// Output the links
			
			$link .= '<div class="sidebar-link'.$class.'"><a href="'.permalink($this->url.'/index.php?a='.$_GET['a'].((isset($_GET['id'])) ? '&id='.$_GET['id'].'&type=stats' : '').'&filter='.$type).'" rel="loadpage">'.$LNG["stats_{$type}"].'</a></div>';
		}
		$link .= '</div></div>';
		return $link;
	}
	
	function getUserStats($filter, $type, $limit = null) {
		// Filter for statistics (today, week, month, etc)
		// Type 0: Return results for COUNT statistics
		// Type 1: Return results for most plays
		$days = intval(str_replace(array('last', 'today', 'total'), array('', '0', '9999'), $filter));
		
		// Check whether the filter value is valid or not
		if(!in_array($days, array(0, 7, 30, 356, 9999))) {
			$days = 0;
		}
		
		// Set a negative integer to bypass the empty IN () error
		$trackList = ($this->trackList ? $this->trackList : -1);
		
		if($type) {
			$plays = $this->db->query(sprintf("SELECT `views`.`track`,`tracks`.`title`,`tracks`.`art`, COUNT(`by`) as `count` FROM `views`,`tracks` WHERE `views`.`track` IN (%s) AND `views`.`track` = `tracks`.`id` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`views`.`time`) GROUP BY `views`.`track`,`tracks`.`title`,`tracks`.`art` ORDER BY `count` DESC LIMIT %s", $trackList, $days, $limit));
			
			$likes = $this->db->query(sprintf("SELECT `likes`.`track`,`tracks`.`title`,`tracks`.`art`, COUNT(`by`) as `count` FROM `likes`,`tracks` WHERE `likes`.`track` IN (%s) AND `likes`.`track` = `tracks`.`id` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`likes`.`time`) GROUP BY `likes`.`track`,`tracks`.`title`,`tracks`.`art` ORDER BY `count` DESC LIMIT %s", $trackList, $days, $limit));
			
			$comments = $this->db->query(sprintf("SELECT `comments`.`tid`,`tracks`.`title`,`tracks`.`art`, COUNT(`comments`.`id`) as `count` FROM `comments`,`tracks` WHERE `comments`.`tid` IN (%s) AND `comments`.`tid` = `tracks`.`id` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`comments`.`time`) GROUP BY `comments`.`tid`,`tracks`.`title`,`tracks`.`art` ORDER BY `count` DESC LIMIT %s", $trackList, $days, $limit));
			
			$played = $this->db->query(sprintf("SELECT `views`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image`, COUNT(`by`) as `count` FROM `views`,`users` WHERE `views`.`track` IN (%s) AND `views`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `views`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image` ORDER BY `count` DESC LIMIT 10", $trackList, $days, $limit));
			
			$downloaded = $this->db->query(sprintf("SELECT `downloads`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image`, COUNT(`by`) as `count` FROM `downloads`,`users` WHERE `downloads`.`track` IN (%s) AND `downloads`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `downloads`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image` ORDER BY `count` DESC LIMIT 10", $trackList, $days, $limit));
			
			$countries = $this->db->query(sprintf("SELECT `users`.`country`, COUNT(`country`) AS `count` FROM `views`,`users` WHERE `views`.`track` IN (%s) AND `users`.`country` != '' AND `views`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `users`.`country` ORDER BY `count` DESC LIMIT %s", $trackList, $days, $limit));
			
			$cities = $this->db->query(sprintf("SELECT `users`.`city`, COUNT(`city`) AS `count` FROM `views`,`users` WHERE `views`.`track` IN (%s) AND `users`.`city` != '' AND `views`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `users`.`city` ORDER BY `count` DESC LIMIT %s", $trackList, $days, $limit));
		} else {
			$query = $this->db->query(sprintf("SELECT(SELECT COUNT(track) FROM `views` WHERE `track` IN (%s) AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as plays, (SELECT COUNT(track) FROM `downloads` WHERE `track` IN (%s) AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as downloads, (SELECT COUNT(track) FROM `likes` WHERE `track` IN (%s) AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as likes, (SELECT COUNT(tid) FROM `comments` WHERE `tid` IN (%s) AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as comments", $trackList, $days, $trackList, $days, $trackList, $days, $trackList, $days));
		}
		
		if($type) {
		    $x = $y = $z = '';
			while($row = $plays->fetch_assoc()) {
				$x .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['track'].'&name='.cleanUrl($row['title'])).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=m&w=50&h=50&src='.$row['art']).'">'.$row['title'].'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
			}
			while($row = $likes->fetch_assoc()) {
				$y .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['track'].'&name='.cleanUrl($row['title'])).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=m&w=50&h=50&src='.$row['art']).'">'.$row['title'].'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
			}
			while($row = $comments->fetch_assoc()) {
				$z .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['tid'].'&name='.cleanUrl($row['title'])).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=m&w=50&h=50&src='.$row['art']).'">'.$row['title'].'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
			}
			
			$rows['plays'] = $x;
			$rows['likes'] = $y;
			$rows['comments'] = $z;
			
			if($this->getProStatus($this->id, 1)) {
			    $a = $b = $c = $d = '';
				while($row = $played->fetch_assoc()) {
					$a .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'">'.realName($row['username'], $row['first_name'], $row['last_name']).'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
				}
				while($row = $downloaded->fetch_assoc()) {
					$d .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'">'.realName($row['username'], $row['first_name'], $row['last_name']).'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
				}
				$i = 1;
				while($row = $countries->fetch_assoc()) {
					$b .= '<div class="user-stats-row"><div class="user-stats-title"><span class="user-stats-row-count">'.$i.'</span>'.$row['country'].'</div><div class="user-stats-count">'.$row['count'].'</div></div>';
					$i++;
				}
				$i = 1;
				while($row = $cities->fetch_assoc()) {
					$c .= '<div class="user-stats-row"><div class="user-stats-title"><span class="user-stats-row-count">'.$i.'</span>'.$row['city'].'</div><div class="user-stats-count">'.$row['count'].'</div></div>';
					$i++;
				}
				
				$rows['played'] = $a;
				$rows['countries'] = $b;
				$rows['cities'] = $c;
				$rows['downloaded'] = $d;
			} else {
				$rows['gopro'] = $this->goProMessage(0);
			}
			
			return $rows;
		} else {
			return $query->fetch_assoc();
		}
	}
	
	function getTrackStats($id, $filter, $type, $limit = null) {
		// Filter for statistics (today, week, month, etc)
		// Type 0: Return results for COUNT statistics
		// Type 1: Return results for most plays
		$days = intval(str_replace(array('last', 'today', 'total'), array('', '0', '9999'), $filter));
		
		// Check whether the filter value is valid or not
		if(!in_array($days, array(0, 7, 30, 356, 9999))) {
			$days = 0;
		}
		
		if($type) {
			$played = $this->db->query(sprintf("SELECT `views`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image`, COUNT(`by`) as `count` FROM `views`,`users` WHERE `views`.`track` = '%s' AND `views`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `views`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image` ORDER BY `count` DESC LIMIT 10", $this->db->real_escape_string($id), $days, $limit));
			
			$downloaded = $this->db->query(sprintf("SELECT `downloads`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image`, COUNT(`by`) as `count` FROM `downloads`,`users` WHERE `downloads`.`track` = '%s' AND `downloads`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `downloads`.`by`,`users`.`idu`,`users`.`username`,`users`.`first_name`,`users`.`last_name`,`users`.`image` ORDER BY `count` DESC LIMIT 10", $this->db->real_escape_string($id), $days, $limit));
		
			$countries = $this->db->query(sprintf("SELECT `users`.`country`, COUNT(`country`) AS `count` FROM `views`,`users` WHERE `views`.`track` = '%s' AND `users`.`country` != '' AND `views`.`by` = `users`.`idu` AND 	DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `users`.`country` ORDER BY `count` DESC LIMIT %s", $this->db->real_escape_string($id), $days, $limit));
		
			$cities = $this->db->query(sprintf("SELECT `users`.`city`, COUNT(`city`) AS `count` FROM `views`,`users` WHERE `views`.`track` = '%s' AND `users`.`city` != '' AND `views`.`by` = `users`.`idu` AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`) GROUP BY `users`.`city` ORDER BY `count` DESC LIMIT %s", $this->db->real_escape_string($id), $days, $limit));
		} else {
			$query = $this->db->query(sprintf("SELECT(SELECT COUNT(track) FROM `views` WHERE `track` = '%s' AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as plays, (SELECT COUNT(track) FROM `downloads` WHERE `track` = '%s' AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as downloads, (SELECT COUNT(track) FROM `likes` WHERE `track` = '%s' AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as likes, (SELECT COUNT(tid) FROM `comments` WHERE `tid` = '%s' AND DATE_SUB(CURDATE(),INTERVAL %s DAY) <= date(`time`)) as comments", $this->db->real_escape_string($id), $days, $this->db->real_escape_string($id), $days, $this->db->real_escape_string($id), $days, $this->db->real_escape_string($id), $days));
		}
		
		if($type) {
		    $x = $y = $z = $a = '';
			if($this->getProStatus($this->id, 1)) {
				while($row = $played->fetch_assoc()) {
					$x .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'">'.realName($row['username'], $row['first_name'], $row['last_name']).'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
				}
				$i = 1;
				while($row = $countries->fetch_assoc()) {
					$y .= '<div class="user-stats-row"><div class="user-stats-title"><span class="user-stats-row-count">'.$i.'</span>'.$row['country'].'</div><div class="user-stats-count">'.$row['count'].'</div></div>';
					$i++;
				}
				$i = 1;
				while($row = $cities->fetch_assoc()) {
					$z .= '<div class="user-stats-row"><div class="user-stats-title"><span class="user-stats-row-count">'.$i.'</span>'.$row['city'].'</div><div class="user-stats-count">'.$row['count'].'</div></div>';
					$i++;
				}
				while($row = $downloaded->fetch_assoc()) {
					$a .= '<div class="user-stats-row"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><div class="user-stats-title"><img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'">'.realName($row['username'], $row['first_name'], $row['last_name']).'</div><div class="user-stats-count">'.$row['count'].'</div></a></div>';
				}
			
				$rows['played'] = $x;
				$rows['countries'] = $y;
				$rows['cities'] = $z;
				$rows['downloaded'] = $a;
			} else {
				$rows['gopro'] = $this->goProMessage(0);
			}
			
			return $rows;
		} else {
			return $query->fetch_assoc();
		}
	}
	
	function getLikes($start = null, $type = null, $value = null) {
		// Type 0: Return the likes count
		// Type 1: Return the liked tracks
		// Type 2: Return the likes from tracks
		
		if($type) {
		global $LNG, $CONF;
			if($type == 1) {
				// If the $start value is 0, empty the query;
				if($start == 0) {
					$start = '';
				} else {
					$start = 'AND `likes`.`id` < \''.$this->db->real_escape_string($start).'\'';
				}
				$query = sprintf("SELECT `likes`.`id` as `extra_id`, `likes`.`time` as `time`,
				`tracks`.`id` as `id`, `tracks`.`title` as `title`, `tracks`.`name` as `name`, `tracks`.`art` as `art`, `tracks`.`as3_track`, `tracks`.`public` as `public`, `tracks`.`tag` as `tag`,
				`users`.`idu` as `idu`, `users`.`username` as `username`, `users`.`first_name` as `first_name`, `users`.`last_name` as `last_name`, `users`.`image` as `image` 
				FROM `likes`, `tracks`, `users` WHERE `likes`.`by` = '%s' AND `likes`.`track` = `tracks`.`id` AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`public` = 1 %s ORDER BY `likes`.`time` DESC LIMIT %s", $this->profile_data['idu'], $start, ($this->per_page + 1));
				
				return $this->getTracks($query, 'loadLikes', '\''.$this->profile_data['username'].'\', 1');
			} elseif($type == 2) {
				if($start == 0) {
					$start = '';
				} else {
					// Else, build up the query
					$start = 'AND `likes`.`id` < \''.$this->db->real_escape_string($start).'\'';
				}
				$query = $this->db->query(sprintf("SELECT * FROM `likes`, `users` WHERE `likes`.`track` = '%s' AND `likes`.`by` = `users`.`idu` %s ORDER BY `likes`.`id` DESC LIMIT %s", $this->db->real_escape_string($value), $start, ($this->per_page + 1)));
			
				// Declare the rows array
				$rows = array();
				while($row = $query->fetch_assoc()) {
					// Store the result into the array
					$rows[] = $row;
				}

				$loadmore = $output = '';
				
				// Decide whether the load more will be shown or not
				if(array_key_exists($this->per_page, $rows)) {
					$loadmore = 1;
						
					// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
					array_pop($rows);
				}
				
				foreach($rows as $row) {
				$subscribersList = $this->getSubs($row['idu'], 1, null);
				$tracks = $this->countSongs($row['idu']);
				$fullName = realName(null, $row['first_name'], $row['last_name']);
				$output .= '<div class="list-container">
								<div class="track-inner">
								<div id="subscribe'.$row['idu'].'">'.$this->getSubscribe(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
									<div class="list-avatar" id="avatar'.$row['idu'].'">
										<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">
											<img src="'.permalink($this->url.'/image.php?t=a&w=100&h=100&src='.$row['image']).'">
										</a>
									</div>
									<div class="list-top" id="user'.$row['idu'].'">
										<div class="track-author" id="author'.$row['idu'].'">
											<a onmouseover="profileCard('.$row['idu'].', '.$row['idu'].', 3, 0);" onmouseout="profileCard(0, 0, 0, 1);" onclick="profileCard(0, 0, 1, 1);" href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">'.$row['username'].'</a>'.(($this->getProStatus($row['idu'])) ? '<a href="'.permalink($this->url.'/index.php?a=pro').'" rel="loadpage"  title="'.$LNG['pro_user'].'"><span class="pro-icon pro-small"></span></a>' : '').'
										</div>
										'.((location($row['country'], $row['city']) || !empty($fullName)) ? '<div class="list-time">'.$fullName.''.((location($row['country'], $row['city'])) ? ' ('.location($row['country'], $row['city']).')' : '&nbsp;').'</div>' : '').'
										<div class="sidebar-suggestions-small">'.(($tracks) ? '<div class="sidebar-suggestions-tracks" title="'.$tracks.' '.$LNG['tracks'].'">'.$tracks.'</div>' : '').'
										'.(($subscribersList[1]) ? '<div class="sidebar-suggestions-followers" title="'.$subscribersList[1].' '.$LNG['subscribers'].'">'.$subscribersList[1].'</div>' : '').'</div>
									</div>
								</div>
							</div>';
				$last = $row['id'];
				}
				
				if($loadmore) {
					$output .= '<div id="load-more">
									<div class="load_more"><a onclick="loadLikes('.$last.', \''.$value.'\', \''.$type.'\')" id="infinite-load">'.$LNG['load_more'].'</a></div>
								</div>';
				}
				
				return $output;
			}
		} else {
			$query = $this->db->query(sprintf("SELECT count(`likes`.`id`) FROM `likes`,`tracks` WHERE `likes`.`by` = '%s' AND `likes`.`track` = `tracks`.`id` AND `tracks`.`public` = '1'", $this->profile_data['idu']));
			
			// Store the array results
			$result = $query->fetch_array();
			
			// Return the likes value
			return $result[0];
		}
	}
	
	function getHashtags($value, $limit) {
		global $LNG;
		
		$query = $this->db->query(sprintf("SELECT tracks.tag FROM tracks WHERE tracks.tag LIKE '%s'", '%'.$this->db->real_escape_string($value).'%', $limit));

		// Store the hashtags into a string
        $hashtags = '';
		while($row = $query->fetch_assoc()) {
			$hashtags .= $row['tag'];
		}

		$output = '<div class="search-content"><div class="search-results"><div class="notification-inner"><a onclick="manageResults(2)">'.$LNG['view_all_results'].'</a> <a onclick="manageResults(0)" title="'.$LNG['close_results'].'"><div class="close_btn"></div></a></div>';
		// If there are no results
		if(empty($hashtags)) {
			$output .= '<div class="message-inner">'.$LNG['no_results'].'</div>';
		} else {
			// Explore each hashtag string into an array
			$explode = explode(',', $hashtags);
			
			// Merge all matched arrays into a string
			$rows = array_unique(array_map('mb_strtolower', $explode));
			
			$i = 1;
			foreach($rows as $row) {
				if(stripos($row, $value) !== false) {
					$output .= '<div class="hashtag">
									<a href="'.permalink($this->url.'/index.php?a=explore&filter='.$row).'" rel="loadpage">
										<div class="hashtag-inner">
											#'.$row.'
										</div>
									</a>
								</div>';
					if($i == $limit) break;
					$i++;
				}
			}
		}
		$output .= '</div></div>';
		return $output;
	}
	
	function searchTracks($start, $value) {
		// If the $start value is 0, empty the query;
		if($start == 0) {
			$start = '';
		} else {
			// Else, build up the query
			$start = 'AND tracks.id < \''.$this->db->real_escape_string($start).'\'';
		}
		
		$query = sprintf("SELECT * FROM `tracks`, `users` WHERE `tracks`.`title` LIKE '%s' AND `tracks`.`uid` = `users`.`idu` %s AND `tracks`.`public` = 1 ORDER BY tracks.id DESC LIMIT %s", '%'.$this->db->real_escape_string($value).'%', $start, ($this->per_page + 1));

		return $this->getTracks($query, 'searchTracks', '\''.$value.'\'');
	}
	
	function getSearch($start, $per_page, $value, $filter = null, $type = null) {
		// $type - switches the type for live search or static one [search page]
		global $LNG, $CONF;
		
		// Define the query type
		// Query Type 0: Normal search username, first and last name
		// Query Type 1: Exact Email search
		if(filter_var($value, FILTER_VALIDATE_EMAIL)) {
			$qt = 1;
		} else {
			$qt = 0;
		}

        $rows = [];
		
		if($qt == 1) {
			$query = $this->db->query(sprintf("SELECT `idu`, `username`, `first_name`, `last_name`, `country`, `city`, `image`, 1 as `profile` FROM `users` WHERE `email` = '%s' LIMIT 1", $this->db->real_escape_string($value)));
		} else {
			// If type is set, search for music as well
			if($type) {
                $query = $this->db->query(sprintf("SELECT `idu`, `username`, `first_name`, `last_name`, `country`, `city`, `image`, `private`, 1 as `profile` FROM `users` WHERE (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') ORDER BY `idu` DESC LIMIT %s, %s", '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), $per_page));
				
				$music = $this->db->query(sprintf("SELECT `id`, `title` as `username`, `art` as `image`, 0 as `profile` FROM `tracks` WHERE `title` LIKE '%s' AND `public` = 1 ORDER BY `id` DESC LIMIT %s, %s", '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), $per_page));

				while($row = $music->fetch_assoc()) {
					$rows[] = $row;
				}
			} else {
                $query = $this->db->query(sprintf("SELECT `idu`, `username`, `first_name`, `last_name`, `country`, `city`, `image`, `private`, 1 as `profile` FROM `users` WHERE (`username` LIKE '%s' OR concat_ws(' ', `first_name`, `last_name`) LIKE '%s') ORDER BY `idu` DESC LIMIT %s, %s", '%'.$this->db->real_escape_string($value).'%', '%'.$this->db->real_escape_string($value).'%', $this->db->real_escape_string($start), $per_page));
			}
		}

		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}

		$output = $loadmore = '';
		
		// If the query type is live, hide the load more button
		if(array_key_exists($per_page, $rows)) {
			$loadmore = 1;
			if($type) {
				$loadmore = 0;
			} else {
				// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
				array_pop($rows);
			}
		}
	
		// If the query type is live show the proper style
		if($type) {
			$output = '<div class="search-content"><div class="search-results"><div class="notification-inner"><a onclick="manageResults(1)">'.$LNG['view_all_results'].'</a> <a onclick="manageResults(0)" title="'.$LNG['close_results'].'"><div class="close_btn"></div></a></div>';
			// If there are no results
			if(empty($rows)) {
				$output .= '<div class="track-inner">'.$LNG['no_results'].'</div>';
			} else {
				foreach($rows as $row) {
					// Verify whether the result is for a profile or not
					if($row['profile']) {
						$url = '<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">';
						$image = '<img src="'.permalink($this->url.'/image.php?t=a&w=50&h=50&src='.$row['image']).'">';
						$kind = '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/profile.png" title="'.$LNG['title_profile'].'">';
						$x = 1;
					} else {
						$url = '<a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['username'])).'" rel="loadpage">';
						$image = '<img src="'.permalink($this->url.'/image.php?t=m&w=50&h=50&src='.$row['image']).'">';
						$kind = '<img src="'.$this->url.'/'.$CONF['theme_url'].'/images/icons/track.png" title="'.$LNG['track'].'">';
						$x = 0;
					}
					$output .= $url.'
									<div class="track-inner">
										<div class="search-image">
											'.$image.'
										</div>
										<div class="search-text">
											<div>
												'.realName($row['username'], $row['first_name'] ?? null, $row['last_name'] ?? null).''.((location($row['country'] ?? null, $row['city'] ?? null)) ? ' ('.location($row['country'] ?? null, $row['city'] ?? null).')' : '&nbsp;').' '.(($this->getProStatus($row['idu'] ?? null) && $x) ? '<span class="pro-icon pro-small" title="'.$LNG['pro_user'].'"></span>' : '').'
											</div>
										</div><div class="search-icons">'.$kind.'</div>
									</div>
								</a>';
				}
			}
			$output .= '</div></div>';
		} else {
			// If there are no results
			if(empty($rows)) {
				$output .= '<div class="message-inner">'.$LNG['no_results'].'</div>';
			} else {
				foreach($rows as $row) {
					$subscribersList = $this->getSubs($row['idu'], 1, null);
					$tracks = $this->countSongs($row['idu']);
					$fullName = realName(null, $row['first_name'], $row['last_name']);

					$output .= '<div class="list-container">
									<div class="list-inner">
										<div id="subscribe'.$row['idu'].'">'.$this->getSubscribe(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
										<div class="list-avatar" id="avatar'.$row['idu'].'">
											<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">
												<img src="'.permalink($this->url.'/image.php?t=a&w=100&h=100&src='.$row['image']).'">
											</a>
										</div>
										<div class="list-top" id="user'.$row['idu'].'">
											<div class="track-author" id="author'.$row['idu'].'">
												<a onmouseover="profileCard('.$row['idu'].', '.$row['idu'].', 3, 0);" onmouseout="profileCard(0, 0, 0, 1);" onclick="profileCard(0, 0, 1, 1);" href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">'.$row['username'].'</a>'.(($this->getProStatus($row['idu'])) ? '<a href="'.permalink($this->url.'/index.php?a=pro').'" rel="loadpage"  title="'.$LNG['pro_user'].'"><span class="pro-icon pro-small"></span></a>' : '').'
											</div>
											'.((location($row['country'], $row['city']) || !empty($fullName)) ? '<div class="list-time">'.$fullName.''.((location($row['country'], $row['city'])) ? ' ('.location($row['country'], $row['city']).')' : '&nbsp;').'</div>' : '').'
											<div class="sidebar-suggestions-small">'.(($tracks) ? '<div class="sidebar-suggestions-tracks" title="'.$tracks.' '.$LNG['tracks'].'">'.$tracks.'</div>' : '').'
											'.(($subscribersList[1]) ? '<div class="sidebar-suggestions-followers" title="'.$subscribersList[1].' '.$LNG['subscribers'].'">'.$subscribersList[1].'</div>' : '').'</div>
										</div>
									</div>
								</div>';
				}
			}
		}
		if($loadmore) {
				$output .= '<div id="load-more">
								<div class="load_more"><a onclick="loadPeople('.($start + $per_page).', \''.$value.'\', \''.$filter.'\')" id="infinite-load">'.$LNG['load_more'].'</a></div>
							</div>';
		}
		
		return $output;
	}
	
	function listSubs($type = null) {
		global $LNG, $CONF;
		$rows = $this->subsList[0];

		$output = $loadmore = '';

		if(array_key_exists($this->s_per_page, $rows)) {
			$loadmore = 1;
			
			// Unset the last array element because it's not needed, it's used only to predict if the Load More Messages should be displayed
			array_pop($rows);
		}
		
		foreach($rows as $row) {
			$subscribersList = $this->getSubs($row['idu'], 1, null);
			$tracks = $this->countSongs($row['idu']);
			$fullName = realName(null, $row['first_name'], $row['last_name']);
			$output .= '<div class="list-container">
							<div class="track-inner">
							<div id="subscribe'.$row['idu'].'">'.$this->getSubscribe(0, array('idu' => $row['idu'], 'username' => $row['username'], 'private' => $row['private']), 1).'</div>'.$this->chatButton($row['idu'], $row['username'], 1).'
								<div class="list-avatar" id="avatar'.$row['idu'].'">
									<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">
										<img src="'.permalink($this->url.'/image.php?t=a&w=100&h=100&src='.$row['image']).'">
									</a>
								</div>
								<div class="list-top" id="user'.$row['idu'].'">
									<div class="track-author" id="author'.$row['idu'].'">
										<a onmouseover="profileCard('.$row['idu'].', '.$row['idu'].', 3, 0);" onmouseout="profileCard(0, 0, 0, 1);" onclick="profileCard(0, 0, 1, 1);" href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage">'.$row['username'].'</a>'.(($this->getProStatus($row['idu'])) ? '<a href="'.permalink($this->url.'/index.php?a=pro').'" rel="loadpage"  title="'.$LNG['pro_user'].'"><span class="pro-icon pro-small"></span></a>' : '').'
									</div>
									'.((location($row['country'], $row['city']) || !empty($fullName)) ? '<div class="list-time">'.$fullName.''.((location($row['country'], $row['city'])) ? ' ('.location($row['country'], $row['city']).')' : '&nbsp;').'</div>' : '').'
									<div class="sidebar-suggestions-small">'.(($tracks) ? '<div class="sidebar-suggestions-tracks" title="'.$tracks.' '.$LNG['tracks'].'">'.$tracks.'</div>' : '').'
									'.(($subscribersList[1]) ? '<div class="sidebar-suggestions-followers" title="'.$subscribersList[1].' '.$LNG['subscribers'].'">'.$subscribersList[1].'</div>' : '').'</div>
								</div>
							</div>
						</div>';
			$last = $row['id'];
		}
		if($loadmore) {
				$output .= '<div id="load-more">
								<div class="load_more"><a onclick="loadSubs('.$last.', '.$type.', '.$this->profile_data['idu'].')" id="infinite-load">'.$LNG['load_more'].'</a></div>
							</div>';
		}
		return $output;
	}
	
	function getSubs($id, $type, $start = null) {
		// Type: 0 Get the subscriptions
		// Type: 1 Get the subscribers
        $limit = '';
		if($type == 0) {
			// If the $start it set (used to list the users on dedicated profile pages)
			if(is_numeric($start)) {
				if($start == 0) {
					$start = '';
				} else {
					$start = 'AND `relations`.`id` < \''.$this->db->real_escape_string($start).'\'';
				}
				$limit = 'LIMIT '.($this->s_per_page + 1);
			}
			$query = sprintf("SELECT * FROM `relations`, `users` WHERE `relations`.`subscriber` = '%s' AND `relations`.`leader` = `users`.`idu` $start ORDER BY `relations`.`id` DESC $limit", $this->db->real_escape_string($id));
		} else {
			if(is_numeric($start)) {
				if($start == 0) {
					$start = '';
				} else {
					$start = 'AND `relations`.`id` < \''.$this->db->real_escape_string($start).'\'';
				}
				$limit = 'LIMIT '.($this->s_per_page + 1);
			}
			$query = sprintf("SELECT * FROM `relations`, `users` WHERE `relations`.`leader` = '%s' AND `relations`.`subscriber` = `users`.`idu` $start ORDER BY `relations`.`id` DESC $limit", $this->db->real_escape_string($id));
		}
		
		$result = $this->db->query($query);
		$array = [];
		while($row = $result->fetch_assoc()) {
			$array [] = $row;
		}
		return array($array, $total = $result->num_rows);
	}
	
	function getActions($id, $type = null, $url = null) {
		global $LNG;

        $download = $buy = $likes = $people = '';

		// If type 1 do the like
		if($type == 1) {
			// Verify the Like state
			$verify = $this->verifyLike($id);
			
			// Verify if track exists
			$result = $this->db->query(sprintf("SELECT * FROM `tracks`, `users` WHERE `id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($id)));
			if($result->num_rows == 0) {
				return $LNG['like_track_not_exist'];
			}
			if(!$verify) {
				// Prepare the INSERT statement
				$stmt = $this->db->prepare("INSERT INTO `likes` (`track`, `by`) VALUES ('{$this->db->real_escape_string($id)}', '{$this->db->real_escape_string($this->id)}')");

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;

				// Close the statement
				$stmt->close();
				if($affected) {
					$this->db->query("UPDATE `tracks` SET `likes` = `likes` + 1, `time` = `time` WHERE id = '{$this->db->real_escape_string($id)}'");
					
					$user = $result->fetch_assoc();
					
					// Do the INSERT notification
					$insertNotification = $this->db->query(sprintf("INSERT INTO `notifications` (`from`, `to`, `parent`, `type`, `read`) VALUES ('%s', '%s', '%s', '2', '0')", $this->db->real_escape_string($this->id), $user['uid'], $user['id']));
					
					// If email on likes is enabled in admin settings
					if($this->email_like) {
						// If user has emails on like enabled and he's not liking his own track
						if($user['email_like'] && ($this->id !== $user['idu'])) {
							// Send e-mail
							sendMail($user['email'], sprintf($LNG['ttl_like_email'], $this->username), sprintf($LNG['like_email'], realName($user['username'], $user['first_name'], $user['last_name']),permalink( $this->url.'/index.php?a=profile&u='.$this->username), $this->username, permalink($this->url.'/index.php?a=track&id='.$id.'&name='.cleanUrl($user['title'])), $this->title, permalink($this->url.'/index.php?a=settings&b=notifications')), $this->email);
						}
					}
				}
			} else {
				$x = 'already_liked';
			}
		} elseif($type == 2) {
			// Verify the Like state
			$verify = $this->verifyLike($id);
			
			// Verify if track exists
			$result = $this->db->query(sprintf("SELECT `id` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id)));
			if($result->num_rows == 0) {
				return $LNG['like_track_not_exist'];
			}
			if($verify) {
				// Prepare the DELETE statement
				$stmt = $this->db->prepare("DELETE FROM `likes` WHERE `track` = '{$this->db->real_escape_string($id)}' AND `by` = '{$this->db->real_escape_string($this->id)}'");

				// Execute the statement
				$stmt->execute();
				
				// Save the affected rows
				$affected = $stmt->affected_rows;

				// Close the statement
				$stmt->close();
				if($affected) {
					$this->db->query("UPDATE `tracks` SET `likes` = `likes` - 1, `time` = `time` WHERE id = '{$this->db->real_escape_string($id)}'");
					$this->db->query("DELETE FROM `notifications` WHERE `parent` = '{$this->db->real_escape_string($id)}' AND `type` = '2' AND `from` = '{$this->db->real_escape_string($this->id)}'");
				}
			} else {
				$x = 'already_disliked';
			}
		}
		
		// Get the likes, views, and other info
		$query = sprintf("SELECT `id`, `uid`, `title`, `name`, `buy`, `download`, `public`, `likes`, `downloads`, `views` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id));
		
		// Run the query
		$result = $this->db->query($query);
		
		// Get the array element for the like
		$get = $result->fetch_assoc();
		
		// Determine whether to show the delete/privacy buttons or not
		if($this->id == $get['uid']) { // If it's current username is the same with the current author
			if($get['public'] == 1) {
				$privacy = '<div class="public-button" onclick="privacy('.$get['id'].', 0, 0)" title="'.$LNG['this_track_public'].'"></div>';
				$delete = '<div id="delete-button-'.$get['id'].'" class="delete-button" onclick="delete_modal('.$get['id'].', 1)" title="'.$LNG['delete'].'"></div>';
			} else {
				$privacy = '<div class="private-button" onclick="privacy('.$get['id'].', 1, 0)" title="'.$LNG['this_track_private'].'"></div>';
				$delete = '<div id="delete-button-'.$get['id'].'" class="delete-button" onclick="delete_modal('.$get['id'].', 1)" title="'.$LNG['delete'].'"></div>';
			}
		} else { // If the current username is not the same as the author
			$privacy = '';
			$delete = '';
		}
		
		// Verify the Like state
		$verify = $this->verifyLike($id);
		
		if($verify) {
			$state = $LNG['dislike'];
			$y = 2;
		} else {
			$state = $LNG['like'];
			$y = 1;
		}
		
		if($this->l_per_post) {
			$query = sprintf("SELECT * FROM `likes`, `users` WHERE `likes`.`track` = '%s' and `likes`.`by` = `users`.`idu` ORDER BY `likes`.`id` DESC LIMIT %s", $this->db->real_escape_string($id), $this->db->real_escape_string($this->l_per_post + 1));

			$array = [];
			$result = $this->db->query($query);
			while($row = $result->fetch_assoc()) {
				$array[] = $row;
			}
			
			$i = 0;
			foreach($array as $row) {
				// If the likes counter hits the l_per_post+1 then show the load more button
				if($i == $this->l_per_post) {
					$likes .= '<a href="'.permalink($this->url.'/index.php?a=track&id='.$id.'&type=likes').'" rel="loadpage"><div class="likes-plus" title="'.$LNG['view_more'].'"></div></a>';
				} else {
					$likes .= '<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" rel="loadpage"><img src="'.permalink($this->url.'/image.php?t=a&w=25&h=25&src='.$row['image']).'" title="'.realName($row['username'], $row['first_name'], $row['last_name']).' '.$LNG['liked_this'].'" /></a> ';
				}
				$i++;
			}
			
			// If any likes are available
			if($i) {
				$people .= '<div class="track-likes" id="users_likes'.$id.'" style="'.((empty($likes)) ? 'margin: 0;' : '').'">'.$likes.'</div>';
			}
		}
		
		// Show the buy button
		if(!empty($get['buy'])) {
			$buy = '<a href="'.$get['buy'].'" target="_blank" rel="nofollow"><div class="buy-button" title="'.$LNG['buy'].'"></div></a>';
		}
		
		// Get the filename extension
		$ext = pathinfo($get['name'], PATHINFO_EXTENSION);
		
		// Show the download button
		if(!empty($get['download'])) {
			$download = '<a href="'.$url.'" target="_blank" download="'.$get['title'].'.'.$ext.'" onclick="addDownload('.$id.');"><div class="download-button" title="'.$LNG['download'].'"></div></a>';
		}

		$getComments = $this->db->query(sprintf("SELECT COUNT(*) FROM `comments` WHERE `tid` = '%s'", $this->db->real_escape_string($id)));
		$comments = $getComments->fetch_row();
		
		$url = permalink($this->url.'/index.php?a=track&id='.$id);
		
		// Actions
		$views_stats = ($get['views']) ? '<a href="'.permalink($this->url.'/index.php?a=track&id='.$id.'&name='.cleanUrl($get['title'])).'" rel="loadpage"><div class="counter views_counter" title="'.sprintf($LNG['listened_x_times'], $get['views']).'">'.$get['views'].'</div></a>' : '';
		$comments_stats = ($comments[0]) ? '<a href="'.permalink($this->url.'/index.php?a=track&id='.$id.'&name='.cleanUrl($get['title'])).'" rel="loadpage"><div class="counter comments_counter" title="'.$comments[0].' '.$LNG['comments'].'">'.$comments[0].'</div></a>' : '';
		$likes_stats = ($get['likes']) ? '<a href="'.permalink($this->url.'/index.php?a=track&id='.$id.'&type=likes').'" rel="loadpage"><div class="counter like_btn" id="like_btn'.$id.'" title="'.$get['likes'].' '.$LNG['likes'].'">'.$get['likes'].'</div></a>' : '';
		$downloads_stats = ($get['downloads']) ? '<a href="'.permalink($this->url.'/index.php?a=track&id='.$id.'&name='.cleanUrl($get['title'])).'" rel="loadpage"><div class="counter downloads_counter" title="'.sprintf($LNG['downloaded_x_times'], $get['downloads']).'">'.$get['downloads'].'</div></a>' : '';
		
		$actions = $people.'<div class="track-buttons-container"><div class="'.(($y == 2) ? 'liked' : 'like').'-button" onclick="doLike('.$id.', '.$y.')" id="doLike'.$id.'" title="'.$state.'"><span class="action-text">'.$state.'</span></div><div class="playlist-button" onclick="playlist('.$id.', 1)" title="'.$LNG['add'].'"><span class="action-text">'.$LNG['add'].'</span></div><div class="share-button" onclick="share('.$id.', 1)" title="'.$LNG['share'].'"><span class="action-text">'.$LNG['share'].'</span></div>'.$buy.$download.$delete.'<span id="privacy'.$get['id'].'">'.$privacy.'</span></div> <div class="track-stats">'.$views_stats.$comments_stats.$likes_stats.$downloads_stats.'</div>';
		
		// If the current user is empty (not logged-in)
		if(empty($this->id)) {
			$actions = $people.'<div class="track-buttons-container"><div class="like-button" onclick="connect_modal()" id="doLike'.$id.'" title="'.$LNG['like'].'"><span class="action-text">'.$LNG['like'].'</span></div><div class="playlist-button" onclick="connect_modal()" title="'.$LNG['add'].'"><span class="action-text">'.$LNG['add'].'</span></div><div class="share-button" onclick="share('.$id.', 1)" title="'.$LNG['share'].'"><span class="action-text">'.$LNG['share'].'</span></div>'.$buy.$download.'</div> <div class="track-stats">'.$views_stats.$comments_stats.$likes_stats.$downloads_stats.'</div>';
		}
		
		// Display an error if the user tries to do the same action twice (e.g: liking the same song from two different pages)
		if(isset($x)) {
			return '<div class="track-buttons-container">'.$LNG["$x"].'</div> <a href="'.$url.'" rel="loadpage"><div class="track-stats">'.$views_stats.$comments_stats.$likes_stats.$downloads_stats.'</div>';
		}
		return $actions;
	}
	
	function verifyLike($id) {
		$result = $this->db->query(sprintf("SELECT * FROM `likes` WHERE `track` = '%s' AND `by` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
	
		// If the Message/Comment exists
		return ($result->num_rows) ? 1 : 0;
	}
	
	function addView($id) {
		// Check if the track exists
		$result = $this->db->query(sprintf("SELECT * FROM `tracks`, `users` WHERE `id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($id)));
		
		// If the result number is true
		if($result->num_rows) {
			
			// Update the track views field
			$this->db->query(sprintf("UPDATE `tracks` SET `time` = `time`, `views` = (`views` + 1) WHERE `id` = '%s'", $this->db->real_escape_string($id)));
			
			// If the user who plays the song is logged in, add the count statistics
			if($this->id) {
				$this->db->query("INSERT INTO `views` (`track`, `by`) VALUES ('{$this->db->real_escape_string($id)}', '{$this->db->real_escape_string($this->id)}')");
			}
		}
	}
	
	function addDownload($id) {
		// Check if the track exists
		$result = $this->db->query(sprintf("SELECT * FROM `tracks`, `users` WHERE `id` = '%s' AND `tracks`.`uid` = `users`.`idu`", $this->db->real_escape_string($id)));
		
		// If the result number is true
		if($result->num_rows) {
			
			// Update the track views field
			$this->db->query(sprintf("UPDATE `tracks` SET `time` = `time`, `downloads` = (`downloads` + 1) WHERE `id` = '%s'", $this->db->real_escape_string($id)));
			
			// If the user who downloads the song is logged in, add the count statistics
			if($this->id) {
				$this->db->query("INSERT INTO `downloads` (`track`, `by`) VALUES ('{$this->db->real_escape_string($id)}', '{$this->db->real_escape_string($this->id)}')");
			}
		}
	}
	
	function getBlocked($id, $type = null) {
		// Type 0: Output the button state
		// Type 1: Block/Unblock a user
		
		$profile = $this->profileData(null, $id);
		
		// If the username does not exist, return nothing
		if(empty($profile)) {
			return false;
		} else {
			// Verify if there is any block issued for this username
			$checkBlocked = $this->db->query(sprintf("SELECT * FROM `blocked` WHERE `uid` = '%s' AND `by` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
	
			// If the Message/Comment exists
			$state = $checkBlocked->num_rows;
			
			// If type 1: Add/Remove
			if($type) {
				// If there is a block issued, remove the block
				if($state) {
					// Remove the block
					$this->db->query(sprintf("DELETE FROM `blocked` WHERE `uid` = '%s' AND `by` = '%s'", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
					
					// Block variable
					$y = 0;
				} else {
					// Insert the block
					$this->db->query(sprintf("INSERT INTO `blocked` (`uid`, `by`) VALUES ('%s', '%s')", $this->db->real_escape_string($id), $this->db->real_escape_string($this->id)));
					
					// Unblock variable
					$y = 1;
				}
				return $this->outputBlocked($id, $profile, $y);
			} else {
				return $this->outputBlocked($id, $profile, $state);
			}
		}
	}
	
	function outputBlocked($id, $profile, $state) {
		global $LNG;
		if($state) {
			$x = '<span class="class="unblock-button""><a onclick="doBlock('.$id.', 1)" title="'.$LNG['unblock'].' '.realName($profile['username'], $profile['first_name'], $profile['last_name']).'">'.$LNG['unblock'].'</a></span>';
		} else {
			$x = '<a onclick="doBlock('.$id.', 1)" title="'.$LNG['block'].' '.realName($profile['username'], $profile['first_name'], $profile['last_name']).'">'.$LNG['block'].'</a>';
		}
		return $x;
	}
}
class player {
	public $db;
	public $url;
	public $l_per_post;
	public $title;
	
	function getEmbed($id) {
		global $LNG, $settings, $s3;
		$query = $this->db->query(sprintf("SELECT * FROM `users`, `tracks` WHERE `tracks`.`id` = '%s' AND `tracks`.`uid` = `users`.`idu` AND `tracks`.`public` = 1 AND `users`.`private` != 1", $this->db->real_escape_string($id)));

		// Set the result into an array
		$rows = array();
		while($row = $query->fetch_assoc()) {
			$rows[] = $row;
		}
		
		if($query->num_rows) {
			foreach($rows as $row) {
				$row['as3_url'] = $url = '';
				if($row['as3_track']) {
					if($settings['as3']) {
						$cmd = $s3->getCommand('GetObject', array(
							'Bucket' => $settings['as3_bucket'],
							'Key'    => 'uploads/tracks/'.$row['name']
						));

						$req = $s3->createPresignedRequest($cmd, '1 week');
						$url = (string)$req->getUri();
					}
					$row['as3_url'] = $url;
				}
				
				$tags = explode(',', $row['tag']);
				$row['tag'] = $tags[0];
				$track = '<div id="track'.$row['id'].'" class="embed-container">
					<div class="song-art"><a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=m&w=112&h=112&src='.$row['art']).'" id="song-art'.$row['id'].'" /></a></div>
					<div class="song-top">
						<div class="embed-powered-by">
							<a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" id="song-url'.$row['id'].'" target="_blank">'.$this->title.'</a>
						</div>
						<div data-track-name="'.$row['name'].'" data-track-id="'.$row['id'].'" id="play'.$row['id'].'" data-track-url="'.($row['as3_url'] ? $row['as3_url'] : $this->url.'/uploads/tracks/'.$row['name']).'" data-track-format="'.strtolower(pathinfo($row['name'], PATHINFO_EXTENSION)).'" class="track song-play-btn">
							</div>
						<div class="song-titles">
							<div class="song-author"><a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank">'.realName($row['username'], $row['first_name'], $row['last_name']).'</a></div>
							<div class="song-tag">
								<a href="'.permalink($this->url.'/index.php?a=explore&filter='.$row['tag']).'" target="_blank">'.$row['tag'].'</a>
							</div>
							<div class="song-title">
								<a href="'.permalink($this->url.'/index.php?a=track&id='.$row['id'].'&name='.cleanUrl($row['title'])).'" id="song-url'.$row['id'].'" target="_blank"><div id="song-name'.$row['id'].'">'.$row['title'].'</div></a>
							</div>
						</div>
					</div>
					<div class="player-controls">
						<div id="song-controls'.$row['id'].'">
							<div id="jp_container_123" class="jp-audio">
								<div class="jp-type-single">
										<div class="jp-gui jp-interface">
											<div class="jp-progress">
												<div class="jp-seek-bar">
												<div class="jp-play-bar"></div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="track-actions-container">
						<div class="track-actions"><div class="track-actions-content" id="track-action'.$row['id'].'">'.$this->embedStats($row['id']).'</div></div>
					</div>
				</div>';
			}
		} else {
			return '<div style="width: 100%; background: url('.$this->url.'/uploads/media/default.png) top center no-repeat #b6b6b6; height: 140px;" class="embed-error"><div class="embed-error-title"><a href="'.$this->url.'" target="_blank">'.$this->title.'</a></div><div class="embed-error-desc"><a href="'.$this->url.'" target="_blank">'.$LNG['embed_error'].'</a></div></div>';
		}
		return $track;
	}
	
	function embedStats($id) {
		global $LNG;
		
		// Get the likes, views, and other info
		$query = sprintf("SELECT `id`,`uid`, `buy`, `public`, `likes`, `downloads`, `views` FROM `tracks` WHERE `id` = '%s'", $this->db->real_escape_string($id));
		
		// Run the query
		$result = $this->db->query($query);
		
		// Get the array element for the like
		$get = $result->fetch_assoc();
		
		if($this->l_per_post) {
			$query = sprintf("SELECT * FROM `likes`,`users` WHERE `likes`.`track` = '%s' and `likes`.`by` = `users`.`idu` ORDER BY `likes`.`id` DESC LIMIT %s", $this->db->real_escape_string($id), $this->db->real_escape_string($this->l_per_post));
		
			$result = $this->db->query($query);
			$array = [];
			while($row = $result->fetch_assoc()) {
				$array[] = $row;
			}
			
			// Define the $people who liked variable
			$people = '';
			foreach($array as $row) {
				$people .= '<a href="'.permalink($this->url.'/index.php?a=profile&u='.$row['username']).'" target="_blank"><img src="'.permalink($this->url.'/image.php?t=a&w=25&h=25&src='.$row['image']).'" title="'.realName($row['username'], $row['first_name'], $row['last_name']).' '.$LNG['liked_this'].'" /></a> ';
			}
		}

		$getComments = $this->db->query(sprintf("SELECT COUNT(*) FROM `comments` WHERE `tid` = '%s'", $this->db->real_escape_string($id)));
		$comments = $getComments->fetch_row();
		
		$url = permalink($this->url.'/index.php?a=track&id='.$id);
		
		// Actions
		$views_stats = ($get['views']) ? '<div class="counter views_counter" title="'.sprintf($LNG['listened_x_times'], $get['views']).'">'.$get['views'].'</div>' : '';
		$comments_stats = ($comments[0]) ? '<div class="counter comments_counter" title="'.$comments[0].' '.$LNG['comments'].'">'.$comments[0].'</div>' : '';
		$likes_stats = ($get['likes']) ? '<div class="counter like_btn" id="like_btn'.$id.'" title="'.$get['likes'].' '.$LNG['likes'].'">'.$get['likes'].'</div>' : '';
		$downloads_stats = ($get['downloads']) ? '<div class="counter downloads_counter" title="'.sprintf($LNG['downloaded_x_times'], $get['downloads']).'">'.$get['downloads'].'</div>' : '';
		
		// Output variable
		$actions = '<div class="track-likes" id="users_likes'.$id.'" style="'.((empty($people)) ? 'margin: 0;' : '').'">'.$people.'<a href="'.permalink($this->url.'/index.php?a=track&id='.$id).'" target="_blank"><div class="track-stats">'.$views_stats.$comments_stats.$likes_stats.$downloads_stats.'</div></a></div>';
		
		return $actions;
	}
}
function nl2clean($text) {
	// Replace two or more new lines with two new rows [blank space between them]
	return preg_replace("/(\r?\n){2,}/", "\n\n", $text);
}
function sendMail($to, $subject, $message, $from) {
	// Load up the site settings
	global $settings;

	// If the SMTP emails option is enabled in the Admin Panel
	if($settings['smtp_email']) {
		//Create a new PHPMailer instance
        $mail = new \PHPMailer\PHPMailer\PHPMailer();

		//Tell PHPMailer to use SMTP
		$mail->isSMTP();
		//Enable SMTP debugging
		// 0 = off (for production use)
		// 1 = client messages
		// 2 = client and server messages
		$mail->SMTPDebug = 0;
		//Set the CharSet encoding
		$mail->CharSet = 'UTF-8';
		//Ask for HTML-friendly debug output
		$mail->Debugoutput = 'html';
		//Set the hostname of the mail server
		$mail->Host = $settings['smtp_host'];
		//Set the SMTP port number - likely to be 25, 465 or 587
		$mail->Port = $settings['smtp_port'];
		//Whether to use SMTP authentication
		$mail->SMTPAuth = $settings['smtp_auth'] ? true : false;
		//Username to use for SMTP authentication
		$mail->Username = $settings['smtp_username'];
		//Password to use for SMTP authentication
		$mail->Password = $settings['smtp_password'];
		//Set who the message is to be sent from
		$mail->setFrom($from, $settings['title']);
		//Set an alternative reply-to address
		$mail->addReplyTo($from, $settings['title']);
		if(!empty($settings['smtp_secure'])) {
			$mail->SMTPSecure = $settings['smtp_secure'];
		} else {
			$mail->SMTPSecure = false;
		}
		//Set who the message is to be sent to
		if(is_array($to)) {
			foreach($to as $address) {
				$mail->addBCC($address);
			}
		} else {
			$mail->addAddress($to);
		}
		//Set the subject line
		$mail->Subject = $subject;
		//Read an HTML message body from an external file, convert referenced images to embedded,
		//convert HTML into a basic plain-text alternative body
		$mail->msgHTML($message);

		//send the message, check for errors
		if(!$mail->send()) {
			// Return the error in the Browser's console
			#echo $mail->ErrorInfo;
		}
	} else {
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . PHP_EOL;
		$headers .= 'From: '.$settings['title'].' <'.$from.'>' . PHP_EOL .
			'Reply-To: '.$settings['title'].' <'.$from.'>' . PHP_EOL .
			'X-Mailer: PHP/' . phpversion();
		if(is_array($to)) {
			foreach($to as $address) {
				@mail($address, $subject, $message, $headers);
			}
		} else {
			@mail($to, $subject, $message, $headers);
		}
	}
}
function vl($type)
{
    global $settings;
return strrev("dednetxE");
    if ($type == 0) {
        if (empty($settings[strrev('kl')]) || empty($settings[strrev('tl')])) {
            return false;
        }

        return true;
    } elseif($type == 1) {
        if ($settings[strrev('tl')] == strrev('mNJdHUMd')) {
            return true;
        }
    } elseif($type == 2) {
        if ($settings[strrev('tl')] == strrev('mNJdHUMd')) {
            return strrev("dednetxE");
        } elseif ($settings[strrev('tl')] == strrev('kdPDmZuw')) {
            return strrev("ralugeR");
        }
    }
}
function strip_tags_array($value) {
	return strip_tags($value);
}
function admin_stats($db, $type, $values = null) {
	if($type == 1) {
		$query = sprintf("SELECT
		(SELECT count(idu) FROM `users` WHERE CURDATE() = `date`) as users_today,
		(SELECT count(idu) FROM `users` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = `date`) as users_yesterday,
		(SELECT count(idu) FROM `users` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = `date`) as users_two_days,
		(SELECT count(idu) FROM `users` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = `date`) as users_three_days,
		(SELECT count(idu) FROM `users` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = `date`) as users_four_days,
		(SELECT count(idu) FROM `users` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = `date`) as users_five_days,
		(SELECT count(idu) FROM `users` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = `date`) as users_six_days,
		(SELECT COUNT(id) FROM `playlists` WHERE CURDATE() = date(`time`)) AS playlists_today,
		(SELECT COUNT(id) FROM `playlists` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS playlists_yesterday,
		(SELECT COUNT(id) FROM `playlists` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS playlists_two_days,
		(SELECT COUNT(id) FROM `playlists` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS playlists_three_days,
		(SELECT COUNT(id) FROM `playlists` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS playlists_four_days,
		(SELECT COUNT(id) FROM `playlists` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS playlists_five_days,
		(SELECT COUNT(id) FROM `playlists` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS playlists_six_days,
		(SELECT COUNT(id) FROM `tracks` WHERE CURDATE() = date(`time`)) AS tracks_today,
		(SELECT COUNT(id) FROM `tracks` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS tracks_yesterday,
		(SELECT COUNT(id) FROM `tracks` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS tracks_two_days,
		(SELECT COUNT(id) FROM `tracks` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS tracks_three_days,
		(SELECT COUNT(id) FROM `tracks` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS tracks_four_days,
		(SELECT COUNT(id) FROM `tracks` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS tracks_five_days,
		(SELECT COUNT(id) FROM `tracks` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS tracks_six_days,
		(SELECT COUNT(id) FROM `comments` WHERE CURDATE() = date(`time`)) AS comments_today,
		(SELECT COUNT(id) FROM `comments` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS comments_yesterday,
		(SELECT COUNT(id) FROM `comments` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS comments_two_days,
		(SELECT COUNT(id) FROM `comments` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS comments_three_days,
		(SELECT COUNT(id) FROM `comments` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS comments_four_days,
		(SELECT COUNT(id) FROM `comments` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS comments_five_days,
		(SELECT COUNT(id) FROM `comments` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS comments_six_days,
		(SELECT count(id) FROM `likes` WHERE CURDATE() = date(`time`)) as likes_today,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) as likes_yesterday,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) as likes_two_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) as likes_three_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) as likes_four_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) as likes_five_days,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) as likes_six_days,
		(SELECT COUNT(id) FROM `downloads` WHERE CURDATE() = date(`time`)) AS downloads_today,
		(SELECT COUNT(id) FROM `downloads` WHERE DATE_SUB(CURDATE(), INTERVAL 1 DAY) = date(`time`)) AS downloads_yesterday,
		(SELECT COUNT(id) FROM `downloads` WHERE DATE_SUB(CURDATE(), INTERVAL 2 DAY) = date(`time`)) AS downloads_two_days,
		(SELECT COUNT(id) FROM `downloads` WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) = date(`time`)) AS downloads_three_days,
		(SELECT COUNT(id) FROM `downloads` WHERE DATE_SUB(CURDATE(), INTERVAL 4 DAY) = date(`time`)) AS downloads_four_days,
		(SELECT COUNT(id) FROM `downloads` WHERE DATE_SUB(CURDATE(), INTERVAL 5 DAY) = date(`time`)) AS downloads_five_days,
		(SELECT COUNT(id) FROM `downloads` WHERE DATE_SUB(CURDATE(), INTERVAL 6 DAY) = date(`time`)) AS downloads_six_days,
		(SELECT count(idu) FROM `users` WHERE `online` > '%s'-'%s') AS online_users", time(), $values['conline']);
	} else {
		$query = sprintf("SELECT 
		(SELECT COUNT(id) FROM tracks) AS tracks_total,
		(SELECT COUNT(id) FROM tracks WHERE public = '1') AS tracks_public,
		(SELECT COUNT(id) FROM tracks WHERE public = '0') as tracks_private,
		(SELECT COUNT(id) FROM comments) as comments_total,
		(SELECT count(idu) FROM users WHERE CURDATE() = `date`) as users_today,
		(SELECT count(idu) FROM users WHERE MONTH(CURDATE()) = MONTH(`date`) AND YEAR(CURDATE()) = YEAR(`date`)) as users_this_month,
		(SELECT count(idu) FROM users WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= `date`) as users_last_30,
		(SELECT count(idu) FROM users) as users_total,
		(SELECT count(id) FROM `reports`) as total_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 0) as pending_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 1) as safe_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 2) as deleted_reports,
		(SELECT count(id) FROM `reports` WHERE `type` = 1) as total_track_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 0 AND `type` = 1) as pending_track_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 1 AND `type` = 1) as safe_track_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 2 AND `state` = 3 AND `type` = 1) as deleted_track_reports,
		(SELECT count(id) FROM `reports` WHERE `type` = 0) as total_comment_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 0 AND `type` = 0) as pending_comment_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 1 AND `type` = 0) as safe_comment_reports,
		(SELECT count(id) FROM `reports` WHERE `state` = 2 AND `type` = 0) as deleted_comment_reports,
		(SELECT count(id) FROM `likes`) as total_likes,
		(SELECT count(id) FROM `likes` WHERE CURDATE() = date(`time`)) as likes_today,
		(SELECT count(id) FROM `likes` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as likes_this_month,
		(SELECT count(id) FROM `likes` WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= date(`time`)) as likes_last_30,
		(SELECT count(id) FROM `views`) as total_plays,
		(SELECT count(id) FROM `views` WHERE CURDATE() = date(`time`)) as plays_today,
		(SELECT count(id) FROM `views` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as plays_this_month,
		(SELECT count(id) FROM `views` WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= date(`time`)) as plays_last_30,
		(SELECT count(id) FROM `downloads`) as total_downloads,
		(SELECT count(id) FROM `downloads` WHERE CURDATE() = date(`time`)) as downloads_today,
		(SELECT count(id) FROM `downloads` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as downloads_this_month,
		(SELECT count(id) FROM `downloads` WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= date(`time`)) as downloads_last_30,
		(SELECT count(id) FROM `playlists`) as total_playlists,
		(SELECT count(id) FROM `playlists` WHERE CURDATE() = date(`time`)) as playlists_today,
		(SELECT count(id) FROM `playlists` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as playlists_this_month,
		(SELECT count(id) FROM `playlists` WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= date(`time`)) as playlists_last_30,
		(SELECT count(id) FROM `payments`) as total_payments,
		(SELECT count(id) FROM `payments` WHERE CURDATE() = date(`time`)) as payments_today,
		(SELECT count(id) FROM `payments` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`))) as payments_this_month,
		(SELECT count(id) FROM `payments` WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= date(`time`)) as payments_last_30,
		(SELECT sum(`amount`) FROM `payments` WHERE `status` = 1 AND `currency` = '%s') as total_earnings,
		(SELECT sum(`amount`) FROM `payments` WHERE CURDATE() = date(`time`) AND `status` = 1 AND `currency` = '%s') as earnings_today,
		(SELECT sum(`amount`) FROM `payments` WHERE MONTH(CURDATE()) = MONTH(date(`time`)) AND YEAR(CURDATE()) = YEAR(date(`time`)) AND `status` = 1 AND `currency` = '%s') as earnings_this_month,
		(SELECT sum(`amount`) FROM `payments` WHERE DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= date(`time`) AND `status` = 1 AND `currency` = '%s') as earnings_last_30", $db->real_escape_string($values['currency']), $db->real_escape_string($values['currency']), $db->real_escape_string($values['currency']), $db->real_escape_string($values['currency']));
	}
	$result = $db->query($query);
	while($row = $result->fetch_assoc()) {
		$rows[] = $row;
	}
	$stats = array();
	foreach($rows[0] as $value) {
		$stats[] = ($value) ? $value : 0;
	}
	return $stats;
}
function percentage($current, $old) {
    // Prevent dividing by zero
    if($old != 0) {
        $result = number_format((($current - $old) / $old * 100), 0);
    } else {
        $result = 0;
    }
	if($result < 0) {
		return '<span class="negative">'.$result.'%</span>';
	} elseif($result > 0) {
		return '<span class="positive">+'.$result.'%</span>';
	} else {
		return '<span class="neutral">'.$result.'%</span>';
	}
}
function fsize($bytes) { #Determine the size of the file, and print a human readable value
   $bytes = empty($bytes) ? 0 : $bytes;
   if ($bytes < 1024) return ($bytes < 0) ? 0 : $bytes.' B';
   elseif ($bytes < 1048576) return round($bytes / 1024, 2).' KB';
   elseif ($bytes < 1073741824) return round($bytes / 1048576, 2).' MB';
   elseif ($bytes < 1099511627776) return round($bytes / 1073741824, 2).' GB';
   else return round($bytes / 1099511627776, 2).' TB';
}
function realName($username, $first = null, $last = null, $fullname = null) {
	if($fullname) {
		if($first && $last) {
			return $first.' '.$last;
		} else {
			return $username;
		}
	}
	if($first && $last) {
		return $first.' '.$last;
	} elseif($first) {
		return $first;
	} elseif($last) {
		return $last;
	} elseif($username) { // If username is not set, return empty (example: the real-name under the subscriptions)
		return $username;
	}
}
function location($country, $city) {
	if($country && $city) {
		return $city.', '.$country;
	} elseif($country) {
		return $country;
	} elseif($city) {
		return $city;
	}
}
function welcomeCategories($rows, $url) {
    $x = '';
	foreach($rows as $row) {
		$x .= '<a href="'.permalink($url.'/index.php?a=explore&filter='.mb_strtolower($row['name'])).'" title="'.$row['name'].'" rel="loadpage">'.$row['name'].'</a>';
	}
	return $x;
}
function parseCallback($matches) {
	// If match www. at the beginning, at http before, to be html valid
	if(substr($matches[1], 0, 4) == 'www.') {
		$url = 'http://'.$matches[1];
	} else {
		$url = $matches[1];
	}
	return '<a href="'.$url.'" target="_blank" rel="nofollow">'.$matches[1].'</a>';
}
function pageHeader($title) {
	return '<div class="page-header page-header-extra">'.htmlspecialchars($title).'</div>';
}
function generateTimezoneForm($current) {
    global $LNG;
    $rows = '<option value="" '.($current == '' ? ' selected' : '').'>'.$LNG['default'].'</option>';
    foreach(timezone_identifiers_list() as $value) {
        $rows .= '<option value="'.htmlspecialchars($value).'" '.($current == $value ? ' selected' : '').'>'.$value.'</option>';
    }

    return $rows;
}
function generateDateForm($type, $current) {
	global $LNG;
	$rows = '';
	if($type == 0) {
		$rows .= '<option value="">'.$LNG['year'].'</option>';
		for ($i = date('Y')+10; $i >= (date('Y')+10 - 110); $i--) {
			if($i == $current) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$rows .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
		}
	} elseif($type == 1) {
		$rows .= '<option value="">'.$LNG['month'].'</option>';
		for ($i = 1; $i <= 12; $i++) {
			if($i == $current) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$rows .= '<option value="'.$i.'"'.$selected.'>'.$LNG["month_$i"].'</option>';
		}
	} elseif($type == 2) {
		$rows .= '<option value="">'.$LNG['day'].'</option>';
		for ($i = 1; $i <= 31; $i++) {
			if($i == $current) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			$rows .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
		}
	}
	return $rows;
}
function generateAd($content) {
	global $LNG;
	if(empty($content)) {
		return false;
	}
	$ad = '<div class="sidebar-container widget-ad-unit"><div class="sidebar-content"><div class="sidebar-header">'.$LNG['sponsored'].'</div>'.$content.'</div></div>';
	return $ad;
}
function sortDateDesc($a, $b) {
	// Convert the array value into a UNIX timestamp
	strtotime($a['time']);
	strtotime($b['time']);
	
	return strcmp($a['time'], $b['time']);
}
function sortDateAsc($a, $b) {
	// Convert the array value into a UNIX timestamp
	strtotime($a['time']); 
	strtotime($b['time']);
	
	if ($a['time'] == $b['time']) {
		return 0;
	}
	return ($a['time'] > $b['time']) ? -1 : 1;  
}
function sortOnlineUsers($a, $b) {
	// Convert the array value into a UNIX timestamp
	strtotime($a['online']); 
	strtotime($b['online']);
	
	if ($a['online'] == $b['online']) {
		return 0;
	}
	return ($a['online'] > $b['online']) ? -1 : 1;  
}
function getLanguage($url, $ln = null, $type = null) {
	global $settings;
	// Type 1: Output the available languages
	
	// Define the languages folder
	$lang_folder = __DIR__ .'/../languages/';
	
	// Open the languages folder
	if($handle = opendir($lang_folder)) {
		// Read the files (this is the correct way of reading the folder)
		while(false !== ($entry = readdir($handle))) {
			// Excluse the . and .. paths and select only .php files
			if($entry != '.' && $entry != '..' && substr($entry, -4, 4) == '.php') {
				$name = pathinfo($entry);
				$languages[] = $name['filename'];
			}
		}
		closedir($handle);
	}
	
	if($type == 1) {
		// Add to array the available languages
        $available = '';
		foreach($languages as $lang) {
			// The path to be parsed
			$path = pathinfo($lang);
			
			// Add the filename into $available array
			$available .= '<span><a href="'.$url.'/index.php?lang='.$path['filename'].'">'.ucfirst(mb_strtolower($path['filename'])).'</a></span>';
		}
		return $available;
	} else {
		// If get is set, set the cookie and stuff
		$lang = $settings['language']; // Default Language
		
		if(isset($_GET['lang'])) {
			if(in_array($_GET['lang'], $languages)) {
				$lang = $_GET['lang'];
				setcookie('lang', $lang, time() + (10 * 365 * 24 * 60 * 60), COOKIE_PATH); // Expire in one month
			} else {
				setcookie('lang', $lang, time() + (10 * 365 * 24 * 60 * 60), COOKIE_PATH); // Expire in one month
			}
		} elseif(isset($_COOKIE['lang'])) {
			if(in_array($_COOKIE['lang'], $languages)) {
				$lang = $_COOKIE['lang'];
			}
		} else {
			setcookie('lang', $lang, time() + (10 * 365 * 24 * 60 * 60), COOKIE_PATH); // Expire in one month
		}

		// If the language file doens't exist, fall back to an existent language file
		if(!file_exists($lang_folder.$lang.'.php')) {
			$lang = $languages[0];
		}
		return $lang_folder.$lang.'.php';
	}
}
function saniscape($value) {
	return htmlspecialchars(addslashes($value), ENT_QUOTES, 'UTF-8');
}
function generateToken($type = null) {
	if($type) {
		return '<input type="hidden" name="token_id" value="'.$_SESSION['token_id'].'">';
	} else {
		if(!isset($_SESSION['token_id'])) {
			$token_id = md5(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10));
			$_SESSION['token_id'] = $token_id;
			return $_SESSION['token_id'];
		}
		return $_SESSION['token_id'];
	}
}
function getUserIp() {
	if($_SERVER['REMOTE_ADDR']) {
		return $_SERVER['REMOTE_ADDR'];
	} else {
		return false;
	}
}
function validateSession($name, $time) {
	// Name holds the session name
	// Time holds the session time difference
	
	// If the session is set
	if(!empty($_SESSION[$name])) {

		// Compare the session time since last request
		if((time()-$_SESSION[$name]) > $time) {
			// If the time difference is meet, make the view and regenerate the session time
			$_SESSION[$name] = time();
			return 1;
		} else {
			return 0;
		}
	// If the session is not set, then generate a new one
	} else {
		$_SESSION[$name] = time();
		return 1;
	}
}
function validateFile($path, $name, $allowed, $type) {
	// Type 0: Image
	// Type 1: Audio
	$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	
	if(!$type) {
        if(empty($path)) {
            return false;
        }
		$image = getimagesize($path);
		$output['width'] = $image[0];
		$output['height'] = $image[1];
		$output['mime'] = str_replace('image/', '', $image['mime']);
		
		// Verify if the mime type and extensions are allowed
		if(in_array($output['mime'], $allowed) && in_array($ext, $allowed)) {
			$output['valid'] = 1;
		} else {
			$output['valid'] = 0;
		}
	} else {
		// If the mime_content_type function exist and the mp3 file is valid
		if(function_exists('mime_content_type')) {
			// Read the mime type
			$mime = mime_content_type($path);
			if($mime == 'audio/mpeg' || $mime == 'audio/mp4' || $mime == 'application/octet-stream' || $mime == 'audio/x-m4a' || $mime = 'audio/x-wav') {
				$mime = 1;
			} else {
				$mime = 0;
			}
		} else {
			$mime = 1;
		}
		
		if(in_array($ext, $allowed) && $mime) {
			$output['valid'] = 1;
		} else {
			$output['valid'] = 0;
		}
	}
	return $output;
}
function imageOrientation($filename) {
	if(function_exists('exif_read_data')) {
		// Read the image exif data
		$exif = @exif_read_data($filename);
		
		// Store the image exif orientation data
		$orientation = (isset($exif['Orientation']) ? $exif['Orientation'] : null);
		
		// Check whether the image has an orientation, and if the orientation is 3, 6, 8
		if(!empty($orientation) && in_array($orientation, array(3, 6, 8))) {
			$image = imagecreatefromjpeg($filename);
			if($orientation == 3) {
				$image = imagerotate($image, 180, 0);
			} elseif($orientation == 6) {
				$image = imagerotate($image, -90, 0);
			} elseif($orientation == 8) {
				$image = imagerotate($image, 90, 0);
			}
			
			// Save the new rotated image
			imagejpeg($image, $filename, 90);
		}
	}
}
function deleteMedia($art, $tracks, $type = null) {
	global $settings, $s3;
	// Type 0: If the request is made from another folder
	// Explode the images string value
	$arts = explode(',', $art);

	// Delete each image except default images
	foreach($arts as $art) {
		if($art !== 'default.png' && !empty($art)) {
			unlink(($type ? '' : '.').'./uploads/media/'.$art);
		}
	}
	
	// Delete each song
	foreach($tracks as $track => $as3) {
		if($as3) {
			if($settings['as3']) {
				try {
					$s3->deleteObjects(array(
						'Bucket' => $settings['as3_bucket'],
						'Delete' => array(
						'Objects' => array(
							array('Key' => 'uploads/tracks/'.$track)
						))
					));
				} catch(S3Exception $e) {
					// echo $e;
				}
			}
		} else {
			unlink(($type ? '' : '.').'./uploads/tracks/'.$track);
		}
	}
}
function deleteImages($image, $type) {
	// Type 0: Delete covers
	// Type 1: Delete avatars
	// Type 2: Delete album art
	
	if($type == 1) {
		$path = 'avatars';
	} elseif($type == 2) {
		$path = 'media';
	} else {
		$path = 'covers';
	}
	
	foreach($image as $name) {
		if($name !== 'default.png') {
			unlink(__DIR__ .'/../uploads/'.$path.'/'.$name);
		}
	}
}
function proStatus($db, $settings, $id = null) {
	$query = $db->query(sprintf("SELECT * FROM `payments` WHERE `by` = '%s' ORDER BY `id` DESC LIMIT 0, 1", ($id) ? $id : $this->db->real_escape_string($this->id)));
	$result = $query->fetch_assoc();

	if($settings['paypalapp']) {
		if($result['status'] == 1 && strtotime($result['valid']) >= time()) {
			return 0;
		} else {
			return 1;
		}
	} else {
		// Return false if pro accounts are not enabled
		return 0;
	}
}
function emulatePayment($db, $settings, $user) {
	$info = 'promoted';
	$date = date("Y-m-d H:m:s", strtotime("+1 year"));
	$db->query(sprintf("INSERT INTO `payments`
	(`by`, `payer_id`, `payer_first_name`, `payer_last_name`, `payer_email`, `payer_country`, `txn_id`, `amount`, `currency`, `type`, `status`, `valid`, `time`) VALUES 
	('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s','%s', '%s', '%s', '%s')",
	$db->real_escape_string($user['idu']), $info, $info, $info, $info, $info, $info, 0, $db->real_escape_string($settings['currency']), 1, 1, $date, date("Y-m-d H:m:s")));
}
function paymentStatus($status) {
	global $LNG;
	if($status == 1) {
		$status = $LNG['completed'];
	} elseif($status == 2) {
		$status = $LNG['reversed'];
	} elseif($status == 3) {
		$status = $LNG['refunded'];
	} elseif($status == 4) {
		$status = $LNG['pending'];
	} elseif($status == 5) {
		$status = $LNG['failed'];
	} elseif($status == 6) {
		$status = $LNG['denied'];
	} else {
		$status = $LNG['suspended'];
	}
	return $status;
}
function open_graph() {
	return false;
}
function isAjax() {
	/*
	 * Check if the request is dynamic (ajax)
	 *
	 * @return bolean
	 */
	
	if(	isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
		// || isset($_GET['live'])
		) {
		return true;
	} else {
		return false;
	}
}
/**
 * @return string
 */
function generateSalt($length = 10) {
    $str = '';
    $salt_chars = array_merge(range('A','Z'), range('a','z'), range(0,9));
    for($i=0; $i < $length; $i++) {
        $str .= $salt_chars[array_rand($salt_chars)];
    }
    return password_hash($str.time(), PASSWORD_DEFAULT);
}
function permalink($url) {
	// url: the URL to be rewritten
	global $settings;

	if($settings['permalinks']) {
		$path['profile'] 			= 'index.php?a=profile';
		$path['page'] 				= 'index.php?a=page';
		$path['stream']				= 'index.php?a=stream';
		$path['explore']			= 'index.php?a=explore';
		$path['notifications'] 		= 'index.php?a=notifications';
		$path['settings'] 			= 'index.php?a=settings';
		$path['messages']			= 'index.php?a=messages';
		$path['pro']				= 'index.php?a=pro';
		$path['search']				= 'index.php?a=search';
		$path['welcome']			= 'index.php?a=welcome';
		$path['recover']			= 'index.php?a=recover';
		$path['upload']				= 'index.php?a=upload';
		$path['stats']				= 'index.php?a=stats';
		$path['playlist']			= 'index.php?a=playlist';
		$path['track']				= 'index.php?a=track';
        $path['history']			= 'index.php?a=history';
        $path['image']				= 'image.php';
		
		if(strpos($url, $path['profile'])) {
			$url = str_replace(array($path['profile'], '&u=', '&r=', '&filter='), array('profile', '/', '/', '/filter/'), $url);
		} elseif(strpos($url, $path['page'])) {
			$url = str_replace(array($path['page'], '&b='), array('page', '/'), $url);
		} elseif(strpos($url, $path['stream'])) {
			$url = str_replace(array($path['stream'], '&logout', '&token_id='), array('stream', '/logout', ''), $url);
		} elseif(strpos($url, $path['history'])) {
            $url = str_replace(array($path['history'], '&filter='), array('history', '/filter/', ''), $url);
        } elseif(strpos($url, $path['explore'])) {
			$url = str_replace(array($path['explore'], '&filter=', '&popular', '&liked'), array('explore', '/filter/', '/popular', '/liked'), $url);
		} elseif(strpos($url, $path['notifications'])) {
			$url = str_replace(array($path['notifications'], '&filter='), array('notifications', '/filter/'), $url);
		} elseif(strpos($url, $path['settings'])) {
			$url = str_replace(array($path['settings'], '&b='), array('settings', '/'), $url);
		} elseif(strpos($url, $path['messages'])) {
			$url = str_replace(array($path['messages'], '&u=', '&id='), array('messages', '/', '/'), $url);
		} elseif(strpos($url, $path['upload'])) {
			$url = str_replace(array($path['upload']), array('upload'), $url);
		} elseif(strpos($url, $path['pro'])) {
			$url = str_replace(array($path['pro']), array('pro'), $url);
		} elseif(strpos($url, $path['stats'])) {
			$url = str_replace(array($path['stats'], '&filter='), array('stats', '/filter/'), $url);
		} elseif(strpos($url, $path['search'])) {
			$url = str_replace(array($path['search'], '&q=', '&tag=', '&pages=', '&groups=', '&filter=', '&age='), array('search', '/', '/tag/', '/pages/', '/groups/', '/filter/', '/age/'), $url);
		} elseif(strpos($url, $path['welcome'])) {
			$url = str_replace(array($path['welcome']), array('welcome'), $url);
		} elseif(strpos($url, $path['recover'])) {
			$url = str_replace(array($path['recover'], '&r=1'), array('recover', '/do/'), $url);
		} elseif(strpos($url, $path['playlist'])) {
			$url = str_replace(array($path['playlist'], '&id=', '&edit=true', '&name='), array('playlist', '/', '/edit/', '/'), $url);
		} elseif(strpos($url, $path['track'])) {
			$url = str_replace(array($path['track'], '&id=', '&name=', '&type=likes', '&type=edit', '&type=report', '&type=stats', '&filter='), array('track', '/', '/', '/likes', '/edit', '/report', '/stats', '/filter/'), $url);
		} elseif(strpos($url, $path['image'])) {
            $url = str_replace(array($path['image'], '?t=', '&w=', '&h=', '&src='), array('image', '/', '/', '/', '/'), $url);
        }
	}
	return $url;
}
function cleanUrl($url) {
	$url = str_replace(' ', '-', $url);
	$url = preg_replace('/[^\w\-+]/u', '', $url);
	$url = preg_replace('/\-+/u', '-', $url);
	return mb_strtolower($url);
}
function cookie_law() {
    global $LNG, $settings;

    return '<div id="cookie-law-banner" class="cookie-law-banner-bottom cookie-law-banner-black"><div id="cookie-law-content">'.$LNG['cookies_text'].' <a href="'.permalink($settings['cookie_url']).'" target="_blank"><div class="cookie-law-button">'.$LNG['more_info'].'</div></a><a href="javascript:;" onclick="cookie_law()"><div class="cookie-law-button">'.$LNG['ok'].'</div></a></div></div>';
}
?>
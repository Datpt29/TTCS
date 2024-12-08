<?php
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Details;
use PayPal\Api\InputFields;
use PayPal\Api\WebProfile;
use PayPal\Api\Presentation;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;

require_once(__DIR__ .'/../includes/vendor/autoload.php');

function PageMain() {
	global $TMPL, $LNG, $CONF, $db, $user, $settings;
	
	if(!$settings['paypalapp']) {
		header("Location: ".$CONF['url']."/index.php?a=welcome");
	}
	
	// Start the music feed
	$feed = new feed();
	$feed->db = $db;
	$feed->url = $CONF['url'];
	$feed->user = $user;
	$feed->id = $user['idu'] ?? null;
	$feed->username = $user['username'] ?? null;
	$proAccount = $feed->getProStatus($feed->id, 0);
	
	$TMPL_old = $TMPL; $TMPL = array();

	$skin = new skin('pro/gopro'); $rows = '';

    $TMPL['form_url'] = permalink($CONF['url'].'/index.php?a=pro');
	
	// If the user is logged-in
	if($feed->id) {
        $apiContext = new ApiContext(
            new OAuthTokenCredential($settings['paypalclientid'], $settings['paypalsecret'])
        );
        $apiContext->setConfig(['mode' => ($settings['paypalsand'] ? 'sandbox' : 'live')]);

        if(isset($_POST['plan']) && !$proAccount) {
            if($_POST['plan'] == 1) {
                $_SESSION['item_price'] = $settings['proyear'];
                $_SESSION['item_currency'] = $settings['currency'];
                $_SESSION['item_plan'] = 1;
                $itemPrice = $settings['proyear'];
                $itemCurrency = $settings['currency'];
                $itemDescription = sprintf($LNG['pro_year'], $settings['title']);
                $itemNumber = md5(1);
            } else {
                $_SESSION['item_price'] = $settings['promonth'];
                $_SESSION['item_currency'] = $settings['currency'];
                $_SESSION['item_plan'] = 0;
                $itemPrice = $settings['promonth'];
                $itemCurrency = $settings['currency'];
                $itemDescription = sprintf($LNG['pro_month'], $settings['title']);
                $itemNumber = md5(0);
            }

            $presentation = new Presentation();
            $presentation->setLogoImage($CONF['url'].'/'.$CONF['theme_url'].'/images/logo_black.png');
            $presentation->setBrandName($settings['title']);

            $inputFields = new InputFields();
            $inputFields->setAllowNote(false);
            $inputFields->setNoShipping(1);

            $webProfile = new WebProfile();
            $webProfile->setName(uniqid());
            $webProfile->setPresentation($presentation);
            $webProfile->setInputFields($inputFields);
            $webProfile->setTemporary(true);

            try {
                $response = $webProfile->create($apiContext);

                $payer = new Payer();
                $payer->setPaymentMethod("paypal");

                $item = new Item();
                $item->setName($itemDescription);
                $item->setCurrency($itemCurrency);
                $item->setQuantity(1);
                $item->setSku($itemNumber);
                $item->setPrice($itemPrice);

                $itemList = new ItemList();
                $itemList->setItems(array($item));

                $details = new Details();
                $details->setSubtotal($itemPrice);

                $amount = new Amount();
                $amount->setTotal($itemPrice);
                $amount->setCurrency($itemCurrency);
                $amount->setDetails($details);

                $transaction = new Transaction();
                $transaction->setAmount($amount);
                $transaction->setItemList($itemList);
                $transaction->setInvoiceNumber(uniqid());

                $redirectUrls = new RedirectUrls();
                $redirectUrls->setReturnUrl($CONF['url']."/index.php?a=pro&type=successful");
                $redirectUrls->setCancelUrl($CONF['url']."/index.php?a=pro&type=canceled");

                $payment = new Payment();
                $payment->setIntent("sale");
                $payment->setPayer($payer);
                $payment->setRedirectUrls($redirectUrls);
                $payment->setTransactions(array($transaction));
                $payment->setExperienceProfileId($response->id);

                try {
                    $payment->create($apiContext);

                    header("Location: ".$payment->getApprovalLink());
                    exit();
                } catch(Exception $e) {
                    $TMPL['error'] = notificationBox('error', htmlspecialchars($e->getData()));
                }
            } catch(Exception $e) {
                $TMPL['error'] = notificationBox('error', htmlspecialchars($e->getData()));
            }
        }

        if(isset($_GET['type']) && $_GET['type'] == 'successful' && isset($_GET['paymentId']) && isset($_GET['PayerID']) && isset($_GET['token'])) {
            $paymentId = $_GET['paymentId'];
            $payment = Payment::get($paymentId, $apiContext);

            $execution = new PaymentExecution();
            $execution->setPayerId($_GET['PayerID']);

            try {
                $result = $payment->execute($execution, $apiContext);
                try {
                    $response = Payment::get($paymentId, $apiContext);

                    if($response->state == 'approved') {
                        $date = date("Y-m-d H:m:s", strtotime(($_SESSION['item_plan'] == 1 ? "+1 year" : "+1 month")));

                        $stmt = $db->prepare(sprintf("INSERT INTO `payments`
								(`by`, `payer_id`, `payer_first_name`, `payer_last_name`, `payer_email`, `payer_country`, `txn_id`, `amount`, `currency`, `type`, `status`, `valid`, `time`) VALUES
								('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s','%s', '%s', '%s', '%s')",
                            $db->real_escape_string($feed->id),
                            $db->real_escape_string($response->payer->payer_info->payer_id),
                            $db->real_escape_string($response->payer->payer_info->first_name),
                            $db->real_escape_string($response->payer->payer_info->last_name),
                            $db->real_escape_string($response->payer->payer_info->email),
                            $db->real_escape_string($response->payer->payer_info->country_code),
                            $db->real_escape_string($response->transactions[0]->related_resources[0]->sale->id),
                            $db->real_escape_string($response->transactions[0]->amount->total),
                            $settings['currency'],
                            $_SESSION['item_plan'],
                            1,
                            $date,
                            date("Y-m-d H:m:s")));

                        // Execute the statement
                        $stmt->execute();

                        // Check the affected rows
                        $affected = $stmt->affected_rows;

                        // Close the statement
                        $stmt->close();

                        // If the pro status has been added
                        if($affected) {
                            // Set the pro account to valid
                            $proAccount = 2;
                        }
                    }
                } catch(Exception $e) {
                    $TMPL['error'] = notificationBox('error', htmlspecialchars($e->getData()));
                }
            } catch(Exception $e) {
                $TMPL['error'] = notificationBox('error', htmlspecialchars($e->getData()));
            }
        } elseif(isset($_GET['type']) && $_GET['type'] == 'canceled') {
            $TMPL['error'] = notificationBox('error', $LNG['payment_error_1']);
        }

		if($proAccount) {
			$skin = new skin('pro/successful'); $rows = '';
			$transaction = $feed->getProStatus($feed->id, 2);
			
			// If the proAccount was just created
			if($proAccount == 2) {
				$TMPL['pro_title'] = $LNG['congratulations'].'!';
				$TMPL['pro_title_desc'] = $LNG['go_pro_congrats'];
			} else {
				$TMPL['pro_title'] = $LNG['pro_plan'];
				$TMPL['pro_title_desc'] = $LNG['account_status'];
			}
			
			// Explode the date to display in a custom format
			$valid = explode('-', $transaction['valid']);
			$TMPL['validuntil'] = $valid[0].'-'.$valid[1].'-'.substr($valid[2], 0, 2);

			// Decide the plan type
			$TMPL['plan'] = ($transaction['type'] ? $LNG['yearly'] : $LNG['monthly']);
			
			// Days left of pro Plan
			$TMPL['daysleft'] = floor((strtotime($transaction['valid']) - strtotime(date("Y-m-d H:i:s")))/(60*60*24)).' '.$LNG['days_left'];
			
			// The Amount paid for the pro plan
			$TMPL['amount'] = $transaction['amount'].' '.$settings['currency'];
		}
		$TMPL['go_pro_action'] = 'formSubmit(\'gopro-form\')';
	} else {
		$TMPL['go_pro_action'] = 'connect_modal()';
	}
	
	$TMPL['history'] = $feed->proAccountHistory(null, 1, 1);
	
	$TMPL['protracksize'] = fsize($settings['protracksize']);
	$TMPL['protracktotal'] = fsize($settings['protracktotal']);
	$TMPL['tracksize'] = fsize($settings['tracksize']);
	$TMPL['tracksizetotal'] = fsize($settings['tracksizetotal']);
	$TMPL['promonth'] = $settings['promonth'];
	$TMPL['proyear'] = $settings['proyear'];
	$TMPL['currency'] = $settings['currency'];
	
	$rows = $skin->make();
	
	$TMPL = $TMPL_old; unset($TMPL_old);
	$TMPL['rows'] = $rows;

	$TMPL['url'] = $CONF['url'];
	$TMPL['title'] = $LNG['go_pro'].' - '.$settings['title'];
	$TMPL['meta_description'] = $settings['title'].' '.$LNG['go_pro'].' - '.$LNG['go_pro_desc'];

	$skin = new skin('pro/content');
	return $skin->make();
}
?>
<?php

require_once(__DIR__ .'/../includes/autoload.php');

$debug = false;

$ipn = new PaypalIPN();

// Use the sandbox endpoint during testing.
if($settings['paypalsand']) {
    $ipn->useSandbox();
}
$ipn->usePHPCerts();
try {
	$verified = $ipn->verifyIPN();
} catch (Exception $e) {
}
if(isset($verified) && $verified) {
	/*
	 * Process IPN
	 * A list of variables is available here:
	 * https://developer.paypal.com/webapps/developer/docs/classic/ipn/integration-guide/IPNandPDTVariables/
	 */

	$paymentstatus = $_POST['payment_status'];
	$txn_id = $_POST['txn_id'];

	if($debug) {
		foreach($_POST as $id => $value) {
			error_log($id.': '.$value);
		}
	}

	$managePayments = new managePayments();
	$managePayments->db = $db;
	$managePayments->url = $CONF['url'];
	$managePayments->per_page = $settings['rperpage'];

	// Payment Status Codes: https://developer.paypal.com/webapps/developer/docs/classic/ipn/integration-guide/IPNandPDTVariables/#id091EB04C0HS__id0913D0E0UQU
	// Set the new Payment Status whenever it changes
	if($paymentstatus == 'Canceled_Reversal' || 'Completed') {
		$managePayments->updatePayment($txn_id, 1);
	} elseif($paymentstatus == 'Reversed') {
		$managePayments->updatePayment($txn_id, 2);
	} elseif($paymentstatus == 'Refunded') {
		$managePayments->updatePayment($txn_id, 3);
	} elseif($paymentstatus == 'Pending') {
		$managePayments->updatePayment($txn_id, 4);
	} elseif($paymentstatus == 'Failed') {
		$managePayments->updatePayment($txn_id, 5);
	} elseif($paymentstatus == 'Denied') {
		$managePayments->updatePayment($txn_id, 6);
	}
}
//
// Reply with an empty 200 response to indicate to paypal the IPN was received correctly.
header("HTTP/1.1 200 OK");

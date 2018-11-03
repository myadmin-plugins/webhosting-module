<?php
/**
* API Functions
* @author    Joe Huss <detain@interserver.net>
* @copyright 2018
* @package   MyAdmin
* @category  API
*/

/**
* Places a webhosting order for a website
*
* @param int $service_type
* @param int $period
* @param string $hostname
* @param string $coupon
* @param string $password
* @param int $script
* @return mixed
*/
function api_place_buy_website($service_type, $period, $hostname, $coupon, $password, $script = 0)
{
	$custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
	function_requirements('validate_buy_website');
	list($continue, $errors, $period, $coupon, $coupon_code, $service_type, $service_cost, $original_cost, $repeat_service_cost, $custid, $hostname, $password) = validate_buy_website($custid, $period, $coupon, $tos, $service_type, $hostname, $password, $script);
	if ($continue === true) {
		function_requirements('place_buy_website');
		list($total_cost, $iid, $iids, $real_iids, $serviceid, $invoice_description, $cj_params, $domain_serviceid, $diid) = place_buy_website($coupon_code, $service_cost, $service_type, $original_cost, $repeat_service_cost, $custid, $period, $hostname, $coupon, $password, false, false, $script);
		$return['status'] = 'ok';
		$return['status_text'] = $serviceid;
	} else {
		$return['status'] = 'error';
		$return['status_text'] = implode("\n", $errors);
	}
	return $return;
}

/**
* Validates the order parameters for a webhosting order.
*
* @param int $period
* @param string $coupon
* @param string $tos
* @param int $service_type
* @param string $hostname
* @param string $password
* @param int $script
* @return array
*/
function api_validate_buy_website($period, $coupon, $tos, $service_type, $hostname, $password, $script)
{
	$custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
	function_requirements('validate_buy_website');
	list($continue, $errors, $period, $coupon, $coupon_code, $service_type, $service_cost, $original_cost, $repeat_service_cost, $custid, $hostname, $password) = validate_buy_website($custid, $period, $coupon, $tos, $service_type, $hostname, $password, $script);
	$return = [];
	if ($continue === true) {
		$return['status'] = 'ok';
		$return['status_text'] = '';
	} else {
		$return['status'] = 'error';
		$return['status_text'] = implode("\n", $errors);
	}
	return $return;
}

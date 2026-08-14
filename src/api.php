<?php
/**
* API Functions
* @author    Joe Huss <detain@interserver.net>
* @copyright 2025
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
    $custid = get_custid(\MyAdmin\App::session()->account_id, 'vps');
    function_requirements('validate_buy_website');
    // 'yes' rather than a $tos parameter: validate_buy_website() rejects anything that is not
    // 'yes' or 'true', and calling this function IS the agreement -- an authenticated client
    // asking us to place the order. It previously passed an undefined $tos, which cast to ''
    // and failed that check on every call, so this function could never place an order at all.
    [$continue, $errors, $period, $coupon, $coupon_code, $service_type, $service_cost, $original_cost, $repeat_service_cost, $custid, $hostname, $password] = validate_buy_website($custid, $period, $coupon, 'yes', $service_type, $hostname, $password, $script);
    if ($continue === true) {
        function_requirements('place_buy_website');
        [$total_cost, $iid, $iids, $real_iids, $serviceid, $invoice_description, $cj_params, $domain_serviceid, $diid] = place_buy_website($coupon_code, $service_cost, $service_type, $original_cost, $repeat_service_cost, $custid, $period, $hostname, $coupon, $password, false, false, $script);
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
    $custid = get_custid(\MyAdmin\App::session()->account_id, 'vps');
    function_requirements('validate_buy_website');
    [$continue, $errors, $period, $coupon, $coupon_code, $service_type, $service_cost, $original_cost, $repeat_service_cost, $custid, $hostname, $password] = validate_buy_website($custid, $period, $coupon, $tos, $service_type, $hostname, $password, $script);
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

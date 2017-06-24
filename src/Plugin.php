<?php

namespace Detain\MyAdminWebhosting;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Webhosting Module';
	public static $description = 'Allows selling of Webhosting Module';
	public static $help = '';
	public static $module = 'webhosting';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 1000,
		'USE_REPEAT_INVOICE' => TRUE,
		'USE_PACKAGES' => TRUE,
		'BILLING_DAYS_OFFSET' => 0,
		'IMGNAME' => 'paper_content_chart_48.png',
		'REPEAT_BILLING_METHOD' => PRORATE_BILLING,
		'DELETE_PENDING_DAYS' => 45,
		'SUSPEND_DAYS' => 14,
		'SUSPEND_WARNING_DAYS' => 7,
		'TITLE' => 'Webhosting',
		'EMAIL_FROM' => 'invoice@interserver.net',
		'TBLNAME' => 'Websites',
		'TABLE' => 'websites',
		'PREFIX' => 'website',
		'TITLE_FIELD' => 'website_hostname',
		'TITLE_FIELD2' => 'website_username',
		'MENUNAME' => 'Webhosting'];


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function loadProcessing(GenericEvent $event) {
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->set_enable(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				function_requirements('website_create');
				$success = website_create($serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_type'], $serviceInfo[$settings['PREFIX'].'_hostname'], website_get_password($serviceInfo[$settings['PREFIX'].'_id']));
				if ($success !== FALSE) {
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					function_requirements('admin_email_website_pending_setup');
					admin_email_website_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
				} else {
					// there was an error setting up the website, email us about it.
					admin_mail('Error Setting Up Website ' . $serviceInfo[$settings['PREFIX'].'_id'], 'There was an error setting up the website.  Please look into it and fix.', FALSE, 'my@interserver.net', 'admin_email_setup_error.tpl');
				}
			})->set_reactivate(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted' || $serviceInfo[$settings['PREFIX'].'_ip'] == '' || (isset($serviceInfo[$settings['PREFIX'].'_username']) && $serviceInfo[$settings['PREFIX'].'_username'] == '')) {
					function_requirements('website_create');
					$success = website_create($serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_type'], $serviceInfo[$settings['PREFIX'].'_hostname'], website_get_password($serviceInfo[$settings['PREFIX'].'_id']));
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				} else {
					$db->query("select * from {$settings['PREFIX']}_masters where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_server']}'", __LINE__, __FILE__);
					$db->next_record(MYSQL_ASSOC);
					$serverdata = $db->Record;
					$hash = $serverdata[$settings['PREFIX'].'_key'];
					$ip = $serverdata[$settings['PREFIX'].'_ip'];
					$success = TRUE;
					$extra = run_event('parse_service_extra', $serviceInfo[$settings['PREFIX'] . '_extra'], self::$module);
					switch ($serviceTypes[$serviceInfo[$settings['PREFIX'] . '_type']]['services_category']) {
						// Parallels Plesk Automation
						case SERVICE_TYPES_WEB_PPA:
							if (sizeof($extra) == 0)
								$extra = get_plesk_info_from_domain($serviceInfo[$settings['PREFIX'].'_hostname']);
							if (sizeof($extra) == 0) {
								$msg = 'Blank/Empty Plesk Subscription Info, Email support@interserver.net about this';
								dialog('Error', $msg);
								myadmin_log(self::$module, 'info', $msg, __LINE__, __FILE__);
								$success = FALSE;
							} else {
								list($account_id, $user_id, $subscription_id, $webspace_id) = $extra;
								require_once(INCLUDE_ROOT . '/webhosting/class.pleskautomation.php');
								$ppaConnector = get_webhosting_ppa_instance($serverdata);
								$request = array(
									'subscription_id' => $subscription_id,
								);
								$result = $ppaConnector->enableSubscription($request);
								//echo "Result:";var_dump($result);echo "\n";
								try {
									\PPAConnector::checkResponse($result);
								} catch (Exception $e) {
									echo 'Caught exception: ' . $e->getMessage() . "\n";
								}
								myadmin_log(self::$module, 'info', 'enableSubscription Called got ' . json_encode($result), __LINE__, __FILE__);
							}
							break;
						// Parallels Plesk
						case SERVICE_TYPES_WEB_PLESK:
							$plesk = get_webhosting_plesk_instance($serverdata);
							list($user_id, $subscription_id) = $extra;
							$request = array(
								'username' => $serviceInfo[$settings['PREFIX'].'_username'],
								'status' => 0,
							);
							try {
								$result = $plesk->update_client($request);
							} catch (Exception $e) {
								echo 'Caught exception: ' . $e->getMessage() . "\n";
							}
							myadmin_log(self::$module, 'info', 'update_client Called got ' . json_encode($result), __LINE__, __FILE__);
							break;
						// VestaCP
						case SERVICE_TYPES_WEB_VESTA:
							$data = $GLOBALS['tf']->accounts->read($serviceInfo[$settings['PREFIX'].'_custid']);
							list($user, $pass) = explode(':', $hash);
							require_once(INCLUDE_ROOT . '/webhosting/VestaCP.php');
							$vesta = new \VestaCP($ip, $user, $pass);
							myadmin_log(self::$module, 'info', "Calling vesta->unsuspend_account({$serviceInfo[$settings['PREFIX'] . '_username']})", __LINE__, __FILE__);
							if ($vesta->unsuspend_account($serviceInfo[$settings['PREFIX'] . '_username'])) {
								myadmin_log(self::$module, 'info', 'Success, Response: ' . var_export($vesta->response, TRUE), __LINE__, __FILE__);
							} else {
								myadmin_log(self::$module, 'info', 'Failure, Response: ' . var_export($vesta->response, TRUE), __LINE__, __FILE__);
								$success = FALSE;
							}
							break;
						// cPanel/WHM
						case SERVICE_TYPES_WEB_CPANEL:
						default:
							function_requirements('whm_api');
							$user = 'root';
							$ip = $serverdata[$settings['PREFIX'].'_ip'];
							$whm = new \xmlapi($ip);
							//$whm->set_debug('true');
							$whm->set_port('2087');
							$whm->set_protocol('https');
							$whm->set_output('json');
							$whm->set_auth_type('hash');
							$whm->set_user($user);
							$whm->set_hash($hash);
							//$whm = whm_api('faith.interserver.net');
							$field1 = explode(',', $serviceTypes[$serviceInfo[$settings['PREFIX'] . '_type']]['services_field1']);
							if (in_array('reseller', $field1))
								$response = json_decode($whm->unsuspendreseller($serviceInfo[$settings['PREFIX'] . '_username']), TRUE);
							else
								$response = json_decode($whm->unsuspendacct($serviceInfo[$settings['PREFIX'] . '_username']), TRUE);
							myadmin_log(self::$module, 'info', json_encode($response), __LINE__, __FILE__);
							break;
					}
					if ($success == TRUE) {
						$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
						$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					}
				}
				$smarty = new \TFSmarty;
				$smarty->assign('website_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin_email_website_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				admin_mail($subject, $email, FALSE, FALSE, 'admin_email_website_reactivated.tpl');
			})->set_disable(function($service) {
			})->register();

	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_server', 'Default Setup Server', NEW_WEBSITE_SERVER);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting', 'Out Of Stock All Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_demo', 'Out Of Stock Demo/Trial Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_DEMO'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ispconfig', 'Out Of Stock ISPconfig Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPCONFIG'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ispmanager', 'Out Of Stock ISPmanager Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPMANAGER'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Costs & Limits', 'website_limited_package_enable', 'Enable a Daily Limited Package', 'Enable/Disable Limiting of a website package', (defined('WEBSITE_LIMITED_PACKAGE_ENABLE') ? WEBSITE_LIMITED_PACKAGE_ENABLE : '0'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_limited_package', 'Daily Limited Package to Limit:', 'The Package ID to Limit per Day.', (defined('WEBSITE_LIMITED_PACKAGE') ? WEBSITE_LIMITED_PACKAGE : 1003));
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_limited_package_limit', 'Daily Limited Package Limit:', 'How many packages can be sold per day.', (defined('WEBSITE_LIMITED_PACKAGE_LIMIT') ? WEBSITE_LIMITED_PACKAGE_LIMIT : 100));
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_limited_package_multiplier', 'Daily Limited Sale Multiplier :', 'Each sale counts as this many towards this limit.', (defined('WEBSITE_LIMITED_PACKAGE_MULTIPLIER') ? WEBSITE_LIMITED_PACKAGE_MULTIPLIER : 1));
		$settings->add_text_setting(self::$module, 'Webhosting Demo', 'website_demo_expire_days', 'Days before Demo Expires:', 'How many days a webhosting demo will be active before expiring.', (defined('WEBSITE_DEMO_EXPIRE_DAYS') ? WEBSITE_DEMO_EXPIRE_DAYS : 14));
		$settings->add_text_setting(self::$module, 'Webhosting Demo', 'website_demo_warning_days', 'Days before Demo Sends Expiring Soon Mail:', 'How many days a webhosting demo will be active before sending out an expiring soon warning email.', (defined('WEBSITE_DEMO_WARNING_DAYS') ? WEBSITE_DEMO_WARNING_DAYS : 10));
		$settings->add_text_setting(self::$module, 'Webhosting Demo', 'website_demo_extend_days', 'Days the demo is extended when extending the demo:', 'How many days a webhosting demo will be active before sending out an expiring soon warning email.', (defined('WEBSITE_DEMO_EXTEND_DAYS') ? WEBSITE_DEMO_EXTEND_DAYS : 10));
		$settings->add_select_master_autosetup(self::$module, 'Auto-Setup Servers', self::$module, 'webhosting_setup_servers', 'Auto-Setup Servers:', '<p>Choose which servers are used for auto-server Setups.</p>');
	}
}

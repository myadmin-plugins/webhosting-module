<?php

namespace Detain\MyAdminWebhosting;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminWebhosting
 */
class Plugin {

	public static $name = 'Webhosting';
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

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event) {
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->set_enable(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				if ($service->getSuccess() !== FALSE) {
					$db = get_module_db(self::$module);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					function_requirements('admin_email_website_pending_setup');
					admin_email_website_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
				} else {
					admin_mail('Error Setting Up Website '.$serviceInfo[$settings['PREFIX'].'_id'], 'There was an error setting up the website.  Please look into it and fix.', FALSE, 'my@interserver.net', 'admin_email_setup_error.tpl');
				}
			})->set_reactivate(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$smarty = new \TFSmarty;
				$smarty->assign('website_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin_email_website_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				admin_mail($subject, $email, FALSE, FALSE, 'admin_email_website_reactivated.tpl');
			})->set_disable(function($service) {
			})->setTerminate(function($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
				$class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
				/** @var \MyAdmin\Orm\Product $class **/
				$serviceClass = new $class();
				$serviceClass->load_real($id);
				$subevent = new GenericEvent($serviceClass, [
					'field1' => $serviceTypes[$serviceClass->getType()]['services_field1'],
					'field2' => $serviceTypes[$serviceClass->getType()]['services_field2'],
					'type' => $serviceTypes[$serviceClass->getType()]['services_type'],
					'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
					'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
				]);
				$success = true;
				try {
					$GLOBALS['tf']->dispatcher->dispatch(self::$module.'.terminate', $subevent);
				} catch (\Exception $e) {
					myadmin_log('webhosting', 'info', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__);
					$serverData = get_service_master($serviceClass->getServer(), self::$module);
					$subject = 'Cant Connect to Webhosting Server to Suspend';
					$headers = 'MIME-Version: 1.0'.EMAIL_NEWLINE;
					$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
					$headers .= 'From: '.$settings['TITLE'].' <'.$settings['EMAIL_FROM'].'>'.EMAIL_NEWLINE;
					$email = $subject.'<br>'.'Username '.$serviceClass->getUsername().'<br>'.'Server '.$serverData[$settings['PREFIX'].'_name'].'<br>'.$e->getMessage();
					admin_mail($subject, $email, $headers, FALSE, 'admin_email_website_connect_error.tpl');
					$success = false;
				}
				if ($success == true && !$subevent->isPropagationStopped()) {
					myadmin_log(self::$module, 'error', 'Dont know how to deactivate '.$settings['TBLNAME'].' '.$id.' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__);
					$success = false;
				}
				if ($success == true) {
					$serviceClass->setServerStatus('deleted')->save();
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_server_status='deleted' where {$settings['PREFIX']}_id={$id}", __LINE__, __FILE__);
				}
			})->register();

	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_server', 'Default Setup Server', NEW_WEBSITE_SERVER);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting', 'Out Of Stock All Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_demo', 'Out Of Stock Demo/Trial Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_DEMO'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ispconfig', 'Out Of Stock ISPconfig Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPCONFIG'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ispmanager', 'Out Of Stock ISPmanager Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPMANAGER'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Costs & Limits', 'website_limited_package_enable', 'Enable a Daily Limited Package', 'Enable/Disable Limiting of a website package', (defined('WEBSITE_LIMITED_PACKAGE_ENABLE') ? WEBSITE_LIMITED_PACKAGE_ENABLE : '0'), ['0', '1'], ['No', 'Yes']);
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_limited_package', 'Daily Limited Package to Limit:', 'The Package ID to Limit per Day.', (defined('WEBSITE_LIMITED_PACKAGE') ? WEBSITE_LIMITED_PACKAGE : 1003));
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_limited_package_limit', 'Daily Limited Package Limit:', 'How many packages can be sold per day.', (defined('WEBSITE_LIMITED_PACKAGE_LIMIT') ? WEBSITE_LIMITED_PACKAGE_LIMIT : 100));
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_limited_package_multiplier', 'Daily Limited Sale Multiplier :', 'Each sale counts as this many towards this limit.', (defined('WEBSITE_LIMITED_PACKAGE_MULTIPLIER') ? WEBSITE_LIMITED_PACKAGE_MULTIPLIER : 1));
		$settings->add_text_setting(self::$module, 'Webhosting Demo', 'website_demo_expire_days', 'Days before Demo Expires:', 'How many days a webhosting demo will be active before expiring.', (defined('WEBSITE_DEMO_EXPIRE_DAYS') ? WEBSITE_DEMO_EXPIRE_DAYS : 14));
		$settings->add_text_setting(self::$module, 'Webhosting Demo', 'website_demo_warning_days', 'Days before Demo Sends Expiring Soon Mail:', 'How many days a webhosting demo will be active before sending out an expiring soon warning email.', (defined('WEBSITE_DEMO_WARNING_DAYS') ? WEBSITE_DEMO_WARNING_DAYS : 10));
		$settings->add_text_setting(self::$module, 'Webhosting Demo', 'website_demo_extend_days', 'Days the demo is extended when extending the demo:', 'How many days a webhosting demo will be active before sending out an expiring soon warning email.', (defined('WEBSITE_DEMO_EXTEND_DAYS') ? WEBSITE_DEMO_EXTEND_DAYS : 10));
		$settings->add_select_master_autosetup(self::$module, 'Auto-Setup Servers', self::$module, 'webhosting_setup_servers', 'Auto-Setup Servers:', '<p>Choose which servers are used for auto-server Setups.</p>');
	}
}

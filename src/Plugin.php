<?php

namespace Detain\MyAdminWebhosting;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminWebhosting
 */
class Plugin
{
	public static $name = 'Webhosting';
	public static $description = 'Allows selling of Webhosting Module';
	public static $help = '';
	public static $module = 'webhosting';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 1000,
		'USE_REPEAT_INVOICE' => true,
		'USE_PACKAGES' => true,
		'BILLING_DAYS_OFFSET' => 0,
		'IMGNAME' => 'website.png',
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
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			'api.register' => [__CLASS__, 'apiRegister'],
			'function.requirements' => [__CLASS__, 'getRequirements'],
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event)
	{
		$loader = $event->getSubject();
		$loader->add_requirement('api_place_buy_website', '/../vendor/detain/myadmin-webhosting-module/src/api.php');
		$loader->add_requirement('api_validate_buy_website', '/../vendor/detain/myadmin-webhosting-module/src/api.php');
	}
	
	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function apiRegister(GenericEvent $event)
	{
		/**
		 * @var \ServiceHandler $subject
		 */
		//$subject = $event->getSubject();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event)
	{
		/**
		 * @var \ServiceHandler $service
		 */
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->setEnable(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				function_requirements('admin_email_website_pending_setup');
				admin_email_website_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
			})->setReactivate(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$smarty = new \TFSmarty;
				$smarty->assign('website_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/website_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
				(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_reactivated.tpl');
			})->setDisable(function ($service) {
			})->setTerminate(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
				/** @var \MyAdmin\Orm\Product $class **/
				$serviceClass = new $class();
				$serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
				$subevent = new GenericEvent($serviceClass, [
					'field1' => $serviceTypes[$serviceClass->getType()]['services_field1'],
					'field2' => $serviceTypes[$serviceClass->getType()]['services_field2'],
					'type' => $serviceTypes[$serviceClass->getType()]['services_type'],
					'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
					'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
				]);
				$success = true;
				try {
					$GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.terminate');
				} catch (\Exception $e) {
					myadmin_log('webhosting', 'info', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
					$serverData = get_service_master($serviceClass->getServer(), self::$module);
					$subject = 'Cant Connect to Webhosting Server to Suspend';
					$email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>Server '.$serverData[$settings['PREFIX'].'_name'].'<br>'.$e->getMessage();
					(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
					$success = false;
				}
				if ($success == true && !$subevent->isPropagationStopped()) {
					myadmin_log(self::$module, 'error', 'Dont know how to deactivate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
					$success = false;
				}
				if ($success == true) {
					$serviceClass->setServerStatus('deleted')->save();
					$GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				}
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		/** @var \MyAdmin\Settings $settings **/
		$settings = $event->getSubject();
		$settings->setTarget('module');
		$settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_webhosting', _('Out Of Stock All Webhosting'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_WEBHOSTING'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_webhosting_demo', _('Out Of Stock Demo/Trial Webhosting'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_WEBHOSTING_DEMO'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_webhosting_ispconfig', _('Out Of Stock ISPconfig Webhosting'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPCONFIG'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_webhosting_ispmanager', _('Out Of Stock ISPmanager Webhosting'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPMANAGER'), ['0', '1'], ['No', 'Yes']);
		$settings->add_master_checkbox_setting(self::$module, _('Server Settings'), self::$module, 'available', 'website_available', _('Auto-Setup'), '<p>Choose which servers are used for auto-server Setups.</p>');
		$settings->add_master_label(self::$module, 'Server Settings', self::$module, 'active_services', 'Active Websites', '<p>The current number of active Websites.</p>', 'count(websites.website_id) as active_services');
		$settings->add_master_text_setting(self::$module, 'Server Settings', self::$module, 'max_sites', 'website_max_sites', 'Max Websites', '<p>The Maximum number of Websites that can be running on each server.</p>');
		//$settings->add_master_text_setting(self::$module, _('Server Settings'), self::$module, 'key', 'website_key', _('API Key'), '<p>'._('The Key needed to connect.').'</p>');
		//$settings->add_select_master_autosetup(self::$module, 'Auto-Setup Servers', self::$module, 'webhosting_setup_servers', _('Auto-Setup Servers'), '<p>Choose which servers are used for auto-server Setups.</p>');
		$settings->setTarget('global');
		$settings->add_dropdown_setting(self::$module, _('Costs & Limits'), 'website_limited_package_enable', _('Enable a Daily Limited Package'), _('Enable/Disable Limiting of a website package'), (defined('WEBSITE_LIMITED_PACKAGE_ENABLE') ? WEBSITE_LIMITED_PACKAGE_ENABLE : '0'), ['0', '1'], ['No', 'Yes']);
		$settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_limited_package', _('Daily Limited Package to Limit'), _('The Package ID to Limit per Day.'), (defined('WEBSITE_LIMITED_PACKAGE') ? WEBSITE_LIMITED_PACKAGE : 1003));
		$settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_limited_package_limit', _('Daily Limited Package Limit'), _('How many packages can be sold per day.'), (defined('WEBSITE_LIMITED_PACKAGE_LIMIT') ? WEBSITE_LIMITED_PACKAGE_LIMIT : 100));
		$settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_limited_package_multiplier', _('Daily Limited Sale Multiplier'), _('Each sale counts as this many towards this limit.'), (defined('WEBSITE_LIMITED_PACKAGE_MULTIPLIER') ? WEBSITE_LIMITED_PACKAGE_MULTIPLIER : 1));
		$settings->add_text_setting(self::$module, _('Webhosting Demo'), 'website_demo_expire_days', _('Days before Demo Expires'), _('How many days a webhosting demo will be active before expiring.'), (defined('WEBSITE_DEMO_EXPIRE_DAYS') ? WEBSITE_DEMO_EXPIRE_DAYS : 14));
		$settings->add_text_setting(self::$module, _('Webhosting Demo'), 'website_demo_warning_days', _('Days before Demo Sends Expiring Soon Mail'), _('How many days a webhosting demo will be active before sending out an expiring soon warning email.'), (defined('WEBSITE_DEMO_WARNING_DAYS') ? WEBSITE_DEMO_WARNING_DAYS : 10));
		$settings->add_text_setting(self::$module, _('Webhosting Demo'), 'website_demo_extend_days', _('Days the demo is extended when extending the demo'), _('How many days a webhosting demo will be active before sending out an expiring soon warning email.'), (defined('WEBSITE_DEMO_EXTEND_DAYS') ? WEBSITE_DEMO_EXTEND_DAYS : 10));
	}
}

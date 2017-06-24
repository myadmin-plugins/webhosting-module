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
		'USE_REPEAT_INVOICE' => true,
		'USE_PACKAGES' => true,
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

	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_server', 'Default Setup Server', NEW_WEBSITE_SERVER);
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_plesk_server', 'Default Plesk Setup Server', NEW_WEBSITE_PLESK_SERVER, SERVICE_TYPES_WEB_PLESK);
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_ppa_server', 'Default Plesk Automation Setup Server', NEW_WEBSITE_PPA_SERVER, SERVICE_TYPES_WEB_PPA);
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_vesta_server', 'Default VestaCP Setup Server', NEW_WEBSITE_VESTA_SERVER, SERVICE_TYPES_WEB_VESTA);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting', 'Out Of Stock All Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_plesk', 'Out Of Stock Plesk Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_PLESK'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ppa', 'Out Of Stock Plesk Automation Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_PPA'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_vestacp', 'Out Of Stock VestaCP Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_VESTACP'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_demo', 'Out Of Stock Demo/Trial Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_DEMO'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ispconfig', 'Out Of Stock ISPconfig Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPCONFIG'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_ispmanager', 'Out Of Stock ISPmanager Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_ISPMANAGER'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_ip_cost', 'Dedicated IP Cost:', 'This is the cost for purchasing an additional IP on top of a Website.', (defined(WEBSITE_IP_COST) ? WEBSITE_IP_COST : 3));
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

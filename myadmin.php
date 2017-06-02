<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_webhosting define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Webhosting Licensing VPS Addon',
	'description' => 'Allows selling of Webhosting Server and VPS License Types.  More info at https://www.netenberg.com/webhosting.php',
	'help' => 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a webhosting license. Allow 10 minutes for activation.',
	'module' => 'vps',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-webhosting-vps-addon',
	'repo' => 'https://github.com/detain/myadmin-webhosting-vps-addon',
	'version' => '1.0.0',
	'type' => 'addon',
	'hooks' => [
		'vps.load_addons' => ['Detain\MyAdminVpsWebhosting\Plugin', 'Load'],
		/* 'function.requirements' => ['Detain\MyAdminVpsWebhosting\Plugin', 'Requirements'],
		'licenses.settings' => ['Detain\MyAdminVpsWebhosting\Plugin', 'Settings'],
		'licenses.activate' => ['Detain\MyAdminVpsWebhosting\Plugin', 'Activate'],
		'licenses.change_ip' => ['Detain\MyAdminVpsWebhosting\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminVpsWebhosting\Plugin', 'Menu'] */
	],
];

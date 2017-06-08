<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_webhosting define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Webhosting Module',
	'description' => 'Allows selling of Webhosting Module',
	'help' => '',
	'module' => 'webhosting',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-webhosting-module',
	'repo' => 'https://github.com/detain/myadmin-webhosting-module',
	'version' => '1.0.0',
	'type' => 'module',
	'hooks' => [
		'webhosting.load_processing' => ['Detain\MyAdminWebhosting\Plugin', 'Load'],
		'webhosting.settings' => ['Detain\MyAdminWebhosting\Plugin', 'Settings'],
		/* 'function.requirements' => ['Detain\MyAdminWebhosting\Plugin', 'Requirements'],
		'webhosting.activate' => ['Detain\MyAdminWebhosting\Plugin', 'Activate'],
		'webhosting.change_ip' => ['Detain\MyAdminWebhosting\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminWebhosting\Plugin', 'Menu'] */
	],
];

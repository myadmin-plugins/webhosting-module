<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * Defines constants required by the Plugin class and loads the Composer autoloader.
 */

// Define constants used by Plugin::$settings that are normally provided by the MyAdmin framework
if (!defined('PRORATE_BILLING')) {
    define('PRORATE_BILLING', 1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * `api_register()` is a global from core's include/Api/api.functions.inc.php, so
 * Plugin::apiRegister() cannot be called at all without it.
 *
 * IT MUST FORWARD, NOT SWALLOW. The shared contract harness observes what this plugin
 * registers by declaring its own api_register*() globals, each behind function_exists() -- and
 * PHP cannot redeclare a function, so whichever file loads first wins. A plain no-op here
 * would win, the harness would record nothing, and assertion B-16 would report this plugin as
 * publishing an empty API surface. Two packages in this fleet were accused of exactly that,
 * wrongly, before the harness learned to detect it (H-4); it now skips instead, which is
 * honest but leaves the assertion doing nothing.
 *
 * Forwarding keeps both readers working: this repo's own tests can call the handler, and the
 * harness still sees every registration.
 */
if (!function_exists('api_register')) {
    function api_register($function, $input, $output, $label = '', $logged_in = true, $wrap = true)
    {
        if (class_exists(\MyAdmin\Plugins\Testing\Harness::class, false)) {
            \MyAdmin\Plugins\Testing\Harness::api()->api_register($function, $input, $output, $label, $logged_in, $wrap);
        }
    }
}

if (!function_exists('api_register_array')) {
    function api_register_array($function, $data)
    {
        if (class_exists(\MyAdmin\Plugins\Testing\Harness::class, false)) {
            \MyAdmin\Plugins\Testing\Harness::api()->api_register_array($function, $data);
        }
    }
}

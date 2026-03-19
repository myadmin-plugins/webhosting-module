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

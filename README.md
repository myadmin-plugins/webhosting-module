# MyAdmin Webhosting Module

[![Tests](https://github.com/detain/myadmin-webhosting-module/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-webhosting-module/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-webhosting-module/version)](https://packagist.org/packages/detain/myadmin-webhosting-module)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-webhosting-module/downloads)](https://packagist.org/packages/detain/myadmin-webhosting-module)
[![License](https://poser.pugx.org/detain/myadmin-webhosting-module/license)](https://packagist.org/packages/detain/myadmin-webhosting-module)

Webhosting service module for the [MyAdmin](https://github.com/detain/myadmin) multiserver control panel. Provides automated lifecycle management of shared hosting accounts -- provisioning, suspension, reactivation, and termination -- across multiple hosting platforms including ISPconfig and ISPmanager.

## Features

- Event-driven architecture using Symfony EventDispatcher
- Automated service provisioning with configurable server selection
- Suspension and reactivation workflows with admin email notifications
- Graceful termination with error handling and fallback notifications
- Configurable billing integration (prorate billing, repeat invoices)
- Out-of-stock controls per hosting platform type
- Demo/trial hosting with configurable expiration and extension periods
- Daily package sale limiting with multiplier support

## Requirements

- PHP 8.2 or higher
- ext-soap
- Symfony EventDispatcher 5.x, 6.x, or 7.x

## Installation

```sh
composer require detain/myadmin-webhosting-module
```

## Testing

```sh
composer install
vendor/bin/phpunit
```

## License

Licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.en.html).

---
name: phpunit-test
description: Writes PHPUnit 9 tests in `tests/` under namespace `Detain\MyAdminWebhosting\Tests\`. Follows ApiTest.php static string-inspection pattern for `src/api.php` and PluginTest.php ReflectionClass pattern for `src/Plugin.php`. Use when user says 'write test', 'add test case', 'test this function', 'add coverage'. Do NOT use for integration tests that require a live database or MyAdmin framework bootstrap.
---
# PHPUnit Test

## Critical

- **Never** instantiate API functions from `src/api.php` in tests — they require global MyAdmin state. Use static string/regex inspection only.
- **Always** add framework constants to `tests/bootstrap.php` with `if (!defined(...))` guards before `require autoload.php`. Missing constants cause fatal errors before any test runs.
- All test files **must** start with `declare(strict_types=1);` and use namespace `Detain\MyAdminWebhosting\Tests`.
- Run `phpunit --configuration phpunit.xml.dist` (not `composer test`) — this repo uses `phpunit.xml.dist` at the root, not the parent project's config.

## Instructions

### Step 1 — Add framework constants to bootstrap (if needed)

Open `tests/bootstrap.php`. If the code under test references a constant not already defined there, add it:

```php
<?php
declare(strict_types=1);

// Define constants used by Plugin::$settings normally provided by MyAdmin framework
if (!defined('PRORATE_BILLING')) {
    define('PRORATE_BILLING', 1);
}
// Add other missing constants here with the same guard pattern

require dirname(__DIR__) . '/vendor/autoload.php';
```

Verify `phpunit --configuration phpunit.xml.dist --list-tests` succeeds before writing test methods.

### Step 2 — Create the test file

Place test classes in `tests/`. Name the file `<Subject>Test.php` matching the class name.

**File skeleton (identical header for every test file):**

```php
<?php

declare(strict_types=1);

namespace Detain\MyAdminWebhosting\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test suite for <describe what is under test>.
 *
 * @package Detain\MyAdminWebhosting\Tests
 */
class <Subject>Test extends TestCase
{
}
```

For `src/Plugin.php` tests also add:
```php
use Detain\MyAdminWebhosting\Plugin;
use ReflectionClass;
use Symfony\Component\EventDispatcher\GenericEvent;
```

Verify the file parses: `php -l tests/<Subject>Test.php`.

### Step 3A — Tests for `src/api.php` (static string-inspection pattern)

Load the file once in `setUpBeforeClass()`, then inspect the string content. Never call the functions directly.

```php
private static string $apiFile;
private static string $apiContent;

public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();
    self::$apiFile = dirname(__DIR__) . '/src/api.php';
    $content = file_get_contents(self::$apiFile);
    self::$apiContent = $content !== false ? $content : '';
}

// File-level assertions
public function testApiFileExists(): void
{
    $this->assertFileExists(self::$apiFile);
}

public function testApiFileIsValidPhp(): void
{
    $output = []; $exitCode = 0;
    $escapedPath = escapeshellarg(self::$apiFile);
    exec("php -l {$escapedPath} 2>&1", $output, $exitCode);
    $this->assertSame(0, $exitCode, implode("\n", $output));
}

public function testFileHasNoNamespace(): void
{
    $this->assertStringNotContainsString('namespace ', self::$apiContent);
}

// Function existence
public function testApiFileContainsFunctionName(): void
{
    $this->assertStringContainsString('function api_place_buy_website', self::$apiContent);
}

// Parameter extraction helper — copy this verbatim
private function extractFunctionParams(string $functionName): array
{
    $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(([^)]*)\)/';
    if (!preg_match($pattern, self::$apiContent, $matches)) {
        return [];
    }
    $paramString = trim($matches[1]);
    if ($paramString === '') { return []; }
    $params = [];
    foreach (explode(',', $paramString) as $param) {
        $param = trim($param);
        if (preg_match('/(\$\w+)/', $param, $varMatch)) {
            $params[] = $varMatch[1];
        }
    }
    return $params;
}

// Parameter count + name assertions
public function testPlaceBuyWebsiteParameterCount(): void
{
    $params = $this->extractFunctionParams('api_place_buy_website');
    $this->assertCount(6, $params, 'api_place_buy_website should accept 6 parameters.');
}

public function testPlaceBuyWebsiteParameterNames(): void
{
    $params = $this->extractFunctionParams('api_place_buy_website');
    $expected = ['$service_type', '$period', '$hostname', '$coupon', '$password', '$script'];
    $this->assertSame($expected, $params);
}

// Return structure
public function testFunctionsUseStatusArray(): void
{
    $this->assertStringContainsString("$return['status']", self::$apiContent);
    $this->assertStringContainsString("$return['status_text']", self::$apiContent);
    $this->assertStringContainsString("'ok'", self::$apiContent);
    $this->assertStringContainsString("'error'", self::$apiContent);
}
```

### Step 3B — Tests for `src/Plugin.php` (ReflectionClass pattern)

Instantiate `ReflectionClass` in `setUp()`, use it for method/property introspection.

```php
private ReflectionClass $reflection;

protected function setUp(): void
{
    parent::setUp();
    $this->reflection = new ReflectionClass(Plugin::class);
}

// Class structure
public function testClassExists(): void
{
    $this->assertTrue(class_exists(Plugin::class));
}

public function testNamespace(): void
{
    $this->assertSame('Detain\\MyAdminWebhosting', $this->reflection->getNamespaceName());
}

// Static property values
public function testModuleProperty(): void
{
    $this->assertSame('webhosting', Plugin::$module);
}

// Hook registration
public function testGetHooksContainsExpectedKeys(): void
{
    $hooks = Plugin::getHooks();
    foreach (['api.register', 'function.requirements', 'webhosting.load_processing', 'webhosting.settings'] as $key) {
        $this->assertArrayHasKey($key, $hooks, "Missing hook: {$key}");
    }
}

public function testGetHooksValuesAreCallableArrays(): void
{
    foreach (Plugin::getHooks() as $event => $handler) {
        $this->assertIsArray($handler);
        $this->assertCount(2, $handler);
        $this->assertSame(Plugin::class, $handler[0]);
    }
}

// Event handler signature check
public function testGetRequirementsSignature(): void
{
    $method = $this->reflection->getMethod('getRequirements');
    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
    $param = $method->getParameters()[0];
    $this->assertSame('event', $param->getName());
    $this->assertSame(GenericEvent::class, $param->getType()->getName());
}

// Anonymous-class stub for getRequirements() behavior test
public function testGetRequirementsCallsAddRequirement(): void
{
    $calls = [];
    $loader = new class($calls) {
        private array $callsRef;
        public function __construct(array &$calls) { $this->callsRef = &$calls; }
        public function add_requirement(string $name, string $path): void {
            $this->callsRef[] = [$name, $path];
        }
    };
    Plugin::getRequirements(new GenericEvent($loader));
    $this->assertCount(2, $calls);
    $this->assertStringContainsString('api.php', $calls[0][1]);
}
```

### Step 4 — Run and verify

```bash
phpunit --configuration phpunit.xml.dist
```

All tests must pass with 0 failures. If you see risky test warnings, add `$this->assertTrue(true)` as the final assertion in any test that lacks explicit assertions (e.g., no-op handlers).

## Examples

**User says:** "Add a test verifying `api_validate_buy_website` has 7 parameters"

**Actions taken:**
1. Read `tests/ApiTest.php` to confirm `extractFunctionParams()` helper exists.
2. Add to `ApiTest` class:
```php
public function testValidateBuyWebsiteParameterCount(): void
{
    $params = $this->extractFunctionParams('api_validate_buy_website');
    $this->assertCount(7, $params, 'api_validate_buy_website should accept 7 parameters.');
}

public function testValidateBuyWebsiteParameterNames(): void
{
    $params = $this->extractFunctionParams('api_validate_buy_website');
    $expected = ['$period', '$coupon', '$tos', '$service_type', '$hostname', '$password', '$script'];
    $this->assertSame($expected, $params);
}
```
3. Run `phpunit --configuration phpunit.xml.dist --filter testValidateBuyWebsite` to verify green.

**Result:** Two new passing tests with zero changes to bootstrap.

## Common Issues

**`PHP Fatal error: Undefined constant "PRORATE_BILLING"`**
Add to `tests/bootstrap.php` before the `require` line:
```php
if (!defined('PRORATE_BILLING')) { define('PRORATE_BILLING', 1); }
```

**`Class 'Detain\MyAdminWebhosting\Plugin' not found`**
Verify autoload is registered: `composer dump-autoload` then re-run.

**`exec(): Cannot execute a blank command`** in lint test
`escapeshellarg()` returned empty — `self::$apiFile` was not set. Ensure `setUpBeforeClass()` runs before any test accesses `self::$apiFile`.

**Test marked risky: "This test did not perform any assertions"**
Add `$this->assertTrue(true);` at the end of any test for a no-op handler (e.g., `apiRegister()`).

**`ReflectionException: Method getType(): Return value must be of type ReflectionNamedType`**
The parameter has no type hint in the source. Guard with `$this->assertNotNull($param->getType())` before calling `->getName()`.

**`assertCount(4, ...) failed, got 3`** on `testGetHooksCount`
A hook was removed from `Plugin::getHooks()`. Update the expected count or re-add the hook — don't lower the assertion to match broken code.

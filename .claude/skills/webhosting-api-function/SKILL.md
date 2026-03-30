---
name: webhosting-api-function
description: Creates a new procedural API function in `src/api.php` following the `api_place_buy_website` / `api_validate_buy_website` pattern. Handles `get_custid()`, `function_requirements()`, destructured return arrays, and `$return['status']`/`$return['status_text']` response shape. Use when user says 'add API function', 'new api_*', 'expose endpoint in api.php'. Do NOT use for class-based methods in `src/Plugin.php` or `include/Api/` in the parent myadmin project.
---
# webhosting-api-function

## Critical

- `src/api.php` is **procedural, no namespace**. Never add `namespace` declarations.
- All functions must be named `api_*` (e.g., `api_place_buy_website`, `api_validate_buy_website`).
- Every function **must** resolve the customer via `get_custid($GLOBALS['tf']->session->account_id, 'vps')` as the very first line of the function body.
- Every function **must** return `$return` with exactly two keys: `$return['status']` (`'ok'` or `'error'`) and `$return['status_text']`.
- Lazy-load helpers with `function_requirements('helper_name')` — never `require`/`include` directly.
- After adding a new function, register it in `getRequirements()` in `src/Plugin.php` or it will never be callable.

## Instructions

### Step 1 — Read `src/api.php` to understand current state

Read the full file before editing to confirm the function name does not already exist and to preserve the existing file header.

Verify: `function api_your_function_name` does not appear in the file before proceeding.

### Step 2 — Write the docblock + function signature

Append to `src/api.php` immediately after the last closing `}`. Follow this exact shape:

```php
/**
* <One-line description of what this function does>
*
* @param <type> $param1
* @param <type> $param2
* @return array
*/
function api_your_function_name($param1, $param2)
{
```

- Use `@return array` (the shape is always the `$return` status array).
- Optional parameters go last with a default: `$script = 0`.

Verify: `php -l src/api.php` exits 0 after adding the signature.

### Step 3 — Resolve customer ID (first line of body)

```php
    $custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
```

This must be the **first statement** in the function body, before any logic.

### Step 4 — Lazy-load dependencies with `function_requirements()`

For each helper function this API function calls, add a `function_requirements()` call:

```php
    function_requirements('validate_buy_website');
```

If the action phase needs a second helper (like `place_buy_website`), load it inside the `if ($continue === true)` block:

```php
    if ($continue === true) {
        function_requirements('place_buy_website');
        // ... call the helper ...
    }
```

### Step 5 — Destructure helper results and build response

For **action** functions (e.g., `api_place_buy_website` pattern):

```php
    [$continue, $errors, /* ...other vars... */] = validate_buy_website(
        $custid, $param1, $param2
    );
    if ($continue === true) {
        function_requirements('place_buy_website');
        [$total_cost, $iid, /* ...other vars... */] = place_buy_website(/* args */);
        $return['status'] = 'ok';
        $return['status_text'] = $serviceid;   // or relevant scalar
    } else {
        $return['status'] = 'error';
        $return['status_text'] = implode("\n", $errors);
    }
    return $return;
```

For **validation-only** functions (e.g., `api_validate_buy_website` pattern):

```php
    [$continue, $errors, /* ...other vars... */] = validate_buy_website(
        $custid, $param1, $param2
    );
    $return = [];
    if ($continue === true) {
        $return['status'] = 'ok';
        $return['status_text'] = '';
    } else {
        $return['status'] = 'error';
        $return['status_text'] = implode("\n", $errors);
    }
    return $return;
```

Verify: `php -l src/api.php` exits 0.

### Step 6 — Register the function in `getRequirements()` in `src/Plugin.php`

Open `src/Plugin.php` and add a `add_requirement()` line inside `getRequirements()`:

```php
public static function getRequirements(GenericEvent $event)
{
    $loader = $event->getSubject();
    $loader->add_requirement('api_place_buy_website', 'src/api.php');
    $loader->add_requirement('api_validate_buy_website', 'src/api.php');
    // ADD YOUR NEW FUNCTION HERE:
    $loader->add_requirement('api_your_function_name', 'src/api.php');
}
```

Verify: `php -l src/Plugin.php` exits 0.

### Step 7 — Run tests

```bash
phpunit --configuration phpunit.xml.dist
```

All existing tests in `tests/ApiTest.php` and `tests/PluginTest.php` must remain green.

Note: `ApiTest::testFileDefinesExactlyTwoFunctions` asserts exactly 2 functions — **update or extend that test** to allow the new count before running.

## Examples

**User says:** "Add an API function to suspend a website by ID"

**Actions taken:**

1. Read `src/api.php` — confirmed `api_suspend_website` does not exist.
2. Appended to `src/api.php`:

```php
/**
* Suspends a webhosting account by service ID
*
* @param int $service_id
* @return array
*/
function api_suspend_website($service_id)
{
    $custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
    function_requirements('validate_website_service');
    [$continue, $errors, $serviceInfo] = validate_website_service($custid, $service_id);
    if ($continue === true) {
        function_requirements('suspend_website');
        suspend_website($serviceInfo);
        $return['status'] = 'ok';
        $return['status_text'] = $service_id;
    } else {
        $return['status'] = 'error';
        $return['status_text'] = implode("\n", $errors);
    }
    return $return;
}
```

3. Added to `getRequirements()` in `src/Plugin.php`:
```php
$loader->add_requirement('api_suspend_website', 'src/api.php');
```

4. Updated `tests/ApiTest.php::testFileDefinesExactlyTwoFunctions` to assert 3.
5. Ran `phpunit --configuration phpunit.xml.dist` — all green.

**Result:** `api_suspend_website` is callable via `function_requirements('api_suspend_website')`.

## Common Issues

**`Fatal error: Call to undefined function get_custid()`**
The function was called outside of the MyAdmin bootstrap context. `src/api.php` is not standalone — it must be loaded via `function_requirements()` from within a running MyAdmin request. Do not call these functions from CLI without the full bootstrap.

**`testFileDefinesExactlyTwoFunctions` fails after adding a function**
The test at `tests/ApiTest.php:314` hardcodes `assertSame(2, $count, ...)`. Change `2` to the new total count of functions in the file.

**`php -l src/api.php` reports `unexpected token "function"`**
A missing closing `}` from the previous function. Count braces — each function body must be closed before the next docblock.

**New function is never found by `function_requirements('api_your_function_name')`**
The `add_requirement()` line was not added to `getRequirements()` in `src/Plugin.php`. Complete Step 6.

**`$return['status_text']` contains raw HTML from errors**
`implode("\n", $errors)` is correct — do not use `<br>` or HTML here. The API layer returns plain text; callers decide how to display it.

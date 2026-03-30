---
name: plugin-hook
description: Adds a new event hook to src/Plugin.php::getHooks() and implements the corresponding static handler accepting a GenericEvent. Follows the run_event() / get_module_settings() / get_module_db() pattern used in loadProcessing(). Use when user says 'add hook', 'listen to event', 'handle dispatcher event', or adds a new event name. Do NOT use for modifying existing lifecycle callbacks already in loadProcessing() (setEnable, setReactivate, setDisable, setTerminate).
---
# plugin-hook

## Critical

- Every handler MUST be `public static function handlerName(GenericEvent $event): void` — no exceptions. Tests in `tests/PluginTest.php` use reflection to assert public+static+GenericEvent signature.
- The hook key in `getHooks()` MUST use `self::$module` for module-scoped events (e.g., `self::$module.'.my_event'`), not a hardcoded string.
- Never use raw `$_GET`/`$_POST` — escape with `$db->real_escape()` or use `make_insert_query()`.
- Do NOT add hooks for lifecycle callbacks (`load_processing`, `setEnable`, `setReactivate`, `setDisable`, `setTerminate`) — those belong inside `loadProcessing()` closures.
- `getHooks()` must remain idempotent (no side effects, returns same array every call).

## Instructions

### Step 1 — Register the hook in `getHooks()`

Open `src/Plugin.php`. Add your event key → handler pair to the returned array:

```php
public static function getHooks()
{
    return [
        'api.register'                        => [__CLASS__, 'apiRegister'],
        'function.requirements'               => [__CLASS__, 'getRequirements'],
        self::$module.'.load_processing'      => [__CLASS__, 'loadProcessing'],
        self::$module.'.settings'             => [__CLASS__, 'getSettings'],
        self::$module.'.my_event'             => [__CLASS__, 'myEventHandler'], // ADD HERE
    ];
}
```

Use `self::$module.'.event_name'` for module-scoped events. Use a plain string like `'global.event_name'` for cross-module events.

Verify: `Plugin::getHooks()` returns an array containing your new key before proceeding.

### Step 2 — Implement the handler method

Add a new `public static` method in `src/Plugin.php` using this exact signature:

```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function myEventHandler(GenericEvent $event): void
{
    $subject = $event->getSubject();        // extract the event subject
    $settings = get_module_settings(self::$module);
    $db = get_module_db(self::$module);

    // Access event arguments with $event['key'] (ArrayAccess)
    // e.g. $type = $event['type'];

    // DB query pattern:
    $db->query(
        "SELECT * FROM {$settings['TABLE']} WHERE {$settings['PREFIX']}_id = '{$id}'",
        __LINE__, __FILE__
    );
    if ($db->num_rows() > 0) {
        $db->next_record(MYSQL_ASSOC);
        $row = $db->Record;
    }

    // Log pattern:
    myadmin_log(self::$module, 'info', 'Message here', __LINE__, __FILE__, self::$module);
}
```

For read-only event introspection (no DB): omit `$db` and `$settings`. Only initialize what you use.

Verify: `method_exists(Plugin::class, 'myEventHandler')` returns true.

### Step 3 — Dispatch the event from a caller (if you own the dispatch site)

To fire the event from elsewhere in the codebase:

```php
// Simple dispatch via run_event() helper:
$result = run_event('webhosting.my_event', $subject, self::$module);

// Or direct dispatcher with arguments:
$subevent = new GenericEvent($subject, [
    'field1' => $value1,
    'type'   => $type,
]);
$GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.my_event');
```

Wrap dispatcher calls in `try/catch (\Exception $e)` when connecting to external services, matching the pattern in `loadProcessing::setTerminate`:

```php
try {
    $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.my_event');
} catch (\Exception $e) {
    myadmin_log(self::$module, 'info', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module);
    // send admin error email if needed
}
```

Verify: no uncaught exceptions during dispatch in test/dev environment.

### Step 4 — Run tests

```bash
phpunit --configuration phpunit.xml.dist
```

If you added a hook, the existing `testGetHooksCount` test in `tests/PluginTest.php` **will fail** because it asserts exactly 4 hooks. Update it:

```php
// tests/PluginTest.php — testGetHooksCount()
$this->assertCount(5, $hooks); // was 4
```

Also add your new key to `testGetHooksContainsExpectedKeys()`:

```php
$expectedKeys = [
    'api.register',
    'function.requirements',
    'webhosting.load_processing',
    'webhosting.settings',
    'webhosting.my_event', // ADD
];
```

Verify: `phpunit --configuration phpunit.xml.dist` passes with no failures.

## Examples

**User says:** "Add a hook to handle `webhosting.provisioned` events and log the new service ID."

**Actions taken:**

1. Add to `getHooks()` in `src/Plugin.php`:
```php
self::$module.'.provisioned' => [__CLASS__, 'onProvisioned'],
```

2. Add handler in `src/Plugin.php`:
```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function onProvisioned(GenericEvent $event): void
{
    $settings = get_module_settings(self::$module);
    $serviceInfo = $event->getSubject();
    $serviceId = $serviceInfo[$settings['PREFIX'].'_id'];
    myadmin_log(self::$module, 'info', 'Website provisioned: '.$serviceId, __LINE__, __FILE__, self::$module);
}
```

3. Update `tests/PluginTest.php`:
```php
// testGetHooksCount: assertCount(5, $hooks)
// testGetHooksContainsExpectedKeys: add 'webhosting.provisioned'
```

4. Run `phpunit --configuration phpunit.xml.dist` — all tests pass.

**Result:** Hook registered, handler invoked on every `webhosting.provisioned` dispatch.

## Common Issues

**`testGetHooksCount` fails with `assertCount(4)` after adding hook:**
Update the assertion in `tests/PluginTest.php::testGetHooksCount()` to match the new count. The test intentionally enforces an exact count.

**`testGetHooksMethodsExist` fails:**
The method name in `getHooks()` does not exactly match the method defined on the class. Check spelling — `[__CLASS__, 'myHandler']` must match `public static function myHandler(...)`.

**`testGetRequirementsCallsAddRequirement` fails after adding `getRequirements` logic:**
The test asserts exactly 2 `add_requirement()` calls. If you add a third requirement inside `getRequirements()`, update `assertCount(2, $calls)` to the new count.

**`Call to undefined function get_module_settings()`:**
This function is only available within the MyAdmin runtime. In unit tests, either mock the global or skip integration-level handler tests — see `tests/bootstrap.php` for what globals/functions are shimmed.

**`$event['key']` returns null:**
The `GenericEvent` was dispatched without that argument. Check the dispatch site: `new GenericEvent($subject, ['key' => $value])`. Argument keys are case-sensitive.

**Handler not called on dispatch:**
The hook key in `getHooks()` must exactly match the string passed to `dispatcher->dispatch()` or `run_event()`. A mismatch silently skips the handler.

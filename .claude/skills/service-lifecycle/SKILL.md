---
name: service-lifecycle
description: Implements service lifecycle callbacks (setEnable, setReactivate, setDisable, setTerminate) inside src/Plugin.php::loadProcessing(). Covers DB status updates via get_module_db(), TFSmarty email rendering, \MyAdmin\Mail::adminMail(), myadmin_log() error paths, and GenericEvent subevent dispatch. Use when user says 'implement suspend', 'handle termination', 'add reactivation logic', or modifies loadProcessing(). Do NOT use for adding new hook types or creating new Plugin methods outside of loadProcessing.
---
# service-lifecycle

## Critical

- All four callbacks (`setEnable`, `setReactivate`, `setDisable`, `setTerminate`) must be chained on `$service` and terminated with `->register()` — omitting `->register()` silently skips all callbacks.
- Never use PDO. Always use `$db = get_module_db(self::$module)` and `$db->query(..., __LINE__, __FILE__)`.
- Always validate `$serviceInfo[$settings['PREFIX'].'_custid']` matches session before any mutation.
- `myadmin_log()` signature: `myadmin_log($module, $level, $message, __LINE__, __FILE__, self::$module, $serviceClass->getId())` — the trailing two args are module and service ID.
- The `setTerminate` callback must use the ORM class, not the raw `$serviceInfo` array, for `setServerStatus('deleted')->save()`.

## Instructions

### Step 1 — Resolve context variables (same in every callback)

Every callback starts with these three lines:
```php
$serviceInfo = $service->getServiceInfo();
$settings = get_module_settings(self::$module);
$db = get_module_db(self::$module);
```
For callbacks that need service type metadata, also add:
```php
$serviceTypes = run_event('get_service_types', false, self::$module);
```
Verify `self::$module` equals `'webhosting'` (set at top of class) before proceeding.

### Step 2 — Implement `setEnable`

Update DB status to `'active'`, record history, then call the pending-setup email helper:
```php
->setEnable(function ($service) {
    $serviceTypes = run_event('get_service_types', false, self::$module);
    $serviceInfo = $service->getServiceInfo();
    $settings = get_module_settings(self::$module);
    $db = get_module_db(self::$module);
    $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
    $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
    function_requirements('admin_email_website_pending_setup');
    admin_email_website_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
})
```
Verify the DB query uses the pattern `{$settings['TABLE']}` / `{$settings['PREFIX']}_status` — never hardcode table or column names.

### Step 3 — Implement `setReactivate`

Update status, then render a Smarty template and send via `adminMail()`:
```php
->setReactivate(function ($service) {
    $serviceTypes = run_event('get_service_types', false, self::$module);
    $serviceInfo = $service->getServiceInfo();
    $settings = get_module_settings(self::$module);
    $db = get_module_db(self::$module);
    $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
    $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
    $smarty = new \TFSmarty();
    $smarty->assign('website_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
    $email = $smarty->fetch('email/admin/website_reactivated.tpl');
    $subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
    (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_reactivated.tpl');
})
```
Verify `$smarty->fetch()` path starts with `email/admin/` and the `adminMail()` fourth arg matches that template path.

### Step 4 — Implement `setDisable`

For a stub (suspension with no server-side action), leave the body empty:
```php
->setDisable(function ($service) {
})
```
If suspension requires a server action, follow the same `try/catch` + `myadmin_log()` pattern as `setTerminate` (Step 5).

### Step 5 — Implement `setTerminate`

Load the ORM object, dispatch a subevent, catch exceptions, and finalize on success:
```php
->setTerminate(function ($service) {
    $serviceInfo = $service->getServiceInfo();
    $settings = get_module_settings(self::$module);
    $serviceTypes = run_event('get_service_types', false, self::$module);
    $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
    /** @var \MyAdmin\Orm\Product $class **/
    $serviceClass = new $class();
    $serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
    $subevent = new GenericEvent($serviceClass, [
        'field1'   => $serviceTypes[$serviceClass->getType()]['services_field1'],
        'field2'   => $serviceTypes[$serviceClass->getType()]['services_field2'],
        'type'     => $serviceTypes[$serviceClass->getType()]['services_type'],
        'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
        'email'    => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
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
})
```
Verify `GenericEvent` is imported at the top of `Plugin.php`: `use Symfony\Component\EventDispatcher\GenericEvent;`

### Step 6 — Close the chain

The final `->register()` call must follow the last callback with no semicolons between callbacks:
```php
        })->register();
```

## Examples

**User says:** "Add reactivation logic that sends an email when a webhosting service is reactivated"

**Actions taken:**
1. Open `src/Plugin.php`, locate `loadProcessing()`.
2. In the `setReactivate` closure, add the DB update, `TFSmarty` render of `email/admin/website_reactivated.tpl`, and `adminMail()` call as shown in Step 3.
3. Confirm `email/admin/website_reactivated.tpl` exists in the templates directory; if not, create it.
4. Run `php -l src/Plugin.php` — expect `No syntax errors detected`.

**Result:**
```php
->setReactivate(function ($service) {
    $serviceTypes = run_event('get_service_types', false, self::$module);
    $serviceInfo  = $service->getServiceInfo();
    $settings     = get_module_settings(self::$module);
    $db           = get_module_db(self::$module);
    $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
    $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
    $smarty = new \TFSmarty();
    $smarty->assign('website_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
    $email   = $smarty->fetch('email/admin/website_reactivated.tpl');
    $subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
    (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_reactivated.tpl');
})
```

## Common Issues

**`Call to a member function getServiceInfo() on null`**
Cause: `$service = $event->getSubject()` returned null because the event was dispatched without a subject.
Fix: Confirm the calling code passes the `ServiceHandler` as the event subject: `new GenericEvent($serviceHandler, [...])`.

**`Class '\MyAdmin\Orm\...' not found`**
Cause: `get_orm_class_from_table()` returned an unexpected class name.
Fix: Run `get_orm_class_from_table('websites')` in a REPL/test and verify the returned string maps to an existing class under `include/Orm/`.

**`->register()` is never reached / callbacks silently do nothing**
Cause: Missing `->register()` at the end of the chain, or a `return` statement inside a closure that short-circuits `->setModule(...)`.
Fix: Ensure the chain in `loadProcessing()` ends with `})->register();` and that no closure exits early with a bare `return`.

**`Uncaught Error: Class 'TFSmarty' not found` in `setReactivate`**
Cause: `\TFSmarty` is a global class; missing leading backslash inside a namespace causes resolution failure.
Fix: Use `new \TFSmarty()` and `new \MyAdmin\Mail()` — both need fully-qualified names since `Plugin.php` is in the `Detain\MyAdminWebhosting` namespace.

**Terminate dispatches but `isPropagationStopped()` is always false → falls into error log**
Cause: No listener handled the `webhosting.terminate` event, so propagation was never stopped.
Fix: Register a terminate listener in the appropriate server-driver plugin (ISPconfig or ISPmanager) that calls `$event->stopPropagation()` on success.
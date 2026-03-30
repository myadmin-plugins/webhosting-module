---
name: plugin-settings
description: Registers new admin settings in src/Plugin.php::getSettings() using add_dropdown_setting(), add_text_setting(), add_master_checkbox_setting(), or add_master_label(). Includes the defined('CONST') ? CONST : fallback default pattern and correct setTarget('module')/setTarget('global') scope. Use when user says 'add setting', 'new config option', 'expose admin toggle', 'add dropdown toggle', 'add text config'. Do NOT use for settings outside the webhosting plugin or for runtime service-lifecycle changes.
---
# plugin-settings

## Critical

- **Always** open with `$settings->setTarget('module')` and **always** close with `$settings->setTarget('global')` — missing either leaks scope to all modules.
- Default values for `add_dropdown_setting` and `add_text_setting` **must** use `(defined('CONST_NAME') ? CONST_NAME : fallback)` where `CONST_NAME` is the uppercase version of the setting key. Exception: `outofstock_*` keys use `$settings->get_setting('OUTOFSTOCK_...')` (no `defined()` check).
- Wrap all user-visible strings in `_()` for i18n. `add_master_*` calls use bare strings (see existing code).
- Setting keys are lowercase with underscores (e.g. `website_demo_expire_days`). The corresponding constant is the uppercase equivalent (`WEBSITE_DEMO_EXPIRE_DAYS`).
- Never build INSERT strings manually; settings registration does not touch the DB directly.

## Instructions

1. **Open `src/Plugin.php`** and locate `getSettings(GenericEvent $event)`. Confirm `$settings->setTarget('module')` is the first statement and `$settings->setTarget('global')` is the last.

2. **Choose the right helper** based on what you are adding:

   | Use case | Method |
   |---|---|
   | Yes/No or multi-value toggle | `add_dropdown_setting()` |
   | Numeric or free-text config | `add_text_setting()` |
   | Per-server checkbox (auto-setup eligibility) | `add_master_checkbox_setting()` |
   | Per-server read-only stat label | `add_master_label()` |
   | Per-server editable field | `add_master_text_setting()` |

3. **Add a dropdown setting** (Yes/No toggle):
   ```php
   $settings->add_dropdown_setting(
       self::$module,                          // module slug: 'webhosting'
       _('Group Name'),                        // tab/group label (i18n)
       'website_my_flag',                      // setting key (lowercase_underscore)
       _('Human-readable Label'),              // field label
       _('One-line description of effect.'),   // field help text
       (defined('WEBSITE_MY_FLAG') ? WEBSITE_MY_FLAG : '0'),  // current/default value
       ['0', '1'],                             // option values
       ['No', 'Yes']                           // option labels
   );
   ```
   Verify: `strtoupper('website_my_flag')` === `'WEBSITE_MY_FLAG'` — the constant and key must match.

4. **Add a text setting** (numeric or string config):
   ```php
   $settings->add_text_setting(
       self::$module,
       _('Group Name'),
       'website_my_limit',
       _('Human-readable Label'),
       _('Description of what this number controls.'),
       (defined('WEBSITE_MY_LIMIT') ? WEBSITE_MY_LIMIT : 100)  // integer default
   );
   ```

5. **Add an out-of-stock toggle** (special case — uses `get_setting()` not `defined()`):
   ```php
   $settings->add_dropdown_setting(
       self::$module,
       _('Out of Stock'),
       'outofstock_webhosting_mytype',
       _('Out Of Stock MyType Webhosting'),
       _('Enable/Disable Sales Of This Type'),
       $settings->get_setting('OUTOFSTOCK_WEBHOSTING_MYTYPE'),
       ['0', '1'],
       ['No', 'Yes']
   );
   ```

6. **Add a per-server master setting** (Server Settings group):
   ```php
   // Checkbox — marks server as eligible for auto-setup
   $settings->add_master_checkbox_setting(
       self::$module, 'Server Settings', self::$module,
       'available', 'website_available',
       'Auto-Setup', '<p>Choose which servers are used for auto-server Setups.</p>'
   );

   // Read-only label — shows a computed column from the server table
   $settings->add_master_label(
       self::$module, 'Server Settings', self::$module,
       'my_stat', 'My Stat Label',
       '<p>Description of the stat.</p>',
       'website_my_col as my_stat'  // SQL expression aliased to field name
   );

   // Editable text field per server
   $settings->add_master_text_setting(
       self::$module, 'Server Settings', self::$module,
       'max_sites', 'website_max_sites',
       'Max Websites', '<p>The Maximum number of Websites per server.</p>'
   );
   ```

7. **Placement order within `getSettings()`** — follow the existing order:
   1. `setTarget('module')`
   2. Out-of-stock dropdowns (`_('Out of Stock')` group)
   3. Costs & Limits settings
   4. Feature-specific groups (e.g. `_('Webhosting Demo')`)
   5. Server Settings (`add_master_*` calls)
   6. `setTarget('global')`

8. **Verify syntax** after editing:
   ```bash
   php -l src/Plugin.php
   ```

9. **Run tests** to confirm nothing regressed:
   ```bash
   vendor/bin/phpunit
   ```

## Examples

**User says:** "Add a setting to enable maintenance mode for webhosting"

**Actions taken:**
- Identify type: Yes/No toggle → `add_dropdown_setting()`
- Key: `website_maintenance_mode`, constant: `WEBSITE_MAINTENANCE_MODE`, default: `'0'`
- Group: `_('Costs & Limits')` (or a new group string if it doesn't fit existing ones)

**Result — inserted before the master checkbox block:**
```php
$settings->add_dropdown_setting(
    self::$module,
    _('Costs & Limits'),
    'website_maintenance_mode',
    _('Maintenance Mode'),
    _('Enable/Disable webhosting maintenance mode for all customers.'),
    (defined('WEBSITE_MAINTENANCE_MODE') ? WEBSITE_MAINTENANCE_MODE : '0'),
    ['0', '1'],
    ['No', 'Yes']
);
```

**User says:** "Add a text setting for the max number of addon domains"

```php
$settings->add_text_setting(
    self::$module,
    _('Costs & Limits'),
    'website_max_addon_domains',
    _('Max Addon Domains'),
    _('Maximum number of addon domains allowed per website account.'),
    (defined('WEBSITE_MAX_ADDON_DOMAINS') ? WEBSITE_MAX_ADDON_DOMAINS : 10)
);
```

## Common Issues

**Scope bleeds into other modules (all settings appear under webhosting):**
- `setTarget('global')` is missing or was accidentally removed. Ensure it is the very last statement in `getSettings()` before the closing brace.

**Setting default is always the fallback, never the stored value:**
- For non-`outofstock_*` settings you used `$settings->get_setting(...)` instead of `(defined('CONST') ? CONST : fallback)`. Switch to the `defined()` pattern.
- For `outofstock_*` settings you used `defined()` instead of `$settings->get_setting('OUTOFSTOCK_...')`. Swap to `get_setting()`.

**`php -l src/Plugin.php` reports `Parse error: syntax error, unexpected ','`:**
- Trailing comma after the last argument of a `add_dropdown_setting()` call. Remove it — PHP 7.4 does not allow trailing commas in function calls.

**Setting key constant mismatch — value never loads from config:**
- Key `website_demo_days` must produce constant `WEBSITE_DEMO_DAYS`. Verify `strtoupper(key)` exactly matches the `defined()` argument. A typo like `WEBSITE_DEMO_DAY` (missing S) silently falls through to the default.

**`add_master_label` SQL alias not matching field name:**
- The alias in the SQL expression (e.g. `website_hdfree as hdfree`) must match the `field` argument (`'hdfree'`). A mismatch produces no output in the UI with no error.

**PHPUnit failure after adding setting:**
- Run `vendor/bin/phpunit` and check `tests/PluginTest.php`. If it tests `getSettings()` event dispatch, the new call must not throw — ensure no undefined function is called at registration time.
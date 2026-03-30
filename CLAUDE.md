# MyAdmin Webhosting Module

Composer plugin (`detain/myadmin-webhosting-module`) for the MyAdmin control panel. Manages shared hosting lifecycle — provisioning, suspension, reactivation, termination — across ISPconfig and ISPmanager.

## Commands

```bash
composer install                  # install deps including phpunit
vendor/bin/phpunit                # run all tests (phpunit.xml.dist)
php -l src/api.php                # syntax-check api file
php -l src/Plugin.php             # syntax-check plugin class
```

## Architecture

- **Plugin entry**: `src/Plugin.php` · namespace `Detain\MyAdminWebhosting\` · registers hooks via `getHooks()`
- **API functions**: `src/api.php` · procedural, no namespace · exposes `api_place_buy_website` and `api_validate_buy_website`
- **Tests**: `tests/ApiTest.php` · `tests/PluginTest.php` · bootstrap at `tests/bootstrap.php`
- **Autoload**: PSR-4 `Detain\MyAdminWebhosting\` → `src/` · test PSR-4 `Detain\MyAdminWebhosting\Tests\` → `tests/`
- **CI/CD**: `.github/` workflows automate test runs and code quality checks on push and pull requests
- **IDE Config**: `.idea/` contains JetBrains project settings including `inspectionProfiles/`, `deployment.xml`, and `encodings.xml`

## Module Constants

| Constant | Value |
|---|---|
| `TABLE` | `'websites'` |
| `PREFIX` | `'website'` |
| `TBLNAME` | `'Websites'` |
| `TITLE_FIELD` | `'website_hostname'` |
| `TITLE_FIELD2` | `'website_username'` |

## Plugin Pattern

`src/Plugin.php` registers four hooks in `getHooks()`:

```php
public static function getHooks() {
    return [
        'api.register'                        => [__CLASS__, 'apiRegister'],
        'function.requirements'               => [__CLASS__, 'getRequirements'],
        self::$module.'.load_processing'      => [__CLASS__, 'loadProcessing'],
        self::$module.'.settings'             => [__CLASS__, 'getSettings'],
    ];
}
```

`getRequirements()` maps function names to `src/api.php` via `add_requirement()`:
```php
$loader->add_requirement('api_place_buy_website', 'src/api.php');
```

## API Function Pattern

Both functions in `src/api.php` follow this structure:
1. `get_custid($GLOBALS['tf']->session->account_id, 'vps')` to resolve customer
2. `function_requirements('validate_buy_website')` to lazy-load helpers
3. Destructure result array from `validate_buy_website()`
4. Return `$return['status']` = `'ok'`|`'error'` and `$return['status_text']`

## Service Lifecycle (`loadProcessing`)

Callbacks set on `$service` in `src/Plugin.php::loadProcessing()`:
- `setEnable` — set `{PREFIX}_status='active'`, call `admin_email_website_pending_setup()`
- `setReactivate` — set status active, fetch `email/admin/website_reactivated.tpl` via `TFSmarty`, send via `\MyAdmin\Mail::adminMail()`
- `setDisable` — empty stub
- `setTerminate` — dispatch `webhosting.terminate` subevent; on exception send `admin/website_connect_error.tpl`; on success call `setServerStatus('deleted')->save()`

## Settings Registration

`getSettings()` in `src/Plugin.php` uses:
- `add_dropdown_setting()` for `outofstock_webhosting*` flags and limited package enable
- `add_text_setting()` for numeric config (limits, demo days)
- `add_master_checkbox_setting()` / `add_master_text_setting()` / `add_master_label()` for per-server controls
- Wrap setting default with `defined('CONST') ? CONST : fallback` pattern

## Testing Conventions

- `tests/bootstrap.php` defines `PRORATE_BILLING` constant before autoload
- `tests/ApiTest.php` reads `src/api.php` as a string and asserts on structure (no execution)
- Assert function signatures, docblocks, `$return['status']` keys, `function_requirements()` calls
- `phpunit.xml.dist` at repo root configures test suite

## Coding Conventions

- Tabs for indentation (see `.scrutinizer.yml` `use_tabs: true`)
- camelCase for parameters and properties
- `src/api.php` must have no namespace declaration
- `src/api.php` must define exactly the functions registered in `getRequirements()`
- Log via `myadmin_log('webhosting', $level, $message, __LINE__, __FILE__, self::$module, $id)`
- DB queries use `get_module_db(self::$module)` — never PDO

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->

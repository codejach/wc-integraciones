# AGENTS.md — WooCommerce Integraciones (`wc-integraciones`)

Machine-oriented context for AI agents. Read this before editing code. Identifiers below are
exact and were extracted from the current source; trust them over prose.

## 1. Identity (from `wc-integraciones.php` header)

| Key | Value |
|---|---|
| Plugin Name | `WooCommerce Integraciones` |
| Slug / `$plugin_name` | `wc-integraciones` |
| Version | `1.0.10` (const `WC_INTEGRACIONES_VERSION`) |
| Text Domain (header) | `wc-integraciones` |
| Domain Path | `/languages` |
| Package (docblock) | `Wc_Integraciones` |

The version lives in TWO places (`Version:` header + `define()`); keep them in sync.
Design docs live in `plans/` (gitignored).

## 2. Bootstrap flow (`wc-integraciones.php`)

1. `WC_INTEGRACIONES_VERSION` defined = `'1.0.10'`.
2. `WC_INTEGRACIONES_ENV` defined = `APP_ENV` (constant, else `getenv('APP_ENV')`), else `'prod'`.
3. `activate_wc_integraciones()` / `deactivate_wc_integraciones()` registered via
   `register_activation_hook` / `register_deactivation_hook`.
4. Requires `includes/class-wc-integraciones.php`.
5. `run_wc_integraciones()` instantiates `Wc_Integraciones` and calls `->run()` (Loader `run()`).

## 3. File map (purpose of each file)

```
wc-integraciones.php                          bootstrap + env const
uninstall.php                                 stub (only WP_UNINSTALL_PLUGIN guard; does nothing)
index.php                                     silent access guard
includes/
  class-wc-integraciones.php                  core class: loads deps, wires Loader hooks, $config
  class-wc-integraciones-loader.php           Wc_Integraciones_Loader (add_action/add_filter/run)
  class-wc-integraciones-i18n.php             Wc_Integraciones_i18n → load_plugin_textdomain('wc-integraciones')
  class-wc-integraciones-activator.php        DB schema (dbDelta) + maybe_upgrade() idempotent migrations
  class-wc-integraciones-deactivator.php      clears legacy cron + unschedules API log purge
  class-wc-integraciones-config.php           Wc_Integraciones_Config (env config loader, get/get_env/is_prod)
  class-wc-integraciones-meli.php             Wc_Integraciones_Meli (token, HTTP wrapper, stock, dev flags)
  class-wc-integraciones-api-logger.php       Wc_Integraciones_Api_Logger (API log: insert, query, redact, purge)
admin/
  class-wc-integraciones-admin.php            Wc_Integraciones_Admin (menus, OAuth, sync, REST, API tab)
  partials/view.php                           main "Integraciones" page
  partials/mercadolibre/view.php              ML page tabs shell
  partials/mercadolibre/configuration/view.php
  partials/mercadolibre/meli2wc/view.php      SKU assignment + sync toggle table
  partials/mercadolibre/log/view.php          inventory log table
  partials/mercadolibre/api/view.php          API tab: token panel + API log
  js/wc-integraciones-admin.js                SKU selector (5s timer) + toggle-sync fetch calls
public/
  class-wc-integraciones-public.php           Wc_Integraciones_Public (webhooks, WC stock hooks, AS)
config/
  config.dev.php / config.qa.php / config.prod.php  → return [] (see §8)
languages/wc-integraciones.pot
```

## 4. Class & symbol index (EXACT casing, do not "fix" blindly)

All classes use the `Wc_Integraciones_` prefix. Only the `i18n` casing is special.

| Class | Prefix used |
|---|---|
| `Wc_Integraciones` | `Wc_` |
| `Wc_Integraciones_Loader` | `Wc_` |
| `Wc_Integraciones_i18n` | `Wc_` + **lowercase `i`** in `i18n` |
| `Wc_Integraciones_Admin` | `Wc_` |
| `Wc_Integraciones_Public` | `Wc_` |
| `Wc_Integraciones_Activator` | `Wc_` |
| `Wc_Integraciones_Deactivator` | `Wc_` |
| `Wc_Integraciones_Config` | `Wc_` |
| `Wc_Integraciones_Meli` | `Wc_` |
| `Wc_Integraciones_Api_Logger` | `Wc_` |

- Functions: `activate_wc_integraciones`, `deactivate_wc_integraciones`,
  `run_wc_integraciones`.
- JS global (localized): `wc_integraciones_admin` (contains `rest_nonce`).
- `Wc_Integraciones::__construct()` sets `$this->plugin_name = 'wc-integraciones'` and loads
  `$this->config = Wc_Integraciones_Config::load()` **after** wiring hooks.
- Key methods:
  - `Wc_Integraciones_Meli`: `request()` (HTTP + logging), `obtener_token()`, `actualizar_stock()`,
    `get_dev_settings()`, `is_dev_mode()`, `full_bodies_enabled()`, `is_dry_run()`,
    `reset_dev_settings_cache()`.
  - `Wc_Integraciones_Api_Logger`: `log()`, `get_logs()`, `count_logs()`, `purge()`, `clear_all()`,
    `contexts()`, `table_name()`.
  - `Wc_Integraciones_Admin`: `display_tab_content()` (tab router), `sync_meli_publicaciones()`,
    `guardar_meli_configuracion()`, `handle_meli_oauth_callback()`, `display_api()`,
    `handle_reveal_token()`, `handle_clear_api_log()`.
  - `Wc_Integraciones_Public`: `handle_meli_webhook()`, `process_meli_webhook_async()`,
    `procesar_notificacion()`, `procesar_order_v2()`, `sincronizar_stock_meli_handler()`,
    `purgar_api_log()`.

## 5. Hook registry (complete)

### Registered via `Wc_Integraciones_Loader` (`includes/class-wc-integraciones.php`)
- `plugins_loaded` → `Wc_Integraciones_i18n::load_plugin_textdomain`
- `plugins_loaded` → `Wc_Integraciones_Activator::maybe_upgrade` (static)
- `admin_enqueue_scripts` → `Wc_Integraciones_Admin::enqueue_styles`
- `admin_enqueue_scripts` → `Wc_Integraciones_Admin::enqueue_scripts`
- `wp_enqueue_scripts` → `Wc_Integraciones_Public::enqueue_styles`
- `wp_enqueue_scripts` → `Wc_Integraciones_Public::enqueue_scripts`

### Registered directly inside constructors (NOT via Loader)
`Wc_Integraciones_Admin::__construct()`:
- `admin_menu` → `add_plugin_admin_menu`
- `admin_post_guardar_meli_configuracion` → `guardar_meli_configuracion`
- `admin_post{SUFFIX}_meli_auth_callback` → `handle_meli_oauth_callback`
  (`SUFFIX` = `''` when prod, `_nopriv` otherwise; see `get_meli_auth_suffix()`)
- `rest_api_init` → `register_sync_toggle_route`
- `rest_api_init` → `register_reveal_token_route`
- `admin_post_wc_integraciones_clear_api_log` → `handle_clear_api_log`

`Wc_Integraciones_Public::__construct()`:
- `rest_api_init` → `register_meli_webhook`
- `rest_api_init` → `register_meli_webhook_async`
- `rest_api_init` → `register_sku_assignment_route`
- `wc_integraciones_procesar_notificacion` → `procesar_notificacion` (1 arg) [Action Scheduler]
- `wc_integraciones_sincronizar_stock_meli` → `sincronizar_stock_meli_handler` (1 arg) [AS]
- `wc_integraciones_purgar_api_log` → `purgar_api_log` (0 args) [AS, diario]
- `woocommerce_updated_product_stock` → `handle_wc_stock_change` (1 arg)
- `woocommerce_product_object_updated_props` → `handle_wc_stock_props_change` (2 args)

> Rule note: the project uses BOTH the WPPB Loader pattern AND direct `add_action` calls.
> New admin/public hooks are conventionally added via direct `add_action` in the constructor.

### Scheduled tasks
- **No WP-Cron events or custom intervals are registered.** Token refresh is lazy
  (`Wc_Integraciones_Meli::obtener_token()`).
- Action Scheduler: recurring `wc_integraciones_purgar_api_log` (daily, group `meli_sync`),
  scheduled by `Activator::activate()` and `maybe_upgrade()`, unscheduled in
  `Deactivator::deactivate()`. All `as_*` calls are `function_exists`-guarded.
- `maybe_upgrade()` and `deactivate()` still call `wp_clear_scheduled_hook('meli_refresh_token_cron')`
  to clean installs from ≤1.0.9. That cron had no handler — do not re-add it.

## 6. REST API surface (namespace `meli/v1`)

| Method | Route | Permission | Handler |
|---|---|---|---|
| POST | `/meli/v1/notifications` | `__return_true` (public) | `Wc_Integraciones_Public::handle_meli_webhook` |
| POST | `/meli/v1/notifications/async` | `__return_true` (public) | `Wc_Integraciones_Public::process_meli_webhook_async` |
| POST | `/meli/v1/asignar-sku` | `manage_options` | `Wc_Integraciones_Public::assign_sku_to_publication` |
| POST | `/meli/v1/toggle-sync` | `manage_options` | `Wc_Integraciones_Admin::handle_toggle_sync` |
| POST | `/meli/v1/reveal-token` | `manage_options` | `Wc_Integraciones_Admin::handle_reveal_token` |

Payloads:
- `asignar-sku`: `{ detalle_id?, publicacion_id?, sku }` — updates `wc_sku` on detalle (if
  `detalle_id>0`) else on publicacion.
- `toggle-sync`: `{ detalle_id?, publicacion_id?, enabled }` — updates `sync_stock_enabled`.
- `reveal-token`: no body; returns `{ success, token }` with the raw `meli_access_token`
  (404 when absent). Admin UI only — do not surface the value elsewhere.

## 7. Database schema (`$wpdb->prefix`, created in `Activator::activate()` via `dbDelta`)

1. `wc_integraciones_settings` — `id`, `client_name` (unique-ish, value `'mercadolibre'`),
   `client_id`, `secret_key`, `user_id` (BIGINT UNSIGNED NULL), `dev_mode` (tinyint),
   `dev_full_bodies` (tinyint, 1.0.10), `dev_dry_run` (tinyint, 1.0.10).
2. `wc_integraciones_meli_publicaciones` — `id` PK, `meli_item_id` UNIQUE, `title`,
   `seller_id`, `price`, `base_price`, `original_price`, `initial_quantity`,
   `available_quantity`, `sold_quantity`, `thumbnail`, `status`, `logistic_type`, `wc_sku`,
   `sync_stock_enabled`, `family_id`, `family_name`, `model_type` (default `'legacy'`),
   `user_product_id`, `date_created`, `INDEX idx_family_id`.
3. `wc_integraciones_meli_publicaciones_detalle` — `id` PK, `publicacion_id` FK→publicaciones
   (CASCADE), `variation_id` (NULLable), `price`, `available_quantity`, `sold_quantity`,
   `user_product_id`, `wc_sku`, `sync_stock_enabled`.
4. `wc_integraciones_meli_variacion_atributos` — `id` PK, `detalle_id` FK→detalle (CASCADE),
   `attribute_id`, `name`, `value_id` (NULLable), `value_name`, `value_type`,
   `UNIQUE (detalle_id, attribute_id, value_id)`.
5. `wc_integraciones_meli_notificaciones` — `id` PK, `topic`, `resource`, `user_id`,
   `raw_json` LONGTEXT, `status` ENUM(`pending`,`processing`,`done`,`error`,`skipped`),
   `attempts`, `processed_at`, `result_message`, `created_at`, `updated_at`,
   indexes on `status`,`topic`,`created_at`.
6. `wc_integraciones_meli_log_inventario` — `id` PK, `product_id`, `sku`, `origin`
   (`'wc'`/`'meli'`), `old_stock`, `new_stock`, `inhibir_sincronizacion_meli`, `description`,
   `created_at`.
7. `wc_integraciones_meli_api_log` — `id` PK, `created_at`, `context` (`oauth`/`stock`/
   `stock_dry_run`/`order`/`sync`), `method`, `endpoint`, `status_code` (NULL on `WP_Error`),
   `duration_ms`, `request_json`, `response_json`, `response_truncated`, `error_message`,
   `user_id` (NULL for background), indexes on `created_at`, `context`, `status_code`.
   Bodies are redacted (tokens, secrets, buyer PII) and capped at 64 KB.

### wp_options used
`meli_access_token`, `meli_refresh_token`, `meli_token_expires` (unix ts),
`wc_integraciones_db_version` (default `'1.0.0'`).

### DB upgrades
`Wc_Integraciones_Activator::maybe_upgrade()` runs on `plugins_loaded`; compares
`wc_integraciones_db_version` vs `WC_INTEGRACIONES_VERSION`. Uses idempotent `SHOW COLUMNS`/
`SHOW INDEX` checks to add `family_id`, `family_name`, `model_type`, `user_product_id`,
`idx_family_id`, and to make `variation_id`/`value_id` NULLable.

### Migration rule (important)
`maybe_upgrade()` **returns early** when `wc_integraciones_db_version >= WC_INTEGRACIONES_VERSION`.
Any schema change therefore requires: bump BOTH version strings (§1) AND add an idempotent
`SHOW COLUMNS`/`SHOW INDEX` check (or `dbDelta`) inside `maybe_upgrade()`.
`dbDelta` here expects canonical SQL: `CREATE TABLE $t (...)` (no `IF NOT EXISTS`),
`PRIMARY KEY  (id)` with two spaces, `KEY` (not `INDEX`) for secondary indexes.
`uninstall.php` is a stub: tables and options survive uninstall.

## 8. Environment config (`Wc_Integraciones_Config`)

- Loads `config/config.{env}.php`; falls back to `config/config.prod.php` if missing.
- Singleton static `$config`; `get($key, $default=null)`; `get_env()`; `is_prod()`.
- Keys (all default to empty string — MUST be filled per environment):
  - `api_ngrok_url` — used for non-prod OAuth redirect URI (`/wp-admin/admin-post.php?action=meli_auth_callback`).
  - `meli_application_id` — webhook validation (optional, only checked if non-empty).
  - `meli_user_id` — webhook validation (optional, only checked if non-empty).
- `is_prod()` also switches hardcoded webhook forward URL in `handle_meli_webhook`:
  - prod → `https://www.ninavestuariosinfantiles.com/wp-json/meli/v1/notifications/async`
  - else → `http://wp_integraciones/wp-json/meli/v1/notifications/async`

## 9. MercadoLibre API surface (all via `Wc_Integraciones_Meli::request()`)

- Auth URL: `https://auth.mercadolibre.com.mx/authorization?response_type=code&client_id={}&redirect_uri={}`
- Token (auth code / refresh): `POST https://api.mercadolibre.com/oauth/token`
- List items: `GET https://api.mercadolibre.com/users/{user_id}/items/search?status=active&limit=&offset=`
- Family members: same endpoint with `search_type=scan&family_id={}`
- Multiget: `GET https://api.mercadolibre.com/items?ids={csv}&attributes={csv}`
- Update stock: `PUT https://api.mercadolibre.com/items/{item_id}[/variations/{variation_id}]`
  body `{ "available_quantity": int }`
- Fetch order: `GET https://api.mercadolibre.com{resource}` (resource e.g. `/orders/...`)

### Logging conventions (`dev_mode`)
- Every ML HTTP call goes through `Wc_Integraciones_Meli::request($method, $url, $args, $context)`.
  It returns the raw `wp_remote_*` result (`array|WP_Error`) and logs. Do not call `wp_remote_*`
  directly for MercadoLibre.
- `$context` must be one of `Wc_Integraciones_Api_Logger::contexts()` (`oauth`, `stock`,
  `stock_dry_run`, `order`, `sync`); register new contexts there so the UI filter lists them.
- A row is written when `dev_mode` is on, OR on error (status ≥ 400 / `WP_Error`), OR when
  `$force = true` (used by dry-run).
- Bodies: stored on errors always; on success only with `dev_full_bodies`. Redaction is
  unconditional (`$sensitive_keys` for tokens/secrets, `$pii_keys` for buyer data) — extend those
  arrays when logging new payload shapes. Truncated at 64 KB with `response_truncated = 1`.
- Endpoints are sanitized (`sanitize_endpoint()`) to strip credentials from query strings.
- Dev flags come from the settings row and are cached in `Wc_Integraciones_Meli::$dev_settings`
  per request. After writing that row, call `reset_dev_settings_cache()`.
- Retention: `purge()` deletes rows older than 7 days (dev_mode + full bodies) or 30 days.
  `clear_all()` backs the "Vaciar log" button (`admin_post_wc_integraciones_clear_api_log`).
- The loopback webhook forward in `handle_meli_webhook` is intentionally NOT wrapped.

## 10. Core data-model concepts

- `model_type` per publicacion: `'legacy'` (has `variations`), `'family'` (`family_id` set,
  no `variations`), `'simple'` (neither).
- `logistic_type === 'fulfillment'` → items are **skipped** (ML manages their stock); SKU/toggle
  UI is hidden for them.
- `wc_sku` is the link between a ML publication/detalle and a WooCommerce product/variation
  (resolved via `wc_get_product_id_by_sku()`).
- `sync_stock_enabled` (0/1) gates the WC→ML direction per detalle/publicacion.
- Dev flags (settings row): `dev_mode`, `dev_full_bodies`, `dev_dry_run` — semantics in §9.
- `Wc_Integraciones_Public::$inhibir_sincronizacion_meli` (static bool) suppresses WC→ML sync
  when the stock change originated from an ML order, preventing loops. Set `true` before
  `$product->save()` and `false` after.

## 11. Synchronization flows

### WC → ML (stock)
1. WooCommerce hook `woocommerce_updated_product_stock` or `woocommerce_product_object_updated_props`
   (when `stock_quantity` in `$updated_props`) → `handle_wc_stock_change` / `handle_wc_stock_props_change`.
2. If `$inhibir_sincronizacion_meli` is true → return.
3. `schedule_stock_sync($product_id)` → `as_enqueue_async_action('wc_integraciones_sincronizar_stock_meli', ['product_id'=>...], 'meli_sync')`.
4. `sincronizar_stock_meli_handler` looks up `meli_item_id`+`variation_id`+`sync_stock_enabled`
   by SKU (detalle JOIN publicaciones, fallback publicaciones), logs intent, and if enabled calls
   `Wc_Integraciones_Meli::actualizar_stock()`. With `dev_dry_run` it returns
   `['dry_run' => true]` and sends nothing.

### ML → WC (orders)
1. ML webhook → `POST /meli/v1/notifications` (`handle_meli_webhook`). Validates `user_id`/
   `application_id` against config (only if non-empty). Forwards body with `blocking=false` to
   `/notifications/async`, returns 200 immediately.
2. `/notifications/async` (`process_meli_webhook_async`): only handles `topic == 'orders_v2'`;
   dedupes by `(topic, resource)`; inserts row with `status='pending'`; enqueues
   `wc_integraciones_procesar_notificacion` (arg `notificacion_id`) via `as_enqueue_async_action`.
3. `procesar_notificacion($id)`: skips if an earlier row with same `(topic, resource)` exists;
   `$procesamiento = -1` if order `status==='cancelled'` else `1`; calls `procesar_order_v2`.
4. `procesar_order_v2`: fetches order; ignores if `date_closed` null or `fulfilled===true`; for
   each `order_items` resolves `wc_sku` (legacy via `user_product_id`+`variation_id`; family via
   `user_product_id` w/ NULL `variation_id`; fallback by `meli_item_id`/`user_product_id`);
   reduces WC stock by `quantity * $procesamiento` (floor 0), with inhibition flag set.

### OAuth connect (admin)
- Config form posts to `admin_post_guardar_meli_configuracion` (nonce `meli_nonce`, action
  `guardar_meli_config`). Secret is masked (`\*+.{3}$` regex keeps stored value).
- "Conectar" builds `get_meli_auth_url()`. Callback `handle_meli_oauth_callback` exchanges
  `code` for tokens and stores `meli_access_token`, `meli_refresh_token`, `meli_token_expires`,
  and `user_id` on the `'mercadolibre'` settings row.

### Token handling (`Wc_Integraciones_Meli::obtener_token`)
- Returns cached `meli_access_token` if `time() < meli_token_expires - 300` (5 min margin).
- Else `renovar_token()` posts `grant_type=refresh_token` using `client_id`/`secret_key` from the
  `wc_integraciones_settings` row and `meli_refresh_token` option.

## 12. Coding rules (project conventions — preserve)

1. WPPB separation: admin logic in `admin/`, public in `public/`, shared in `includes/`. Do not
   mix admin and public logic.
2. OOP classes; prefix classes `Wc_Integraciones_` (the `Wc_` casing is mandatory, never
   `WC_`).
3. Functions/vars/hook tags prefixed `wc_integraciones_`; class files named `class-*.php`.
4. i18n: `__()`/`_e()`/`esc_html__()` for user-facing strings.
5. Security: `sanitize_text_field()`, `intval()`, `esc_html()`, `esc_attr()`, `esc_url()`,
   `wp_kses()`; nonces via `wp_verify_nonce()` + `current_user_can()`.
6. Every PHP entry file begins with `if ( ! defined( 'WPINC' ) ) { die; }` (or `ABSPATH`).
7. MercadoLibre HTTP calls use `Wc_Integraciones_Meli::request()` with a context registered in
   `Wc_Integraciones_Api_Logger::contexts()` (see §9).
8. Schema changes require a version bump (§1) plus an idempotent migration in `maybe_upgrade()` (§7).
9. New admin tab: link in `partials/mercadolibre/view.php`, `case` in `display_tab_content()`,
   partial at `partials/mercadolibre/<tab>/view.php`, loaded from a private `display_<tab>()`
   that prepares the variables the partial uses.

## 13. Known inconsistencies / gotchas (do NOT "fix" without being asked)

- **Text domain mismatch**: header + i18n load `wc-integraciones`, but admin menu strings in
  `class-wc-integraciones-admin.php` use domain `integraciones-woocommerce`. Admin menu strings
  are effectively untranslated.
- **`i18n` class is `Wc_Integraciones_i18n`** (lowercase `i`) — autoloader-free, so casing matters
  only for readability, but keep it exact.
- **`SELECT Id` / `['Id' => ...]` (capital) in `sync_meli_publicaciones`** around
  `$existing_id` — relies on MySQL case-insensitive column names; inconsistent with `id` elsewhere.
- **Config files lack the WPINC guard** (they only `return [...]`), unlike other PHP files.
- **Two hook-registration styles coexist** (Loader vs direct `add_action` in constructors).
- Requires WooCommerce active (`wc_get_product_id_by_sku`, `wc_get_product`, WC stock hooks) and
  Action Scheduler available (`as_enqueue_async_action`) for async paths (guarded with
  `function_exists`).
- **Dev flags are cached per request** in `Wc_Integraciones_Meli::$dev_settings`; writing the
  settings row without `reset_dev_settings_cache()` only takes effect on the next request.
- **`error_log()` still dumps unredacted data**: `procesar_order_v2` logs the full order JSON
  (`🔍 Orden obtenida:`) to the PHP error log. The DB API log is redacted; the error log is not.
- **API log rows appear with `dev_mode` off** (errors only). An empty table does not mean logging
  is disabled.
- **`get_dev_settings()` runs one uncached query per request** on the first ML call (the settings
  row is not a `wp_option`).
- **Purge timezone mismatch**: rows are stamped with `current_time('mysql')` (site TZ) but purged
  against `NOW()` (MySQL TZ), so retention can be off by the TZ offset.
- **Admin partials are hardcoded Spanish** with no `__()` (contradicts rule 4). Match the
  surrounding file instead of introducing translations piecemeal.

## 14. Verifying changes in this workspace

- No PHP binary and no WSL: `php -l` is unavailable. Syntax can be checked by parsing every
  `*.php` with the Node `php-parser` package installed outside the repo.
- No WordPress/WooCommerce runtime or test suite here, so DB migrations, hooks and ML calls
  cannot be executed; verify them by reading the code paths above.
- Admin JS: `node --check admin/js/wc-integraciones-admin.js`.
- Git warns about LF→CRLF on some files; that is expected (`core.autocrlf`).

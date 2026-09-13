# WooCommerce Integraciones - Agent Guidelines & Context

## Project Overview
This project is a WordPress plugin named **WooCommerce Integraciones** (`wc-integraciones`).
**Description:** Administra y sincroniza tus integraciones de WooCommerce con múltiples plataformas.
**Version:** 1.0.9
**Text Domain:** `wc-integraciones`

## Architecture & Structure
The plugin is built using the **WordPress Plugin Boilerplate (WPPB)** structure, ensuring a clear separation of concerns and an object-oriented design.

- `admin/`: Contains all admin-facing functionality (styles, scripts, views, and classes).
- `public/`: Contains all public-facing (frontend) functionality.
- `includes/`: The core of the plugin. Contains the main plugin class (`class-wc-integraciones.php`), activator, deactivator, i18n handler, and the loader (`class-wc-integraciones-loader.php`) which manages hooks.
- `languages/`: Contains `.pot`, `.po`, and `.mo` files for internationalization.

## Key Features & Customizations
- **Environment Handling:** Utilizes `WC_INTEGRACIONES_ENV` constant, falling back to `prod` if `APP_ENV` is not defined.
- **Cron Jobs:** Registers a custom WP Cron schedule interval of every 6 hours (`every_six_hours`).

## Coding Guidelines (Rules)
When assisting with or writing code for this project, agents must adhere to the following rules:

1. **Follow WPPB Standards:** Maintain the existing boilerplate architecture. Do not mix admin and public logic.
2. **Object-Oriented Programming:** Add new functionalities using classes following the WPPB structure. Use the Loader class in `includes/class-wc-integraciones.php` to register `add_action` and `add_filter` hooks.
3. **Naming Conventions:**
   - Use `Wc_Integraciones` as the prefix for classes.
   - Use `wc_integraciones` as the prefix for functions, variables, and hook tags to avoid collisions.
   - File names containing classes should follow the `class-*.php` format.
4. **Internationalization (i18n):** Always use WordPress localization functions (e.g., `__()`, `_e()`) with the `wc-integraciones` text domain for any user-facing strings.
5. **Security:** Sanitize all inputs using functions like `sanitize_text_field()`, `intval()`, etc. Escape all outputs using functions like `esc_html()`, `esc_attr()`, `wp_kses()`.
6. **No Direct Access:** Always ensure files cannot be accessed directly by including:
   ```php
   if ( ! defined( 'WPINC' ) ) {
       die;
   }
   ```

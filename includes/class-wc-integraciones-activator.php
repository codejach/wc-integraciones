<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://codejach.github.io/curriculo/
 * @since      1.0.0
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 * @author     Alberto Chávez <axuan@protonmail.com>
 */
class Wc_Integraciones_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Programar evento cron para refrescar token cada 6 horas
		if (!wp_next_scheduled('meli_refresh_token_cron')) {
			wp_schedule_event(time(), 'every_six_hours', 'meli_refresh_token_cron');
		}

		$table_name = $wpdb->prefix . 'wc_integraciones_settings';
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			client_name varchar(255) NOT NULL,
			client_id varchar(255) NOT NULL,
			secret_key varchar(255) NOT NULL,
			user_id BIGINT UNSIGNED NULL,
			dev_mode tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";


		// Tabla principal
		$table_publicaciones = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		$sql1 = "CREATE TABLE IF NOT EXISTS $table_publicaciones (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			meli_item_id VARCHAR(50) NOT NULL UNIQUE,
			title VARCHAR(255),
			seller_id BIGINT,
			price DECIMAL(10,2),
			base_price DECIMAL(10,2),
			original_price DECIMAL(10,2),
			initial_quantity INT,
			available_quantity INT,
			sold_quantity INT,
			thumbnail VARCHAR(255),
			status VARCHAR(50),
			logistic_type VARCHAR(50),
			wc_sku VARCHAR(100),
			sync_stock_enabled TINYINT(1) DEFAULT 0,
			family_id BIGINT NULL,
			family_name VARCHAR(255) NULL,
			model_type VARCHAR(20) DEFAULT 'legacy',
			user_product_id VARCHAR(100) NULL,
			date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_family_id (family_id)
		) $charset_collate;";


		// Tabla publicaciones detalle
		$table_detalle = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$sql2 = "CREATE TABLE IF NOT EXISTS $table_detalle (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			publicacion_id BIGINT NOT NULL,
			variation_id VARCHAR(50) NULL,
			price DECIMAL(10,2),
			available_quantity INT,
			sold_quantity INT,
			user_product_id VARCHAR(100),
			wc_sku VARCHAR(100),
			sync_stock_enabled TINYINT(1) DEFAULT 0,
			FOREIGN KEY (publicacion_id) REFERENCES $table_publicaciones(id) 
				ON DELETE CASCADE
		) $charset_collate;";

		// Tabla atributos de variaciones
		$table_atributos = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';
		$sql3 = "CREATE TABLE IF NOT EXISTS $table_atributos (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			detalle_id BIGINT NOT NULL,
			attribute_id VARCHAR(100) NOT NULL,
			name VARCHAR(255),
			value_id VARCHAR(100) NULL,
			value_name VARCHAR(255),
			value_type VARCHAR(50),
    		UNIQUE KEY unique_attr (detalle_id, attribute_id, value_id),
			FOREIGN KEY (detalle_id) REFERENCES $table_detalle(id)
				ON DELETE CASCADE
		) $charset_collate;";

		// Tabla notificaciones
		$table_notificaciones = $wpdb->prefix . 'wc_integraciones_meli_notificaciones';
		$sql4 = "CREATE TABLE IF NOT EXISTS $table_notificaciones (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			topic VARCHAR(100) DEFAULT NULL,
			resource VARCHAR(255) DEFAULT NULL,
			user_id BIGINT DEFAULT NULL,
			raw_json LONGTEXT NOT NULL,
			status ENUM('pending', 'processing', 'done', 'error', 'skipped') DEFAULT 'pending',
			attempts INT DEFAULT 0,
			processed_at DATETIME NULL,
			result_message TEXT DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_status (status),
			INDEX idx_topic (topic),
			INDEX idx_created (created_at)
		) $charset_collate;";

		// Tabla Log de Inventario
		$table_log = $wpdb->prefix . 'wc_integraciones_meli_log_inventario';
		$sql5 = "CREATE TABLE IF NOT EXISTS $table_log (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			product_id BIGINT,
			sku VARCHAR(100),
			origin VARCHAR(20),
			old_stock INT,
			new_stock INT,
			inhibir_sincronizacion_meli TINYINT(1) DEFAULT 0,
			description TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;";


		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		dbDelta($sql1);
		dbDelta($sql2);
		dbDelta($sql3);
		dbDelta($sql4);
		dbDelta($sql5);
	}

	/**
	 * Runs database upgrades for existing installations.
	 *
	 * @since    1.0.8
	 */
	public static function maybe_upgrade() {
		$current_version = get_option('wc_integraciones_db_version', '1.0.0');
		if (version_compare($current_version, WC_INTEGRACIONES_VERSION, '>=')) {
			return;
		}

		global $wpdb;
		$table_publicaciones = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		$table_detalle       = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$table_atributos     = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';

		$wpdb->hide_errors();

		// Helper para verificar existencia de columna de forma fiable.
		$column_exists = function ($table, $column) use ($wpdb) {
			$result = $wpdb->get_row(
				$wpdb->prepare("SHOW COLUMNS FROM `{$table}` WHERE Field = %s", $column)
			);
			return !empty($result);
		};

		if (!$column_exists($table_publicaciones, 'family_id')) {
			$wpdb->query("ALTER TABLE $table_publicaciones ADD COLUMN family_id BIGINT NULL");
		}
		if (!$column_exists($table_publicaciones, 'family_name')) {
			$wpdb->query("ALTER TABLE $table_publicaciones ADD COLUMN family_name VARCHAR(255) NULL");
		}
		if (!$column_exists($table_publicaciones, 'model_type')) {
			$wpdb->query("ALTER TABLE $table_publicaciones ADD COLUMN model_type VARCHAR(20) DEFAULT 'legacy'");
		}
		if (!$column_exists($table_publicaciones, 'user_product_id')) {
			$wpdb->query("ALTER TABLE $table_publicaciones ADD COLUMN user_product_id VARCHAR(100) NULL");
		}

		// Make sure index exists on family_id.
		$has_family_index = (bool) $wpdb->get_row(
			$wpdb->prepare("SHOW INDEX FROM `{$table_publicaciones}` WHERE Key_name = %s", 'idx_family_id')
		);
		if (!$has_family_index) {
			$wpdb->query("ALTER TABLE $table_publicaciones ADD INDEX idx_family_id (family_id)");
		}

		// Allow nullable variation_id for family items.
		$nullable_check = $wpdb->get_row(
			$wpdb->prepare("SHOW COLUMNS FROM `{$table_detalle}` WHERE Field = %s", 'variation_id')
		);
		if ($nullable_check && strtoupper($nullable_check->Null) !== 'YES') {
			$wpdb->query("ALTER TABLE $table_detalle MODIFY COLUMN variation_id VARCHAR(50) NULL");
		}

		// Allow nullable value_id for family item attributes.
		$nullable_check = $wpdb->get_row(
			$wpdb->prepare("SHOW COLUMNS FROM `{$table_atributos}` WHERE Field = %s", 'value_id')
		);
		if ($nullable_check && strtoupper($nullable_check->Null) !== 'YES') {
			$wpdb->query("ALTER TABLE $table_atributos MODIFY COLUMN value_id VARCHAR(100) NULL");
		}

		$wpdb->show_errors();

		update_option('wc_integraciones_db_version', WC_INTEGRACIONES_VERSION);
	}
}

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
			date_created DATETIME DEFAULT CURRENT_TIMESTAMP
		) $charset_collate;";


		// Tabla publicaciones detalle
		$table_detalle = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$sql2 = "CREATE TABLE IF NOT EXISTS $table_detalle (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			publicacion_id BIGINT NOT NULL,
			variation_id VARCHAR(50) NOT NULL,
			price DECIMAL(10,2),
			available_quantity INT,
			sold_quantity INT,
			user_product_id VARCHAR(100),
			wc_sku VARCHAR(100),
			FOREIGN KEY (publicacion_id) REFERENCES $table_publicaciones(id) 
				ON DELETE CASCADE
		) $charset_collate;";

		// Tabla atributos de variaciones
		$table_atributos = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';
		$sql3 = "CREATE TABLE IF NOT EXISTS $table_atributos (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			detalle_id BIGINT NOT NULL,
			attribute_id VARCHAR(100),
			name VARCHAR(255),
			value_id VARCHAR(100),
			value_name VARCHAR(255),
			value_type VARCHAR(50),
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


		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		dbDelta($sql1);
		dbDelta($sql2);
		dbDelta($sql3);
		dbDelta($sql4);
	}
}

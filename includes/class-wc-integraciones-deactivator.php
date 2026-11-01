<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://https://codejach.github.io/curriculo/
 * @since      1.0.0
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 * @author     Alberto Chávez <axuan@protonmail.com>
 */
class Wc_Integraciones_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Limpieza legacy: el evento meli_refresh_token_cron fue eliminado por no tener callback.
		wp_clear_scheduled_hook('meli_refresh_token_cron');

		// Detener la purga del log de API si Action Scheduler está disponible.
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions('wc_integraciones_purgar_api_log');
		}
	}

}

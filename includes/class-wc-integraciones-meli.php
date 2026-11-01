<?php 

/**
 * Register all actions and filters for the plugin
 *
 * @link       https://https://codejach.github.io/curriculo/
 * @since      1.0.0
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Integración con la API de MercadoLibre: token, peticiones HTTP y stock.
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 * @author     Alberto Chávez <axuan@protonmail.com>
 */
class Wc_Integraciones_Meli {

	/**
	 * Caché estática de los flags de desarrollo.
	 *
	 * @var array|null
	 */
	private static $dev_settings = null;

	/**
	 * Devuelve los flags de desarrollo configurados.
	 *
	 * @since  1.0.10
	 * @return array{dev_mode:int,dev_full_bodies:int,dev_dry_run:int}
	 */
	public static function get_dev_settings() {
		if ( null !== self::$dev_settings ) {
			return self::$dev_settings;
		}

		global $wpdb;

		self::$dev_settings = array(
			'dev_mode'        => 0,
			'dev_full_bodies' => 0,
			'dev_dry_run'     => 0,
		);

		$row = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}wc_integraciones_settings
			 WHERE client_name = 'mercadolibre'"
		);

		if ( $row ) {
			self::$dev_settings['dev_mode']        = isset( $row->dev_mode ) ? (int) $row->dev_mode : 0;
			self::$dev_settings['dev_full_bodies'] = isset( $row->dev_full_bodies ) ? (int) $row->dev_full_bodies : 0;
			self::$dev_settings['dev_dry_run']     = isset( $row->dev_dry_run ) ? (int) $row->dev_dry_run : 0;
		}

		return self::$dev_settings;
	}

	/**
	 * Limpia la caché de flags (tras guardar la configuración).
	 *
	 * @since 1.0.10
	 */
	public static function reset_dev_settings_cache() {
		self::$dev_settings = null;
	}

	/**
	 * ¿Está activo el modo desarrollo?
	 *
	 * @since  1.0.10
	 * @return bool
	 */
	public static function is_dev_mode() {
		$settings = self::get_dev_settings();

		return ! empty( $settings['dev_mode'] );
	}

	/**
	 * ¿Se guardan todos los cuerpos de respuesta?
	 *
	 * @since  1.0.10
	 * @return bool
	 */
	public static function full_bodies_enabled() {
		$settings = self::get_dev_settings();

		return ! empty( $settings['dev_full_bodies'] );
	}

	/**
	 * ¿Las escrituras hacia MercadoLibre se simulan?
	 *
	 * @since  1.0.10
	 * @return bool
	 */
	public static function is_dry_run() {
		$settings = self::get_dev_settings();

		return ! empty( $settings['dev_dry_run'] );
	}

	/**
	 * Ejecuta una petición HTTP contra MercadoLibre y la registra en el log.
	 *
	 * Devuelve la misma estructura que wp_remote_* para no alterar a los
	 * consumidores existentes.
	 *
	 * @since 1.0.10
	 *
	 * @param string $method  GET, POST, PUT, DELETE...
	 * @param string $url     URL completa.
	 * @param array  $args    Argumentos de wp_remote_*.
	 * @param string $context Contexto para el log.
	 * @return array|WP_Error
	 */
	public function request( $method, $url, $args = array(), $context = 'api' ) {
		$method  = strtoupper( (string) $method );
		$started = microtime( true );

		if ( 'GET' === $method ) {
			$response = wp_remote_get( $url, $args );
		} elseif ( 'POST' === $method ) {
			$response = wp_remote_post( $url, $args );
		} else {
			$args['method'] = $method;
			$response       = wp_remote_request( $url, $args );
		}

		$duration = (int) round( ( microtime( true ) - $started ) * 1000 );

		$this->log_response( $context, $method, $url, $args, $response, $duration );

		return $response;
	}

	/**
	 * Registra la petición en el log de desarrollo.
	 *
	 * @param string          $context  Contexto.
	 * @param string          $method   Método HTTP.
	 * @param string          $url      URL.
	 * @param array           $args     Argumentos originales.
	 * @param array|WP_Error  $response Respuesta.
	 * @param int             $duration Duración en ms.
	 */
	private function log_response( $context, $method, $url, $args, $response, $duration ) {
		$is_error = is_wp_error( $response );

		$status = $is_error ? null : (int) wp_remote_retrieve_response_code( $response );
		$error  = $is_error ? $response->get_error_message() : null;

		$request_body = null;
		if ( isset( $args['body'] ) && '' !== $args['body'] ) {
			$request_body = $this->decode_body( $args['body'] );
		}

		$response_body = null;
		if ( ! $is_error ) {
			$raw_body = wp_remote_retrieve_body( $response );
			if ( null !== $raw_body && '' !== $raw_body ) {
				$response_body = $this->decode_body( $raw_body );
			}
		}

		Wc_Integraciones_Api_Logger::log(
			$context,
			$method,
			$url,
			$status,
			$duration,
			$request_body,
			$response_body,
			$error
		);
	}

	/**
	 * Decodifica un cuerpo JSON; si no lo es, devuelve el valor original.
	 *
	 * @param mixed $body Cuerpo.
	 * @return mixed
	 */
	private function decode_body( $body ) {
		if ( is_array( $body ) || is_object( $body ) ) {
			return $body;
		}

		if ( ! is_string( $body ) || '' === $body ) {
			return $body;
		}

		$decoded = json_decode( $body, true );

		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $body;
	}

	/**
	 * Obtiene un access token válido (caché o renovación).
	 *
	 * @return string|false
	 */
	public function obtener_token() {

		$access_token = get_option( 'meli_access_token' );
		$expires_at   = get_option( 'meli_token_expires' );

		// Si aún es válido (5 min margen)
		if ( $access_token && $expires_at && ( time() < $expires_at - 300 ) ) {
			return $access_token;
		}

		return $this->renovar_token();
	}

	/**
	 * Renueva el token mediante refresh_token.
	 *
	 * @return string|false
	 */
	private function renovar_token() {
		global $wpdb;

		$refresh_token = get_option( 'meli_refresh_token' );

		$config = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}wc_integraciones_settings
             WHERE client_name = 'mercadolibre'"
		);

		if ( ! $config || ! $refresh_token ) {
			error_log( '❌ Configuración o refresh_token faltante.' );
			return false;
		}

		$response = $this->request(
			'POST',
			'https://api.mercadolibre.com/oauth/token',
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $config->client_id,
					'client_secret' => $config->secret_key,
					'refresh_token' => $refresh_token,
				),
			),
			'oauth'
		);

		if ( is_wp_error( $response ) ) {
			error_log( '❌ Error HTTP renovando token.' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['access_token'] ) ) {
			error_log( '❌ Respuesta inválida al renovar token.' );
			return false;
		}

		update_option( 'meli_access_token', $body['access_token'] );
		update_option( 'meli_refresh_token', $body['refresh_token'] );
		update_option( 'meli_token_expires', time() + $body['expires_in'] );

		error_log( '✅ Token Mercado Libre renovado automáticamente.' );

		return $body['access_token'];
	}

	/**
	 * Actualiza el stock de un item o una variación en Mercado Libre.
	 *
	 * En modo dry-run no se ejecuta la escritura: solo se registra la intención.
	 *
	 * @param string $meli_item_id El ID del item en Mercado Libre (e.g., MLA12345678).
	 * @param int|null $variation_id El ID de la variación (opcional).
	 * @param int $new_stock La nueva cantidad disponible.
	 * @return array|bool Respuesta de la API, true/false, o array con dry_run.
	 */
	public function actualizar_stock( $meli_item_id, $variation_id, $new_stock ) {
		$url = "https://api.mercadolibre.com/items/{$meli_item_id}";
		if ( $variation_id ) {
			$url .= "/variations/{$variation_id}";
		}

		$body = array(
			'available_quantity' => $new_stock,
		);

		if ( self::is_dry_run() ) {
			Wc_Integraciones_Api_Logger::log(
				'stock_dry_run',
				'PUT',
				$url,
				null,
				0,
				$body,
				null,
				null,
				true
			);

			error_log( "🧪 Dry-run: se omite actualización de stock en ML (ID={$meli_item_id}, Variación=" . ( $variation_id ? $variation_id : 'N/A' ) . ", Nuevo Stock={$new_stock})" );

			return array(
				'dry_run'            => true,
				'meli_item_id'       => $meli_item_id,
				'variation_id'       => $variation_id,
				'available_quantity' => $new_stock,
			);
		}

		$access_token = $this->obtener_token();
		if ( ! $access_token ) {
			return false;
		}

		error_log( "🚀 Enviando actualización de stock a ML: ID={$meli_item_id}, Variación=" . ( $variation_id ? $variation_id : 'N/A' ) . ", Nuevo Stock={$new_stock}" );

		$response = $this->request(
			'PUT',
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			),
			'stock'
		);

		if ( is_wp_error( $response ) ) {
			error_log( '❌ Error al actualizar stock en ML: ' . $response->get_error_message() );
			return false;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			error_log( '❌ Error en respuesta de ML (Status ' . $status_code . '): ' . wp_json_encode( $response_body ) );
			return false;
		}

		error_log( '✅ Stock actualizado exitosamente en Mercado Libre.' );
		return $response_body;
	}
}

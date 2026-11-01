<?php

/**
 * Persistencia y consulta del log de llamadas a la API de MercadoLibre.
 *
 * @link       https://codejach.github.io/curriculo/
 * @since      1.0.10
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Registra las peticiones HTTP hacia MercadoLibre cuando el modo desarrollo
 * está activo, o cuando la petición falla.
 *
 * Los cuerpos se redactan antes de persistirse: nunca se guardan tokens,
 * secretos ni datos personales del comprador en claro.
 *
 * @since    1.0.10
 * @package  Wc_Integraciones
 */
class Wc_Integraciones_Api_Logger {

	/**
	 * Tamaño máximo por cuerpo antes de truncar (bytes).
	 *
	 * @since 1.0.10
	 */
	const MAX_BODY_BYTES = 65536;

	/**
	 * Retención en días cuando se guardan todos los cuerpos.
	 *
	 * @since 1.0.10
	 */
	const RETENTION_DAYS = 7;

	/**
	 * Retención en días cuando solo se guardan errores.
	 *
	 * @since 1.0.10
	 */
	const RETENTION_DAYS_ERRORS_ONLY = 30;

	/**
	 * Claves cuyo valor nunca debe persistirse.
	 *
	 * @var string[]
	 */
	private static $sensitive_keys = array(
		'access_token',
		'refresh_token',
		'client_secret',
		'secret_key',
		'authorization',
		'password',
		'token',
		'code',
	);

	/**
	 * Claves con datos personales que se enmascaran.
	 *
	 * @var string[]
	 */
	private static $pii_keys = array(
		'buyer',
		'buyer_alt',
		'billing_info',
		'receiver',
		'receiver_address',
		'receiver_phone',
		'shipping_address',
		'email',
		'phone',
		'full_name',
		'first_name',
		'last_name',
		'identification',
		'payer',
	);

	/**
	 * Nombre completo de la tabla.
	 *
	 * @since  1.0.10
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wc_integraciones_meli_api_log';
	}

	/**
	 * Persiste una llamada a la API.
	 *
	 * Con dev_mode apagado solo se guardan los errores. Los cuerpos se guardan
	 * cuando hay error o cuando el sub-flag de cuerpos completos está activo.
	 *
	 * @since 1.0.10
	 *
	 * @param string     $context   Contexto (oauth, stock, order, sync...).
	 * @param string     $method    Método HTTP.
	 * @param string     $endpoint  URL solicitada.
	 * @param int|null   $status    Código de respuesta; null si hubo WP_Error.
	 * @param int|null   $duration  Duración en milisegundos.
	 * @param mixed      $request   Cuerpo de la petición, ya decodificado.
	 * @param mixed      $response  Cuerpo de la respuesta, ya decodificado.
	 * @param string|null $error    Mensaje de error de transporte.
	 * @param bool       $force     Registrar aunque dev_mode esté apagado.
	 * @return bool
	 */
	public static function log( $context, $method, $endpoint, $status = null, $duration = null, $request = null, $response = null, $error = null, $force = false ) {
		$is_error = ( null !== $error ) || ( null !== $status && $status >= 400 );

		if ( ! $force && ! Wc_Integraciones_Meli::is_dev_mode() && ! $is_error ) {
			return false;
		}

		$store_bodies = $is_error || $force || Wc_Integraciones_Meli::full_bodies_enabled();

		$request_json  = null;
		$response_json = null;
		$truncated     = 0;

		if ( $store_bodies && null !== $request ) {
			$request_json = self::encode_body( $request );
		}

		if ( $store_bodies && null !== $response ) {
			list( $response_json, $truncated ) = self::encode_body_with_flag( $response );
		}

		global $wpdb;

		$user_id = get_current_user_id();

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'         => current_time( 'mysql' ),
				'context'            => substr( (string) $context, 0, 40 ),
				'method'             => substr( (string) $method, 0, 10 ),
				'endpoint'           => self::sanitize_endpoint( $endpoint ),
				'status_code'        => $status,
				'duration_ms'        => $duration,
				'request_json'       => $request_json,
				'response_json'      => $response_json,
				'response_truncated' => $truncated,
				'error_message'      => $error,
				'user_id'            => $user_id ? $user_id : null,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Consulta el log con filtros y paginación.
	 *
	 * @since 1.0.10
	 *
	 * @param array $filters  Claves admitidas: context, status (all|ok|error).
	 * @param int   $per_page Filas por página.
	 * @param int   $offset   Desplazamiento.
	 * @return array
	 */
	public static function get_logs( $filters = array(), $per_page = 50, $offset = 0 ) {
		global $wpdb;

		$table = self::table_name();
		$args  = self::build_where( $filters );

		$sql = "SELECT * FROM {$table}{$args['where']} ORDER BY id DESC LIMIT %d OFFSET %d";

		$params   = $args['params'];
		$params[] = max( 1, (int) $per_page );
		$params[] = max( 0, (int) $offset );

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Cuenta filas del log con los mismos filtros.
	 *
	 * @since 1.0.10
	 *
	 * @param array $filters Filtros.
	 * @return int
	 */
	public static function count_logs( $filters = array() ) {
		global $wpdb;

		$table = self::table_name();
		$args  = self::build_where( $filters );

		$sql = "SELECT COUNT(*) FROM {$table}{$args['where']}";

		if ( empty( $args['params'] ) ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args['params'] ) );
	}

	/**
	 * Elimina filas fuera de la ventana de retención.
	 *
	 * @since 1.0.10
	 *
	 * @return int|false Filas eliminadas o false en error.
	 */
	public static function purge() {
		global $wpdb;

		$days  = ( Wc_Integraciones_Meli::is_dev_mode() && Wc_Integraciones_Meli::full_bodies_enabled() )
			? self::RETENTION_DAYS
			: self::RETENTION_DAYS_ERRORS_ONLY;
		$table = self::table_name();

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB( NOW(), INTERVAL %d DAY )",
				$days
			)
		);
	}

	/**
	 * Vacía el log completo.
	 *
	 * @since 1.0.10
	 *
	 * @return int|false
	 */
	public static function clear_all() {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Contextos conocidos para el selector de filtros.
	 *
	 * @since  1.0.10
	 * @return string[]
	 */
	public static function contexts() {
		return array( 'oauth', 'stock', 'stock_dry_run', 'order', 'sync' );
	}

	/**
	 * Construye el WHERE y sus parámetros.
	 *
	 * @param array $filters Filtros.
	 * @return array{where:string,params:array}
	 */
	private static function build_where( $filters ) {
		$where  = array();
		$params = array();

		if ( ! empty( $filters['context'] ) ) {
			$where[]  = 'context = %s';
			$params[] = $filters['context'];
		}

		if ( ! empty( $filters['status'] ) ) {
			if ( 'error' === $filters['status'] ) {
				$where[] = '( status_code >= 400 OR error_message IS NOT NULL )';
			} elseif ( 'ok' === $filters['status'] ) {
				$where[] = 'status_code BETWEEN 200 AND 299';
			}
		}

		return array(
			'where'  => $where ? ' WHERE ' . implode( ' AND ', $where ) : '',
			'params' => $params,
		);
	}

	/**
	 * Redacta un payload antes de guardarlo.
	 *
	 * @since 1.0.10
	 *
	 * @param mixed $data   Payload decodificado.
	 * @param int   $depth  Profundidad actual.
	 * @return mixed
	 */
	private static function redact( $data, $depth = 0 ) {
		if ( $depth > 12 ) {
			return '***';
		}

		if ( is_object( $data ) ) {
			$data = (array) $data;
		}

		if ( ! is_array( $data ) ) {
			return $data;
		}

		$clean = array();

		foreach ( $data as $key => $value ) {
			$normalized = strtolower( (string) $key );

			if ( in_array( $normalized, self::$sensitive_keys, true ) ) {
				$clean[ $key ] = self::mask_value( $value );
				continue;
			}

			if ( in_array( $normalized, self::$pii_keys, true ) ) {
				$clean[ $key ] = '***';
				continue;
			}

			$clean[ $key ] = self::redact( $value, $depth + 1 );
		}

		return $clean;
	}

	/**
	 * Enmascara un valor sensible conservando extremos reconocibles.
	 *
	 * @param mixed $value Valor original.
	 * @return string
	 */
	private static function mask_value( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) < 12 ) {
			return '***';
		}

		return substr( $value, 0, 6 ) . str_repeat( '*', 8 ) . substr( $value, -4 );
	}

	/**
	 * Serializa un payload redactado.
	 *
	 * @param mixed $payload Payload.
	 * @return string
	 */
	private static function encode_body( $payload ) {
		$json = wp_json_encode( self::redact( $payload ) );

		return false === $json ? '' : $json;
	}

	/**
	 * Serializa y trunca un payload, reportando si hubo corte.
	 *
	 * @param mixed $payload Payload.
	 * @return array{0:string,1:int}
	 */
	private static function encode_body_with_flag( $payload ) {
		$json = self::encode_body( $payload );

		if ( strlen( $json ) <= self::MAX_BODY_BYTES ) {
			return array( $json, 0 );
		}

		$slice = function_exists( 'mb_strcut' )
			? mb_strcut( $json, 0, self::MAX_BODY_BYTES, 'UTF-8' )
			: substr( $json, 0, self::MAX_BODY_BYTES );

		return array( $slice . '... [truncado]', 1 );
	}

	/**
	 * Elimina credenciales de la URL registrada.
	 *
	 * @param string $url URL original.
	 * @return string
	 */
	private static function sanitize_endpoint( $url ) {
		$url   = (string) $url;
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return substr( $url, 0, 2000 );
		}

		$clean = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'];
		$clean .= isset( $parts['path'] ) ? $parts['path'] : '';

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );

			foreach ( array( 'access_token', 'token', 'code', 'client_secret' ) as $key ) {
				if ( isset( $query[ $key ] ) ) {
					$query[ $key ] = '***';
				}
			}

			$query_string = http_build_query( $query );
			if ( $query_string ) {
				$clean .= '?' . str_replace( '%2C', ',', $query_string );
			}
		}

		return $clean;
	}
}

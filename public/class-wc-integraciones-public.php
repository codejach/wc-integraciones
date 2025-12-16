<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://https://codejach.github.io/curriculo/
 * @since      1.0.0
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/public
 * @author     Alberto Chávez <axuan@protonmail.com>
 */
class Wc_Integraciones_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action('rest_api_init', [$this, 'register_meli_webhook']);
		add_action('rest_api_init', [$this, 'register_meli_webhook_async']);

		add_action('rest_api_init', [$this, 'register_sku_assignment_route']);

		// Registrar acción para el Action Scheduler
		add_action(
			'wc_integraciones_procesar_notificacion',
			[$this, 'procesar_notificacion'],
			10,
			1
		);
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wc_Integraciones_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wc_Integraciones_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wc-integraciones-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wc_Integraciones_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wc_Integraciones_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wc-integraciones-public.js', array( 'jquery' ), $this->version, false );

	}

	// Notifications Callback URL (webhook de Mercado Libre)
	public function register_meli_webhook() {
		register_rest_route('meli/v1', '/notifications', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_meli_webhook'],
			'permission_callback' => '__return_true',
		]);
	}

	public function handle_meli_webhook($request) {
		$data = $request->get_json_params();

		// Lanza procesamiento asíncrono (sin bloquear respuesta)
		$response = wp_remote_post(
			'http://wp_integraciones/wp-json/meli/v1/notifications/async',
			// 'https://www.ninavestuariosinfantiles.com/wp-json/meli/v1/notifications/async',
			[
				'blocking' => false, // importante: no esperar respuesta
				'headers' => ['Content-Type' => 'application/json'],
				'body'    => wp_json_encode($data),
			]
		);
		error_log('✅ Webhook principal devolviendo respuesta 200 OK');
		// Responder inmediatamente (dentro de 500 ms)
		return new WP_REST_Response(['status' => 'ok'], 200);
	}

	
	// Endpoint para procesamiento real (asíncrono)
	public function register_meli_webhook_async() {
		register_rest_route('meli/v1', '/notifications/async', [
			'methods' => 'POST',
			'callback' => [$this, 'process_meli_webhook_async'],
			'permission_callback' => '__return_true',
		]);
	}

	public function process_meli_webhook_async($request) {
		try {
			global $wpdb;
			$data = $request->get_json_params();

			if (isset($data['topic']) && $data['topic'] == 'orders_v2') {
				error_log('⏳ Recibida notificación orders_v2, se programará procesamiento asíncrono');

				// Omitir registro en caso de que ya exista una notificación igual pendiente en base al topic y resource
				$existeRegistro = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wc_integraciones_meli_notificaciones WHERE topic = %s AND resource = %s",
					$data['topic'],
					$data['resource']
				));
				if ($existeRegistro) {
					error_log('⚠️ Notificación orders_v2 ya existe, se omite registro duplicado.');
					return new WP_REST_Response(['status' => 'ok'], 200);
				}

				$table = $wpdb->prefix . 'wc_integraciones_meli_notificaciones';
				$wpdb->insert($table, [
					'topic'        => $data['topic'] ?? null,
					'resource'     => $data['resource'] ?? null,
					'user_id'      => $data['user_id'] ?? null,
					'raw_json'     => wp_json_encode($data),
					'status'       => 'pending',
					'attempts'     => 0
				]);
				
				$notificacion_id = $wpdb->insert_id;

				// Programar tarea asincrónica con Action Scheduler
				if (function_exists('as_enqueue_async_action')) {
					error_log("⏱️ Programando procesamiento asíncrono para notificación $notificacion_id");
					as_enqueue_async_action('wc_integraciones_procesar_notificacion', ['notificacion_id' => $notificacion_id]);
				} else {
					error_log('⚠️ Action Scheduler no disponible.');
				}
			}

			return new WP_REST_Response(['status' => 'processed'], 200);
		} catch (Exception $e) {
			error_log('❌ Error en procesamiento asíncrono de webhook: ' . $e->getMessage());
			return new WP_REST_Response(['status' => 'error', 'message' => $e->getMessage()], 500);
		}
	}
	

	// Ruta para asignar SKU a una publicación detalle
	public function register_sku_assignment_route() {
		register_rest_route('meli/v1', '/asignar-sku', [
			'methods' => 'POST',
			'callback' => [$this, 'assign_sku_to_publication'],
			'permission_callback' => '__return_true',
		]);
	}

	// Lógica para asignar SKU a una publicación detalle
	public function assign_sku_to_publication($request) {
		global $wpdb;

		$detalle_id = intval($request['detalle_id']);
		$sku = sanitize_text_field($request['sku']);

		$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';

		$updated = $wpdb->update(
			$table,
			['wc_sku' => $sku],
			['id' => $detalle_id],
			['%s'],
			['%d']
		);

		return new WP_REST_Response([
			'success' => (bool)$updated,
			'detalle_id' => $detalle_id,
			'sku' => $sku
		], 200);
	}

	/**
     * Acción asíncrona que procesa una notificación específica
     */
    public function procesar_notificacion($notificacion_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_integraciones_meli_notificaciones';

        $notificacion = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $notificacion_id));

        if (!$notificacion) {
            error_log("❌ Notificación $notificacion_id no encontrada");
            return;
        }

        $data = json_decode($notificacion->raw_json, true);

		error_log("⏳ Procesando notificación $notificacion_id ({$data['topic']})");

        try {
			// Busca una notificación $table a partir de data['topic'] y data['resource']
			$notificacion_existente = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM $table WHERE topic = %s AND resource = %s AND id < %d",
				$data['topic'],
				$data['resource'],
				$notificacion_id
			));

			if ($notificacion_existente) {
				// Marcar la notificación actual como 'skipped'
				$wpdb->update($table, [
					'status'   => 'skipped',
					'attempts' => $notificacion->attempts + 1,
				], ['id' => $notificacion_id]);

				error_log("⚠️ Notificación $notificacion_id omitida (ya existe una más reciente con mismo topic y resource)");
				return;
			}

			// Suma cuando es cancelada, resta cuando es nueva
			$procesamiento = $data['status'] === 'cancelled' ? -1 : 1;

			// Procesar según el topic
			$result_message = $this->procesar_order_v2($data, $procesamiento);

			// Marcar como procesada y almacena en result message el cuerpo del response
			$wpdb->update($table, [
				'status'   => 'done',
				'attempts' => $notificacion->attempts + 1,
				'processed_at' => current_time('mysql'),
				'result_message' => wp_json_encode($result_message),
			], ['id' => $notificacion_id]);

			error_log("✅ Procesada notificación $notificacion_id ({$data['topic']})");

        } catch (Exception $e) {
            // En caso de error, marcar como fallida
            $wpdb->update($table, [
                'status'   => 'error',
                'attempts' => $notificacion->attempts + 1,
            ], ['id' => $notificacion_id]);
            error_log("❌ Error procesando notificación $notificacion_id: " . $e->getMessage());
        }
    }
	
	/**
	 * Procesamiento de notificaciones tipo "order_v2"
	 * Actualiza el stock de WooCommerce según los productos vendidos en ML.
	 */
	private function procesar_order_v2($data, $procesamiento) {
		global $wpdb;

		// Ejemplo de resource: "/orders/2000013516363394"
		$resource = $data['resource'] ?? null;

		if (!$resource) {
			error_log('❌ No se proporcionó resource en la notificación.');
			return;
		}

		// Obtener token de MercadoLibre
		$access_token = get_option('meli_access_token');
		if (!$access_token) {
			error_log('❌ Token de acceso de MercadoLibre no configurado.');
			return;
		}

		// Configurar encabezados
		$headers = [
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		];

		// Llamada al endpoint de ML
		$url = "https://api.mercadolibre.com" . $resource;
		$response = wp_remote_get($url, ['headers' => $headers]);

		if (is_wp_error($response)) {
			error_log('❌ Error al obtener orden desde ML: ' . $response->get_error_message());
			return;
		}

		$order_data = json_decode(wp_remote_retrieve_body($response), true);

		error_log("🔍 Orden obtenida: " . wp_json_encode($order_data));

		if ($order_data['date_closed'] === null) {
			error_log('ℹ️ La orden no ha sido cerrada, no se procesará.');
			return;
		}

		if (empty($order_data['order_items'])) {
			error_log('⚠️ La orden no contiene order_items.');
			return;
		}

		foreach ($order_data['order_items'] as $item) {
			$user_product_id 	= $item['item']['user_product_id'] ?? null;
			$variation_id    	= $item['item']['variation_id'] ?? null;
			$quantity        	= intval($item['quantity'] ?? 0) * $procesamiento;
			$tags           	= $item['item']['tags'] ?? [];

			if (!$user_product_id || !$variation_id || $quantity <= 0 ) {
				error_log('⚠️ Datos incompletos en el item: ' . wp_json_encode($item));
				continue;
			}

			error_log("🔍 Procesando item: user_product_id={$user_product_id}, variation_id={$variation_id}, quantity={$quantity}");

			// Buscar SKU correspondiente en tu tabla personalizada y devolver id y wc_sku
			$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
			$detalle = $wpdb->get_row($wpdb->prepare(
				"SELECT id, wc_sku FROM $table WHERE user_product_id = %s AND variation_id = %s",
				$user_product_id,
				$variation_id
			));

			error_log("🔍 Detalle encontrado: " . wp_json_encode($detalle));

			if (!$detalle || empty($detalle->wc_sku)) {
				error_log("❌ No se encontró wc_sku para user_product_id={$user_product_id}, variation_id={$variation_id}");
				continue;
			}

			// Buscar producto en WooCommerce por SKU
			$product_id = wc_get_product_id_by_sku($detalle->wc_sku);

			if (!$product_id) {
				error_log("❌ No se encontró producto en WooCommerce con SKU {$detalle->wc_sku}");
				continue;
			}

			$product = wc_get_product($product_id);

			if (!$product || !$product->managing_stock()) {
				error_log("⚠️ El producto con SKU {$detalle->wc_sku} no gestiona inventario o no es válido.");
				continue;
			}

			// Restar la cantidad vendida (sin dejar en negativo)
			$current_stock = (int) $product->get_stock_quantity();
			$new_stock = max(0, $current_stock - $quantity);

			$product->set_stock_quantity($new_stock);
			$product->save();

			error_log("✅ Stock actualizado para SKU {$detalle->wc_sku}: {$current_stock} → {$new_stock} (venta de {$quantity} unidades)");
		}

		return $order_data;
	}

}

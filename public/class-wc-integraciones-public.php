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
	 * @access   private
	 * @var      string    $ngrok_url    The current ngrok URL of this plugin.
	 */
	private $ngrok_url;

    /**
     * Propiedad para inhibir la sincronización hacia Mercado Libre
     * Evita bucles infinitos cuando el stock se actualiza desde un webhook de ML.
     */
    public static $inhibir_sincronizacion_meli = false;

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

        // Acción para sincronizar stock hacia Mercado Libre (asíncrona)
        add_action(
            'wc_integraciones_sincronizar_stock_meli',
            [$this, 'sincronizar_stock_meli_handler'],
            10,
            1
        );

        // Hooks de WooCommerce para detectar cambios de stock
        add_action('woocommerce_updated_product_stock', [$this, 'handle_wc_stock_change'], 10, 1);
        add_action('woocommerce_product_object_updated_props', [$this, 'handle_wc_stock_props_change'], 10, 2);

		$this->ngrok_url = WC_Integraciones_Config::get('api_ngrok_url', '');
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

		// Case #3: Validación por IDs de Entorno
		$expected_user_id = WC_Integraciones_Config::get('meli_user_id');
		$expected_app_id  = WC_Integraciones_Config::get('meli_application_id');

		// Validar user_id (obligatorio en Mercado Libre)
		if (isset($data['user_id']) && !empty($expected_user_id) && (int)$data['user_id'] !== (int)$expected_user_id) {
			error_log('❌ Webhook rechazado: User ID no coincide con el entorno.');
			return new WP_REST_Response(['status' => 'unauthorized'], 401);
		}

		// Validar application_id (si viene en el payload y está configurado)
		if (isset($data['application_id']) && !empty($expected_app_id) && (int)$data['application_id'] !== (int)$expected_app_id) {
			error_log('❌ Webhook rechazado: Application ID no coincide.');
			return new WP_REST_Response(['status' => 'unauthorized'], 401);
		}

		if (WC_Integraciones_Config::is_prod()) {
			$webhook_url = "https://www.ninavestuariosinfantiles.com/wp-json/meli/v1/notifications/async";
		} else {
			$webhook_url = "http://wp_integraciones/wp-json/meli/v1/notifications/async";
		}

		// Lanza procesamiento asíncrono (sin bloquear respuesta)
		$response = wp_remote_post(
			$webhook_url,
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
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
		]);
	}

	// Lógica para asignar SKU a una publicación o detalle
	public function assign_sku_to_publication($request) {
		global $wpdb;

		$detalle_id = isset($request['detalle_id']) ? intval($request['detalle_id']) : 0;
		$publicacion_id = isset($request['publicacion_id']) ? intval($request['publicacion_id']) : 0;
		$sku = sanitize_text_field($request['sku']);

		if ($detalle_id > 0) {
			$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
			$updated = $wpdb->update($table, ['wc_sku' => $sku], ['id' => $detalle_id], ['%s'], ['%d']);
		} else if ($publicacion_id > 0) {
			$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
			$updated = $wpdb->update($table, ['wc_sku' => $sku], ['id' => $publicacion_id], ['%s'], ['%d']);
		} else {
			$updated = false;
		}

		return new WP_REST_Response([
			'success' => (bool)$updated,
			'detalle_id' => $detalle_id,
			'publicacion_id' => $publicacion_id,
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
			$result_process = $this->procesar_order_v2($data, $procesamiento);

			$status = $result_process['success'] ? 'done' : 'error';

			// Marcar como procesada y almacena en result message el cuerpo del response
			$wpdb->update($table, [
				'status'   => $status,
				'attempts' => $notificacion->attempts + 1,
				'processed_at' => current_time('mysql'),
				'result_message' => wp_json_encode($result_process),
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
			$message = '❌ No se proporcionó resource en la notificación.';
			error_log($message);
			return ['success' => true, 'message' => $message, 'response' => $data];
		}

		// Obtener token de MercadoLibre
		$meli = new WC_Integraciones_Meli();
		$access_token = $meli->obtener_token();

		// Configurar encabezados
		$headers = [
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		];

		// Llamada al endpoint de ML
		$url = "https://api.mercadolibre.com" . $resource;
		$response = wp_remote_get($url, ['headers' => $headers]);

		if (is_wp_error($response)) {
			$message = '❌ Error al obtener orden desde ML: ' . $response->get_error_message();
			error_log($message);
			return ['success' => false, 'message' => $message, 'response' => null];
		}

		$order_data = json_decode(wp_remote_retrieve_body($response), true);

		error_log("🔍 Orden obtenida: " . wp_json_encode($order_data));

		if ($order_data['date_closed'] === null) {
			$message = 'ℹ️ La orden no ha sido cerrada, no se procesará.';
			error_log($message);
			return ['success' => true, 'message' => $message, 'response' => $order_data];
		}

		if ($order_data['fulfilled'] === true) {
			$message = 'ℹ️ La orden ya ha sido cumplida, no se procesará.';
			error_log($message);
			return ['success' => true, 'message' => $message, 'response' => $order_data];
		}

		if (empty($order_data['order_items'])) {
			$message = '❌ La orden no contiene order_items.';
			error_log($message);
			return ['success' => false, 'message' => $message, 'response' => $order_data];
		}

		foreach ($order_data['order_items'] as $item) {
			$meli_item_id       = $item['item']['id'] ?? null;
			$user_product_id 	= $item['item']['user_product_id'] ?? null;
			$variation_id    	= $item['item']['variation_id'] ?? null;
			$quantity        	= intval($item['quantity'] ?? 0) * $procesamiento;
			$tags           	= $item['item']['tags'] ?? [];

			if (!$meli_item_id || $quantity <= 0 ) {
				error_log('⚠️ Datos incompletos en el item: ' . wp_json_encode($item));
				continue;
			}

			error_log("🔍 Procesando item: meli_item_id={$meli_item_id}, user_product_id={$user_product_id}, variation_id={$variation_id}, quantity={$quantity}");

			$wc_sku = null;

			if ($variation_id) {
				// Buscar SKU en variaciones
				$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
				$detalle = $wpdb->get_row($wpdb->prepare(
					"SELECT wc_sku FROM $table WHERE user_product_id = %s AND variation_id = %s",
					$user_product_id,
					$variation_id
				));
				$wc_sku = $detalle ? $detalle->wc_sku : null;
			} else {
				// Buscar SKU en publicaciones generales
				$table_pub = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
				$pub = $wpdb->get_row($wpdb->prepare(
					"SELECT wc_sku FROM $table_pub WHERE meli_item_id = %s",
					$meli_item_id
				));
				$wc_sku = $pub ? $pub->wc_sku : null;
			}

			error_log("🔍 SKU encontrado: " . ($wc_sku ? $wc_sku : 'ninguno'));

			if (empty($wc_sku)) {
				error_log("❌ No se encontró wc_sku para meli_item_id={$meli_item_id}, variation_id={$variation_id}");
				continue;
			}

			// Buscar producto en WooCommerce por SKU
			$product_id = wc_get_product_id_by_sku($wc_sku);

			if (!$product_id) {
				error_log("❌ No se encontró producto en WooCommerce con SKU {$wc_sku}");
				continue;
			}

			$product = wc_get_product($product_id);

			if (!$product || !$product->managing_stock()) {
				error_log("⚠️ El producto con SKU {$wc_sku} no gestiona inventario o no es válido.");
				continue;
			}

			// Restar la cantidad vendida (sin dejar en negativo)
			$current_stock = (int) $product->get_stock_quantity();
			$new_stock = max(0, $current_stock - $quantity);

			// Activar inhibición para evitar que el cambio en WC dispare una actualización de vuelta a ML
            self::$inhibir_sincronizacion_meli = true;

			$product->set_stock_quantity($new_stock);
            $product->save();

            // Registrar Log
            $this->log_inventory(
                $product_id,
                $wc_sku,
                'meli',
                $current_stock,
                $new_stock,
                1,
                "Venta de {$quantity} unidades desde Mercado Libre (Item: {$meli_item_id})"
            );

            // Desactivar inhibición
            self::$inhibir_sincronizacion_meli = false;

			error_log("✅ Stock actualizado para SKU {$wc_sku}: {$current_stock} → {$new_stock} (venta de {$quantity} unidades)");
		}

		return ['success' => true, 'message' => 'Procesamiento completado.', 'response' => $order_data];
	}

    /**
     * Handler para el hook 'woocommerce_updated_product_stock'
     */
    public function handle_wc_stock_change($product_id) {
        if (self::$inhibir_sincronizacion_meli) {
            error_log("🚫 Sincronización hacia ML inhibida (cambio originado en ML) para ID: $product_id");
            return;
        }
        $this->schedule_stock_sync($product_id);
    }

    /**
     * Handler para el hook 'woocommerce_product_object_updated_props'
     * Detecta cambios manuales en el objeto producto (incluyendo stock)
     */
    public function handle_wc_stock_props_change($product, $updated_props) {
        if (self::$inhibir_sincronizacion_meli) {
            return;
        }

        if (in_array('stock_quantity', $updated_props)) {
            $product_id = $product->get_id();
            error_log("📝 Cambio manual de stock detectado para ID: $product_id");
            $this->schedule_stock_sync($product_id);
        }
    }

    /**
     * Programa la tarea asíncrona en Action Scheduler
     */
    private function schedule_stock_sync($product_id) {
        if (function_exists('as_enqueue_async_action')) {
            error_log("⏱️ Programando sincronización de stock a ML para Producto ID: $product_id");
            as_enqueue_async_action('wc_integraciones_sincronizar_stock_meli', ['product_id' => $product_id], 'meli_sync');
        } else {
            error_log('⚠️ Action Scheduler no disponible para sincronización de stock.');
        }
    }

    /**
     * Callback del Action Scheduler para ejecutar la sincronización
     */
    public function sincronizar_stock_meli_handler($product_id) {
        global $wpdb;

        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("❌ Producto no encontrado para sincronización: $product_id");
            return;
        }

        $sku = $product->get_sku();
        if (empty($sku)) {
            error_log("ℹ️ Producto sin SKU, se ignora sincronización a ML: $product_id");
            return;
        }

        $new_stock = $product->get_stock_quantity();
        error_log("🔍 Sincronizando stock para SKU: $sku (Stock WC: $new_stock)");

        // Buscar en la tabla de detalles (variaciones) con JOIN a publicaciones para obtener meli_item_id
        $table_detalle = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
        $table_pub = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		
        $detalle = $wpdb->get_row($wpdb->prepare(
            "SELECT p.meli_item_id, d.variation_id, d.sync_stock_enabled 
             FROM $table_detalle d
             INNER JOIN $table_pub p ON d.publicacion_id = p.id
             WHERE d.wc_sku = %s",
            $sku
        ));

        $meli_item_id = null;
        $variation_id = null;
        $sync_enabled = 0;

        if ($detalle) {
            $meli_item_id = $detalle->meli_item_id;
            $variation_id = $detalle->variation_id;
            $sync_enabled = $detalle->sync_stock_enabled;
        } else {
            // Buscar en la tabla de publicaciones (productos simples)
            $pub = $wpdb->get_row($wpdb->prepare(
                "SELECT meli_item_id, sync_stock_enabled FROM $table_pub WHERE wc_sku = %s",
                $sku
            ));
            if ($pub) {
                $meli_item_id = $pub->meli_item_id;
                $sync_enabled = $pub->sync_stock_enabled;
            }
        }

        if (!$meli_item_id) {
            error_log("ℹ️ No se encontró vinculación en ML para SKU: $sku");
            return;
        }

        // Registrar log del intento (indicando si está habilitado o no)
        $this->log_inventory(
            $product_id,
            $sku,
            'wc',
            null,
            $new_stock,
            self::$inhibir_sincronizacion_meli ? 1 : 0,
            $sync_enabled ? "Sincronización hacia ML iniciada." : "Sincronización hacia ML ignorada (Desactivado por el usuario)."
        );

        if (!$sync_enabled) {
            error_log("🚫 Sincronización desactivada por el usuario para SKU: $sku");
            return;
        }

        $meli = new WC_Integraciones_Meli();
        $resultado = $meli->actualizar_stock($meli_item_id, $variation_id, $new_stock);

        if ($resultado) {
            error_log("✅ Sincronización exitosa WC -> ML para SKU: $sku");
        } else {
            error_log("❌ Falló la sincronización WC -> ML para SKU: $sku");
        }
    }

    /**
     * Helper para registrar cambios de inventario
     */
    private function log_inventory($product_id, $sku, $origin, $old_stock, $new_stock, $inhibir, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_integraciones_meli_log_inventario';
        $wpdb->insert($table, [
            'product_id' => $product_id,
            'sku' => $sku,
            'origin' => $origin,
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'inhibir_sincronizacion_meli' => $inhibir,
            'description' => $description,
            'created_at' => current_time('mysql')
        ]);
    }

}

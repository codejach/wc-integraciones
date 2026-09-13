<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://codejach.github.io/curriculo/
 * @since      1.0.0
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/admin
 * @author     Alberto Chávez <axuan@protonmail.com>
 */
class Wc_Integraciones_Admin {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

    	add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

		add_action('admin_post_guardar_meli_configuracion', [$this, 'guardar_meli_configuracion']);

		add_action('admin_post' . self::get_meli_auth_suffix() . '_meli_auth_callback', [$this, 'handle_meli_oauth_callback']);

		add_action('rest_api_init', [$this, 'register_sync_toggle_route']);

		$this->ngrok_url = WC_Integraciones_Config::get('api_ngrok_url', '');
	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wc-integraciones-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wc-integraciones-admin.js', array( 'jquery' ), $this->version, true );

		wp_localize_script( $this->plugin_name, 'wc_integraciones_admin', array(
			'rest_nonce' => wp_create_nonce( 'wp_rest' )
		));
	}

	/**
	 * Register the admin menu and pages.
	 * 
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		// Menú principal Integraciones
		add_menu_page(
			__('Integraciones', 'integraciones-woocommerce'),
			__('Integraciones', 'integraciones-woocommerce'),
			'manage_options',
			'integraciones-woocommerce',
			array($this, 'display_integraciones_page'),
			'dashicons-admin-generic', // Icono
			56 // posición
		);

		// Submenú MercadoLibre
		add_submenu_page(
			'integraciones-woocommerce',
			__('MercadoLibre', 'integraciones-woocommerce'),
			__('MercadoLibre', 'integraciones-woocommerce'),
			'manage_options',
			'integraciones-woocommerce-mercadolibre',
			array($this, 'display_mercadolibre_page')
		);
	}

	// Página principal Integraciones
	public function display_integraciones_page() {
		 include_once plugin_dir_path( __FILE__ ) . 'partials/view.php';
	}

	// Página MercadoLibre
	public function display_mercadolibre_page() {
		include_once plugin_dir_path( __FILE__ ) . 'partials/mercadolibre/view.php';
	}

	// Obtener tab activa
	private function get_active_tab($tab) {
		return (isset($_GET['tab']) && $_GET['tab'] === $tab) ? 'nav-tab-active' : '';
	}

	// Mostrar contenido según la pestaña activa
	private function display_tab_content() {
		$tab = isset($_GET['tab']) ? $_GET['tab'] : 'panel';
		switch ($tab) {
			case 'wc2meli':
				echo '<p>Sincronización WooCommerce → MercadoLibre</p>';
				break;
			case 'meli2wc':
            	$this->display_meli2wc();
				break;
			case 'registros':
				echo '<p>Historial de registros</p>';
				break;
			case 'log':
				$this->display_log();
				break;
			case 'configuracion':
            	$this->display_configuracion();
				break;
			default:
				echo '<p>Panel principal de MercadoLibre</p>';
				break;
		}
	}

	// display meli2wc view
	private function display_meli2wc() {
		global $wpdb;

		// Procesar sincronización si se presionó el botón
		if (isset($_POST['meli_sync_btn']) && isset($_POST['meli_sync_nonce']) && wp_verify_nonce($_POST['meli_sync_nonce'], 'meli_sync_action')) {
			$this->sync_meli_publicaciones();
		}

		// Obtener publicaciones desde la base de datos
		$table_pub = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		$table_det = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$table_attr = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';

		$publicaciones = $wpdb->get_results("
			SELECT 
				p.*, 
				d.id as detalle_id, 
				d.variation_id,

				COALESCE(d.price, p.price) as price,
				COALESCE(d.available_quantity, p.available_quantity) as available_quantity,
				COALESCE(d.sold_quantity, p.sold_quantity) as sold_quantity,
				COALESCE(d.wc_sku, p.wc_sku) as wc_sku,
				COALESCE(d.sync_stock_enabled, p.sync_stock_enabled) as sync_stock_enabled,

				d.user_product_id,
				p.logistic_type,
				p.family_id,
				p.family_name,
				p.model_type
			FROM $table_pub p
			LEFT JOIN $table_det d ON p.id = d.publicacion_id
			ORDER BY p.family_id, p.logistic_type, p.date_created DESC
		");

		// Obtener todos los atributos (para agruparlos después)
		$atributos = $wpdb->get_results("
			SELECT a.detalle_id, a.attribute_id, a.name, a.value_id, a.value_name, a.value_type
			FROM $table_attr a
			WHERE a.attribute_id != 'FABRIC_DESIGN'
		");

		// Obtener productos WooCommerce para asignar SKU
		$wc_products = $wpdb->get_results("
			SELECT p.ID, p.post_title, pm.meta_value AS sku
			FROM {$wpdb->prefix}posts p
			INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
			WHERE pm.meta_key = '_sku'
				AND pm.meta_value IS NOT NULL
				AND pm.meta_value <> ''
				AND p.post_type IN ('product', 'product_variation')
		");

		// Agrupar atributos por detalle_id para fácil acceso
		$atributos_por_detalle = [];
		foreach ($atributos as $attr) {
			$atributos_por_detalle[$attr->detalle_id][] = [
				'attribute_id' => $attr->attribute_id,
				'name' => $attr->name,
				'value_id' => $attr->value_id,
				'value_name' => $attr->value_name,
				'value_type' => $attr->value_type
			];
		}

		$grouped_publicaciones = [];
		foreach ($publicaciones as $row) {
			$id = $row->id;

			if (!isset($grouped_publicaciones[$id])) {
				$grouped_publicaciones[$id] = [
					'publicacion_id' => $row->id,
					'item_id' => $row->meli_item_id,
					'title' => $row->title,
					'thumbnail' => $row->thumbnail,
					'status' => $row->status,
					'logistic_type' => $row->logistic_type,
					'price' => $row->price,
					'available_quantity' => $row->available_quantity,
					'sold_quantity' => $row->sold_quantity,
					'wc_sku' => $row->wc_sku ?? '',
					'sync_stock_enabled' => $row->sync_stock_enabled,
					'family_id' => $row->family_id ?? null,
					'family_name' => $row->family_name ?? null,
					'model_type' => $row->model_type ?? 'legacy',
					'variations' => []
				];
			}

			// añadir variación (legacy) o detalle family (variation_id nulo).
			if ($row->variation_id || ($row->model_type === 'family' && $row->detalle_id)) {
				$grouped_publicaciones[$id]['variations'][] = [
					'variation_id' => $row->variation_id,
					'price' => $row->price,
					'available_quantity' => $row->available_quantity,
					'sold_quantity' => $row->sold_quantity,
					'user_product_id' => $row->user_product_id ?? '',
					'attributes' => $atributos_por_detalle[$row->detalle_id] ?? [],
					'detalle_id' => $row->detalle_id,
					'wc_sku' => $row->wc_sku ?? '',
					'sync_stock_enabled' => $row->sync_stock_enabled,
				];
			}
		}
		
		// Obtener SKUs asignados en WooCommerce
		$assigned_skus = [];
		foreach ($grouped_publicaciones as $pub) {
			if (empty($pub['variations']) && !empty($pub['wc_sku'])) {
				$assigned_skus[] = $pub['wc_sku'];
			}
			foreach ($pub['variations'] as $var) {
				if (!empty($var['wc_sku'])) {
					$assigned_skus[] = $var['wc_sku'];
				}
			}
		}

		// Incluir layout
		include plugin_dir_path(__FILE__) . 'partials/mercadolibre/meli2wc/view.php';

	}

	// Sincronizar publicaciones desde Mercado Libre
	private function sync_meli_publicaciones() {
		global $wpdb;
		$meli = new WC_Integraciones_Meli();

		$access_token = $meli->obtener_token();

		// Recuperar configuración
		$table_name = $wpdb->prefix . 'wc_integraciones_settings';
		$config = $wpdb->get_row("SELECT * FROM $table_name WHERE client_name = 'mercadolibre'");

		if (!$config || !$config->user_id) {
			echo '<p style="color:red;">No se encontró el user_id de MercadoLibre. Debes reconectar la cuenta.</p>';
			return;
		}

		$user_id = $config->user_id;

		// Obtener items activos
		$limit = 50;
		$offset = 0;
		$all_items = [];

		do {
			$response_items = wp_remote_get(
				"https://api.mercadolibre.com/users/{$user_id}/items/search?status=active&limit={$limit}&offset={$offset}",
				[
					'headers' => ['Authorization' => 'Bearer ' . $access_token]
				]
			);

			if (is_wp_error($response_items)) {
				echo '<div class="error"><p>Error al obtener publicaciones: ' . $response_items->get_error_message() . '</p></div>';
				return;
			}

			$items_list = json_decode(wp_remote_retrieve_body($response_items), true);

			if (empty($items_list['results'])) {
				break;
			}

			// Acumular resultados
			$all_items = array_merge($all_items, $items_list['results']);

			$total = $items_list['paging']['total'];

			$offset += $limit;

		} while ($offset < $total);

		if (empty($all_items)) {
			echo '<p>No se encontraron publicaciones activas.</p>';
			return;
		};

		// Obtener detalles de los items para detectar familias y asegurar que se sincronicen todos los miembros.
		$family_ids = [];
		$items_by_id = array_flip($all_items);

		$detail_chunks = array_chunk($all_items, 20);
		foreach ($detail_chunks as $chunk) {
			$ids = implode(',', $chunk);
			$url = "https://api.mercadolibre.com/items?ids={$ids}"
				. "&attributes=id,family_id";

			$response = wp_remote_get($url, [
				'headers' => ['Authorization' => 'Bearer ' . $access_token]
			]);

			if (is_wp_error($response)) {
				error_log('Error en multiget de familias: ' . $response->get_error_message());
				continue;
			}

			$details = json_decode(wp_remote_retrieve_body($response), true);
			foreach ($details as $entry) {
				if (!isset($entry['body']['family_id'])) {
					continue;
				}
				$family_id = $entry['body']['family_id'];
				if (!empty($family_id)) {
					$family_ids[$family_id] = true;
				}
			}
		}

		// Buscar todos los miembros de cada familia detectada.
		foreach (array_keys($family_ids) as $family_id) {
			$family_offset = 0;
			$family_limit = 50;
			do {
				$response_family = wp_remote_get(
					"https://api.mercadolibre.com/users/{$user_id}/items/search?search_type=scan&family_id={$family_id}&limit={$family_limit}&offset={$family_offset}",
					[
						'headers' => ['Authorization' => 'Bearer ' . $access_token]
					]
				);

				if (is_wp_error($response_family)) {
					error_log("Error obteniendo familia $family_id: " . $response_family->get_error_message());
					break;
				}

				$family_body = json_decode(wp_remote_retrieve_body($response_family), true);
				if (!empty($family_body['results']) && is_array($family_body['results'])) {
					foreach ($family_body['results'] as $family_item_id) {
						if (!isset($items_by_id[$family_item_id])) {
							$all_items[] = $family_item_id;
							$items_by_id[$family_item_id] = true;
						}
					}
				}

				$family_total = $family_body['paging']['total'] ?? 0;
				$family_offset += $family_limit;
			} while ($family_offset < $family_total);
		}

		$table_pub = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		$table_det = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$table_attrs = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';

		$chunks = array_chunk($all_items, 20);

		foreach ($chunks as $chunk) {
			$ids = implode(',', $chunk);

			$url = "https://api.mercadolibre.com/items?ids={$ids}"
				. "&attributes=id,title,seller_id,price,base_price,original_price,initial_quantity,available_quantity,sold_quantity,thumbnail,status,shipping,variations,family_id,family_name,user_product_id,attributes,tags";

			$response = wp_remote_get($url, [
				'headers' => ['Authorization' => 'Bearer ' . $access_token]
			]);

			if (is_wp_error($response)) {
				error_log('Error en multiget: ' . $response->get_error_message());
				continue;
			}

			$items = json_decode(wp_remote_retrieve_body($response), true);

			foreach ($items as $entry) {
				if (!isset($entry['body'])) {
					continue;
				}

				$item = $entry['body'];
				$item_id = $item['id'];

				if (isset($item['shipping']['logistic_type']) && $item['shipping']['logistic_type'] === 'fulfillment') {
					error_log("Omitiendo item ID: $item_id (logística fulfillment)");
					continue;
				}

				// Detectar modelo del item.
				$has_family = !empty($item['family_id']);
				$has_variations = !empty($item['variations']) && is_array($item['variations']);

				if ($has_family && !$has_variations) {
					$model_type = 'family';
				} elseif ($has_variations) {
					$model_type = 'legacy';
				} else {
					$model_type = 'simple';
				}

				// Guardar en Publicaciones
				$existing_id = $wpdb->get_var(
					$wpdb->prepare("SELECT Id FROM $table_pub WHERE meli_item_id = %s", $item_id)
				);

				$inserted = null;
				$pub_data = [
					'title'              => $item['title'],
					'seller_id'          => (int)$item['seller_id'],
					'price'              => (float)$item['price'],
					'base_price'         => (float)$item['base_price'],
					'original_price'     => isset($item['original_price']) ? (float)$item['original_price'] : null,
					'initial_quantity'   => (int)$item['initial_quantity'],
					'available_quantity' => (int)$item['available_quantity'],
					'sold_quantity'      => (int)$item['sold_quantity'],
					'thumbnail'          => $item['thumbnail'],
					'status'             => $item['status'],
					'logistic_type'      => $item['shipping']['logistic_type'],
					'family_id'          => $has_family ? (int)$item['family_id'] : null,
					'family_name'        => $has_family ? $item['family_name'] : null,
					'model_type'         => $model_type,
					'user_product_id'    => isset($item['user_product_id']) ? $item['user_product_id'] : null,
				];

				$pub_format = ['%s','%d','%f','%f','%f','%d','%d','%d','%s','%s','%s','%d','%s','%s','%s'];

				if ($existing_id) {
					$wpdb->update(
						$table_pub,
						$pub_data,
						['Id' => $existing_id],
						$pub_format,
						['%d']
					);
				} else {
					$inserted = $wpdb->insert(
						$table_pub,
						array_merge($pub_data, ['meli_item_id' => $item_id]),
						array_merge($pub_format, ['%s'])
					);
				}

				// Obtener id del registro insertado o actualizado
				$publicacion_id = $inserted ? $wpdb->insert_id : $existing_id;

				error_log("Procesando item ID: $item_id, modelo: $model_type, registro ID en BD: $publicacion_id");

				if ($model_type === 'legacy') {
					// Guardar variaciones
					foreach ($item['variations'] as $variation) {
						error_log('Procesando variación: ' . wp_json_encode($variation));

						$existing = $wpdb->get_var( $wpdb->prepare(
							"SELECT id FROM $table_det WHERE publicacion_id = %d AND variation_id = %s",
							$publicacion_id,
							$variation['id']
						));

						$data = [
							'price' => $variation['price'],
							'available_quantity' => $variation['available_quantity'],
							'sold_quantity' => $variation['sold_quantity'],
							'user_product_id' => isset($variation['user_product_id']) ? $variation['user_product_id'] : null,
						];

						$format = ['%f','%d','%d','%s'];

						if ($existing) {
							$wpdb->update($table_det, $data, ['id' => $existing], $format, ['%d']);
						} else {
							$wpdb->insert($table_det, array_merge($data, [
								'publicacion_id' => $publicacion_id,
								'variation_id' => $variation['id'],
								'wc_sku' => null,
							]), array_merge($format, ['%d','%s','%s']));
						}

						$detalle_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_det WHERE publicacion_id=%d AND variation_id=%s", $publicacion_id, $variation['id']));

						// Guardar atributos de variación
						if (!empty($variation['attribute_combinations']) && is_array($variation['attribute_combinations'])) {
							foreach ($variation['attribute_combinations'] as $attr) {
								$this->sync_guardar_atributo($table_attrs, $detalle_id, $attr);
							}
						}
					}
				} elseif ($model_type === 'family') {
					// Familia: un detalle ficticio sin variation_id para almacenar SKU/atributos.
					$existing = $wpdb->get_var( $wpdb->prepare(
						"SELECT id FROM $table_det WHERE publicacion_id = %d AND variation_id IS NULL",
						$publicacion_id
					));

					$data = [
						'price' => (float)$item['price'],
						'available_quantity' => (int)$item['available_quantity'],
						'sold_quantity' => (int)$item['sold_quantity'],
						'user_product_id' => isset($item['user_product_id']) ? $item['user_product_id'] : null,
					];
					$format = ['%f','%d','%d','%s'];

					if ($existing) {
						$wpdb->update($table_det, $data, ['id' => $existing], $format, ['%d']);
						$detalle_id = $existing;
					} else {
						$wpdb->insert($table_det, array_merge($data, [
							'publicacion_id' => $publicacion_id,
							'variation_id' => null,
							'wc_sku' => null,
						]), array_merge($format, ['%d','%s','%s']));
						$detalle_id = $wpdb->insert_id;
					}

					// Guardar atributos del item (modelo family).
					if (!empty($item['attributes']) && is_array($item['attributes'])) {
						foreach ($item['attributes'] as $attr) {
							if (isset($attr['id']) && $attr['id'] === 'FABRIC_DESIGN') {
								continue;
							}
							$this->sync_guardar_atributo($table_attrs, $detalle_id, $attr);
						}
					}
				}
			}
		}

		echo '<div class="notice notice-success is-dismissible"><p>Sincronización completada correctamente.</p></div>';
	}

	/**
	 * Guarda o actualiza un atributo de variación/item.
	 *
	 * @since    1.0.8
	 * @access   private
	 * @param string $table_attrs Nombre de la tabla de atributos.
	 * @param int    $detalle_id  ID del detalle.
	 * @param array  $attr        Datos del atributo.
	 */
	private function sync_guardar_atributo($table_attrs, $detalle_id, $attr) {
		global $wpdb;

		$attr_id       = $attr['id'] ?? null;
		$value_id      = $attr['value_id'] ?? null;
		$value_name    = $attr['value_name'] ?? null;

		if (!$attr_id || !$value_name) {
			return;
		}

		$existing_attr = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_attrs WHERE detalle_id = %d AND attribute_id = %s AND value_name = %s",
				$detalle_id,
				$attr_id,
				$value_name
			)
		);

		$attr_data = [
			'name'       => $attr['name'] ?? '',
			'value_name' => $value_name,
			'value_type' => $attr['value_type'] ?? null,
		];

		if ($existing_attr) {
			$wpdb->update(
				$table_attrs,
				$attr_data,
				['id' => $existing_attr],
				['%s','%s','%s'],
				['%d']
			);
		} else {
			$wpdb->insert(
				$table_attrs,
				[
					'detalle_id'   => $detalle_id,
					'attribute_id' => $attr_id,
					'name'         => $attr['name'] ?? '',
					'value_id'     => $value_id,
					'value_name'   => $value_name,
					'value_type'   => $attr['value_type'] ?? null,
				],
				['%d','%s','%s','%s','%s','%s']
			);
		}
	}

	// display configuración view
	private function display_configuracion() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_integraciones_settings';
		$config = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE client_name = %s",
			'mercadolibre'
		));

		$client_id  = $config ? $config->client_id : '';
		$secret_key = $config ? $config->secret_key : '';
		$dev_mode   = $config ? $config->dev_mode : 0;

		// Secret enmascarado
		$masked_secret = $secret_key 
			? str_repeat('*', max(0, strlen($secret_key) - 3)) . substr($secret_key, -3)
			: '';

		include_once plugin_dir_path( __FILE__ ) . 'partials/mercadolibre/configuration/view.php';
	}

	// Guardar configuración de MercadoLibre
	public function guardar_meli_configuracion() {
		if (!isset($_POST['meli_nonce']) || !wp_verify_nonce($_POST['meli_nonce'], 'guardar_meli_config')) {
			wp_die('Acceso no autorizado.');
		}

		if (!current_user_can('manage_options')) {
			wp_die('No tienes suficientes permisos para realizar esta acción.');
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_integraciones_settings';

		$client_id = sanitize_text_field($_POST['meli_client_id'] ?? '');
		$secret_input = sanitize_text_field($_POST['meli_secret_key'] ?? '');
		$dev_mode = isset($_POST['meli_dev_mode']) ? 1 : 0;

		// Recuperar registro actual
		$current = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE client_name = %s",
			'mercadolibre'
		));

		// Si secret_key no se modificó (ej. "***********XYZ")
		if ($current && preg_match('/^\*+.{3}$/', $secret_input)) {
			$secret_key = $current->secret_key;
		} else {
			$secret_key = $secret_input;
		}

		// Insertar o actualizar registro
		if ($current) {
			$result = $wpdb->update(
				$table_name,
				[
					'client_id'  => $client_id,
					'secret_key' => $secret_key,
					'dev_mode'   => $dev_mode,
				],
				['client_name' => 'mercadolibre'],
				['%s','%s','%d'],
				['%s']
			);
		} else {
			$result = $wpdb->insert(
				$table_name,
				[
					'client_name' => 'mercadolibre',
					'client_id'   => $client_id,
					'secret_key'  => $secret_key,
					'dev_mode'    => $dev_mode,
				],
				['%s','%s','%s','%d']
			);
		}

		// Mostrar mensaje de error
		if ($result === false) {
			wp_redirect(add_query_arg('configuracion_guardada', 'false', wp_get_referer()));
			exit;
		}

		// Redirigir con mensaje de éxito
    	wp_redirect(add_query_arg('configuracion_guardada', 'true', wp_get_referer()));
		exit;
	}

	// Callback OAuth Mercado Libre
	public function handle_meli_oauth_callback() {
		if (WC_Integraciones_Config::is_prod()) {
			$redirect_uri = admin_url('admin-post.php?action=meli_auth_callback');
			$scheme = 'https';
		} else {
			$redirect_uri = $this->ngrok_url . '/wp-admin/admin-post.php?action=meli_auth_callback';
			$scheme = 'http';
		}

		// Validar parámetro "code" recibido de MercadoLibre
		if (!isset($_GET['code'])) {
			wp_die('No se recibió el código de autorización de Mercado Libre.');
		}

		$code = sanitize_text_field($_GET['code']);

		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_integraciones_settings';
		$config = $wpdb->get_row("SELECT * FROM $table_name WHERE client_name = 'mercadolibre'");

		if (!$config) {
			wp_die('No se encontró la configuración de Mercado Libre.');
		}

		// Solicitar el token a Mercado Libre
		$response = wp_remote_post('https://api.mercadolibre.com/oauth/token', [
			'body' => [
				'grant_type' => 'authorization_code',
				'client_id' => $config->client_id,
				'client_secret' => $config->secret_key,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
			],
		]);

		if (is_wp_error($response)) {
			wp_die('Error al obtener el token: ' . $response->get_error_message());
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!isset($body['access_token'])) {
			wp_die('No se obtuvo el token de acceso.');
		}

		// Guardar tokens en la base de datos
		update_option('meli_access_token', $body['access_token']);
		update_option('meli_refresh_token', $body['refresh_token']);
		update_option('meli_token_expires', time() + $body['expires_in']);

		// Guardar el user_id en la base de datos
		$result = $wpdb->update(
			$table_name,
			['user_id' => $body['user_id']],
			['client_name' => 'mercadolibre'],
			['%d'],
			['%s']
		);
		// Verificar si la actualización fue exitosa
		if ($result === false) {
			wp_die('Error al guardar el user_id en la base de datos.');
		}

		// Redirigir al usuario de vuelta a tu pestaña de configuración
		wp_redirect(admin_url(
			'admin.php?page=integraciones-woocommerce-mercadolibre&tab=configuracion&guardado=true',
			$scheme
		));
		exit;
	}

	// Generar URL de autorización OAuth de Mercado Libre
	public function get_meli_auth_url() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_integraciones_settings';

		$config = $wpdb->get_row("SELECT * FROM $table_name WHERE client_name = 'mercadolibre'");

		if (!$config) {
			return '';
		}

		if (WC_Integraciones_Config::is_prod()) {
			$redirect_uri = urlencode(admin_url('admin-post.php?action=meli_auth_callback'));
		} else {
			$redirect_uri = $this->ngrok_url . '/wp-admin/admin-post.php?action=meli_auth_callback';
		}
		$client_id = $config->client_id;
		$meli_auth_url = "https://auth.mercadolibre.com.mx/authorization?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}";

		return $meli_auth_url;
	}

	public static function get_meli_auth_suffix() {
		return WC_Integraciones_Config::is_prod() ? '' : '_nopriv';
	}

	/**
	 * Registrar ruta para activar/desactivar sincronización de SKU
	 */
	public function register_sync_toggle_route() {
		register_rest_route('meli/v1', '/toggle-sync', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_toggle_sync'],
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
		]);
	}

	/**
	 * Callback para el toggle de sincronización
	 */
	public function handle_toggle_sync($request) {
		global $wpdb;
		$detalle_id = isset($request['detalle_id']) ? intval($request['detalle_id']) : 0;
		$publicacion_id = isset($request['publicacion_id']) ? intval($request['publicacion_id']) : 0;
		$enabled = isset($request['enabled']) ? intval($request['enabled']) : 0;

		if ($detalle_id > 0) {
			$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
			$updated = $wpdb->update($table, ['sync_stock_enabled' => $enabled], ['id' => $detalle_id], ['%d'], ['%d']);
		} else if ($publicacion_id > 0) {
			$table = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
			$updated = $wpdb->update($table, ['sync_stock_enabled' => $enabled], ['id' => $publicacion_id], ['%d'], ['%d']);
		} else {
			return new WP_REST_Response(['success' => false, 'message' => 'Faltan parámetros'], 400);
		}

		return new WP_REST_Response(['success' => (bool)$updated, 'enabled' => $enabled], 200);
	}

	/**
	 * Muestra la pestaña de logs
	 */
	private function display_log() {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_integraciones_meli_log_inventario';
		$logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");

		include_once plugin_dir_path(__FILE__) . 'partials/mercadolibre/log/view.php';
	}
}

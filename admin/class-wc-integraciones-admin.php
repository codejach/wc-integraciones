<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://codejach.github.io/curriculo/
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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

    	add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

		add_action('admin_post_guardar_meli_configuracion', [$this, 'guardar_meli_configuracion']);

		add_action('admin_post_meli_auth_callback', [$this, 'handle_meli_oauth_callback']);

		add_action('meli_refresh_token_cron', [$this, 'obtener_token_meli']);

		if (!wp_next_scheduled('meli_refresh_token_cron')) {
			wp_schedule_event(time(), 'hourly', 'meli_refresh_token_cron');
		}
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
				echo '<p>Logs del plugin</p>';
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

		error_log($this->obtener_token_meli());

		// Procesar sincronización si se presionó el botón
		if (isset($_POST['meli_sync_btn']) && isset($_POST['meli_sync_nonce']) && wp_verify_nonce($_POST['meli_sync_nonce'], 'meli_sync_action')) {
			$this->sync_meli_publicaciones();
		}

		// Obtener publicaciones desde la base de datos
		$table_pub = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		$table_det = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$table_attr = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';

		$publicaciones = $wpdb->get_results("
			SELECT p.*, d.id as detalle_id, d.variation_id, d.price as var_price, d.available_quantity, d.sold_quantity, d.user_product_id, d.wc_sku, p.logistic_type
			FROM $table_pub p
			LEFT JOIN $table_det d ON p.id = d.publicacion_id
			ORDER BY p.logistic_type, p.date_created DESC
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
					'item_id' => $row->meli_item_id,
					'title' => $row->title,
					'thumbnail' => $row->thumbnail,
					'status' => $row->status,
					'logistic_type' => $row->logistic_type,
					'variations' => []
				];
			}

			// añadir variación
			if ($row->variation_id) {
				$grouped_publicaciones[$id]['variations'][] = [
					'variation_id' => $row->variation_id,
					'price' => $row->var_price,
					'available_quantity' => $row->available_quantity,
					'sold_quantity' => $row->sold_quantity,
					'user_product_id' => $row->user_product_id ?? '',
					'attributes' => $atributos_por_detalle[$row->detalle_id] ?? [],
					'detalle_id' => $row->detalle_id,
					'wc_sku' => $row->wc_sku ?? '',
				];
			}
		}
		
		// Obtener SKUs asignados en WooCommerce
		$assigned_skus = [];
		foreach ($grouped_publicaciones as $pub) {
			foreach ($pub['variations'] as $var) {
				if (!empty($var['wc_sku'])) {
					$assigned_skus[] = $var['wc_sku'];
				}
			}
		}

		// imprime el token en el log de debug
		error_log('Access Token ML: ' . $this->obtener_token_meli());		

		// Incluir layout
		include plugin_dir_path(__FILE__) . 'partials/mercadolibre/meli2wc/view.php';

	}

	// Sincronizar publicaciones desde Mercado Libre
	private function sync_meli_publicaciones() {
		global $wpdb;
		$access_token = $this->obtener_token_meli();

		// Recuperar configuración
		$table_name = $wpdb->prefix . 'wc_integraciones_settings';
		$config = $wpdb->get_row("SELECT * FROM $table_name WHERE client_name = 'mercadolibre'");

		if (!$config || !$config->user_id) {
			echo '<p style="color:red;">No se encontró el user_id de MercadoLibre. Debes reconectar la cuenta.</p>';
			return;
		}

		$user_id = $config->user_id;

		// Obtener items activos
		$response_items = wp_remote_get("https://api.mercadolibre.com/users/{$user_id}/items/search?status=active", [
			'headers' => ['Authorization' => 'Bearer ' . $access_token]
		]);
		if (is_wp_error($response_items)) {
			echo '<div class="error"><p>Error al obtener publicaciones: ' . $response_items->get_error_message() . '</p></div>';
			return;
		}

		$items_list = json_decode(wp_remote_retrieve_body($response_items), true);
		if (empty($items_list['results'])) {
			echo '<p>No se encontraron publicaciones activas.</p>';
			return;
		};

		$table_pub = $wpdb->prefix . 'wc_integraciones_meli_publicaciones';
		$table_det = $wpdb->prefix . 'wc_integraciones_meli_publicaciones_detalle';
		$table_attrs = $wpdb->prefix . 'wc_integraciones_meli_variacion_atributos';

		foreach ($items_list['results'] as $item_id) {
			$response_detail = wp_remote_get("https://api.mercadolibre.com/items/{$item_id}", [
				'headers' => ['Authorization' => 'Bearer ' . $access_token]
			]);

			$item = json_decode(wp_remote_retrieve_body($response_detail), true);

			if ($item['shipping']['logistic_type'] === 'fulfillment') {
				error_log("Omitiendo item ID: $item_id (logística fulfillment)");
				continue;
			}

			// Guardar en Publicaciones
			$existing_id = $wpdb->get_var(
				$wpdb->prepare("SELECT Id FROM $table_pub WHERE meli_item_id = %s", $item_id)
			);

			$inserted = null;

			if ($existing_id) {
				$wpdb->update(
					$table_pub,
					[
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
					],
					['Id' => $existing_id],
					['%s','%d','%f','%f','%f','%d','%d','%d','%s','%s','%s'],
					['%d'] // formato del WHERE
				);
			} else {
				$inserted = $wpdb->insert(
					$table_pub,
					[
						'meli_item_id'       => $item_id,
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
					],
					['%s','%s','%d','%f','%f','%f','%d','%d','%d','%s','%s','%s'] // formatos de datos para insert
				);
			}

			// Obtener id del registro insertado o actualizado
			$publicacion_id = $inserted ? $wpdb->insert_id : $existing_id;

			error_log("Procesando item ID: $item_id, registro ID en BD: $publicacion_id");

			// Validar si hay variaciones
			if (!isset($item['variations']) || !is_array($item['variations'])) {
				error_log('No se encontraron variaciones para el item: ' . wp_json_encode($item));
				continue;
			}

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
				foreach ($variation['attribute_combinations'] as $attr) {
					$wpdb->insert(
						$table_attrs,
						[
							'detalle_id' => $detalle_id,
							'attribute_id' => $attr['id'],
							'name' => $attr['name'],
							'value_id' => $attr['value_id'],
							'value_name' => $attr['value_name'],
							'value_type' => $attr['value_type']
						],
						['%d','%s','%s','%s','%s','%s']
					);
				}
			}
		}

		echo '<div class="notice notice-success is-dismissible"><p>Sincronización completada correctamente.</p></div>';
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

	/**
	 * Devuelve un access_token válido de MercadoLibre.
	 * Si está expirado, se renueva automáticamente usando el refresh_token.
	 */
	private function obtener_token_meli() {
		global $wpdb;

		$access_token = get_option('meli_access_token');
		$refresh_token = get_option('meli_refresh_token');
		$expires_at = get_option('meli_token_expires');

		// Verificar si el token sigue siendo válido (5 min de margen)
		if ($access_token && $expires_at && (time() < $expires_at - 300)) {
			return $access_token;
		}

		// Si expiró, renovarlo
		$config = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_integraciones_settings WHERE client_name = 'mercadolibre'");

		if (!$config) {
			wp_redirect(add_query_arg('mensaje_error_renovar_token', 'configuracion', wp_get_referer()));
			error_log('❌ No se encontró configuración de Mercado Libre.');
			exit;
		}

		if (!$refresh_token) {
			wp_redirect(add_query_arg('mensaje_error_renovar_token', 'ausente', wp_get_referer()));
			error_log('❌ No se encontró refresh_token para renovar el token ML.');
			exit;
		}

		$response = wp_remote_post('https://api.mercadolibre.com/oauth/token', [
			'body' => [
				'grant_type'    => 'refresh_token',
				'client_id'     => $config->client_id,
				'client_secret' => $config->secret_key,
				'refresh_token' => $refresh_token,
			],
		]);

		if (is_wp_error($response)) {
			wp_redirect(add_query_arg('mensaje_error_renovar_token', 'renovar', wp_get_referer()));
			error_log('❌ Error al renovar token ML: ' . $response->get_error_message());
			exit;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!isset($body['access_token'])) {
			wp_redirect(add_query_arg('mensaje_error_renovar_token', 'acceso', wp_get_referer()));
			error_log('❌ No se obtuvo access_token al renovar: ' . wp_remote_retrieve_body($response));
			exit;
		}

		// Guardar nuevos tokens
		update_option('meli_access_token', $body['access_token']);
		update_option('meli_refresh_token', $body['refresh_token']);
		update_option('meli_token_expires', time() + $body['expires_in']);

		error_log('✅ Token de Mercado Libre renovado automáticamente.');

		return $body['access_token'];
	}


	// Callback OAuth Mercado Libre
	public function handle_meli_oauth_callback() {

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

		// Importante: debe coincidir con la Redirect URI registrada en Mercado Libre
		// $redirect_uri = 'https://ab08bc90788e.ngrok-free.app/wp-admin/admin-post.php?action=meli_auth_callback';
		$redirect_uri = admin_url('admin-post.php?action=meli_auth_callback');

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
		wp_redirect(admin_url('admin.php?page=integraciones-woocommerce-mercadolibre&tab=configuracion&guardado=true'));
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

		// $redirect_uri = 'https://ab08bc90788e.ngrok-free.app/wp-admin/admin-post.php?action=meli_auth_callback';
		$redirect_uri = urlencode(admin_url('admin-post.php?action=meli_auth_callback'));
		$client_id = $config->client_id;
		$meli_auth_url = "https://auth.mercadolibre.com.mx/authorization?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}";

		return $meli_auth_url;
	}
}

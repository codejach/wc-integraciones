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

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Wc_Integraciones
 * @subpackage Wc_Integraciones/includes
 * @author     Alberto Chávez <axuan@protonmail.com>
 */
class WC_Integraciones_Meli {

    public function obtener_token() {

        $access_token = get_option('meli_access_token');
        $expires_at   = get_option('meli_token_expires');

        // Si aún es válido (5 min margen)
        if ($access_token && $expires_at && (time() < $expires_at - 300)) {
            return $access_token;
        }

        return $this->renovar_token();
    }

    private function renovar_token() {
        global $wpdb;

        $refresh_token = get_option('meli_refresh_token');

        $config = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}wc_integraciones_settings 
             WHERE client_name = 'mercadolibre'"
        );

        if (!$config || !$refresh_token) {
            error_log('❌ Configuración o refresh_token faltante.');
            return false;
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
            error_log('❌ Error HTTP renovando token.');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['access_token'])) {
            error_log('❌ Respuesta inválida al renovar token.');
            return false;
        }

        update_option('meli_access_token', $body['access_token']);
        update_option('meli_refresh_token', $body['refresh_token']);
        update_option('meli_token_expires', time() + $body['expires_in']);

        error_log('✅ Token Mercado Libre renovado automáticamente.');

        return $body['access_token'];
    }

    /**
     * Actualiza el stock de un item o una variación en Mercado Libre.
     * 
     * @param string $meli_item_id El ID del item en Mercado Libre (e.g., MLA12345678).
     * @param int|null $variation_id El ID de la variación (opcional).
     * @param int $new_stock La nueva cantidad disponible.
     * @return array|bool Respuesta de la API o false en caso de error.
     */
    public function actualizar_stock($meli_item_id, $variation_id, $new_stock) {
        $access_token = $this->obtener_token();
        if (!$access_token) {
            return false;
        }

        $url = "https://api.mercadolibre.com/items/{$meli_item_id}";
        if ($variation_id) {
            $url .= "/variations/{$variation_id}";
        }

        $body = [
            'available_quantity' => $new_stock
        ];

        error_log("🚀 Enviando actualización de stock a ML: ID={$meli_item_id}, Variación=" . ($variation_id ?: "N/A") . ", Nuevo Stock={$new_stock}");

        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            error_log('❌ Error al actualizar stock en ML: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            error_log('❌ Error en respuesta de ML (Status ' . $status_code . '): ' . wp_json_encode($response_body));
            return false;
        }

        error_log('✅ Stock actualizado exitosamente en Mercado Libre.');
        return $response_body;
    }
}

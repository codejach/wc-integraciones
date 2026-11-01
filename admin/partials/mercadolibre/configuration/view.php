<?php
    if(isset($_GET['configuracion_guardada']))
    {
        if ($_GET['configuracion_guardada'] === 'true') {
            add_settings_error('meli_messages', 'meli_save_success', 'Configuración guardada correctamente.', 'updated');
        } else {
            add_settings_error('meli_messages', 'meli_save_info', 'Ajustes no modificados.', 'info');
        }
        settings_errors('meli_messages'); 
    }
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('guardar_meli_config', 'meli_nonce'); ?>
    <input type="hidden" name="action" value="guardar_meli_configuracion">

    <div class="meli-settings-card">
        <h2><span class="dashicons dashicons-admin-network"></span> Configuración MercadoLibre</h2>
        <p>Introduce tus credenciales para conectar WooCommerce con MercadoLibre.</p>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="meli_client_id">Cliente ID</label></th>
                <td><input type="text" name="meli_client_id" id="meli_client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="meli_secret_key">Secret Key</label></th>
                <td>
                    <input type="password"
                        name="meli_secret_key"
                        id="meli_secret_key"
                        value="<?php echo esc_attr($secret_key); ?>"
                        class="regular-text"
                        />
                    <button type="button" class="button" id="toggle_secret">👁 Mostrar</button>
                </td>
            </tr>
            <tr>
                <th scope="row">Modo Desarrollo</th>
                <td>
                    <label><input type="checkbox" name="meli_dev_mode" value="1" <?php checked($dev_mode, 1); ?> /> Activar</label>
                    <p class="description">Registra las llamadas a la API de MercadoLibre en la pestaña <strong>API</strong>.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Guardar cuerpos completos</th>
                <td>
                    <label><input type="checkbox" name="meli_dev_full_bodies" value="1" <?php checked($dev_full_bodies, 1); ?> /> Activar</label>
                    <p class="description">Guarda también las respuestas correctas (2xx). Sin esto solo se guardan los cuerpos de error.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Dry-run de escrituras</th>
                <td>
                    <label><input type="checkbox" name="meli_dev_dry_run" value="1" <?php checked($dev_dry_run, 1); ?> /> Activar</label>
                    <p class="description">Simula las actualizaciones de stock hacia MercadoLibre sin enviarlas.</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Guardar configuración'); ?>

        <?php 
        $token = get_option('meli_access_token');
        if ($token):
        ?>
            <p style="color:green;">✅ Conectado a Mercado Libre</p>
        <?php else: ?>
            <p style="color:red;">🔴 No conectado</p>
        <?php endif; ?>

        <?php if (method_exists($this, 'get_meli_auth_url')): ?>
            <?php $meli_auth_url = $this->get_meli_auth_url(); ?>
            <?php if ($meli_auth_url): ?>
                <div style="margin-top: 20px;">
                    <a href="<?php echo esc_url($meli_auth_url); ?>" class="button button-primary">
                        <span class="dashicons dashicons-admin-network"></span> Conectar con Mercado Libre
                    </a>
                </div>
            <?php else: ?>
                <p style="color: #a00;">⚠️ Debes guardar el <strong>Client ID</strong> y <strong>Secret Key</strong> antes de conectar con Mercado Libre.</p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</form>
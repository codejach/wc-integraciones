<?php
/**
 * Layout para la pestaña "Meli a WC"
 * Recibe:
 *   - $publicaciones : array con los registros de la base de datos
 */

    if(isset($_GET['mensaje_error_renovar_token']) && $_GET['mensaje_error_renovar_token'] === 'renovar') {
        add_settings_error('meli_messages', 'meli_save_error', 'Error al renovar el token de Mercado Libre. Por favor, reconecta la cuenta.', 'error');
    } elseif (isset($_GET['mensaje_error_renovar_token']) && $_GET['mensaje_error_renovar_token'] === 'acceso') {
        add_settings_error('meli_messages', 'meli_save_error', 'No se pudo obtener un nuevo token de acceso de Mercado Libre. Por favor, reconecta la cuenta.', 'error');
    } elseif (isset($_GET['mensaje_error_renovar_token']) && $_GET['mensaje_error_renovar_token'] === 'ausente') {
        add_settings_error('meli_messages', 'meli_save_error', 'No se encontró el token de renovación. Por favor, configura la cuenta de Mercado Libre.', 'error');
    } elseif (isset($_GET['mensaje_error_renovar_token']) && $_GET['mensaje_error_renovar_token'] === 'configuracion') {
        add_settings_error('meli_messages', 'meli_save_error', 'No se encontró la configuración de Mercado Libre. Por favor, reconfigura la cuenta.', 'error');
    }
?>

<h2>Publicaciones Mercado Libre</h2>

<!-- Botón de sincronización -->
<form method="post">
    <?php wp_nonce_field('meli_sync_action', 'meli_sync_nonce'); ?>
    <input type="submit" name="meli_sync_btn" class="button button-primary" value="Sincronizar desde Mercado Libre">
</form>

<?php if (empty($grouped_publicaciones)): ?>
    <p>No hay publicaciones sincronizadas.</p>
<?php else: ?>
    <table class="widefat striped" style="margin-top:20px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Miniatura</th>
                <th>Estatus</th>
                <th>Variaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grouped_publicaciones as $pub): ?>
            <tr style="background-color: <?php echo ($pub['logistic_type'] === 'fulfillment') ? '#f0f0f0' : '#ffffff'; ?>">
                <td><?php echo esc_html($pub['item_id']); ?></td>
                <td><?php echo esc_html($pub['title']); ?></td>
                <td>
                    <?php if ($pub['thumbnail']): ?>
                        <img src="<?php echo esc_url($pub['thumbnail']); ?>" alt="" style="width:50px;height:auto;">
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html($pub['status']); ?></td>
                <td>
                    <?php if (!empty($pub['variations'])): ?>
                        <table style="width:100%; border:1px solid #ccc; margin:5px 0;">
                            <thead>
                                <tr>
                                    <th>Variation ID</th>
                                    <th>Precio</th>
                                    <th>Stock disponible</th>
                                    <th>Vendidos</th>
                                    <th>User Product ID</th>
                                    <th>Atributos</th>
                                    <th>SKU en Tienda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pub['variations'] as $var): ?>
                                <tr>
                                    <td><?php echo esc_html($var['variation_id']); ?></td>
                                    <td><?php echo esc_html($var['price']); ?></td>
                                    <td><?php echo esc_html($var['available_quantity']); ?></td>
                                    <td><?php echo esc_html($var['sold_quantity']); ?></td>
                                    <td><?php echo esc_html($var['user_product_id']); ?></td>
                                    <td>
                                        <?php if (!empty($var['attributes'])): ?>
                                            <ul style="margin:0; padding-left:15px;">
                                                <?php foreach ($var['attributes'] as $attr): ?>
                                                    <li>
                                                        <strong><?php echo esc_html($attr['name']); ?>:</strong>
                                                        <?php echo esc_html($attr['value_name']); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <em>Sin atributos</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pub['logistic_type'] === 'fulfillment'): ?>
                                            <em>No aplicable (logística fulfillment)</em>
                                        <?php else: ?>
                                            <select class="sku-selector" style="width:70%;" data-detalle-id="<?php echo $var['detalle_id']; ?>">
                                                <option value="">-- Seleccionar SKU --</option>
                                                <?php foreach ($wc_products as $p): ?>
                                                    <option value="<?php echo esc_attr($p->sku); ?>"
                                                        <?php echo in_array($p->sku, $assigned_skus) && $var['wc_sku'] !== $p->sku ? 'disabled' : ''; ?>
                                                        <?php selected($p->sku, $var['wc_sku']); ?>>
                                                        <?php echo esc_html($p->sku . ' — ' . $p->post_title); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="cancel-btn button" style="display:none;">Cancelar</button>
                                            <span class="save-timer" style="margin-left:5px;color:#666;display:block;"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No hay variaciones</p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

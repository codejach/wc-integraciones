<?php
/**
 * Vista para la pestaña de Log de Inventario
 */
?>

<h2>Log de Cambios en Inventario</h2>

<table class="widefat striped" style="margin-top:20px;">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>SKU</th>
            <th>Origen</th>
            <th>Stock Anterior</th>
            <th>Stock Nuevo</th>
            <th>Inhibido (Sinc Meli)</th>
            <th>Descripción</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="7">No hay registros de inventario.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td><strong><?php echo esc_html($log->sku); ?></strong></td>
                    <td>
                        <?php if ($log->origin === 'meli'): ?>
                            <span class="dashicons dashicons-cart" title="Mercado Libre"></span> ML
                        <?php else: ?>
                            <span class="dashicons dashicons-admin-home" title="WooCommerce"></span> WC
                        <?php endif; ?>
                    </td>
                    <td><?php echo ($log->old_stock !== null) ? esc_html($log->old_stock) : '-'; ?></td>
                    <td><?php echo esc_html($log->new_stock); ?></td>
                    <td>
                        <?php if ($log->inhibir_sincronizacion_meli): ?>
                            <span style="color:red; font-weight:bold;">SÍ</span>
                        <?php else: ?>
                            <span style="color:green;">NO</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($log->description); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php
/**
 * Vista de la pestaña API: estado del token y log de llamadas a MercadoLibre.
 *
 * @since 1.0.10
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$dev_settings  = Wc_Integraciones_Meli::get_dev_settings();
$has_token     = '' !== $token;
$expires_in    = $expires_at > 0 ? ( $expires_at - time() ) : 0;
$dev_flags_url = admin_url( 'admin.php?page=integraciones-woocommerce-mercadolibre&tab=configuracion' );

$human_expiry = 'sin fecha';
if ( $expires_at > 0 ) {
	if ( $expires_in > 0 ) {
		$hours        = (int) floor( $expires_in / 3600 );
		$minutes      = (int) floor( ( $expires_in % 3600 ) / 60 );
		$human_expiry = sprintf( '%dh %02dm', $hours, $minutes );
	} else {
		$human_expiry = 'expirado';
	}
}

$render_json = function ( $json ) {
	if ( empty( $json ) ) {
		return '';
	}

	$decoded = json_decode( $json, true );

	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return $json;
	}

	$pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	return false === $pretty ? $json : $pretty;
};

$status_label = array(
	'ok'    => 'Correctas (2xx)',
	'error' => 'Errores (4xx/5xx)',
	'all'   => 'Todas',
);

$base_url = admin_url( 'admin.php?page=integraciones-woocommerce-mercadolibre&tab=api' );
?>

<h2>API de MercadoLibre</h2>

<?php if ( isset( $_GET['api_log_cleared'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p>Log de API vaciado.</p></div>
<?php endif; ?>

<div class="meli-settings-card" style="margin-bottom:20px;">
	<h3 style="margin-top:0;"><span class="dashicons dashicons-lock"></span> Token de acceso</h3>

	<table class="form-table">
		<tr>
			<th scope="row">Estado</th>
			<td>
				<?php if ( $has_token ) : ?>
					<span style="color:green;font-weight:bold;">Conectado</span>
					<?php if ( $expires_in > 0 ) : ?>
						<span class="description">(expira en <?php echo esc_html( $human_expiry ); ?>)</span>
					<?php else : ?>
						<span style="color:#a00;font-weight:bold;">(token expirado, se renovará en la próxima llamada)</span>
					<?php endif; ?>
				<?php else : ?>
					<span style="color:#a00;font-weight:bold;">Sin token</span>
					<span class="description">Conecta MercadoLibre desde <a href="<?php echo esc_url( $dev_flags_url ); ?>">Configuración</a>.</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $has_token ) : ?>
			<tr>
				<th scope="row">Token</th>
				<td>
					<code id="meli-token-value" data-masked="<?php echo esc_attr( $masked_token ); ?>"><?php echo esc_html( $masked_token ); ?></code>
					<button type="button" class="button" id="meli-reveal-token">Revelar</button>
					<p class="description">Se oculta automáticamente a los 30 segundos.</p>
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<th scope="row">Flags</th>
			<td class="description">
				Modo desarrollo: <strong><?php echo $dev_settings['dev_mode'] ? 'ON' : 'OFF'; ?></strong> ·
				Cuerpos completos: <strong><?php echo $dev_settings['dev_full_bodies'] ? 'ON' : 'OFF'; ?></strong> ·
				Dry-run: <strong><?php echo $dev_settings['dev_dry_run'] ? 'ON' : 'OFF'; ?></strong>
				<br />
				<a href="<?php echo esc_url( $dev_flags_url ); ?>">Cambiar en Configuración</a>
			</td>
		</tr>
	</table>
</div>

<h3>Llamadas registradas</h3>
<p class="description">
	Se guardan los errores siempre; con modo desarrollo activo se registran todas las llamadas.
	Retención: <?php echo esc_html( Wc_Integraciones_Api_Logger::RETENTION_DAYS ); ?> días con cuerpos completos,
	<?php echo esc_html( Wc_Integraciones_Api_Logger::RETENTION_DAYS_ERRORS_ONLY ); ?> días solo errores.
</p>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:12px 0;">
	<input type="hidden" name="page" value="integraciones-woocommerce-mercadolibre" />
	<input type="hidden" name="tab" value="api" />
	<select name="context">
		<option value="">Todos los contextos</option>
		<?php foreach ( Wc_Integraciones_Api_Logger::contexts() as $ctx ) : ?>
			<option value="<?php echo esc_attr( $ctx ); ?>" <?php selected( $context, $ctx ); ?>><?php echo esc_html( $ctx ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="status">
		<?php foreach ( $status_label as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="button">Filtrar</button>
	<a class="button" href="<?php echo esc_url( $base_url ); ?>">Limpiar filtros</a>

	<span style="float:right;">
		<?php echo esc_html( sprintf( '%d registros', $total ) ); ?>
	</span>
</form>

<table class="widefat striped">
	<thead>
		<tr>
			<th style="width:150px;">Fecha</th>
			<th style="width:100px;">Contexto</th>
			<th style="width:70px;">Método</th>
			<th>Endpoint</th>
			<th style="width:70px;">Status</th>
			<th style="width:70px;">ms</th>
			<th style="width:80px;">Usuario</th>
			<th style="width:120px;">Detalle</th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $logs ) ) : ?>
			<tr>
				<td colspan="8">No hay llamadas registradas.</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $logs as $log ) : ?>
				<?php
				$status_code = null !== $log->status_code ? (int) $log->status_code : null;
				$status_color = '#666';
				if ( null !== $status_code ) {
					if ( $status_code >= 500 ) {
						$status_color = '#a00';
					} elseif ( $status_code >= 400 ) {
						$status_color = '#b26b00';
					} else {
						$status_color = '#1a7f37';
					}
				} elseif ( ! empty( $log->error_message ) ) {
					$status_color = '#a00';
				}
				?>
				<tr>
					<td><?php echo esc_html( $log->created_at ); ?></td>
					<td><?php echo esc_html( $log->context ); ?></td>
					<td><?php echo esc_html( $log->method ); ?></td>
					<td style="word-break:break-all;">
						<?php echo esc_html( (string) $log->endpoint ); ?>
						<?php if ( ! empty( $log->error_message ) ) : ?>
							<br /><span style="color:#a00;"><?php echo esc_html( $log->error_message ); ?></span>
						<?php endif; ?>
					</td>
					<td style="color:<?php echo esc_attr( $status_color ); ?>;font-weight:bold;">
						<?php echo null !== $status_code ? esc_html( $status_code ) : '—'; ?>
					</td>
					<td><?php echo null !== $log->duration_ms ? esc_html( $log->duration_ms ) : '—'; ?></td>
					<td><?php echo ! empty( $log->user_id ) ? esc_html( $log->user_id ) : 'sistema'; ?></td>
					<td>
						<?php if ( ! empty( $log->response_json ) || ! empty( $log->request_json ) ) : ?>
							<details>
								<summary style="cursor:pointer;">Ver JSON</summary>
								<?php if ( ! empty( $log->request_json ) ) : ?>
									<strong>Request</strong>
									<pre style="max-height:300px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html( $render_json( $log->request_json ) ); ?></pre>
								<?php endif; ?>
								<?php if ( ! empty( $log->response_json ) ) : ?>
									<strong>Response<?php echo $log->response_truncated ? ' (truncado)' : ''; ?></strong>
									<pre style="max-height:300px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html( $render_json( $log->response_json ) ); ?></pre>
								<?php endif; ?>
							</details>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<?php
$total_pages = (int) ceil( $total / $per_page );

if ( $total_pages > 1 ) {
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo paginate_links(
		array(
			'base'    => add_query_arg( 'paged', '%#%' ),
			'format'  => '',
			'current' => $paged,
			'total'   => $total_pages,
		)
	);
	echo '</div></div>';
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;"
	onsubmit="return confirm('¿Vaciar todo el log de llamadas a la API?');">
	<?php wp_nonce_field( 'clear_api_log', 'api_log_nonce' ); ?>
	<input type="hidden" name="action" value="wc_integraciones_clear_api_log" />
	<button type="submit" class="button button-secondary">Vaciar log</button>
</form>

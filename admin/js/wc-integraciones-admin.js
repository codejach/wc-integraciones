(function ($) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(document).ready(function () {
		const $toggleBtn = $('#toggle_secret');
		const $secretField = $('#meli_secret_key');

		if ($toggleBtn.length && $secretField.length) {
			$toggleBtn.on('click', function () {
				const isPassword = $secretField.attr('type') === 'password';
				$secretField.attr('type', isPassword ? 'text' : 'password');
				$(this).text(isPassword ? '🙈 Ocultar' : '👁 Mostrar');
			});
		}

		// Manejo del guardado de SKU con temporizador y opción de cancelar
		document.querySelectorAll('.sku-selector').forEach(function (select) {
			select.addEventListener('change', function () {
				const detalleId = this.dataset.detalleId || null;
				const pubId = this.dataset.publicacionId || null;
				const sku = this.value;
				const row = this.closest('tr') || this.closest('div');

				let timerSpan = row.querySelector('.save-timer');
				let cancelBtn = row.querySelector('.cancel-btn');
				let counter = 5;

				timerSpan.textContent = `Guardando en ${counter}s...`;
				cancelBtn.style.display = 'inline';

				const countdown = setInterval(() => {
					counter--;
					if (counter <= 0) {
						clearInterval(countdown);
						cancelBtn.style.display = 'none';
						timerSpan.textContent = 'Guardando...';

						fetch('/wp-json/meli/v1/asignar-sku', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': wc_integraciones_admin.rest_nonce
							},
							body: JSON.stringify({ detalle_id: detalleId, publicacion_id: pubId, sku })
						}).then(r => r.json()).then(data => {
							timerSpan.textContent = data.success ? '✅ Guardado' : '❌ Error';
						})
							.catch(() => {
								timerSpan.textContent = '❌ Error. Inténtalo de nuevo.';
							});
					} else {
						timerSpan.textContent = `Guardando en ${counter}s...`;
					}
				}, 1000);

				cancelBtn.onclick = () => {
					clearInterval(countdown);
					cancelBtn.style.display = 'none';
					timerSpan.textContent = '⏸ Cancelado';
				};
			});
		});

		// Toggle de sincronización de stock hacia Mercado Libre
		document.querySelectorAll('.toggle-sync-btn').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();

				const detalleId = this.dataset.detalleId || null;
				const publicacionId = this.dataset.publicacionId || null;
				const currentEnabled = parseInt(this.dataset.enabled, 10);
				const newEnabled = currentEnabled ? 0 : 1;

				btn.disabled = true;
				btn.textContent = 'Guardando...';

				fetch('/wp-json/meli/v1/toggle-sync', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': wc_integraciones_admin.rest_nonce
					},
					body: JSON.stringify({
						detalle_id: detalleId,
						publicacion_id: publicacionId,
						enabled: newEnabled
					})
				})
					.then(r => r.json())
					.then(data => {
						if (data.success) {
							btn.dataset.enabled = newEnabled;
							btn.textContent = newEnabled ? 'Sincronización: ON' : 'Sincronización: OFF';
							if (newEnabled) {
								btn.classList.add('button-primary');
							} else {
								btn.classList.remove('button-primary');
							}
						} else {
							btn.textContent = '❌ Error al guardar';
						}
					})
					.catch(() => {
						btn.textContent = '❌ Error de red';
					})
					.finally(() => {
						btn.disabled = false;
					});
			});
		});

		// Revelar el token de Mercado Libre (pestaña API)
		const $revealBtn = $('#meli-reveal-token');
		const $tokenValue = $('#meli-token-value');

		if ($revealBtn.length && $tokenValue.length) {
			let hideTimer = null;
			let isRevealed = false;

			const hideToken = function () {
				$tokenValue.text($tokenValue.data('masked'));
				$revealBtn.text('Revelar');
				isRevealed = false;
				hideTimer = null;
			};

			$revealBtn.on('click', function () {
				if (isRevealed) {
					clearTimeout(hideTimer);
					hideToken();
					return;
				}

				$revealBtn.prop('disabled', true).text('Cargando...');

				fetch('/wp-json/meli/v1/reveal-token', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': wc_integraciones_admin.rest_nonce
					}
				})
					.then(r => r.json())
					.then(data => {
						if (data && data.success && data.token) {
							$tokenValue.text(data.token);
							$revealBtn.text('Ocultar');
							isRevealed = true;
							hideTimer = setTimeout(hideToken, 30000);
						} else {
							$revealBtn.text('❌ No disponible');
						}
					})
					.catch(() => {
						$revealBtn.text('❌ Error de red');
					})
					.finally(() => {
						$revealBtn.prop('disabled', false);
					});
			});
		}

	});

})(jQuery);